<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TemporaryModuleExportService
{
    public function __construct(
        private readonly TemporaryModuleFieldService $fieldService,
        private readonly TemporaryModuleEntryDataService $entryDataService,
    ) {
    }

    public function exportExcel(int $moduleId, string $mode = 'single'): \Symfony\Component\HttpFoundation\Response
    {
        $temporaryModule = TemporaryModule::query()
            ->with([
                'fields:id,temporary_module_id,label,key,type',
                'entries' => function ($query) {
                    $query->latest('submitted_at');
                },
            ])
            ->findOrFail($moduleId);

        $baseName = Str::slug((string) $temporaryModule->name, '_');
        if ($baseName === '') {
            $baseName = 'modulo_temporal_'.$temporaryModule->id;
        }

        $fileName = $baseName.'_'.now()->format('Y-m-d').'.xlsx';
        $tempFilePath = storage_path('app/temp/'.$fileName);

        if (!is_dir(dirname($tempFilePath))) {
            mkdir(dirname($tempFilePath), 0755, true);
        }

        $spreadsheet = new Spreadsheet();
        
        // --- HOJA 1: ANÁLISIS GENERAL ---
        $analysisSheet = $spreadsheet->getActiveSheet();
        $analysisSheet->setTitle('Análisis General');
        $this->fillAnalysisSheet($analysisSheet, $temporaryModule);

        // --- HOJAS DE DATOS ---
        $microrregionMeta = DB::table('microrregiones')
            ->select(['id', 'cabecera', 'microrregion'])
            ->whereIn('id', $temporaryModule->entries->pluck('microrregion_id')->filter()->unique()->values())
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

        if ($mode === 'mr') {
            $groups = $temporaryModule->entries
                ->groupBy(function ($entry) {
                    return (int) ($entry->microrregion_id ?? 0);
                })
                ->sortKeys();

            if ($groups->isEmpty()) {
                $targetSheet = $spreadsheet->createSheet();
                $targetSheet->setTitle('Registros');
                $this->fillSheet($targetSheet, $temporaryModule, collect(), $microrregionMeta);
            } else {
                $usedTitles = [];

                foreach ($groups as $microrregionId => $entries) {
                    $targetSheet = $spreadsheet->createSheet();
                    $meta = $microrregionMeta[(int) $microrregionId] ?? null;
                    $baseTitle = $this->sheetTitleForMicrorregion(
                        (int) $microrregionId,
                        (string) ($meta['number'] ?? ''),
                        (string) ($meta['name'] ?? '')
                    );
                    $targetSheet->setTitle($this->uniqueSheetTitle($baseTitle, $usedTitles));

                    $this->fillSheet($targetSheet, $temporaryModule, $entries, $microrregionMeta);
                }
            }
        } else {
            $targetSheet = $spreadsheet->createSheet();
            $targetSheet->setTitle('Registros');
            $this->fillSheet($targetSheet, $temporaryModule, $temporaryModule->entries, $microrregionMeta);
        }

        // Asegurar que la primera hoja (Análisis) sea la activa al abrir
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFilePath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->download($tempFilePath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ])->deleteFileAfterSend(true);
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

        // 2. Extraer datos globales
        $totalRegistros = $temporaryModule->entries->count();
        $microrregionesCapturadas = $temporaryModule->entries->pluck('microrregion_id')->filter()->unique()->count();
        
        $municipiosCapturadosUnicos = 0;
        $capturasPorMunicipioYMicro = []; // [microrregion_id => [municipio_id => count]]

        if ($municipioFieldKey) {
            $municipiosCapturadosRaw = [];
            foreach ($temporaryModule->entries as $entry) {
                $val = $entry->data[$municipioFieldKey] ?? null;
                if ($val) {
                    $municipiosCapturadosRaw[] = mb_strtolower(trim((string)$val));
                }
            }
            $municipiosCapturadosGlobalVals = array_unique($municipiosCapturadosRaw);
        }

        // Para el total global, calcularemos en base a lo que mapeemos directamente del catálogo de la base de datos a continuación
        // 3. Imprimir encabezados generales
        $sheet->setCellValue('A1', 'Total de Registros:');
        $sheet->setCellValue('B1', $totalRegistros);
        
        $sheet->setCellValue('A3', 'Total de Microrregiones Capturadas:');
        $sheet->setCellValue('B3', $microrregionesCapturadas);

        // Estilos de los totales
        $sheet->getStyle('A1:A3')->getFont()->setBold(true);
        $sheet->getStyle('B1:B3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

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
            $sheet->getStyle($col . $startRow)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($col . $startRow)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF2E3B4E');
        }

        $allMicrorregiones = \App\Models\Microrregione::with('municipios')->orderBy('microrregion')->get();
        $gruposPorMicro = $temporaryModule->entries->groupBy('microrregion_id');

        $row = $startRow + 1;
        $globalMunicipiosCapturadosNombres = [];

        foreach ($allMicrorregiones as $mr) {
            $entradasMR = $gruposPorMicro->get($mr->id, collect());
            $cantidadRegistros = $entradasMR->count();
            
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
    }

    private function fillSheet(Worksheet $sheet, TemporaryModule $temporaryModule, Collection $entries, Collection $microrregionMeta): void
    {
        $headers = ['Ítem', 'Microrregión'];
        foreach ($temporaryModule->fields as $field) {
            $headers[] = (string) $field->label;
        }
        $headers[] = 'Fecha';

        foreach ($headers as $headerIndex => $headerText) {
            $column = Coordinate::stringFromColumnIndex($headerIndex + 1);
            $sheet->setCellValue($column.'1', $headerText);
            $sheet->getStyle($column.'1')->getFont()->setBold(true);
        }

        $sheet->freezePane('A2');
        $lastColumnLetter = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->setAutoFilter('A1:'.$lastColumnLetter.'1');

        $imageColumns = [];
        $rowIndex = 2;

        if ($entries->isEmpty()) {
            $sheet->setCellValue('A2', 'Sin registros');
        } else {
            $itemNumber = 1;
            foreach ($entries as $entry) {
                $sheet->setCellValue('A'.$rowIndex, $itemNumber);
                $sheet->setCellValue('B'.$rowIndex, (string) (($microrregionMeta[(int) ($entry->microrregion_id ?? 0)]['label'] ?? 'Sin microrregión')));

                $columnIndex = 3;

                foreach ($temporaryModule->fields as $field) {
                    $cell = $entry->data[$field->key] ?? null;
                    $cellCoordinate = Coordinate::stringFromColumnIndex($columnIndex).$rowIndex;
                    $fieldType = $this->fieldService->canonicalFieldType((string) $field->type);

                    if (is_bool($cell)) {
                        $sheet->setCellValue($cellCoordinate, $cell ? 'Sí' : 'No');
                        $columnIndex++;
                        continue;
                    }

                    if ($fieldType === 'image' && is_string($cell) && trim($cell) !== '') {
                        $imagePath = $this->entryDataService->resolveStoredFilePath($cell);
                        if ($imagePath !== null) {
                            try {
                                $drawing = new Drawing();
                                $drawing->setPath($imagePath);
                                $drawing->setCoordinates($cellCoordinate);
                                $drawing->setOffsetX(2);
                                $drawing->setOffsetY(2);
                                $drawing->setHeight(62);
                                $drawing->setCoordinates2($cellCoordinate);
                                $drawing->setOffsetX2(96);
                                $drawing->setOffsetY2(48);
                                $drawing->setEditAs(BaseDrawing::EDIT_AS_TWOCELL);
                                $drawing->setWorksheet($sheet);
                                $sheet->getRowDimension($rowIndex)->setRowHeight(50);
                                $imageColumns[$columnIndex] = true;
                            } catch (\Throwable $exception) {
                                $sheet->setCellValue($cellCoordinate, route('temporary-modules.entry-file.preview', ['entry' => $entry->id, 'fieldKey' => $field->key]));
                            }
                        } else {
                            $sheet->setCellValue($cellCoordinate, route('temporary-modules.entry-file.preview', ['entry' => $entry->id, 'fieldKey' => $field->key]));
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

                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex).$rowIndex, (string) (optional($entry->submitted_at)->format('d/m/Y H:i') ?? ''));

                $rowIndex++;
                $itemNumber++;
            }
        }

        $lastColumnIndex = count($headers);
        for ($columnIndex = 1; $columnIndex <= $lastColumnIndex; $columnIndex++) {
            $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
            if (isset($imageColumns[$columnIndex])) {
                $sheet->getColumnDimension($columnLetter)->setWidth(14);
                continue;
            }

            $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
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
