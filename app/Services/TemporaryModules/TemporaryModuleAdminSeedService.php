<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use App\Models\TemporaryModuleEntry;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\CSV\Options as CsvWriterOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Crea un módulo temporal + campos + registros precargados desde Excel.
 * Prioridad: columna Municipio → microrregión por BD. Si no hay columna Municipio, solo Microrregión (cabecera como etiqueta).
 */
class TemporaryModuleAdminSeedService
{
    public function __construct(
        private readonly TemporaryModuleExcelImportService $excelReader,
        private readonly TemporaryModuleSpoutReader $spoutReader,
        private readonly TemporaryModulePythonExcelService $pythonExcel,
    ) {}

    /**
     * Preview liviano: solo headers + filas de muestra. Pensado para que
     * funcione bajo límites estrictos de PHP-FPM (memory_limit=128M,
     * max_input_time=60). El análisis de opciones se hace bajo demanda en
     * un segundo paso vía analyzeColumnSuggestions().
     *
     * Si el archivo es .xlsx usa OpenSpout (streaming, baja memoria).
     * Para .xls cae a PhpSpreadsheet.
     */
    public function previewHeaders(UploadedFile $file, int $headerRow, int $sheetIndex = 0, bool $includeSuggestions = false): array
    {
        $this->raiseLimitsForFile($file);

        if ($this->spoutReader->supports($file)) {
            $preview = $this->pythonExcel->previewHeaders($file, $headerRow, $sheetIndex)
                ?? $this->previewHeadersViaSpout($file, $headerRow, $sheetIndex);
        } else {
            $preview = $this->excelReader->preview($file, $headerRow, false, $sheetIndex);
            try {
                $preview['preview_rows'] = $this->buildPreviewRows(
                    $file,
                    (int) ($preview['header_row'] ?? $headerRow),
                    (int) ($preview['sheet_index'] ?? $sheetIndex),
                    12
                );
            } catch (\Throwable $e) {
                Log::warning('Seed preview: preview_rows omitido por error: '.$e->getMessage());
                $preview['preview_rows'] = [];
            }
        }

        // Sugerencias deshabilitadas por defecto. El frontend pedirá un
        // segundo endpoint cuando el usuario quiera analizar opciones.
        $preview['column_suggestions'] = [];
        $preview['suggestions_pending'] = true;

        if ($includeSuggestions) {
            try {
                $preview['column_suggestions'] = $this->analyzeColumnSuggestions(
                    $file,
                    (int) ($preview['header_row'] ?? $headerRow),
                    (int) ($preview['sheet_index'] ?? $sheetIndex),
                    (array) ($preview['headers'] ?? [])
                );
                $preview['suggestions_pending'] = false;
            } catch (\Throwable $e) {
                Log::warning('Seed preview: column_suggestions omitido por error: '.$e->getMessage());
                $preview['column_suggestions_warning'] = 'No fue posible analizar opciones por columna.';
            }
        }

        return $preview;
    }

    /**
     * Calcula las sugerencias por columna (select/multiselect) con escaneo
     * adaptativo según el tamaño del archivo. Pensado para invocarse en una
     * segunda petición cuando el usuario lo solicita.
     *
     * Para XLSX usa OpenSpout (streaming) que escala con archivos grandes.
     * Para XLS cae a PhpSpreadsheet con chunks + límite de memoria.
     *
     * @param  list<array{index:int,letter:string,label:string}>  $headers
     * @return array<int, array{select:list<string>,multiselect:list<string>}>
     */
    public function analyzeColumnSuggestions(UploadedFile $file, int $headerRow, int $sheetIndex, array $headers): array
    {
        $this->raiseLimitsForFile($file);

        $sizeMb = $file->getSize() / 1_048_576;

        if ($this->spoutReader->supports($file)) {
            // Spout es eficiente en memoria → podemos escanear más filas sin riesgo.
            if ($sizeMb > 80) {
                $maxScanRows = 2000;
            } elseif ($sizeMb > 30) {
                $maxScanRows = 3000;
            } else {
                $maxScanRows = 5000;
            }
            $memoryBudget = $this->parseMemoryLimitToBytes((string) ini_get('memory_limit'));
            $memoryBudget = $memoryBudget > 0 ? (int) ($memoryBudget * 0.80) : 0;

            return $this->spoutReader->scanColumnSuggestions(
                $file->getRealPath(),
                $headerRow,
                $sheetIndex,
                $headers,
                $maxScanRows,
                80,
                $memoryBudget,
            );
        }

        // Fallback PhpSpreadsheet (.xls)
        if ($sizeMb > 80) {
            $maxScanRows = 200;
        } elseif ($sizeMb > 30) {
            $maxScanRows = 400;
        } elseif ($sizeMb > 10) {
            $maxScanRows = 800;
        } else {
            $maxScanRows = 1500;
        }

        return $this->buildColumnSuggestions(
            $file,
            $headerRow,
            $sheetIndex,
            $headers,
            $maxScanRows,
            80
        );
    }

    /**
     * Genera el preview (headers + sample rows) usando OpenSpout en streaming.
     *
     * @return array{success:bool, headers:list<array{index:int,letter:string,label:string}>, suggested_map:array, header_row:int, sheet_names:list<string>, sheet_index:int, preview_rows:list<array{row:int,cells:list<string>}>}
     */
    private function previewHeadersViaSpout(UploadedFile $file, int $headerRow, int $sheetIndex): array
    {
        $path = $file->getRealPath();

        try {
            $head = $this->spoutReader->previewHeaders($path, $headerRow, $sheetIndex);
            $resolvedHeaderRow = (int) ($head['header_row'] ?? $headerRow);
            $resolvedSheetIndex = (int) ($head['sheet_index'] ?? $sheetIndex);
        } catch (\Throwable $e) {
            Log::warning('Seed preview: Spout falló al leer headers, cayendo a PhpSpreadsheet: '.$e->getMessage());

            return $this->excelReader->preview($file, $headerRow, false, $sheetIndex);
        }

        $previewRows = [];
        $headerCount = count($head['headers'] ?? []);
        try {
            $previewRows = $this->spoutReader->previewRows(
                $path,
                $resolvedHeaderRow,
                $resolvedSheetIndex,
                12,
                $headerCount,
            );
        } catch (\Throwable $e) {
            Log::warning('Seed preview: Spout falló al leer preview_rows: '.$e->getMessage());
        }

        return [
            'success' => true,
            'headers' => $head['headers'] ?? [],
            'suggested_map' => [],
            'header_row' => $resolvedHeaderRow,
            'sheet_names' => $head['sheet_names'] ?? [],
            'sheet_index' => $resolvedSheetIndex,
            'preview_rows' => $previewRows,
        ];
    }

    /**
     * Sube memory_limit/set_time_limit de forma uniforme. ini_set sobre
     * memory_limit puede ser bloqueado por disable_functions en producción;
     * intentamos pero seguimos si falla.
     */
    private function raiseLimitsForFile(UploadedFile $file): void
    {
        $sizeMb = $file->getSize() / 1_048_576;
        if ($sizeMb > 80) {
            @ini_set('memory_limit', '2G');
            @set_time_limit(600);
        } elseif ($sizeMb > 20) {
            @ini_set('memory_limit', '1G');
            @set_time_limit(300);
        } else {
            @ini_set('memory_limit', '512M');
            @set_time_limit(180);
        }
    }

    /**
     * @return list<array{row:int,cells:list<string>}>
     */
    private function buildPreviewRows(UploadedFile $file, int $headerRow, int $sheetIndex, int $maxRows = 24): array
    {
        $sizeMb = $file->getSize() / 1_048_576;
        if ($sizeMb > 80) {
            @ini_set('memory_limit', '2G');
            @set_time_limit(600);
        } elseif ($sizeMb > 20) {
            @ini_set('memory_limit', '1G');
            @set_time_limit(300);
        }

        $headerRow = max(1, $headerRow);
        $endRow = $headerRow + max(8, $maxRows);

        $reader = IOFactory::createReaderForFile($file->getRealPath());
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        if (method_exists($reader, 'setReadFilter')) {
            $reader->setReadFilter(new class($endRow) implements IReadFilter {
                public function __construct(private int $maxRow) {}
                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return $row <= $this->maxRow;
                }
            });
        }

