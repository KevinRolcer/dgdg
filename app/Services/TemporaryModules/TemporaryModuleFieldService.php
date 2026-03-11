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

            if ($type === 'categoria') {
                $options = $this->parseCategoriaOptions((string) ($field['options'] ?? ''));
                if (empty($options)) {
                    throw ValidationException::withMessages([
                        'fields.'.$index.'.options' => 'Debes agregar al menos una categoría (formato: Categoría: sub1, sub2).',
                    ]);
                }
            }

            if ($type === 'seccion') {
                $title = trim((string) ($field['options_title'] ?? $field['options']['title'] ?? ''));
                $subsections = $this->parseSubsectionsList((string) ($field['options_subsections'] ?? ''));
                if (isset($field['options']) && is_array($field['options']) && isset($field['options']['subsections'])) {
                    $subsections = $field['options']['subsections'];
                }
                $options = ['title' => $title ?: $label, 'subsections' => $subsections];
            }

            $prepared[] = [
                'label' => $label,
                ...($this->supportsFieldComment() ? ['comment' => (trim((string) ($field['comment'] ?? '')) ?: null)] : []),
                'key' => $key,
                'type' => $type,
                'is_required' => $type !== 'seccion' && (bool) ($field['required'] ?? false),
                'options' => $options,
                'sort_order' => $sortOrder,
                'subsection_index' => null,
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

        if ($type === 'categoria') {
            $raw = is_array($field['options'] ?? null) ? '' : (string) ($field['options'] ?? '');
            if (is_array($field['options'] ?? null)) {
                $options = $field['options'];
            } else {
                $options = $this->parseCategoriaOptions($raw);
            }
            if (empty($options)) {
                throw ValidationException::withMessages([
                    $validationPrefix.'.options' => 'Debes agregar al menos una categoría.',
                ]);
            }
        }

        if ($type === 'seccion') {
            $title = trim((string) ($field['options_title'] ?? ($field['options']['title'] ?? '')));
            $subsections = $this->parseSubsectionsList((string) ($field['options_subsections'] ?? ''));
            if (is_array($field['options'] ?? null) && !empty($field['options']['subsections'])) {
                $subsections = $field['options']['subsections'];
            }
            $options = ['title' => $title ?: $label, 'subsections' => $subsections];
        }

        $subsectionIndex = $this->parseSubsectionIndex($field['subsection_index'] ?? null);

        return [
            'label' => $label,
            ...($this->supportsFieldComment() ? ['comment' => (trim((string) ($field['comment'] ?? '')) ?: null)] : []),
            'key' => $key,
            'type' => $type,
            'is_required' => $type !== 'seccion' && (bool) ($field['required'] ?? false),
            'options' => $options,
            'sort_order' => $sortOrder,
            'subsection_index' => $subsectionIndex,
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
            'categoria' => [...$rules, 'string', 'max:255', Rule::in($this->categoriaAllowedValues($options))],
            'municipio' => [...$rules, Rule::in($municipios)],
            'boolean' => [...$rules, 'boolean'],
            'seccion' => ['nullable', 'string'], // no se guarda valor; solo layout
            'geopoint' => [...$rules, 'string', 'max:120'],
            'file' => [...$rules, 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'image' => [...$rules, 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            default => [...$rules, 'string', 'max:255'],
        };
    }

    /** @return list<string> */
    public function categoriaAllowedValues(array $options): array
    {
        $allowed = [];
        foreach ($options as $cat) {
            $name = is_array($cat) ? trim((string) ($cat['name'] ?? '')) : '';
            if ($name === '') {
                continue;
            }
            $allowed[] = $name;
            $subs = is_array($cat) && isset($cat['sub']) && is_array($cat['sub']) ? $cat['sub'] : [];
            foreach ($subs as $sub) {
                $sub = trim((string) $sub);
                if ($sub !== '') {
                    $allowed[] = $name.' > '.$sub;
                }
            }
        }
        return $allowed;
    }

    /** @return array<int, array{name: string, sub: array<string>}> */
    public function parseCategoriaOptions(string $raw): array
    {
        $out = [];
        $lines = preg_split('/\r\n|\r|\n/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s*:\s*/', $line, 2);
            $name = trim((string) ($parts[0] ?? ''));
            if ($name === '') {
                continue;
            }
            $subRaw = isset($parts[1]) ? trim((string) $parts[1]) : '';
            $subs = $subRaw !== ''
                ? collect(preg_split('/\s*,\s*/', $subRaw))->map(fn ($s) => trim((string) $s))->filter(fn ($s) => $s !== '')->values()->all()
                : [];
            $out[] = ['name' => $name, 'sub' => $subs];
        }
        return $out;
    }

    /** @return array<string> */
    public function parseSubsectionsList(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return collect($lines)->map(fn ($s) => trim((string) $s))->filter(fn ($s) => $s !== '')->values()->all();
    }

    private function parseSubsectionIndex(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = is_numeric($value) ? (int) $value : null;
        return $n !== null && $n >= 0 ? $n : null;
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
