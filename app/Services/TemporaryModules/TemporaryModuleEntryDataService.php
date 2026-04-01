<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use App\Models\TemporaryModuleEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TemporaryModuleEntryDataService
{
    private const PRIMARY_DISK = 'secure_shared';
    private const LEGACY_DISK = 'public';

    public function deleteStoredPath(string $path): void
    {
        $normalizedPath = $this->normalizeStoredPath($path);
        if ($normalizedPath === null) {
            return;
        }

        foreach ([self::PRIMARY_DISK, self::LEGACY_DISK] as $disk) {
            if (Storage::disk($disk)->exists($normalizedPath)) {
                Storage::disk($disk)->delete($normalizedPath);
            }
        }
    }

    public function resolveStoredFilePath(string $path): ?string
    {
        $normalizedPath = $this->normalizeStoredPath($path);
        if ($normalizedPath === null) {
            return null;
        }

        foreach ([self::PRIMARY_DISK, self::LEGACY_DISK] as $disk) {
            if (Storage::disk($disk)->exists($normalizedPath)) {
                return Storage::disk($disk)->path($normalizedPath);
            }
        }

        $publicPath = public_path($normalizedPath);
        if (is_file($publicPath)) {
            return $publicPath;
        }

        $prefixedPublicPath = public_path('storage/'.$normalizedPath);
        if (is_file($prefixedPublicPath)) {
            return $prefixedPublicPath;
        }

        return null;
    }

    public function pathCanBePreviewedInline(string $path): bool
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'pdf', 'txt', 'csv'], true);
    }

    /** Elimina una entrada y sus archivos asociados (imagen/file). */
    public function deleteEntryAndFiles(TemporaryModuleEntry $entry, TemporaryModule $module): void
    {
        $fileFieldKeys = $module->fields
            ->whereIn('type', ['image', 'file'])
            ->pluck('key')
            ->all();

        foreach ($fileFieldKeys as $fieldKey) {
            $path = $entry->data[$fieldKey] ?? null;
            if (is_string($path) && trim($path) !== '' && !filter_var($path, FILTER_VALIDATE_URL)) {
                $this->deleteStoredPath($path);
            }
        }

        $entry->delete();
    }

    public function clearModuleEntriesData(TemporaryModule $temporaryModule): void
    {
        $fileFieldKeys = $temporaryModule->fields
            ->whereIn('type', ['image', 'file'])
            ->pluck('key')
            ->all();

        $filesToDelete = [];
        if (!empty($fileFieldKeys)) {
            foreach ($temporaryModule->entries()->select(['id', 'data'])->cursor() as $entry) {
                foreach ($fileFieldKeys as $fieldKey) {
                    $path = $entry->data[$fieldKey] ?? null;
                    if (!is_string($path) || trim($path) === '' || filter_var($path, FILTER_VALIDATE_URL)) {
                        continue;
                    }

                    $filesToDelete[] = ltrim(str_replace('\\', '/', $path), '/');
                }
            }
        }

        DB::transaction(function () use ($temporaryModule): void {
            $temporaryModule->entries()->delete();
        });

        if (!empty($filesToDelete)) {
            $uniqueFiles = array_values(array_unique($filesToDelete));
            Storage::disk(self::PRIMARY_DISK)->delete($uniqueFiles);
            Storage::disk(self::LEGACY_DISK)->delete($uniqueFiles);
        }
    }

    public function clearFieldDataFromEntries(TemporaryModule $temporaryModule, array $fieldKeys, array $imageLikeKeys = []): void
    {
        $keysToClear = array_values(array_unique(array_filter($fieldKeys, fn ($key) => is_string($key) && $key !== '')));
        if (empty($keysToClear)) {
            return;
        }

        $imageKeys = array_values(array_unique(array_filter($imageLikeKeys, fn ($key) => is_string($key) && $key !== '')));
        $filesToDelete = [];

        $entries = TemporaryModuleEntry::query()
            ->where('temporary_module_id', $temporaryModule->id)
            ->select(['id', 'data', 'main_image_field_key'])
            ->get();

        foreach ($entries as $entry) {
            $data = $entry->data ?? [];
            $changed = false;

            foreach ($keysToClear as $fieldKey) {
                if (!array_key_exists($fieldKey, $data)) {
                    continue;
                }

                $value = $data[$fieldKey];
                if (in_array($fieldKey, $imageKeys, true) && is_string($value) && trim($value) !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $filesToDelete[] = ltrim(str_replace('\\', '/', $value), '/');
                }

                unset($data[$fieldKey]);
                $changed = true;
            }

            $mainImageFieldKey = $entry->main_image_field_key;
            if (is_string($mainImageFieldKey) && in_array($mainImageFieldKey, $keysToClear, true)) {
                $mainImageFieldKey = null;
                $changed = true;
            }

            if ($changed) {
                TemporaryModuleEntry::query()->whereKey($entry->id)->update([
                    'data' => $data,
                    'main_image_field_key' => $mainImageFieldKey,
                ]);
            }
        }

        if (!empty($filesToDelete)) {
            $uniqueFiles = array_values(array_unique($filesToDelete));
            Storage::disk(self::PRIMARY_DISK)->delete($uniqueFiles);
            Storage::disk(self::LEGACY_DISK)->delete($uniqueFiles);
        }
    }

    private function normalizeStoredPath(string $path): ?string
    {
        $value = trim($path);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        $normalizedPath = ltrim(str_replace('\\', '/', $value), '/');
        if ($normalizedPath === '' || str_contains($normalizedPath, '..')) {
            return null;
        }

        return $normalizedPath;
    }
}
