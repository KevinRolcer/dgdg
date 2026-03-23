<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use App\Models\TemporaryModuleField;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class TemporaryModuleExcelImportService
{
    /** Tipos que se pueden llenar desde Excel (sin archivo/imagen). */
    public const IMPORTABLE_TYPES = [
        'text', 'textarea', 'number', 'date', 'datetime', 'select', 'multiselect', 'linked', 'boolean', 'categoria', 'municipio', 'geopoint', 'image', 'semaforo',
    ];

    public function importableFields(Collection $fields): Collection
    {
        return $fields->filter(fn (TemporaryModuleField $f) => in_array($f->type, self::IMPORTABLE_TYPES, true));
    }

    /**
     * Lee la fila de encabezados y sugiere mapeo campo -> índice de columna (0-based).
     *
     * @return array{headers: list<array{index:int,letter:string,label:string}>, suggested_map: array<string,int|null>, header_row:int}
     */
    public function preview(UploadedFile $file, int $headerRow = 1): array
    {
        $headerRow = max(1, $headerRow);
        $spreadsheet = $this->loadSpreadsheet($file);
        $sheet = $spreadsheet->getActiveSheet();
        $highestCol = $sheet->getHighestDataColumn($headerRow);
        $maxColIndex = Coordinate::columnIndexFromString($highestCol);

        $headers = [];
        for ($col = 1; $col <= $maxColIndex; $col++) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $raw = $sheet->getCell($letter.$headerRow)->getValue();
            $label = $this->stringifyCell($raw);
            $headers[] = [
                'index' => $col - 1,
                'letter' => $letter,
                'label' => $label,
            ];
        }

        return [
            'headers' => $headers,
            'suggested_map' => [],
            'header_row' => $headerRow,
        ];
    }

    /**
     * Sugiere mapeo comparando etiqueta/clave del módulo con encabezados del Excel.
     *
     * @param  list<array{index:int,letter:string,label:string}>  $headers
     * @return array<string, int|null> field_key => column index or null
     */
    public function suggestMap(Collection $importableFields, array $headers): array
    {
        $map = [];
        $normHeaders = [];
        foreach ($headers as $h) {
            $norm = $this->normalizeLabel($h['label']);
            if ($norm !== '') {
                $normHeaders[$h['index']] = $norm;
            }
        }

        foreach ($importableFields as $field) {
            $key = $field->key;
            $normLabel = $this->normalizeLabel($field->label);
            $normKey = $this->normalizeLabel(str_replace('_', ' ', $key));

            $bestIndex = null;
            $bestScore = 0;
            foreach ($headers as $h) {
                $hn = $normHeaders[$h['index']] ?? '';
                if ($hn === '') {
                    continue;
                }
                $score = 0;
                if ($hn === $normLabel || $hn === $normKey) {
                    $score = 100;
                } elseif ($key === 'municipio' && in_array($hn, ['MUNICIPIO', 'MUN', 'MUNICIP', 'NOM_MUN', 'NOMBRE_MUNICIPIO', 'NOMBRE_MUN', 'NOMMUN'], true)) {
                    $score = 95;
                } elseif (str_contains($hn, $normLabel) && strlen($normLabel) >= 3) {
                    $score = 80;
                } elseif (str_contains($normLabel, $hn) && strlen($hn) >= 3) {
                    $score = 75;
                } elseif ($normKey !== '' && ($hn === $normKey || str_contains($hn, $normKey))) {
                    $score = 70;
                }
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestIndex = $h['index'];
                }
            }
            $map[$key] = $bestScore >= 70 ? $bestIndex : null;
        }

        return $map;
    }

    /**
     * @param  array<string,int|null>  $mapping  field_key => 0-based column index; null = omit
    * @param  list<string>  $allowedMunicipioNames  nombres permitidos para microrregión actual
    * @param  list<string>  $suggestionMunicipioNames  nombres para sugerencias de coincidencia
     * @return array{imported:int, skipped:int, row_errors:list<array{row:int,message:string,data:array,suggestions:array}>}
     */
    public function importRows(
        TemporaryModule $module,
        int $userId,
        ?int $microrregionId,
        UploadedFile $file,
        int $headerRow,
        int $dataStartRow,
        array $mapping,
        Collection $fieldsByKey,
        array $allowedMunicipioNames,
        array $municipioToMrMap = [],
        array $suggestionMunicipioNames = [],
    ): array {
        $headerRow = max(1, $headerRow);
        $dataStartRow = max($headerRow + 1, $dataStartRow);
        $spreadsheet = $this->loadSpreadsheet($file);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = (int) $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn($headerRow);
        $maxCol = Coordinate::columnIndexFromString($highestCol);

        $importable = $this->importableFields($fieldsByKey->values());
        $requiredKeys = $importable->where('is_required', true)->pluck('key')->all();
        $municipioField = $fieldsByKey->firstWhere('type', 'municipio');
        $municipioKey = $municipioField ? $municipioField->key : null;
        $suggestionBaseMunicipios = !empty($suggestionMunicipioNames) ? $suggestionMunicipioNames : $allowedMunicipioNames;

        $imported = 0;
        $skipped = 0;
        $rowErrors = [];
        $existingSignaturesByMicro = [];

        $module->entries()
            ->select(['id', 'microrregion_id', 'data'])
            ->orderBy('id')
            ->chunk(500, function ($entries) use (&$existingSignaturesByMicro) {
                foreach ($entries as $entry) {
                    $microId = (int) ($entry->microrregion_id ?? 0);
                    $signature = $this->buildDuplicateSignature((array) ($entry->data ?? []));
                    $existingSignaturesByMicro[$microId][$signature] = true;
                }
            });

        $pendingSignaturesByMicro = [];

        for ($row = $dataStartRow; $row <= $highestRow; $row++) {
            $values = [];
            $rawValues = [];
            $hasAny = false;
            foreach ($mapping as $fieldKey => $colIndex) {
                if ($colIndex === null || !isset($fieldsByKey[$fieldKey])) {
                    continue;
                }
                $field = $fieldsByKey[$fieldKey];
                if (!in_array($field->type, self::IMPORTABLE_TYPES, true)) {
                    continue;
                }
                $col = (int) $colIndex + 1;
                if ($col < 1 || $col > $maxCol) {
                    continue;
                }
                $letter = Coordinate::stringFromColumnIndex($col);
                $cell = $sheet->getCell($letter.$row);
                $raw = $cell->getValue();
                $calculated = $cell->getCalculatedValue();
                $str = $this->stringifyCell($calculated !== null ? $calculated : $raw);
                $rawValues[$fieldKey] = $str;
                if (trim($str) !== '') {
                    $hasAny = true;
                }
                $values[$fieldKey] = $this->coerceValue($field, $raw, $calculated, $str, $allowedMunicipioNames);
            }

            if (!$hasAny) {
                $skipped++;

                continue;
            }

            foreach ($requiredKeys as $rk) {
                $v = $values[$rk] ?? null;
                $empty = $v === null || $v === '' || (is_string($v) && trim($v) === '');
                if ($empty) {
                    $suggestions = [];
                    if ($municipioKey !== null && $rk === $municipioKey) {
                        $rawMunicipio = trim((string) ($rawValues[$rk] ?? ''));
                        if ($rawMunicipio !== '') {
                            $suggestions = $this->suggestMunicipios($rawMunicipio, $suggestionBaseMunicipios, $municipioToMrMap);
                        }
                    }

                    $rowErrors[] = [
                        'row' => $row,
                        'message' => "Fila {$row}: falta campo obligatorio ({$fieldsByKey[$rk]->label}).",
                        'data' => $values,
                        'municipio_key' => $rk === $municipioKey ? $rk : null,
                        'failed_fields' => [[
                            'key' => $rk,
                            'label' => (string) ($fieldsByKey[$rk]->label ?? $rk),
                            'reason' => 'Campo obligatorio vacío o no reconocido.',
                            'received' => (string) ($rawValues[$rk] ?? ''),
                        ]],
                        'suggestions' => $suggestions,
                    ];
                    $skipped++;

                    continue 2;
                }
            }

            $rowMrId = $microrregionId;
            if ($rowMrId === null && $municipioKey) {
                $munVal = $values[$municipioKey] ?? null;
                if ($munVal) {
                    $rowMrId = $municipioToMrMap[$this->normalizeLabel($munVal)] ?? null;
                }
            }

            if ($rowMrId === null || $rowMrId <= 0) {
                $munVal = $municipioKey ? ($values[$municipioKey] ?? '') : '';
                $suggestions = [];
                if ($munVal !== '') {
                    $suggestions = $this->suggestMunicipios($munVal, $suggestionBaseMunicipios, $municipioToMrMap);
                }

                $rowErrors[] = [
                    'row' => $row,
                    'message' => "Fila {$row}: No se pudo determinar la microrregión para el municipio '{$munVal}'.",
                    'data' => $values,
                    'municipio_key' => $municipioKey,
                    'failed_fields' => $municipioKey ? [[
                        'key' => $municipioKey,
                        'label' => (string) ($fieldsByKey[$municipioKey]->label ?? $municipioKey),
                        'reason' => 'El municipio no coincide con una microrregión asignada.',
                        'received' => (string) $munVal,
                    ]] : [],
                    'suggestions' => $suggestions,
                ];
                $skipped++;

                continue;
            }

            // Defaults para campos no mapeados (evitar nulls en JSON)
            foreach ($importable as $field) {
                if (!array_key_exists($field->key, $values)) {
                    $values[$field->key] = $field->type === 'boolean' ? false : null;
                }
            }

            $rowSignature = $this->buildDuplicateSignature($values);
            $microKey = (int) $rowMrId;
            $isDuplicate = isset($existingSignaturesByMicro[$microKey][$rowSignature])
                || isset($pendingSignaturesByMicro[$microKey][$rowSignature]);

            if ($isDuplicate) {
                $rowErrors[] = [
                    'row' => $row,
                    'message' => "Fila {$row}: registro duplicado (todos los campos iguales).",
                    'data' => $values,
                    'failed_fields' => [[
                        'key' => '__duplicate__',
                        'label' => 'Registro completo',
                        'reason' => 'Ya existe un registro igual en el módulo para la misma microrregión.',
                        'received' => '',
                    ]],
                    'suggestions' => [],
                ];
                $skipped++;

                continue;
            }

            $module->entries()->create([
                'user_id' => $userId,
                'microrregion_id' => $rowMrId,
                'data' => $values,
                'main_image_field_key' => null,
                'submitted_at' => Carbon::now(),
            ]);
            $pendingSignaturesByMicro[$microKey][$rowSignature] = true;
            $imported++;
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'row_errors' => array_slice($rowErrors, 0, 50),
        ];
    }

    private function loadSpreadsheet(UploadedFile $file): Spreadsheet
    {
        return IOFactory::load($file->getRealPath());
    }

    public function normalizeLabel(string $s): string
    {
        $s = mb_strtoupper(trim($s), 'UTF-8');
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;

        return $this->quitarAcentos($s);
    }

    private function quitarAcentos(string $s): string
    {
        $map = ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N', 'À' => 'A', 'È' => 'E'];

        return strtr($s, $map);
    }

    private function stringifyCell(mixed $raw): string
    {
        if ($raw === null) {
            return '';
        }
        if (is_numeric($raw) && !is_string($raw)) {
            return (string) $raw;
        }
        if ($raw instanceof \DateTimeInterface) {
            return $raw->format('Y-m-d H:i:s');
        }
        if (is_bool($raw)) {
            return $raw ? 'Sí' : 'No';
        }

        return trim((string) $raw);
    }

    private function coerceValue(
        TemporaryModuleField $field,
        mixed $raw,
        mixed $calculated,
        string $strTrim,
        array $allowedMunicipioNames,
    ): mixed {
        $t = $field->type;
        $str = trim($strTrim);

        if ($t === 'number') {
            if ($str === '') {
                return null;
            }
            $n = filter_var(str_replace(',', '.', preg_replace('/[^\d.,\-]/', '', $str)), FILTER_VALIDATE_FLOAT);

            return $n !== false ? $n : null;
        }

        if ($t === 'boolean') {
            if ($str === '') {
                return false;
            }
            $u = mb_strtoupper($str, 'UTF-8');

            return in_array($u, ['1', 'SI', 'SÍ', 'YES', 'TRUE', 'VERDADERO'], true)
                || $u === 'X';
        }

        if ($t === 'semaforo') {
            if ($str === '') {
                return null;
            }

            return TemporaryModuleFieldService::normalizeSemaforoInput($str);
        }

        if ($t === 'date') {
            if ($str === '') {
                return null;
            }
            if (is_numeric($raw)) {
                try {
                    $dt = ExcelDate::excelToDateTimeObject((float) $raw);

                    return $dt->format('Y-m-d');
                } catch (\Throwable) {
                    // fall through
                }
            }
            foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $fmt) {
                try {
                    return Carbon::createFromFormat($fmt, $str)->format('Y-m-d');
                } catch (\Throwable) {
                }
            }
            try {
                return Carbon::parse($str)->format('Y-m-d');
            } catch (\Throwable) {
                return $str;
            }
        }

        if ($t === 'datetime') {
            if ($str === '') {
                return null;
            }
            if (is_numeric($raw)) {
                try {
                    $dt = ExcelDate::excelToDateTimeObject((float) $raw);

                    return $dt->format('Y-m-d\TH:i');
                } catch (\Throwable) {
                }
            }
            try {
                return Carbon::parse($str)->format('Y-m-d\TH:i');
            } catch (\Throwable) {
                return $str;
            }
        }

        if ($t === 'multiselect') {
            if ($str === '') {
                return null;
            }

            $validOpts = array_map('strval', $field->options ?? []);

            if (!empty($validOpts)) {
                // Strategy 1: search for each known option inside the cell text (case-insensitive).
                // We keep the order of the defined options list and only include those found.
                $found = [];
                foreach ($validOpts as $opt) {
                    if ($opt === '') {
                        continue;
                    }
                    // Match the option as a whole word / token, surrounded by non-word chars or start/end
                    $pattern = '/(?:^|[\s,;|()\[\]{}\-\/\\\\])'.preg_quote($opt, '/').'(?:$|[\s,;|()\[\]{}\-\/\\\\])/ui';
                    if (preg_match($pattern, ' '.$str.' ')) {
                        $found[] = $opt;
                    }
                }

                if (!empty($found)) {
                    return $found;
                }
            }

            // Strategy 2 (fallback): split on comma or semicolon
            $items = collect(preg_split('/[,;]+/', $str))
                ->map(fn ($s) => trim((string) $s))
                ->filter(fn ($s) => $s !== '')
                ->values()
                ->all();

            return $items === [] ? null : $items;
        }

        if ($t === 'linked') {
            if ($str === '') {
                return null;
            }
            // Support "primary | secondary" format in a cell
            $parts = explode('|', $str, 2);
            $primary = trim($parts[0]);
            $secondary = isset($parts[1]) ? trim($parts[1]) : null;

            $result = ['primary' => $primary !== '' ? $primary : null, 'secondary' => $secondary];
            $opts = is_array($field->options) ? $field->options : [];
            $pt = (string) ($opts['primary_type'] ?? '');
            if ($pt === 'semaforo' && is_string($result['primary']) && trim($result['primary']) !== '') {
                $result['primary'] = TemporaryModuleFieldService::normalizeSemaforoInput($result['primary']);
            }
            $st = (string) ($opts['secondary_type'] ?? '');
            if ($st === 'semaforo' && is_string($result['secondary']) && trim($result['secondary']) !== '') {
                $result['secondary'] = TemporaryModuleFieldService::normalizeSemaforoInput($result['secondary']);
            }

            return $result;
        }

        if ($t === 'select') {
            $opts = array_map('strval', $field->options ?? []);
            if ($str === '') {
                return null;
            }
            foreach ($opts as $o) {
                if (mb_strtoupper(trim($o), 'UTF-8') === mb_strtoupper($str, 'UTF-8')) {
                    return $o;
                }
            }

            return $str;
        }

        if ($t === 'categoria') {
            if ($str === '') {
                return null;
            }
            $catOpts = is_array($field->options) ? $field->options : [];
            foreach ($catOpts as $cat) {
                $name = (string) ($cat['name'] ?? '');
                if (mb_strtoupper($name, 'UTF-8') === mb_strtoupper($str, 'UTF-8')) {
                    return $name;
                }
                foreach ((array) ($cat['sub'] ?? []) as $sub) {
                    $subVal = $name.' > '.$sub;
                    if (mb_strtoupper($subVal, 'UTF-8') === mb_strtoupper($str, 'UTF-8')
                        || mb_strtoupper((string) $sub, 'UTF-8') === mb_strtoupper($str, 'UTF-8')) {
                        return str_contains($str, '>') ? $str : $subVal;
                    }
                }
            }

            return $str;
        }

        if ($t === 'municipio') {
            if ($str === '') {
                return null;
            }
            $norm = $this->normalizeLabel($str);
            foreach ($allowedMunicipioNames as $name) {
                if ($this->normalizeLabel($name) === $norm) {
                    return $name;
                }
            }
            // Si el municipio no pertenece a la microregión seleccionada, lo dejamos vacío
            // para que el registro no se asigne incorrectamente.
            return null;
        }

        return $str === '' ? null : $str;
    }

    /**
     * Sugerencias de municipio basadas en similitud de texto.
     */
    public function suggestMunicipios(string $search, array $allowedNames, array $municipioToMrMap): array
    {
        $normSearch = $this->normalizeLabel($search);
        if ($normSearch === '') {
            return [];
        }

        $compactSearch = $this->compactAlnum($normSearch);
        $best = [];

        foreach ($allowedNames as $name) {
            $normName = $this->normalizeLabel($name);
            $compactName = $this->compactAlnum($normName);
            $score = 0;

            if ($normName === $normSearch) {
                $score = 100;
            } elseif ($compactName !== '' && $compactName === $compactSearch) {
                $score = 95;
            } elseif (str_contains($normName, $normSearch) && mb_strlen($normSearch) >= 4) {
                $score = 85;
            } elseif (str_contains($normSearch, $normName) && mb_strlen($normName) >= 4) {
                $score = 80;
            } else {
                similar_text($compactSearch, $compactName, $pct);
                if ($pct >= 45) {
                    $score = (int) $pct;
                }
            }

            if ($score >= 45) {
                $best[] = [
                    'score' => $score,
                    'municipio' => $name,
                    'microrregion_id' => $municipioToMrMap[$normName] ?? null,
                ];
            }
        }

        usort($best, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($best, 0, 5);
    }

    private function compactAlnum(string $s): string
    {
        return preg_replace('/[^A-Z0-9]/', '', $s) ?? '';
    }

    public function buildDuplicateSignature(array $values): string
    {
        $normalized = $this->normalizeDataForSignature($values);

        return md5(json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function normalizeDataForSignature(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $k => $v) {
                $normalized[(string) $k] = $this->normalizeDataForSignature($v);
            }
            ksort($normalized);

            return $normalized;
        }

        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (string) (0 + $value);
        }

        return trim((string) $value);
    }
}
