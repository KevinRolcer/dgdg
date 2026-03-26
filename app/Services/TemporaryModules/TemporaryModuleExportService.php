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
    public function __construct(
        private readonly TemporaryModuleFieldService $fieldService,
        private readonly TemporaryModuleEntryDataService $entryDataService,
    ) {
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

        // Normalizar configuración opcional desde el front
        $this->exportConfig = $exportConfig ?? [];
        $this->columnConfigByKey = [];
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
                ->join('microrregiones', 'microrregiones.id', '=', 'temporary_module_entries.microrregion_id')
                ->orderByRaw('CAST(microrregiones.microrregion AS UNSIGNED) ASC')
                ->distinct()
                ->pluck('microrregion_id')
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

                    $entriesQuery = $temporaryModule->entries()
                        ->where('microrregion_id', $microrregionId)
                        ->latest('submitted_at');

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
                $this->applyPrintSetup($totalsSheet, $orientation);
            } else {
                $targetSheet = $createDataSheet();
                $targetSheet->setTitle('Registros');
                $entriesQuery = $temporaryModule->entries()
                    ->withoutGlobalScopes()
                    ->leftJoin('microrregiones', 'microrregiones.id', '=', 'temporary_module_entries.microrregion_id')
                    ->orderByRaw('CASE WHEN microrregion_id IS NULL THEN 1 ELSE 0 END, CAST(microrregiones.microrregion AS UNSIGNED) ASC, submitted_at DESC')
                    ->select('temporary_module_entries.*');

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

    public function generateTemplate(TemporaryModule $module): array
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Plantilla');

        $importableTypes = TemporaryModuleExcelImportService::IMPORTABLE_TYPES;
        $fields = $module->fields()
            ->whereIn('type', $importableTypes)
            ->orderBy('sort_order')
            ->get();

        $col = 1;
        foreach ($fields as $field) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue($letter.'1', $field->label);

            $style = $sheet->getStyle($letter.'1');
            $style->getFont()->setBold(true)->getColor()->setARGB(self::HEADER_FONT_COLOR);
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL_COLOR);

            // Placeholder content in first data row
            $placeholder = '';
            $type = (string) $field->type;
            if ($type === 'select') {
                $opts = is_array($field->options) ? $field->options : [];
                $placeholder = '('.implode(', ', $opts).')';
            } elseif ($type === 'image' || $type === 'file') {
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

        $baseName = Str::slug((string) $module->name, '_');
        if ($baseName === '') {
            $baseName = 'plantilla_modulo_'.$module->id;
        }
        $fileName = 'plantilla_'.$baseName.'.xlsx';
        $exportDir = storage_path('app/public/temporary-exports');
        $tempFilePath = $exportDir.'/'.$fileName;

        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFilePath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'name' => $fileName,
            'url' => route('temporary-modules.plantilla.download', ['file' => $fileName]),
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
    private function buildCountTableData(Collection $entries, array $countByFields, array $fieldLabels = []): array
    {
        $total = $entries->count();
        $groups = [
            ['label' => 'Total de registros', 'values' => [['label' => '', 'count' => $total]]],
        ];

        foreach ($countByFields as $fieldKey) {
            $valueCounts = [];
            $labelByLower = [];
            foreach ($entries as $entry) {
                $val = $entry->data[$fieldKey] ?? null;

                // Multiselect: count each option separately
                if (is_array($val) && !isset($val['primary'])) {
                    foreach ($val as $item) {
                        $key = is_scalar($item) ? trim((string) $item) : '';
                        if ($key !== '') {
                            $lower = mb_strtolower($key);
                            $valueCounts[$lower] = ($valueCounts[$lower] ?? 0) + 1;
                            if (!isset($labelByLower[$lower])) {
                                $labelByLower[$lower] = $key;
                            }
                        }
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
                }
            }
            ksort($valueCounts, SORT_NATURAL);
            $values = [];
            foreach ($valueCounts as $lower => $count) {
                $values[] = ['label' => $labelByLower[$lower] ?? $lower, 'count' => $count];
            }
            if ($values !== []) {
                $groups[] = [
                    'label' => $fieldLabels[$fieldKey] ?? $fieldKey,
                    'values' => $values,
                ];
            }
        }

        return ['groups' => $groups];
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

        $fixedHeaders = ['Ítem', 'Microrregión'];
        $numFixed = count($fixedHeaders);
        $fechaCorteStr = $fechaCorte->format('d/m/Y H:i');
        $dataColumnsCount = $numFixed + count($exportColumns);

        $titleRow = 1;
        $dateRow = 2;
        $includeCountTable = !empty($this->exportConfig['include_count_table']);
        $countByFields = $includeCountTable && is_array($this->exportConfig['count_by_fields'] ?? null)
            ? array_values(array_filter(array_map('strval', $this->exportConfig['count_by_fields'])))
            : [];
        $countTableColors = is_array($this->exportConfig['count_table_colors'] ?? null) ? $this->exportConfig['count_table_colors'] : [];
        $countTableRows = 0;
        $maxColIdx = $dataColumnsCount;

        if ($includeCountTable) {
            $entriesForCount = (clone $entriesQuery)->get(['id', 'data']);
            $fieldLabels = [];
            foreach ($temporaryModule->fields as $f) {
                if ($f->type !== 'seccion') {
                    $fieldLabels[$f->key] = (string) ($f->label ?? $f->key);
                }
            }
            $countData = $this->buildCountTableData($entriesForCount, $countByFields, $fieldLabels);
            $groups = $countData['groups'];
            $colIdx = 1;
            $groupRow = 3;
            $valueRow = 4;
            $dataRow = 5;

            // Primero: Escribir contenido y calcular ancho
            foreach ($groups as $gi => $group) {
                $key = $gi === 0 ? '_total' : ($countByFields[$gi - 1] ?? '');
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
            $sheet->getStyle('A'.$groupRow.':'.$countLastColLetter.$groupRow)->getFont()->setBold(true)->getColor()->setARGB(self::HEADER_FONT_COLOR);
            $sheet->getStyle('A'.$valueRow.':'.$countLastColLetter.$valueRow)->getFont()->setBold(true);
            $sheet->getStyle('A'.$valueRow.':'.$countLastColLetter.$valueRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
            $sheet->getStyle('A'.$dataRow.':'.$countLastColLetter.$dataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A'.$dataRow.':'.$countLastColLetter.$dataRow)->getFont()->getColor()->setARGB('FFFF0000');

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
                $key = $gi === 0 ? '_total' : ($countByFields[$gi - 1] ?? '');
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

        $lastColumnLetter = Coordinate::stringFromColumnIndex($maxColIdx);

        // Título y Fecha con el ancho máximo real
        $titleText = !empty($this->exportConfig['title']) ? (string)$this->exportConfig['title'] : (string)$temporaryModule->name;
        $sheet->setCellValue('A'.$titleRow, $titleText);
        $sheet->mergeCells('A'.$titleRow.':'.$lastColumnLetter.$titleRow);
        $sheet->getStyle('A'.$titleRow)->getFont()->setSize(16)->setBold(true);

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

        $headerStartRow = 3 + $countTableRows;
        if ($includeCountTable) {
            $desgloseRow = 3 + $countTableRows;
            $sheet->setCellValue('A'.$desgloseRow, 'Desglose');
            $sheet->mergeCells('A'.$desgloseRow.':'.$lastColumnLetter.$desgloseRow);
            $sheet->getStyle('A'.$desgloseRow)->getFont()->setBold(true);
            $headerStartRow++;
        }

        if ($hasSections) {
            $r1 = $headerStartRow;
            $r2 = $headerStartRow + 1;
            $sheet->setCellValue('A'.$r1, $fixedHeaders[0]);
            $sheet->setCellValue('B'.$r1, $fixedHeaders[1]);
            $sheet->mergeCells('A'.$r1.':A'.$r2);
            $sheet->mergeCells('B'.$r1.':B'.$r2);
            $colIdx = $numFixed + 1;
            $mergeStart = null;
            $mergeHeader1 = null;
            foreach ($exportColumns as $col) {
                $colLetter = Coordinate::stringFromColumnIndex($colIdx);
                $sheet->setCellValue($colLetter.$r1, $col['header1'] ?? '');
                $sheet->setCellValue($colLetter.$r2, $col['header2']);
                $h1 = $col['header1'] ?? '';
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
            $headers = array_merge($fixedHeaders, array_map(fn ($c) => $c['header2'], $exportColumns));
            foreach ($headers as $i => $headerText) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).$headerStartRow, $headerText);
            }
            $headerRowCount = 1;
        }

        $tableHeaderFirstRow = $headerStartRow;
        $tableHeaderLastRow = $headerStartRow + $headerRowCount - 1;
        $headerRange = 'A'.$tableHeaderFirstRow.':'.$lastColumnLetter.$tableHeaderLastRow;
        $sheet->getStyle($headerRange)
            ->getFont()->setBold(true)->getColor()->setARGB(self::HEADER_FONT_COLOR);
        $sheet->getStyle($headerRange)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL_COLOR);
        $sheet->getStyle($headerRange)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true)->setShrinkToFit(true);

        // Aplicar colores por columna según configuración (solo encabezados)
        if (!empty($this->columnConfigByKey)) {
            // 1) Fijos: Ítem y Microrregión
            $fixedKeys = [
                1 => 'item',
                2 => 'microrregion',
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

        $dataStartRow = $headerStartRow + $headerRowCount;
        $sheet->freezePane('A'.$dataStartRow);
        $sheet->setAutoFilter('A'.$tableHeaderFirstRow.':'.$lastColumnLetter.$tableHeaderLastRow);

        $rowIndex = $dataStartRow;

        if ($entriesQuery->count() === 0) {
            $sheet->setCellValue('A'.$rowIndex, 'Sin registros');
        } else {
            $itemNumber = 1;

            // Tracking for row spanning/merging (microrregion column is usually B [index 2])
            $lastMicrorregionValue = null;
            $mergingStartRow = null;

            $entriesQuery->chunk(250, function ($entries) use (&$sheet, &$rowIndex, &$itemNumber, $microrregionMeta, $temporaryModule, $exportColumns, $headerRowCount, &$lastMicrorregionValue, &$mergingStartRow) {
                foreach ($entries as $entry) {
                    $sheet->setCellValue('A'.$rowIndex, $itemNumber);
                    $meta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));
                    $currentMicrorregionValue = (string) ($meta->label ?? $meta['label'] ?? 'Sin microrregión');

                    // Handling Microrregion merging (Column B)
                    if ($lastMicrorregionValue === null) {
                        $lastMicrorregionValue = $currentMicrorregionValue;
                        $mergingStartRow = $rowIndex;
                        $sheet->setCellValue('B'.$rowIndex, $currentMicrorregionValue);
                    } elseif ($lastMicrorregionValue !== $currentMicrorregionValue) {
                        // Merge previous group
                        if ($mergingStartRow !== null && $mergingStartRow < ($rowIndex - 1)) {
                            $sheet->mergeCells('B'.$mergingStartRow.':B'.($rowIndex - 1));
                            $sheet->getStyle('B'.$mergingStartRow.':B'.($rowIndex - 1))->getAlignment()
                                ->setVertical(Alignment::VERTICAL_CENTER)
                                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        }
                        $lastMicrorregionValue = $currentMicrorregionValue;
                        $mergingStartRow = $rowIndex;
                        $sheet->setCellValue('B'.$rowIndex, $currentMicrorregionValue);
                    } else {
                        // Same as before, don't set value yet (PhpSpreadsheet requirement for merging later)
                        $sheet->setCellValue('B'.$rowIndex, '');
                    }

                    $columnIndex = 3;
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
                                        $height = 80;
                                        $fieldCfg = $this->columnConfigByKey[(string) $field->key] ?? null;
                                        if (is_array($fieldCfg) && isset($fieldCfg['image_height']) && is_numeric($fieldCfg['image_height'])) {
                                            $height = max(20, min((int) $fieldCfg['image_height'], 400));
                                        }
                                        $drawing->setHeight($height);
                                        $drawing->setWorksheet($sheet);
                                        // Asegurar que el alto de la fila sea suficiente para la imagen más alta en ella
                                        $currentRowHeight = (float) $sheet->getRowDimension($rowIndex)->getRowHeight();
                                        if ($height > $currentRowHeight || $currentRowHeight < 0) {
                                            $sheet->getRowDimension($rowIndex)->setRowHeight($height + 4);
                                        }
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

                // Merge last remaining group after all iterations
                if ($mergingStartRow !== null && $mergingStartRow < ($rowIndex - 1)) {
                    $sheet->mergeCells('B'.$mergingStartRow.':B'.($rowIndex - 1));
                    $sheet->getStyle('B'.$mergingStartRow.':B'.($rowIndex - 1))->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            });
        }

        $lastDataRow = $rowIndex > $dataStartRow ? $rowIndex - 1 : $dataStartRow;
        $tableRange = 'A'.$tableHeaderFirstRow.':'.$lastColumnLetter.$lastDataRow;
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($tableRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

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
                $fieldKey = 'microrregion';
                $defaultWidth = 18;
            } else {
                $exportColIndex = $colIdx - 3;
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
}
