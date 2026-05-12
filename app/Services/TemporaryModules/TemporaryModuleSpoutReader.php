<?php

namespace App\Services\TemporaryModules;

use DateInterval;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Lector ligero para XLSX usando OpenSpout (streaming, baja memoria).
 *
 * Diseñado como alternativa a PhpSpreadsheet para las operaciones
 * de "preview" y "analyze options" en archivos grandes (>15-20 MB),
 * que en PhpSpreadsheet pueden consumir cientos de MB y disparar 503.
 *
 * OpenSpout sólo soporta .xlsx (y .csv/.ods). Para .xls (formato viejo)
 * el llamador debe caer a PhpSpreadsheet.
 */
class TemporaryModuleSpoutReader
{
    /**
     * ¿El archivo es .xlsx y por tanto procesable por este lector?
     */
    public function supports(UploadedFile $file): bool
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        if ($ext === 'xlsx') {
            return true;
        }
        $mime = strtolower((string) $file->getMimeType());
        if (str_contains($mime, 'spreadsheetml')) {
            return true;
        }

        return false;
    }

    /**
     * Nombres de las hojas (en orden) — equivalente a getSheetNames().
     *
     * @return list<string>
     */
    public function sheetNames(string $path): array
    {
        $reader = $this->makeReader();
        $reader->open($path);
        try {
            $names = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                $names[] = $sheet->getName();
            }

            return $names;
        } finally {
            $reader->close();
        }
    }

    /**
     * Construye los headers en formato compatible con el servicio actual.
     * Lee header row + primeras 20 filas de datos para determinar el
     * ancho real de la tabla (OpenSpout trunca celdas vacías al final
     * de cada fila, así que el ancho efectivo es el de la fila más ancha).
     *
     * @return array{headers: list<array{index:int,letter:string,label:string}>, sheet_names: list<string>, sheet_index: int, header_row: int}
     */
    public function previewHeaders(string $path, int $headerRow = 1, int $sheetIndex = 0): array
    {
        $headerRow = max(1, $headerRow);
        $sheetNames = [];

        $reader = $this->makeReader();
        $reader->open($path);
        try {
            $idx = 0;
            $targetSheetIndex = $sheetIndex;
            $resolvedSheetIndex = $sheetIndex;
            $headers = [];
            $autoDetected = $headerRow;

            foreach ($reader->getSheetIterator() as $sheet) {
                $sheetNames[] = $sheet->getName();
                if ($idx === $targetSheetIndex) {
                    $resolvedSheetIndex = $idx;

                    // Si headerRow === 1, heurística: probar filas 1..5 y
                    // quedarnos con la que tenga más celdas no vacías.
                    if ($headerRow === 1) {
                        $scanEnd = 25; // 5 candidatos a header + ~20 datos
                        $candidateRows = $this->fetchRows($sheet, 1, $scanEnd);
                        $bestRow = 1;
                        $bestNonEmpty = 0;
                        for ($r = 1; $r <= 5; $r++) {
                            $cells = $candidateRows[$r] ?? [];
                            $nonEmpty = 0;
                            foreach ($cells as $c) {
                                if (trim((string) $c) !== '') {
                                    $nonEmpty++;
                                }
                            }
                            if ($nonEmpty > $bestNonEmpty) {
                                $bestNonEmpty = $nonEmpty;
                                $bestRow = $r;
                            }
                        }
                        $autoDetected = $bestRow;
                        $labels = $candidateRows[$bestRow] ?? [];
                        $dataRows = [];
                        foreach ($candidateRows as $rNum => $cells) {
                            if ($rNum > $bestRow) {
                                $dataRows[] = $cells;
                            }
                        }
                    } else {
                        $rows = $this->fetchRows($sheet, $headerRow, $headerRow + 20);
                        $labels = $rows[$headerRow] ?? [];
                        $dataRows = [];
                        foreach ($rows as $rNum => $cells) {
                            if ($rNum > $headerRow) {
                                $dataRows[] = $cells;
                            }
                        }
                    }

                    // Ancho efectivo: máximo entre header row y primeras
                    // filas de datos (Spout trunca celdas vacías al final).
                    $maxCols = count($labels);
                    foreach ($dataRows as $cells) {
                        $maxCols = max($maxCols, count($cells));
                    }
                    // Cap a 100 columnas para evitar tablas absurdas.
                    $maxCols = min($maxCols, 100);

                    for ($colIdx = 0; $colIdx < $maxCols; $colIdx++) {
                        $val = $labels[$colIdx] ?? '';
                        $letter = Coordinate::stringFromColumnIndex($colIdx + 1);
                        $headers[] = [
                            'index' => $colIdx,
                            'letter' => $letter,
                            'label' => $this->formatCellValue($val),
                        ];
                    }
                    break;
                }
                $idx++;
            }

            // Si pedimos sheet 2 pero hay 5, ya recorrimos sólo hasta 2, hay que seguir leyendo los demás nombres.
            // Como ya cortamos con break, recargamos para obtener todos los names si faltan.
            if (count($sheetNames) <= $targetSheetIndex) {
                $reader->close();
                $sheetNames = $this->sheetNames($path);

                return $this->previewHeaders($path, $headerRow, 0);
            }
        } finally {
            $reader->close();
        }

        if ($sheetNames === [] || $headers === []) {
            if ($sheetIndex !== 0 && $sheetNames === []) {
                return $this->previewHeaders($path, $headerRow, 0);
            }
        }

        if (count($sheetNames) <= $resolvedSheetIndex) {
            $sheetNames = $this->sheetNames($path);
        }

        return [
            'headers' => $headers,
            'sheet_names' => $sheetNames,
            'sheet_index' => $resolvedSheetIndex,
            'header_row' => $autoDetected,
        ];
    }

    /**
     * Filas de muestra para previsualizar mapeo.
     * Rellena cada fila con celdas vacías hasta `$minColumns` para que
     * la vista previa muestre TODAS las columnas detectadas (Spout
     * trunca celdas vacías al final de cada fila por defecto).
     *
     * @return list<array{row:int,cells:list<string>}>
     */
    public function previewRows(string $path, int $headerRow, int $sheetIndex, int $maxRows = 12, int $minColumns = 0): array
    {
        $headerRow = max(1, $headerRow);
        $endRow = $headerRow + max(8, $maxRows);

        $reader = $this->makeReader();
        $reader->open($path);
        try {
            $idx = 0;
            $out = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($idx === $sheetIndex) {
                    $rows = $this->fetchRows($sheet, $headerRow, $endRow);

                    // Primera pasada: máximo número de columnas en cualquier fila leída.
                    $maxColsSeen = $minColumns;
                    foreach ($rows as $cells) {
                        $maxColsSeen = max($maxColsSeen, count($cells));
                    }
                    $maxColsSeen = min($maxColsSeen, 100);

                    foreach ($rows as $rowNumber => $cells) {
                        $stringCells = [];
                        $nonEmpty = 0;
                        for ($c = 0; $c < $maxColsSeen; $c++) {
                            $val = $cells[$c] ?? '';
                            $s = $this->formatCellValue($val);
                            $stringCells[] = $s;
                            if ($s !== '') {
                                $nonEmpty++;
                            }
                        }
                        if ($nonEmpty === 0) {
                            continue;
                        }
                        $out[] = ['row' => $rowNumber, 'cells' => $stringCells];
                        if (count($out) >= $maxRows) {
                            break;
                        }
                    }
                    break;
                }
                $idx++;
            }

            return $out;
        } finally {
            $reader->close();
        }
    }

    /**
     * Detecta la fila más probable de encabezados de tabla.
     * Devuelve mismo shape que TemporaryModuleAdminSeedService::detectTableLayout().
     *
     * @return array{header_row:int,data_start_row:int,score:int,note:string}|null
     */
    public function detectTableLayout(string $path, int $maxScanRow = 80, int $sheetIndex = 0): ?array
    {
        $reader = $this->makeReader();
        $reader->open($path);
        try {
            $idx = 0;
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($idx === $sheetIndex) {
                    $rows = $this->fetchRows($sheet, 1, $maxScanRow + 200);
                    $bestRow = null;
                    $bestScore = 0;

                    for ($r = 1; $r <= $maxScanRow; $r++) {
                        $cells = $rows[$r] ?? [];
                        if ($cells === []) {
                            continue;
                        }
                        $nonEmpty = array_values(array_filter(array_map(fn ($s) => trim((string) $this->formatCellValue($s)), $cells), fn ($s) => $s !== ''));
                        if (count($nonEmpty) < 3) {
                            continue;
                        }
                        $longCells = 0;
                        foreach ($nonEmpty as $s) {
                            if (mb_strlen($s) > 70) {
                                $longCells++;
                            }
                        }
                        if ($longCells >= 2 || (count($nonEmpty) <= 2 && $longCells >= 1)) {
                            continue;
                        }

                        $norm = array_map(fn ($s) => $this->normalizeHeaderToken($s), $nonEmpty);
                        $joined = ' '.implode(' ', $norm).' ';

                        $score = 0;
                        if (preg_match('/\b(MUNICIPIO|MUNICIP)\b/u', $joined)) {
                            $score += 4;
                        }
                        if (preg_match('/\b(MICRORREGION|MICROREGION|MICRORREG)\b/u', $joined)) {
                            $score += 4;
                        }
                        if (preg_match('/\b(DELEGACION|DELEG)\b/u', $joined)) {
                            $score += 3;
                        }
                        if (preg_match('/\bACCION\b/u', $joined) && ! preg_match('/SOLICITUDES DE ACCIONES/u', $joined)) {
                            $score += 2;
                        }
                        if (preg_match('/\bESTATUS\b/u', $joined)) {
                            $score += 2;
                        }
                        if (preg_match('/\b(N°|NO\.|NUMERO|NUM|ITEM|#\b)/u', $joined)) {
                            $score += 1;
                        }

                        if ($score > $bestScore) {
                            $bestScore = $score;
                            $bestRow = $r;
                        }
                    }

                    if ($bestRow === null || $bestScore < 6) {
                        return null;
                    }

                    // Primera fila bajo el encabezado con varias celdas con contenido.
                    $dataStart = $bestRow + 1;
                    $highest = $maxScanRow + 200;
                    for ($r = $bestRow + 1; $r <= $highest; $r++) {
                        $cells = $rows[$r] ?? [];
                        $filled = 0;
                        foreach ($cells as $s) {
                            if (trim((string) $this->formatCellValue($s)) !== '') {
                                $filled++;
                            }
                        }
                        if ($filled >= 2) {
                            $dataStart = $r;
                            break;
                        }
                    }

                    return [
                        'header_row' => $bestRow,
                        'data_start_row' => $dataStart,
                        'score' => $bestScore,
                        'note' => "Tabla detectada: encabezados fila {$bestRow}, primer ítem fila {$dataStart}.",
                    ];
                }
                $idx++;
            }

            return null;
        } finally {
            $reader->close();
        }
    }

    /**
     * Escaneo en streaming para sugerencias de opciones por columna.
     * Aborta automáticamente si se acerca al 80% del memory_limit.
     *
     * @param  list<array{index:int,letter:string,label:string}>  $headers
     * @return array<int, array{select:list<string>,multiselect:list<string>}>
     */
    public function scanColumnSuggestions(
        string $path,
        int $headerRow,
        int $sheetIndex,
        array $headers,
        int $maxScanRows = 1500,
        int $maxOptionsPerColumn = 80,
        ?int $memoryBudget = null,
    ): array {
        $headerRow = max(1, $headerRow);

        $maxColFromHeaders = 0;
        foreach ($headers as $h) {
            $maxColFromHeaders = max($maxColFromHeaders, ((int) ($h['index'] ?? 0)) + 1);
        }
        $maxCol = $maxColFromHeaders > 0 ? min($maxColFromHeaders, 60) : 60;

        $selectBuckets = [];
        $multiBuckets = [];
        $rowsProcessed = 0;
        $endRow = $headerRow + max(50, $maxScanRows);

        $reader = $this->makeReader();
        $reader->open($path);
        try {
            $idx = 0;
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($idx === $sheetIndex) {
                    $rowNumber = 0;
                    foreach ($sheet->getRowIterator() as $row) {
                        $rowNumber++;
                        if ($rowNumber <= $headerRow) {
                            continue;
                        }
                        if ($rowNumber > $endRow) {
                            break;
                        }

                        $values = $row->toArray();
                        $i = 0;
                        foreach ($values as $cellVal) {
                            $colIdx = $i++;
                            if ($colIdx >= $maxCol) {
                                break;
                            }
                            $raw = trim($this->formatCellValue($cellVal));
                            if ($raw === '' || str_starts_with($raw, '=')) {
                                continue;
                            }
                            $this->pushSuggestionValue($selectBuckets[$colIdx], $raw, $maxOptionsPerColumn);
                            if (preg_match('/[\r\n,;|]/', $raw)) {
                                $parts = preg_split('/[\r\n,;|]+/u', $raw) ?: [];
                                foreach ($parts as $part) {
                                    $token = trim((string) $part);
                                    if ($token === '') {
                                        continue;
                                    }
                                    $this->pushSuggestionValue($multiBuckets[$colIdx], $token, $maxOptionsPerColumn);
                                }
                            }
                        }
                        $rowsProcessed++;

                        // Cada 500 filas, verificar memoria
                        if ($rowsProcessed % 500 === 0 && $memoryBudget !== null && $memoryBudget > 0) {
                            if (memory_get_usage(true) > $memoryBudget) {
                                break 2;
                            }
                        }
                    }
                    break;
                }
                $idx++;
            }
        } finally {
            $reader->close();
        }

        $result = [];
        $effectiveMax = $maxColFromHeaders > 0 ? $maxColFromHeaders : $maxCol;
        for ($colIdx = 0; $colIdx < $effectiveMax; $colIdx++) {
            $selectVals = isset($selectBuckets[$colIdx]) && is_array($selectBuckets[$colIdx])
                ? array_values($selectBuckets[$colIdx])
                : [];
            $multiVals = isset($multiBuckets[$colIdx]) && is_array($multiBuckets[$colIdx])
                ? array_values($multiBuckets[$colIdx])
                : [];
            if ($multiVals === [] && $selectVals !== []) {
                $multiVals = $selectVals;
            }
            $result[$colIdx] = [
                'select' => $selectVals,
                'multiselect' => $multiVals,
            ];
        }

        return $result;
    }

    /**
     * Lee un rango de filas (1-based) de una hoja Spout.
     * Devuelve `[rowNumber => [cellValue, cellValue, ...]]` con los valores
     * en bruto (sin formatear; el llamador hace `formatCellValue()`).
     *
     * @return array<int, list<mixed>>
     */
    private function fetchRows($sheet, int $startRow, int $endRow): array
    {
        $rows = [];
        $rowNumber = 0;
        foreach ($sheet->getRowIterator() as $row) {
            $rowNumber++;
            if ($rowNumber < $startRow) {
                continue;
            }
            if ($rowNumber > $endRow) {
                break;
            }
            $rows[$rowNumber] = $row->toArray();
        }

        return $rows;
    }

    private function makeReader(): XlsxReader
    {
        $options = new XlsxOptions();
        $options->SHOULD_FORMAT_DATES = true;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        return new XlsxReader($options);
    }

    /**
     * Convierte cualquier valor que devuelva Spout (string, int, float, bool,
     * DateTimeInterface, DateInterval, null) a string para previsualización.
     */
    private function formatCellValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_string($value)) {
            return trim($value);
        }
        if (is_bool($value)) {
            return $value ? 'VERDADERO' : 'FALSO';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            // Evita notación científica para enteros disfrazados de float.
            if (floor($value) === $value && abs($value) < 1e15) {
                return (string) (int) $value;
            }

            return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
        }
        if ($value instanceof DateTimeInterface) {
            $time = $value->format('H:i:s');
            if ($time === '00:00:00') {
                return $value->format('Y-m-d');
            }

            return $value->format('Y-m-d H:i:s');
        }
        if ($value instanceof DateInterval) {
            return $value->format('%H:%I:%S');
        }

        return trim((string) $value);
    }

    private function pushSuggestionValue(?array &$bucket, string $rawValue, int $maxOptions): void
    {
        if (! is_array($bucket)) {
            $bucket = [];
        }
        if (count($bucket) >= $maxOptions) {
            return;
        }
        $key = $this->normalizeSuggestionCompareKey($rawValue);
        if ($key === '' || isset($bucket[$key])) {
            return;
        }
        $bucket[$key] = $this->formatSuggestionDisplay($rawValue);
    }

    private function normalizeSuggestionCompareKey(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $value = mb_strtolower($value, 'UTF-8');
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        ]);
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;
        $value = preg_replace('/(?<=\p{L})\s+(?=\d)|(?<=\d)\s+(?=\p{L})/u', '', $value) ?: $value;

        return trim($value);
    }

    private function formatSuggestionDisplay(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?: $value);
        if ($value === '') {
            return '';
        }

        return mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function normalizeHeaderToken(string $s): string
    {
        $s = mb_strtoupper(preg_replace('/\s+/', ' ', trim($s)), 'UTF-8');

        return strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N']);
    }
}
