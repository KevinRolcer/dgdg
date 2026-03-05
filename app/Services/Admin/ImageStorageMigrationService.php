<?php

namespace App\Services\Admin;

use App\Models\MesaPazAsistencia;
use Illuminate\Support\Facades\Storage;

class ImageStorageMigrationService
{
    private const LEGACY_LOCALSTORAGE_DIR = 'localstorage';
    private const LEGACY_MESAS_DIR = 'localstorage/segob/mesas_paz/evidencias';
    private const LEGACY_TEMPORARY_MODULES_DIR = 'temporary-modules';
    private const SHARED_MESAS_DIR = 'mesas_paz/evidencias';
    private const SHARED_DISK = 'secure_shared';

    /**
     * Migra archivos legacy de public/localstorage al almacenamiento compartido,
     * y actualiza referencias de Mesas de Paz hacia la nueva ruta compartida.
     *
     * @return array<string, mixed>
     */
    public function migrate(bool $deleteOriginals = false): array
    {
        $report = [
            'started_at' => now()->toDateTimeString(),
            'delete_originals' => $deleteOriginals,
            'mesas_records_scanned' => 0,
            'mesas_records_updated' => 0,
            'mesas_paths_updated' => 0,
            'mesas_paths_missing' => 0,
            'temporary_modules_files_scanned' => 0,
            'temporary_modules_files_migrated' => 0,
            'temporary_modules_files_missing' => 0,
            'files_copied' => 0,
            'files_skipped_existing' => 0,
            'files_deleted_original' => 0,
            'files_failed' => 0,
            'errors' => [],
        ];

        $this->migrateMesasPazEvidence($report, $deleteOriginals);
        $this->migrateTemporaryModulesPublicDisk($report, $deleteOriginals);
        $this->migrateLegacyLocalStorageTree($report, $deleteOriginals);

        $report['finished_at'] = now()->toDateTimeString();

        return $report;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function migrateTemporaryModulesPublicDisk(array &$report, bool $deleteOriginals): void
    {
        $publicDisk = Storage::disk('public');

        if (!$publicDisk->exists(self::LEGACY_TEMPORARY_MODULES_DIR)) {
            return;
        }

        $allFiles = $publicDisk->allFiles(self::LEGACY_TEMPORARY_MODULES_DIR);
        foreach ($allFiles as $relativePathRaw) {
            $relativePath = ltrim(str_replace('\\', '/', (string) $relativePathRaw), '/');
            if ($relativePath === '') {
                continue;
            }

            $report['temporary_modules_files_scanned']++;

            if (Storage::disk(self::SHARED_DISK)->exists($relativePath)) {
                $report['files_skipped_existing']++;
                if ($deleteOriginals) {
                    if ($publicDisk->delete($relativePath)) {
                        $report['files_deleted_original']++;
                    }
                }
                continue;
            }

            if (!$publicDisk->exists($relativePath)) {
                $report['temporary_modules_files_missing']++;
                continue;
            }

            try {
                $stream = $publicDisk->readStream($relativePath);
                if ($stream === false) {
                    throw new \RuntimeException('No se pudo leer archivo en disco public.');
                }

                Storage::disk(self::SHARED_DISK)->writeStream($relativePath, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                $report['files_copied']++;
                $report['temporary_modules_files_migrated']++;

                if ($deleteOriginals) {
                    if ($publicDisk->delete($relativePath)) {
                        $report['files_deleted_original']++;
                    }
                }
            } catch (\Throwable $e) {
                $report['files_failed']++;
                $report['errors'][] = 'Migracion modulos temporales '.$relativePath.': '.$e->getMessage();
            }
        }
    }

    /**
     * @param array<string, mixed> $report
     */
    private function migrateMesasPazEvidence(array &$report, bool $deleteOriginals): void
    {
        MesaPazAsistencia::query()
            ->whereNotNull('evidencia')
            ->where('evidencia', 'like', '%'.self::LEGACY_MESAS_DIR.'/%')
            ->select(['asist_id', 'evidencia'])
            ->orderBy('asist_id')
            ->chunkById(200, function ($rows) use (&$report, $deleteOriginals): void {
                foreach ($rows as $row) {
                    $report['mesas_records_scanned']++;
                    $paths = $this->decodeEvidencePaths($row->evidencia);
                    if (empty($paths)) {
                        continue;
                    }

                    $updated = false;
                    foreach ($paths as $index => $path) {
                        if (!str_starts_with($path, self::LEGACY_MESAS_DIR.'/')) {
                            continue;
                        }

                        $filename = basename($path);
                        if ($filename === '' || $filename === '.' || $filename === '..') {
                            continue;
                        }

                        $targetPath = self::SHARED_MESAS_DIR.'/'.$filename;
                        $sourceAbsolutePath = public_path($path);

                        $canUseTarget = false;
                        if (Storage::disk(self::SHARED_DISK)->exists($targetPath)) {
                            $report['files_skipped_existing']++;
                            $canUseTarget = true;
                        } elseif (is_file($sourceAbsolutePath)) {
                            try {
                                $stream = fopen($sourceAbsolutePath, 'rb');
                                if ($stream === false) {
                                    throw new \RuntimeException('No se pudo abrir archivo de origen para lectura.');
                                }

                                Storage::disk(self::SHARED_DISK)->writeStream($targetPath, $stream);
                                if (is_resource($stream)) {
                                    fclose($stream);
                                }
                                $report['files_copied']++;
                                $canUseTarget = true;

                                if ($deleteOriginals) {
                                    if (@unlink($sourceAbsolutePath)) {
                                        $report['files_deleted_original']++;
                                    }
                                }
                            } catch (\Throwable $e) {
                                $report['files_failed']++;
                                $report['errors'][] = 'Mesas evidencia '.$path.': '.$e->getMessage();
                            }
                        } else {
                            $report['mesas_paths_missing']++;
                        }

                        if ($canUseTarget) {
                            $paths[$index] = $targetPath;
                            $updated = true;
                            $report['mesas_paths_updated']++;
                        }
                    }

                    if ($updated) {
                        MesaPazAsistencia::query()
                            ->where('asist_id', (int) $row->asist_id)
                            ->update([
                                'evidencia' => $this->encodeEvidencePaths($paths),
                            ]);
                        $report['mesas_records_updated']++;
                    }
                }
            }, 'asist_id', 'asist_id');
    }

    /**
     * @param array<string, mixed> $report
     */
    private function migrateLegacyLocalStorageTree(array &$report, bool $deleteOriginals): void
    {
        $sourceRoot = public_path(self::LEGACY_LOCALSTORAGE_DIR);
        if (!is_dir($sourceRoot)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceRoot, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $absolutePath = $fileInfo->getPathname();
            $relativePart = ltrim(str_replace('\\', '/', substr($absolutePath, strlen($sourceRoot))), '/');
            if ($relativePart === '') {
                continue;
            }

            $targetPath = self::LEGACY_LOCALSTORAGE_DIR.'/'.$relativePart;

            if (Storage::disk(self::SHARED_DISK)->exists($targetPath)) {
                $report['files_skipped_existing']++;
                if ($deleteOriginals && @unlink($absolutePath)) {
                    $report['files_deleted_original']++;
                }
                continue;
            }

            try {
                $stream = fopen($absolutePath, 'rb');
                if ($stream === false) {
                    throw new \RuntimeException('No se pudo abrir archivo de origen para lectura.');
                }

                Storage::disk(self::SHARED_DISK)->writeStream($targetPath, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                $report['files_copied']++;
                if ($deleteOriginals && @unlink($absolutePath)) {
                    $report['files_deleted_original']++;
                }
            } catch (\Throwable $e) {
                $report['files_failed']++;
                $report['errors'][] = 'Migracion localstorage '.$targetPath.': '.$e->getMessage();
            }
        }
    }

    /**
     * @return string[]
     */
    private function decodeEvidencePaths(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $text = trim($value);
        if ($text === '') {
            return [];
        }

        $decoded = json_decode($text, true);
        $items = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [$text];

        return collect($items)
            ->map(fn ($item) => ltrim(str_replace('\\', '/', (string) $item), '/'))
            ->filter(fn ($path) => $path !== '')
            ->values()
            ->all();
    }

    /**
     * @param string[] $paths
     */
    private function encodeEvidencePaths(array $paths): ?string
    {
        $normalized = collect($paths)
            ->map(fn ($path) => ltrim(str_replace('\\', '/', (string) $path), '/'))
            ->filter(fn ($path) => $path !== '')
            ->unique()
            ->values()
            ->all();

        return empty($normalized) ? null : json_encode($normalized, JSON_UNESCAPED_UNICODE);
    }
}
