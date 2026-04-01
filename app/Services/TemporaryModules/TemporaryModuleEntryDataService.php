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
        $candidatePaths = $this->candidateStoredPaths($path);
        if ($candidatePaths === []) {
            return null;
        }

        foreach ($candidatePaths as $candidatePath) {
            foreach ([self::PRIMARY_DISK, self::LEGACY_DISK] as $disk) {
                if (Storage::disk($disk)->exists($candidatePath)) {
                    return Storage::disk($disk)->path($candidatePath);
                }
            }

            $publicPath = public_path($candidatePath);
            if (is_file($publicPath)) {
                return $publicPath;
            }

            $prefixedPublicPath = public_path('storage/'.$candidatePath);
            if (is_file($prefixedPublicPath)) {
                return $prefixedPublicPath;
            }
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
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', '', $path) ?? $path;
        $value = trim($value);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        // Normalize accidental spacing around slashes from wrapped/copied values.
        $value = preg_replace('~\s*/\s*~u', '/', $value) ?? $value;
        // If a temporary-modules path is wrapped (Word/PDF/HTML), it may contain whitespace between letters.
        // Stored paths never contain whitespace, so stripping it is safe here.
        if (preg_match('~^temporary~iu', $value) === 1) {
            $value = preg_replace('~\s+~u', '', $value) ?? $value;
        }
        // For temporary module stored paths, spaces are never expected and may come
        // from wrapped text copied from documents/logs.
        $value = preg_replace('~^(temporary[\s_-]*modules/)~iu', 'temporary-modules/', $value) ?? $value;
        if (preg_match('~^temporary[-_]modules/~i', $value) === 1) {
            $value = preg_replace('/\s+/u', '', $value) ?? $value;
        }

        $normalizedPath = ltrim(str_replace('\\', '/', $value), '/');
        if ($normalizedPath === '' || str_contains($normalizedPath, '..')) {
            return null;
        }

        return $normalizedPath;
    }

    /**
     * Build path candidates to support legacy prefixes and minor path formatting differences.
     *
     * @return array<int, string>
     */
    private function candidateStoredPaths(string $path): array
    {
        $normalizedPath = $this->normalizeStoredPath($path);
        if ($normalizedPath === null) {
            return [];
        }

        $candidates = [$normalizedPath];

        if (str_starts_with($normalizedPath, 'temporary_modules/')) {
            $candidates[] = 'temporary-modules/'.substr($normalizedPath, strlen('temporary_modules/'));
        }

        if (str_starts_with($normalizedPath, 'temporary-modules/')) {
            $candidates[] = 'temporary_modules/'.substr($normalizedPath, strlen('temporary-modules/'));
        }

        return array_values(array_unique(array_filter($candidates, static fn ($v) => is_string($v) && $v !== '')));
    }
}
