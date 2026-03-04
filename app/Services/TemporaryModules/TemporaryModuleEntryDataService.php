<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use App\Models\TemporaryModuleEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TemporaryModuleEntryDataService
{
    public function deleteStoredPath(string $path): void
    {
        if (trim($path) === '' || filter_var($path, FILTER_VALIDATE_URL)) {
            return;
        }

        $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');
        if (Storage::disk('public')->exists($normalizedPath)) {
            Storage::disk('public')->delete($normalizedPath);
        }
    }

    public function resolveStoredFilePath(string $path): ?string
    {
        if (trim($path) === '' || filter_var($path, FILTER_VALIDATE_URL)) {
            return null;
        }

        $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');

        if (Storage::disk('public')->exists($normalizedPath)) {
            return Storage::disk('public')->path($normalizedPath);
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
            Storage::disk('public')->delete(array_values(array_unique($filesToDelete)));
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
            Storage::disk('public')->delete(array_values(array_unique($filesToDelete)));
        }
    }
}
