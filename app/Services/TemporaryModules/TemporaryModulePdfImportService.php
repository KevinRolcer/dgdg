<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TemporaryModulePdfImportService
{
    private $excelService;

    public function __construct(TemporaryModuleExcelImportService $excelService)
    {
        $this->excelService = $excelService;
    }

    /**
     * Preview the PDF content to identify columns and structure.
     */
    public function preview(TemporaryModule $module, string $filePath, int $headerRow = 1): array
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();

            // Heuristic for table extraction: split into lines and filter empty ones
            $lines = explode("\n", $text);
            $structuredRows = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                // Attempt to split columns by multiple spaces or tabs
                $cols = preg_split('/\s{2,}|[\t]/', $line);
                if (count($cols) > 0) {
                    $structuredRows[] = array_map('trim', $cols);
                }
            }

            if (empty($structuredRows)) {
                return [
                    'success' => false,
                    'message' => 'No se detectaron tablas o datos legibles en el PDF.',
                ];
            }

            // Pick the header row (1-based index)
            $headerRowIndex = max(1, $headerRow) - 1;
            $rawHeaders = $structuredRows[$headerRowIndex] ?? ($structuredRows[0] ?? []);
            
            $headers = [];
            foreach ($rawHeaders as $idx => $label) {
                $headers[] = [
                    'index' => $idx,
                    'letter' => chr(65 + $idx), // Simple A, B, C...
                    'label' => $label ?: "Columna " . ($idx + 1),
                ];
            }

            $sheets = [
                [
                    'name' => 'PDF Extraction',
                    'rows' => array_slice($structuredRows, 0, 50),
                ]
            ];

            return [
                'success' => true,
                'headers' => $headers,
                'sheets' => $sheets,
                'is_pdf' => true,
            ];
        } catch (\Exception $e) {
            Log::error('PDF Preview Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al leer el PDF: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Import rows from PDF after mapping.
     * Reuses validation and coercion logic from ExcelService where possible.
     */
    public function import(TemporaryModule $module, array $options): array
    {
        $filePath = $options['file_path'] ?? null;
        $mapping = $options['mapping'] ?? [];
        $headerRow = (int) ($options['header_row'] ?? 1);
        $dataStartRow = (int) ($options['data_start_row'] ?? 2);
        $allMicrorregions = (bool) ($options['all_microrregions'] ?? false);
        $microrregionId = (int) ($options['selected_microrregion_id'] ?? 0);

        if (!$filePath || !file_exists($filePath)) {
            return ['success' => false, 'message' => 'Archivo no encontrado.'];
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
            $lines = explode("\n", $text);

            $structuredRows = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $cols = preg_split('/\s{2,}|[\t]/', $line);
                if (count($cols) > 0) {
                    $structuredRows[] = array_map('trim', $cols);
                }
            }

            // The data rows (adjusting 1-based to 0-based index)
            $dataRows = array_slice($structuredRows, $dataStartRow - 1);
            
            // Re-use logic for importing from rows
            return $this->excelService->importFromDataArray($module, $dataRows, [
                'mapping' => $mapping,
                'selected_microrregion_id' => $microrregionId,
                'all_microrregions' => $allMicrorregions,
                'row_offset' => $dataStartRow,
            ]);

        } catch (\Exception $e) {
            Log::error('PDF Import Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error processing PDF: ' . $e->getMessage()];
        }
    }
}
