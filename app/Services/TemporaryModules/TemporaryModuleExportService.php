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

    public function exportExcel(int $moduleId, string $mode = 'single'): BinaryFileResponse
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
        $sheet = $spreadsheet->getActiveSheet();

        $microrregionNames = DB::table('microrregiones')
            ->select(['id', 'cabecera', 'microrregion'])
            ->whereIn('id', $temporaryModule->entries->pluck('microrregion_id')->filter()->unique()->values())
            ->get()
            ->mapWithKeys(function ($row) {
                $label = trim((string) ($row->cabecera ?: $row->microrregion ?: ''));

                return [(int) $row->id => $label !== '' ? $label : 'Sin microrregión'];
            });

        if ($mode === 'mr') {
            $groups = $temporaryModule->entries
                ->groupBy(function ($entry) {
                    return (int) ($entry->microrregion_id ?? 0);
                })
                ->sortKeys();

            if ($groups->isEmpty()) {
                $sheet->setTitle('Registros');
                $this->fillSheet($sheet, $temporaryModule, collect(), $microrregionNames);
            } else {
                $usedTitles = [];
                $sheetIndex = 0;

                foreach ($groups as $microrregionId => $entries) {
                    $targetSheet = $sheetIndex === 0 ? $sheet : $spreadsheet->createSheet();
                    $baseTitle = $this->sheetTitleForMicrorregion((int) $microrregionId, (string) ($microrregionNames[(int) $microrregionId] ?? 'Sin microrregión'));
                    $targetSheet->setTitle($this->uniqueSheetTitle($baseTitle, $usedTitles));

                    $this->fillSheet($targetSheet, $temporaryModule, $entries, $microrregionNames);
                    $sheetIndex++;
                }
            }
        } else {
            $sheet->setTitle('Registros');
            $this->fillSheet($sheet, $temporaryModule, $temporaryModule->entries, $microrregionNames);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFilePath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->download($tempFilePath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ])->deleteFileAfterSend(true);
    }

    private function fillSheet(Worksheet $sheet, TemporaryModule $temporaryModule, Collection $entries, Collection $microrregionNames): void
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
                $sheet->setCellValue('B'.$rowIndex, (string) ($microrregionNames[(int) ($entry->microrregion_id ?? 0)] ?? 'Sin microrregión'));

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

    private function sheetTitleForMicrorregion(int $microrregionId, string $microrregionName): string
    {
        $base = trim($microrregionName);
        if ($base === '' || mb_strtolower($base) === 'sin microrregión') {
            $base = 'Sin microrregión';
        }

        $sanitized = preg_replace('~[\\\\/?*\[\]:]~', ' ', $base) ?? 'Microrregión';
        $sanitized = trim(preg_replace('/\s+/', ' ', $sanitized) ?? 'Microrregión');

        if ($sanitized === '') {
            $sanitized = 'Microrregión';
        }

        return mb_substr($sanitized, 0, 31);
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
