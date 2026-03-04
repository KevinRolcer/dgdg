<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModuleEntry;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TemporaryModuleFieldService
{
    private ?bool $hasFieldCommentColumn = null;

    public function prepareFields(array $fields, array $reservedKeys = [], int $startOrder = 1): array
    {
        $prepared = [];
        $usedKeys = $reservedKeys;
        $sortOrder = $startOrder;

        foreach ($fields as $index => $field) {
            $label = trim((string) ($field['label'] ?? ''));
            $type = (string) ($field['type'] ?? 'text');
            $rawKey = trim((string) ($field['key'] ?? ''));
            $key = Str::slug($rawKey !== '' ? $rawKey : $label, '_');

            if ($key === '') {
                $key = 'campo_'.$sortOrder;
            }

            $baseKey = $key;
            $suffix = 2;
            while (in_array($key, $usedKeys, true)) {
                $key = $baseKey.'_'.$suffix;
                $suffix++;
            }

            $usedKeys[] = $key;

            $options = null;
            if ($type === 'select') {
                $options = collect(preg_split('/\r\n|\r|\n|,/', (string) ($field['options'] ?? '')))
                    ->map(fn ($option) => trim((string) $option))
                    ->filter(fn ($option) => $option !== '')
                    ->values()
                    ->all();

                if (empty($options)) {
                    throw ValidationException::withMessages([
                        'fields.'.$index.'.options' => 'Debes agregar opciones para el campo '.$label.'.',
                    ]);
                }
            }

            $prepared[] = [
                'label' => $label,
                ...($this->supportsFieldComment() ? ['comment' => (trim((string) ($field['comment'] ?? '')) ?: null)] : []),
                'key' => $key,
                'type' => $type,
                'is_required' => (bool) ($field['required'] ?? false),
                'options' => $options,
                'sort_order' => $sortOrder,
            ];

            $sortOrder++;
        }

        return $prepared;
    }

    public function normalizeFieldRow(array $field, int $sortOrder, array &$usedKeys, string $validationPrefix): array
    {
        $label = trim((string) ($field['label'] ?? ''));
        $type = (string) ($field['type'] ?? 'text');
        $rawKey = trim((string) ($field['key'] ?? ''));
        $key = Str::slug($rawKey !== '' ? $rawKey : $label, '_');

        if ($key === '') {
            $key = 'campo_'.$sortOrder;
        }

        $baseKey = $key;
        $suffix = 2;
        while (in_array($key, $usedKeys, true)) {
            $key = $baseKey.'_'.$suffix;
            $suffix++;
        }

        $usedKeys[] = $key;

        $options = null;
        if ($type === 'select') {
            $options = collect(preg_split('/\r\n|\r|\n|,/', (string) ($field['options'] ?? '')))
                ->map(fn ($option) => trim((string) $option))
                ->filter(fn ($option) => $option !== '')
                ->values()
                ->all();

            if (empty($options)) {
                throw ValidationException::withMessages([
                    $validationPrefix.'.options' => 'Debes agregar opciones para el campo '.$label.'.',
                ]);
            }
        }

        return [
            'label' => $label,
            ...($this->supportsFieldComment() ? ['comment' => (trim((string) ($field['comment'] ?? '')) ?: null)] : []),
            'key' => $key,
            'type' => $type,
            'is_required' => (bool) ($field['required'] ?? false),
            'options' => $options,
            'sort_order' => $sortOrder,
        ];
    }

    public function supportsFieldComment(): bool
    {
        if (is_bool($this->hasFieldCommentColumn)) {
            return $this->hasFieldCommentColumn;
        }

        $this->hasFieldCommentColumn = Schema::hasColumn('temporary_module_fields', 'comment');

        return $this->hasFieldCommentColumn;
    }

    public function countFieldDataUsage(int $moduleId): array
    {
        $usage = [];

        foreach (TemporaryModuleEntry::query()->where('temporary_module_id', $moduleId)->select(['data'])->cursor() as $entry) {
            foreach (($entry->data ?? []) as $key => $value) {
                if (!is_string($key) || $key === '') {
                    continue;
                }

                if (!$this->hasFieldValue($value)) {
                    continue;
                }

                $usage[$key] = ($usage[$key] ?? 0) + 1;
            }
        }

        return $usage;
    }

    public function rulesForField(string $type, bool $required, array $options, array $municipios): array
    {
        $rules = $required ? ['required'] : ['nullable'];

        return match ($type) {
            'text' => [...$rules, 'string', 'max:255'],
            'textarea' => [...$rules, 'string', 'max:5000'],
            'number' => [...$rules, 'numeric'],
            'date' => [...$rules, 'date'],
            'datetime' => [...$rules, 'date'],
            'select' => [...$rules, Rule::in($options)],
            'municipio' => [...$rules, Rule::in($municipios)],
            'boolean' => [...$rules, 'boolean'],
            'geopoint' => [...$rules, 'string', 'max:120'],
            'file' => [...$rules, 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'image' => [...$rules, 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            default => [...$rules, 'string', 'max:255'],
        };
    }

    public function canonicalFieldType(string $type): string
    {
        return $type === 'file' ? 'image' : $type;
    }

    private function hasFieldValue(mixed $value): bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return !empty($value);
        }

        return true;
    }
}
