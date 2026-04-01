<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModuleEntry;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TemporaryModuleFieldService
{
    /** Valores canónicos guardados en JSON (ponderación / viñetas). */
    public const SEMAFORO_VALUES = ['verde', 'amarillo', 'rojo'];

    private ?bool $hasFieldCommentColumn = null;

    /** @return array<string, string> slug => etiqueta */
    public static function semaforoLabels(): array
    {
        return [
            'verde' => 'Verde',
            'amarillo' => 'Amarillo',
            'rojo' => 'Rojo',
        ];
    }

    public static function labelForSemaforo(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return self::semaforoLabels()[$value] ?? $value;
    }

    /**
     * Normaliza texto (Excel o formulario) al valor canónico o null si no coincide.
     */
    public static function normalizeSemaforoInput(string $str): ?string
    {
        $raw = trim($str);
        if ($raw === '') {
            return null;
        }
        $s = mb_strtolower($raw, 'UTF-8');
        $s = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $s);
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;

        if (in_array($s, ['verde', 'green'], true)) {
            return 'verde';
        }
        if (in_array($s, ['amarillo', 'yellow'], true)) {
            return 'amarillo';
        }
        if (in_array($s, ['rojo', 'red'], true)) {
            return 'rojo';
        }
        if ($s === '1') {
            return 'verde';
        }
        if ($s === '2') {
            return 'amarillo';
        }
        if ($s === '3') {
            return 'rojo';
        }
        if (str_contains($s, 'verde')) {
            return 'verde';
        }
        if (str_contains($s, 'amarill')) {
            return 'amarillo';
        }
        if (str_contains($s, 'rojo')) {
            return 'rojo';
        }

        return null;
    }

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
            if (in_array($type, ['select', 'multiselect'], true)) {
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

            if ($type === 'linked') {
                $options = $this->parseLinkedOptions($field);
                if (empty($options['primary_type'])) {
                    throw ValidationException::withMessages([
                        'fields.'.$index.'.options' => 'El campo vinculado requiere definir el campo principal.',
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
        if (in_array($type, ['select', 'multiselect'], true)) {
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

        if ($type === 'linked') {
            if (is_array($field['options'] ?? null) && isset($field['options']['primary_type'])) {
                $options = $field['options'];
            } else {
                $options = $this->parseLinkedOptions($field);
            }
            if (empty($options['primary_type'])) {
                throw ValidationException::withMessages([
                    $validationPrefix.'.options' => 'El campo vinculado requiere definir el campo principal.',
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
            'multiselect' => [...($required ? ['required'] : ['nullable']), 'array'],
            'linked' => ['nullable', 'array'], // validado internamente campo por campo
            'categoria' => (function () use ($required, $options, $rules) {
                $allowed = $this->categoriaAllowedValues($options);
                // Si el campo NO es obligatorio, permitimos que llegue '' (select vacío)
                // para que los registros aplicables solo a una parte queden en blanco en Excel.
                if (! $required) {
                    $allowed = array_values(array_unique(array_merge([''], $allowed)));
                }

                return [...$rules, 'string', 'max:255', Rule::in($allowed)];
            })(),
            'municipio' => [...$rules, Rule::in($municipios)],
            'delegado' => [...$rules, 'string', 'max:255'],
            'boolean' => [...$rules, 'boolean'],
            'semaforo' => (function () use ($required, $rules) {
                $allowed = self::SEMAFORO_VALUES;
                if (! $required) {
                    $allowed = array_values(array_unique(array_merge([''], $allowed)));
                }

                return [...$rules, 'string', 'max:32', Rule::in($allowed)];
            })(),
            'seccion' => ['nullable', 'string'], // no se guarda valor; solo layout
            'geopoint' => [...$rules, 'string', 'max:120'],
            'file' => [...$rules, 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'image' => [...$rules, 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            default => [...$rules, 'string', 'max:255'],
        };
    }

    /**
     * Parses the linked field options from the raw field data.
     * Returns ['primary_type', 'primary_label', 'primary_options', 'secondary_type', 'secondary_label', 'secondary_options', 'secondary_required'].
     */
    public function parseLinkedOptions(array $field): array
    {
        if (is_array($field['options'] ?? null) && isset($field['options']['primary_type'])) {
            return $field['options'];
        }

        // When coming from the form, sub-fields are in 'linked_primary_*' and 'linked_secondary_*' keys
        $parseOpts = function (string $raw): array {
            return collect(preg_split('/\r\n|\r|\n|,/', $raw))
                ->map(fn ($o) => trim((string) $o))
                ->filter(fn ($o) => $o !== '')
                ->values()
                ->all();
        };

        return [
            'primary_type'        => (string) ($field['linked_primary_type'] ?? 'text'),
            'primary_label'       => trim((string) ($field['linked_primary_label'] ?? '')),
            'primary_options'     => $parseOpts((string) ($field['linked_primary_options'] ?? '')),
            'secondary_type'      => (string) ($field['linked_secondary_type'] ?? 'text'),
            'secondary_label'     => trim((string) ($field['linked_secondary_label'] ?? '')),
            'secondary_options'   => $parseOpts((string) ($field['linked_secondary_options'] ?? '')),
            'secondary_required'  => (bool) ($field['linked_secondary_required'] ?? true),
        ];
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
        $type = strtolower(trim($type));

        // Normalización histórica: algunos módulos guardan "foto" como tipo.
        if ($type === 'foto' || $type === 'photo') {
            return 'image';
        }

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
