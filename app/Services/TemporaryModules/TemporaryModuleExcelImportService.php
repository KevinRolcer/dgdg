<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use App\Models\TemporaryModuleField;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TemporaryModuleExcelImportService
{
    /** Tipos que se pueden llenar desde Excel (sin archivo/imagen). */
    public const IMPORTABLE_TYPES = [
        'text', 'textarea', 'number', 'date', 'datetime', 'select', 'multiselect', 'linked', 'boolean', 'categoria', 'municipio', 'geopoint', 'image', 'semaforo', 'delegado',
    ];

    public function importableFields(Collection $fields): Collection
    {
        return $fields->filter(fn (TemporaryModuleField $f) => in_array($f->type, self::IMPORTABLE_TYPES, true));
    }

    /**
     * Lee la fila de encabezados y sugiere mapeo campo -> índice de columna (0-based).
     *
     * @return array{headers: list<array{index:int,letter:string,label:string}>, suggested_map: array<string,int|null>, header_row:int, preview_thumbnails?: list<array{row:int,col:int,data_url:string}>}
     */
    public function preview(UploadedFile $file, int $headerRow = 1, bool $includeDrawingThumbnails = false, int $sheetIndex = 0): array
    {
        $headerRow = max(1, $headerRow);

        if ($includeDrawingThumbnails) {
            $spreadsheet = $this->loadSpreadsheet($file);
        } else {
            $spreadsheet = $this->loadSpreadsheetLightweight($file, $headerRow + 5);
        }

        $sheetNames = $spreadsheet->getSheetNames();
        $sheetIndex = max(0, min($sheetIndex, count($sheetNames) - 1));
        $sheet = $spreadsheet->getSheet($sheetIndex);

        // Fallback: scan up to 5 rows starting from headerRow, pick the one with most non-empty cells
        $bestHeaderRow = $headerRow;
        $bestNonEmpty = 0;
        $bestColCount = 0;
        $bestLabels = [];
        for ($tryRow = $headerRow; $tryRow < $headerRow + 5; $tryRow++) {
            $highestCol = $sheet->getHighestDataColumn($tryRow);
            $maxColIndex = Coordinate::columnIndexFromString($highestCol);
            $labels = [];
            $nonEmpty = 0;
            for ($col = 1; $col <= $maxColIndex; $col++) {
                $letter = Coordinate::stringFromColumnIndex($col);
                $raw = $sheet->getCell($letter.$tryRow)->getValue();
                $label = $this->stringifyCell($raw);
                $labels[] = [
                    'index' => $col - 1,
                    'letter' => $letter,
                    'label' => $label,
                ];
                if (trim($label) !== '') {
                    $nonEmpty++;
                }
            }
            if ($nonEmpty > $bestNonEmpty || ($nonEmpty === $bestNonEmpty && $maxColIndex > $bestColCount)) {
                $bestHeaderRow = $tryRow;
                $bestNonEmpty = $nonEmpty;
                $bestColCount = $maxColIndex;
                $bestLabels = $labels;
            }
        }

        $headers = $bestLabels;
        $headerRow = $bestHeaderRow;

        $result = [
            'success' => true,
            'headers' => $headers,
            'suggested_map' => [],
            'header_row' => $headerRow,
            'sheet_names' => $sheetNames,
            'sheet_index' => $sheetIndex,
        ];

        if ($includeDrawingThumbnails) {
            try {
                $result['preview_thumbnails'] = $this->buildDrawingPreviewThumbnails($sheet, 40, 72);
            } catch (\Throwable $e) {
                Log::warning('Vista previa Excel: miniaturas de dibujos omitidas: '.$e->getMessage());
                $result['preview_thumbnails'] = [];
            }
        }

        return $result;
    }

    /**
     * @return list<array{row:int,col:int,data_url:string}>
     */
    private function buildDrawingPreviewThumbnails(Worksheet $sheet, int $maxThumbs, int $maxWidthPx): array
    {
        if ($maxThumbs < 1 || ! extension_loaded('gd')) {
            return [];
        }

        $out = [];
        $seenCell = [];
        foreach ($this->allSheetDrawings($sheet) as $drawing) {
            if (count($out) >= $maxThumbs) {
                break;
            }
            if (! method_exists($drawing, 'getCoordinates')) {
                continue;
            }
            $coord = (string) $drawing->getCoordinates();
            if ($coord === '') {
                continue;
            }
            if (str_contains($coord, '!')) {
                $coord = explode('!', $coord)[1];
            }
            if (str_contains($coord, ':')) {
                $coord = explode(':', $coord)[0];
            }
            if (! preg_match('/^([A-Za-z]+)(\d+)$/', $coord, $m)) {
                continue;
            }
            $colLetter = strtoupper($m[1]);
            $row = (int) $m[2];
            $colIndex = Coordinate::columnIndexFromString($colLetter) - 1;
            $cellKey = $colLetter.$row;
            if (isset($seenCell[$cellKey])) {
                continue;
            }

            $resolved = $this->getDrawingBinaryAndExtension($drawing);
            if ($resolved === null) {
                continue;
            }
            [$binary, $ext] = $resolved;
            $dataUrl = $this->binaryToJpegThumbnailDataUri($binary, $maxWidthPx);
            if ($dataUrl === null) {
                continue;
            }
            $seenCell[$cellKey] = true;
            $out[] = [
                'row' => $row,
                'col' => $colIndex,
                'data_url' => $dataUrl,
            ];
        }

        return $out;
    }

    /**
     * @return list<mixed>
     */
    private function allSheetDrawings(Worksheet $sheet): array
    {
        return array_merge(
            iterator_to_array($sheet->getDrawingCollection()->getIterator(), false),
            iterator_to_array($sheet->getInCellDrawingCollection()->getIterator(), false),
        );
    }

    /**
     * @return array{0: string, 1: string}|null [binary, extension without dot]
     */
    private function getDrawingBinaryAndExtension(mixed $drawing): ?array
    {
        if ($drawing instanceof MemoryDrawing) {
            $image = $drawing->getImageResource();
            $renderingFunction = $drawing->getRenderingFunction();
            ob_start();
            switch ($renderingFunction) {
                case MemoryDrawing::RENDERING_JPEG:
                    imagejpeg($image);
                    $extension = 'jpg';
                    break;
                case MemoryDrawing::RENDERING_GIF:
                    imagegif($image);
                    $extension = 'gif';
                    break;
                case MemoryDrawing::RENDERING_PNG:
                case MemoryDrawing::RENDERING_DEFAULT:
                    imagepng($image);
                    $extension = 'png';
                    break;
                default:
                    imagepng($image);
                    $extension = 'png';
                    break;
            }
            $contents = ob_get_clean();
            if ($contents === false || $contents === '') {
                return null;
            }

            return [$contents, $extension];
        }

        if ($drawing instanceof Drawing) {
            $path = $drawing->getPath();
            if ($path === '') {
                return null;
            }
            if (preg_match('/^data:image\/(\w+);base64,(.+)$/i', $path, $m)) {
                $decoded = base64_decode($m[2], true);
                if ($decoded === false || $decoded === '') {
                    return null;
                }
                $ext = strtolower($m[1]);

                return [$decoded, $ext === 'jpeg' ? 'jpg' : $ext];
            }
            // zip:// y otras rutas de flujo: is_file() falla en Windows con zip:// aunque file_get_contents funcione
            $contents = @file_get_contents($path);
            if ($contents === false || $contents === '') {
                return null;
            }
            $ext = strtolower((string) $drawing->getExtension());
            $ext = ltrim($ext, '.');

            return [$contents, $ext !== '' ? $ext : 'jpg'];
        }

        return null;
    }

    private function binaryToJpegThumbnailDataUri(string $binary, int $maxWidthPx): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }
        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 1 || $h < 1) {
            imagedestroy($src);

            return null;
        }
        $nw = min($maxWidthPx, $w);
        $nh = (int) round($h * ($nw / $w));
        $dst = imagescale($src, $nw, $nh, IMG_NEAREST_NEIGHBOUR);
        imagedestroy($src);
        if ($dst === false) {
            return null;
        }
        ob_start();
        imagejpeg($dst, null, 40);
        imagedestroy($dst);
        $jpg = ob_get_clean();
        if ($jpg === false || $jpg === '') {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode($jpg);
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
    public function importRows(TemporaryModule $module, UploadedFile $file, array $options): array
    {
        $mapping = $options['mapping'] ?? [];
        $headerRow = (int) ($options['header_row'] ?? 1);
        $dataStartRow = (int) ($options['data_start_row'] ?? $headerRow + 1);
        $sheetIndex = (int) ($options['sheet_index'] ?? 0);
        $allMicrorregions = (bool) ($options['all_microrregions'] ?? false);
        $microrregionId = $options['selected_microrregion_id'] ? (int) $options['selected_microrregion_id'] : null;
        $userId = auth()->id();
        $headerRow = max(1, $headerRow);
        $dataStartRow = max($headerRow + 1, $dataStartRow);
        $spreadsheet = $this->loadSpreadsheet($file);
        $sheetIndex = max(0, min($sheetIndex, count($spreadsheet->getSheetNames()) - 1));
        $sheet = $spreadsheet->getSheet($sheetIndex);
        $highestRow = (int) $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $maxCol = Coordinate::columnIndexFromString($highestColumn);
        $drawingIndex = $this->buildDrawingIndex($sheet);

        $rowsData = [];
        for ($row = $dataStartRow; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($col = 1; $col <= $maxCol; $col++) {
                $letter = Coordinate::stringFromColumnIndex($col);

                // Prioridad a dibujos de Excel si el campo es de tipo imagen/archivo
                $matchedDrawing = $this->matchDrawingInCell($sheet, $drawingIndex, $letter, $row);
                if ($matchedDrawing) {
                    $savedPath = $this->saveExcelDrawing($matchedDrawing, $module, $letter . $row);
                    $rowData[$col - 1] = $savedPath ?: '';
                    continue;
                }

                $cell = $sheet->getCell($letter . $row);
                $raw = $cell->getValue();
                $calculated = $cell->getCalculatedValue();
                $rowData[$col - 1] = $calculated !== null ? $calculated : $raw;
            }
            $rowsData[] = $rowData;
        }

        return $this->importFromDataArray($module, $rowsData, array_merge($options, [
            'mapping' => $mapping,
            'row_offset' => $dataStartRow
        ]));
    }

    private function loadSpreadsheet(UploadedFile $file): Spreadsheet
    {
        $this->ensureMemoryForLargeFile($file);
        $path = $file->getRealPath();
        $reader = IOFactory::createReaderForFile($path);

        // Aseguramos que cargue dibujos y metadatos
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(false);
        }

        return $reader->load($path);
    }

    /**
     * Carga ligera: solo datos (sin dibujos/estilos), opcionalmente limitada a N filas.
     */
    private function loadSpreadsheetLightweight(UploadedFile $file, ?int $maxRow = null): Spreadsheet
    {
        $this->ensureMemoryForLargeFile($file);
        $path = $file->getRealPath();
        $reader = IOFactory::createReaderForFile($path);

        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        if ($maxRow !== null && method_exists($reader, 'setReadFilter')) {
            $reader->setReadFilter(new class($maxRow) implements IReadFilter {
                public function __construct(private int $maxRow) {}
                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return $row <= $this->maxRow;
                }
            });
        }

        return $reader->load($path);
    }

    /**
     * Sube el memory_limit de PHP si el archivo es grande (>20MB → 512M, >80MB → 1G).
     */
    private function ensureMemoryForLargeFile(UploadedFile $file): void
    {
        $sizeMb = $file->getSize() / 1_048_576;
        if ($sizeMb > 80) {
            @ini_set('memory_limit', '1G');
            @set_time_limit(300);
        } elseif ($sizeMb > 20) {
            @ini_set('memory_limit', '512M');
            @set_time_limit(180);
        }
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
        if (is_numeric($raw) && ! is_string($raw)) {
            return (string) $raw;
        }
        if ($raw instanceof \DateTimeInterface) {
            return $raw->format('Y-m-d H:i:s');
        }
        if (is_bool($raw)) {
            return $raw ? 'Sí' : 'No';
        }
        if (is_object($raw)) {
            if (method_exists($raw, '__toString')) {
                return trim((string) $raw);
            }

            return '';
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
        $str = preg_replace('/^[\s\x{00A0}\x{200B}\x{FEFF}]+|[\s\x{00A0}\x{200B}\x{FEFF}]+$/u', '', $strTrim);

        if ($t === 'number') {
            if ($str === '') {
                return null;
            }
            $n = filter_var(str_replace(',', '.', preg_replace('/[^\d.,\-]/', '', $str)), FILTER_VALIDATE_FLOAT);

            return $n !== false ? $n : null;
        }

        if ($t === 'boolean') {
            if ($str === '') {
                return null;
            }
            $u = mb_strtoupper($str, 'UTF-8');

            if (in_array($u, ['1', 'SI', 'SÍ', 'YES', 'TRUE', 'VERDADERO', 'X'], true)) {
                return true;
            }

            if (in_array($u, ['0', 'NO', 'FALSE', 'FALSO'], true)) {
                return false;
            }

            return null;
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

            if (! empty($validOpts)) {
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

                if (! empty($found)) {
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

            // 1. Exact match (case-insensitive)
            foreach ($opts as $o) {
                if (mb_strtoupper(trim($o), 'UTF-8') === mb_strtoupper($str, 'UTF-8')) {
                    return $o;
                }
            }

            // 2. Smart match: search for option labels within the cell text
            // We sort options by length descending to match 'Inactiva' before 'Activa'
            $sortedOpts = $opts;
            usort($sortedOpts, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

            $normStr = $this->normalizeLabel($str);
            foreach ($sortedOpts as $o) {
                $normO = $this->normalizeLabel($o);
                if ($normO !== '' && str_contains($normStr, $normO)) {
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

            // 1. Exact matches (full or sub)
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

            // 2. Smart match: search for category or sub-category names within cell text
            $normStr = $this->normalizeLabel($str);
            // Collect all possible valid strings for categories and sort by length
            $allPossible = [];
            foreach ($catOpts as $cat) {
                $allPossible[] = ['val' => (string) ($cat['name'] ?? ''), 'is_sub' => false];
                foreach ((array) ($cat['sub'] ?? []) as $sub) {
                    $allPossible[] = ['val' => ($cat['name'] ?? '').' > '.$sub, 'is_sub' => true, 'sub_only' => (string) $sub];
                }
            }
            usort($allPossible, fn ($a, $b) => mb_strlen((string) $b['val']) <=> mb_strlen((string) $a['val']));

            foreach ($allPossible as $item) {
                $labelToSearch = $item['is_sub'] ? $item['sub_only'] : $item['val'];
                $normLabel = $this->normalizeLabel((string) $labelToSearch);
                if ($normLabel !== '' && str_contains($normStr, $normLabel)) {
                    return $item['val'];
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

            // Conservar el texto original para que se puedan generar sugerencias
            // al reintentar la importación de este registro.
            return $str;
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

    /**
     * Construye un índice coordenada → dibujo para búsqueda rápida.
     * Combina getDrawingCollection() e getInCellDrawingCollection() de la hoja.
     */
    private function buildDrawingIndex(Worksheet $sheet): array
    {
        $index = [];
        foreach ($this->allSheetDrawings($sheet) as $drawing) {
            if (! method_exists($drawing, 'getCoordinates')) {
                continue;
            }
            $rawCoord = (string) $drawing->getCoordinates();
            if ($rawCoord === '') {
                continue;
            }
            // Limpiar prefijo de hoja ("Sheet1!G2" → "G2") y rangos ("G2:H3" → "G2")
            $clean = str_contains($rawCoord, '!') ? explode('!', $rawCoord)[1] : $rawCoord;
            if (str_contains($clean, ':')) {
                $clean = explode(':', $clean)[0];
            }
            // Solo guardar el primero encontrado por coordenada
            if (! isset($index[$clean])) {
                $index[$clean] = $drawing;
            }
        }

        return $index;
    }

    /**
     * Busca un dibujo en el índice que corresponda a la coordenada de la celda,
     * considerando celdas combinadas.
     */
    private function matchDrawingInCell(Worksheet $sheet, array $drawingIndex, string $letter, int $row): mixed
    {
        $coord = $letter.$row;

        // 1. Caso directo: "G2"
        if (isset($drawingIndex[$coord])) {
            return $drawingIndex[$coord];
        }

        // 2. Si la celda es parte de un rango combinado, buscar el dibujo en el inicio del rango
        foreach ($sheet->getMergeCells() as $mergeRange) {
            if ($sheet->getCell($coord)->isInRange($mergeRange)) {
                [$start] = explode(':', $mergeRange);
                if (isset($drawingIndex[$start])) {
                    return $drawingIndex[$start];
                }
            }
        }

        return null;
    }

    /**
     * Guarda un dibujo extraído de una celda de Excel en el almacenamiento compartido.
     */
    private function saveExcelDrawing(mixed $drawing, TemporaryModule $module, string $logCoord = ''): ?string
    {
        try {
            $resolved = $this->getDrawingBinaryAndExtension($drawing);
        } catch (\Throwable $e) {
            Log::error("Error procesando binario de dibujo en $logCoord: ".$e->getMessage());

            return null;
        }

        if ($resolved === null) {
            return null;
        }

        [$contents, $extension] = $resolved;
        if ($contents === '') {
            return null;
        }

        // Comprimir imagen antes de guardar para reducir espacio en disco
        $contents = $this->compressImageBinary($contents, $extension);

        $filename = 'imp_'.$module->id.'_'.bin2hex(random_bytes(8)).'.jpg';
        $secureDiskCfg = config('filesystems.disks.secure_shared');
        $storageDisk = ! empty($secureDiskCfg) ? 'secure_shared' : 'public';
        $storagePath = "temporary-modules/images/{$filename}";

        try {
            Storage::disk($storageDisk)->put($storagePath, $contents);
        } catch (\Throwable $e) {
            Log::error("Error guardando imagen extraída de Excel en disco $storageDisk: ".$e->getMessage());

            return null;
        }

        return $storagePath;
    }

    /**
     * Comprime un binario de imagen a JPEG redimensionado (max 1200px) con calidad 65%.
     */
    private function compressImageBinary(string $binary, string $extension): string
    {
        if (! extension_loaded('gd')) {
            return $binary;
        }
        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            return $binary;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $maxDim = 1200;
        if ($w > $maxDim || $h > $maxDim) {
            $ratio = min($maxDim / $w, $maxDim / $h);
            $nw = (int) round($w * $ratio);
            $nh = (int) round($h * $ratio);
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }
        ob_start();
        imagejpeg($src, null, 65);
        imagedestroy($src);
        $compressed = ob_get_clean();

        return ($compressed !== false && $compressed !== '') ? $compressed : $binary;
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

    /**
     * Asegura que si hay imágenes en la data del error, se incluyan sus data_urls base64.
     */
    public function hydrateErrorWithImages(array $error): array
    {
        if (empty($error['data']) || ! is_array($error['data'])) {
            return $error;
        }

        if (! isset($error['data_urls'])) {
            $error['data_urls'] = [];
        }

        foreach ($error['data'] as $k => $v) {
            if (is_string($v) && str_starts_with($v, 'temporary-modules/images/')) {
                try {
                    $disk = ! empty(config('filesystems.disks.secure_shared')) ? 'secure_shared' : 'public';
                    if (Storage::disk($disk)->exists($v)) {
                        $mime = Storage::mimeType($v) ?: 'image/jpeg';
                        $base64 = base64_encode(Storage::disk($disk)->get($v));
                        $error['data_urls'][$k] = "data:{$mime};base64,{$base64}";
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        return $error;
    }

    /**
     * Valida una sola fila (útil para correcciones manuales).
     * Recopila TODOS los errores (select/multiselect, requeridos, microrregión) con sugerencias.
     *
     * @return array|null El objeto de error si falla, null si es válido.
     */
    public function validateSingleRow($module, array $data, ?int $microrregionId, ?int $userId = null): ?array
    {
        $fields = $module->fields;
        $fieldsByKey = $fields->keyBy('key');
        $municipioField = $fields->firstWhere('type', 'municipio');
        $municipioKey = $municipioField ? $municipioField->key : null;

        // Limpiar datos: asegurar que existen todas las llaves para evitar offsets
        foreach ($fields as $field) {
            if (! array_key_exists($field->key, $data)) {
                $data[$field->key] = null;
            }
        }

        $failedFields = [];

        // 1. Validar valores de select/multiselect
        foreach ($fields as $f) {
            $val = $data[$f->key] ?? null;
            if ($val === null || $val === '' || (is_array($val) && empty($val))) {
                continue;
            }
            if ($f->type === 'select') {
                $opts = array_map('strval', $f->options ?? []);
                if (! empty($opts) && ! in_array((string) $val, $opts, true)) {
                    // Coincidencia case+accent insensitive
                    $optsNorm = array_map(fn ($o) => $this->normalizeLabel($o), $opts);
                    $matchIdx = array_search($this->normalizeLabel((string) $val), $optsNorm, true);
                    if ($matchIdx !== false) {
                        $data[$f->key] = $opts[$matchIdx];
                    } else {
                        $failedFields[] = [
                            'key' => $f->key,
                            'label' => (string) $f->label,
                            'reason' => 'No coincide con la lista.',
                            'received' => (string) $val,
                            'suggestions' => $opts,
                        ];
                    }
                }
            } elseif ($f->type === 'multiselect') {
                $opts = array_map('strval', $f->options ?? []);
                // Normalizar: si es string, convertir a array
                if (is_string($val)) {
                    if (in_array($val, $opts, true)) {
                        $valArray = [$val];
                    } else {
                        $valArray = array_values(array_filter(array_map('trim', preg_split('/[,;]+/', $val)), fn ($s) => $s !== ''));
                    }
                } else {
                    $valArray = is_array($val) ? $val : [$val];
                }
                // Coincidencia case+accent insensitive para cada elemento
                if (! empty($opts)) {
                    $optsNorm = array_map(fn ($o) => $this->normalizeLabel($o), $opts);
                    $resolved = [];
                    $invalid = [];
                    foreach ($valArray as $item) {
                        $idx = array_search($this->normalizeLabel((string) $item), $optsNorm, true);
                        if ($idx !== false) {
                            $resolved[] = $opts[$idx];
                        } else {
                            $invalid[] = $item;
                        }
                    }
                    if (! empty($invalid)) {
                        $failedFields[] = [
                            'key' => $f->key,
                            'label' => (string) $f->label,
                            'reason' => 'Contiene elementos inválidos.',
                            'received' => is_array($val) ? implode(', ', $val) : (string) $val,
                            'suggestions' => $opts,
                        ];
                    } else {
                        $data[$f->key] = $resolved;
                    }
                }
            }
        }

        // 2. Validar requeridos (si no están ya en failedFields)
        $failedKeys = array_column($failedFields, 'key');
        foreach ($fields as $field) {
            if ($field->is_required && ! in_array($field->key, $failedKeys, true)) {
                $val = $data[$field->key] ?? null;
                $isEmpty = $val === null || $val === '' || (is_string($val) && trim($val) === '');
                if ($isEmpty) {
                    $failedFields[] = [
                        'key' => $field->key,
                        'label' => (string) $field->label,
                        'reason' => 'Campo obligatorio vacío.',
                        'received' => '',
                    ];
                }
            }
        }

        // 3. Validar microrregión — si no se pudo resolver, generar sugerencias de municipio
        $suggestions = [];
        if ($microrregionId === null || $microrregionId <= 0) {
            if ($userId !== null && $municipioKey) {
                $micros = (new TemporaryModuleAccessService())->microrregionesConMunicipiosPorUsuario($userId);
                $suggBase = [];
                $munToMrMap = [];
                foreach ($micros as $m) {
                    foreach ($m->municipios ?? [] as $mun) {
                        $norm = $this->normalizeLabel($mun);
                        if ($norm !== '') {
                            $munToMrMap[$norm] = $m->id;
                            $suggBase[] = $mun;
                        }
                    }
                }
                $rawMunVal = trim((string) ($data[$municipioKey] ?? ''));
                if ($rawMunVal !== '') {
                    $suggestions = $this->suggestMunicipios($rawMunVal, $suggBase, $munToMrMap);
                }
            }
            $failedKeys = array_column($failedFields, 'key');
            if ($municipioKey && ! in_array($municipioKey, $failedKeys, true)) {
                $failedFields[] = [
                    'key' => $municipioKey,
                    'label' => $municipioField ? (string) $municipioField->label : 'Municipio',
                    'reason' => 'No coincide con la microrregión asignada.',
                    'received' => trim((string) ($data[$municipioKey] ?? '')),
                ];
            }
        }

        // Módulo sin municipio: la MR debe venir del import / corrección (no se infiere por catálogo).
        if (($microrregionId === null || $microrregionId <= 0) && $municipioKey === null) {
            $failedFields[] = [
                'key' => '__microrregion__',
                'label' => 'Microrregión',
                'reason' => 'No se recibió la microrregión de destino. Usa la misma importación donde elegiste la MR o corrige desde el modal de Excel.',
                'received' => '',
            ];
        }

        if (! empty($failedFields)) {
            return $this->hydrateErrorWithImages([
                'row' => 'Manual',
                'message' => 'datos no válidos en ' . count($failedFields) . ' campos.',
                'data' => $data,
                'municipio_key' => $municipioKey,
                'failed_fields' => $failedFields,
                'suggestions' => $suggestions,
            ]);
        }

        // 4. Validar duplicados en DB
        $targetSignature = $this->buildDuplicateSignature($data);
        $duplicateId = null;
        $module->entries()
            ->select(['id', 'data'])
            ->where('microrregion_id', $microrregionId)
            ->orderBy('id')
            ->chunk(500, function ($entries) use ($targetSignature, &$duplicateId) {
                foreach ($entries as $entry) {
                    $entrySignature = $this->buildDuplicateSignature((array) ($entry->data ?? []));
                    if (hash_equals($targetSignature, $entrySignature)) {
                        $duplicateId = (int) $entry->id;
                        return false;
                    }
                }
                return true;
            });

        if ($duplicateId !== null) {
            // Obtener datos del conflicto
            $conflictEntry = \App\Models\TemporaryModuleEntry::find($duplicateId);
            $conflictData = (array) ($conflictEntry->data ?? []);
            // Hydrate conflict images
            foreach ($conflictData as $k => $v) {
                if (is_string($v) && str_starts_with($v, 'temporary-modules/images/')) {
                    try {
                        $disk = ! empty(config('filesystems.disks.secure_shared')) ? 'secure_shared' : 'public';
                        if (Storage::disk($disk)->exists($v)) {
                            $mime = Storage::mimeType($v) ?: 'image/jpeg';
                            $base64 = base64_encode(Storage::disk($disk)->get($v));
                            $conflictData[$k] = "data:{$mime};base64,{$base64}";
                        }
                    } catch (\Throwable $e) {}
                }
            }

            return $this->hydrateErrorWithImages([
                'row' => 'Manual',
                'is_duplicate' => true,
                'duplicate_type' => 'database',
                'original_row' => 'db',
                'conflict_data' => $conflictData,
                'message' => 'Registro duplicado: ya existe en el sistema.',
                'data' => $data,
                'failed_fields' => [[
                    'key' => '__duplicate__',
                    'label' => 'Registro duplicado',
                    'reason' => 'Este registro ya existe en el sistema para esta microrregión.',
                    'received' => '',
                ]],
                'suggestions' => [],
            ]);
        }

        return null; // Todo OK
    }

    /**
     * Generalized importer from a 2D array of data.
     */
    public function importFromDataArray(TemporaryModule $module, array $rows, array $options): array
    {
        $mapping = $options['mapping'] ?? [];
        $rowOffset = (int) ($options['row_offset'] ?? 1);
        $allMicrorregions = (bool) ($options['all_microrregions'] ?? false);
        $microrregionId = $options['selected_microrregion_id'] ? (int) $options['selected_microrregion_id'] : null;
        $selectedMunicipio = trim((string) ($options['selected_municipio'] ?? ''));
        $autoIdentifyMunicipio = (bool) ($options['auto_identify_municipio'] ?? true);
        $userId = auth()->id();

        $importable = $module->fields->filter(fn ($f) => in_array($f->type, self::IMPORTABLE_TYPES, true));
        $fieldsByKey = $importable->keyBy('key');
        $requiredKeys = $importable->filter(fn ($f) => $f->is_required)->pluck('key')->all();

        $municipioField = $importable->firstWhere('type', 'municipio');
        $municipioKey = $municipioField ? $municipioField->key : null;
        $municipioMapped = $municipioKey !== null
            && array_key_exists($municipioKey, $mapping)
            && $mapping[$municipioKey] !== null;

        $microrregionesAsignadas = (new TemporaryModuleAccessService())->microrregionesConMunicipiosPorUsuario($userId);
        $suggestionBaseMunicipios = [];
        $municipioToMrMap = [];
        foreach ($microrregionesAsignadas as $micro) {
            foreach (($micro->municipios ?? []) as $mName) {
                $norm = $this->normalizeLabel($mName);
                if ($norm !== '') {
                    $municipioToMrMap[$norm] = $micro->id;
                    $suggestionBaseMunicipios[] = $mName;
                }
            }
        }

        $existingSignaturesByMicro = [];
        $microsToPreload = $allMicrorregions ? $microrregionesAsignadas->pluck('id')->all() : ($microrregionId ? [$microrregionId] : []);

        if (! empty($microsToPreload)) {
            $module->entries()
                ->select(['id', 'microrregion_id', 'data'])
                ->whereIn('microrregion_id', $microsToPreload)
                ->orderBy('id')
                ->chunk(1000, function ($entries) use (&$existingSignaturesByMicro) {
                    foreach ($entries as $e) {
                        $sig = $this->buildDuplicateSignature((array) ($e->data ?? []));
                        $existingSignaturesByMicro[(int) $e->microrregion_id][$sig] = (array) ($e->data ?? []);
                    }
                });
        }

        $imported = 0;
        $skipped = 0;
        $rowErrors = [];
        $pendingSignaturesByMicro = [];
        $firstOccurrenceRowByMicro = [];
        $firstOccurrenceDataByMicro = [];

        foreach ($rows as $index => $rowData) {
            $row = $index + $rowOffset;
            $values = [];
            $rawValues = [];
            $hasAnyMappedData = false;
            $hasAnyOverallData = false;

            foreach ($rowData as $val) {
                if ($val !== null && trim((string)$val) !== '') {
                    $hasAnyOverallData = true;
                    break;
                }
            }

            foreach ($mapping as $fieldKey => $colIndex) {
                if ($colIndex === null || !isset($fieldsByKey[$fieldKey])) continue;

                $field = $fieldsByKey[$fieldKey];
                $col = (int) $colIndex;
                $raw = $rowData[$col] ?? null;

                if (in_array($field->type, ['image', 'file'], true) && is_string($raw) && str_starts_with($raw, 'temporary-modules/')) {
                    $values[$fieldKey] = $raw;
                    $rawValues[$fieldKey] = '[Imagen]';
                    if ($raw !== '') $hasAnyMappedData = true;
                    continue;
                }

                $str = $this->stringifyCell($raw);
                $rawValues[$fieldKey] = $str;
                if (trim($str) !== '') $hasAnyMappedData = true;

                $values[$fieldKey] = $this->coerceValue($field, $raw, $raw, $str, $suggestionBaseMunicipios);
            }

            // Si el import no trae columna municipio (o no se desea auto-identificar),
            // permite fijar un municipio manual para todas las filas.
            if ($municipioKey !== null && $selectedMunicipio !== '') {
                $rawMunicipio = trim((string) ($rawValues[$municipioKey] ?? ''));
                $hasAutoMunicipio = $municipioMapped && $autoIdentifyMunicipio && $rawMunicipio !== '';
                if (! $hasAutoMunicipio) {
                    $values[$municipioKey] = $selectedMunicipio;
                    $rawValues[$municipioKey] = $selectedMunicipio;
                    $hasAnyMappedData = true;
                }
            }

            if (!$hasAnyMappedData) {
                if ($hasAnyOverallData) {
                    $rowErrors[] = [
                        'row' => $row,
                        'message' => "Fila {$row}: las columnas mapeadas están vacías, pero la fila contiene otros datos.",
                        'data' => $values,
                        'failed_fields' => [['key' => '__empty_mapped__', 'label' => 'Columnas mapeadas', 'reason' => 'Ninguna de las columnas asignadas tiene datos.', 'received' => '']],
                        'suggestions' => [],
                        'selected_microrregion_id' => $microrregionId,
                    ];
                }
                $skipped++;
                continue;
            }

            $failedFields = [];
            foreach ($importable as $f) {
                $val = $values[$f->key] ?? null;
                if ($val === null || $val === '' || (is_array($val) && empty($val))) continue;

                if ($f->type === 'select') {
                    $opts = array_map('strval', $f->options ?? []);
                    if (!empty($opts) && !in_array((string)$val, $opts, true)) {
                        // Fallback: coincidencia case+accent insensitive
                        $optsNorm = array_map(fn ($o) => $this->normalizeLabel($o), $opts);
                        $matchIdx = array_search($this->normalizeLabel((string) $val), $optsNorm, true);
                        if ($matchIdx !== false) {
                            $values[$f->key] = $opts[$matchIdx];
                        } else {
                            $failedFields[] = ['key' => $f->key, 'label' => $f->label, 'reason' => 'No coincide con la lista.', 'received' => (string)($rawValues[$f->key] ?? ''), 'suggestions' => $opts];
                        }
                    }
                } elseif ($f->type === 'multiselect') {
                    $opts = array_map('strval', $f->options ?? []);
                    $valArray = is_array($val) ? $val : [$val];
                    if (!empty($opts)) {
                        $optsNorm = array_map(fn ($o) => $this->normalizeLabel($o), $opts);
                        $resolved = [];
                        $invalidOnes = [];
                        foreach ($valArray as $item) {
                            $idx = array_search($this->normalizeLabel((string) $item), $optsNorm, true);
                            if ($idx !== false) {
                                $resolved[] = $opts[$idx];
                            } else {
                                $invalidOnes[] = $item;
                            }
                        }
                        if (!empty($invalidOnes)) {
                            $failedFields[] = ['key' => $f->key, 'label' => $f->label, 'reason' => 'Contiene elementos inválidos.', 'received' => (string)($rawValues[$f->key] ?? ''), 'suggestions' => $opts];
                        } else {
                            $values[$f->key] = $resolved;
                        }
                    }
                }
            }

            if (!empty($failedFields)) {
                $rowErrors[] = [
                    'row' => $row,
                    'message' => "Fila {$row}: datos no válidos en " . count($failedFields) . ' campos.',
                    'data' => $values,
                    'failed_fields' => $failedFields,
                    'suggestions' => (count($failedFields) === 1) ? ($failedFields[0]['suggestions'] ?? []) : [],
                    'selected_microrregion_id' => $microrregionId,
                ];
                $skipped++;
                continue;
            }

            foreach ($requiredKeys as $rk) {
                $v = $values[$rk] ?? null;
                if ($v === null || $v === '' || (is_string($v) && trim($v) === '')) {
                    $suggestions = [];
                    if ($municipioKey && $rk === $municipioKey) {
                        $suggestions = $this->suggestMunicipios(trim((string)($rawValues[$rk] ?? '')), $suggestionBaseMunicipios, $municipioToMrMap);
                    }
                    $rowErrors[] = $this->hydrateErrorWithImages([
                        'row' => $row,
                        'message' => "Fila {$row}: falta campo obligatorio ({$fieldsByKey[$rk]->label}).",
                        'data' => $values,
                        'municipio_key' => $rk === $municipioKey ? $rk : null,
                        'failed_fields' => [['key' => $rk, 'label' => (string) $fieldsByKey[$rk]->label, 'reason' => 'Falta dato.', 'received' => (string) ($rawValues[$rk] ?? '')]],
                        'suggestions' => $suggestions,
                        'selected_microrregion_id' => $microrregionId,
                    ]);
                    $skipped++;
                    continue 2;
                }
            }

            $rowMrId = $microrregionId;
            if ($rowMrId === null && $municipioKey) {
                $munVal = $values[$municipioKey] ?? null;
                if ($munVal) $rowMrId = $municipioToMrMap[$this->normalizeLabel($munVal)] ?? null;
            }

            if ($rowMrId === null || $rowMrId <= 0) {
                $rawMunVal = $municipioKey ? trim((string)($rawValues[$municipioKey] ?? '')) : '';
                $suggestions = $rawMunVal !== '' ? $this->suggestMunicipios($rawMunVal, $suggestionBaseMunicipios, $municipioToMrMap) : [];
                $rowErrors[] = $this->hydrateErrorWithImages([
                    'row' => $row,
                    'message' => "Fila {$row}: No se pudo determinar la microrregión.",
                    'data' => $values,
                    'municipio_key' => $municipioKey,
                    'failed_fields' => [['key' => $municipioKey, 'label' => 'Municipio', 'reason' => 'No coincide con MR asignada.', 'received' => $rawMunVal]],
                    'suggestions' => $suggestions,
                    'selected_microrregion_id' => $microrregionId,
                ]);
                $skipped++;
                continue;
            }

            foreach ($importable as $field) {
                if (!array_key_exists($field->key, $values)) {
                    $values[$field->key] = null;
                }
            }
            $rowSignature = $this->buildDuplicateSignature($values);
            $microKey = (int)$rowMrId;

            $existingDuplicate = isset($existingSignaturesByMicro[$microKey][$rowSignature]);
            $excelDuplicate = isset($pendingSignaturesByMicro[$microKey][$rowSignature]);

            if ($existingDuplicate || $excelDuplicate) {
                $duplicateRef = $excelDuplicate ? ($firstOccurrenceRowByMicro[$microKey][$rowSignature] ?? null) : 'db';
                $conflictData = $excelDuplicate ? ($firstOccurrenceDataByMicro[$microKey][$rowSignature] ?? []) : ($existingSignaturesByMicro[$microKey][$rowSignature] ?? []);

                foreach ($conflictData as $k => $v) {
                    if (is_string($v) && str_starts_with($v, 'temporary-modules/images/')) {
                        try {
                            $disk = !empty(config('filesystems.disks.secure_shared')) ? 'secure_shared' : 'public';
                            if (\Storage::disk($disk)->exists($v)) {
                                $mime = \Storage::mimeType($v) ?: 'image/jpeg';
                                $base64 = base64_encode(\Storage::disk($disk)->get($v));
                                $conflictData[$k] = "data:{$mime};base64,{$base64}";
                            }
                        } catch (\Throwable) {}
                    }
                }

                $rowErrors[] = $this->hydrateErrorWithImages([
                    'row' => $row,
                    'is_duplicate' => true,
                    'duplicate_type' => $excelDuplicate ? 'excel' : 'database',
                    'original_row' => $duplicateRef,
                    'conflict_data' => $conflictData,
                    'message' => "Fila {$row}: registro duplicado (" . ($excelDuplicate ? "fila {$duplicateRef}" : 'ya existe') . ').',
                    'data' => $values,
                    'failed_fields' => [['key' => '__duplicate__', 'label' => 'Duplicado', 'reason' => 'Ya existe en el sistema o archivo.', 'received' => '']],
                    'suggestions' => [],
                    'selected_microrregion_id' => $rowMrId,
                ]);
                $skipped++;
                continue;
            }

            if (!isset($firstOccurrenceRowByMicro[$microKey][$rowSignature])) {
                $firstOccurrenceRowByMicro[$microKey][$rowSignature] = $row;
                $firstOccurrenceDataByMicro[$microKey][$rowSignature] = $values;
            }
            $pendingSignaturesByMicro[$microKey][$rowSignature] = true;

            $module->entries()->create(['user_id' => $userId, 'microrregion_id' => $rowMrId, 'data' => $values, 'submitted_at' => \Carbon\Carbon::now()]);
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'row_errors' => array_slice($rowErrors, 0, 50)];
    }

    /**
     * Actualiza registros existentes con datos faltantes (ej. imágenes) desde un Excel.
     *
     * Compara los campos de texto/datos del Excel con los registros existentes en la BD.
     * Si un registro existente coincide y tiene campos vacíos que el Excel sí tiene (especialmente imágenes),
     * se actualizan solo esos campos vacíos.
     *
     * @return array{updated:int, skipped:int, row_errors:list<array>}
     */
    public function updateExistingEntries(TemporaryModule $module, UploadedFile $file, array $options): array
    {
        $mapping = $options['mapping'] ?? [];
        $headerRow = (int) ($options['header_row'] ?? 1);
        $dataStartRow = (int) ($options['data_start_row'] ?? $headerRow + 1);
        $sheetIndex = (int) ($options['sheet_index'] ?? 0);
        $allMicrorregions = (bool) ($options['all_microrregions'] ?? false);
        $microrregionId = $options['selected_microrregion_id'] ? (int) $options['selected_microrregion_id'] : null;
        $userId = auth()->id();
        $headerRow = max(1, $headerRow);
        $dataStartRow = max($headerRow + 1, $dataStartRow);
        $spreadsheet = $this->loadSpreadsheet($file);
        $sheetIndex = max(0, min($sheetIndex, count($spreadsheet->getSheetNames()) - 1));
        $sheet = $spreadsheet->getSheet($sheetIndex);
        $highestRow = (int) $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $maxCol = Coordinate::columnIndexFromString($highestColumn);
        $drawingIndex = $this->buildDrawingIndex($sheet);

        // Leer todas las filas del Excel con prioridad a imágenes
        $rowsData = [];
        for ($row = $dataStartRow; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($col = 1; $col <= $maxCol; $col++) {
                $letter = Coordinate::stringFromColumnIndex($col);
                $matchedDrawing = $this->matchDrawingInCell($sheet, $drawingIndex, $letter, $row);
                if ($matchedDrawing) {
                    $savedPath = $this->saveExcelDrawing($matchedDrawing, $module, $letter . $row);
                    $rowData[$col - 1] = $savedPath ?: '';
                    continue;
                }
                $cell = $sheet->getCell($letter . $row);
                $raw = $cell->getValue();
                $calculated = $cell->getCalculatedValue();
                $rowData[$col - 1] = $calculated !== null ? $calculated : $raw;
            }
            $rowsData[] = $rowData;
        }

        return $this->updateFromDataArray($module, $rowsData, array_merge($options, [
            'mapping' => $mapping,
            'row_offset' => $dataStartRow,
        ]));
    }

    /**
     * Busca registros existentes que coincidan por campos de texto/datos y completa campos vacíos.
     */
    public function updateFromDataArray(TemporaryModule $module, array $rows, array $options): array
    {
        $mapping = $options['mapping'] ?? [];
        $rowOffset = (int) ($options['row_offset'] ?? 1);
        $allMicrorregions = (bool) ($options['all_microrregions'] ?? false);
        $microrregionId = $options['selected_microrregion_id'] ? (int) $options['selected_microrregion_id'] : null;
        $userId = auth()->id();

        $importable = $module->fields->filter(fn ($f) => in_array($f->type, self::IMPORTABLE_TYPES, true));
        $fieldsByKey = $importable->keyBy('key');

        $municipioField = $importable->firstWhere('type', 'municipio');
        $municipioKey = $municipioField ? $municipioField->key : null;

        $microrregionesAsignadas = (new TemporaryModuleAccessService())->microrregionesConMunicipiosPorUsuario($userId);
        $suggestionBaseMunicipios = [];
        $municipioToMrMap = [];
        foreach ($microrregionesAsignadas as $micro) {
            foreach (($micro->municipios ?? []) as $mName) {
                $norm = $this->normalizeLabel($mName);
                if ($norm !== '') {
                    $municipioToMrMap[$norm] = $micro->id;
                    $suggestionBaseMunicipios[] = $mName;
                }
            }
        }

        // Cargar todos los registros existentes del módulo para las microrregiones permitidas
        $microsToPreload = $allMicrorregions
            ? $microrregionesAsignadas->pluck('id')->all()
            : ($microrregionId ? [$microrregionId] : []);

        $existingEntries = [];
        if (! empty($microsToPreload)) {
            $module->entries()
                ->whereIn('microrregion_id', $microsToPreload)
                ->orderBy('id')
                ->chunk(1000, function ($entries) use (&$existingEntries) {
                    foreach ($entries as $entry) {
                        $existingEntries[] = $entry;
                    }
                });
        }

        // Campos que se usan como "llave de coincidencia" (todos los campos de texto/datos, no imágenes)
        $matchFields = $importable->filter(fn ($f) => ! in_array($f->type, ['image', 'file'], true));
        // Campos que se pueden completar (imágenes y archivos, más cualquier campo vacío)
        $fillableFields = $importable;

        $updated = 0;
        $skipped = 0;
        $rowErrors = [];

        foreach ($rows as $index => $rowData) {
            $row = $index + $rowOffset;
            $values = [];
            $hasAnyMappedData = false;

            foreach ($mapping as $fieldKey => $colIndex) {
                if ($colIndex === null || ! isset($fieldsByKey[$fieldKey])) {
                    continue;
                }
                $field = $fieldsByKey[$fieldKey];
                $col = (int) $colIndex;
                $raw = $rowData[$col] ?? null;

                if (in_array($field->type, ['image', 'file'], true) && is_string($raw) && str_starts_with($raw, 'temporary-modules/')) {
                    $values[$fieldKey] = $raw;
                    if ($raw !== '') {
                        $hasAnyMappedData = true;
                    }
                    continue;
                }

                $str = $this->stringifyCell($raw);
                if (trim($str) !== '') {
                    $hasAnyMappedData = true;
                }
                $values[$fieldKey] = $this->coerceValue($field, $raw, $raw, $str, $suggestionBaseMunicipios);
            }

            if (! $hasAnyMappedData) {
                $skipped++;
                continue;
            }

            // Buscar registro existente que coincida por los campos de texto
            $matchedEntry = $this->findMatchingEntry($existingEntries, $values, $matchFields);

            if ($matchedEntry === null) {
                $rowErrors[] = [
                    'row' => $row,
                    'message' => "Fila {$row}: no se encontró un registro existente que coincida.",
                    'data' => $values,
                    'failed_fields' => [['key' => '__no_match__', 'label' => 'Coincidencia', 'reason' => 'No hay registro existente con los mismos datos de texto.', 'received' => '']],
                    'suggestions' => [],
                ];
                $skipped++;
                continue;
            }

            // Determinar qué campos del registro existente están vacíos y el Excel los tiene
            $entryData = (array) ($matchedEntry->data ?? []);
            $fieldsToUpdate = [];

            foreach ($fillableFields as $field) {
                $excelVal = $values[$field->key] ?? null;
                $existingVal = $entryData[$field->key] ?? null;

                // ¿El campo del registro existente está vacío?
                $existingEmpty = $existingVal === null || $existingVal === ''
                    || (is_string($existingVal) && trim($existingVal) === '');

                // ¿El Excel tiene un valor para este campo?
                $excelHasValue = $excelVal !== null && $excelVal !== ''
                    && (! is_string($excelVal) || trim($excelVal) !== '');

                if ($existingEmpty && $excelHasValue) {
                    $fieldsToUpdate[$field->key] = $excelVal;
                }
            }

            if (empty($fieldsToUpdate)) {
                $rowErrors[] = [
                    'row' => $row,
                    'message' => "Fila {$row}: el registro ya tiene todos los campos completos.",
                    'data' => $values,
                    'failed_fields' => [['key' => '__complete__', 'label' => 'Sin cambios', 'reason' => 'No hay campos vacíos que completar.', 'received' => '']],
                    'suggestions' => [],
                ];
                $skipped++;
                continue;
            }

            // Aplicar la actualización parcial
            $newData = array_merge($entryData, $fieldsToUpdate);
            $matchedEntry->data = $newData;
            $matchedEntry->save();
            $updated++;
        }

        return ['updated' => $updated, 'skipped' => $skipped, 'row_errors' => array_slice($rowErrors, 0, 50)];
    }

    /**
     * Busca un registro existente que coincida por los campos de comparación (no-imagen).
     */
    private function findMatchingEntry(array $entries, array $excelValues, Collection $matchFields): ?object
    {
        $bestMatch = null;
        $bestScore = 0;

        foreach ($entries as $entry) {
            $entryData = (array) ($entry->data ?? []);
            $score = 0;
            $totalMatchable = 0;

            foreach ($matchFields as $field) {
                $excelVal = $excelValues[$field->key] ?? null;
                $entryVal = $entryData[$field->key] ?? null;

                // Saltar campos que no están en el Excel (no mapeados)
                if ($excelVal === null || ($excelVal === '' && ($entryVal === null || $entryVal === ''))) {
                    continue;
                }

                $totalMatchable++;

                $normExcel = $this->normalizeLabel($this->stringifyCell($excelVal));
                $normEntry = $this->normalizeLabel($this->stringifyCell($entryVal));

                if ($normExcel === $normEntry) {
                    $score++;
                }
            }

            // Necesita al menos 2 campos coincidentes y un 80% de coincidencia
            if ($totalMatchable >= 2 && $score > 0) {
                $pct = $score / $totalMatchable;
                if ($pct >= 0.8 && $score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = $entry;
                }
            } elseif ($totalMatchable === 1 && $score === 1 && $bestScore === 0) {
                $bestMatch = $entry;
                $bestScore = $score;
            }
        }

        return $bestMatch;
    }
}