        $spreadsheet = $reader->load($file->getRealPath());
        $sheetNames = $spreadsheet->getSheetNames();
        $sheetIndex = max(0, min($sheetIndex, count($sheetNames) - 1));
        $sheet = $spreadsheet->getSheet($sheetIndex);

        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn($headerRow));
        $maxCol = max(1, min($maxCol, 30));

        $rows = [];
        for ($r = $headerRow; $r <= $endRow; $r++) {
            $cells = [];
            $nonEmpty = 0;
            for ($c = 1; $c <= $maxCol; $c++) {
                $letter = Coordinate::stringFromColumnIndex($c);
                $cell = trim((string) ($sheet->getCell($letter.$r)->getFormattedValue() ?? ''));
                $cells[] = $cell;
                if ($cell !== '') {
                    $nonEmpty++;
                }
            }
            if ($nonEmpty === 0) {
                continue;
            }
            $rows[] = ['row' => $r, 'cells' => $cells];
            if (count($rows) >= $maxRows) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param  list<array{index:int,letter:string,label:string}>  $headers
     * @return array<int, array{select:list<string>,multiselect:list<string>}>
     */
    private function buildColumnSuggestions(
        UploadedFile $file,
        int $headerRow,
        int $sheetIndex,
        array $headers,
        int $maxScanRows = 1500,
        int $maxOptionsPerColumn = 80,
    ): array {
        $this->raiseLimitsForFile($file);

        $headerRow = max(1, $headerRow);
        $endRow = $headerRow + max(50, $maxScanRows);

        // Calcular maxCol antes de cargar para limitar también el lector.
        $maxColFromHeaders = 0;
        foreach ($headers as $h) {
            $maxColFromHeaders = max($maxColFromHeaders, ((int) ($h['index'] ?? 0)) + 1);
        }
        $maxColCap = $maxColFromHeaders > 0 ? min($maxColFromHeaders, 40) : 40;

        $reader = IOFactory::createReaderForFile($file->getRealPath());
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        // Filtro que limita filas Y columnas — clave para reducir memoria
        // al cargar archivos grandes.
        if (method_exists($reader, 'setReadFilter')) {
            $reader->setReadFilter(new class($endRow, $maxColCap) implements IReadFilter {
                public function __construct(private int $maxRow, private int $maxColIdx) {}
                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    if ($row > $this->maxRow) return false;
                    $colIdx = Coordinate::columnIndexFromString($columnAddress);
                    return $colIdx <= $this->maxColIdx;
                }
            });
        }

        $spreadsheet = $reader->load($file->getRealPath());
        $sheetNames = $spreadsheet->getSheetNames();
        $sheetIndex = max(0, min($sheetIndex, count($sheetNames) - 1));
        $sheet = $spreadsheet->getSheet($sheetIndex);

        $maxCol = $maxColFromHeaders > 0
            ? $maxColFromHeaders
            : Coordinate::columnIndexFromString($sheet->getHighestDataColumn($headerRow));
        $maxCol = max(1, min($maxCol, 40));

        $startRow = $headerRow + 1;
        $highest = min((int) $sheet->getHighestDataRow(), $endRow);
        $selectBuckets = [];
        $multiBuckets = [];

        // Umbral de memoria: si rebasamos el 80% del límite, abortamos el
        // escaneo para no morir con fatal error. Devolvemos lo que tengamos.
        $memoryLimit = $this->parseMemoryLimitToBytes((string) ini_get('memory_limit'));
        $memoryBudget = $memoryLimit > 0 ? (int) ($memoryLimit * 0.80) : 0;
        $rowsProcessed = 0;
        // Chunk de filas a procesar por iteración (rangeToArray es más
        // eficiente que getCell celda por celda y libera referencias).
        $chunkSize = 200;

        for ($chunkStart = $startRow; $chunkStart <= $highest; $chunkStart += $chunkSize) {
            $chunkEnd = min($chunkStart + $chunkSize - 1, $highest);
            $rangeStart = 'A'.$chunkStart;
            $rangeEnd = Coordinate::stringFromColumnIndex($maxCol).$chunkEnd;
            $range = $rangeStart.':'.$rangeEnd;
            try {
                // rangeToArray con formato visible (4° arg true) y solo valores.
                $matrix = $sheet->rangeToArray($range, null, true, true, false);
            } catch (\Throwable $e) {
                Log::warning('Seed preview: rangeToArray falló para '.$range.': '.$e->getMessage());
                break;
            }
            foreach ($matrix as $rowArr) {
                if (! is_array($rowArr)) continue;
                $i = 0;
                foreach ($rowArr as $cellVal) {
                    $idx = $i++;
                    if ($idx >= $maxCol) break;
                    $raw = trim((string) ($cellVal ?? ''));
                    if ($raw === '' || str_starts_with($raw, '=')) continue;

                    $this->pushSuggestionValue($selectBuckets[$idx], $raw, $maxOptionsPerColumn);

                    if (preg_match('/[\r\n,;|]/', $raw)) {
                        $parts = preg_split('/[\r\n,;|]+/u', $raw) ?: [];
                        foreach ($parts as $part) {
                            $token = trim((string) $part);
                            if ($token === '') continue;
                            $this->pushSuggestionValue($multiBuckets[$idx], $token, $maxOptionsPerColumn);
                        }
                    }
                }
                $rowsProcessed++;
            }
            unset($matrix);

            // Verificación de memoria cada chunk
            if ($memoryBudget > 0 && memory_get_usage(true) > $memoryBudget) {
                Log::warning('Seed preview: corte preventivo de buildColumnSuggestions por memoria; filas leídas='.$rowsProcessed);
                break;
            }
        }

        // Liberar el spreadsheet completo (a veces los workers reutilizados
        // mantienen referencias innecesarias).
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $sheet);

        $result = [];
        foreach (range(0, $maxCol - 1) as $idx) {
            $selectVals = isset($selectBuckets[$idx]) && is_array($selectBuckets[$idx])
                ? array_values($selectBuckets[$idx])
                : [];
            $multiVals = isset($multiBuckets[$idx]) && is_array($multiBuckets[$idx])
                ? array_values($multiBuckets[$idx])
                : [];
            if ($multiVals === [] && $selectVals !== []) {
                $multiVals = $selectVals;
            }
            $result[$idx] = [
                'select' => $selectVals,
                'multiselect' => $multiVals,
            ];
        }

        return $result;
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

    /**
     * Convierte el valor de ini_get('memory_limit') a bytes.
     * Devuelve 0 si es ilimitado (-1) o no parseable.
     */
    private function parseMemoryLimitToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0;
        }
        $unit = strtoupper(substr($value, -1));
        $num = (float) $value;
        switch ($unit) {
            case 'G': return (int) ($num * 1024 * 1024 * 1024);
            case 'M': return (int) ($num * 1024 * 1024);
            case 'K': return (int) ($num * 1024);
            default:  return is_numeric($value) ? (int) $value : 0;
        }
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
        $tokens = preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens !== []) {
            $tokens = array_map(fn ($t) => $this->singularizeSuggestionToken((string) $t), $tokens);
            $value = implode(' ', $tokens);
        }

        // Colapsa espacios entre letras y números (ej: "A 1" => "A1").
        $value = preg_replace('/(?<=\p{L})\s+(?=\d)|(?<=\d)\s+(?=\p{L})/u', '', $value) ?: $value;

        return trim($value);
    }

    private function singularizeSuggestionToken(string $token): string
    {
        $token = trim($token);
        $len = mb_strlen($token, 'UTF-8');
        if ($len <= 3) {
            return $token;
        }

        if (preg_match('/ces$/u', $token) === 1 && $len > 4) {
            return mb_substr($token, 0, -3, 'UTF-8').'z';
        }

        if (preg_match('/[aeiou]s$/u', $token) === 1 && $len > 4) {
            return mb_substr($token, 0, -1, 'UTF-8');
        }

        if (preg_match('/[bcdfghjklmnñpqrstvwxyz]es$/u', $token) === 1 && $len > 5) {
            return mb_substr($token, 0, -2, 'UTF-8');
        }

        return $token;
    }

    private function formatSuggestionDisplay(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?: $value);
        if ($value === '') {
            return '';
        }

        return mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Detecta la fila de encabezados de la tabla de ítems (p. ej. N°, MICROREGION, MUNICIPIO, ACCION)
     * ignorando bloques superiores como KPIs o títulos largos en una sola celda.
     *
     * Para XLSX usa OpenSpout. Para XLS cae a PhpSpreadsheet.
     *
     * @return array{header_row:int,data_start_row:int,score:int,note:string}|null
     */
    public function detectTableLayout(UploadedFile $file, int $maxScanRow = 80, int $sheetIndex = 0): ?array
    {
        $path = $file->getRealPath();

        if ($this->spoutReader->supports($file)) {
            try {
                return $this->spoutReader->detectTableLayout($path, $maxScanRow, $sheetIndex);
            } catch (\Throwable $e) {
                Log::warning('detectTableLayout Spout falló, fallback PhpSpreadsheet: '.$e->getMessage());
                // continúa con PhpSpreadsheet abajo
            }
        }

        $reader = IOFactory::createReaderForFile($path);

        // Subir memoria para archivos grandes
        $sizeMb = $file->getSize() / 1_048_576;
        if ($sizeMb > 80) {
            @ini_set('memory_limit', '1G');
            @set_time_limit(300);
        } elseif ($sizeMb > 20) {
            @ini_set('memory_limit', '512M');
            @set_time_limit(180);
        }

        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        // Solo leemos las primeras filas necesarias para detección
        $scanLimit = $maxScanRow + 200; // margen para detectFirstDataRow
        if (method_exists($reader, 'setReadFilter')) {
            $reader->setReadFilter(new class($scanLimit) implements IReadFilter {
                public function __construct(private int $maxRow) {}
                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return $row <= $this->maxRow;
                }
            });
        }

        $spreadsheet = $reader->load($path);
        $sheetNames = $spreadsheet->getSheetNames();
        $sheetIndex = max(0, min($sheetIndex, count($sheetNames) - 1));
        $sheet = $spreadsheet->getSheet($sheetIndex);
        $maxScanRow = min($maxScanRow, (int) $sheet->getHighestRow());
        $maxScanRow = max(1, $maxScanRow);

        $bestRow = null;
        $bestScore = 0;

        for ($r = 1; $r <= $maxScanRow; $r++) {
            $cells = $this->rowCellStrings($sheet, $r);
            $nonEmpty = array_filter($cells, fn ($s) => trim((string) $s) !== '');
            if (count($nonEmpty) < 3) {
                continue;
            }

            $longCells = 0;
            foreach ($nonEmpty as $s) {
                if (mb_strlen(trim($s)) > 70) {
                    $longCells++;
                }
            }
            // Fila título tipo "SOLICITUDES DE ACCIONES..." suele ser 1 celda muy larga o pocas celdas con mucho texto
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

        $dataStart = $this->detectFirstDataRow($sheet, $bestRow, $maxScanRow);

        return [
            'header_row' => $bestRow,
            'data_start_row' => $dataStart,
            'score' => $bestScore,
            'note' => "Tabla detectada: encabezados fila {$bestRow}, primer ítem fila {$dataStart}.",
        ];
    }

    /**
     * Primera fila bajo el encabezado con varias celdas con contenido (evita filas vacías por merges).
     */
    private function detectFirstDataRow(Worksheet $sheet, int $headerRow, int $maxScanRow): int
    {
        $highest = min((int) $sheet->getHighestRow(), $maxScanRow + 200);
        for ($r = $headerRow + 1; $r <= $highest; $r++) {
            $cells = $this->rowCellStrings($sheet, $r);
            $filled = 0;
            foreach ($cells as $s) {
                if (trim((string) $s) !== '') {
                    $filled++;
                }
            }
            if ($filled >= 2) {
                return $r;
            }
        }

        return $headerRow + 1;
    }

    /** @return list<string> por columna A.. hasta última con dato en esa fila */
    private function rowCellStrings(Worksheet $sheet, int $row): array
    {
        $lastCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn($row));
        $lastCol = max(1, min($lastCol, 40));
        $out = [];
        for ($c = 1; $c <= $lastCol; $c++) {
            $letter = Coordinate::stringFromColumnIndex($c);
            $v = $sheet->getCell($letter.$row)->getValue();
            $out[] = trim((string) ($v ?? ''));
        }

        return $out;
    }

    private function normalizeHeaderToken(string $s): string
    {
        $s = mb_strtoupper(preg_replace('/\s+/', ' ', trim($s)), 'UTF-8');

        return strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N']);
    }

    /**
     * @param  array<int, true>  $fieldColumnIndices  índices 0-based de columnas que serán campos del módulo
     * @param  array{created:int,skipped:int,unmatched:list<array{row:int,reason:string}>}  $stats  out
     */
    public function createModuleFromExcel(
        int $adminUserId,
        string $name,
        ?string $description,
        ?Carbon $expiresAt,
        bool $isIndefinite,
        UploadedFile $file,
        int $headerRow,
        int $dataStartRow,
        int $colMicrorregion,
        int $colMunicipio,
        array $fieldColumnIndices,
        array $fieldTypesByColumn,
        array $fieldOptionsByColumn,
        array $fieldUnificationsByColumn,
        array &$stats,
        int $sheetIndex = 0,
        array $encryptionConfig = [],
    ): TemporaryModule {
        $hasMunCol = $colMunicipio >= 0;
        $hasMrCol = $colMicrorregion >= 0;
        if (! $hasMunCol && ! $hasMrCol) {
            throw new \InvalidArgumentException('Debe indicarse columna Municipio o Microrregión.');
        }

        $headerRow = max(1, $headerRow);
        $dataStartRow = max($headerRow + 1, $dataStartRow);

        // Subir memoria para archivos grandes
        $sizeMb = $file->getSize() / 1_048_576;
        if ($sizeMb > 80) {
            @ini_set('memory_limit', '1G');
            @set_time_limit(300);
        } elseif ($sizeMb > 20) {
            @ini_set('memory_limit', '512M');
            @set_time_limit(180);
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheetNames = $spreadsheet->getSheetNames();
        $sheetIndex = max(0, min($sheetIndex, count($sheetNames) - 1));
        $sheet = $spreadsheet->getSheet($sheetIndex);
        $highestRow = (int) $sheet->getHighestDataRow();
        // Fin real de la tabla por columnas clave (evita timeout: no barrer 12k×40 celdas)
        $scanEnd = max($highestRow, $dataStartRow);
        $colsForExtent = array_values(array_filter(array_unique(array_map('intval', array_merge(
            array_filter([$colMicrorregion, $colMunicipio], fn ($c) => $c >= 0),
            $fieldColumnIndices ?: []
        ))), fn ($ci) => $ci >= 0));
        foreach ($colsForExtent as $ci) {
            if ($ci < 0) {
                continue;
            }
            $letter = Coordinate::stringFromColumnIndex($ci + 1);
            try {
                $scanEnd = max($scanEnd, (int) $sheet->getHighestDataRow($letter));
            } catch (\Throwable) {
            }
        }
        $scanEnd = min($scanEnd + 80, $dataStartRow + 6000);
        $highestColLetter = $sheet->getHighestDataColumn($headerRow);
        $maxCol = Coordinate::columnIndexFromString($highestColLetter);

        $headers = [];
        for ($c = 1; $c <= $maxCol; $c++) {
            $letter = Coordinate::stringFromColumnIndex($c);
            $headers[$c - 1] = trim((string) $sheet->getCell($letter.$headerRow)->getValue());
        }

        $usedKeys = [];
        $preparedFields = [];
        $sort = 1;
        foreach ($fieldColumnIndices as $idx) {
            $idx = (int) $idx;
            if ($idx < 0 || $idx >= count($headers)) {
                continue;
            }
            $label = $headers[$idx] !== '' ? $headers[$idx] : 'Columna_'.($idx + 1);
            $key = Str::slug($label, '_');
            if ($key === '') {
                $key = 'campo_'.$sort;
            }
            $base = $key;
            $n = 2;
            while (in_array($key, $usedKeys, true)) {
                $key = $base.'_'.$n;
                $n++;
            }
            $usedKeys[] = $key;
            $fieldType = strtolower(trim((string) ($fieldTypesByColumn[$idx] ?? 'text')));
            if (! in_array($fieldType, ['text', 'textarea', 'number', 'date', 'datetime', 'select', 'multiselect', 'municipio', 'boolean', 'semaforo'], true)) {
                $fieldType = 'text';
            }
            $rawOptions = trim((string) ($fieldOptionsByColumn[$idx] ?? ''));
            $rawUnifications = trim((string) ($fieldUnificationsByColumn[$idx] ?? ''));
            $unificationMap = $this->parseSeedUnificationRules($fieldType, $rawUnifications);
            $preparedFields[] = [
                'label' => $label,
                'comment' => null,
                'key' => $key,
                'type' => $fieldType,
                'is_required' => false,
                'options' => $this->normalizeSeedFieldOptions($fieldType, $rawOptions, $unificationMap),
                'unifications' => $unificationMap,
                'sort_order' => $sort,
                'subsection_index' => null,
            ];
            $sort++;
        }

        if ($preparedFields === []) {
            throw new \InvalidArgumentException('Selecciona al menos una columna como campo del módulo.');
        }

        $indexToKey = [];
        $fieldCols = array_values($fieldColumnIndices);
        foreach ($fieldCols as $i => $colIdx) {
            if (isset($preparedFields[$i])) {
                $indexToKey[(int) $colIdx] = [
                    'key' => $preparedFields[$i]['key'],
                    'type' => (string) ($preparedFields[$i]['type'] ?? 'text'),
                    'options' => $preparedFields[$i]['options'] ?? null,
                    'unifications' => $preparedFields[$i]['unifications'] ?? [],
                ];
            }
        }

        $municipiosRows = DB::table('municipios as mu')
            ->join('microrregiones as mr', 'mr.id', '=', 'mu.microrregion_id')
            ->select(['mu.id', 'mu.municipio', 'mu.microrregion_id', 'mr.microrregion as mr_num'])
            ->get();

        /** @var array<int, list<object>> microrregion_id => filas municipio */
        $municipiosByMicro = [];
        foreach ($municipiosRows as $m) {
            $mid = (int) $m->microrregion_id;
            $municipiosByMicro[$mid][] = $m;
        }

        $microPorNumero = [];
        $microByNormToken = [];
        foreach (DB::table('microrregiones')->select(['id', 'microrregion', 'cabecera'])->get() as $mr) {
            $n = trim((string) $mr->microrregion);
            $microPorNumero[$n] = (int) $mr->id;
            $num = (int) preg_replace('/\D/', '', $n) ?: (int) $n;
            if ($num > 0) {
                $microPorNumero[(string) $num] = (int) $mr->id;
                $microPorNumero[str_pad((string) $num, 2, '0', STR_PAD_LEFT)] = (int) $mr->id;
            }
            $cab = $this->normalizeMunicipioName((string) ($mr->cabecera ?? ''));
            if (mb_strlen($cab) >= 4) {
                $microByNormToken[$cab] = (int) $mr->id;
            }
            foreach (preg_split('/\s+/', $cab) ?: [] as $tok) {
                if (mb_strlen((string) $tok) >= 5) {
                    $microByNormToken[(string) $tok] = (int) $mr->id;
                }
            }
        }

        $municipiosGlobalNorm = [];
        foreach ($municipiosRows as $m) {
            $k = $this->normalizeMunicipioName($m->municipio);
            $municipiosGlobalNorm[$k][] = $m;
        }

        $microLabels = [];
        $cabeceraPorMicro = [];
        foreach (DB::table('microrregiones')->select(['id', 'microrregion', 'cabecera'])->get() as $mr) {
            $n = str_pad(trim((string) $mr->microrregion), 2, '0', STR_PAD_LEFT);
            $id = (int) $mr->id;
            $microLabels[$id] = 'MR '.$n.' '.trim((string) ($mr->cabecera ?? ''));
            $cabeceraPorMicro[$id] = trim((string) ($mr->cabecera ?? ''));
        }

        $stats = ['created' => 0, 'skipped' => 0, 'unmatched' => [], 'discarded' => []];

        $slug = Str::slug($name);
        /** @var TemporaryModuleSlugService $slugSvc */
        $slugSvc = app(TemporaryModuleSlugService::class);
        $slugSvc->reclaimSlugForCreate($slug);
        $baseSlug = $slug;
        $suf = 2;
        while (TemporaryModule::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suf;
            $suf++;
            if ($suf > 999) {
                $slug = $baseSlug.'-'.substr(sha1(uniqid((string) mt_rand(), true)), 0, 8);
                break;
            }
        }

        $expires = $isIndefinite ? null : $expiresAt;

        return DB::transaction(function () use (
            $adminUserId,
            $name,
            $slug,
            $description,
            $expires,
            $preparedFields,
            $sheet,
            $dataStartRow,
            $scanEnd,
            $maxCol,
            $colMicrorregion,
            $colMunicipio,
            $hasMrCol,
            $hasMunCol,
            $fieldColumnIndices,
            $indexToKey,
            $municipiosByMicro,
            $microPorNumero,
            $microByNormToken,
            $municipiosGlobalNorm,
            $microLabels,
            $cabeceraPorMicro,
            &$stats,
            $encryptionConfig,
        ): TemporaryModule {
            $moduleAttrs = [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'expires_at' => $expires,
                'is_active' => true,
                'applies_to_all' => false,
                'created_by' => $adminUserId,
            ];
            $this->applyEncryptionConfigToAttrs($moduleAttrs, $encryptionConfig);
            $module = TemporaryModule::query()->create($moduleAttrs);

            $module->fields()->createMany($preparedFields);

            $assignedUserIds = [];
            $microrregionIdsUsed = [];
            $lastMicroId = null;
            $lastMunicipioCell = '';
            $lastFieldCarry = [];
            $mc = [$maxCol, max($fieldColumnIndices ?: [0]) + 5];
            if ($hasMrCol) {
                $mc[] = $colMicrorregion + 5;
            }
            if ($hasMunCol) {
                $mc[] = $colMunicipio + 5;
            }
            $maxColScan = min(40, max($mc));
            $lastDataRow = $scanEnd;

            for ($row = $dataStartRow; $row <= $scanEnd; $row++) {
                $cellMr = $hasMrCol ? $this->cellStrMerged($sheet, $colMicrorregion, $row) : '';
                $cellMun = $hasMunCol ? $this->cellStrMerged($sheet, $colMunicipio, $row) : '';

                if ($hasMrCol && $cellMr !== '') {
                    $parsedMr = $this->resolveMicrorregionId($cellMr, $microPorNumero)
                        ?? $this->resolveMicrorregionByName($cellMr, $microByNormToken);
                    if ($parsedMr !== null) {
                        $lastMicroId = $parsedMr;
                    }
                }
                if ($hasMunCol && $cellMun !== '') {
                    $lastMunicipioCell = $cellMun;
                }
                $microId = $lastMicroId ?? ($hasMrCol ? ($this->resolveMicrorregionId($cellMr, $microPorNumero)
                    ?? $this->resolveMicrorregionByName($cellMr, $microByNormToken)) : null);

                $rowHasAny = false;
                for ($c = 1; $c <= $maxColScan; $c++) {
                    if ($this->cellStrMerged($sheet, $c - 1, $row) !== '') {
                        $rowHasAny = true;
                        break;
                    }
                }
                $insideDataBlock = $row <= $lastDataRow && $row >= $dataStartRow;
                $differsFromCarry = false;
                foreach ($fieldColumnIndices as $ci) {
                    $ci = (int) $ci;
                    $now = $this->cellStrMerged($sheet, $ci, $row);
                    $prev = $lastFieldCarry[$ci] ?? '';
                    if ($now !== '' && $now !== $prev) {
                        $differsFromCarry = true;
                        break;
                    }
                }
                $itemColHint = $this->cellStrMerged($sheet, 0, $row);
                $itemColHint2 = $this->cellStrMerged($sheet, 1, $row);
                $looksLikeItemRow = (preg_match('/^\s*\d+\s*$/', $itemColHint) || preg_match('/^\s*\d+\s*$/', $itemColHint2));
                if (! $looksLikeItemRow && preg_match('/^\s*\d{1,4}\s*$/', trim($itemColHint.''))) {
                    $looksLikeItemRow = true;
                }

                $hasGeo = $hasMunCol ? ($lastMunicipioCell !== '' || $cellMun !== '') : ($lastMicroId !== null);
                if (! $rowHasAny && $insideDataBlock && $hasGeo) {
                    if ($looksLikeItemRow || $differsFromCarry) {
                        $rowHasAny = true;
                    }
                }
                if (! $rowHasAny && $hasGeo && $row <= $scanEnd && $looksLikeItemRow) {
                    $rowHasAny = true;
                }
                if (! $rowHasAny) {
                    continue;
                }

                if ($hasMunCol) {
                    $municipioSearch = $cellMun !== '' ? $cellMun : $lastMunicipioCell;
                    if ($municipioSearch === '') {
                        $stats['skipped']++;
                        $this->pushDiscarded($stats, $sheet, $row, 'Sin municipio en fila', $colMicrorregion, $colMunicipio, $fieldColumnIndices, $cellMr, '', $hasMrCol, $hasMunCol, null, null);

                        continue;
                    }
                    // Si el Excel trae delegación/microrregión, úsala como referencia.
                    // Un municipio que no pertenezca a esa MR se descarta para corrección manual.
                    $municipioDB = $microId !== null
                        ? $this->resolveMunicipioInMicrorregion($municipioSearch, $microId, $municipiosByMicro)
                        : $this->resolveMunicipioGlobal($municipioSearch, null, $municipiosGlobalNorm, $municipiosByMicro);
                    if (! $municipioDB) {
                        $stats['skipped']++;
                        if (count($stats['unmatched']) < 150) {
                            $stats['unmatched'][] = ['row' => $row, 'reason' => 'Municipio: '.$municipioSearch];
                        }
                        $entryPayload = $this->buildSeedRowFieldPayload(
                            $sheet,
                            $row,
                            $fieldColumnIndices,
                            $indexToKey,
                            $lastFieldCarry,
                            $municipiosGlobalNorm,
                            $municipiosByMicro
                        );
                        $suggestions = $this->suggestMunicipiosForDiscard($municipioSearch, $microId, $municipiosGlobalNorm, $microLabels);
                        $this->pushDiscarded(
                            $stats,
                            $sheet,
                            $row,
                            'Municipio no resuelto: '.$municipioSearch,
                            $colMicrorregion,
                            $colMunicipio,
                            $fieldColumnIndices,
                            $cellMr,
                            $municipioSearch,
                            $hasMrCol,
                            $hasMunCol,
                            $entryPayload,
                            $suggestions,
                        );

                        continue;
                    }
                    $microrregionId = (int) $municipioDB->microrregion_id;
                    $userId = $this->resolveUserIdForMicrorregion($microrregionId) ?? $adminUserId;
                    $data = [
                        '_microrregion_reporte' => $microLabels[$microrregionId] ?? ('MR '.$microrregionId),
                        '_municipio_reporte' => (string) $municipioDB->municipio,
                    ];
                } else {
                    // Solo microrregión (sin columna municipio)
                    if ($microId === null) {
                        $stats['skipped']++;
                        $this->pushDiscarded($stats, $sheet, $row, 'Sin microrregión en fila', $colMicrorregion, $colMunicipio, $fieldColumnIndices, $cellMr, '', $hasMrCol, $hasMunCol, null, null);

                        continue;
                    }
                    $microrregionId = $microId;
                    $userId = $this->resolveUserIdForMicrorregion($microrregionId) ?? $adminUserId;
                    $cab = $cabeceraPorMicro[$microrregionId] ?? '';
                    $data = [
                        '_microrregion_reporte' => $microLabels[$microrregionId] ?? ('MR '.$microrregionId),
                        '_municipio_reporte' => $cab !== '' ? $cab.' (MR)' : 'MR '.$microrregionId,
                    ];
                }

                foreach ($fieldColumnIndices as $colIdx) {
                    $colIdx = (int) $colIdx;
                    $descriptor = $indexToKey[$colIdx] ?? null;
                    if (! is_array($descriptor) || ! isset($descriptor['key'])) {
                        continue;
                    }
                    $key = (string) $descriptor['key'];
                    $raw = $this->cellStrMerged($sheet, $colIdx, $row);
                    if ($raw !== '') {
                        $lastFieldCarry[$colIdx] = $raw;
                    }
                    $resolved = $raw !== '' ? $raw : ($lastFieldCarry[$colIdx] ?? '');
                    $data[$key] = $this->normalizeSeedFieldValue(
                        $resolved,
                        (string) ($descriptor['type'] ?? 'text'),
                        $descriptor['options'] ?? null,
                        $municipiosGlobalNorm,
                        $municipiosByMicro,
                        is_array($descriptor['unifications'] ?? null) ? $descriptor['unifications'] : []
                    );
                }

                // Ignora filas de totales/resumen (ej. =SUM(...)) cuando no representan un ítem real.
                $fieldRawValues = [];
                foreach ($fieldColumnIndices as $colIdx) {
                    $fieldRawValues[] = $this->cellStrMerged($sheet, (int) $colIdx, $row);
                }
                $hasAggregateFormula = false;
                $hasNonFormulaValue = false;
                foreach ($fieldRawValues as $v) {
                    $txt = trim((string) $v);
                    if ($txt === '') {
                        continue;
                    }
                    if ($this->isAggregateFormulaValue($txt)) {
                        $hasAggregateFormula = true;
                        continue;
                    }
                    $hasNonFormulaValue = true;
                }
                if (! $looksLikeItemRow && $hasAggregateFormula && ! $hasNonFormulaValue) {
                    continue;
                }

                $data['_fila_excel'] = (string) $row;

                TemporaryModuleEntry::query()->create([
                    'temporary_module_id' => $module->id,
                    'user_id' => $userId,
                    'microrregion_id' => $microrregionId,
                    'data' => $data,
                    'main_image_field_key' => null,
                    'submitted_at' => Carbon::now(),
                ]);
                $microrregionIdsUsed[$microrregionId] = true;
                $assignedUserIds[$userId] = true;
                $stats['created']++;
            }

            /** Todos los delegados/enlaces de cada MR con registros pueden ver el módulo y dar seguimiento */
            $accessService = app(TemporaryModuleAccessService::class);
            $allTargetUserIds = $accessService->userIdsForMicrorregionIds(array_keys($microrregionIdsUsed));
            if ($allTargetUserIds === []) {
                $allTargetUserIds = array_keys($assignedUserIds);
            }
            $module->targetUsers()->sync($allTargetUserIds);

            $module->seed_discard_log = array_values($stats['discarded'] ?? []);
            $module->save();

            return $module;
        });
    }

    /**
     * @param  array<int>  $fieldColumnIndices
     */
    /**
     * @param  array<int, string|null>  $entryPayload  clave de campo => valor (para reintentar registro desde el log)
     * @param  list<array{id:int, municipio:string, microrregion_id:int, label:string}>|null  $suggestions
     */
    private function pushDiscarded(
        array &$stats,
        Worksheet $sheet,
        int $row,
        string $reason,
        int $colMicrorregion,
        int $colMunicipio,
        array $fieldColumnIndices,
        string $cellMr,
        string $municipioCell,
        bool $hasMrCol = true,
        bool $hasMunCol = true,
        ?array $entryPayload = null,
        ?array $suggestions = null,
    ): void {
        $accion = '';
        foreach ($fieldColumnIndices as $ci) {
            $t = $this->cellStrMerged($sheet, (int) $ci, $row);
            if (mb_strlen($t) > mb_strlen($accion)) {
                $accion = $t;
            }
        }
        if ($accion === '') {
            foreach ($fieldColumnIndices as $ci) {
                $t = $this->cellStrMerged($sheet, (int) $ci, $row);
                if ($t !== '') {
                    $accion = $t;
                    break;
                }
            }
        }
        if (mb_strlen($accion) > 220) {
            $accion = mb_substr($accion, 0, 217).'…';
        }
        $entry = [
            'discard_uid' => (string) Str::uuid(),
            'row' => $row,
            'reason' => $reason,
            'microrregion' => ($hasMrCol && $cellMr !== '') ? $cellMr : null,
            'municipio' => ($hasMunCol && $municipioCell !== '') ? $municipioCell : null,
            'accion' => $accion !== '' ? $accion : null,
        ];
        if (is_array($entryPayload) && $entryPayload !== []) {
            $entry['entry_payload'] = $entryPayload;
        }
        if (is_array($suggestions) && $suggestions !== []) {
            $entry['municipio_suggestions'] = $suggestions;
        }
        $stats['discarded'][] = $entry;
    }

    /**
     * Datos de campos del módulo para una fila (misma lógica que al crear el registro), sin tocar lastFieldCarry global.
     *
     * @param  array<int, mixed>  $lastFieldCarrySnapshot
     * @return array<string, string>
     */
    private function buildSeedRowFieldPayload(
        Worksheet $sheet,
        int $row,
        array $fieldColumnIndices,
        array $indexToKey,
        array $lastFieldCarrySnapshot,
        array $municipiosGlobalNorm,
        array $municipiosByMicro,
    ): array {
        $carry = $lastFieldCarrySnapshot;
        $data = [];
        foreach ($fieldColumnIndices as $colIdx) {
            $colIdx = (int) $colIdx;
            $descriptor = $indexToKey[$colIdx] ?? null;
            if (! is_array($descriptor) || ! isset($descriptor['key'])) {
                continue;
            }
            $key = (string) $descriptor['key'];
            $raw = $this->cellStrMerged($sheet, $colIdx, $row);
            if ($raw !== '') {
                $carry[$colIdx] = $raw;
            }
            $resolved = $raw !== '' ? $raw : ($carry[$colIdx] ?? '');
            $data[$key] = $this->normalizeSeedFieldValue(
                $resolved,
                (string) ($descriptor['type'] ?? 'text'),
                $descriptor['options'] ?? null,
                $municipiosGlobalNorm,
                $municipiosByMicro,
                is_array($descriptor['unifications'] ?? null) ? $descriptor['unifications'] : []
            );
        }
        $data['_fila_excel'] = (string) $row;

        return $data;
    }

    /**
     * Sugerencias de municipio del catálogo (para el log de descartes).
     *
     * @param  array<string, list<object>>  $municipiosGlobalNorm
     * @param  array<int, string>  $microLabels
     * @return list<array{id:int, municipio:string, microrregion_id:int, label:string}>
     */
    private function suggestMunicipiosForDiscard(
        string $municipioSearch,
        ?int $hintMicroId,
        array $municipiosGlobalNorm,
        array $microLabels,
    ): array {
        $raw = trim($municipioSearch);
        // Delimitador # para no chocar con / dentro de la clase; comillas dobles para que \s sea espacio en regex.
        $variants = preg_split("#\s*[/\\\\|]+\s*|\s*,\s*#u", $raw) ?: [];
        $variants = array_values(array_filter(array_map('trim', $variants)));
        if ($variants === []) {
            $variants = [$raw];
        }
        $bestById = [];
        foreach ($variants as $variant) {
            $normV = $this->normalizeMunicipioName($variant);
            if ($normV === '') {
                continue;
            }
            foreach ($municipiosGlobalNorm as $rows) {
                foreach ($rows as $m) {
                    $id = (int) $m->id;
                    $dbN = $this->normalizeMunicipioName($m->municipio);
                    $cCell = $this->compactAlnum($normV);
                    $cDb = $this->compactAlnum($dbN);
                    $score = 0.0;
                    if ($dbN === $normV) {
                        $score = 100;
                    } elseif ($cCell !== '' && $cCell === $cDb) {
                        $score = 98;
                    } elseif (str_contains($dbN, $normV) && mb_strlen($normV) >= 4) {
                        $score = 88;
                    } elseif (str_contains($normV, $dbN) && mb_strlen($dbN) >= 5) {
                        $score = 82;
                    } else {
                        similar_text($cCell, $cDb, $pct);
                        if ($pct >= 42) {
                            $score = min(78.0, $pct * 0.82);
                        }
                    }
                    if ($hintMicroId !== null && (int) $m->microrregion_id === $hintMicroId) {
                        $score += 4;
                    }
                    if ($score < 40) {
                        continue;
                    }
                    if (! isset($bestById[$id]) || $score > $bestById[$id]['_s']) {
                        $mid = (int) $m->microrregion_id;
                        $bestById[$id] = [
                            '_s' => $score,
                            'id' => $id,
                            'municipio' => (string) $m->municipio,
                            'microrregion_id' => $mid,
                            'label' => $microLabels[$mid] ?? ('MR '.$mid),
                        ];
                    }
                }
            }
        }
        $list = array_values($bestById);
        usort($list, fn ($a, $b) => $b['_s'] <=> $a['_s']);
        $list = array_slice($list, 0, 10);
        foreach ($list as &$item) {
            unset($item['_s']);
        }
        unset($item);

        return $list;
    }

    /**
     * Crea el registro a partir de una fila del log y actualiza el JSON del módulo.
     *
     * @return array{entry: TemporaryModuleEntry, seed_discard_log: array<int, mixed>}
     */
    public function registerDiscardedSeedRow(
        TemporaryModule $module,
        string $discardUid,
        int $municipioId,
        int $actingUserId,
    ): array {
        $discardUid = trim($discardUid);
        if ($discardUid === '') {
            throw ValidationException::withMessages(['discard_uid' => 'Identificador de fila no válido.']);
        }

        $module->loadMissing('fields');
        $log = $module->seed_discard_log;
        if (! is_array($log)) {
            $log = [];
        }

        $foundIndex = null;
        $foundItem = null;
        foreach ($log as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['discard_uid'] ?? '') === $discardUid) {
                $foundIndex = $i;
                $foundItem = $item;
                break;
            }
        }
        if ($foundItem === null || $foundIndex === null) {
            throw ValidationException::withMessages(['discard_uid' => 'Esta fila ya no está en el log. Recarga la página.']);
        }

        $reason = (string) ($foundItem['reason'] ?? '');
        if ($reason === '' || ! str_starts_with($reason, 'Municipio no resuelto')) {
            throw ValidationException::withMessages(['discard_uid' => 'Esta fila no admite registro manual desde el log.']);
        }

        $payload = $foundItem['entry_payload'] ?? null;
        if (! is_array($payload) || $payload === []) {
            throw ValidationException::withMessages(['discard_uid' => 'No hay datos guardados para esta fila (módulo creado con una versión anterior).']);
        }

        $munRow = DB::table('municipios as mu')
            ->join('microrregiones as mr', 'mr.id', '=', 'mu.microrregion_id')
            ->where('mu.id', $municipioId)
            ->select(['mu.id', 'mu.municipio', 'mu.microrregion_id'])
            ->first();
        if (! $munRow) {
            throw ValidationException::withMessages(['municipio_id' => 'El municipio no existe o no tiene microrregión asignada en el catálogo.']);
        }

        $microrregionId = (int) $munRow->microrregion_id;
        $allowedKeys = $module->fields->pluck('key')->all();
        $data = [];
        foreach ($payload as $k => $v) {
            if ($k === '_fila_excel') {
                $data[$k] = is_scalar($v) ? (string) $v : '';

                continue;
            }
            if (in_array($k, $allowedKeys, true)) {
                $data[$k] = is_scalar($v) ? (string) $v : '';
            }
        }

        $microLabels = [];
        foreach (DB::table('microrregiones')->select(['id', 'microrregion', 'cabecera'])->get() as $mr) {
            $n = str_pad(trim((string) $mr->microrregion), 2, '0', STR_PAD_LEFT);
            $id = (int) $mr->id;
            $microLabels[$id] = 'MR '.$n.' '.trim((string) ($mr->cabecera ?? ''));
        }

        $data['_microrregion_reporte'] = $microLabels[$microrregionId] ?? ('MR '.$microrregionId);
        $data['_municipio_reporte'] = (string) $munRow->municipio;

        $userId = $this->resolveUserIdForMicrorregion($microrregionId) ?? $actingUserId;

        return DB::transaction(function () use ($module, $data, $userId, $microrregionId, $log, $foundIndex, $actingUserId) {
            $entry = TemporaryModuleEntry::query()->create([
                'temporary_module_id' => $module->id,
                'user_id' => $userId,
                'microrregion_id' => $microrregionId,
                'data' => $data,
                'main_image_field_key' => null,
                'submitted_at' => Carbon::now(),
            ]);

            unset($log[$foundIndex]);
            $newLog = array_values($log);
            $module->seed_discard_log = $newLog;
            $module->save();

            $accessService = app(TemporaryModuleAccessService::class);
            $microrregionIdsUsed = TemporaryModuleEntry::query()
                ->where('temporary_module_id', $module->id)
                ->pluck('microrregion_id')
                ->unique()
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
            $allTargetUserIds = $accessService->userIdsForMicrorregionIds($microrregionIdsUsed);
            if ($allTargetUserIds === []) {
                $allTargetUserIds = [$actingUserId];
            }
            $module->targetUsers()->sync($allTargetUserIds);

            return [
                'entry' => $entry,
                'seed_discard_log' => $newLog,
            ];
        });
    }

    /**
     * Crea registros a partir de varias filas del log usando el mismo municipio.
     *
     * @param  list<string>  $discardUids
     * @return array{created:int, seed_discard_log: array<int, mixed>}
     */
    public function registerDiscardedSeedRows(
        TemporaryModule $module,
        array $discardUids,
        int $municipioId,
        int $actingUserId,
    ): array {
        $discardUids = array_values(array_unique(array_filter(array_map(
            static fn ($uid) => trim((string) $uid),
            $discardUids
        ), static fn (string $uid): bool => $uid !== '')));

        if ($discardUids === []) {
            throw ValidationException::withMessages(['discard_uids' => 'Selecciona al menos una fila del log.']);
        }
        if (count($discardUids) > 2000) {
            throw ValidationException::withMessages(['discard_uids' => 'Corrige hasta 2000 filas por grupo para evitar saturar el servidor.']);
        }

        $module->loadMissing('fields');
        $log = $module->seed_discard_log;
        if (! is_array($log)) {
            $log = [];
        }

        $munRow = DB::table('municipios as mu')
            ->join('microrregiones as mr', 'mr.id', '=', 'mu.microrregion_id')
            ->where('mu.id', $municipioId)
            ->select(['mu.id', 'mu.municipio', 'mu.microrregion_id'])
            ->first();
        if (! $munRow) {
            throw ValidationException::withMessages(['municipio_id' => 'El municipio no existe o no tiene microrregión asignada en el catálogo.']);
        }

        $wanted = array_fill_keys($discardUids, true);
        $selected = [];
        $remainingLog = [];
        foreach ($log as $item) {
            if (! is_array($item)) {
                continue;
            }
            $uid = (string) ($item['discard_uid'] ?? '');
            if ($uid !== '' && isset($wanted[$uid])) {
                $reason = (string) ($item['reason'] ?? '');
                $payload = $item['entry_payload'] ?? null;
                if ($reason !== '' && str_starts_with($reason, 'Municipio no resuelto') && is_array($payload) && $payload !== []) {
                    $selected[] = $item;
                    continue;
                }
            }
            $remainingLog[] = $item;
        }

        if ($selected === []) {
            throw ValidationException::withMessages(['discard_uids' => 'Las filas seleccionadas ya no están disponibles o no admiten corrección por municipio.']);
        }

        $microrregionId = (int) $munRow->microrregion_id;
        $allowedKeys = $module->fields->pluck('key')->all();
        $microLabels = [];
        foreach (DB::table('microrregiones')->select(['id', 'microrregion', 'cabecera'])->get() as $mr) {
            $n = str_pad(trim((string) $mr->microrregion), 2, '0', STR_PAD_LEFT);
            $id = (int) $mr->id;
            $microLabels[$id] = 'MR '.$n.' '.trim((string) ($mr->cabecera ?? ''));
        }

        $userId = $this->resolveUserIdForMicrorregion($microrregionId) ?? $actingUserId;
        $now = Carbon::now();
        $rows = [];
        foreach ($selected as $item) {
            $payload = (array) ($item['entry_payload'] ?? []);
            $data = [];
            foreach ($payload as $k => $v) {
                if ($k === '_fila_excel') {
                    $data[$k] = is_scalar($v) ? (string) $v : '';
                    continue;
                }
                if (in_array($k, $allowedKeys, true)) {
                    $data[$k] = is_scalar($v) ? (string) $v : '';
                }
            }
            $data['_microrregion_reporte'] = $microLabels[$microrregionId] ?? ('MR '.$microrregionId);
            $data['_municipio_reporte'] = (string) $munRow->municipio;

            $rows[] = [
                'temporary_module_id' => $module->id,
                'user_id' => $userId,
                'microrregion_id' => $microrregionId,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'main_image_field_key' => null,
                'submitted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return DB::transaction(function () use ($module, $rows, $remainingLog, $actingUserId) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('temporary_module_entries')->insert($chunk);
            }

            $module->seed_discard_log = array_values($remainingLog);
            $module->save();

            $accessService = app(TemporaryModuleAccessService::class);
            $microrregionIdsUsed = TemporaryModuleEntry::query()
                ->where('temporary_module_id', $module->id)
                ->pluck('microrregion_id')
                ->unique()
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
            $allTargetUserIds = $accessService->userIdsForMicrorregionIds($microrregionIdsUsed);
            if ($allTargetUserIds === []) {
                $allTargetUserIds = [$actingUserId];
            }
            $module->targetUsers()->sync($allTargetUserIds);

            return [
                'created' => count($rows),
                'seed_discard_log' => array_values($remainingLog),
            ];
        });
    }

    private function cellStr($sheet, int $colZeroBased, int $row): string
    {
        $col = $colZeroBased + 1;
        if ($col < 1) {
            return '';
        }
        $letter = Coordinate::stringFromColumnIndex($col);
        $v = $sheet->getCell($letter.$row)->getValue();

        return trim((string) ($v ?? ''));
    }

    /**
     * Valor visible como en Excel: si la celda pertenece a un merge, se lee la celda maestra (arriba-izquierda).
     * Así cada fila recupera N°, ACCIÓN, etc. aunque el municipio vaya combinado en bloque.
     */
    private function cellStrMerged($sheet, int $colZeroBased, int $row): string
    {
        $col = $colZeroBased + 1;
        if ($col < 1 || $row < 1) {
            return '';
        }
        $letter = Coordinate::stringFromColumnIndex($col);
        $coord = $letter.$row;
        try {
            foreach ($sheet->getMergeCells() as $range) {
                if (Coordinate::coordinateIsInsideRange($range, $coord)) {
                    $parts = explode(':', $range);
                    $master = $parts[0] ?? $coord;

                    return $this->cellStrFromCoord($sheet, $master);
                }
            }
        } catch (\Throwable) {
        }

        return $this->cellStr($sheet, $colZeroBased, $row);
    }

    private function cellStrFromCoord($sheet, string $coord): string
    {
        try {
            $v = $sheet->getCell($coord)->getValue();
        } catch (\Throwable) {
            return '';
        }

        return trim((string) ($v ?? ''));
    }

    private function normalize(string $s): string
    {
        $s = mb_strtoupper(preg_replace('/\s+/', ' ', trim($s)), 'UTF-8');

        return strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N']);
    }

    private function isAggregateFormulaValue(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || ! str_starts_with($value, '=')) {
            return false;
        }

        return (bool) preg_match('/^=\s*(SUM|SUBTOTAL|PROMEDIO|AVERAGE|COUNT|COUNTA|MAX|MIN)\s*\(/i', $value);
    }

    private function normalizeSeedFieldOptions(string $type, string $rawOptions, array $unificationMap = []): ?array
    {
        if (! in_array($type, ['select', 'multiselect'], true)) {
            return null;
        }
        if ($rawOptions === '') {
            return [];
        }
        $parts = preg_split('/[\r\n,;|]+/u', $rawOptions) ?: [];
        $options = [];
        $seen = [];
        foreach ($parts as $part) {
            $v = trim((string) $part);
            if ($v === '') {
                continue;
            }
            $v = $this->applySeedUnificationValue($v, $unificationMap);
            $compareKey = $this->normalizeSuggestionCompareKey($v);
            if ($compareKey === '' || isset($seen[$compareKey])) {
                continue;
            }
            $seen[$compareKey] = true;
            $options[] = $v;
        }

        return $options;
    }

    /**
     * @return array<string, string> clave normalizada origen => destino
     */
    private function parseSeedUnificationRules(string $type, string $rawRules): array
    {
        if (! in_array($type, ['select', 'multiselect'], true)) {
            return [];
        }
        $rawRules = trim($rawRules);
        if ($rawRules === '') {
            return [];
        }

        $lines = preg_split('/[\r\n;|]+/u', $rawRules) ?: [];
        $map = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s*(?:=>|->|=)\s*/u', $line, 2) ?: [];
            if (count($parts) !== 2) {
                continue;
            }
            $from = trim((string) ($parts[0] ?? ''));
            $to = trim((string) ($parts[1] ?? ''));
            if ($from === '' || $to === '') {
                continue;
            }
            $fromKey = $this->normalizeSuggestionCompareKey($from);
            if ($fromKey === '') {
                continue;
            }
            $map[$fromKey] = preg_replace('/\s+/', ' ', $to) ?: $to;
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $unificationMap
     */
    private function applySeedUnificationValue(string $value, array $unificationMap): string
    {
        if ($unificationMap === []) {
            return $value;
        }
        $key = $this->normalizeSuggestionCompareKey($value);
        if ($key === '') {
            return $value;
        }

        return (string) ($unificationMap[$key] ?? $value);
    }

    private function normalizeSeedFieldValue(
        string $value,
        string $type,
        mixed $options = null,
        array $municipiosGlobalNorm = [],
        array $municipiosByMicro = [],
        array $unificationMap = [],
    ): mixed
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if ($type === 'municipio') {
            if ($municipiosGlobalNorm !== [] && $municipiosByMicro !== []) {
                $municipioDB = $this->resolveMunicipioGlobal($value, null, $municipiosGlobalNorm, $municipiosByMicro);
                if ($municipioDB) {
                    return (string) $municipioDB->municipio;
                }
            }

            return $value;
        }

        if ($type === 'select') {
            return $this->applySeedUnificationValue($value, $unificationMap);
        }

        if ($type === 'number') {
            $compact = str_replace([',', ' '], ['', ''], $value);
            if (is_numeric($compact)) {
                return str_contains($compact, '.') ? (float) $compact : (int) $compact;
            }

            return $value;
        }

        if ($type === 'boolean') {
            $n = $this->normalize($value);
            if (in_array($n, ['SI', 'S', 'TRUE', 'VERDADERO', '1', 'X'], true)) {
                return true;
            }
            if (in_array($n, ['NO', 'N', 'FALSE', 'FALSO', '0'], true)) {
                return false;
            }

            return $value;
        }

        if ($type === 'multiselect') {
            $parts = preg_split('/[\r\n,;|]+/u', $value) ?: [];
            $vals = [];
            foreach ($parts as $part) {
                $v = $this->applySeedUnificationValue(trim((string) $part), $unificationMap);
                if ($v !== '' && ! in_array($v, $vals, true)) {
                    $vals[] = $v;
                }
            }
            if ($vals === []) {
                return [];
            }
            if (is_array($options) && $options !== []) {
                $normOpts = array_map(fn ($x) => $this->normalize((string) $x), $options);
                foreach ($unificationMap as $target) {
                    $normTarget = $this->normalize((string) $target);
                    if (! in_array($normTarget, $normOpts, true)) {
                        $normOpts[] = $normTarget;
                    }
                }
                $vals = array_values(array_filter($vals, fn ($v) => in_array($this->normalize($v), $normOpts, true)));
            }

            return $vals === [] ? [] : $vals;
        }

        if ($type === 'semaforo') {
            $n = $this->normalize($value);
            if (str_contains($n, 'VERDE')) {
                return 'Verde';
            }
            if (str_contains($n, 'AMARILLO')) {
                return 'Amarillo';
            }
            if (str_contains($n, 'ROJO')) {
                return 'Rojo';
            }
        }

        return $value;
    }

    /**
     * @param  array<string, int>  $microPorNumero  microrregion number string => id
     */
    private function resolveMicrorregionId(string $cellMr, array $microPorNumero): ?int
    {
        $cellMr = trim($cellMr);
        if ($cellMr === '') {
            return null;
        }
        if (preg_match('/\b(\d{1,2})\b/', $cellMr, $m)) {
            $num = (int) $m[1];
            $padded = str_pad((string) $num, 2, '0', STR_PAD_LEFT);

            return (int) ($microPorNumero[$padded] ?? $microPorNumero[(string) $num] ?? 0) ?: null;
        }

        return null;
    }

    /**
     * Fragmentos que aparecen en Excel pero en BD el nombre oficial es distinto o más largo
     * (lista 105 acciones: IXITLAN→San Miguel Ixitlán, Coxcatlan, etc.).
     *
     * @var array<string, list<string>> fragmento normalizado => subcadenas que debe contener municipio en BD
     */
    private const MUNICIPIO_SUBSTRING_IN_MR = [
        'IXITLAN' => ['IXITLAN'],
        'COXCATLAN' => ['COXCATLAN'],
        'ZIHUATEUTLA' => ['ZIHUATEUTLA'],
        'XICOTEPEC' => ['XICOTEPEC'],
        'SAN JOSE MIAHUATLAN' => ['MIAHUATLAN'],
        'VICENTE GUERRERO' => ['VICENTE GUERRERO', 'GUERRERO'],
        'CHIAUTLA DE TAPIA' => ['CHIAUTLA'],
        'COHETZALA' => ['COHETZALA'],
        'XAYACATLAN DE BRAVO' => ['XAYACATLAN'],
        'HUEHUETLAN EL CHICO' => ['HUEHUETLAN'],
        'TEPEYAHUALCO DE CUAUHTEMOC' => ['TEPEYAHUALCO'],
        'YEHUALTEPEC' => ['YEHUALTEPEC'],
        'TLALTENANGO' => ['TLALTENANGO'],
        'CORONANGO' => ['CORONANGO'],
        'NAUZONTLA' => ['NAUZONTLA'],
        'ATLEQUIZAYAN' => ['ATLEQUIZAYAN'],
        'JONOTLA' => ['JONOTLA'],
        'AMIXTLAN' => ['AMIXTLAN'],
        'CAMOCUAUTLA' => ['CAMOCUAUTLA'],
        'TEPETZINTLA' => ['TEPETZINTLA'],
        'ZACATLAN' => ['ZACATLAN'],
        'CHIGNAHUAPAN' => ['CHIGNAHUAPAN'],
        'HERMENEGILDO GALEANA' => ['GALEANA', 'HERMENEGILDO'],
        'GUADALUPE VICTORIA' => ['GUADALUPE VICTORIA'],
        'OCOTEPEC' => ['OCOTEPEC'],
        'ESPERANZA' => ['ESPERANZA'],
        'CHILA' => ['CHILA'],
        'CHALCHICOMULA DE SESMA' => ['CHALCHICOMULA', 'SESMA'],
        'JUAN GALINDO' => ['JUAN GALINDO', 'GALINDO'],
        'TLAOLA' => ['TLAOLA'],
    ];

    /**
     * Coincide municipio solo dentro de la microrregión ya resuelta (evita homónimos en otra MR).
     */
    public function resolveMunicipioInMicrorregion(string $cellMun, int $microId, array $municipiosByMicro): ?object
    {
        $candidates = $municipiosByMicro[$microId] ?? [];
        if ($candidates === []) {
            return null;
        }

        $norm = $this->normalizeMunicipioName($cellMun);
        if ($norm === '') {
            return null;
        }

        foreach (self::MUNICIPIO_SUBSTRING_IN_MR as $frag => $needles) {
            if (! str_contains($norm, $frag) && $norm !== $frag) {
                continue;
            }
            foreach ($candidates as $m) {
                $dbN = $this->normalizeMunicipioName($m->municipio);
                foreach ($needles as $nd) {
                    $nd = $this->normalizeMunicipioName($nd);
                    if ($nd !== '' && str_contains($dbN, $nd)) {
                        if ($frag === 'VICENTE GUERRERO' && ! str_contains($dbN, 'VICENTE')) {
                            continue;
                        }

                        return $m;
                    }
                }
            }
        }

        foreach ($candidates as $m) {
            if ($this->normalizeMunicipioName($m->municipio) === $norm) {
                return $m;
            }
        }

        $normCompact = $this->compactAlnum($norm);
        foreach ($candidates as $m) {
            if ($this->compactAlnum($this->normalizeMunicipioName($m->municipio)) === $normCompact) {
                return $m;
            }
        }

        foreach ($candidates as $m) {
            $dbN = $this->normalizeMunicipioName($m->municipio);
            if (str_contains($dbN, $norm) || str_contains($norm, $dbN)) {
                if (mb_strlen($norm) >= 5 || mb_strlen($dbN) >= 8) {
                    return $m;
                }
            }
        }

        $best = null;
        $bestScore = 0;
        foreach ($candidates as $m) {
            $dbN = $this->compactAlnum($this->normalizeMunicipioName($m->municipio));
            similar_text($normCompact, $dbN, $pct);
            if ($pct > $bestScore && $pct >= 80) {
                $bestScore = $pct;
                $best = $m;
            }
        }

        // Misma regla primera palabra solo entre candidatos de esta MR
        $byFirst = $this->resolveMunicipioByFirstWordInCandidates($norm, $candidates);
        if ($byFirst !== null) {
            return $byFirst;
        }

        return $best;
    }

    /**
     * @param  list<object>  $candidates
     */
    private function resolveMunicipioByFirstWordInCandidates(string $norm, array $candidates): ?object
    {
        $parts = preg_split('/\s+/', $norm, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) < 2 || mb_strlen($parts[0]) < 4) {
            return null;
        }
        $first = $this->normalizeMunicipioName($parts[0]);
        $second = $parts[1] ?? '';
        if (! in_array($second, ['DE', 'DEL', 'Y'], true) && mb_strlen($first) < 7) {
            return null;
        }
        $fc = $this->compactAlnum($first);
        $hits = [];
        foreach ($candidates as $m) {
            $dbN = $this->normalizeMunicipioName($m->municipio);
            if ($dbN === $first || $this->compactAlnum($dbN) === $fc) {
                $hits[] = $m;
            }
        }
        if (count($hits) === 1) {
            return $hits[0];
        }

        return null;
    }

    /** MR por nombre cabecera (ej. celda "14 AJALPAN" → token AJALPAN) */
    private function resolveMicrorregionByName(string $cellMr, array $microByNormToken): ?int
    {
        $norm = $this->normalizeMunicipioName($cellMr);
        if ($norm === '') {
            return null;
        }
        foreach ($microByNormToken as $token => $id) {
            if ($token !== '' && str_contains($norm, $token)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param  array<string, list<object>>  $municipiosGlobalNorm
     */
    public function resolveMunicipioGlobal(
        string $cellMun,
        ?int $preferredMicroId,
        array $municipiosGlobalNorm,
        array $municipiosByMicro,
    ): ?object {
        $norm = $this->normalizeMunicipioName($cellMun);
        if ($norm === '') {
            return null;
        }
        $compact = $this->compactAlnum($norm);
        if ($preferredMicroId !== null && str_contains($norm, 'MIAHUATLAN')) {
            foreach ($municipiosByMicro[$preferredMicroId] ?? [] as $m) {
                if (str_contains($this->normalizeMunicipioName($m->municipio), 'MIAHUATLAN')) {
                    return $m;
                }
            }
        }
        if ($preferredMicroId !== null && (str_contains($norm, 'COXCATLAN') || str_contains($norm, 'COXCATL'))) {
            foreach ($municipiosByMicro[$preferredMicroId] ?? [] as $m) {
                if (str_contains($this->compactAlnum($this->normalizeMunicipioName($m->municipio)), 'COXCATLAN')) {
                    return $m;
                }
            }
        }
        $list = $municipiosGlobalNorm[$norm] ?? [];
        if ($list === []) {
            foreach ($municipiosGlobalNorm as $rows) {
                foreach ($rows as $m) {
                    if ($this->compactAlnum($this->normalizeMunicipioName($m->municipio)) === $compact) {
                        $list[] = $m;
                    }
                }
            }
        }
        if ($preferredMicroId !== null) {
            foreach ($list as $m) {
                if ((int) $m->microrregion_id === $preferredMicroId) {
                    return $m;
                }
            }
        }
        if (count($list) === 1) {
            return $list[0];
        }

        // Excel "CHIAUTLA DE TAPIA" → BD municipio "Chiautla" (solo primera palabra)
        $byFirst = $this->resolveMunicipioByFirstWord($norm, $preferredMicroId, $municipiosGlobalNorm);
        if ($byFirst !== null) {
            return $byFirst;
        }

        $best = null;
        $bestScore = 0;
        foreach ($municipiosByMicro as $microId => $candidates) {
            foreach ($candidates as $m) {
                $dbN = $this->compactAlnum($this->normalizeMunicipioName($m->municipio));
                similar_text($compact ?? $this->compactAlnum($norm), $dbN, $pct);
                if ($pct > $bestScore && $pct >= 76) {
                    $bestScore = $pct;
                    $best = $m;
                }
            }
        }

        return $best;
    }

    /**
     * Si el texto viene compuesto (p. ej. "CHIAUTLA DE TAPIA") y en BD el municipio es la primera palabra ("CHIAUTLA").
     */
    private function resolveMunicipioByFirstWord(
        string $norm,
        ?int $preferredMicroId,
        array $municipiosGlobalNorm,
    ): ?object {
        $parts = preg_split('/\s+/', $norm, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) < 2) {
            return null;
        }
        $first = $parts[0];
        if (mb_strlen($first) < 4) {
            return null;
        }
        // Disparar si lleva "DE", "DEL", "Y" como segunda palabra (composición típica)
        $second = $parts[1] ?? '';
        $compound = in_array($second, ['DE', 'DEL', 'Y'], true)
            || (count($parts) >= 3 && $second === 'JOSE'); // SAN JOSE ...
        if (! $compound && count($parts) < 3) {
            return null;
        }
        if (! $compound && ! in_array($second, ['DE', 'DEL', 'Y'], true)) {
            // Varias palabras sin "DE": solo si primera palabra es larga (evita "SAN PEDRO" → SAN)
            if (mb_strlen($first) < 7) {
                return null;
            }
        }

        $candidates = $municipiosGlobalNorm[$first] ?? [];
        if ($candidates === []) {
            $fc = $this->compactAlnum($first);
            foreach ($municipiosGlobalNorm as $rows) {
                foreach ($rows as $m) {
                    if ($this->compactAlnum($this->normalizeMunicipioName($m->municipio)) === $fc) {
                        $candidates[] = $m;
                    }
                }
            }
        }
        $seen = [];
        $uniq = [];
        foreach ($candidates as $m) {
            $id = (int) $m->id;
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $uniq[] = $m;
        }
        $candidates = $uniq;
        if ($candidates === []) {
            return null;
        }
        if ($preferredMicroId !== null) {
            foreach ($candidates as $m) {
                if ((int) $m->microrregion_id === $preferredMicroId) {
                    return $m;
                }
            }
        }
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        return null;
    }

    private function normalizeMunicipioName(string $s): string
    {
        $s = mb_strtoupper(preg_replace('/\s+/', ' ', trim($s)), 'UTF-8');
        $map = ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N', 'À' => 'A'];

        return strtr($s, $map);
    }

    private function compactAlnum(string $s): string
    {
        return preg_replace('/[^A-Z0-9]/', '', $s) ?? '';
    }

    private function resolveUserIdForMicrorregion(int $microrregionId): ?int
    {
        $delegado = DB::table('delegados as d')
            ->join('users as u', 'u.id', '=', 'd.user_id')
            ->where('d.microrregion_id', $microrregionId)
            ->value('d.user_id');
        if ($delegado) {
            return (int) $delegado;
        }

        $uid = DB::table('user_microrregion as um')
            ->join('users as u', 'u.id', '=', 'um.user_id')
            ->where('um.microrregion_id', $microrregionId)
            ->orderBy('user_id')
            ->value('um.user_id');

        return $uid ? (int) $uid : null;
    }

    /**
     * Normaliza los valores de un campo municipio ya existente (guardado como texto)
     * contra el catálogo oficial de municipios.
     *
     * @return array{updated:int,skipped:int,unmatched:list<string>}
     */
    public function normalizeMunicipioField(TemporaryModule $module, string $fieldKey = 'municipio'): array
    {
        $entries = TemporaryModuleEntry::query()
            ->where('temporary_module_id', $module->id)
            ->select(['id', 'microrregion_id', 'data'])
            ->get();

        if ($entries->isEmpty()) {
            return ['updated' => 0, 'skipped' => 0, 'unmatched' => []];
        }

        $municipiosRows = DB::table('municipios as mu')
            ->join('microrregiones as mr', 'mr.id', '=', 'mu.microrregion_id')
            ->select(['mu.id', 'mu.municipio', 'mu.microrregion_id', 'mr.microrregion as mr_num'])
            ->get();

        $municipiosByMicro = [];
        foreach ($municipiosRows as $m) {
            $mid = (int) $m->microrregion_id;
            $municipiosByMicro[$mid][] = $m;
        }

        $municipiosGlobalNorm = [];
        foreach ($municipiosRows as $m) {
            $k = $this->normalizeMunicipioName($m->municipio);
            $municipiosGlobalNorm[$k][] = $m;
        }

        $updated = 0;
        $skipped = 0;
        $unmatched = [];

        foreach ($entries as $entry) {
            $data = $entry->data ?? [];
            $raw = $data[$fieldKey] ?? null;
            if (! is_string($raw) || trim($raw) === '') {
                $skipped++;

                continue;
            }

            $municipioSearch = trim($raw);
            $municipioDB = $this->resolveMunicipioGlobal($municipioSearch, null, $municipiosGlobalNorm, $municipiosByMicro);

            $microId = (int) ($entry->microrregion_id ?? 0);
            if (! $municipioDB && $microId > 0) {
                $municipioDB = $this->resolveMunicipioInMicrorregion($municipioSearch, $microId, $municipiosByMicro)
                    ?: $this->resolveMunicipioGlobal($municipioSearch, $microId, $municipiosGlobalNorm, $municipiosByMicro);
            }

            if (! $municipioDB) {
                $skipped++;
                if (count($unmatched) < 100) {
                    $unmatched[] = $municipioSearch;
                }

                continue;
            }

            $nombre = (string) $municipioDB->municipio;
            if (($data[$fieldKey] ?? null) === $nombre) {
                continue;
            }

            $data[$fieldKey] = $nombre;
            TemporaryModuleEntry::query()->whereKey($entry->id)->update(['data' => $data]);
            $updated++;
        }

        $unmatched = array_values(array_unique($unmatched));

        return ['updated' => $updated, 'skipped' => $skipped, 'unmatched' => $unmatched];
    }

    // =====================================================================
    //  Pipeline DIFERIDA (carga por lotes) — para archivos grandes
    // =====================================================================
    //  Flujo:
    //    1. prepareDeferredSeed(): valida + persiste archivo + crea módulo
    //       vacío con sus campos + exporta la hoja (desde data_start_row)
    //       a CSV lineal + guarda estado pendiente. total_rows = líneas CSV.
    //    2. processSeedBatch(): el frontend llama N veces; cada llamada
    //       avanza en el CSV con fgetcsv (sin re-leer el XLSX desde la fila 1).
    //    3. finalizeSeed(): cuando ya no hay filas pendientes, sincroniza
    //       delegados, mueve logs de descartes al campo definitivo y
    //       borra el archivo temporal (.xlsx y .csv).
    // =====================================================================

    private const SEED_STATE_LOG_TYPE = 'seed_pending_state';
    private const SEED_STORAGE_DIR = 'temporary-module-seeds';

    /**
     * Persiste el archivo y crea el shell del módulo. NO procesa registros.
     *
     * @param  array<int>  $fieldColumnIndices
     * @param  array<int, string>  $fieldTypesByColumn
     * @param  array<int, string>  $fieldOptionsByColumn
     * @param  array<int, string>  $fieldUnificationsByColumn
     * @return array{module: TemporaryModule, total_rows: int, batch_size: int, next_row: int}
     */
    public function prepareDeferredSeed(
        int $adminUserId,
        string $name,
        ?string $description,
        ?Carbon $expiresAt,
        bool $isIndefinite,
        UploadedFile $file,
        int $headerRow,
        int $dataStartRow,
        int $colMicrorregion,
        int $colMunicipio,
        array $fieldColumnIndices,
        array $fieldTypesByColumn,
        array $fieldOptionsByColumn,
        array $fieldUnificationsByColumn,
        int $sheetIndex = 0,
        array $encryptionConfig = [],
    ): array {
        if ($colMunicipio < 0 && $colMicrorregion < 0) {
            throw new \InvalidArgumentException('Debe indicarse columna Municipio o Microrregión.');
        }
        if (! $this->spoutReader->supports($file)) {
            throw new \InvalidArgumentException('La carga por lotes solo está disponible para archivos .xlsx. Para .xls usa la carga normal.');
        }

        $this->raiseLimitsForFile($file);

        $headerRow = max(1, $headerRow);
        $dataStartRow = max($headerRow + 1, $dataStartRow);

        // 1. Persistir archivo en storage local con extensión .xlsx
        $disk = Storage::disk('local');
        $fileName = (string) Str::uuid().'.xlsx';
        $storagePath = self::SEED_STORAGE_DIR.'/'.$fileName;
        $disk->putFileAs(self::SEED_STORAGE_DIR, $file, $fileName);
        $absolutePath = $disk->path($storagePath);

        // 2. Leer headers (Spout) y preparar campos
        $headerInfo = $this->spoutReader->previewHeaders($absolutePath, $headerRow, $sheetIndex);
        $headers = $headerInfo['headers'] ?? [];
        $headerLabels = array_map(fn ($h) => (string) ($h['label'] ?? ''), $headers);

        [$preparedFields, $indexToKey] = $this->buildPreparedFieldsAndIndex(
            $headerLabels,
            $fieldColumnIndices,
            $fieldTypesByColumn,
            $fieldOptionsByColumn,
            $fieldUnificationsByColumn,
        );

        if ($preparedFields === []) {
            $disk->delete($storagePath);

            throw new \InvalidArgumentException('Selecciona al menos una columna como campo del módulo.');
        }

        // 2b. Materializar la hoja desde data_start_row como CSV lineal.
        // Cada lote HTTP salta líneas con fgetcsv (O(batch)) en lugar de re-leer
        // desde la fila 1 del XLSX en cada petición (O(n²), timeouts con ~100k+ filas).
        $this->raiseLimitsForDeferredCsvExport();
        $csvFileName = (string) Str::uuid().'.csv';
        $csvStoragePath = self::SEED_STORAGE_DIR.'/'.$csvFileName;
        $csvAbsolutePath = $disk->path($csvStoragePath);
        try {
            $totalRows = $this->pythonExcel->exportSheetToCsv($absolutePath, $sheetIndex, $dataStartRow, $csvAbsolutePath)
                ?? $this->exportDeferredSeedSheetToCsv($absolutePath, $sheetIndex, $dataStartRow, $csvAbsolutePath);
        } catch (\Throwable $e) {
            $disk->delete($storagePath);
            if (is_file($csvAbsolutePath)) {
                @unlink($csvAbsolutePath);
            }
            throw $e;
        }

        // 3. Crear módulo + campos
        $slug = $this->buildUniqueSeedSlug($name);
        $expires = $isIndefinite ? null : $expiresAt;

        $moduleAttrs = [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'expires_at' => $expires,
            'is_active' => false, // se activa al finalizar el lote
            'applies_to_all' => false,
            'created_by' => $adminUserId,
        ];
        $this->applyEncryptionConfigToAttrs($moduleAttrs, $encryptionConfig);

        $module = DB::transaction(function () use ($moduleAttrs, $preparedFields) {
            $module = TemporaryModule::query()->create($moduleAttrs);
            $module->fields()->createMany($preparedFields);

            return $module;
        });

        // 4. Guardar estado pendiente en seed_discard_log con sentinel.
        $state = [
            'log_type' => self::SEED_STATE_LOG_TYPE,
            'file_path' => $storagePath,
            'csv_path' => $csvStoragePath,
            'sheet_index' => $sheetIndex,
            'header_row' => $headerRow,
            'data_start_row' => $dataStartRow,
            'col_microrregion' => $colMicrorregion,
            'col_municipio' => $colMunicipio,
            'field_column_indices' => array_values(array_map('intval', $fieldColumnIndices)),
            'index_to_key' => $indexToKey,
            'admin_user_id' => $adminUserId,
            'total_estimated_rows' => $totalRows,
            'next_row' => $dataStartRow,
            'csv_offset' => 0,
            'processed_count' => 0,
            'state' => [
                'lastMicroId' => null,
                'lastMunicipioCell' => '',
                'lastFieldCarry' => [],
                'microrregionIdsUsed' => [],
                'assignedUserIds' => [],
                'stats' => ['created' => 0, 'skipped' => 0, 'unmatched' => [], 'discarded' => []],
            ],
        ];
        $module->seed_discard_log = [$state];
        $module->save();

        return [
            'module' => $module,
            'total_rows' => $totalRows,
            'batch_size' => 1000,
            'next_row' => $dataStartRow,
        ];
    }

    /**
     * Procesa el siguiente lote del archivo persistido.
     * Devuelve progreso para que el frontend itere hasta agotar el archivo.
     *
     * @return array{processed_in_batch:int, processed_total:int, next_row:int, has_more:bool, total_rows:int, stats:array}
     */
    public function processSeedBatch(TemporaryModule $module, int $batchSize = 200): array
    {
        $module->refresh();
        $state = $this->loadSeedPendingState($module);
        if ($state === null) {
            throw new \RuntimeException('Este módulo no tiene una carga pendiente.');
        }

        @set_time_limit(120);
        @ini_set('memory_limit', '1G');

        $disk = Storage::disk('local');
        $storagePath = (string) ($state['file_path'] ?? '');
        $csvStoragePath = (string) ($state['csv_path'] ?? '');
        $hasCsv = $csvStoragePath !== '' && $disk->exists($csvStoragePath);

        if (! $hasCsv) {
            if ($storagePath === '' || ! $disk->exists($storagePath)) {
                throw new \RuntimeException('El archivo de carga ya no existe. Reintenta la carga desde cero.');
            }
        }

        $absolutePath = ($storagePath !== '' && $disk->exists($storagePath)) ? $disk->path($storagePath) : '';
        $csvAbsolutePath = $hasCsv ? $disk->path($csvStoragePath) : '';

        $config = [
            'sheet_index' => (int) ($state['sheet_index'] ?? 0),
            'header_row' => (int) ($state['header_row'] ?? 1),
            'data_start_row' => (int) ($state['data_start_row'] ?? 2),
            'col_microrregion' => (int) ($state['col_microrregion'] ?? -1),
            'col_municipio' => (int) ($state['col_municipio'] ?? -1),
            'field_column_indices' => array_map('intval', (array) ($state['field_column_indices'] ?? [])),
            'index_to_key' => (array) ($state['index_to_key'] ?? []),
        ];
        $adminUserId = (int) ($state['admin_user_id'] ?? 0);
        $totalRows = (int) ($state['total_estimated_rows'] ?? 0);
        $startRow = (int) ($state['next_row'] ?? $config['data_start_row']);
        $processedBefore = (int) ($state['processed_count'] ?? 0);

        $runtimeState = (array) ($state['state'] ?? []);
        $runtimeState['lastMicroId'] = isset($runtimeState['lastMicroId']) ? (int) $runtimeState['lastMicroId'] ?: null : null;
        $runtimeState['lastMunicipioCell'] = (string) ($runtimeState['lastMunicipioCell'] ?? '');
        $runtimeState['lastFieldCarry'] = (array) ($runtimeState['lastFieldCarry'] ?? []);
        $runtimeState['microrregionIdsUsed'] = (array) ($runtimeState['microrregionIdsUsed'] ?? []);
        $runtimeState['assignedUserIds'] = (array) ($runtimeState['assignedUserIds'] ?? []);
        $runtimeState['stats'] = (array) ($runtimeState['stats'] ?? ['created' => 0, 'skipped' => 0, 'unmatched' => [], 'discarded' => []]);

        $catalogs = $this->buildSeedCatalogs();

        $processedInBatch = 0;
        $lastSeenRow = $startRow - 1;
        $reachedEnd = false;
        $pendingEntryRows = [];
        $nextCsvOffset = isset($state['csv_offset']) ? (int) $state['csv_offset'] : null;

        if ($csvAbsolutePath !== '') {
            $fh = fopen($csvAbsolutePath, 'rb');
            if ($fh === false) {
                throw new \RuntimeException('No se pudo abrir el CSV de trabajo. Reintenta la carga desde cero.');
            }
            try {
                if ($nextCsvOffset !== null && $nextCsvOffset > 0) {
                    fseek($fh, $nextCsvOffset);
                } else {
                    $dataStart = (int) $config['data_start_row'];
                    $linesToSkip = max(0, $startRow - $dataStart);
                    for ($s = 0; $s < $linesToSkip; $s++) {
                        if ($this->readDeferredSeedCsvRow($fh) === null) {
                            $reachedEnd = true;
                            break;
                        }
                    }
                }
                if (! $reachedEnd) {
                    $rowNumber = $startRow;
                    for ($n = 0; $n < $batchSize; $n++) {
                        $cells = $this->readDeferredSeedCsvRow($fh);
                        if ($cells === null) {
                            $reachedEnd = true;
                            break;
                        }
                        $this->processSeedRowFromCells(
                            $cells,
                            $rowNumber,
                            $runtimeState,
                            $catalogs,
                            $config,
                            $module,
                            $adminUserId,
                            $pendingEntryRows,
                        );
                        $processedInBatch++;
                        $lastSeenRow = $rowNumber;
                        $rowNumber++;
                    }
                }
                $pos = ftell($fh);
                $nextCsvOffset = is_int($pos) ? $pos : null;
            } finally {
                fclose($fh);
            }
        } else {
            $endRow = $startRow + $batchSize - 1;
            $reader = $this->makeStreamingXlsxReader();
            $reader->open($absolutePath);
            try {
                $idx = 0;
                foreach ($reader->getSheetIterator() as $sheet) {
                    if ($idx !== $config['sheet_index']) {
                        $idx++;
                        continue;
                    }
                    $rowNumber = 0;
                    foreach ($sheet->getRowIterator() as $row) {
                        $rowNumber++;
                        if ($rowNumber < $startRow) {
                            continue;
                        }
                        if ($rowNumber > $endRow) {
                            break;
                        }
                        $cells = $this->rowToStringCells($row);

                        $this->processSeedRowFromCells(
                            $cells,
                            $rowNumber,
                            $runtimeState,
                            $catalogs,
                            $config,
                            $module,
                            $adminUserId,
                            $pendingEntryRows,
                        );
                        $processedInBatch++;
                        $lastSeenRow = $rowNumber;
                    }

                    // Si terminamos el iterator sin alcanzar endRow, se acabó el archivo.
                    if ($rowNumber < $endRow) {
                        $reachedEnd = true;
                    }
                    break;
                }
            } finally {
                $reader->close();
            }
        }

        $this->insertSeedEntryRows($pendingEntryRows);

        $nextRow = $reachedEnd ? -1 : ($lastSeenRow + 1);
        $processedTotal = $processedBefore + $processedInBatch;

        // Guardar nuevo estado
        $state['next_row'] = $nextRow;
        if ($nextCsvOffset !== null) {
            $state['csv_offset'] = $nextRow > 0 ? $nextCsvOffset : null;
        }
        $state['processed_count'] = $processedTotal;
        $state['state'] = $runtimeState;
        $module->seed_discard_log = $this->replaceSeedPendingState($module, $state);
        $module->save();

        return [
            'processed_in_batch' => $processedInBatch,
            'processed_total' => $processedTotal,
            'next_row' => $nextRow,
            'has_more' => $nextRow > 0,
            'total_rows' => $totalRows,
            'stats' => [
                'created' => (int) ($runtimeState['stats']['created'] ?? 0),
                'skipped' => (int) ($runtimeState['stats']['skipped'] ?? 0),
                'discarded' => count($runtimeState['stats']['discarded'] ?? []),
                'unmatched' => count($runtimeState['stats']['unmatched'] ?? []),
            ],
        ];
    }

    /**
     * Cierra la carga por lotes: vincula delegados, mueve descartes,
     * activa el módulo y borra el archivo temporal.
     *
     * @return array{module: TemporaryModule, stats: array}
     */
    public function finalizeSeed(TemporaryModule $module, int $actingUserId): array
    {
        $module->refresh();
        $state = $this->loadSeedPendingState($module);
        if ($state === null) {
            return ['module' => $module, 'stats' => ['created' => 0, 'skipped' => 0]];
        }

        $runtime = (array) ($state['state'] ?? []);
        $stats = (array) ($runtime['stats'] ?? ['created' => 0, 'skipped' => 0, 'unmatched' => [], 'discarded' => []]);
        $microrregionIdsUsed = array_keys((array) ($runtime['microrregionIdsUsed'] ?? []));
        $assignedUserIds = array_keys((array) ($runtime['assignedUserIds'] ?? []));

        // Sincronizar usuarios destino
        $accessService = app(TemporaryModuleAccessService::class);
        $allTargetUserIds = $accessService->userIdsForMicrorregionIds(array_map('intval', $microrregionIdsUsed));
        if ($allTargetUserIds === []) {
            $allTargetUserIds = array_map('intval', $assignedUserIds);
        }
        if ($allTargetUserIds === []) {
            $allTargetUserIds = [$actingUserId];
        }
        $module->targetUsers()->sync($allTargetUserIds);

        // Borrar archivo temporal
        $disk = Storage::disk('local');
        $storagePath = (string) ($state['file_path'] ?? '');
        if ($storagePath !== '' && $disk->exists($storagePath)) {
            try {
                $disk->delete($storagePath);
            } catch (\Throwable $e) {
                Log::warning('finalizeSeed: no se pudo borrar archivo temporal '.$storagePath.': '.$e->getMessage());
            }
        }
        $csvPath = (string) ($state['csv_path'] ?? '');
        if ($csvPath !== '' && $disk->exists($csvPath)) {
            try {
                $disk->delete($csvPath);
            } catch (\Throwable $e) {
                Log::warning('finalizeSeed: no se pudo borrar CSV temporal '.$csvPath.': '.$e->getMessage());
            }
        }

        // Activar módulo y guardar log final (sin sentinel de estado)
        $module->is_active = true;
        $module->seed_discard_log = array_values((array) ($stats['discarded'] ?? []));
        $module->save();

        return [
            'module' => $module,
            'stats' => [
                'created' => (int) ($stats['created'] ?? 0),
                'skipped' => (int) ($stats['skipped'] ?? 0),
                'discarded' => count((array) ($stats['discarded'] ?? [])),
                'unmatched' => count((array) ($stats['unmatched'] ?? [])),
                'unmatched_list' => array_values((array) ($stats['unmatched'] ?? [])),
            ],
        ];
    }

    /**
     * Cancela una carga pendiente: borra archivo + módulo (force delete).
     */
    public function cancelDeferredSeed(TemporaryModule $module): void
    {
        $state = $this->loadSeedPendingState($module);
        if ($state !== null) {
            $disk = Storage::disk('local');
            $storagePath = (string) ($state['file_path'] ?? '');
            if ($storagePath !== '' && $disk->exists($storagePath)) {
                try {
                    $disk->delete($storagePath);
                } catch (\Throwable) {
                }
            }
            $csvPath = (string) ($state['csv_path'] ?? '');
            if ($csvPath !== '' && $disk->exists($csvPath)) {
                try {
                    $disk->delete($csvPath);
                } catch (\Throwable) {
                }
            }
        }
        $module->forceDelete();
    }

    private function loadSeedPendingState(TemporaryModule $module): ?array
    {
        $log = $module->seed_discard_log;
        if (! is_array($log)) {
            return null;
        }
        foreach ($log as $item) {
            if (is_array($item) && (($item['log_type'] ?? '') === self::SEED_STATE_LOG_TYPE)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Reemplaza el sentinel de estado pendiente conservando los demás items
     * (discards, normalizations) que estuvieran en el log.
     *
     * @return array<int, array<string, mixed>>
     */
    private function replaceSeedPendingState(TemporaryModule $module, array $newState): array
    {
        $log = $module->seed_discard_log;
        if (! is_array($log)) {
            $log = [];
        }
        $out = [];
        $replaced = false;
        foreach ($log as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['log_type'] ?? '') === self::SEED_STATE_LOG_TYPE) {
                if (! $replaced) {
                    $out[] = $newState;
                    $replaced = true;
                }
                continue;
            }
            $out[] = $item;
        }
        if (! $replaced) {
            $out[] = $newState;
        }

        return $out;
    }

    /**
     * Convierte una fila de Spout en lista de celdas string (indexadas 0..N).
     *
     * @return list<string>
     */
    private function rowToStringCells($row): array
    {
        $values = method_exists($row, 'toArray') ? $row->toArray() : [];
        $out = [];
        foreach ($values as $v) {
            $out[] = $this->stringifyAnyCellValue($v);
        }

        return $out;
    }

    private function stringifyAnyCellValue(mixed $value): string
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
            if (floor($value) === $value && abs($value) < 1e15) {
                return (string) (int) $value;
            }

            return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
        }
        if ($value instanceof \DateTimeInterface) {
            $time = $value->format('H:i:s');
            if ($time === '00:00:00') {
                return $value->format('Y-m-d');
            }

            return $value->format('Y-m-d H:i:s');
        }
        if ($value instanceof \DateInterval) {
            return $value->format('%H:%I:%S');
        }

        return trim((string) $value);
    }

    /**
     * Procesa una sola fila (en formato array de celdas) replicando la
     * lógica del loop principal de createModuleFromExcel.
     */
    private function processSeedRowFromCells(
        array $cells,
        int $rowNumber,
        array &$runtimeState,
        array $catalogs,
        array $config,
        TemporaryModule $module,
        int $adminUserId,
        array &$pendingEntryRows,
    ): void {
        $colMicrorregion = (int) $config['col_microrregion'];
        $colMunicipio = (int) $config['col_municipio'];
        $fieldColumnIndices = (array) $config['field_column_indices'];
        $indexToKey = (array) $config['index_to_key'];

        $hasMrCol = $colMicrorregion >= 0;
        $hasMunCol = $colMunicipio >= 0;

        $microPorNumero = $catalogs['microPorNumero'];
        $microByNormToken = $catalogs['microByNormToken'];
        $municipiosGlobalNorm = $catalogs['municipiosGlobalNorm'];
        $municipiosByMicro = $catalogs['municipiosByMicro'];
        $microLabels = $catalogs['microLabels'];
        $cabeceraPorMicro = $catalogs['cabeceraPorMicro'];
        $userByMicro = (array) ($catalogs['userByMicro'] ?? []);

        $cellAt = function (int $colIdx) use ($cells): string {
            if ($colIdx < 0) {
                return '';
            }

            return trim((string) ($cells[$colIdx] ?? ''));
        };

        $cellMr = $hasMrCol ? $cellAt($colMicrorregion) : '';
        $cellMun = $hasMunCol ? $cellAt($colMunicipio) : '';

        if ($hasMrCol && $cellMr !== '') {
            $parsedMr = $this->resolveMicrorregionId($cellMr, $microPorNumero)
                ?? $this->resolveMicrorregionByName($cellMr, $microByNormToken);
            if ($parsedMr !== null) {
                $runtimeState['lastMicroId'] = $parsedMr;
            }
        }
        if ($hasMunCol && $cellMun !== '') {
            $runtimeState['lastMunicipioCell'] = $cellMun;
        }
        $microId = $runtimeState['lastMicroId'] ?? null;

        // ¿Fila tiene contenido en alguna columna relevante?
        $rowHasAny = false;
        foreach ($cells as $c) {
            if (trim((string) $c) !== '') {
                $rowHasAny = true;
                break;
            }
        }

        // ¿Difiere del carry actual?
        $differsFromCarry = false;
        foreach ($fieldColumnIndices as $ci) {
            $ci = (int) $ci;
            $now = $cellAt($ci);
            $prev = $runtimeState['lastFieldCarry'][$ci] ?? '';
            if ($now !== '' && $now !== $prev) {
                $differsFromCarry = true;
                break;
            }
        }

        $itemColHint = $cellAt(0);
        $itemColHint2 = $cellAt(1);
        $looksLikeItemRow = (preg_match('/^\s*\d+\s*$/', $itemColHint) || preg_match('/^\s*\d+\s*$/', $itemColHint2)) === 1;

        $hasGeo = $hasMunCol
            ? ($runtimeState['lastMunicipioCell'] !== '' || $cellMun !== '')
            : ($runtimeState['lastMicroId'] !== null);

        if (! $rowHasAny && $hasGeo && ($looksLikeItemRow || $differsFromCarry)) {
            $rowHasAny = true;
        }
        if (! $rowHasAny) {
            return;
        }

        $stats = &$runtimeState['stats'];

        if ($hasMunCol) {
            $municipioSearch = $cellMun !== '' ? $cellMun : $runtimeState['lastMunicipioCell'];
            if ($municipioSearch === '') {
                $stats['skipped'] = (int) ($stats['skipped'] ?? 0) + 1;
                $this->pushDiscardedFromCells($stats, $cells, $rowNumber, 'Sin municipio en fila', $colMicrorregion, $colMunicipio, $fieldColumnIndices, $cellMr, '', $hasMrCol, $hasMunCol, null, null);

                return;
            }
            // Si el Excel trae delegación/microrregión, úsala como referencia.
            // Un municipio que no pertenezca a esa MR se descarta para corrección manual.
            $municipioDB = $microId !== null
                ? $this->resolveMunicipioInMicrorregion($municipioSearch, $microId, $municipiosByMicro)
                : $this->resolveMunicipioGlobal($municipioSearch, null, $municipiosGlobalNorm, $municipiosByMicro);
            if (! $municipioDB) {
                $stats['skipped'] = (int) ($stats['skipped'] ?? 0) + 1;
                if (count((array) ($stats['unmatched'] ?? [])) < 150) {
                    $stats['unmatched'][] = ['row' => $rowNumber, 'reason' => 'Municipio: '.$municipioSearch];
                }
                $entryPayload = $this->buildSeedRowFieldPayloadFromCells(
                    $cells,
                    $rowNumber,
                    $fieldColumnIndices,
                    $indexToKey,
                    $runtimeState['lastFieldCarry'],
                    $municipiosGlobalNorm,
                    $municipiosByMicro,
                );
                $suggestions = $this->suggestMunicipiosForDiscard($municipioSearch, $microId, $municipiosGlobalNorm, $microLabels);
                $this->pushDiscardedFromCells(
                    $stats,
                    $cells,
                    $rowNumber,
                    'Municipio no resuelto: '.$municipioSearch,
                    $colMicrorregion,
                    $colMunicipio,
                    $fieldColumnIndices,
                    $cellMr,
                    $municipioSearch,
                    $hasMrCol,
                    $hasMunCol,
                    $entryPayload,
                    $suggestions,
                );

                return;
            }
            $microrregionId = (int) $municipioDB->microrregion_id;
            $userId = (int) ($userByMicro[$microrregionId] ?? $adminUserId);
            $data = [
                '_microrregion_reporte' => $microLabels[$microrregionId] ?? ('MR '.$microrregionId),
                '_municipio_reporte' => (string) $municipioDB->municipio,
            ];
        } else {
            if ($microId === null) {
                $stats['skipped'] = (int) ($stats['skipped'] ?? 0) + 1;
                $this->pushDiscardedFromCells($stats, $cells, $rowNumber, 'Sin microrregión en fila', $colMicrorregion, $colMunicipio, $fieldColumnIndices, $cellMr, '', $hasMrCol, $hasMunCol, null, null);

                return;
            }
            $microrregionId = $microId;
            $userId = (int) ($userByMicro[$microrregionId] ?? $adminUserId);
            $cab = $cabeceraPorMicro[$microrregionId] ?? '';
            $data = [
                '_microrregion_reporte' => $microLabels[$microrregionId] ?? ('MR '.$microrregionId),
                '_municipio_reporte' => $cab !== '' ? $cab.' (MR)' : 'MR '.$microrregionId,
            ];
        }

        // Llenado de campos con carry-over
        foreach ($fieldColumnIndices as $colIdx) {
            $colIdx = (int) $colIdx;
            $descriptor = $indexToKey[$colIdx] ?? null;
            if (! is_array($descriptor) || ! isset($descriptor['key'])) {
                continue;
            }
            $key = (string) $descriptor['key'];
            $raw = $cellAt($colIdx);
            if ($raw !== '') {
                $runtimeState['lastFieldCarry'][$colIdx] = $raw;
            }
            $resolved = $raw !== '' ? $raw : ($runtimeState['lastFieldCarry'][$colIdx] ?? '');
            $data[$key] = $this->normalizeSeedFieldValue(
                $resolved,
                (string) ($descriptor['type'] ?? 'text'),
                $descriptor['options'] ?? null,
                $municipiosGlobalNorm,
                $municipiosByMicro,
                is_array($descriptor['unifications'] ?? null) ? $descriptor['unifications'] : [],
            );
        }

        // Filas de totales/resumen
        $hasAggregateFormula = false;
        $hasNonFormulaValue = false;
        foreach ($fieldColumnIndices as $colIdx) {
            $txt = $cellAt((int) $colIdx);
            if ($txt === '') {
                continue;
            }
            if ($this->isAggregateFormulaValue($txt)) {
                $hasAggregateFormula = true;
                continue;
            }
            $hasNonFormulaValue = true;
        }
        if (! $looksLikeItemRow && $hasAggregateFormula && ! $hasNonFormulaValue) {
            return;
        }

        $data['_fila_excel'] = (string) $rowNumber;

        $now = Carbon::now();
        $pendingEntryRows[] = [
            'temporary_module_id' => $module->id,
            'user_id' => $userId,
            'microrregion_id' => $microrregionId,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'main_image_field_key' => null,
            'submitted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $runtimeState['microrregionIdsUsed'][$microrregionId] = true;
        $runtimeState['assignedUserIds'][$userId] = true;
        $stats['created'] = (int) ($stats['created'] ?? 0) + 1;
    }

    /**
     * Inserta registros precargados en bloque. Evita un INSERT por fila,
     * que era el cuello principal cuando un archivo trae miles de registros.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertSeedEntryRows(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('temporary_module_entries')->insert($chunk);
        }
    }

    /**
     * Equivalente a pushDiscarded() pero operando sobre array de celdas
     * (no necesita Worksheet).
     */
    private function pushDiscardedFromCells(
        array &$stats,
        array $cells,
        int $row,
        string $reason,
        int $colMicrorregion,
        int $colMunicipio,
        array $fieldColumnIndices,
        string $cellMr,
        string $municipioCell,
        bool $hasMrCol,
        bool $hasMunCol,
        ?array $entryPayload,
        ?array $suggestions,
    ): void {
        $accion = '';
        foreach ($fieldColumnIndices as $ci) {
            $t = trim((string) ($cells[(int) $ci] ?? ''));
            if (mb_strlen($t) > mb_strlen($accion)) {
                $accion = $t;
            }
        }
        if ($accion === '') {
            foreach ($fieldColumnIndices as $ci) {
                $t = trim((string) ($cells[(int) $ci] ?? ''));
                if ($t !== '') {
                    $accion = $t;
                    break;
                }
            }
        }
        if (mb_strlen($accion) > 220) {
            $accion = mb_substr($accion, 0, 217).'…';
        }
        $entry = [
            'discard_uid' => (string) Str::uuid(),
            'row' => $row,
            'reason' => $reason,
            'microrregion' => ($hasMrCol && $cellMr !== '') ? $cellMr : null,
            'municipio' => ($hasMunCol && $municipioCell !== '') ? $municipioCell : null,
            'accion' => $accion !== '' ? $accion : null,
            'log_type' => 'seed_discard',
        ];
        if (is_array($entryPayload) && $entryPayload !== []) {
            $entry['entry_payload'] = $entryPayload;
        }
        if (is_array($suggestions) && $suggestions !== []) {
            $entry['municipio_suggestions'] = $suggestions;
        }
        $stats['discarded'][] = $entry;
    }

    /**
     * Equivalente a buildSeedRowFieldPayload() para array de celdas.
     *
     * @return array<string, mixed>
     */
    private function buildSeedRowFieldPayloadFromCells(
        array $cells,
        int $row,
        array $fieldColumnIndices,
        array $indexToKey,
        array $lastFieldCarrySnapshot,
        array $municipiosGlobalNorm,
        array $municipiosByMicro,
    ): array {
        $carry = $lastFieldCarrySnapshot;
        $data = [];
        foreach ($fieldColumnIndices as $colIdx) {
            $colIdx = (int) $colIdx;
            $descriptor = $indexToKey[$colIdx] ?? null;
            if (! is_array($descriptor) || ! isset($descriptor['key'])) {
                continue;
            }
            $key = (string) $descriptor['key'];
            $raw = trim((string) ($cells[$colIdx] ?? ''));
            if ($raw !== '') {
                $carry[$colIdx] = $raw;
            }
            $resolved = $raw !== '' ? $raw : ($carry[$colIdx] ?? '');
            $data[$key] = $this->normalizeSeedFieldValue(
                $resolved,
                (string) ($descriptor['type'] ?? 'text'),
                $descriptor['options'] ?? null,
                $municipiosGlobalNorm,
                $municipiosByMicro,
                is_array($descriptor['unifications'] ?? null) ? $descriptor['unifications'] : [],
            );
        }
        $data['_fila_excel'] = (string) $row;

        return $data;
    }

    /**
     * Construye y devuelve los catálogos compartidos por todos los lotes.
     *
     * @return array{municipiosByMicro: array, microPorNumero: array, microByNormToken: array, municipiosGlobalNorm: array, microLabels: array, cabeceraPorMicro: array, userByMicro: array}
     */
    private function buildSeedCatalogs(): array
    {
        $municipiosRows = DB::table('municipios as mu')
            ->join('microrregiones as mr', 'mr.id', '=', 'mu.microrregion_id')
            ->select(['mu.id', 'mu.municipio', 'mu.microrregion_id', 'mr.microrregion as mr_num'])
            ->get();

        $municipiosByMicro = [];
        foreach ($municipiosRows as $m) {
            $mid = (int) $m->microrregion_id;
            $municipiosByMicro[$mid][] = $m;
        }

        $microPorNumero = [];
        $microByNormToken = [];
        $microLabels = [];
        $cabeceraPorMicro = [];
        foreach (DB::table('microrregiones')->select(['id', 'microrregion', 'cabecera'])->get() as $mr) {
            $id = (int) $mr->id;
            $n = trim((string) $mr->microrregion);
            $microPorNumero[$n] = $id;
            $num = (int) preg_replace('/\D/', '', $n) ?: (int) $n;
            if ($num > 0) {
                $microPorNumero[(string) $num] = $id;
                $microPorNumero[str_pad((string) $num, 2, '0', STR_PAD_LEFT)] = $id;
            }
            $cab = $this->normalizeMunicipioName((string) ($mr->cabecera ?? ''));
            if (mb_strlen($cab) >= 4) {
                $microByNormToken[$cab] = $id;
            }
            foreach (preg_split('/\s+/', $cab) ?: [] as $tok) {
                if (mb_strlen((string) $tok) >= 5) {
                    $microByNormToken[(string) $tok] = $id;
                }
            }
            $padded = str_pad(trim((string) $mr->microrregion), 2, '0', STR_PAD_LEFT);
            $microLabels[$id] = 'MR '.$padded.' '.trim((string) ($mr->cabecera ?? ''));
            $cabeceraPorMicro[$id] = trim((string) ($mr->cabecera ?? ''));
        }

        $municipiosGlobalNorm = [];
        foreach ($municipiosRows as $m) {
            $k = $this->normalizeMunicipioName($m->municipio);
            $municipiosGlobalNorm[$k][] = $m;
        }

        $userByMicro = [];
        foreach (DB::table('user_microrregion as um')->select(['um.microrregion_id', 'um.user_id'])->orderBy('um.user_id')->get() as $row) {
            $mid = (int) $row->microrregion_id;
            if (! isset($userByMicro[$mid])) {
                $userByMicro[$mid] = (int) $row->user_id;
            }
        }
        foreach (DB::table('delegados')->select(['microrregion_id', 'user_id'])->get() as $row) {
            $mid = (int) $row->microrregion_id;
            if ($mid > 0 && (int) $row->user_id > 0) {
                $userByMicro[$mid] = (int) $row->user_id;
            }
        }

        return compact(
            'municipiosByMicro',
            'microPorNumero',
            'microByNormToken',
            'municipiosGlobalNorm',
            'microLabels',
            'cabeceraPorMicro',
            'userByMicro',
        );
    }

    /**
     * Genera $preparedFields (para `fields()->createMany`) y $indexToKey
     * a partir de los headers y opciones del formulario.
     *
     * @return array{0: list<array<string,mixed>>, 1: array<int, array<string,mixed>>}
     */
    private function buildPreparedFieldsAndIndex(
        array $headerLabels,
        array $fieldColumnIndices,
        array $fieldTypesByColumn,
        array $fieldOptionsByColumn,
        array $fieldUnificationsByColumn,
    ): array {
        $usedKeys = [];
        $preparedFields = [];
        $sort = 1;
        foreach ($fieldColumnIndices as $idx) {
            $idx = (int) $idx;
            if ($idx < 0) {
                continue;
            }
            $label = (string) ($headerLabels[$idx] ?? '');
            if ($label === '') {
                $label = 'Columna_'.($idx + 1);
            }
            $key = Str::slug($label, '_');
            if ($key === '') {
                $key = 'campo_'.$sort;
            }
            $base = $key;
            $n = 2;
            while (in_array($key, $usedKeys, true)) {
                $key = $base.'_'.$n;
                $n++;
            }
            $usedKeys[] = $key;
            $fieldType = strtolower(trim((string) ($fieldTypesByColumn[$idx] ?? 'text')));
            if (! in_array($fieldType, ['text', 'textarea', 'number', 'date', 'datetime', 'select', 'multiselect', 'municipio', 'boolean', 'semaforo'], true)) {
                $fieldType = 'text';
            }
            $rawOptions = trim((string) ($fieldOptionsByColumn[$idx] ?? ''));
            $rawUnifications = trim((string) ($fieldUnificationsByColumn[$idx] ?? ''));
            $unificationMap = $this->parseSeedUnificationRules($fieldType, $rawUnifications);
            $preparedFields[] = [
                'label' => $label,
                'comment' => null,
                'key' => $key,
                'type' => $fieldType,
                'is_required' => false,
                'options' => $this->normalizeSeedFieldOptions($fieldType, $rawOptions, $unificationMap),
                'unifications' => $unificationMap,
                'sort_order' => $sort,
                'subsection_index' => null,
            ];
            $sort++;
        }

        $indexToKey = [];
        $fieldCols = array_values($fieldColumnIndices);
        foreach ($fieldCols as $i => $colIdx) {
            if (isset($preparedFields[$i])) {
                $indexToKey[(int) $colIdx] = [
                    'key' => $preparedFields[$i]['key'],
                    'type' => (string) ($preparedFields[$i]['type'] ?? 'text'),
                    'options' => $preparedFields[$i]['options'] ?? null,
                    'unifications' => $preparedFields[$i]['unifications'] ?? [],
                ];
            }
        }

        return [$preparedFields, $indexToKey];
    }

    private function buildUniqueSeedSlug(string $name): string
    {
        $slug = Str::slug($name);
        /** @var TemporaryModuleSlugService $slugSvc */
        $slugSvc = app(TemporaryModuleSlugService::class);
        $slugSvc->reclaimSlugForCreate($slug);
        $baseSlug = $slug;
        $suf = 2;
        while (TemporaryModule::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suf;
            $suf++;
            if ($suf > 999) {
                $slug = $baseSlug.'-'.substr(sha1(uniqid((string) mt_rand(), true)), 0, 8);
                break;
            }
        }

        return $slug;
    }

    private function raiseLimitsForDeferredCsvExport(): void
    {
        @ini_set('memory_limit', '2G');
        @set_time_limit(0);
    }

    /**
     * Exporta en streaming la hoja indicada desde data_start_row a CSV
     * (una línea = una fila Excel). Usado para lotes sin re-escanear el XLSX.
     *
     * @return int Número de filas escritas
     */
    private function exportDeferredSeedSheetToCsv(string $xlsxAbsolutePath, int $sheetIndex, int $dataStartRow, string $csvAbsolutePath): int
    {
        $writerOpts = new CsvWriterOptions();
        $writerOpts->SHOULD_ADD_BOM = false;

        $writer = new CsvWriter($writerOpts);
        $writer->openToFile($csvAbsolutePath);
        $written = 0;
        try {
            $reader = $this->makeStreamingXlsxReader();
            $reader->open($xlsxAbsolutePath);
            try {
                $idx = 0;
                foreach ($reader->getSheetIterator() as $sheet) {
                    if ($idx !== $sheetIndex) {
                        $idx++;
                        continue;
                    }
                    $rowNumber = 0;
                    foreach ($sheet->getRowIterator() as $row) {
                        $rowNumber++;
                        if ($rowNumber < $dataStartRow) {
                            continue;
                        }
                        $cells = $this->rowToStringCells($row);
                        $writer->addRow(Row::fromValues($cells));
                        $written++;
                    }
                    break;
                }
            } finally {
                $reader->close();
            }
        } finally {
            $writer->close();
        }

        return $written;
    }

    /**
     * @param  resource  $fh
     * @return list<string>|null null = EOF
     */
    private function readDeferredSeedCsvRow($fh): ?array
    {
        $row = fgetcsv($fh, 0, ',', '"', '');
        if ($row === false) {
            return null;
        }
        $out = [];
        foreach ($row as $cell) {
            $out[] = $cell === null ? '' : trim((string) $cell);
        }

        return $out;
    }

    private function makeStreamingXlsxReader(): XlsxReader
    {
        $options = new XlsxOptions();
        $options->SHOULD_FORMAT_DATES = true;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        return new XlsxReader($options);
    }

    /**
     * Aplica los atributos de evento cifrado al array de creación del módulo.
     *
     * @param  array<string, mixed>  $attrs  modificado in-place
     * @param  array{is_encrypted_event?:bool,edit_permission_duration_hours?:int,encrypted_pdf_password?:?string}  $config
     */
    private function applyEncryptionConfigToAttrs(array &$attrs, array $config): void
    {
        $isEncrypted = (bool) ($config['is_encrypted_event'] ?? false);
        $attrs['is_encrypted_event'] = $isEncrypted;
        $attrs['edit_permission_duration_hours'] = (int) ($config['edit_permission_duration_hours'] ?? 1);

        $rawPassword = $config['encrypted_pdf_password'] ?? null;
        if ($isEncrypted && is_string($rawPassword) && trim($rawPassword) !== '') {
            $attrs['pdf_password_encrypted'] = Crypt::encryptString($rawPassword);
        } else {
            $attrs['pdf_password_encrypted'] = null;
        }
    }
}
