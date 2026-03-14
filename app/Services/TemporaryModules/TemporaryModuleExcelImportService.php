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
    /** Tipos que se pueden llenar desde Excel (sin archivo/imagen/geopunto). */
    public const IMPORTABLE_TYPES = [
        'text', 'textarea', 'number', 'date', 'datetime', 'select', 'boolean', 'categoria', 'municipio',
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
     * @param  list<int>  $allowedMunicipioNames  nombres permitidos para microrregión actual
     * @return array{imported:int, skipped:int, row_errors:list<array{row:int,message:string}>}
     */
    public function importRows(
        TemporaryModule $module,
        int $userId,
        int $microrregionId,
        UploadedFile $file,
        int $headerRow,
        int $dataStartRow,
        array $mapping,
        Collection $fieldsByKey,
        array $allowedMunicipioNames,
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

        $imported = 0;
        $skipped = 0;
        $rowErrors = [];

        for ($row = $dataStartRow; $row <= $highestRow; $row++) {
            $values = [];
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
                    $rowErrors[] = ['row' => $row, 'message' => "Fila {$row}: falta campo obligatorio ({$rk})."];
                    $skipped++;

                    continue 2;
                }
            }

            // Defaults para campos no mapeados (evitar nulls en JSON)
            foreach ($importable as $field) {
                if (!array_key_exists($field->key, $values)) {
                    $values[$field->key] = $field->type === 'boolean' ? false : null;
                }
            }

            $module->entries()->create([
                'user_id' => $userId,
                'microrregion_id' => $microrregionId,
                'data' => $values,
                'main_image_field_key' => null,
                'submitted_at' => Carbon::now(),
            ]);
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

    private function normalizeLabel(string $s): string
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

            return $str;
        }

        return $str === '' ? null : $str;
    }
}
