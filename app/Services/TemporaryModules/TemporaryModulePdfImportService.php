<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Log;

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
            $allRows = $this->extractTableRows($filePath);

            if (empty($allRows)) {
                return [
                    'success' => false,
                    'message' => 'No se detectaron tablas o datos legibles en el PDF.',
                ];
            }

            $headerRowIndex = max(0, $headerRow - 1);
            $rawHeaders = $allRows[$headerRowIndex] ?? $allRows[0];

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
            $allRows = $this->extractTableRows($filePath);

            // Normalise column count to match header
            $headerIdx = max(0, $headerRow - 1);
            $colCount = count($allRows[$headerIdx] ?? []);
            foreach ($allRows as &$r) {
                $r = array_pad(array_slice($r, 0, $colCount), $colCount, '');
            }
            unset($r);

            $dataRows = array_values(array_slice($allRows, $dataStartRow - 1));

            return $this->excelService->importFromDataArray($module, $dataRows, array_merge($options, [
                'row_offset' => $dataStartRow,
            ]));
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
        $headerBandBottom = $colDefs[0][2]; // stored by detectColumnsFromHeader

        // Header row
        $allRows = [];
        $allRows[] = array_map(fn ($c) => $c[1], $colDefs);

        // 3. Process each page to extract data rows.
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
        }

        return $allRows;
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

        // For each Y-band, count how many distinct X-clusters it has.
        $bestBand = null;
        $bestXcount = 0;
        foreach ($yBands as $band) {
            $bandTop = max($band['ys']) + 15;
            $bandBottom = min($band['ys']) - 15;
            $bandFrags = array_filter($page0Frags, fn ($f) => $f[1] >= $bandBottom && $f[1] <= $bandTop);
            $xClusters = $this->clusterByX($bandFrags, 25);
            if (count($xClusters) > $bestXcount) {
                $bestXcount = count($xClusters);
                $bestBand = ['top' => $bandTop, 'bottom' => $bandBottom, 'frags' => array_values($bandFrags)];
            }
        }

        if ($bestBand === null || $bestXcount < 2) {
            return [];
        }

        $clusters = $this->clusterByX($bestBand['frags'], 25);
        $colDefs = [];
        foreach ($clusters as $cluster) {
            $leftX = min(array_column($cluster, 0));
            usort($cluster, fn ($a, $b) => $b[1] <=> $a[1]);
            $label = implode(' ', array_map(fn ($f) => rtrim($f[2]), $cluster));
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
}
