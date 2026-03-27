<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppChatArchive;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use ZipArchive;

final class WhatsAppChatArchiveImportService
{
    public function __construct(
        private readonly WhatsAppChatEncryptionService $encryption
    ) {}

    /**
     * Extrae upload.zip bajo storage_root_path del registro, detecta el chat, cifra y actualiza el registro.
     *
     * @throws ValidationException
     */
    public function processFromPendingZip(WhatsAppChatArchive $archive): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw ValidationException::withMessages([
                'import' => 'Este servidor no tiene soporte para ZIP (ZipArchive).',
            ]);
        }

        $disk = $archive->disk();
        $diskName = $archive->storageDiskName();
        $diskRootAbs = $this->diskAbsoluteRoot($diskName);

        $extractDirRelative = trim(str_replace('\\', '/', (string) $archive->storage_root_path), '/');
        $extractDirAbsolute = $disk->path($extractDirRelative);
        $tempZipPath = $extractDirAbsolute.DIRECTORY_SEPARATOR.'upload.zip';

        if (! is_file($tempZipPath)) {
            throw ValidationException::withMessages([
                'import' => 'No se encontró el ZIP de subida en el servidor.',
            ]);
        }

        $zip = new ZipArchive;
        if ($zip->open($tempZipPath) !== true) {
            throw ValidationException::withMessages([
                'import' => 'No se pudo abrir el ZIP. Verifica que sea una exportación válida de WhatsApp.',
            ]);
        }
        $zip->extractTo($extractDirAbsolute);
        $zip->close();

        [$chatTitle, $chatRootDirRelative, $messageParts] = $this->detectChatAndMessageParts(
            $extractDirRelative,
            $extractDirAbsolute,
            $diskRootAbs
        );

        $dek = $this->encryption->generateDek();
        $wrapped = $this->encryption->wrapDek($dek);
        $this->encryption->encryptTree($disk, $chatRootDirRelative, $dek);

        if ($disk->exists($extractDirRelative.'/upload.zip')) {
            $disk->delete($extractDirRelative.'/upload.zip');
        }

        $compactParts = WhatsAppChatPathNormalizer::normalizeStoragePaths($messageParts, $chatRootDirRelative);

        $archive->forceFill([
            'title' => $chatTitle,
            'storage_root_path' => $chatRootDirRelative,
            'message_parts' => $compactParts,
            'message_parts_count' => count($compactParts),
            'is_encrypted' => true,
            'wrapped_dek' => $wrapped,
            'encrypted_key_version' => 1,
            'import_status' => WhatsAppChatArchive::IMPORT_STATUS_READY,
            'import_error' => null,
            'imported_at' => now(),
        ])->save();
    }

    private function diskAbsoluteRoot(string $diskName): string
    {
        $root = (string) config("filesystems.disks.{$diskName}.root", '');

        return rtrim($root, DIRECTORY_SEPARATOR);
    }

    /**
     * @return array{0:string,1:string,2:array<int,string>}
     */
    private function detectChatAndMessageParts(string $extractDirRelative, string $extractDirAbsolute, string $diskAbsoluteRoot): array
    {
        $subdirs = File::directories($extractDirAbsolute);
        foreach ($subdirs as $candidateAbsoluteDir) {
            $candidateName = basename($candidateAbsoluteDir);

            $messagesDirAbsolute = $candidateAbsoluteDir.DIRECTORY_SEPARATOR.'messages';
            if (! is_dir($messagesDirAbsolute)) {
                continue;
            }

            $htmlFiles = glob($messagesDirAbsolute.DIRECTORY_SEPARATOR.'message_*.html');
            if (! $htmlFiles || count($htmlFiles) === 0) {
                continue;
            }

            sort($htmlFiles, SORT_NATURAL);

            $messageParts = array_map(function (string $absFile) use ($diskAbsoluteRoot) {
                $normalizedAbs = str_replace('\\', '/', $absFile);
                $normalizedRoot = rtrim(str_replace('\\', '/', $diskAbsoluteRoot), '/');

                return ltrim(str_replace($normalizedRoot, '', $normalizedAbs), '/');
            }, $htmlFiles);

            $chatRootDirRelative = $extractDirRelative.'/'.$candidateName;

            return [$candidateName, str_replace('\\', '/', $chatRootDirRelative), $messageParts];
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractDirAbsolute, \FilesystemIterator::SKIP_DOTS)
        );

        $messageHtml = [];
        $chatTxt = [];
        foreach ($iter as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if ($fileInfo->isFile() && preg_match('/message_\\d+\\.html$/', $fileInfo->getFilename())) {
                $messageHtml[] = $fileInfo->getPathname();
            }
            if ($fileInfo->isFile() && preg_match('/(^|_)chat\\.txt$/i', $fileInfo->getFilename())) {
                $chatTxt[] = $fileInfo->getPathname();
            }
        }

        if (count($messageHtml) > 0) {
            sort($messageHtml, SORT_NATURAL);
            $firstMessageAbs = $messageHtml[0];
            $messagesDirAbs = dirname($firstMessageAbs);
            $chatRootAbs = dirname($messagesDirAbs);
            $chatRootName = basename($chatRootAbs);

            $messageParts = array_map(function (string $absFile) use ($diskAbsoluteRoot) {
                $normalizedAbs = str_replace('\\', '/', $absFile);
                $normalizedRoot = rtrim(str_replace('\\', '/', $diskAbsoluteRoot), '/');

                return ltrim(str_replace($normalizedRoot, '', $normalizedAbs), '/');
            }, $messageHtml);

            $chatRootDirRelative = ltrim(str_replace(
                rtrim(str_replace('\\', '/', $diskAbsoluteRoot), '/'),
                '',
                str_replace('\\', '/', $chatRootAbs)
            ), '/');

            return [$chatRootName, $chatRootDirRelative, $messageParts];
        }

        if (count($chatTxt) === 0) {
            throw ValidationException::withMessages([
                'import' => 'No se detectó ningún chat válido. Se esperaba message_*.html o _chat.txt dentro del ZIP.',
            ]);
        }

        sort($chatTxt, SORT_NATURAL);
        $firstChatTxtAbs = $chatTxt[0];
        $chatRootAbs = dirname($firstChatTxtAbs);
        $chatRootName = basename($chatRootAbs);

        $messageParts = array_map(function (string $absFile) use ($diskAbsoluteRoot) {
            $normalizedAbs = str_replace('\\', '/', $absFile);
            $normalizedRoot = rtrim(str_replace('\\', '/', $diskAbsoluteRoot), '/');

            return ltrim(str_replace($normalizedRoot, '', $normalizedAbs), '/');
        }, $chatTxt);

        $chatRootDirRelative = ltrim(str_replace(
            rtrim(str_replace('\\', '/', $diskAbsoluteRoot), '/'),
            '',
            str_replace('\\', '/', $chatRootAbs)
        ), '/');

        return [$chatRootName, $chatRootDirRelative, $messageParts];
    }
}
