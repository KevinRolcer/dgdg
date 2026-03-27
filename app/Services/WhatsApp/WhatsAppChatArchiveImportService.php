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
     * @param  (callable(int $percent, string $phase): void)|null  $onProgress Porcentaje aproximado global (0–100) y etiqueta.
     * @throws ValidationException
     */
    public function processFromPendingZip(WhatsAppChatArchive $archive, ?callable $onProgress = null): void
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

        if ($onProgress !== null) {
            $onProgress(12, 'Descomprimiendo ZIP…');
        }

        $zip = new ZipArchive;
        if ($zip->open($tempZipPath) !== true) {
            throw ValidationException::withMessages([
                'import' => 'No se pudo abrir el ZIP. Verifica que sea una exportación válida de WhatsApp.',
            ]);
        }
        $zip->extractTo($extractDirAbsolute);
        $zip->close();

        if ($onProgress !== null) {
            $onProgress(36, 'Analizando estructura del chat…');
        }

        [$chatTitle, $chatRootDirRelative, $messageParts] = $this->detectChatAndMessageParts(
            $extractDirRelative,
            $extractDirAbsolute,
            $diskRootAbs
        );

        if ($onProgress !== null) {
            $onProgress(48, 'Preparando cifrado…');
        }

        $dek = $this->encryption->generateDek();
        $wrapped = $this->encryption->wrapDek($dek);

        if ($onProgress !== null) {
            $onProgress(52, 'Cifrando archivos…');
        }

        $encryptProgress = $onProgress;
        $this->encryption->encryptTree($disk, $chatRootDirRelative, $dek, function (int $done, int $total) use ($encryptProgress): void {
            if ($encryptProgress === null) {
                return;
            }
            if ($total < 1) {
                $encryptProgress(92, 'Cifrando archivos…');

                return;
            }
            $step = max(1, (int) ceil($total / 40));
            if ($done !== $total && ($done % $step) !== 0) {
                return;
            }
            $pct = 52 + (int) round(($done / $total) * 44);
            $pct = min(96, max(52, $pct));
            $encryptProgress($pct, 'Cifrando archivos ('.$done.'/'.$total.')…');
        });

        if ($disk->exists($extractDirRelative.'/upload.zip')) {
            $disk->delete($extractDirRelative.'/upload.zip');
        }

        $compactParts = WhatsAppChatPathNormalizer::normalizeStoragePaths($messageParts, $chatRootDirRelative);

        if ($onProgress !== null) {
            $onProgress(98, 'Guardando registro…');
        }

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
            'import_progress' => 100,
            'import_phase' => 'Completado',
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
