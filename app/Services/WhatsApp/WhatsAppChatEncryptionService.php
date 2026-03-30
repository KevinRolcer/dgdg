<?php

namespace App\Services\WhatsApp;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cifrado por chat: DEK (32 bytes) envuelta con KEK.
 * Payload de archivo: magic WA1 + iv(12) + ciphertext + tag(16) AES-256-GCM.
 */
final class WhatsAppChatEncryptionService
{
    private const MAGIC = 'WA1';

    public function deriveKek(): string
    {
        $b64 = (string) config('whatsapp_chats.master_key_base64', '');
        if ($b64 !== '') {
            $raw = base64_decode($b64, true);
            if ($raw !== false && strlen($raw) === 32) {
                return $raw;
            }
        }

        return hash('sha256', (string) config('app.key').'|whatsapp-chats-kek-v1', true);
    }

    public function generateDek(): string
    {
        return random_bytes(32);
    }

    public function wrapDek(string $dek): string
    {
        $sealed = $this->encryptWithKey($dek, $this->deriveKek());

        return base64_encode($sealed);
    }

    public function unwrapDek(string $wrappedBase64): string
    {
        $bin = base64_decode($wrappedBase64, true);
        if ($bin === false || $bin === '') {
            throw new RuntimeException('DEK inválida.');
        }

        return $this->decryptWithKey($bin, $this->deriveKek());
    }

    public function encryptBytes(string $plain, string $dek): string
    {
        return $this->encryptWithKey($plain, $dek);
    }

    public function decryptBytes(string $blob, string $dek): string
    {
        return $this->decryptWithKey($blob, $dek);
    }

    public function isEncryptedBlob(string $maybe): bool
    {
        return str_starts_with($maybe, self::MAGIC);
    }

    /**
     * Cifra contenido de un archivo en disco (sobrescribe).
     */
    public function encryptDiskFile(Filesystem $disk, string $relativePath, string $dek): void
    {
        $relativePath = $this->normalizeRel($relativePath);
        if ($relativePath === '' || ! $disk->exists($relativePath)) {
            return;
        }

        $plain = $disk->get($relativePath);
        if (! is_string($plain) || $plain === '') {
            return;
        }

        if ($this->isEncryptedBlob($plain)) {
            return;
        }

        $disk->put($relativePath, $this->encryptBytes($plain, $dek));
    }

    public function decryptDiskFileToString(Filesystem $disk, string $relativePath, string $dek, bool $allowPlaintextFallback = false): string
    {
        $relativePath = $this->normalizeRel($relativePath);
        if ($relativePath === '' || ! $disk->exists($relativePath)) {
            return '';
        }

        $raw = $disk->get($relativePath);
        if (! is_string($raw)) {
            return '';
        }

        if ($raw === '') {
            return '';
        }

        if ($this->isEncryptedBlob($raw)) {
            return $this->decryptBytes($raw, $dek);
        }

        if ($allowPlaintextFallback) {
            return $raw;
        }

        throw new RuntimeException('Archivo no cifrado cuando se esperaba ciphertext.');
    }

    /**
     * @return array{0: string, 1: string} [contents, mime]
     */
    public function decryptDiskFileForHttp(Filesystem $disk, string $relativePath, string $dek, bool $allowPlaintextFallback = false): array
    {
        $contents = $this->decryptDiskFileToString($disk, $relativePath, $dek, $allowPlaintextFallback);
        $mime = $this->guessMimeFromPath($relativePath);

        return [$contents, $mime];
    }

    /**
     * Cifra recursivamente todos los archivos bajo $rootRelative (excluye upload.zip antes de borrarlo aparte).
     *
     * @param  (callable(int $done, int $total): void)|null  $onFileProgress
     * @return int número de archivos cifrados
     */
    public function encryptTree(Filesystem $disk, string $rootRelative, string $dek, ?callable $onFileProgress = null): int
    {
        $rootRelative = $this->normalizeRel($rootRelative);
        if ($rootRelative === '' || ! $disk->exists($rootRelative)) {
            return 0;
        }

        $files = $disk->allFiles($rootRelative);
        $toEncrypt = [];
        foreach ($files as $file) {
            $file = $this->normalizeRel((string) $file);
            if ($file === '' || str_ends_with(mb_strtolower($file), 'upload.zip')) {
                continue;
            }
            $toEncrypt[] = $file;
        }

        $total = count($toEncrypt);
        $done = 0;
        $count = 0;
        $skippedCorrupted = 0;

        foreach ($toEncrypt as $file) {
            try {
                $before = $disk->exists($file) ? strlen((string) $disk->get($file)) : 0;
                $this->encryptDiskFile($disk, $file, $dek);
            } catch (\Throwable $e) {
                $msg = (string) $e->getMessage();
                if (str_contains($msg, 'Corrupted path detected')) {
                    $skippedCorrupted++;
                    Log::warning('Skipping corrupted WhatsApp path during encryption tree.', [
                        'path' => $file,
                        'error' => $msg,
                    ]);
                    $done++;
                    if ($onFileProgress !== null) {
                        $onFileProgress($done, $total);
                    }

                    continue;
                }

                throw $e;
            }
            if ($before > 0) {
                $count++;
            }
            $done++;
            if ($onFileProgress !== null) {
                $onFileProgress($done, $total);
            }
        }

        if ($skippedCorrupted > 0) {
            Log::warning('WhatsApp encryption tree completed with corrupted paths skipped.', [
                'root' => $rootRelative,
                'skipped' => $skippedCorrupted,
                'total' => $total,
            ]);
        }

        return $count;
    }

    private function encryptWithKey(string $plain, string $key): string
    {
        if (strlen($key) !== 32) {
            throw new RuntimeException('La clave debe ser de 32 bytes.');
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false || strlen($tag) !== 16) {
            throw new RuntimeException('Fallo al cifrar.');
        }

        return self::MAGIC.$iv.$cipher.$tag;
    }

    private function decryptWithKey(string $blob, string $key): string
    {
        if (strlen($key) !== 32) {
            throw new RuntimeException('La clave debe ser de 32 bytes.');
        }
        if (! str_starts_with($blob, self::MAGIC)) {
            throw new RuntimeException('Formato de cifrado no reconocido.');
        }
        $iv = substr($blob, 3, 12);
        $tag = substr($blob, -16);
        $ct = substr($blob, 15, -16);
        $plain = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new RuntimeException('Fallo al descifrar (clave dañada o datos corruptos).');
        }

        return $plain;
    }

    private function normalizeRel(string $path): string
    {
        return trim(str_replace('\\', '/', $path), '/');
    }

    private function guessMimeFromPath(string $relativePath): string
    {
        $ext = mb_strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'opus', 'ogg', 'oga' => 'audio/ogg',
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain; charset=UTF-8',
            'html', 'htm' => 'text/html; charset=UTF-8',
            default => 'application/octet-stream',
        };
    }
}
