<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use App\Services\TemporaryModules\TemporaryModuleExcelImportService;
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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TemporaryModuleExportService
{
    private const HEADER_FILL_COLOR = 'FF861E34'; // --clr-primary #861e34

    private const HEADER_FONT_COLOR = 'FFFFFFFF'; // blanco

    /** @var array<string,mixed>|null */
    private ?array $exportConfig = null;

    /** @var array<string,array<string,mixed>> */
    private array $columnConfigByKey = [];

    /** @var array<string,string> lower group name => ARGB */
    private array $groupHeaderColorByName = [];
    public function __construct(
        private readonly TemporaryModuleFieldService $fieldService,
        private readonly TemporaryModuleEntryDataService $entryDataService,
    ) {
    }

    private function resolveMicrorregionSortDirection(): string
    {
        $direction = strtolower(trim((string) ($this->exportConfig['microrregion_sort'] ?? 'asc')));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';
    }

    private function buildStandardDocumentName(TemporaryModule $temporaryModule, string $extension): string
    {
        $rawModuleName = trim((string) $temporaryModule->name);
        if ($rawModuleName === '') {
            $rawModuleName = 'Modulo'.$temporaryModule->id;
        }

        $cleanModuleName = (string) Str::of(Str::ascii($rawModuleName))
            ->replaceMatches('/[^A-Za-z0-9]+/', '')
            ->trim();

        if ($cleanModuleName === '') {
            $cleanModuleName = 'Modulo'.$temporaryModule->id;
        }

        $safeExt = ltrim(strtolower($extension), '.');

        return 'DGDG_'.$cleanModuleName.'.'.now()->format('d.m.Y').'.'.$safeExt;
    }

    private function orderEntriesByMicrorregion($query)
    {
        $direction = strtoupper($this->resolveMicrorregionSortDirection());

        return $query
            ->reorder()
            ->leftJoin('microrregiones', 'microrregiones.id', '=', 'temporary_module_entries.microrregion_id')
            ->orderByRaw(
                'CASE WHEN temporary_module_entries.microrregion_id IS NULL THEN 1 ELSE 0 END, '.
                'CAST(COALESCE(microrregiones.microrregion, 0) AS UNSIGNED) '.$direction.', '.
                "LOWER(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(temporary_module_entries.data, '$._municipio_reporte')), ''))) ASC, ".
                'temporary_module_entries.submitted_at DESC'
            )
            ->select('temporary_module_entries.*');
    }

    public function exportExcel(int $moduleId, string $mode = 'single', bool $includeAnalysis = true, ?array $exportConfig = null): array
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $temporaryModule = TemporaryModule::query()
            ->with(['fields' => fn ($q) => $q->orderBy('sort_order')])
            ->findOrFail($moduleId);

        $entries = \Cache::remember("temporary_module_entries_{$moduleId}", 600, function () use ($temporaryModule) {
            return $temporaryModule->entries()->withoutGlobalScopes()->get();
        });

        $fileName = $this->buildStandardDocumentName($temporaryModule, 'xlsx');
        $exportDir = storage_path('app/public/temporary-exports');
        $tempFilePath = $exportDir.'/'.$fileName;

        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $spreadsheet = new Spreadsheet();

        // Normalizar configuración opcional desde el front
        $this->exportConfig = $exportConfig ?? [];
        $this->columnConfigByKey = [];
        $this->groupHeaderColorByName = $this->resolveGroupHeaderColorMap(is_array($this->exportConfig['groups'] ?? null) ? $this->exportConfig['groups'] : []);
        foreach (($this->exportConfig['columns'] ?? []) as $colCfg) {
            if (!is_array($colCfg)) {
                continue;
            }
            $key = (string) ($colCfg['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $this->columnConfigByKey[$key] = $colCfg;
        }

        $orientation = isset($this->exportConfig['orientation']) && $this->exportConfig['orientation'] === 'landscape'
            ? 'landscape'
            : 'portrait';

        $fechaCorte = now();

        // Sin hoja de análisis en Excel (informe Word aparte). Primera hoja = datos.
        $usedDataSheet = false;
        $spreadsheet->getActiveSheet()->setTitle('Registros');

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
                    'cabecera' => $name,
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
            $groupDirection = strtoupper($this->resolveMicrorregionSortDirection());
            $groups = $temporaryModule->entries()
                ->withoutGlobalScopes()
                ->reorder() // evitamos ordenar por submitted_at en una consulta DISTINCT
                ->select([
                    'temporary_module_entries.microrregion_id',
                    'microrregiones.microrregion as microrregion_sort',
                ])
                ->join('microrregiones', 'microrregiones.id', '=', 'temporary_module_entries.microrregion_id')
                ->groupBy('temporary_module_entries.microrregion_id', 'microrregiones.microrregion')
                ->orderByRaw('CAST(microrregiones.microrregion AS UNSIGNED) '.$groupDirection)
                ->pluck('temporary_module_entries.microrregion_id')
                ->values();

            if ($groups->isEmpty()) {
                $targetSheet = $createDataSheet();
                $targetSheet->setTitle('Registros');
                $entriesQuery = $temporaryModule->entries()->whereNull('microrregion_id'); // Fallback if no groups
                $this->fillSheet($targetSheet, $temporaryModule, $entriesQuery, $microrregionMeta, $fechaCorte);
                $this->applyPrintSetup($targetSheet, $orientation);
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

                    $entriesQuery = $this->orderEntriesByMicrorregion(
                        $temporaryModule->entries()
                            ->withoutGlobalScopes()
                            ->where('temporary_module_entries.microrregion_id', $microrregionId)
                    );

                    $this->fillSheet($targetSheet, $temporaryModule, $entriesQuery, $microrregionMeta, $fechaCorte);
                    $this->applyPrintSetup($targetSheet, $orientation);
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
                    $entriesQuery = $this->orderEntriesByMicrorregion(
                        $temporaryModule->entries()
                            ->withoutGlobalScopes()
                            ->where(function ($q) use ($catKey, $catName) {
                                $q->where('data->'.$catKey, $catName)
                                    ->orWhere('data->'.$catKey, 'like', $catName.' > %');
                            })
                    );
                    $this->fillSheet($targetSheet, $temporaryModule, $entriesQuery, $microrregionMeta, $fechaCorte);
                    $this->applyPrintSetup($targetSheet);
                }

                $totalsSheet = $createDataSheet();
                $totalsSheet->setTitle('Totales por categoría');
                $this->fillTotalesPorCategoriaSheet($totalsSheet, $temporaryModule, $categoriaField);
                $this->applyPrintSetup($totalsSheet, $orientation);
            } else {
                $targetSheet = $createDataSheet();
                $targetSheet->setTitle('Registros');
                $entriesQuery = $this->orderEntriesByMicrorregion(
                    $temporaryModule->entries()
                        ->withoutGlobalScopes()
                );

                $this->fillSheet($targetSheet, $temporaryModule, $entriesQuery, $microrregionMeta, $fechaCorte);
                $this->applyPrintSetup($targetSheet, $orientation);
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

    public function generateTemplate(TemporaryModule $module, ?array $options = null): array
    {
        $options = $options ?? [];
        $withData = (bool) ($options['with_data'] ?? false);
        $microrregionId = isset($options['microrregion_id']) ? (int) $options['microrregion_id'] : null;
        $suffix = trim((string) ($options['suffix'] ?? ''));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Plantilla');

        $fields = $this->templateFields($module);
        $this->fillTemplateHeader($sheet, $fields);

        if ($withData) {
            $entriesQuery = $module->entries()
                ->withoutGlobalScopes()
                ->reorder()
                ->orderBy('submitted_at');

            if (($microrregionId ?? 0) > 0) {
                $entriesQuery->where('microrregion_id', $microrregionId);
            }

            $entries = $entriesQuery->get(['data']);
            $this->fillTemplateDataRows($sheet, $fields, $entries, 3);
        }

        $eventToken = preg_replace('/[^A-Za-z0-9]+/', '_', Str::ascii((string) $module->name));
        $eventToken = trim((string) $eventToken, '_');
        if ($eventToken === '') {
            $eventToken = 'Modulo_'.$module->id;
        }

        if ($withData && ($microrregionId ?? 0) > 0) {
            $microrregionNo = (string) DB::table('microrregiones')
                ->where('id', (int) $microrregionId)
                ->value('microrregion');
            $microrregionNo = trim($microrregionNo);
            if ($microrregionNo === '') {
                $microrregionNo = str_pad((string) $microrregionId, 2, '0', STR_PAD_LEFT);
            } else {
                $microrregionNo = str_pad($microrregionNo, 2, '0', STR_PAD_LEFT);
            }

            $fileName = $microrregionNo.'.'.$eventToken.'_'.now()->format('Ymd_His').'.xlsx';
        } else {
            $fileName = $eventToken.'_Plantilla.xlsx';
        }
        $exportDir = storage_path('app/public/temporary-exports');
        $tempFilePath = $exportDir.'/'.$fileName;

        if (! is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFilePath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'name' => $fileName,
            'path' => $tempFilePath,
            'url' => route('temporary-modules.plantilla.download', ['file' => $fileName], false),
        ];
    }

    private function templateFields(TemporaryModule $module): Collection
    {
        $importableTypes = TemporaryModuleExcelImportService::IMPORTABLE_TYPES;

        if ($module->relationLoaded('fields') && $module->fields instanceof Collection) {
            return $module->fields
                ->whereIn('type', $importableTypes)
                ->sortBy('sort_order')
                ->values();
        }

        return $module->fields()
            ->whereIn('type', $importableTypes)
            ->orderBy('sort_order')
            ->get();
    }

    private function fillTemplateHeader(Worksheet $sheet, Collection $fields): void
    {
        $col = 1;
        foreach ($fields as $field) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue($letter.'1', $field->label);

            $style = $sheet->getStyle($letter.'1');
            $style->getFont()->setBold(true)->getColor()->setARGB(self::HEADER_FONT_COLOR);
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL_COLOR);

            $placeholder = '';
            $type = (string) $field->type;
            if ($type === 'select') {
                $opts = is_array($field->options) ? $field->options : [];
                $placeholder = '('.implode(', ', $opts).')';
            } elseif ($type === 'image' || $type === 'file' || $type === 'document') {
                $comment = trim((string) ($field->comment ?? ''));
                $placeholder = $comment !== '' ? $comment : '';
            } elseif ($type === 'geopoint') {
                $placeholder = '(Enlace de google maps)';
            } elseif ($type === 'boolean') {
                $placeholder = '(Sí / No)';
            } elseif ($type === 'semaforo') {
                $placeholder = '(Verde, Amarillo, Rojo)';
            } elseif ($type === 'date') {
                $placeholder = '(AAAA-MM-DD)';
            } elseif ($type === 'datetime') {
                $placeholder = '(AAAA-MM-DD HH:MM)';
            }

            if ($placeholder !== '') {
                $sheet->setCellValue($letter.'2', $placeholder);
                $sheet->getStyle($letter.'2')->getFont()->setItalic(true)->getColor()->setARGB('FF777777');
            }

            $sheet->getColumnDimension($letter)->setAutoSize(true);
            $col++;
        }
    }

    private function fillTemplateDataRows(Worksheet $sheet, Collection $fields, Collection $entries, int $startRow): void
    {
        $row = max(3, $startRow);

        foreach ($entries as $entry) {
            $data = is_array($entry->data ?? null) ? $entry->data : [];
            $col = 1;

            foreach ($fields as $field) {
                $letter = Coordinate::stringFromColumnIndex($col);
                $value = $data[$field->key] ?? '';

                if (is_array($value)) {
                    $value = implode(', ', array_filter(array_map('strval', $value), fn ($v) => trim($v) !== ''));
                } elseif (is_bool($value)) {
                    $value = $value ? 'Sí' : 'No';
                } elseif (! is_scalar($value) && $value !== null) {
                    $value = '';
                }

                $sheet->setCellValue($letter.$row, (string) ($value ?? ''));
                $col++;
            }

            $row++;
        }
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
                foreach ($mr->municipios as $muni) {
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

    private function applyPrintSetup(Worksheet $sheet, string $orientation = 'landscape'): void
    {
        $sheet->getPageSetup()
            ->setOrientation($orientation === 'landscape'
                ? PageSetup::ORIENTATION_LANDSCAPE
                : PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
    }

    /**
     * Reordenar/filtrar columnas según configuración enviada desde el front.
     *
     * @param array<int,array{header1:?string,header2:string,field:\App\Models\TemporaryModuleField}> $exportColumns
     * @return array<int,array{header1:?string,header2:string,field:\App\Models\TemporaryModuleField}>
     */
    private function reorderExportColumns(array $exportColumns): array
    {
        if (empty($this->columnConfigByKey) || empty($this->exportConfig['columns'] ?? null)) {
            return $exportColumns;
        }

        $byKey = [];
        foreach ($exportColumns as $col) {
            $byKey[(string) $col['field']->key] = $col;
        }

        $ordered = [];
        foreach ($this->exportConfig['columns'] as $cfgCol) {
            if (!is_array($cfgCol) || !isset($cfgCol['key'])) {
                continue;
            }
            $key = (string) $cfgCol['key'];
            if (isset($byKey[$key])) {
                $col = $byKey[$key];
                $label = (string) ($cfgCol['label'] ?? '');
                if ($label !== '' && $label !== $key) {
                    $col['header2'] = $label;
                }

                // Aplicar grupo personalizado si existe
                $group = (string) ($cfgCol['group'] ?? '');
                if ($group !== '') {
                    $col['header1'] = $group;
                }

                $ordered[] = $col;
            }
        }

        return $ordered ?: $exportColumns;
    }

    private function configuredColumnLabel(string $key, string $default): string
    {
        $cfg = $this->columnConfigByKey[$key] ?? null;
        if (is_array($cfg)) {
            $label = trim((string) ($cfg['label'] ?? ''));
            if ($label !== '' && $label !== $key) {
                return $label;
            }
        }

        return $default;
    }

    private function shouldUppercaseTitle(): bool
    {
        return ! empty($this->exportConfig['title_uppercase']);
    }

    private function shouldUppercaseHeaders(): bool
    {
        return ! empty($this->exportConfig['headers_uppercase']);
    }

    private function normalizeExportHeading(string $text, bool $uppercase = false): string
    {
        $text = trim($text);

        return $uppercase && $text !== '' ? mb_strtoupper($text, 'UTF-8') : $text;
    }

    private function transformCountTableLabels(array $countTable, bool $uppercase = false): array
    {
        if (! $uppercase || empty($countTable['groups']) || ! is_array($countTable['groups'])) {
            return $countTable;
        }

        foreach ($countTable['groups'] as &$group) {
            $group['label'] = $this->normalizeExportHeading((string) ($group['label'] ?? ''), true);
            if (! empty($group['values']) && is_array($group['values'])) {
                foreach ($group['values'] as &$value) {
                    $value['label'] = $this->normalizeExportHeading((string) ($value['label'] ?? ''), true);
                }
                unset($value);
            }
        }
        unset($group);

        return $countTable;
    }

    private function mapCssColorToArgb(string $color): ?string
    {
        $color = trim($color);
        if ($color === '') {
            return null;
        }

        $map = [
            'var(--clr-primary)' => 'FF861E34',
            'var(--clr-secondary)' => 'FF246257',
            'var(--clr-accent)' => 'FFC79B66',
            'var(--clr-text-main)' => 'FF484747',
            'var(--clr-text-light)' => 'FF6B6A6A',
            'var(--clr-bg)' => 'FFF7F7F8',
            'var(--clr-card)' => 'FFFFFFFF',
        ];

        if (isset($map[$color])) {
            return $map[$color];
        }

        if ($color[0] === '#') {
            $hex = strtoupper(ltrim($color, '#'));
            if (strlen($hex) === 6) {
                return 'FF'.$hex;
            }
        }

        return null;
    }

    /**
     * @param array<int,mixed> $groups
     * @return array<string,string>
     */
    private function resolveGroupHeaderColorMap(array $groups): array
    {
        $map = [];
        foreach ($groups as $group) {
            if (is_string($group)) {
                $name = trim($group);
                if ($name === '') {
                    continue;
                }
                $map[mb_strtolower($name, 'UTF-8')] = 'FF64748B';
                continue;
            }
            if (!is_array($group)) {
                continue;
            }
            $name = trim((string) ($group['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $argb = $this->mapCssColorToArgb((string) ($group['color'] ?? '#64748B'));
            $map[mb_strtolower($name, 'UTF-8')] = $argb ?? 'FF64748B';
        }

        return $map;
    }

    private function fillTotalesPorCategoriaSheet(Worksheet $sheet, TemporaryModule $temporaryModule, $categoriaField): void
    {
        $catKey = $categoriaField->key;
        $counts = $temporaryModule->entries()
            ->withoutGlobalScopes()
            ->get()
            ->groupBy(fn ($e) => (string) ($e->data[$catKey] ?? ''))
            ->map(fn($g) => $g->count());

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

            $makeColumn = function (string $header1, string $header2) use ($field): array {
                return ['header1' => $header1, 'header2' => $header2, 'field' => $field];
            };

            // Linked fields expand into TWO virtual columns (primary then secondary)
            if ($field->type === 'linked') {
                $opts = is_array($field->options) ? $field->options : [];
                $primaryLabel = trim((string) ($opts['primary_label'] ?? '')) ?: ($field->label.' (principal)');
                $secondaryLabel = trim((string) ($opts['secondary_label'] ?? '')) ?: ($field->label.' (dep.)');
                if ($currentSection !== null && !empty($currentSection['subsections'])) {
                    $columns[] = ['header1' => $currentSection['title'], 'header2' => $primaryLabel, 'field' => $field];
                    $columns[] = ['header1' => $currentSection['title'], 'header2' => $secondaryLabel, 'field' => $field];
                } else {
                    $columns[] = ['header1' => $primaryLabel, 'header2' => $primaryLabel, 'field' => $field];
                    $columns[] = ['header1' => $secondaryLabel, 'header2' => $secondaryLabel, 'field' => $field];
                    $currentSection = null;
                }
                continue;
            }

            if ($currentSection !== null && !empty($currentSection['subsections'])) {
                $idx = (int) ($field->subsection_index ?? 0);
                $subName = $currentSection['subsections'][$idx] ?? $field->label;
                $columns[] = $makeColumn($currentSection['title'], $subName);
            } else {
                $columns[] = $makeColumn($field->label, $field->label);
                $currentSection = null;
            }
        }

        return $columns;
    }

    /**
     * Build count table data as groups: each group has a title (field name) and sub-values with counts.
     *
     * @param \Illuminate\Support\Collection $entries collection of entries with 'data'
     * @param array<string> $countByFields field keys to count by value
     * @param array<string, string> $fieldLabels map field key => display label (e.g. estatus => ESTATUS)
     * @return array{groups: list<array{label: string, values: list<array{label: string, count: int}>}>}
     */
    private function buildCountTableData(Collection $entries, array $countByFields, array $fieldLabels = [], array $countTableColors = []): array
    {
        $total = $entries->count();
        $groups = [
            [
                'label' => 'Total de registros',
                'values' => [['label' => '', 'count' => $total]],
                'color_key' => '_total',
            ],
        ];

        foreach ($countByFields as $fieldKey) {
            $fieldCfg = is_array($countTableColors[$fieldKey] ?? null) ? $countTableColors[$fieldKey] : [];
            $includeValuesCfg = is_array($fieldCfg['includeValues'] ?? null) ? $fieldCfg['includeValues'] : [];
            $includeSR = !array_key_exists('showSR', $fieldCfg) || !empty($fieldCfg['showSR']);
            $valueCounts = [];
            $labelByLower = [];
            $sinRespuestaCount = 0;
            foreach ($entries as $entry) {
                $val = $entry->data[$fieldKey] ?? null;

                // Multiselect: count each option separately
                if (is_array($val) && !isset($val['primary'])) {
                    $hasAnyValue = false;
                    foreach ($val as $item) {
                        $key = is_scalar($item) ? trim((string) $item) : '';
                        if ($key !== '') {
                            $hasAnyValue = true;
                            $lower = mb_strtolower($key);
                            $valueCounts[$lower] = ($valueCounts[$lower] ?? 0) + 1;
                            if (!isset($labelByLower[$lower])) {
                                $labelByLower[$lower] = $key;
                            }
                        }
                    }
                    if (! $hasAnyValue) {
                        $sinRespuestaCount++;
                    }
                    continue;
                }

                // Linked: count by primary value
                if (is_array($val) && isset($val['primary'])) {
                    $val = $val['primary'];
                }

                if (is_bool($val)) {
                    $key = $val ? 'Sí' : 'No';
                } elseif (is_scalar($val)) {
                    $key = trim((string) $val);
                } else {
                    $key = '';
                }
                if ($key !== '') {
                    $lower = mb_strtolower($key);
                    $valueCounts[$lower] = ($valueCounts[$lower] ?? 0) + 1;
                    if (!isset($labelByLower[$lower])) {
                        $labelByLower[$lower] = $key;
                    }
                } else {
                    $sinRespuestaCount++;
                }
            }
            ksort($valueCounts, SORT_NATURAL);
            $values = [];
            foreach ($valueCounts as $lower => $count) {
                $valueLabel = (string) ($labelByLower[$lower] ?? $lower);
                $includeValue = true;
                if (array_key_exists($valueLabel, $includeValuesCfg)) {
                    $includeValue = (bool) $includeValuesCfg[$valueLabel];
                } elseif (array_key_exists($lower, $includeValuesCfg)) {
                    $includeValue = (bool) $includeValuesCfg[$lower];
                }
                if ($includeValue) {
                    $values[] = ['label' => $valueLabel, 'count' => $count];
                }
            }
            if ($includeSR && $sinRespuestaCount > 0) {
                $values[] = ['label' => 'S/R', 'count' => $sinRespuestaCount];
            }
            if ($values !== []) {
                $groups[] = [
                    'label' => $fieldLabels[$fieldKey] ?? $fieldKey,
                    'values' => $values,
                    'color_key' => $fieldKey,
                ];
            }
        }

        return ['groups' => $groups];
    }

    private function normalizeSummaryText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        $txt = trim((string) $value);

        return mb_strtolower($txt, 'UTF-8');
    }

    private function parseSummaryNumber(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        $raw = preg_replace('/\s+/', '', trim((string) $value)) ?: '';
        if ($raw === '') {
            return null;
        }

        $commaPos = strrpos($raw, ',');
        $dotPos = strrpos($raw, '.');
        if ($commaPos !== false && $dotPos !== false) {
            if ($commaPos > $dotPos) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($commaPos !== false && $dotPos === false) {
            $raw = str_replace(',', '.', $raw);
        }

        if (! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    private function resolveMunicipioGroupLabel(array $entryData, array $fieldLabels): string
    {
        $raw = trim((string) ($entryData['_municipio_reporte'] ?? ''));
        if ($raw !== '') {
            return $raw;
        }

        foreach ($fieldLabels as $key => $label) {
            $cmp = mb_strtolower((string) $key, 'UTF-8').' '.mb_strtolower((string) $label, 'UTF-8');
            if (str_contains($cmp, 'municipio')) {
                $v = trim((string) ($entryData[$key] ?? ''));
                if ($v !== '') {
                    return $v;
                }
            }
        }

        return 'Sin municipio';
    }

    /**
     * @param  array<int,array{id:string,label:string,group?:string,field_key:string,agg:string,match_value?:string}>  $sumMetrics
     * @param  array<int,array{id:string,label:string,group?:string,op:string,metric_ids:array<int,string>,base_metric_id?:string}>  $sumFormulas
     * @param  array<string,string>  $fieldLabels
    * @return array{group_by:string,group_label:string,metric_columns:array<int,array{id:string,label:string,group:string,include_total:bool}>,formula_columns:array<int,array{id:string,label:string,group:string,op:string,base_metric_id:string,include_total:bool}>,metric_labels:array<string,string>,formula_labels:array<string,string>,rows:array<int,array{group:string,mr_number:string,mr_cabecera:string,metrics:array<string,float>,formulas:array<string,float>}>}
     */
    private function buildSumTableData(
        Collection $entries,
        array $sumMetrics,
        array $sumFormulas,
        string $groupBy,
        array $fieldLabels,
        Collection $microrregionMeta,
    ): array {
        $groupBy = $groupBy === 'municipio' ? 'municipio' : 'microrregion';
        $metricLabels = [];
        $formulaLabels = [];
        $metricColumns = [];
        $formulaColumns = [];
        foreach ($sumMetrics as $metric) {
            $id = (string) ($metric['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $label = (string) ($metric['label'] ?? $id);
            $group = trim((string) ($metric['group'] ?? ''));
            $sortOrder = (int) ($metric['sort_order'] ?? 0);
            $includeTotal = !array_key_exists('include_total', $metric) || !empty($metric['include_total']);
            $metricLabels[$id] = $label;
            $metricColumns[] = ['id' => $id, 'label' => $label, 'group' => $group, 'include_total' => $includeTotal, 'sort_order' => $sortOrder];
        }
        foreach ($sumFormulas as $formula) {
            $id = (string) ($formula['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $label = (string) ($formula['label'] ?? $id);
            $group = trim((string) ($formula['group'] ?? ''));
            $op = (string) ($formula['op'] ?? 'add');
            $baseMetricId = (string) ($formula['base_metric_id'] ?? '');
            $sortOrder = (int) ($formula['sort_order'] ?? 0);
            $includeTotal = !array_key_exists('include_total', $formula) || !empty($formula['include_total']);
            $formulaLabels[$id] = $label;
            $metricIds = array_values(array_filter(array_map('strval', (array) ($formula['metric_ids'] ?? []))));
            $formulaColumns[] = ['id' => $id, 'label' => $label, 'group' => $group, 'op' => $op, 'base_metric_id' => $baseMetricId, 'metric_ids' => $metricIds, 'include_total' => $includeTotal, 'sort_order' => $sortOrder];
        }

        $rowsByKey = [];
        $orderedKeys = [];
        foreach ($entries as $entry) {
            $entryData = (array) ($entry->data ?? []);
            $meta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));
            $mrNumber = (string) ($meta['number'] ?? $meta->number ?? '');
            $mrCabecera = (string) ($meta['cabecera'] ?? $meta->cabecera ?? $meta['name'] ?? $meta->name ?? '');
            if ($groupBy === 'municipio') {
                $groupLabel = $this->resolveMunicipioGroupLabel($entryData, $fieldLabels);
            } else {
                $groupLabel = (string) (($meta['label'] ?? null) ?: 'Sin microrregión');
            }
            $groupKey = $groupBy.':'.$groupLabel;
            if (! isset($rowsByKey[$groupKey])) {
                $orderedKeys[] = $groupKey;
                $rowsByKey[$groupKey] = ['group' => $groupLabel, 'mr_number' => $mrNumber, 'mr_cabecera' => $mrCabecera, 'metrics' => [], 'formulas' => []];
                foreach ($sumMetrics as $metric) {
                    $rowsByKey[$groupKey]['metrics'][(string) $metric['id']] = 0.0;
                }
            }
            if (($rowsByKey[$groupKey]['mr_number'] ?? '') === '' && $mrNumber !== '') {
                $rowsByKey[$groupKey]['mr_number'] = $mrNumber;
            }
            if (($rowsByKey[$groupKey]['mr_cabecera'] ?? '') === '' && $mrCabecera !== '') {
                $rowsByKey[$groupKey]['mr_cabecera'] = $mrCabecera;
            }

            foreach ($sumMetrics as $metric) {
                $metricId = (string) ($metric['id'] ?? '');
                $fieldKey = (string) ($metric['field_key'] ?? '');
                $agg = (string) ($metric['agg'] ?? 'sum');
                if ($metricId === '' || $fieldKey === '') {
                    continue;
                }
                $raw = $entryData[$fieldKey] ?? null;
                if ($agg === 'sum') {
                    $acc = 0.0;
                    if (is_array($raw)) {
                        foreach ($raw as $part) {
                            $n = $this->parseSummaryNumber($part);
                            if ($n !== null) {
                                $acc += $n;
                            }
                        }
                    } else {
                        $n = $this->parseSummaryNumber($raw);
                        if ($n !== null) {
                            $acc += $n;
                        }
                    }
                    $rowsByKey[$groupKey]['metrics'][$metricId] += $acc;
                } elseif ($agg === 'count_non_empty') {
                    $hasValue = false;
                    if (is_array($raw) && ! isset($raw['primary'])) {
                        $hasValue = collect($raw)->contains(fn ($item) => trim((string) $item) !== '');
                    } elseif (is_array($raw) && isset($raw['primary'])) {
                        $hasValue = trim((string) ($raw['primary'] ?? '')) !== '';
                    } else {
                        $hasValue = trim((string) ($raw ?? '')) !== '';
                    }
                    if ($hasValue) {
                        $rowsByKey[$groupKey]['metrics'][$metricId] += 1;
                    }
                } elseif ($agg === 'count_empty') {
                    $isEmpty = false;
                    if (is_array($raw) && ! isset($raw['primary'])) {
                        $isEmpty = !collect($raw)->contains(fn ($item) => trim((string) $item) !== '');
                    } elseif (is_array($raw) && isset($raw['primary'])) {
                        $isEmpty = trim((string) ($raw['primary'] ?? '')) === '';
                    } else {
                        $isEmpty = trim((string) ($raw ?? '')) === '';
                    }
                    if ($isEmpty) {
                        $rowsByKey[$groupKey]['metrics'][$metricId] += 1;
                    }
                } elseif ($agg === 'count_unique' || $agg === 'count_equals') {
                    $target = $this->normalizeSummaryText((string) ($metric['match_value'] ?? ''));
                    if ($agg === 'count_equals' && $target !== '') {
                        $matched = false;
                        if (is_array($raw) && ! isset($raw['primary'])) {
                            foreach ($raw as $part) {
                                if ($this->normalizeSummaryText($part) === $target) {
                                    $matched = true;
                                    break;
                                }
                            }
                        } else {
                            $matched = $this->normalizeSummaryText($raw) === $target;
                        }
                        if ($matched) {
                            $rowsByKey[$groupKey]['metrics'][$metricId] += 1;
                        }
                    } else {
                        if (! isset($rowsByKey[$groupKey]['_unique_sets'])) {
                            $rowsByKey[$groupKey]['_unique_sets'] = [];
                        }
                        if (! isset($rowsByKey[$groupKey]['_unique_sets'][$metricId])) {
                            $rowsByKey[$groupKey]['_unique_sets'][$metricId] = [];
                        }
                        $pushUnique = function (mixed $value) use (&$rowsByKey, $groupKey, $metricId): void {
                            $key = $this->normalizeSummaryText($value);
                            if ($key !== '') {
                                $rowsByKey[$groupKey]['_unique_sets'][$metricId][$key] = true;
                            }
                        };
                        if (is_array($raw) && ! isset($raw['primary'])) {
                            foreach ($raw as $part) {
                                $pushUnique($part);
                            }
                        } elseif (is_array($raw) && isset($raw['primary'])) {
                            $pushUnique($raw['primary'] ?? null);
                        } else {
                            $pushUnique($raw);
                        }
                    }
                }
            }
        }

        if ($groupBy === 'municipio') {
            usort($orderedKeys, function ($a, $b) use ($rowsByKey) {
                return strcasecmp((string) ($rowsByKey[$a]['group'] ?? ''), (string) ($rowsByKey[$b]['group'] ?? ''));
            });
        }

        foreach ($orderedKeys as $groupKey) {
            foreach ($sumMetrics as $metric) {
                $metricId = (string) ($metric['id'] ?? '');
                $agg = (string) ($metric['agg'] ?? 'sum');
                $target = $this->normalizeSummaryText((string) ($metric['match_value'] ?? ''));
                if ($metricId === '') {
                    continue;
                }
                if ($agg === 'count_unique' || ($agg === 'count_equals' && $target === '')) {
                    $set = $rowsByKey[$groupKey]['_unique_sets'][$metricId] ?? [];
                    $rowsByKey[$groupKey]['metrics'][$metricId] = (float) count($set);
                }
            }
            unset($rowsByKey[$groupKey]['_unique_sets']);
        }

        foreach ($orderedKeys as $groupKey) {
            foreach ($sumFormulas as $formula) {
                $formulaId = (string) ($formula['id'] ?? '');
                $op = (string) ($formula['op'] ?? 'add');
                $metricIds = array_values(array_filter(array_map('strval', (array) ($formula['metric_ids'] ?? []))));
                $baseMetricId = (string) ($formula['base_metric_id'] ?? '');
                if ($formulaId === '' || $metricIds === []) {
                    continue;
                }
                $vals = array_map(function ($metricId) use ($rowsByKey, $groupKey) {
                    return (float) ($rowsByKey[$groupKey]['metrics'][$metricId] ?? 0.0);
                }, $metricIds);

                $result = 0.0;
                if ($op === 'subtract') {
                    $result = array_shift($vals) ?? 0.0;
                    foreach ($vals as $v) {
                        $result -= $v;
                    }
                } elseif ($op === 'multiply') {
                    $result = 1.0;
                    foreach ($vals as $v) {
                        $result *= $v;
                    }
                } elseif ($op === 'divide') {
                    $result = array_shift($vals) ?? 0.0;
                    foreach ($vals as $v) {
                        if ($v == 0.0) {
                            $result = 0.0;
                            break;
                        }
                        $result /= $v;
                    }
                } elseif ($op === 'percent') {
                    $numerator = (float) (($vals[0] ?? 0.0));
                    $base = (float) ($rowsByKey[$groupKey]['metrics'][$baseMetricId] ?? 0.0);
                    $result = ($base !== 0.0) ? (($numerator / $base) * 100.0) : 0.0;
                } else {
                    foreach ($vals as $v) {
                        $result += $v;
                    }
                }
                $rowsByKey[$groupKey]['formulas'][$formulaId] = $result;
            }
        }

        $rows = [];
        foreach ($orderedKeys as $key) {
            $rows[] = $rowsByKey[$key];
        }

        return [
            'group_by' => $groupBy,
            'group_label' => $groupBy === 'municipio' ? 'Municipio' : 'Microrregión',
            'metric_columns' => $metricColumns,
            'formula_columns' => $formulaColumns,
            'metric_labels' => $metricLabels,
            'formula_labels' => $formulaLabels,
            'rows' => $rows,
        ];
    }

    private function fillSheet(Worksheet $sheet, TemporaryModule $temporaryModule, $entriesQuery, Collection $microrregionMeta, \DateTimeInterface $fechaCorte): void
    {
        $exportColumns = $this->buildExportColumns($temporaryModule);
        $exportColumns = $this->reorderExportColumns($exportColumns);
        $hasSections = false;
        foreach ($exportColumns as $col) {
            if ($col['header1'] !== $col['header2']) {
                $hasSections = true;
                break;
            }
        }

        $headersUppercase = $this->shouldUppercaseHeaders();
        $titleUppercase = $this->shouldUppercaseTitle();
        $fixedHeaders = [
            $this->normalizeExportHeading($this->configuredColumnLabel('item', '#'), $headersUppercase),
            $this->normalizeExportHeading($this->configuredColumnLabel('delegacion_numero', 'Delegación'), $headersUppercase),
            $this->normalizeExportHeading($this->configuredColumnLabel('cabecera_microrregion', 'Cabecera'), $headersUppercase),
        ];
        $numFixed = count($fixedHeaders);
        $fechaCorteStr = $fechaCorte->format('d/m/Y H:i');
        $dataColumnsCount = $numFixed + count($exportColumns);

        $titleRow = 2;
        $dateRow = 3;
        $logoFilePath = public_path('images/LogoSegobHorizontal.png');
        if (is_file($logoFilePath)) {
            $logoDrawing = new Drawing();
            $logoDrawing->setName('Logo');
            $logoDrawing->setPath($logoFilePath);
            $logoDrawing->setHeight(38);
            $logoDrawing->setCoordinates('A1');
            $logoDrawing->setOffsetX(2);
            $logoDrawing->setOffsetY(2);
            $logoDrawing->setWorksheet($sheet);
            $sheet->getRowDimension(1)->setRowHeight(30);
        }
        $includeCountTable = !empty($this->exportConfig['include_count_table']);
        $includeSumTable = !empty($this->exportConfig['include_sum_table']);
        $countByFields = $includeCountTable && is_array($this->exportConfig['count_by_fields'] ?? null)
            ? array_values(array_filter(array_map('strval', $this->exportConfig['count_by_fields'])))
            : [];
        $countTableColors = is_array($this->exportConfig['count_table_colors'] ?? null) ? $this->exportConfig['count_table_colors'] : [];
        $countTableFontSizePx = max(7, min(24, (int) ($this->exportConfig['count_table_font_px'] ?? $this->exportConfig['count_table_font_size_px'] ?? $this->exportConfig['countTableFontPx'] ?? 9)));
        $sumGroupBy = (string) ($this->exportConfig['sum_group_by'] ?? 'microrregion');
        $sumMetrics = $includeSumTable && is_array($this->exportConfig['sum_metrics'] ?? null)
            ? array_values($this->exportConfig['sum_metrics'])
            : [];
        $sumFormulas = $includeSumTable && is_array($this->exportConfig['sum_formulas'] ?? null)
            ? array_values($this->exportConfig['sum_formulas'])
            : [];
        $sumIncludeTotalsRow = $includeSumTable && !empty($this->exportConfig['include_sum_totals_row']);
        $sumTableAlign = strtolower((string) ($this->exportConfig['sum_table_align'] ?? 'left'));
        $sumShowItem = !array_key_exists('sum_show_item', $this->exportConfig) || !empty($this->exportConfig['sum_show_item']);
        $sumItemLabel = trim((string) ($this->exportConfig['sum_item_label'] ?? '#'));
        if ($sumItemLabel === '') {
            $sumItemLabel = '#';
        }
        $sumShowDelegacion = !array_key_exists('sum_show_delegacion', $this->exportConfig) || !empty($this->exportConfig['sum_show_delegacion']);
        $sumDelegacionLabel = trim((string) ($this->exportConfig['sum_delegacion_label'] ?? 'Delegación'));
        if ($sumDelegacionLabel === '') {
            $sumDelegacionLabel = 'Delegación';
        }
        $sumShowCabecera = !array_key_exists('sum_show_cabecera', $this->exportConfig) || !empty($this->exportConfig['sum_show_cabecera']);
        $sumCabeceraLabel = trim((string) ($this->exportConfig['sum_cabecera_label'] ?? 'Cabecera'));
        if ($sumCabeceraLabel === '') {
            $sumCabeceraLabel = 'Cabecera';
        }
        $sumTotalsBold = !array_key_exists('sum_totals_bold', $this->exportConfig ?? []) || !empty($this->exportConfig['sum_totals_bold']);
        $sumTotalsTextColorArgb = $this->mapCssColorToArgb((string) ($this->exportConfig['sum_totals_text_color'] ?? '')) ?: self::HEADER_FILL_COLOR;
        $fieldLabels = [];
        foreach ($exportColumns as $column) {
            $field = $column['field'] ?? null;
            if ($field && isset($field->key)) {
                $fieldLabels[(string) $field->key] = (string) ($column['header2'] ?? $field->label ?? $field->key);
            }
        }
        $countTableRows = 0;
        $maxColIdx = $dataColumnsCount;
        $entriesForSummary = null;
        if ($includeCountTable || $includeSumTable) {
            $entriesForSummary = (clone $entriesQuery)->get(['id', 'data', 'microrregion_id']);
        }

        if ($includeCountTable) {
            $entriesForCount = $entriesForSummary instanceof Collection
                ? $entriesForSummary
                : (clone $entriesQuery)->get(['id', 'data', 'microrregion_id']);
            $countData = $this->buildCountTableData($entriesForCount, $countByFields, $fieldLabels, $countTableColors);
            $countData = $this->transformCountTableLabels($countData, $headersUppercase);
            $groups = $countData['groups'];
            $colIdx = 1;
            $groupRow = 4;
            $valueRow = 5;
            $dataRow = 6;

            // Primero: Escribir contenido y calcular ancho
            foreach ($groups as $gi => $group) {
                $key = (string) ($group['color_key'] ?? ($gi === 0 ? '_total' : ($countByFields[$gi - 1] ?? '')));
                $groupCfg = $countTableColors[$key] ?? [];
                $includePct = !empty($groupCfg['showPct']);

                $numValues = count($group['values']);
                $span = $includePct ? $numValues * 2 : $numValues;
                $startCol = $colIdx;
                $isRedundant = ($numValues === 1 && (trim((string)($group['values'][0]['label'] ?? '')) === '' || trim((string)($group['values'][0]['label'] ?? '')) === trim((string)($group['label'] ?? '')))) || $key === '_total';

                $sheet->setCellValue(Coordinate::stringFromColumnIndex($startCol).$groupRow, $group['label']);

                if ($span > 1) {
                    $endCol = $startCol + $span - 1;
                    $sheet->mergeCells(Coordinate::stringFromColumnIndex($startCol).$groupRow.':'.Coordinate::stringFromColumnIndex($endCol).$groupRow);
                } elseif ($isRedundant && !$includePct) {
                    $sheet->mergeCells(Coordinate::stringFromColumnIndex($startCol).$groupRow.':'.Coordinate::stringFromColumnIndex($startCol).$valueRow);
                }

                $gTotal = array_sum(array_column($group['values'], 'count'));
                foreach ($group['values'] as $v) {
                    $label = $v['label'] !== '' ? $v['label'] : $group['label'];
                    if ($includePct) {
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx).$valueRow, $isRedundant ? 'Cantidad' : $label);
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 1).$valueRow, '%');
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx).$dataRow, $v['count']);
                        $pct = $gTotal > 0 ? ($v['count'] / $gTotal) : 0;
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 1).$dataRow, $pct);
                        $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx + 1).$dataRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
                        $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx+1).$valueRow)->getFont()->setSize(8);
                        $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx+1).$dataRow)->getFont()->setSize(8);
                        $colIdx += 2;
                    } else {
                        if (!$isRedundant) {
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx).$valueRow, $label);
                        }
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx).$dataRow, $v['count']);
                        $colIdx++;
                    }
                }
            }
            $maxColIdx = max($maxColIdx, $colIdx - 1);
            $countLastCol = $colIdx - 1;
            $countLastColLetter = Coordinate::stringFromColumnIndex($countLastCol);

            // Estilos de la tabla de conteo
            $sheet->getStyle('A'.$groupRow.':'.$countLastColLetter.$dataRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('A'.$groupRow.':'.$countLastColLetter.$groupRow)->getFont()->setBold(true)->setSize($countTableFontSizePx)->getColor()->setARGB(self::HEADER_FONT_COLOR);
            $sheet->getStyle('A'.$valueRow.':'.$countLastColLetter.$valueRow)->getFont()->setBold(true)->setSize($countTableFontSizePx);
            $sheet->getStyle('A'.$valueRow.':'.$countLastColLetter.$valueRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
            $sheet->getStyle('A'.$dataRow.':'.$countLastColLetter.$dataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A'.$dataRow.':'.$countLastColLetter.$dataRow)->getFont()->setSize($countTableFontSizePx)->getColor()->setARGB('FFFF0000');

            // Colores personalizados del conteo
            $resolveCountColor = function (string $key, int $rowNum, ?string $valueLabel = null) use ($countTableColors): string {
                $c = $countTableColors[$key] ?? null;
                if (!is_array($c)) return $rowNum === 1 ? self::HEADER_FILL_COLOR : 'FF2d5a27';
                if ($rowNum === 1) return $this->mapCssColorToArgb((string)($c['row1'] ?? '')) ?: self::HEADER_FILL_COLOR;
                if ($valueLabel !== null && isset($c['row2Values'][$valueLabel])) return $this->mapCssColorToArgb((string)$c['row2Values'][$valueLabel]) ?: 'FF2d5a27';
                return $this->mapCssColorToArgb((string)($c['row2'] ?? '')) ?: 'FF2d5a27';
            };
            $colIdx = 1;
            foreach ($groups as $gi => $group) {
                $key = (string) ($group['color_key'] ?? ($gi === 0 ? '_total' : ($countByFields[$gi - 1] ?? '')));
                $includePct = !empty($countTableColors[$key]['showPct']);
                $argb1 = $resolveCountColor($key, 1);
                $span = $includePct ? count($group['values']) * 2 : count($group['values']);
                $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx).$groupRow.':'.Coordinate::stringFromColumnIndex($colIdx+$span-1).$groupRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb1);
                foreach ($group['values'] as $v) {
                    $vLabel = $v['label'] !== '' ? $v['label'] : $group['label'];
                    $argb2 = $resolveCountColor($key, 2, $vLabel);
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx).$valueRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb2);
                    if ($includePct) {
                        $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx+1).$valueRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb2);
                        $colIdx += 2;
                    } else { $colIdx++; }
                }
            }
            $countTableRows = 4;
        }

        if ($includeSumTable && $sumMetrics !== [] && $entriesForSummary instanceof Collection) {
            $sumData = $this->buildSumTableData(
                $entriesForSummary,
                $sumMetrics,
                $sumFormulas,
                $sumGroupBy,
                $fieldLabels,
                $microrregionMeta,
            );
            $sumRows = is_array($sumData['rows'] ?? null) ? $sumData['rows'] : [];
            if ($sumRows !== []) {
                $sumGroupColorArgb = $this->mapCssColorToArgb((string) ($this->exportConfig['sum_group_color'] ?? '')) ?: 'FF475569';
                $sumStartRow = 4 + $countTableRows;
                $sumStartCol = 1;
                $sumFirstLetter = Coordinate::stringFromColumnIndex($sumStartCol);
                $sumTitleRow = $sumStartRow;
                $sumHeaderRow = $sumStartRow + 1;
                $sumDataStartRow = $sumStartRow + 2;

                $sumMetricColumns = is_array($sumData['metric_columns'] ?? null) ? $sumData['metric_columns'] : [];
                $sumFormulaColumns = is_array($sumData['formula_columns'] ?? null) ? $sumData['formula_columns'] : [];
                $sumMetricLabels = [];
                foreach ($sumMetricColumns as $col) {
                    $sumMetricLabels[(string) ($col['id'] ?? '')] = (string) ($col['label'] ?? '');
                }
                if ($sumMetricLabels === []) {
                    $sumMetricLabels = is_array($sumData['metric_labels'] ?? null) ? $sumData['metric_labels'] : [];
                }
                $sumFormulaLabels = [];
                foreach ($sumFormulaColumns as $col) {
                    $sumFormulaLabels[(string) ($col['id'] ?? '')] = (string) ($col['label'] ?? '');
                }
                if ($sumFormulaLabels === []) {
                    $sumFormulaLabels = is_array($sumData['formula_labels'] ?? null) ? $sumData['formula_labels'] : [];
                }
                $sumGroupByCurrent = (string) ($sumData['group_by'] ?? $sumGroupBy);
                $sumGroupLabel = (string) ($sumData['group_label'] ?? 'Grupo');
                $sumLeadCols = [];
                if ($sumShowItem) {
                    $sumLeadCols[] = ['key' => 'item', 'label' => $this->normalizeExportHeading($sumItemLabel, $headersUppercase)];
                }
                if ($sumGroupByCurrent === 'microrregion') {
                    if ($sumShowDelegacion) {
                        $sumLeadCols[] = ['key' => 'delegacion_numero', 'label' => $this->normalizeExportHeading($sumDelegacionLabel, $headersUppercase)];
                    }
                    if ($sumShowCabecera) {
                        $sumLeadCols[] = ['key' => 'cabecera_microrregion', 'label' => $this->normalizeExportHeading($sumCabeceraLabel, $headersUppercase)];
                    }
                } else {
                    $sumLeadCols[] = ['key' => 'group', 'label' => $sumGroupLabel];
                    if ($sumShowDelegacion) {
                        $sumLeadCols[] = ['key' => 'delegacion_numero', 'label' => $this->normalizeExportHeading($sumDelegacionLabel, $headersUppercase)];
                    }
                    if ($sumShowCabecera) {
                        $sumLeadCols[] = ['key' => 'cabecera_microrregion', 'label' => $this->normalizeExportHeading($sumCabeceraLabel, $headersUppercase)];
                    }
                }
                if ($sumLeadCols === []) {
                    $sumLeadCols[] = ['key' => 'group', 'label' => $sumGroupLabel];
                }
                $sumLeadCount = count($sumLeadCols);
                $sumCols = $sumLeadCount + count($sumMetricLabels) + count($sumFormulaLabels);
                $sumLastCol = max($sumStartCol, $sumStartCol + $sumCols - 1);
                $sumLastLetter = Coordinate::stringFromColumnIndex($sumLastCol);
                $sumCombinedCols = [];
                foreach ($sumMetricColumns as $col) {
                    $sumCombinedCols[] = [
                        'id' => (string) ($col['id'] ?? ''),
                        'label' => (string) ($col['label'] ?? ''),
                        'group' => trim((string) ($col['group'] ?? '')),
                        'op' => 'metric',
                        'include_total' => !array_key_exists('include_total', $col) || !empty($col['include_total']),
                        'sort_order' => (int) ($col['sort_order'] ?? 0),
                    ];
                }
                if ($sumCombinedCols === [] && $sumMetricLabels !== []) {
                    foreach ($sumMetricLabels as $id => $label) {
                        $sumCombinedCols[] = ['id' => (string) $id, 'label' => (string) $label, 'group' => '', 'op' => 'metric', 'include_total' => true, 'sort_order' => 0];
                    }
                }
                foreach ($sumFormulaColumns as $col) {
                    $sumCombinedCols[] = [
                        'id' => (string) ($col['id'] ?? ''),
                        'label' => (string) ($col['label'] ?? ''),
                        'group' => trim((string) ($col['group'] ?? '')),
                        'op' => (string) ($col['op'] ?? 'add'),
                        'base_metric_id' => (string) ($col['base_metric_id'] ?? ''),
                        'metric_ids' => array_values(array_map('strval', (array) ($col['metric_ids'] ?? []))),
                        'include_total' => !array_key_exists('include_total', $col) || !empty($col['include_total']),
                        'sort_order' => (int) ($col['sort_order'] ?? 0),
                    ];
                }
                if (empty($sumFormulaColumns) && $sumFormulaLabels !== []) {
                    foreach ($sumFormulaLabels as $id => $label) {
                        $sumCombinedCols[] = ['id' => (string) $id, 'label' => (string) $label, 'group' => '', 'op' => 'add', 'include_total' => true, 'sort_order' => 0];
                    }
                }
                if ($sumCombinedCols !== []) {
                    usort($sumCombinedCols, static function (array $a, array $b): int {
                        $sa = (int) ($a['sort_order'] ?? 0);
                        $sb = (int) ($b['sort_order'] ?? 0);
                        if ($sa !== $sb) {
                            if ($sa === 0) return 1;
                            if ($sb === 0) return -1;
                            return $sa <=> $sb;
                        }
                        return 0;
                    });
                }
                $hasSumGroupHeaders = collect($sumCombinedCols)->contains(fn ($col) => ((string) ($col['group'] ?? '')) !== '');

                $sumTitleBase = trim((string) ($this->exportConfig['sum_title'] ?? 'Sumatoria'));
                if ($sumTitleBase === '') {
                    $sumTitleBase = 'Sumatoria';
                }
                $sumTitleCase = strtolower((string) ($this->exportConfig['sum_title_case'] ?? 'normal'));
                if (!in_array($sumTitleCase, ['normal', 'upper', 'lower'], true)) {
                    $sumTitleCase = 'normal';
                }
                $sumTitleText = $this->normalizeExportHeading($sumTitleBase, $headersUppercase);
                if ($sumTitleCase === 'upper') {
                    $sumTitleText = mb_strtoupper($sumTitleText);
                } elseif ($sumTitleCase === 'lower') {
                    $sumTitleText = mb_strtolower($sumTitleText);
                    $first = mb_substr($sumTitleText, 0, 1, 'UTF-8');
                    $rest = mb_substr($sumTitleText, 1, null, 'UTF-8');
                    $sumTitleText = mb_strtoupper($first, 'UTF-8').$rest;
                }

                $sheet->setCellValue($sumFirstLetter.$sumTitleRow, $sumTitleText.' '.$this->normalizeExportHeading('por '.$sumGroupLabel, $headersUppercase));
                $sheet->mergeCells($sumFirstLetter.$sumTitleRow.':'.$sumLastLetter.$sumTitleRow);
                $sheet->getStyle($sumFirstLetter.$sumTitleRow)->getFont()->setBold(true);

                if ($hasSumGroupHeaders) {
                    $sumHeaderRow = $sumStartRow + 2;
                    $sumDataStartRow = $sumStartRow + 3;
                    $sumGroupHeaderRow = $sumStartRow + 1;
                    for ($leadIdx = 0; $leadIdx < $sumLeadCount; $leadIdx++) {
                        $leadCol = $sumStartCol + $leadIdx;
                        $leadLetter = Coordinate::stringFromColumnIndex($leadCol);
                        $sheet->setCellValue($leadLetter.$sumGroupHeaderRow, '');
                    }
                    $colIdx = $sumStartCol + $sumLeadCount;
                    // Mantener vacías las celdas superiores de columnas sin grupo.
                    foreach ($sumCombinedCols as $idx => $col) {
                        $upperLetter = Coordinate::stringFromColumnIndex($sumStartCol + $sumLeadCount + $idx);
                        $sheet->setCellValue($upperLetter.$sumGroupHeaderRow, '');
                    }
                    $startCol = $sumStartCol + $sumLeadCount;
                    $lastGroup = null;
                    foreach ($sumCombinedCols as $col) {
                        $groupLabel = (string) ($col['group'] ?? '');
                        if ($lastGroup === null) {
                            $lastGroup = $groupLabel;
                        }
                        if ($groupLabel !== $lastGroup) {
                            $endCol = $colIdx - 1;
                            if (trim($lastGroup) !== '') {
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex($startCol).$sumGroupHeaderRow, $this->normalizeExportHeading($lastGroup, $headersUppercase));
                                if ($endCol > $startCol) {
                                    $sheet->mergeCells(Coordinate::stringFromColumnIndex($startCol).$sumGroupHeaderRow.':'.Coordinate::stringFromColumnIndex($endCol).$sumGroupHeaderRow);
                                }
                                $groupArgb = $this->groupHeaderColorByName[mb_strtolower(trim($lastGroup), 'UTF-8')] ?? 'FF64748B';
                                $sheet->getStyle(Coordinate::stringFromColumnIndex($startCol).$sumGroupHeaderRow.':'.Coordinate::stringFromColumnIndex($endCol).$sumGroupHeaderRow)
                                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($groupArgb);
                            }
                            $startCol = $colIdx;
                            $lastGroup = $groupLabel;
                        }
                        $colIdx++;
                    }
                    $endCol = $colIdx - 1;
                    if (trim((string) $lastGroup) !== '') {
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($startCol).$sumGroupHeaderRow, $this->normalizeExportHeading((string) $lastGroup, $headersUppercase));
                        if ($endCol > $startCol) {
                            $sheet->mergeCells(Coordinate::stringFromColumnIndex($startCol).$sumGroupHeaderRow.':'.Coordinate::stringFromColumnIndex($endCol).$sumGroupHeaderRow);
                        }
                        $groupArgb = $this->groupHeaderColorByName[mb_strtolower(trim((string) $lastGroup), 'UTF-8')] ?? 'FF64748B';
                        $sheet->getStyle(Coordinate::stringFromColumnIndex($startCol).$sumGroupHeaderRow.':'.Coordinate::stringFromColumnIndex($endCol).$sumGroupHeaderRow)
                            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($groupArgb);
                    }
                    $sumLeadLastCol = $sumStartCol + $sumLeadCount - 1;
                    $sheet->getStyle($sumFirstLetter.$sumGroupHeaderRow.':'.Coordinate::stringFromColumnIndex($sumLeadLastCol).$sumGroupHeaderRow)
                        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($sumGroupColorArgb);
                    $sheet->getStyle($sumFirstLetter.$sumGroupHeaderRow.':'.$sumLastLetter.$sumGroupHeaderRow)
                        ->getFont()->setBold(true)->getColor()->setARGB(self::HEADER_FONT_COLOR);
                    $sheet->getStyle($sumFirstLetter.$sumGroupHeaderRow.':'.$sumLastLetter.$sumGroupHeaderRow)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                }

                $colIdx = $sumStartCol;
                foreach ($sumLeadCols as $leadCol) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx).$sumHeaderRow, (string) ($leadCol['label'] ?? ''));
                    $colIdx++;
                }
                foreach ($sumCombinedCols as $col) {
                    $sheet->setCellValue(
                        Coordinate::stringFromColumnIndex($colIdx).$sumHeaderRow,
                        $this->normalizeExportHeading((string) ($col['label'] ?? ''), $headersUppercase)
                    );
                    $colIdx++;
                }

                $rowPtr = $sumDataStartRow;
                foreach ($sumRows as $rowIndex => $row) {
                    $colIdx = $sumStartCol;
                    foreach ($sumLeadCols as $leadCol) {
                        $leadKey = (string) ($leadCol['key'] ?? 'group');
                        $leadText = '';
                        if ($leadKey === 'item') {
                            $leadText = (string) ($rowIndex + 1);
                        } elseif ($leadKey === 'delegacion_numero') {
                            $leadText = (string) ($row['mr_number'] ?? '');
                        } elseif ($leadKey === 'cabecera_microrregion') {
                            $leadText = (string) ($row['mr_cabecera'] ?? '');
                        } else {
                            $leadText = (string) ($row['group'] ?? '');
                        }
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx).$rowPtr, $leadText);
                        $colIdx++;
                    }
                    foreach ($sumCombinedCols as $col) {
                        $id = (string) ($col['id'] ?? '');
                        if ((string) ($col['op'] ?? 'metric') === 'metric') {
                            $v = (float) (($row['metrics'][$id] ?? 0.0));
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx).$rowPtr, round($v, 2));
                        } else {
                            $v = (float) (($row['formulas'][$id] ?? 0.0));
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx).$rowPtr, round($v, 2));
                            if ((string) ($col['op'] ?? '') === 'percent') {
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx).$rowPtr, $v / 100);
                                $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx).$rowPtr)
                                    ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
                            }
                        }
                        $colIdx++;
                    }
                    $rowPtr++;
                }

                if ($sumIncludeTotalsRow) {
                    $colIdx = $sumStartCol;
                    foreach ($sumLeadCols as $leadIdx => $leadCol) {
                        $sheet->setCellValue(
                            Coordinate::stringFromColumnIndex($colIdx).$rowPtr,
                            $leadIdx === 0 ? $this->normalizeExportHeading('Total', $headersUppercase) : ''
                        );
                        $colIdx++;
                    }
                    foreach ($sumCombinedCols as $col) {
                        $includeTotal = !array_key_exists('include_total', $col) || !empty($col['include_total']);
                        if (!$includeTotal) {
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx).$rowPtr, '');
                            $colIdx++;
                            continue;
                        }

                        $id = (string) ($col['id'] ?? '');
                        $op = (string) ($col['op'] ?? 'metric');
                        $total = 0.0;

                        if ($op === 'percent') {
                            $metricIds = array_values(array_map('strval', (array) ($col['metric_ids'] ?? [])));
                            $numeratorMetricId = (string) ($metricIds[0] ?? '');
                            $baseMetricId = (string) ($col['base_metric_id'] ?? '');
                            $numeratorTotal = 0.0;
                            $baseTotal = 0.0;
                            if ($numeratorMetricId !== '' && $baseMetricId !== '') {
                                foreach ($sumRows as $row) {
                                    $numeratorTotal += (float) (($row['metrics'][$numeratorMetricId] ?? 0.0));
                                    $baseTotal += (float) (($row['metrics'][$baseMetricId] ?? 0.0));
                                }
                            }
                            $total = $baseTotal !== 0.0 ? (($numeratorTotal / $baseTotal) * 100.0) : 0.0;
                        } else {
                            foreach ($sumRows as $row) {
                                if ($op === 'metric') {
                                    $total += (float) (($row['metrics'][$id] ?? 0.0));
                                } else {
                                    $total += (float) (($row['formulas'][$id] ?? 0.0));
                                }
                            }
                        }

                        if ($op === 'percent') {
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx).$rowPtr, $total / 100);
                            $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx).$rowPtr)
                                ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
                        } else {
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx).$rowPtr, round($total, 2));
                        }
                        $colIdx++;
                    }

                    $sheet->getStyle($sumFirstLetter.$rowPtr.':'.$sumLastLetter.$rowPtr)
                        ->getFont()->setBold($sumTotalsBold)->getColor()->setARGB($sumTotalsTextColorArgb);
                    $rowPtr++;
                }

                $sumLastDataRow = $rowPtr - 1;
                $sheet->getStyle($sumFirstLetter.$sumHeaderRow.':'.$sumLastLetter.$sumHeaderRow)
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF475569');
                $sumLeadLastCol = $sumStartCol + $sumLeadCount - 1;
                $sheet->getStyle($sumFirstLetter.$sumHeaderRow.':'.Coordinate::stringFromColumnIndex($sumLeadLastCol).$sumHeaderRow)
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($sumGroupColorArgb);
                foreach ($sumCombinedCols as $idx => $col) {
                    $groupLabel = trim((string) ($col['group'] ?? ''));
                    if ($groupLabel === '') {
                        continue;
                    }
                    $groupKey = mb_strtolower($groupLabel, 'UTF-8');
                    $groupArgb = $this->groupHeaderColorByName[$groupKey] ?? 'FF64748B';
                    $colIndex = $sumStartCol + $sumLeadCount + $idx;
                    $letter = Coordinate::stringFromColumnIndex($colIndex);
                    $sheet->getStyle($letter.$sumHeaderRow)
                        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($groupArgb);
                }
                $sheet->getStyle($sumFirstLetter.$sumHeaderRow.':'.$sumLastLetter.$sumHeaderRow)
                    ->getFont()->setBold(true)->getColor()->setARGB(self::HEADER_FONT_COLOR);
                $sheet->getStyle($sumFirstLetter.$sumHeaderRow.':'.$sumLastLetter.$sumLastDataRow)
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle($sumFirstLetter.$sumHeaderRow.':'.$sumLastLetter.$sumLastDataRow)
                    ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle($sumFirstLetter.$sumDataStartRow.':'.$sumLastLetter.$sumLastDataRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($sumFirstLetter.$sumDataStartRow.':'.$sumFirstLetter.$sumLastDataRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                for ($leadIdx = 0; $leadIdx < $sumLeadCount; $leadIdx++) {
                    $leadCol = $sumStartCol + $leadIdx;
                    $leadKey = (string) ($sumLeadCols[$leadIdx]['key'] ?? 'group');
                    $leadWidth = 20;
                    if ($leadKey === 'item') {
                        $leadWidth = 6;
                    } elseif ($leadKey === 'delegacion_numero') {
                        $leadWidth = 12;
                    } elseif ($leadKey === 'cabecera_microrregion') {
                        $leadWidth = 22;
                    }
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($leadCol))->setWidth(max($leadWidth, (float) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($leadCol))->getWidth()));
                }
                for ($c = $sumStartCol + $sumLeadCount; $c <= $sumLastCol; $c++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(14);
                }

                $sumRowsConsumed = ($hasSumGroupHeaders ? 3 : 2) + count($sumRows) + ($sumIncludeTotalsRow ? 1 : 0);
                $countTableRows += $sumRowsConsumed + 3;
                $maxColIdx = max($maxColIdx, $sumLastCol);
            }
        }

        $lastColumnLetter = Coordinate::stringFromColumnIndex($maxColIdx);

        // Título y Fecha con el ancho máximo real
        $titleText = !empty($this->exportConfig['title']) ? (string)$this->exportConfig['title'] : (string)$temporaryModule->name;
        $titleText = $this->normalizeExportHeading($titleText, $titleUppercase);
        $titleFontSizePx = max(10, min(36, (int) ($this->exportConfig['title_font_size_px'] ?? 18)));
        $sheet->setCellValue('A'.$titleRow, $titleText);
        $sheet->mergeCells('A'.$titleRow.':'.$lastColumnLetter.$titleRow);
        $sheet->getStyle('A'.$titleRow)->getFont()->setSize((int) round($titleFontSizePx * 0.75))->setBold(true);

        $titleAlign = Alignment::HORIZONTAL_CENTER;
        if (is_array($this->exportConfig) && isset($this->exportConfig['title_align'])) {
            $alignCfg = (string) $this->exportConfig['title_align'];
            if ($alignCfg === 'left') $titleAlign = Alignment::HORIZONTAL_LEFT;
            elseif ($alignCfg === 'right') $titleAlign = Alignment::HORIZONTAL_RIGHT;
        }
        $sheet->getStyle('A'.$titleRow)->getAlignment()->setHorizontal($titleAlign)->setVertical(Alignment::VERTICAL_CENTER);

        // Fecha y hora de corte
        $sheet->setCellValue('A'.$dateRow, 'Fecha y hora de corte: ' . $fechaCorteStr);
        $sheet->mergeCells('A'.$dateRow.':'.$lastColumnLetter.$dateRow);
        $sheet->getStyle('A'.$dateRow)->getFont()->setSize(10);
        $sheet->getStyle('A'.$dateRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_TOP);

        $headerStartRow = 4 + $countTableRows;
        if ($includeCountTable) {
            $desgloseRow = 4 + $countTableRows;
            $sheet->setCellValue('A'.$desgloseRow, $this->normalizeExportHeading('Desglose', $headersUppercase));
            $sheet->mergeCells('A'.$desgloseRow.':'.$lastColumnLetter.$desgloseRow);
            $sheet->getStyle('A'.$desgloseRow)->getFont()->setBold(true);
            $headerStartRow++;
        }

        if ($hasSections) {
            $r1 = $headerStartRow;
            $r2 = $headerStartRow + 1;
            $sheet->setCellValue('A'.$r1, $fixedHeaders[0]);
            $sheet->setCellValue('B'.$r1, $fixedHeaders[1]);
            $sheet->setCellValue('C'.$r1, $fixedHeaders[2]);
            $sheet->mergeCells('A'.$r1.':A'.$r2);
            $sheet->mergeCells('B'.$r1.':B'.$r2);
            $sheet->mergeCells('C'.$r1.':C'.$r2);
            $colIdx = $numFixed + 1;
            $mergeStart = null;
            $mergeHeader1 = null;
            foreach ($exportColumns as $col) {
                $colLetter = Coordinate::stringFromColumnIndex($colIdx);
                $rawHeader1 = (string) ($col['header1'] ?? '');
                $header2 = $this->normalizeExportHeading((string) $col['header2'], $headersUppercase);
                $header1 = $this->normalizeExportHeading($rawHeader1, $headersUppercase);
                if ($header1 === $header2) {
                    $header1 = '';
                }
                $sheet->setCellValue($colLetter.$r1, $header1);
                $sheet->setCellValue($colLetter.$r2, $header2);
                $h1 = $header1;
                if ($h1 !== '') {
                    if ($mergeStart === null || $mergeHeader1 !== $h1) {
                        if ($mergeStart !== null) {
                            $startLetter = Coordinate::stringFromColumnIndex($mergeStart);
                            $endLetter = Coordinate::stringFromColumnIndex($colIdx - 1);
                            if ($mergeStart < $colIdx - 1) {
                                $sheet->mergeCells($startLetter.$r1.':'.$endLetter.$r1);
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
                            $sheet->mergeCells($startLetter.$r1.':'.$endLetter.$r1);
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
                    $sheet->mergeCells($startLetter.$r1.':'.$endLetter.$r1);
                }
            }
            $headerRowCount = 2;
        } else {
            $headers = array_merge($fixedHeaders, array_map(fn ($c) => $this->normalizeExportHeading((string) $c['header2'], $headersUppercase), $exportColumns));
            foreach ($headers as $i => $headerText) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).$headerStartRow, $headerText);
            }
            $headerRowCount = 1;
        }

        $tableHeaderFirstRow = $headerStartRow;
        $tableHeaderLastRow = $headerStartRow + $headerRowCount - 1;
        $cellFontSizePx = max(9, min(24, (int) ($this->exportConfig['cell_font_size_px'] ?? $this->exportConfig['cellFontPx'] ?? 12)));
        $cellFontSizePt = max(7, min(18, (int) round($cellFontSizePx * 0.75)));
        $headerFontSizePx = max(9, min(28, (int) ($this->exportConfig['header_font_size_px'] ?? $this->exportConfig['headerFontPx'] ?? 12)));
        $headerFontSizePt = max(7, min(21, (int) round($headerFontSizePx * 0.75)));
        $headerRange = 'A'.$tableHeaderFirstRow.':'.$lastColumnLetter.$tableHeaderLastRow;
        $sheet->getStyle($headerRange)
            ->getFont()->setBold(true)->setSize($headerFontSizePt)->getColor()->setARGB(self::HEADER_FONT_COLOR);
        $sheet->getStyle($headerRange)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL_COLOR);
        $sheet->getStyle($headerRange)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true)->setShrinkToFit(true);

        // Aplicar colores por columna según configuración (solo encabezados)
        if (!empty($this->columnConfigByKey)) {
            // 1) Fijos: Ítem y Microrregión
            $fixedKeys = [
                1 => 'item',
                2 => 'delegacion_numero',
                3 => 'cabecera_microrregion',
            ];
            foreach ($fixedKeys as $colIndex => $key) {
                $cfg = $this->columnConfigByKey[$key] ?? null;
                if (!is_array($cfg) || empty($cfg['color'])) {
                    continue;
                }
                $argb = $this->mapCssColorToArgb((string) $cfg['color']);
                if ($argb === null) {
                    continue;
                }
                $letter = Coordinate::stringFromColumnIndex($colIndex);
                $range = $letter.$tableHeaderFirstRow.':'.$letter.$tableHeaderLastRow;
                $sheet->getStyle($range)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($argb);
            }

            // 2) Dinámicos: columnas provenientes de campos del módulo
            foreach ($exportColumns as $idx => $col) {
                $field = $col['field'];
                $key = (string) $field->key;
                $cfg = $this->columnConfigByKey[$key] ?? null;
                if (!is_array($cfg) || empty($cfg['color'])) {
                    continue;
                }
                $argb = $this->mapCssColorToArgb((string) $cfg['color']);
                if ($argb === null) {
                    continue;
                }
                $colIndex = $numFixed + $idx + 1;
                $letter = Coordinate::stringFromColumnIndex($colIndex);
                $range = $letter.$tableHeaderFirstRow.':'.$letter.$tableHeaderLastRow;
                $sheet->getStyle($range)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($argb);
            }
        }

        if ($hasSections) {
            $groupHeaderRow = $tableHeaderFirstRow;
            foreach ($exportColumns as $idx => $col) {
                $group = trim((string) ($col['header1'] ?? ''));
                if ($group === '') {
                    continue;
                }
                $groupKey = mb_strtolower($group, 'UTF-8');
                $argb = $this->groupHeaderColorByName[$groupKey] ?? 'FF64748B';
                $colIndex = $numFixed + $idx + 1;
                $letter = Coordinate::stringFromColumnIndex($colIndex);
                $sheet->getStyle($letter.$tableHeaderFirstRow.':'.$letter.$tableHeaderLastRow)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($argb);
                $sheet->getStyle($letter.$tableHeaderFirstRow.':'.$letter.$tableHeaderLastRow)->getFont()->getColor()->setARGB(self::HEADER_FONT_COLOR);
            }
        }

        $dataStartRow = $headerStartRow + $headerRowCount;
        $sheet->setAutoFilter('A'.$tableHeaderFirstRow.':'.$lastColumnLetter.$tableHeaderLastRow);

        $rowIndex = $dataStartRow;

        if ($entriesQuery->count() === 0) {
            $sheet->setCellValue('A'.$rowIndex, 'Sin registros');
        } else {
            $itemNumber = 1;

            // Tracking for row spanning/merging in Delegación y Cabecera.
            $lastMicrorregionKey = null;
            $mergingStartRow = null;

            $entriesQuery->chunk(250, function ($entries) use (&$sheet, &$rowIndex, &$itemNumber, $microrregionMeta, $temporaryModule, $exportColumns, $headerRowCount, &$lastMicrorregionKey, &$mergingStartRow) {
                foreach ($entries as $entry) {
                    $sheet->setCellValue('A'.$rowIndex, $itemNumber);
                    $meta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));
                    $currentMicrorregionKey = (string) ($entry->microrregion_id ?? '__none__');
                    $mrNumber = (string) ($meta->number ?? $meta['number'] ?? '');
                    $mrCabecera = (string) ($meta->cabecera ?? $meta['cabecera'] ?? $meta->name ?? $meta['name'] ?? '');
                    if ($mrNumber === '' && $mrCabecera === '') {
                        $mrCabecera = 'Sin microrregión';
                    }

                    // Handling Microrregion merging (Columnas B y C)
                    if ($lastMicrorregionKey === null) {
                        $lastMicrorregionKey = $currentMicrorregionKey;
                        $mergingStartRow = $rowIndex;
                        $sheet->setCellValue('B'.$rowIndex, $mrNumber);
                        $sheet->setCellValue('C'.$rowIndex, $mrCabecera);
                    } elseif ($lastMicrorregionKey !== $currentMicrorregionKey) {
                        // Merge previous group
                        if ($mergingStartRow !== null && $mergingStartRow < ($rowIndex - 1)) {
                            $sheet->mergeCells('B'.$mergingStartRow.':B'.($rowIndex - 1));
                            $sheet->getStyle('B'.$mergingStartRow.':B'.($rowIndex - 1))->getAlignment()
                                ->setVertical(Alignment::VERTICAL_CENTER)
                                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                            $sheet->mergeCells('C'.$mergingStartRow.':C'.($rowIndex - 1));
                            $sheet->getStyle('C'.$mergingStartRow.':C'.($rowIndex - 1))->getAlignment()
                                ->setVertical(Alignment::VERTICAL_CENTER)
                                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        }
                        $lastMicrorregionKey = $currentMicrorregionKey;
                        $mergingStartRow = $rowIndex;
                        $sheet->setCellValue('B'.$rowIndex, $mrNumber);
                        $sheet->setCellValue('C'.$rowIndex, $mrCabecera);
                    } else {
                        // Same as before, don't set value yet (PhpSpreadsheet requirement for merging later)
                        $sheet->setCellValue('B'.$rowIndex, '');
                        $sheet->setCellValue('C'.$rowIndex, '');
                    }

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

                        // Multiselect: array → comma-joined string
                        if ($field->type === 'multiselect' && is_array($cell)) {
                            $sheet->setCellValue($cellCoordinate, implode(', ', $cell));
                            $columnIndex++;
                            continue;
                        }

                        // Linked: {primary, secondary} object → split into two virtual columns
                        if ($field->type === 'linked') {
                            // We emit two cells: primary then secondary (columns are doubled in buildExportColumns)
                            $linkOpts = is_array($field->options) ? $field->options : [];
                            $primaryVal = is_array($cell) ? ($cell['primary'] ?? '') : '';
                            $secondaryVal = is_array($cell) ? ($cell['secondary'] ?? '') : '';
                            $primaryOut = is_scalar($primaryVal) ? (string) $primaryVal : '';
                            if (($linkOpts['primary_type'] ?? '') === 'semaforo' && $primaryOut !== '') {
                                $primaryOut = TemporaryModuleFieldService::labelForSemaforo($primaryOut) ?: $primaryOut;
                            }
                            $secondaryOut = is_scalar($secondaryVal) ? (string) $secondaryVal : '';
                            if (($linkOpts['secondary_type'] ?? '') === 'semaforo' && $secondaryOut !== '') {
                                $secondaryOut = TemporaryModuleFieldService::labelForSemaforo($secondaryOut) ?: $secondaryOut;
                            }
                            $sheet->setCellValue($cellCoordinate, $primaryOut);
                            $columnIndex++;
                            $secondaryCoord = Coordinate::stringFromColumnIndex($columnIndex).$rowIndex;
                            $sheet->setCellValue($secondaryCoord, $secondaryOut);
                            $columnIndex++;
                            continue;
                        }

                        if ($field->type === 'semaforo' && is_string($cell) && trim($cell) !== '') {
                            $sheet->setCellValue($cellCoordinate, TemporaryModuleFieldService::labelForSemaforo($cell) ?: $cell);
                            $columnIndex++;
                            continue;
                        }

                        if ($this->shouldRenderAsImageCell($fieldType, $cell)) {
                            $rawPaths = is_array($cell) ? array_filter($cell) : ($cell ? [(string) $cell] : []);
                            $imagesRendered = 0;
                            foreach ($rawPaths as $idx => $rawValue) {
                                if (!is_string($rawValue) || trim($rawValue) === '') continue;
                                $rawValue = trim($rawValue);
                                $localPath = null;
                                if (filter_var($rawValue, FILTER_VALIDATE_URL)) {
                                    $localPath = $this->tryResolveLocalPathFromUrl($rawValue);
                                } else {
                                    $localPath = $this->entryDataService->resolveStoredFilePath($rawValue);
                                }

                                if (is_string($localPath) && is_file($localPath)) {
                                    $drawing = new Drawing();
                                    $drawing->setPath($localPath);
                                    $drawing->setCoordinates($cellCoordinate);
                                    $drawing->setOffsetX(2 + ($imagesRendered * 85)); // Offset horizontal para múltiples imágenes
                                    $drawing->setOffsetY(2);
                                    $drawing->setResizeProportional(true);
                                    $height = 80;
                                    $fieldKeyStr = (string)$field->key;
                                    $fieldCfg = $this->columnConfigByKey[$fieldKeyStr] ?? null;
                                    if (is_array($fieldCfg) && isset($fieldCfg['image_height']) && is_numeric($fieldCfg['image_height'])) {
                                        $height = max(20, min((int) $fieldCfg['image_height'], 400));
                                    }
                                    $drawing->setHeight($height);
                                    $drawing->setWorksheet($sheet);
                                    $heightPt = ($height * 0.75) + 4;
                                    $currentRowHeight = (float) $sheet->getRowDimension($rowIndex)->getRowHeight();
                                    if ($heightPt > $currentRowHeight || $currentRowHeight < 0) {
                                        $sheet->getRowDimension($rowIndex)->setRowHeight($heightPt);
                                    }
                                    $imagesRendered++;
                                }
                            }
                            if ($imagesRendered === 0) {
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

            // Merge final microrregión group once, after processing all chunks.
            if ($mergingStartRow !== null && $mergingStartRow < ($rowIndex - 1)) {
                $sheet->mergeCells('B'.$mergingStartRow.':B'.($rowIndex - 1));
                $sheet->getStyle('B'.$mergingStartRow.':B'.($rowIndex - 1))->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->mergeCells('C'.$mergingStartRow.':C'.($rowIndex - 1));
                $sheet->getStyle('C'.$mergingStartRow.':C'.($rowIndex - 1))->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        $lastDataRow = $rowIndex > $dataStartRow ? $rowIndex - 1 : $dataStartRow;
        $tableRange = 'A'.$tableHeaderFirstRow.':'.$lastColumnLetter.$lastDataRow;
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($tableRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        if ($lastDataRow > $tableHeaderLastRow) {
            $sheet->getStyle('A'.$dataStartRow.':'.$lastColumnLetter.$lastDataRow)
                ->getFont()->setSize($cellFontSizePt);
        }

        if ($lastDataRow > $tableHeaderLastRow) {
            $sheet->getStyle('A'.$dataStartRow.':'.$lastColumnLetter.$lastDataRow)
                ->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        }

        // Celdas de datos se mantienen con color de fondo por defecto (blanco),
        // solo los encabezados se colorean por columna.

        for ($colIdx = 1; $colIdx <= $maxColIdx; $colIdx++) {
            $columnLetter = Coordinate::stringFromColumnIndex($colIdx);

            $cfgWidth = null;
            $fieldKey = null;

            if ($colIdx === 1) {
                $fieldKey = 'item';
                $defaultWidth = 4;
            } elseif ($colIdx === 2) {
                $fieldKey = 'delegacion_numero';
                $defaultWidth = 10;
            } elseif ($colIdx === 3) {
                $fieldKey = 'cabecera_microrregion';
                $defaultWidth = 18;
            } else {
                $exportColIndex = $colIdx - 4;
                if (isset($exportColumns[$exportColIndex])) {
                    $fieldKey = (string) $exportColumns[$exportColIndex]['field']->key;
                }
                $defaultWidth = 20;
            }

            if ($fieldKey) {
                $colCfg = $this->columnConfigByKey[$fieldKey] ?? null;
                if (is_array($colCfg) && isset($colCfg['max_width_chars']) && is_numeric($colCfg['max_width_chars'])) {
                    $chars = (int) $colCfg['max_width_chars'];
                    $cfgWidth = max(4, min($chars + 2, 50));
                }
            }

            $sheet->getColumnDimension($columnLetter)->setWidth($cfgWidth ?? $defaultWidth);
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

    private function isImageLikeStoredValue(mixed $value): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        $path = trim((string) $value);
        if ($path === '') {
            return false;
        }

        $extension = strtolower((string) pathinfo(parse_url($path, PHP_URL_PATH) ?? $path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
    }

    private function shouldRenderAsImageCell(string $fieldType, mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $v) {
                if ($this->shouldRenderAsImageCell($fieldType, $v)) return true;
            }
            return false;
        }

        if (!is_scalar($value)) {
            return false;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return false;
        }

        if ($fieldType === 'image') {
            return true;
        }

        if (! in_array($fieldType, ['file', 'document'], true)) {
            return false;
        }

        if ($this->isImageLikeStoredValue($raw)) {
            return true;
        }

        if (filter_var($raw, FILTER_VALIDATE_URL)) {
            return false;
        }

        $fullPath = $this->entryDataService->resolveStoredFilePath($raw);
        if (!is_string($fullPath) || !is_file($fullPath)) {
            return false;
        }

        $mime = strtolower((string) (@mime_content_type($fullPath) ?: ''));

        return str_starts_with($mime, 'image/');
    }


    private function tryResolveLocalPathFromUrl(string $url): ?string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $path = ltrim($path, '/');
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8) ?: '';
        }

        if ($path === '') {
            return null;
        }

        $resolved = $this->entryDataService->resolveStoredFilePath($path);
        return (is_string($resolved) && is_file($resolved)) ? $resolved : null;
    }
}
