<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use Smalot\PdfParser\Parser;
use Smalot\PdfParser\Page;
use Smalot\PdfParser\PDFObject;
use Smalot\PdfParser\Element\ElementArray;
use Smalot\PdfParser\Element\ElementXRef;
use Smalot\PdfParser\XObject\Image as PdfImageXObject;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TemporaryModulePdfImportService
{
    private TemporaryModuleExcelImportService $excelService;

    public function __construct(TemporaryModuleExcelImportService $excelService)
    {
        $this->excelService = $excelService;
    }

    /**
     * Preview: extract headers and a few sample rows from the PDF table.
     */
    public function preview(TemporaryModule $module, string $filePath, int $headerRow = 1): array
    {
        try {
            $dataset = $this->extractTableDataset($filePath);
            $allRows = $dataset['rows'];

            if (empty($allRows)) {
                return [
                    'success' => false,
                    'message' => 'No se detectaron tablas o datos legibles en el PDF.',
                ];
            }

            // Fallback: scan up to 5 rows starting from headerRow, pick the one with most non-empty cells
            $bestHeaderRowIdx = max(0, $headerRow - 1);
            $bestNonEmpty = 0;
            $bestColCount = 0;
            $bestLabels = [];
            for ($tryIdx = $bestHeaderRowIdx; $tryIdx < $bestHeaderRowIdx + 5 && $tryIdx < count($allRows); $tryIdx++) {
                $row = $allRows[$tryIdx];
                $nonEmpty = 0;
                foreach ($row as $label) {
                    if (trim((string)$label) !== '') {
                        $nonEmpty++;
                    }
                }
                if ($nonEmpty > $bestNonEmpty || ($nonEmpty === $bestNonEmpty && count($row) > $bestColCount)) {
                    $bestHeaderRowIdx = $tryIdx;
                    $bestNonEmpty = $nonEmpty;
                    $bestColCount = count($row);
                    $bestLabels = $row;
                }
            }

            $rawHeaders = $bestLabels;

            $headers = [];
            foreach ($rawHeaders as $idx => $label) {
                $headers[] = [
                    'index' => $idx,
                    'letter' => $this->colLetter($idx),
                    'label' => trim($label) ?: 'Columna ' . ($idx + 1),
                ];
            }

            // Build preview rows (all data rows after header)
            $previewRows = [];
            foreach ($allRows as $i => $row) {
                $previewRows[] = array_values($row);
            }

            return [
                'success' => true,
                'headers' => $headers,
                'is_pdf' => true,
                'preview_rows' => $previewRows,
                'preview_thumbnails' => $dataset['preview_thumbnails'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error('PDF Preview Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al leer el PDF: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Import rows from PDF, reusing the Excel service's importFromDataArray.
     */
    public function import(TemporaryModule $module, array $options): array
    {
        $filePath = $options['file_path'] ?? null;
        $headerRow = (int) ($options['header_row'] ?? 1);
        $dataStartRow = (int) ($options['data_start_row'] ?? 2);

        if (! $filePath || ! file_exists($filePath)) {
            return ['imported' => 0, 'skipped' => 0, 'row_errors' => []];
        }

        try {
            $dataset = $this->extractTableDataset($filePath, $module, true);
            $allRows = $dataset['rows'];

            // Normalise column count to match header
            $headerIdx = max(0, $headerRow - 1);
            $colCount = count($allRows[$headerIdx] ?? []);
            foreach ($allRows as &$r) {
                $r = array_pad(array_slice($r, 0, $colCount), $colCount, '');
            }
            unset($r);

            $dataRows = array_values(array_slice($allRows, $dataStartRow - 1));
            $imageCells = $dataset['image_cells'] ?? [];
            if (! empty($imageCells) && ! empty($dataRows)) {
                foreach ($imageCells as $cell) {
                    $row0 = (int) ($cell['row'] ?? -1);
                    $col0 = (int) ($cell['col'] ?? -1);
                    $path = (string) ($cell['path'] ?? '');
                    if ($path === '' || $row0 < 0 || $col0 < 0) {
                        continue;
                    }
                    if ($row0 < ($dataStartRow - 1)) {
                        continue;
                    }
                    $targetRow = $row0 - ($dataStartRow - 1);
                    if (! isset($dataRows[$targetRow])) {
                        continue;
                    }
                    $dataRows[$targetRow][$col0] = $path;
                }
            }

            $result = $this->excelService->importFromDataArray($module, $dataRows, array_merge($options, [
                'row_offset' => $dataStartRow,
            ]));
            $result['pdf_images_saved'] = count($imageCells);

            return $result;
        } catch (\Throwable $e) {
            Log::error('PDF Import Error: ' . $e->getMessage());
            return ['imported' => 0, 'skipped' => 0, 'row_errors' => []];
        }
    }

    // ─── table extraction engine ───────────────────────────

    /**
     * Extract structured rows from all pages of a PDF.
     * Uses positional (TM) data to reconstruct the grid.
     */
    private function extractTableRows(string $filePath): array
    {
        $dataset = $this->extractTableDataset($filePath);

        return $dataset['rows'];
    }

    /**
     * @return array{
     *   rows:list<list<string>>,
     *   preview_thumbnails:list<array{row:int,col:int,data_url:string}>,
     *   image_cells:list<array{row:int,col:int,path:string}>
     * }
     */
    private function extractTableDataset(string $filePath, ?TemporaryModule $module = null, bool $persistImages = false): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $pages = $pdf->getPages();

        // 1. Collect text fragments with (x, y) from every page.
        $fragments = [];
        foreach ($pages as $pageIdx => $page) {
            $items = $page->getDataTm();
            foreach ($items as $item) {
                $x = (float) $item[0][4];
                $y = (float) $item[0][5];
                $text = trim((string) $item[1]);
                if ($text === '') {
                    continue;
                }
                $fragments[] = [$x, $y, $text, $pageIdx];
            }
        }

        if (empty($fragments)) {
            return [];
        }

        // 2. Detect column boundaries from the header row on page 0.
        //    The header band is the Y-band on page 0 that contains the most distinct X clusters.
        $page0Frags = array_values(array_filter($fragments, fn ($f) => $f[3] === 0));

        $colDefs = $this->detectColumnsFromHeader($page0Frags);
        if (count($colDefs) < 2) {
            return [];
        }

        $colCount = count($colDefs);
        $headerRow = array_map(static function ($colDef) {
            return trim((string) ($colDef[1] ?? ''));
        }, $colDefs);
        $headerBandBottom = $colDefs[0][2]; // stored by detectColumnsFromHeader

        // 3. Process each page to extract data rows and map images to row/column.
        $allRows = [];
        $imagePlacementsByCell = [];
        foreach ($pages as $pageIdx => $page) {
            $pageFrags = array_values(array_filter($fragments, fn ($f) => $f[3] === $pageIdx));
            if (empty($pageFrags)) {
                continue;
            }

            // On page 0 skip everything at or above the header band bottom.
            if ($pageIdx === 0) {
                $pageFrags = array_values(array_filter($pageFrags, fn ($f) => $f[1] < $headerBandBottom));
            }
            if (empty($pageFrags)) {
                continue;
            }

            // Assign column index to each fragment.
            $assigned = [];
            foreach ($pageFrags as $f) {
                $colIdx = $this->assignColumn($f[0], $colDefs);
                $assigned[] = [$colIdx, $f[1], $f[2]];
            }

            // Use column 0 Y positions as definitive row anchors.
            $col0Ys = [];
            foreach ($assigned as [$ci, $y, $t]) {
                if ($ci === 0) {
                    $col0Ys[] = round($y, 1);
                }
            }
            $col0Ys = array_unique($col0Ys);
            rsort($col0Ys); // top-first (PDF Y grows upward)

            if (empty($col0Ys)) {
                // Fallback: use gap-based row detection.
                $col0Ys = $this->detectRowAnchorsByGap($assigned);
            }
            if (empty($col0Ys)) {
                continue;
            }

            // Build row mid-boundaries for assignment.
            // Each anchor represents a row. Boundary between row i and row i+1
            // is the midpoint of their Y positions.
            $boundaries = []; // index => [yTop, yBottom]
            for ($i = 0; $i < count($col0Ys); $i++) {
                $top = ($i === 0) ? PHP_FLOAT_MAX : ($col0Ys[$i - 1] + $col0Ys[$i]) / 2;
                $bottom = ($i === count($col0Ys) - 1) ? -PHP_FLOAT_MAX : ($col0Ys[$i] + $col0Ys[$i + 1]) / 2;
                $boundaries[$i] = [$top, $bottom];
            }

            // Assign each fragment to a row.
            $rowBuckets = [];
            foreach ($assigned as [$colIdx, $y, $text]) {
                $rowIdx = 0;
                foreach ($boundaries as $ri => [$top, $bottom]) {
                    if ($y <= $top && $y >= $bottom) {
                        $rowIdx = $ri;
                        break;
                    }
                }
                $rowBuckets[$rowIdx][$colIdx][] = [$y, $text];
            }

            ksort($rowBuckets);
            $pageBaseRow = count($allRows);
            foreach ($rowBuckets as $bucket) {
                $row = array_fill(0, $colCount, '');
                foreach ($bucket as $colIdx => $pieces) {
                    usort($pieces, fn ($a, $b) => $b[0] <=> $a[0]);
                    $row[$colIdx] = implode(' ', array_map(fn ($p) => rtrim($p[1]), $pieces));
                }
                // Skip rows where all cells are empty
                if (implode('', $row) !== '') {
                    $allRows[] = $row;
                }
            }

            $pagePlacements = $this->extractPageImagePlacements($page);
            foreach ($pagePlacements as $placement) {
                $w = max(1.0, (float) ($placement['w'] ?? 1.0));
                $h = max(1.0, (float) ($placement['h'] ?? 1.0));
                $x = (float) ($placement['x'] ?? 0.0);
                $y = (float) ($placement['y'] ?? 0.0);

                // Filtrar artefactos típicos de PDF (máscaras/segmentos muy delgados).
                $aspect = $w / $h;
                $area = $w * $h;
                if ($area < 250.0 || $w < 10.0 || $h < 10.0 || $aspect < 0.20 || $aspect > 8.0) {
                    continue;
                }

                // Usar ancla izquierda para columna (el centro puede "brincar" de columna en la primera fila).
                $xAnchor = $x + min(8.0, $w * 0.10);
                $yCenter = (float) $placement['y'] + (((float) $placement['h']) / 2.0);

                $rowIdx = null;
                foreach ($boundaries as $ri => [$top, $bottom]) {
                    if ($yCenter <= $top && $yCenter >= $bottom) {
                        $rowIdx = (int) $ri;
                        break;
                    }
                }
                if ($rowIdx === null) {
                    continue;
                }

                $colIdx = $this->assignColumn((float) $xAnchor, $colDefs);
                $globalRow = $pageBaseRow + $rowIdx;
                $cellKey = $globalRow.'|'.$colIdx;
                if (! isset($imagePlacementsByCell[$cellKey])) {
                    $imagePlacementsByCell[$cellKey] = [
                        'row' => $globalRow,
                        'col' => $colIdx,
                        'binary' => $placement['binary'],
                        'area' => $area,
                    ];
                } elseif (($imagePlacementsByCell[$cellKey]['area'] ?? 0.0) < $area) {
                    // Si caen varias imágenes en la misma celda, quedarse con la más representativa (mayor área).
                    $imagePlacementsByCell[$cellKey] = [
                        'row' => $globalRow,
                        'col' => $colIdx,
                        'binary' => $placement['binary'],
                        'area' => $area,
                    ];
                }
            }
        }

        // Incluir explícitamente la fila de encabezado detectada para que
        // header_row/data_start_row (1/2) funcionen igual que en Excel.
        $headerOffset = 0;
        if (! empty(array_filter($headerRow, static fn ($v) => $v !== ''))) {
            array_unshift($allRows, array_pad(array_slice($headerRow, 0, $colCount), $colCount, ''));
            $headerOffset = 1;
        }

        $previewThumbnails = [];
        $imageCells = [];
        $thumbLimit = 40;
        foreach ($imagePlacementsByCell as $cell) {
            $row0 = (int) $cell['row'] + $headerOffset;
            $col0 = (int) $cell['col'];
            $binary = (string) ($cell['binary'] ?? '');
            if ($binary === '' || $row0 < 0 || $col0 < 0) {
                continue;
            }

            if (count($previewThumbnails) < $thumbLimit) {
                $dataUrl = $this->binaryToJpegThumbnailDataUri($binary, 72);
                if ($dataUrl !== null) {
                    $previewThumbnails[] = [
                        'row' => $row0 + 1,
                        'col' => $col0,
                        'data_url' => $dataUrl,
                    ];
                }
            }

            if ($persistImages && $module !== null) {
                $storedPath = $this->savePdfImageBinary($binary, $module);
                if ($storedPath !== null) {
                    $imageCells[] = [
                        'row' => $row0,
                        'col' => $col0,
                        'path' => $storedPath,
                    ];
                }
            }
        }

        return [
            'rows' => $allRows,
            'preview_thumbnails' => $previewThumbnails,
            'image_cells' => $imageCells,
        ];
    }

    /**
     * Detect column definitions by finding the Y-band with the most
     * distinct X-clusters (the header row).
     * Returns: [ [leftX, label, headerBandTop], ... ] sorted by leftX.
     */
    private function detectColumnsFromHeader(array $page0Frags): array
    {
        if (empty($page0Frags)) {
            return [];
        }

        // Group fragments into Y-bands (tolerance ±15pt).
        $yValues = array_map(fn ($f) => $f[1], $page0Frags);
        rsort($yValues);

        // Find distinct Y bands by clustering.
        $yBands = [];
        foreach ($yValues as $y) {
            $found = false;
            foreach ($yBands as &$band) {
                if (abs($y - $band['center']) < 15) {
                    $band['ys'][] = $y;
                    $band['center'] = array_sum($band['ys']) / count($band['ys']);
                    $found = true;
                    break;
                }
            }
            unset($band);
            if (! $found) {
                $yBands[] = ['center' => $y, 'ys' => [$y]];
            }
        }

        // For each Y-band, score likely header candidates.
        // Data rows often have many números/coordenadas; header rows are mostly texto.
        $bestBand = null;
        $bestScore = -INF;
        $pageTopY = max(array_map(fn ($f) => (float) $f[1], $page0Frags));

        $headerWords = [
            'NO', 'NO.', 'NUM', 'NUMERO', 'DIRECCION', 'UBICACION', 'GEOREFERENCIADA',
            'FOTO', 'ACTIVA', 'INACTIVA',
        ];

        foreach ($yBands as $band) {
            $bandTop = max($band['ys']) + 15;
            $bandBottom = min($band['ys']) - 15;
            $bandFrags = array_filter($page0Frags, fn ($f) => $f[1] >= $bandBottom && $f[1] <= $bandTop);
            $xClusters = $this->clusterByX($bandFrags, 25);

            $texts = array_map(fn ($f) => mb_strtoupper(trim((string) $f[2]), 'UTF-8'), $bandFrags);
            $texts = array_values(array_filter($texts, fn ($t) => $t !== ''));

            $alphaChars = 0;
            $digitChars = 0;
            $keywordHits = 0;
            foreach ($texts as $t) {
                preg_match_all('/[A-ZÁÉÍÓÚÜÑ]/u', $t, $mA);
                preg_match_all('/\d/u', $t, $mD);
                $alphaChars += count($mA[0] ?? []);
                $digitChars += count($mD[0] ?? []);
                foreach ($headerWords as $kw) {
                    if (str_contains($this->excelService->normalizeLabel($t), $kw)) {
                        $keywordHits++;
                        break;
                    }
                }
            }

            $density = count($xClusters);
            $textScore = $alphaChars * 0.08;
            $numberPenalty = $digitChars * 0.12;
            $keywordScore = $keywordHits * 2.5;
            $topBias = ($pageTopY > 0) ? (($band['center'] / $pageTopY) * 2.0) : 0;
            $score = ($density * 6.0) + $textScore + $keywordScore + $topBias - $numberPenalty;

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestBand = [
                    'top' => $bandTop,
                    'bottom' => $bandBottom,
                    'center' => $band['center'],
                    'frags' => array_values($bandFrags),
                ];
            }
        }

        if ($bestBand === null) {
            return [];
        }

        // Merge nearby text bands to support multi-line headers like:
        // "UBICACIÓN" + "GEOREFERENCIADA", "ACTIVA O" + "INACTIVA".
        $expandedFrags = $bestBand['frags'];
        foreach ($yBands as $band) {
            // Solo fusionar bandas cercanas al encabezado y hacia abajo (no títulos de página arriba).
            if ($band['center'] > $bestBand['center']) {
                continue;
            }
            if (abs($band['center'] - $bestBand['center']) > 32) {
                continue;
            }
            $bandTop = max($band['ys']) + 15;
            $bandBottom = min($band['ys']) - 15;
            $bandFrags = array_values(array_filter($page0Frags, fn ($f) => $f[1] >= $bandBottom && $f[1] <= $bandTop));
            if (empty($bandFrags)) {
                continue;
            }
            foreach ($bandFrags as $f) {
                $expandedFrags[] = $f;
            }
        }

        // Evitar duplicados exactos al fusionar bandas (misma pieza en varias bandas por tolerancia Y).
        $uniq = [];
        foreach ($expandedFrags as $f) {
            $k = sprintf('%.3f|%.3f|%s', (float) $f[0], (float) $f[1], trim((string) $f[2]));
            $uniq[$k] = $f;
        }
        $expandedFrags = array_values($uniq);

        $clusters = $this->clusterByX($expandedFrags, 25);
        if (count($clusters) < 2) {
            return [];
        }

        $colDefs = [];
        foreach ($clusters as $cluster) {
            $leftX = min(array_column($cluster, 0));
            usort($cluster, fn ($a, $b) => $b[1] <=> $a[1]);
            $rawParts = array_map(fn ($f) => trim((string) $f[2]), $cluster);
            $parts = [];
            $seenNorm = [];
            foreach ($rawParts as $p) {
                if ($p === '') {
                    continue;
                }
                $norm = $this->excelService->normalizeLabel($p);
                if ($norm === '' || isset($seenNorm[$norm])) {
                    continue;
                }
                $seenNorm[$norm] = true;
                $parts[] = $p;
            }
            $label = implode(' ', $parts);
            $colDefs[] = [$leftX, trim($label), $bestBand['bottom']];
        }
        usort($colDefs, fn ($a, $b) => $a[0] <=> $b[0]);

        return $colDefs;
    }

    /**
     * Cluster fragments by X proximity.
     */
    private function clusterByX(array $frags, float $tolerance): array
    {
        if (empty($frags)) {
            return [];
        }
        $frags = array_values($frags);
        usort($frags, fn ($a, $b) => $a[0] <=> $b[0]);

        $clusters = [[$frags[0]]];
        for ($i = 1, $n = count($frags); $i < $n; $i++) {
            $lastCluster = &$clusters[count($clusters) - 1];
            $clusterMaxX = max(array_column($lastCluster, 0));
            if ($frags[$i][0] - $clusterMaxX <= $tolerance) {
                $lastCluster[] = $frags[$i];
            } else {
                $clusters[] = [$frags[$i]];
            }
            unset($lastCluster);
        }

        return $clusters;
    }

    /**
     * Determine which column a fragment's X belongs to
     * using midpoint boundaries between adjacent columns.
     */
    private function assignColumn(float $x, array $colDefs): int
    {
        $n = count($colDefs);
        if ($n === 0) {
            return 0;
        }
        for ($i = 0; $i < $n - 1; $i++) {
            $mid = ($colDefs[$i][0] + $colDefs[$i + 1][0]) / 2;
            if ($x < $mid) {
                return $i;
            }
        }

        return $n - 1;
    }

    /**
     * Fallback row detection when column 0 is missing:
     * identify large Y-gaps between fragments.
     */
    private function detectRowAnchorsByGap(array $assigned): array
    {
        $allYs = array_unique(array_map(fn ($f) => round($f[1], 1), $assigned));
        rsort($allYs);
        $vals = array_values($allYs);

        if (count($vals) <= 1) {
            return $vals;
        }

        $gaps = [];
        for ($i = 0; $i < count($vals) - 1; $i++) {
            $gaps[] = $vals[$i] - $vals[$i + 1];
        }
        $medianGap = $this->median($gaps);
        $threshold = max($medianGap * 2.5, 20);

        $anchors = [$vals[0]];
        for ($i = 0; $i < count($vals) - 1; $i++) {
            if (($vals[$i] - $vals[$i + 1]) > $threshold) {
                $anchors[] = $vals[$i + 1];
            }
        }

        return $anchors;
    }

    private function median(array $nums): float
    {
        sort($nums);
        $c = count($nums);
        if ($c === 0) {
            return 0;
        }
        $mid = (int) ($c / 2);

        return $c % 2 === 0 ? ($nums[$mid - 1] + $nums[$mid]) / 2 : $nums[$mid];
    }

    private function colLetter(int $idx): string
    {
        $letter = '';
        $idx++;
        while ($idx > 0) {
            $idx--;
            $letter = chr(65 + $idx % 26) . $letter;
            $idx = (int) ($idx / 26);
        }

        return $letter;
    }

    /**
     * @return list<array{x:float,y:float,w:float,h:float,binary:string}>
     */
    private function extractPageImagePlacements(Page $page): array
    {
        $content = $this->flattenPageContentStream($page);
        if ($content === '') {
            return [];
        }

        $tokens = preg_split('/\s+/', str_replace(["\r", "\n", "\t"], ' ', $content)) ?: [];
        $stack = [];
        $ctm = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
        $ctmStack = [];
        $placements = [];

        foreach ($tokens as $tok) {
            if ($tok === '') {
                continue;
            }
            if ($this->isPdfNumericToken($tok) || str_starts_with($tok, '/')) {
                $stack[] = $tok;
                continue;
            }

            if ($tok === 'q') {
                $ctmStack[] = $ctm;
                continue;
            }
            if ($tok === 'Q') {
                $ctm = ! empty($ctmStack) ? array_pop($ctmStack) : [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
                continue;
            }
            if ($tok === 'cm') {
                if (count($stack) >= 6) {
                    $f = (float) array_pop($stack);
                    $e = (float) array_pop($stack);
                    $d = (float) array_pop($stack);
                    $c = (float) array_pop($stack);
                    $b = (float) array_pop($stack);
                    $a = (float) array_pop($stack);
                    $ctm = $this->multiplyAffine($ctm, [$a, $b, $c, $d, $e, $f]);
                }
                continue;
            }
            if ($tok === 'Do') {
                $idToken = null;
                while (! empty($stack)) {
                    $candidate = (string) array_pop($stack);
                    if (str_starts_with($candidate, '/')) {
                        $idToken = $candidate;
                        break;
                    }
                }
                if ($idToken === null) {
                    continue;
                }
                $xobject = $page->getXObject(trim($idToken, '/ '));
                if (! $xobject instanceof PdfImageXObject) {
                    continue;
                }
                $binary = (string) ($xobject->getContent() ?? '');
                if ($binary === '') {
                    continue;
                }
                $placements[] = [
                    'x' => (float) $ctm[4],
                    'y' => (float) $ctm[5],
                    'w' => max(1.0, abs((float) $ctm[0])),
                    'h' => max(1.0, abs((float) $ctm[3])),
                    'binary' => $binary,
                ];
            }
        }

        return $placements;
    }

    private function flattenPageContentStream(Page $page): string
    {
        $contents = $page->get('Contents');
        if (! $contents) {
            return '';
        }

        if ($contents instanceof PDFObject) {
            $elements = $contents->getHeader()->getElements();
            if (is_numeric(key($elements))) {
                $combined = '';
                foreach ($elements as $element) {
                    if ($element instanceof ElementXRef) {
                        $combined .= (string) $element->getObject()->getContent();
                    } else {
                        $combined .= (string) $element->getContent();
                    }
                    $combined .= "\n";
                }

                return $combined;
            }

            return (string) ($contents->getContent() ?? '');
        }

        if ($contents instanceof ElementArray) {
            $combined = '';
            foreach ($contents->getContent() as $content) {
                $combined .= (string) $content->getContent();
                $combined .= "\n";
            }

            return $combined;
        }

        return '';
    }

    private function isPdfNumericToken(string $token): bool
    {
        return preg_match('/^-?(?:\d+(?:\.\d+)?|\.\d+)$/', $token) === 1;
    }

    /**
     * Multiplica matrices afines PDF [a b c d e f].
     *
     * @param  array{0:float,1:float,2:float,3:float,4:float,5:float}  $m1
     * @param  array{0:float,1:float,2:float,3:float,4:float,5:float}  $m2
     * @return array{0:float,1:float,2:float,3:float,4:float,5:float}
     */
    private function multiplyAffine(array $m1, array $m2): array
    {
        return [
            ($m1[0] * $m2[0]) + ($m1[2] * $m2[1]),
            ($m1[1] * $m2[0]) + ($m1[3] * $m2[1]),
            ($m1[0] * $m2[2]) + ($m1[2] * $m2[3]),
            ($m1[1] * $m2[2]) + ($m1[3] * $m2[3]),
            ($m1[0] * $m2[4]) + ($m1[2] * $m2[5]) + $m1[4],
            ($m1[1] * $m2[4]) + ($m1[3] * $m2[5]) + $m1[5],
        ];
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
        imagejpeg($dst, null, 45);
        imagedestroy($dst);
        $jpg = ob_get_clean();
        if ($jpg === false || $jpg === '') {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode($jpg);
    }

    private function savePdfImageBinary(string $binary, TemporaryModule $module): ?string
    {
        if ($binary === '') {
            return null;
        }
        $contents = $this->compressImageBinary($binary);
        if ($contents === '') {
            return null;
        }

        $filename = 'imp_pdf_'.$module->id.'_'.bin2hex(random_bytes(8)).'.jpg';
        $secureDiskCfg = config('filesystems.disks.secure_shared');
        $storageDisk = ! empty($secureDiskCfg) ? 'secure_shared' : 'public';
        $storagePath = "temporary-modules/images/{$filename}";
        try {
            Storage::disk($storageDisk)->put($storagePath, $contents);
        } catch (\Throwable $e) {
            Log::error('Error guardando imagen extraída de PDF: '.$e->getMessage());

            return null;
        }

        return $storagePath;
    }

    private function compressImageBinary(string $binary): string
    {
        if (! extension_loaded('gd')) {
            return $binary;
        }
        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            return '';
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

        return ($compressed !== false && $compressed !== '') ? $compressed : '';
    }
}
