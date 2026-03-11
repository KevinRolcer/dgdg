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
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TemporaryModuleExportService
{
    public function __construct(
        private readonly TemporaryModuleFieldService $fieldService,
        private readonly TemporaryModuleEntryDataService $entryDataService,
    ) {
    }

    public function exportExcel(int $moduleId, string $mode = 'single'): array
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $temporaryModule = TemporaryModule::query()
            ->with(['fields:id,temporary_module_id,label,key,type'])
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

        $analysisSheet = $spreadsheet->getActiveSheet();
        $analysisSheet->setTitle('Análisis General');
        $this->fillAnalysisSheet($analysisSheet, $temporaryModule);

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
                $targetSheet = $spreadsheet->createSheet();
                $targetSheet->setTitle('Registros');
                $entriesQuery = $temporaryModule->entries()->whereNull('microrregion_id'); // Fallback if no groups
                $this->fillSheet($targetSheet, $temporaryModule, $entriesQuery, $microrregionMeta);
            } else {
                $usedTitles = [];

                foreach ($groups as $microrregionId) {
                    $targetSheet = $spreadsheet->createSheet();
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

                    $this->fillSheet($targetSheet, $temporaryModule, $entriesQuery, $microrregionMeta);
                }
            }
        } else {
            $targetSheet = $spreadsheet->createSheet();
            $targetSheet->setTitle('Registros');
            $entriesQuery = $temporaryModule->entries()->latest('submitted_at');
            $this->fillSheet($targetSheet, $temporaryModule, $entriesQuery, $microrregionMeta);
        }

        // Asegurar que la primera hoja (Análisis) sea la activa al abrir
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
    }

    private function fillSheet(Worksheet $sheet, TemporaryModule $temporaryModule, $entriesQuery, Collection $microrregionMeta): void
    {
        $headers = ['Ítem', 'Microrregión'];
        foreach ($temporaryModule->fields as $field) {
            $headers[] = (string) $field->label;
        }

        foreach ($headers as $headerIndex => $headerText) {
            $column = Coordinate::stringFromColumnIndex($headerIndex + 1);
            $sheet->setCellValue($column.'1', $headerText);

            // Establecer el auto-size a la celda del header también
            // Opcional: Centrado y Estilo
            $sheet->getStyle($column.'1')->getFont()->setBold(true);
        }

        $sheet->freezePane('A2');
        $lastColumnLetter = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->setAutoFilter('A1:'.$lastColumnLetter.'1');

        $rowIndex = 2;

        if ($entriesQuery->count() === 0) {
            $sheet->setCellValue('A2', 'Sin registros');
        } else {
            $itemNumber = 1;
            $entriesQuery->chunk(250, function ($entries) use (&$sheet, &$rowIndex, &$itemNumber, $microrregionMeta, $temporaryModule) {
                foreach ($entries as $entry) {
                    $sheet->setCellValue('A'.$rowIndex, $itemNumber);

                    $meta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));
                    $sheet->setCellValue('B'.$rowIndex, (string) ($meta->label ?? $meta['label'] ?? 'Sin microrregión'));

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

        // Ajustar envoltura de texto para todas las celdas de datos
        if ($rowIndex > 2) {
            $lastColumnLetter = Coordinate::stringFromColumnIndex(count($headers));
            $lastDataRow = $rowIndex - 1;
            $sheet->getStyle('A2:'.$lastColumnLetter.$lastDataRow)
                ->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_TOP);
        }

        // Anchos de columna pensados para no generar filas extremadamente largas
        $lastColumnIndex = count($headers);
        for ($columnIndex = 1; $columnIndex <= $lastColumnIndex; $columnIndex++) {
            $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);

            if ($columnIndex === 1) { // Ítem
                $sheet->getColumnDimension($columnLetter)->setWidth(6);
            } elseif ($columnIndex === 2) { // Microrregión
                $sheet->getColumnDimension($columnLetter)->setWidth(22);
            } else { // Campos de datos
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
