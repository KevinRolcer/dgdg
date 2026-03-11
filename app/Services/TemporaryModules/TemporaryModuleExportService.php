<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TemporaryModuleExportService
{
    private const HEADER_FILL_COLOR = 'FF861E34'; // --clr-primary #861e34

    private const HEADER_FONT_COLOR = 'FFFFFFFF'; // blanco
    public function __construct(
        private readonly TemporaryModuleFieldService $fieldService,
        private readonly TemporaryModuleEntryDataService $entryDataService,
    ) {
    }

    public function exportExcel(int $moduleId, string $mode = 'single', bool $includeAnalysis = true): array
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $temporaryModule = TemporaryModule::query()
            ->with(['fields' => fn ($q) => $q->orderBy('sort_order')])
            ->findOrFail($moduleId);

        $entries = \Cache::remember("temporary_module_entries_{$moduleId}", 600, function () use ($temporaryModule) {
            return $temporaryModule->entries()->withoutGlobalScopes()->get();
        });

        $baseName = Str::slug((string) $temporaryModule->name, '_');
        if ($baseName === '') {
            $baseName = 'modulo_temporal_'.$temporaryModule->id;
        }

        $fileName = $baseName.'_'.now()->format('Ymd_His').'.xlsx';
        $exportDir = storage_path('app/public/temporary-exports');
        $tempFilePath = $exportDir.'/'.$fileName;

        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $spreadsheet = new Spreadsheet();

        // Si hay hoja de análisis, la primera hoja se usa para eso.
        // Si NO hay análisis, la primera hoja se reserva para datos y no creamos una vacía adicional.
        $usedDataSheet = $includeAnalysis;

        $fechaCorte = now();

        if ($includeAnalysis) {
            $analysisSheet = $spreadsheet->getActiveSheet();
            $analysisSheet->setTitle('Análisis General');
            $this->fillAnalysisSheet($analysisSheet, $temporaryModule);
            $this->applyPrintSetup($analysisSheet);
        } else {
            $spreadsheet->getActiveSheet()->setTitle('Registros');
        }

        $microrregionIds = $temporaryModule->entries()
            ->withoutGlobalScopes()
            ->reorder()
            ->select('microrregion_id')
            ->distinct()
            ->pluck('microrregion_id')
            ->filter()
            ->values()
            ->all();

        $microrregionMeta = DB::table('microrregiones')
            ->select(['id', 'cabecera', 'microrregion'])
            ->whereIn('id', $microrregionIds)
            ->get()
            ->mapWithKeys(function ($row) {
                $number = trim((string) ($row->microrregion ?? ''));
                $name = trim((string) ($row->cabecera ?? ''));

                return [(int) $row->id => [
                    'number' => $number,
                    'name' => $name,
                    'label' => $this->buildMicrorregionLabel($number, $name),
                ]];
            });

        $createDataSheet = function () use (&$usedDataSheet, $spreadsheet) {
            if (!$usedDataSheet) {
                $usedDataSheet = true;
                return $spreadsheet->getActiveSheet();
            }

            return $spreadsheet->createSheet();
        };

        if ($mode === 'mr') {
            // Obtenemos los grupos directamente de la base de datos para no cargar todo en memoria a la vez
            $groups = $temporaryModule->entries()
                ->withoutGlobalScopes()
                ->reorder() // evitamos ordenar por submitted_at en una consulta DISTINCT
                ->select('microrregion_id')
                ->distinct()
                ->pluck('microrregion_id')
                ->sort()
                ->values();

            if ($groups->isEmpty()) {
                $targetSheet = $createDataSheet();
                $targetSheet->setTitle('Registros');
                $entriesQuery = $temporaryModule->entries()->whereNull('microrregion_id'); // Fallback if no groups
                $this->fillSheet($targetSheet, $temporaryModule, $entriesQuery, $microrregionMeta, $fechaCorte);
                $this->applyPrintSetup($targetSheet);
            } else {
                $usedTitles = [];

                foreach ($groups as $microrregionId) {
                    $targetSheet = $createDataSheet();
                    $meta = $microrregionMeta->get((int) $microrregionId);
                    $baseTitle = $this->sheetTitleForMicrorregion(
                        (int) $microrregionId,
                        (string) ($meta->number ?? $meta['number'] ?? ''),
                        (string) ($meta->name ?? $meta['name'] ?? '')
                    );
                    $targetSheet->setTitle($this->uniqueSheetTitle($baseTitle, $usedTitles));

                    $entriesQuery = $temporaryModule->entries()
                        ->where('microrregion_id', $microrregionId)
                        ->latest('submitted_at');

                    $this->fillSheet($targetSheet, $temporaryModule, $entriesQuery, $microrregionMeta, $fechaCorte);
                    $this->applyPrintSetup($targetSheet);
                }
            }
        } else {
            $categoriaField = $temporaryModule->fields->firstWhere('type', 'categoria');

            if ($categoriaField && $mode === 'single') {
                $catOpts = is_array($categoriaField->options) ? $categoriaField->options : [];
                $topLevelCats = array_filter(array_map(fn ($c) => is_array($c) ? trim((string) ($c['name'] ?? '')) : '', $catOpts));
                $usedTitles = [];
                foreach ($topLevelCats as $catName) {
                    if ($catName === '') {
                        continue;
                    }
                    $targetSheet = $createDataSheet();
                    $sheetTitle = $this->uniqueSheetTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $catName) ?: $catName, 0, 31), $usedTitles);
                    $targetSheet->setTitle($sheetTitle);

                    $catKey = $categoriaField->key;
                    $entriesQuery = $temporaryModule->entries()
                        ->withoutGlobalScopes()
                        ->latest('submitted_at')
                        ->where(function ($q) use ($catKey, $catName) {
                            $q->where('data->'.$catKey, $catName)
                                ->orWhere('data->'.$catKey, 'like', $catName.' > %');
                        });
                    $this->fillSheet($targetSheet, $temporaryModule, $entriesQuery, $microrregionMeta, $fechaCorte);
                    $this->applyPrintSetup($targetSheet);
                }

                $totalsSheet = $createDataSheet();
                $totalsSheet->setTitle('Totales por categoría');
                $this->fillTotalesPorCategoriaSheet($totalsSheet, $temporaryModule, $categoriaField);
                $this->applyPrintSetup($totalsSheet);
            } else {
                $targetSheet = $createDataSheet();
                $targetSheet->setTitle('Registros');
                $entriesQuery = $temporaryModule->entries()->latest('submitted_at');
                $this->fillSheet($targetSheet, $temporaryModule, $entriesQuery, $microrregionMeta, $fechaCorte);
                $this->applyPrintSetup($targetSheet);
            }
        }

        // Asegurar que la primera hoja (sea análisis o registros) sea la activa al abrir
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFilePath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'name' => $fileName,
            'url' => route('temporary-modules.admin.exports.download', ['file' => $fileName]),
        ];
    }

    private function fillAnalysisSheet(Worksheet $sheet, TemporaryModule $temporaryModule): void
    {
        // 1. Identificar si existe algún campo tipo "municipio"
        $municipioFieldKey = null;
        foreach ($temporaryModule->fields as $field) {
            if ($this->fieldService->canonicalFieldType((string)$field->type) === 'municipio') {
                $municipioFieldKey = $field->key;
                break;
            }
        }

        // 2. Extraer datos globales (usando agregaciones para minimizar consultas)
        $totalRegistros = $temporaryModule->entries()->count();
        $microrregionesCapturadas = $temporaryModule->entries()
            ->withoutGlobalScopes()
            ->reorder()
            ->select('microrregion_id')
            ->distinct()
            ->count('microrregion_id');

        $municipiosCapturadosUnicos = 0;
        $capturasPorMunicipioYMicro = []; // [microrregion_id => [municipio_id => count]]

        if ($municipioFieldKey) {
            $municipiosCapturadosRaw = [];
            $temporaryModule->entries()->select('data')->chunk(500, function ($entries) use (&$municipiosCapturadosRaw, $municipioFieldKey) {
                foreach ($entries as $entry) {
                    $val = $entry->data[$municipioFieldKey] ?? null;
                    if ($val) {
                        $municipiosCapturadosRaw[] = mb_strtolower(trim((string)$val));
                    }
                }
            });
            $municipiosCapturadosGlobalVals = array_unique($municipiosCapturadosRaw);
        }

        // Para el total global, calcularemos en base a lo que mapeemos directamente del catálogo de la base de datos a continuación
        // 3. Imprimir encabezados generales
        $sheet->setCellValue('A1', 'Total de Registros:');
        $sheet->setCellValue('B1', $totalRegistros);

        $sheet->setCellValue('A3', 'Total de Microrregiones Capturadas:');
        $sheet->setCellValue('B3', $microrregionesCapturadas);

        // Títulos de totales: fondo primario y letra blanca
        $sheet->getStyle('A1:A3')->getFont()->setBold(true)->getColor()->setARGB(self::HEADER_FONT_COLOR);
        $sheet->getStyle('A1:A3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL_COLOR);
        $sheet->getStyle('A1:B3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // 4. Armar tabla por microrregiones
        $startRow = 6;
        $headers = [
            'A' => 'Microrregión',
            'B' => 'Total Registros',
            'C' => 'Total Municipios Capturados',
            'D' => 'Municipios Capturados (Lista)',
            'E' => 'Total Municipios Faltantes',
            'F' => 'Municipios Faltantes (Lista)'
        ];

        foreach ($headers as $col => $header) {
            $sheet->setCellValue($col . $startRow, $header);
            $sheet->getStyle($col . $startRow)->getFont()->setBold(true)->getColor()->setARGB(self::HEADER_FONT_COLOR);
            $sheet->getStyle($col . $startRow)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB(self::HEADER_FILL_COLOR);
            $sheet->getStyle($col . $startRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        }

        $allMicrorregiones = \App\Models\Microrregione::with('municipios')->orderBy('microrregion')->get();

        // Precalcular conteo de registros por microrregión en una sola consulta
        $registrosPorMicrorregion = $temporaryModule->entries()
            ->reorder() // evitar ORDER BY heredado (submitted_at) con ONLY_FULL_GROUP_BY
            ->select('microrregion_id', DB::raw('COUNT(*) as total'))
            ->groupBy('microrregion_id')
            ->pluck('total', 'microrregion_id');

        $row = $startRow + 1;
        $globalMunicipiosCapturadosNombres = [];

        foreach ($allMicrorregiones as $mr) {
            $cantidadRegistros = (int) ($registrosPorMicrorregion[$mr->id] ?? 0);

            $nombreMr = $this->buildMicrorregionLabel($mr->microrregion ?? '', $mr->cabecera ?? '');

            $municipiosCapturados = 0;
            $capturadosArray = [];
            $faltantesArray = [];
            $totalFaltantes = 0;

            if ($municipioFieldKey) {
                // Revisamos sobre TODOS los capturados a nivel global en este módulo (ya sean IDs o Nombres guardados)
                foreach ($mr->getRelation('municipios') as $muni) {
                    $muniIdStr = (string)$muni->id;
                    $muniNombreStr = mb_strtolower(trim($muni->municipio));

                    if (in_array($muniIdStr, $municipiosCapturadosGlobalVals, true) || in_array($muniNombreStr, $municipiosCapturadosGlobalVals, true)) {
                        $capturadosArray[] = $muni->municipio;
                        $globalMunicipiosCapturadosNombres[] = $muni->municipio;
                        $municipiosCapturados++;
                    } else {
                        $faltantesArray[] = $muni->municipio;
                        $totalFaltantes++;
                    }
                }
            } else {
                $municipiosCapturados = 'N/A';
                $capturadosArray = ['N/A'];
                $faltantesArray = ['El módulo no tiene campo de municipio'];
                $totalFaltantes = 'N/A';
            }

            $sheet->setCellValue('A' . $row, $nombreMr);
            $sheet->setCellValue('B' . $row, $cantidadRegistros);
            $sheet->setCellValue('C' . $row, $municipiosCapturados);
            $sheet->setCellValue('D' . $row, implode(', ', $capturadosArray));
            $sheet->setCellValue('E' . $row, $totalFaltantes);
            $sheet->setCellValue('F' . $row, implode(', ', $faltantesArray));

            // Ajustar saltos de línea para las listas
            $sheet->getStyle('D' . $row)->getAlignment()->setWrapText(true);
            $sheet->getStyle('F' . $row)->getAlignment()->setWrapText(true);

            $row++;
        }

        // Actualizamos el B2 con los nombres globales únicos de los municipios capturados (o la cuenta real validada)
        $sheet->setCellValue('A2', 'Total de Municipios Registrados:');
        if ($municipioFieldKey) {
            $globalValidados = count(array_unique($globalMunicipiosCapturadosNombres));
            $sheet->setCellValue('B2', $globalValidados . ' (De Catálogo)');
        } else {
            $sheet->setCellValue('B2', 'N/A (No tiene campo municipio)');
        }

        // Congelar paneles y ajustar columnas de la tabla de análisis
        $sheet->freezePane('A' . ($startRow + 1));

        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(25);
        $sheet->getColumnDimension('D')->setWidth(50);
        $sheet->getColumnDimension('E')->setWidth(25);
        $sheet->getColumnDimension('F')->setWidth(70);

        $lastRow = $row > $startRow ? $row - 1 : $startRow + 1;
        $analysisRange = 'A1:F' . $lastRow;
        $sheet->getStyle($analysisRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($analysisRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('D' . ($startRow + 1) . ':F' . $lastRow)->getAlignment()->setWrapText(true);
    }

    private function applyPrintSetup(Worksheet $sheet): void
    {
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4);
    }

    private function fillTotalesPorCategoriaSheet(Worksheet $sheet, TemporaryModule $temporaryModule, $categoriaField): void
    {
        $catKey = $categoriaField->key;
        $counts = $temporaryModule->entries()
            ->withoutGlobalScopes()
            ->get()
            ->groupBy(fn ($e) => (string) ($e->data[$catKey] ?? ''))
            ->map->count();

        $sheet->setCellValue('A1', 'Categoría');
        $sheet->setCellValue('B1', 'Total');
        $sheet->getStyle('A1:B1')
            ->getFont()->setBold(true)->getColor()->setARGB(self::HEADER_FONT_COLOR);
        $sheet->getStyle('A1:B1')
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL_COLOR);
        $sheet->getStyle('A1:B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $row = 2;
        foreach ($counts->sortKeys() as $categoriaVal => $total) {
            $sheet->setCellValue('A'.$row, $categoriaVal !== '' ? $categoriaVal : '(sin categoría)');
            $sheet->setCellValue('B'.$row, $total);
            $row++;
        }

        $lastRow = $row > 2 ? $row - 1 : 2;
        $sheet->getStyle('A1:B'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A1:B'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(12);
    }

    /** @return list<array{header1: ?string, header2: string, field: \App\Models\TemporaryModuleField}> */
    private function buildExportColumns(TemporaryModule $temporaryModule): array
    {
        $columns = [];
        $currentSection = null;

        foreach ($temporaryModule->fields as $field) {
            if ($field->type === 'seccion') {
                $opts = is_array($field->options) ? $field->options : [];
                $currentSection = [
                    'title' => (string) ($opts['title'] ?? $field->label),
                    'subsections' => array_values((array) ($opts['subsections'] ?? [])),
                ];
                continue;
            }

            if ($currentSection !== null && !empty($currentSection['subsections'])) {
                $idx = (int) ($field->subsection_index ?? 0);
                $subName = $currentSection['subsections'][$idx] ?? $field->label;
                $columns[] = ['header1' => $currentSection['title'], 'header2' => $subName, 'field' => $field];
            } else {
                $columns[] = ['header1' => $field->label, 'header2' => $field->label, 'field' => $field];
                $currentSection = null;
            }
        }

        return $columns;
    }

    private function fillSheet(Worksheet $sheet, TemporaryModule $temporaryModule, $entriesQuery, Collection $microrregionMeta, \DateTimeInterface $fechaCorte): void
    {
        $exportColumns = $this->buildExportColumns($temporaryModule);
        $hasSections = false;
        foreach ($exportColumns as $col) {
            if ($col['header1'] !== $col['header2']) {
                $hasSections = true;
                break;
            }
        }

        $fixedHeaders = ['Ítem', 'Microrregión', 'Fecha corte'];
        $numFixed = count($fixedHeaders);
        $fechaCorteStr = $fechaCorte->format('d/m/Y H:i');

        if ($hasSections) {
            $sheet->setCellValue('A1', $fixedHeaders[0]);
            $sheet->setCellValue('B1', $fixedHeaders[1]);
            $sheet->setCellValue('C1', $fixedHeaders[2]);
            $sheet->mergeCells('A1:A2');
            $sheet->mergeCells('B1:B2');
            $sheet->mergeCells('C1:C2');
            $colIdx = $numFixed + 1;
            $mergeStart = null;
            $mergeHeader1 = null;
            foreach ($exportColumns as $col) {
                $colLetter = Coordinate::stringFromColumnIndex($colIdx);
                $sheet->setCellValue($colLetter.'1', $col['header1'] ?? '');
                $sheet->setCellValue($colLetter.'2', $col['header2']);
                $h1 = $col['header1'] ?? '';
                if ($h1 !== '') {
                    if ($mergeStart === null || $mergeHeader1 !== $h1) {
                        if ($mergeStart !== null) {
                            $startLetter = Coordinate::stringFromColumnIndex($mergeStart);
                            $endLetter = Coordinate::stringFromColumnIndex($colIdx - 1);
                            if ($mergeStart < $colIdx - 1) {
                                $sheet->mergeCells($startLetter.'1:'.$endLetter.'1');
                            }
                        }
                        $mergeStart = $colIdx;
                        $mergeHeader1 = $h1;
                    }
                } else {
                    if ($mergeStart !== null) {
                        $startLetter = Coordinate::stringFromColumnIndex($mergeStart);
                        $endLetter = Coordinate::stringFromColumnIndex($colIdx - 1);
                        if ($mergeStart < $colIdx - 1) {
                            $sheet->mergeCells($startLetter.'1:'.$endLetter.'1');
                        }
                        $mergeStart = null;
                    }
                }
                $colIdx++;
            }
            if ($mergeStart !== null) {
                $startLetter = Coordinate::stringFromColumnIndex($mergeStart);
                $endLetter = Coordinate::stringFromColumnIndex($colIdx - 1);
                if ($mergeStart < $colIdx - 1) {
                    $sheet->mergeCells($startLetter.'1:'.$endLetter.'1');
                }
            }
            $headerRowCount = 2;
        } else {
            $headers = array_merge($fixedHeaders, array_map(fn ($c) => $c['header2'], $exportColumns));
            foreach ($headers as $i => $headerText) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $headerText);
            }
            $headerRowCount = 1;
        }

        $totalColumns = $numFixed + count($exportColumns);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($totalColumns);
        $headerRange = 'A1:'.$lastColumnLetter.($headerRowCount === 2 ? '2' : '1');
        $sheet->getStyle($headerRange)
            ->getFont()->setBold(true)->getColor()->setARGB(self::HEADER_FONT_COLOR);
        $sheet->getStyle($headerRange)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL_COLOR);
        $sheet->getStyle($headerRange)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->freezePane('A'.($headerRowCount + 1));
        $sheet->setAutoFilter('A1:'.$lastColumnLetter.$headerRowCount);

        $rowIndex = $headerRowCount + 1;

        if ($entriesQuery->count() === 0) {
            $sheet->setCellValue('A'.$rowIndex, 'Sin registros');
        } else {
            $itemNumber = 1;
            $entriesQuery->chunk(250, function ($entries) use (&$sheet, &$rowIndex, &$itemNumber, $microrregionMeta, $temporaryModule, $fechaCorteStr, $exportColumns, $headerRowCount) {
                foreach ($entries as $entry) {
                    $sheet->setCellValue('A'.$rowIndex, $itemNumber);
                    $meta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));
                    $sheet->setCellValue('B'.$rowIndex, (string) ($meta->label ?? $meta['label'] ?? 'Sin microrregión'));
                    $sheet->setCellValue('C'.$rowIndex, $fechaCorteStr);

                    $columnIndex = 4;
                    foreach ($exportColumns as $col) {
                        $field = $col['field'];
                        $cell = $entry->data[$field->key] ?? null;
                        $cellCoordinate = Coordinate::stringFromColumnIndex($columnIndex).$rowIndex;
                        $fieldType = $this->fieldService->canonicalFieldType((string) $field->type);

                        if (is_bool($cell)) {
                            $sheet->setCellValue($cellCoordinate, $cell ? 'Sí' : 'No');
                            $columnIndex++;
                            continue;
                        }

                        if ($fieldType === 'image') {
                            if (is_string($cell) && trim($cell) !== '') {
                                $rawValue = trim($cell);
                                if (filter_var($rawValue, FILTER_VALIDATE_URL)) {
                                    $sheet->setCellValue($cellCoordinate, $rawValue);
                                } else {
                                    $fullPath = $this->entryDataService->resolveStoredFilePath($rawValue);
                                    if (is_string($fullPath) && is_file($fullPath)) {
                                        $drawing = new Drawing();
                                        $drawing->setPath($fullPath);
                                        $drawing->setCoordinates($cellCoordinate);
                                        $drawing->setOffsetX(2);
                                        $drawing->setOffsetY(2);
                                        $drawing->setResizeProportional(true);
                                        $drawing->setHeight(80);
                                        $drawing->setWorksheet($sheet);
                                        $sheet->getRowDimension($rowIndex)->setRowHeight(80);
                                    } else {
                                        $sheet->setCellValue($cellCoordinate, 'Sin Imagen');
                                    }
                                }
                            } else {
                                $sheet->setCellValue($cellCoordinate, 'Sin Imagen');
                            }
                            $columnIndex++;
                            continue;
                        }

                        if ($fieldType === 'geopoint' && is_string($cell) && trim($cell) !== '') {
                            $geoValue = trim($cell);
                            $sheet->setCellValue($cellCoordinate, $geoValue);
                            if (filter_var($geoValue, FILTER_VALIDATE_URL)) {
                                $sheet->getCell($cellCoordinate)->getHyperlink()->setUrl($geoValue);
                            }
                            $columnIndex++;
                            continue;
                        }

                        $sheet->setCellValue($cellCoordinate, is_scalar($cell) ? (string) $cell : '');
                        $columnIndex++;
                    }

                    $rowIndex++;
                    $itemNumber++;
                }
            });
        }

        $lastDataRow = $rowIndex > ($headerRowCount + 1) ? $rowIndex - 1 : $headerRowCount + 1;
        $dataRange = 'A1:'.$lastColumnLetter.$lastDataRow;
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($dataRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        if ($lastDataRow > $headerRowCount) {
            $sheet->getStyle('A'.($headerRowCount + 1).':'.$lastColumnLetter.$lastDataRow)
                ->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        }

        for ($colIdx = 1; $colIdx <= $totalColumns; $colIdx++) {
            $columnLetter = Coordinate::stringFromColumnIndex($colIdx);
            if ($colIdx === 1) {
                $sheet->getColumnDimension($columnLetter)->setWidth(6);
            } elseif ($colIdx === 2) {
                $sheet->getColumnDimension($columnLetter)->setWidth(22);
            } elseif ($colIdx === 3) {
                $sheet->getColumnDimension($columnLetter)->setWidth(18);
            } else {
                $sheet->getColumnDimension($columnLetter)->setWidth(32);
            }
        }
    }

    private function sheetTitleForMicrorregion(int $microrregionId, string $microrregionNumber, string $microrregionName): string
    {
        $base = $this->buildMicrorregionLabel($microrregionNumber, $microrregionName);
        if (mb_strtolower($base) === 'sin microrregión') {
            $base = 'Sin microrregión';
        }

        $sanitized = preg_replace('~[\\\\/?*\[\]:]~', ' ', $base) ?? 'Microrregión';
        $sanitized = trim(preg_replace('/\s+/', ' ', $sanitized) ?? 'Microrregión');

        if ($sanitized === '') {
            $sanitized = 'Microrregión';
        }

        return mb_substr($sanitized, 0, 31);
    }

    private function buildMicrorregionLabel(string $microrregionNumber, string $microrregionName): string
    {
        $number = trim($microrregionNumber);
        $name = trim($microrregionName);

        if ($number === '' && $name === '') {
            return 'Sin microrregión';
        }

        if ($number === '') {
            return $name;
        }

        if ($name === '') {
            return 'MR '.$number;
        }

        return 'MR '.$number.' - '.$name;
    }

    private function uniqueSheetTitle(string $baseTitle, array &$usedTitles): string
    {
        $candidate = $baseTitle;
        $counter = 2;

        while (in_array($candidate, $usedTitles, true)) {
            $suffix = ' '.$counter;
            $candidate = mb_substr($baseTitle, 0, max(1, 31 - mb_strlen($suffix))).$suffix;
            $counter++;
        }

        $usedTitles[] = $candidate;

        return $candidate;
    }
}
