<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Section as SectionStyle;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Storage;

class TemporaryModuleWordPdfService
{
    public function __construct(
        private readonly TemporaryModuleEntryDataService $entryDataService,
    ) {
    }

    private function resolveMicrorregionSortDirection(?array $exportConfig): string
    {
        $direction = strtolower(trim((string) ($exportConfig['microrregion_sort'] ?? 'asc')));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';
    }

    private function sortEntriesByMicrorregion(Collection $entries, Collection $microrregionMeta, string $direction): Collection
    {
        $descending = strtolower($direction) === 'desc';

        return $entries->sort(function ($left, $right) use ($microrregionMeta, $descending) {
            $leftMeta = $microrregionMeta->get((int) ($left->microrregion_id ?? 0));
            $rightMeta = $microrregionMeta->get((int) ($right->microrregion_id ?? 0));

            $leftNumber = (int) ($leftMeta['number'] ?? 999999);
            $rightNumber = (int) ($rightMeta['number'] ?? 999999);

            if ($leftNumber !== $rightNumber) {
                return $descending ? ($rightNumber <=> $leftNumber) : ($leftNumber <=> $rightNumber);
            }

            $leftSubmitted = optional($left->submitted_at)?->getTimestamp() ?? 0;
            $rightSubmitted = optional($right->submitted_at)?->getTimestamp() ?? 0;
            if ($leftSubmitted !== $rightSubmitted) {
                return $rightSubmitted <=> $leftSubmitted;
            }

            return ((int) ($left->id ?? 0)) <=> ((int) ($right->id ?? 0));
        })->values();
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

    /**
     * @param string $format 'word' or 'pdf'
     * @param array|null $exportConfig
     * @return array{name: string, url: string}
     * @throws \Exception
     */
    public function export(int $moduleId, string $format, ?array $exportConfig = null): array
    {
        // Tamaño de letra para encabezados de columnas
        $headerFontRaw = $exportConfig['records_header_font_size_px'] ?? $exportConfig['header_font_size_px'] ?? $exportConfig['headerFontPx'] ?? null;
        $headerFontSizePx = $headerFontRaw !== null ? max(9, min(28, (int) $headerFontRaw)) : 12;
        $headerFontSizePt = max(7, min(21, (int) round($headerFontSizePx * 0.75)));

        $temporaryModule = TemporaryModule::query()->findOrFail($moduleId);
        $fileName = trim((string) $temporaryModule->name) !== '' ? $temporaryModule->name : 'Módulo '.$moduleId;

        // PDF/Word export can be expensive with many rows/images; relax runtime constraints.
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1536M');
        $columnsCfg = is_array($exportConfig) && isset($exportConfig['columns']) && is_array($exportConfig['columns'])
            ? $exportConfig['columns']
            : [];

        // Si no hay configuracion de columnas, tomar todas como es el caso por defecto.
        $dbFieldLabels = $temporaryModule->fields->pluck('label', 'key')->all();

        if ($columnsCfg === []) {
             $cols = [];
             // Agregar columnas virtuales fijas al inicio
             $cols[] = ['key' => 'item', 'label' => '#', 'color' => ''];
             $cols[] = ['key' => 'delegacion_numero', 'label' => 'Delegación', 'color' => ''];
             $cols[] = ['key' => 'cabecera_microrregion', 'label' => 'Cabecera', 'color' => ''];
             foreach ($temporaryModule->fields as $field) {
                 $cols[] = [
                     'key' => $field->key,
                     'label' => (string) ($field->label ?? $field->key),
                     'color' => ''
                 ];
             }
             if(count($cols) > 0) {
                 $columnsCfg = $cols;
             }
        }

        $fieldTypesByKey = $temporaryModule->fields->pluck('type', 'key')->all();

        $columnMap = [];
        foreach ($columnsCfg as $col) {
            if (!is_array($col)) {
                continue;
            }
            $key = (string) ($col['key'] ?? '');
            if ($key === '') {
                continue;
            }

            // Preferir etiqueta del config si no es el mismo key y no está vacía,
            // de lo contrario usar la de la DB.
            $label = (string) ($col['label'] ?? '');
            if ($label === '' || $label === $key) {
                $label = $dbFieldLabels[$key] ?? $key;
            }

            $mw = $col['max_width_chars'] ?? null;
            $columnMap[$key] = [
                'key' => $key,
                'label' => $label,
                'color' => (string) ($col['color'] ?? ''),
                'group' => (string) ($col['group'] ?? ''),
                'max_width_chars' => ($mw !== null && is_numeric($mw)) ? (int) $mw : null,
                'fill_empty_mode' => (string) ($col['fill_empty_mode'] ?? 'none'),
                'fill_empty_value' => (string) ($col['fill_empty_value'] ?? ''),
                'content_bold' => !empty($col['content_bold']),
                'breakdown_answer_fills' => is_array($col['breakdown_answer_fills'] ?? null) ? $col['breakdown_answer_fills'] : [],
                'breakdown_data_text_color' => (string) ($col['breakdown_data_text_color'] ?? ''),
            ];
        }
        $columns = array_values($columnMap);
        if ($columns === []) {
            throw new \Exception('No hay columnas seleccionadas para el reporte.');
        }

        $calculatedColumns = [];
        if (!empty($exportConfig['include_calculated_columns']) && is_array($exportConfig['calculated_columns'] ?? null)) {
            foreach ($exportConfig['calculated_columns'] as $idx => $calcRaw) {
                if (!is_array($calcRaw)) {
                    continue;
                }
                $id = trim((string) ($calcRaw['id'] ?? ('calc_'.$idx)));
                if ($id === '') {
                    $id = 'calc_'.$idx;
                }
                $label = trim((string) ($calcRaw['label'] ?? ''));
                if ($label === '') {
                    $label = 'Calculada '.($idx + 1);
                }
                $operation = strtolower(trim((string) ($calcRaw['operation'] ?? '')));
                if (!in_array($operation, ['add', 'subtract', 'multiply', 'percent'], true)) {
                    $operation = (!array_key_exists('include_percent', $calcRaw) || !empty($calcRaw['include_percent'])) ? 'percent' : 'add';
                }
                $group = trim((string) ($calcRaw['group'] ?? ''));
                $baseField = trim((string) ($calcRaw['base_field'] ?? $calcRaw['baseField'] ?? $calcRaw['reference_field'] ?? $calcRaw['referenceField'] ?? ''));
                $afterKey = trim((string) ($calcRaw['position_after_key'] ?? $calcRaw['after_key'] ?? $calcRaw['afterKey'] ?? ''));
                $operationFields = is_array($calcRaw['operation_fields'] ?? null)
                    ? array_values(array_filter(array_map('strval', $calcRaw['operation_fields']), static fn (string $k): bool => $k !== ''))
                    : (is_array($calcRaw['fields'] ?? null)
                        ? array_values(array_filter(array_map('strval', $calcRaw['fields']), static fn (string $k): bool => $k !== ''))
                        : []);

                $cellColor = trim((string) ($calcRaw['cell_color'] ?? $calcRaw['cellColor'] ?? $calcRaw['color'] ?? ''));
                if ($cellColor === '') {
                    $cellColor = 'var(--clr-secondary)';
                }
                $cellSizeRaw = $calcRaw['cell_size_ch'] ?? $calcRaw['cellSizeCh'] ?? null;
                $cellSize = 18;
                if ($cellSizeRaw !== null && is_numeric($cellSizeRaw)) {
                    $cellSize = (int) $cellSizeRaw;
                }
                $cellSize = max(8, min(40, $cellSize));

                $calculatedColumns[] = [
                    'id' => $id,
                    'label' => $label,
                    'group' => $group,
                    'operation' => $operation,
                    'base_field' => $baseField,
                    'position_after_key' => $afterKey,
                    'operation_fields' => $operationFields,
                    'cell_color' => $cellColor,
                    'cell_size_ch' => $cellSize,
                    'cell_bold' => !empty($calcRaw['cell_bold']) || !empty($calcRaw['cellBold']),
                    // Compatibilidad con config legado.
                    'reference_field' => $baseField,
                    'include_percent' => $operation === 'percent',
                    'fields' => $operationFields,
                    'weights' => [],
                ];
            }
        } elseif (!empty($exportConfig['include_operations_column'])) {
            $legacyOp = !array_key_exists('operations_include_percent', (array) $exportConfig)
                || !empty($exportConfig['operations_include_percent'])
                ? 'percent'
                : 'add';
            $legacyFields = is_array($exportConfig['operations_fields'] ?? null)
                ? array_values(array_filter(array_map('strval', $exportConfig['operations_fields']), static fn (string $k): bool => $k !== ''))
                : [];
            $calculatedColumns[] = [
                'id' => 'legacy_ops_1',
                'label' => trim((string) ($exportConfig['operations_label'] ?? 'Operaciones')) ?: 'Operaciones',
                'group' => trim((string) ($exportConfig['operations_group'] ?? '')),
                'operation' => $legacyOp,
                'base_field' => trim((string) ($exportConfig['operations_reference_field'] ?? '')),
                'position_after_key' => trim((string) ($exportConfig['operations_after_key'] ?? '')),
                'operation_fields' => $legacyFields,
                'cell_color' => (string) ($exportConfig['operations_color'] ?? 'var(--clr-secondary)'),
                'cell_size_ch' => 18,
                'cell_bold' => !empty($exportConfig['operations_cell_bold']),
                'reference_field' => trim((string) ($exportConfig['operations_reference_field'] ?? '')),
                'include_percent' => $legacyOp === 'percent',
                'fields' => $legacyFields,
                'weights' => [],
            ];
        }

        foreach ($calculatedColumns as $calc) {
            $calcColor = trim((string) ($calc['cell_color'] ?? ''));
            if ($calcColor === '') {
                $calcColor = (string) ($exportConfig['operations_color'] ?? 'var(--clr-secondary)');
            }
            $calcSize = (int) ($calc['cell_size_ch'] ?? 18);
            $calcSize = max(8, min(40, $calcSize));
            $calcColumn = [
                'key' => '__calc_'.((string) ($calc['id'] ?? 'calc')),
                'label' => (string) ($calc['label'] ?? 'Calculada'),
                'color' => $calcColor,
                'group' => (string) ($calc['group'] ?? ''),
                'max_width_chars' => $calcSize,
                'content_bold' => !empty($calc['cell_bold']),
                '_calc_config' => $calc,
            ];

            $afterKey = trim((string) ($calc['position_after_key'] ?? ''));
            if ($afterKey === '') {
                $columns[] = $calcColumn;
                continue;
            }

            $inserted = false;
            $nextColumns = [];
            foreach ($columns as $existingColumn) {
                $nextColumns[] = $existingColumn;
                if ((string) ($existingColumn['key'] ?? '') === $afterKey) {
                    $nextColumns[] = $calcColumn;
                    $inserted = true;
                }
            }
            if (! $inserted) {
                $nextColumns[] = $calcColumn;
            }
            $columns = $nextColumns;
        }

        $totalCols = count($columns);
        $stretch = ($exportConfig['table_align'] ?? 'left') === 'stretch';

        $sortDirection = strtoupper($this->resolveMicrorregionSortDirection($exportConfig));

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

                $label = $number !== ''
                    ? ('MR '.str_pad($number, 2, '0', STR_PAD_LEFT).($name !== '' ? ' — '.$name : ''))
                    : ($name !== '' ? $name : 'Sin microrregión');

                return [(int) $row->id => [
                    'number' => $number,
                    'name' => $name,
                    'cabecera' => $name,
                    'label' => $label,
                ]];
            });

        $entries = $temporaryModule->entries()
            ->withoutGlobalScopes()
            ->leftJoin('microrregiones', 'microrregiones.id', '=', 'temporary_module_entries.microrregion_id')
            ->orderByRaw(
                'CASE WHEN temporary_module_entries.microrregion_id IS NULL THEN 1 ELSE 0 END, '.
                'CAST(COALESCE(microrregiones.microrregion, 0) AS UNSIGNED) '.$sortDirection.', '.
                'temporary_module_entries.submitted_at DESC'
            )
            ->select('temporary_module_entries.*')
            ->get(['microrregion_id', 'data', 'submitted_at']);
        $entries = $this->sortEntriesByMicrorregion($entries, $microrregionMeta, $sortDirection);

        $exportDir = storage_path('app/public/temporary-exports');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $titleUppercase = !empty($exportConfig['title_uppercase']);
        $headersUppercase = !empty($exportConfig['headers_uppercase']);
        $title = $this->normalizeExportHeading((string) ($exportConfig['title'] ?? $fileName), $titleUppercase);
        $orientationConfig = ($exportConfig['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
        $paperSizeRaw = strtolower(trim((string) ($exportConfig['paper_size'] ?? 'letter')));
        if ($paperSizeRaw === 'oficio') {
            $paperSizeRaw = 'legal';
        }
        $paperSize = in_array($paperSizeRaw, ['letter', 'legal'], true) ? $paperSizeRaw : 'letter';
        $docMarginPreset = strtolower((string) ($exportConfig['doc_margin_preset'] ?? 'compact'));
        if (!in_array($docMarginPreset, ['normal', 'compact', 'none'], true)) {
            $docMarginPreset = 'compact';
        }
        $titleAlign = (string) ($exportConfig['title_align'] ?? 'center');
        $countTableAlign = strtolower((string) ($exportConfig['count_table_align'] ?? 'left'));
        if (!in_array($countTableAlign, ['left', 'center', 'right'], true)) {
            $countTableAlign = 'left';
        }
        $sumTableAlign = strtolower((string) ($exportConfig['sum_table_align'] ?? 'left'));
        if (!in_array($sumTableAlign, ['left', 'center', 'right'], true)) {
            $sumTableAlign = 'left';
        }
        $sumTitle = trim((string) ($exportConfig['sum_title'] ?? 'Sumatoria'));
        if ($sumTitle === '') {
            $sumTitle = 'Sumatoria';
        }
        $sumTitleCase = strtolower((string) ($exportConfig['sum_title_case'] ?? 'normal'));
        if (!in_array($sumTitleCase, ['normal', 'upper', 'lower'], true)) {
            $sumTitleCase = 'normal';
        }
        $sumTitleAlign = strtolower((string) ($exportConfig['sum_title_align'] ?? 'center'));
        if (!in_array($sumTitleAlign, ['left', 'center', 'right'], true)) {
            $sumTitleAlign = 'center';
        }
        $sumTitleFontSizePx = max(10, min(36, (int) ($exportConfig['sum_title_font_size_px'] ?? 14)));
        $sumTitleFontSizePt = max(8, min(27, (int) round($sumTitleFontSizePx * 0.75)));
        $sumShowItem = !array_key_exists('sum_show_item', $exportConfig) || !empty($exportConfig['sum_show_item']);
        $sumItemLabel = trim((string) ($exportConfig['sum_item_label'] ?? '#'));
        if ($sumItemLabel === '') {
            $sumItemLabel = '#';
        }
        $sumShowDelegacion = !array_key_exists('sum_show_delegacion', $exportConfig) || !empty($exportConfig['sum_show_delegacion']);
        $sumDelegacionLabel = trim((string) ($exportConfig['sum_delegacion_label'] ?? 'Delegación'));
        if ($sumDelegacionLabel === '') {
            $sumDelegacionLabel = 'Delegación';
        }
        $sumShowCabecera = !array_key_exists('sum_show_cabecera', $exportConfig) || !empty($exportConfig['sum_show_cabecera']);
        $sumCabeceraLabel = trim((string) ($exportConfig['sum_cabecera_label'] ?? 'Cabecera'));
        if ($sumCabeceraLabel === '') {
            $sumCabeceraLabel = 'Cabecera';
        }
        $sumGroupColorHex = $this->cssColorToHex((string) ($exportConfig['sum_group_color'] ?? 'var(--clr-primary)'));
        $sumIncludeTotalsRow = !empty($exportConfig['include_sum_totals_row']);
        $includeTotalsTable = !empty($exportConfig['include_totals_table']);
        $totalsTableTitle = trim((string) ($exportConfig['totals_table_title'] ?? 'Totales'));
        if ($totalsTableTitle === '') {
            $totalsTableTitle = 'Totales';
        }
        $totalsTableAlign = strtolower((string) ($exportConfig['totals_table_align'] ?? 'left'));
        if (!in_array($totalsTableAlign, ['left', 'center', 'right'], true)) {
            $totalsTableAlign = 'left';
        }
        $sumTotalsBold = !array_key_exists('sum_totals_bold', $exportConfig) || !empty($exportConfig['sum_totals_bold']);
        $sumTotalsTextColorHex = $this->cssColorToHex((string) ($exportConfig['sum_totals_text_color'] ?? 'var(--clr-primary)'));
        $dataTableAlign = strtolower((string) ($exportConfig['table_align'] ?? 'left'));
        if (!in_array($dataTableAlign, ['left', 'center', 'right', 'stretch'], true)) {
            $dataTableAlign = 'left';
        }
        $sectionLabelRaw = trim((string) ($exportConfig['section_label'] ?? 'Desglose'));
        if ($sectionLabelRaw === '') {
            $sectionLabelRaw = 'Desglose';
        }
        $sectionLabelAlign = strtolower((string) ($exportConfig['section_label_align'] ?? 'left'));
        if (!in_array($sectionLabelAlign, ['left', 'center', 'right'], true)) {
            $sectionLabelAlign = 'left';
        }
        $sectionLabelJc = match ($sectionLabelAlign) {
            'center' => Jc::CENTER,
            'right' => Jc::END,
            default => Jc::START,
        };
        $sectionLabel = $this->normalizeExportHeading($sectionLabelRaw, $headersUppercase);
        $cellFontSizePx = $this->normalizeCellFontSizePx($exportConfig['records_cell_font_size_px'] ?? $exportConfig['cell_font_size_px'] ?? $exportConfig['cellFontPx'] ?? null);
        $cellFontSizePt = $this->cellPxToWordPt($cellFontSizePx);
        $countTableCellFontSizePx = $this->normalizeCellFontSizePx($exportConfig['count_table_font_px'] ?? $exportConfig['count_table_font_size_px'] ?? $exportConfig['countTableFontPx'] ?? 9);
        $countTableCellFontSizePt = $this->cellPxToWordPt($countTableCellFontSizePx);
        $legacyCountTableFont = $exportConfig['count_table_font_px'] ?? $exportConfig['count_table_font_size_px'] ?? $exportConfig['countTableFontPx'] ?? null;
        $pdfCountHeaderRaw = $exportConfig['count_table_header_font_size_px'] ?? $exportConfig['countTableHeaderFontPx'] ?? null;
        $pdfCountCellRaw = $exportConfig['count_table_cell_font_size_px'] ?? $exportConfig['countTableCellFontPx'] ?? null;
        $pdfCountHeaderPx = $pdfCountHeaderRaw !== null && $pdfCountHeaderRaw !== ''
            ? (int) $pdfCountHeaderRaw
            : ($legacyCountTableFont !== null && $legacyCountTableFont !== '' ? (int) $legacyCountTableFont : 8);
        $pdfCountCellPx = $pdfCountCellRaw !== null && $pdfCountCellRaw !== ''
            ? (int) $pdfCountCellRaw
            : ($legacyCountTableFont !== null && $legacyCountTableFont !== '' ? (int) $legacyCountTableFont : 10);
        $pdfCountHeaderPx = max(7, min(36, $pdfCountHeaderPx));
        $pdfCountCellPx = max(7, min(24, $pdfCountCellPx));
        $sumTableCellFontSizePx = $this->normalizeCellFontSizePx($exportConfig['sum_table_cell_font_size_px'] ?? $cellFontSizePx);
        $sumTableCellFontSizePt = $this->cellPxToWordPt($sumTableCellFontSizePx);
        $sumHeaderFontSizePx = max(9, min(28, (int) ($exportConfig['sum_table_header_font_size_px'] ?? $headerFontSizePx)));
        $sumHeaderFontSizePt = max(7, min(21, (int) round($sumHeaderFontSizePx * 0.75)));
        $recordsGroupHeaderFontSizePx = max(9, min(48, (int) ($exportConfig['records_group_header_font_size_px'] ?? $headerFontSizePx)));
        $recordsGroupHeaderFontSizePt = max(7, min(36, (int) round($recordsGroupHeaderFontSizePx * 0.75)));
        $sumGroupHeaderFontSizePx = max(9, min(48, (int) ($exportConfig['sum_group_header_font_size_px'] ?? $sumHeaderFontSizePx)));
        $sumGroupHeaderFontSizePt = max(7, min(36, (int) round($sumGroupHeaderFontSizePx * 0.75)));
        $totalsTableCellFontSizePx = $this->normalizeCellFontSizePx($exportConfig['totals_table_cell_font_size_px'] ?? $sumTableCellFontSizePx);
        $totalsTableCellFontSizePt = $this->cellPxToWordPt($totalsTableCellFontSizePx);
        $totalsHeaderFontSizePx = max(9, min(48, (int) ($exportConfig['totals_table_header_font_size_px'] ?? $sumHeaderFontSizePx)));
        $totalsHeaderFontSizePt = max(7, min(36, (int) round($totalsHeaderFontSizePx * 0.75)));
        $totalsGroupHeaderFontSizePx = max(9, min(48, (int) ($exportConfig['totals_group_header_font_size_px'] ?? $totalsHeaderFontSizePx)));
        $totalsGroupHeaderFontSizePt = max(7, min(36, (int) round($totalsGroupHeaderFontSizePx * 0.75)));
        $titleFontSizePx = max(10, min(36, (int) ($exportConfig['title_font_size_px'] ?? 18)));
        $titleFontSizePt = max(8, min(27, (int) round($titleFontSizePx * 0.75)));
        $exportFontName = $this->resolveExportFontName();
        $logoPath = public_path('images/LogoSegobHorizontal.png');
        $hasLogo = is_file($logoPath);
        $groupHeaderColors = $this->resolveGroupHeaderColorMap(is_array($exportConfig['groups'] ?? null) ? $exportConfig['groups'] : []);

        $columns = $this->transformExportColumns($columns, $headersUppercase);

        $colByKey = [];
        foreach ($columns as $c) {
            $kk = trim((string) ($c['key'] ?? ''));
            if ($kk !== '') {
                $colByKey[$kk] = $c;
            }
        }
        $rowHighlightStyles = $this->buildRowHighlightStyleRows($entries, $exportConfig, $columns, $colByKey, $fieldTypesByKey, $microrregionMeta, $headersUppercase);

        $paperPortraitWidthTwips = 12240;
        $paperPortraitHeightTwips = $paperSize === 'legal' ? 20160 : 15840;
        $marginTwips = $docMarginPreset === 'none' ? 0 : ($docMarginPreset === 'normal' ? 1134 : 720);
        $paperCurrentWidthTwips = $orientationConfig === 'landscape' ? $paperPortraitHeightTwips : $paperPortraitWidthTwips;
        $usableTableTwips = max(7200, $paperCurrentWidthTwips - ($marginTwips * 2));
        $columnWidthFractions = $this->computeColumnWidthFractions($columns);
        $columnTwips = $this->distributeTwipsFromFractions($columnWidthFractions, $usableTableTwips);

        $includeCountTable = !empty($exportConfig['include_count_table']);
        $includeSumTable = !empty($exportConfig['include_sum_table']);
        $countByFields = $includeCountTable && is_array($exportConfig['count_by_fields'] ?? null)
            ? array_values(array_filter(array_map('strval', $exportConfig['count_by_fields'])))
            : [];
        $countTableColors = is_array($exportConfig['count_table_colors'] ?? null) ? $exportConfig['count_table_colors'] : [];
        $sumGroupBy = (string) ($exportConfig['sum_group_by'] ?? 'microrregion');
        $sumMetrics = $includeSumTable && is_array($exportConfig['sum_metrics'] ?? null)
            ? array_values($exportConfig['sum_metrics'])
            : [];
        $sumFormulas = $includeSumTable && is_array($exportConfig['sum_formulas'] ?? null)
            ? array_values($exportConfig['sum_formulas'])
            : [];
        $countTable = null;
        $sumTable = null;
        $totalsTable = null;
        $fieldLabels = [];
        foreach ($columns as $column) {
            $fieldLabels[(string) ($column['key'] ?? '')] = (string) ($column['label'] ?? '');
        }
        if ($includeCountTable) {
            $countTable = $this->buildCountTableData($entries, $countByFields, $fieldLabels, $countTableColors);
            $countTable = $this->transformCountTableLabels($countTable, $headersUppercase);
        }
        if ($includeSumTable && $sumMetrics !== []) {
            $sumTable = $this->buildSumTableData(
                $entries,
                $sumMetrics,
                $sumFormulas,
                $sumGroupBy,
                $fieldLabels,
                $microrregionMeta,
            );
            $sumTable['include_totals_row'] = $sumIncludeTotalsRow;
            $sumTable['totals_bold'] = $sumTotalsBold;
            $sumTable['totals_text_color'] = $sumTotalsTextColorHex;
            if ($headersUppercase) {
                $sumTable['group_label'] = $this->normalizeExportHeading((string) ($sumTable['group_label'] ?? ''), true);
                if (is_array($sumTable['metric_labels'] ?? null)) {
                    foreach ($sumTable['metric_labels'] as $id => $label) {
                        $sumTable['metric_labels'][$id] = $this->normalizeExportHeading((string) $label, true);
                    }
                }
                if (is_array($sumTable['metric_columns'] ?? null)) {
                    foreach ($sumTable['metric_columns'] as $idx => $col) {
                        $sumTable['metric_columns'][$idx]['label'] = $this->normalizeExportHeading((string) ($col['label'] ?? ''), true);
                        $sumTable['metric_columns'][$idx]['group'] = $this->normalizeExportHeading((string) ($col['group'] ?? ''), true);
                    }
                }
                if (is_array($sumTable['formula_labels'] ?? null)) {
                    foreach ($sumTable['formula_labels'] as $id => $label) {
                        $sumTable['formula_labels'][$id] = $this->normalizeExportHeading((string) $label, true);
                    }
                }
                if (is_array($sumTable['formula_columns'] ?? null)) {
                    foreach ($sumTable['formula_columns'] as $idx => $col) {
                        $sumTable['formula_columns'][$idx]['label'] = $this->normalizeExportHeading((string) ($col['label'] ?? ''), true);
                        $sumTable['formula_columns'][$idx]['group'] = $this->normalizeExportHeading((string) ($col['group'] ?? ''), true);
                    }
                }
                if (is_array($sumTable['rows'] ?? null)) {
                    foreach ($sumTable['rows'] as $idx => $row) {
                        $sumTable['rows'][$idx]['group'] = $this->normalizeExportHeading((string) ($row['group'] ?? ''), true);
                    }
                }
            }
        }
        if ($sumTable !== null && $includeTotalsTable) {
            $totalsTable = $this->buildTotalsStandaloneTableData($sumTable);
        }

        if ($format === 'word') {
            $wordFileName = $this->buildStandardDocumentName($temporaryModule, 'docx');
            $fullPath = $exportDir.'/'.$wordFileName;

            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            $phpWord->setDefaultFontName($exportFontName);
            $phpWord->setDefaultFontSize(10);
            $orientation = $orientationConfig === 'landscape'
                ? \PhpOffice\PhpWord\Style\Section::ORIENTATION_LANDSCAPE
                : \PhpOffice\PhpWord\Style\Section::ORIENTATION_PORTRAIT;
            $section = $phpWord->addSection([
                'orientation' => $orientation,
                'pageSizeW' => $orientationConfig === 'landscape' ? $paperPortraitHeightTwips : $paperPortraitWidthTwips,
                'pageSizeH' => $orientationConfig === 'landscape' ? $paperPortraitWidthTwips : $paperPortraitHeightTwips,
                'marginTop' => $marginTwips,
                'marginBottom' => $marginTwips,
                'marginLeft' => $marginTwips,
                'marginRight' => $marginTwips,
            ]);

            $jc = match ($titleAlign) {
                'left' => \PhpOffice\PhpWord\SimpleType\Jc::START,
                'right' => \PhpOffice\PhpWord\SimpleType\Jc::END,
                default => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
            };

            if ($hasLogo) {
            }

            $fechaCorteStr = now()->format('d/m/Y H:i');
            // Header table: logo (full width) in row 1; title (full width, align center) in row 2; date (full width, align right) in row 3
            $hdrTbl = $section->addTable([
                'borderSize' => 0,
                'borderColor' => 'FFFFFF',
                'cellMarginTop' => 0,
                'cellMarginBottom' => 0,
                'cellMarginLeft' => 0,
                'cellMarginRight' => 0,
            ]);
            if ($hasLogo) {
                $hdrLogoRow = $hdrTbl->addRow(800);
                $hdrLogoCell = $hdrLogoRow->addCell($usableTableTwips, ['gridSpan' => 2, 'valign' => 'bottom', 'borderSize' => 0, 'borderColor' => 'FFFFFF']);
                $hdrLogoRun = $hdrLogoCell->addTextRun(['alignment' => Jc::START]);
                $hdrLogoRun->addImage($logoPath, ['height' => 52]);
            }

            $hdrTbl->addRow();
            $hdrTitleCell = $hdrTbl->addCell($usableTableTwips, ['gridSpan' => 2, 'borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMarginTop' => 80]);
            $hdrTitleCell->addText($title, ['name' => $exportFontName, 'bold' => true, 'size' => $titleFontSizePt, 'color' => '861E34'], ['alignment' => $jc, 'spaceAfter' => 40]);

            $hdrTbl->addRow();
            $hdrDateCell = $hdrTbl->addCell($usableTableTwips, ['gridSpan' => 2, 'valign' => 'bottom', 'borderSize' => 0, 'borderColor' => 'FFFFFF']);
            $hdrDateCell->addText('Fecha y hora de corte: '.$fechaCorteStr, ['name' => $exportFontName, 'size' => 9], ['alignment' => Jc::END, 'spaceAfter' => 120]);

            $section->addTextBreak(1);

            $hasSummarySections = false;

            if ($totalsTable !== null && !empty($totalsTable['columns'])) {
                $totalsTitleJc = Jc::CENTER;
                $totalsTitleText = $this->normalizeExportHeading($totalsTableTitle, $headersUppercase);
                $section->addTextBreak(3);
                $section->addText(
                    $totalsTitleText,
                    ['name' => $exportFontName, 'bold' => true, 'size' => $sumTitleFontSizePt],
                    ['spaceAfter' => 120, 'alignment' => $totalsTitleJc]
                );

                $totalsTbl = $section->addTable([
                    'borderSize' => 6,
                    'borderColor' => '444444',
                    'cellMargin' => 80,
                    'alignment' => $this->resolveWordTableAlignment('center'),
                ]);

                $totalsCols = is_array($totalsTable['columns'] ?? null) ? $totalsTable['columns'] : [];
                $totalsValues = is_array($totalsTable['values'] ?? null) ? $totalsTable['values'] : [];
                $totalsHasGroups = collect($totalsCols)->contains(fn ($c) => ((string) ($c['group'] ?? '')) !== '');

                if ($totalsHasGroups) {
                    $totalsTbl->addRow();
                    $totalsTbl->addCell(2200, ['bgColor' => $sumGroupColorHex, 'valign' => 'center']);
                    $spanCount = 0;
                    $spanGroup = null;
                    foreach ($totalsCols as $idx => $col) {
                        $grp = (string) ($col['group'] ?? '');
                        if ($spanGroup === null) {
                            $spanGroup = $grp;
                            $spanCount = 1;
                        } elseif ($grp === $spanGroup) {
                            $spanCount++;
                        } else {
                            $spanGroupKey = mb_strtolower(trim((string) $spanGroup), 'UTF-8');
                            $spanBg = trim((string) $spanGroup) !== '' ? ($groupHeaderColors[$spanGroupKey] ?? '64748B') : '334155';
                            $totalsTbl->addCell(1600 * $spanCount, ['gridSpan' => $spanCount, 'bgColor' => $spanBg, 'valign' => 'center'])
                                ->addText(trim($spanGroup) === '' ? '' : (string) $spanGroup, ['name' => $exportFontName, 'bold' => true, 'size' => $totalsGroupHeaderFontSizePt, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
                            $spanGroup = $grp;
                            $spanCount = 1;
                        }
                        if ($idx === count($totalsCols) - 1) {
                            $spanGroupKey = mb_strtolower(trim((string) $spanGroup), 'UTF-8');
                            $spanBg = trim((string) $spanGroup) !== '' ? ($groupHeaderColors[$spanGroupKey] ?? '64748B') : '334155';
                            $totalsTbl->addCell(1600 * $spanCount, ['gridSpan' => $spanCount, 'bgColor' => $spanBg, 'valign' => 'center'])
                                ->addText(trim((string) $spanGroup) === '' ? '' : (string) $spanGroup, ['name' => $exportFontName, 'bold' => true, 'size' => $totalsGroupHeaderFontSizePt, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
                        }
                    }
                }

                $totalsTbl->addRow();
                $totalsTbl->addCell(2200, ['bgColor' => $sumGroupColorHex, 'valign' => 'center'])
                    ->addText($this->normalizeExportHeading('Total', $headersUppercase), ['name' => $exportFontName, 'bold' => true, 'size' => $totalsHeaderFontSizePt, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
                foreach ($totalsCols as $col) {
                    $totalsGroup = trim((string) ($col['group'] ?? ''));
                    $totalsGroupKey = mb_strtolower($totalsGroup, 'UTF-8');
                    $totalsBg = $totalsGroup !== '' ? ($groupHeaderColors[$totalsGroupKey] ?? '64748B') : '475569';
                    $totalsTbl->addCell(1600, ['bgColor' => $totalsBg, 'valign' => 'center'])
                        ->addText((string) ($col['label'] ?? ''), ['name' => $exportFontName, 'bold' => true, 'size' => $totalsHeaderFontSizePt, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
                }

                $totalsTbl->addRow();
                $totalsTbl->addCell(2200, ['valign' => 'center'])
                    ->addText($this->normalizeExportHeading('Total', $headersUppercase), ['name' => $exportFontName, 'size' => $totalsTableCellFontSizePt, 'bold' => $sumTotalsBold, 'color' => $sumTotalsTextColorHex], ['alignment' => Jc::CENTER]);
                foreach ($totalsCols as $col) {
                    $id = (string) ($col['id'] ?? '');
                    $val = (float) ($totalsValues[$id] ?? 0.0);
                    $text = (string) round($val, 2);
                    if ((string) ($col['op'] ?? '') === 'percent') {
                        $text .= '%';
                    }
                    $totalsTbl->addCell(1600)->addText($text, ['name' => $exportFontName, 'size' => $totalsTableCellFontSizePt, 'bold' => $sumTotalsBold, 'color' => $sumTotalsTextColorHex], ['alignment' => Jc::CENTER]);
                }

                $section->addTextBreak(1);
                $hasSummarySections = true;

                if (($countTable !== null && isset($countTable['groups'])) || ($sumTable !== null && !empty($sumTable['rows']) && is_array($sumTable['rows']))) {
                    $section->addPageBreak();

                    $hdrTbl = $section->addTable([
                        'borderSize' => 0,
                        'borderColor' => 'FFFFFF',
                        'cellMarginTop' => 0,
                        'cellMarginBottom' => 0,
                        'cellMarginLeft' => 0,
                        'cellMarginRight' => 0,
                    ]);
                    if ($hasLogo) {
                        $hdrLogoRow = $hdrTbl->addRow(800);
                        $hdrLogoCell = $hdrLogoRow->addCell($usableTableTwips, ['gridSpan' => 2, 'valign' => 'bottom', 'borderSize' => 0, 'borderColor' => 'FFFFFF']);
                        $hdrLogoRun = $hdrLogoCell->addTextRun(['alignment' => Jc::START]);
                        $hdrLogoRun->addImage($logoPath, ['height' => 52]);
                    }

                    $hdrTbl->addRow();
                    $hdrTitleCell = $hdrTbl->addCell($usableTableTwips, ['gridSpan' => 2, 'borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMarginTop' => 80]);
                    $hdrTitleCell->addText($title, ['name' => $exportFontName, 'bold' => true, 'size' => $titleFontSizePt, 'color' => '861E34'], ['alignment' => $jc, 'spaceAfter' => 40]);

                    $hdrTbl->addRow();
                    $hdrDateCell = $hdrTbl->addCell($usableTableTwips, ['gridSpan' => 2, 'valign' => 'bottom', 'borderSize' => 0, 'borderColor' => 'FFFFFF']);
                    $hdrDateCell->addText('Fecha y hora de corte: '.$fechaCorteStr, ['name' => $exportFontName, 'size' => 9], ['alignment' => Jc::END, 'spaceAfter' => 120]);

                    $section->addTextBreak(1);
                }
            }

            if ($countTable !== null && isset($countTable['groups'])) {
                $countTblStyle = [
                    'borderSize' => 6,
                    'borderColor' => '444444',
                    'cellMargin' => 80,
                    'alignment' => $this->resolveWordTableAlignment($countTableAlign),
                ];
                $countTbl = $section->addTable($countTblStyle);
                $baseCountCellWidthCh = max(6, min(40, (int) ($exportConfig['count_table_cell_width'] ?? 12)));
                $chToTwips = static fn (int $ch): int => max(900, (int) round($ch * 120));
                $resolveCountColor = function ($key, int $rowNum, ?string $valueLabel = null) use ($countTableColors): string {
                    $c = $countTableColors[$key] ?? null;
                    if (is_string($c) && $c !== '') {
                        return $this->cssColorToHex($c);
                    }
                    if (is_array($c)) {
                        if ($rowNum === 1) {
                            return $this->cssColorToHex($c['row1'] ?? '#861e34');
                        }
                        if ($valueLabel !== null && ! empty($c['row2Values']) && is_array($c['row2Values'])) {
                            $hit = $this->resolveCountTableRow2MapValue($c['row2Values'], $valueLabel);
                            if ($hit !== null && $hit !== '') {
                                return $this->cssColorToHex((string) $hit);
                            }
                        }
                        return $this->cssColorToHex($c['row2'] ?? '#2d5a27');
                    }
                    return $rowNum === 1 ? '861E34' : '2d5a27';
                };
                $resolveCountWidth = function (string $key, ?string $valueLabel, bool $forPct = false) use ($countTableColors, $baseCountCellWidthCh, $chToTwips): int {
                    $widthCh = $baseCountCellWidthCh;
                    $cfg = $countTableColors[$key] ?? null;
                    if (is_array($cfg) && $valueLabel !== null && ! empty($cfg['row2Widths']) && is_array($cfg['row2Widths'])) {
                        $raw = $this->resolveCountTableRow2MapValue($cfg['row2Widths'], $valueLabel);
                        if ($raw !== null) {
                            $parsed = (int) $raw;
                            $widthCh = max(6, min(40, $parsed));
                        }
                    }
                    if ($forPct) {
                        $widthCh = max(6, (int) floor($widthCh * 0.7));
                    }
                    return $chToTwips($widthCh);
                };
                $countTbl->addRow();
                foreach ($countTable['groups'] as $gi => $group) {
                    $key = (string) ($group['color_key'] ?? ($gi === 0 ? '_total' : ($countByFields[$gi - 1] ?? '')));
                    $bgHex = $resolveCountColor($key, 1);
                    $includePct = !empty($countTableColors[$key]['showPct']);
                    $numValues = count($group['values']);
                    $span = $includePct ? $numValues * 2 : $numValues;
                    $isRedundant = ($gi === 0 || ($numValues === 1 && (trim((string)($group['values'][0]['label'] ?? '')) === '' || trim((string)($group['values'][0]['label'] ?? '')) === trim((string)($group['label'] ?? '')))));

                    $groupTwips = 0;
                    foreach ($group['values'] as $v) {
                        $subLabel = $v['label'] !== '' ? $v['label'] : $group['label'];
                        $groupTwips += $resolveCountWidth($key, $subLabel, false);
                        if ($includePct) { $groupTwips += $resolveCountWidth($key, $subLabel, true); }
                    }
                    if ($groupTwips <= 0) {
                        $groupTwips = $span * $chToTwips($baseCountCellWidthCh);
                    }

                    $cellStyle = ['gridSpan' => $span, 'bgColor' => $bgHex, 'valign' => 'center'];
                    if ($isRedundant && !$includePct) {
                        $cellStyle['vMerge'] = 'restart';
                    }

                    $cell = $countTbl->addCell($groupTwips, $cellStyle);
                    $cell->addText((string) $group['label'], ['name' => $exportFontName, 'bold' => true, 'size' => $countTableCellFontSizePt, 'color' => 'FFFFFF'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                }
                $countTbl->addRow();
                foreach ($countTable['groups'] as $gi => $group) {
                    $key = (string) ($group['color_key'] ?? ($gi === 0 ? '_total' : ($countByFields[$gi - 1] ?? '')));
                    $includePct = !empty($countTableColors[$key]['showPct']);

                    foreach ($group['values'] as $v) {
                        $subLabel = $v['label'] !== '' ? $v['label'] : $group['label'];
                        $bgHex = $resolveCountColor($key, 2, $subLabel);

                        $isRedundant = ($gi === 0 || (count($group['values']) === 1 && (trim((string)$v['label']) === '' || trim((string)$v['label']) === trim((string)$group['label']))));
                        $cellTwips = $resolveCountWidth($key, $subLabel, false);
                        $pctTwips = $resolveCountWidth($key, $subLabel, true);

                        if ($includePct) {
                            $countTbl->addCell($cellTwips, ['bgColor' => $bgHex, 'valign' => 'center'])->addText($isRedundant ? 'Cantidad' : (string) $subLabel, ['name' => $exportFontName, 'bold' => true, 'size' => $countTableCellFontSizePt, 'color' => 'FFFFFF'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                            $countTbl->addCell($pctTwips, ['bgColor' => $bgHex, 'valign' => 'center'])->addText('%', ['name' => $exportFontName, 'bold' => true, 'size' => $countTableCellFontSizePt, 'color' => 'FFFFFF'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                        } else {
                            if ($isRedundant) {
                                $countTbl->addCell($cellTwips, ['bgColor' => $bgHex, 'vMerge' => 'continue']);
                            } else {
                                $countTbl->addCell($cellTwips, ['bgColor' => $bgHex, 'valign' => 'center'])->addText((string)$subLabel, ['name' => $exportFontName, 'bold' => true, 'size' => $countTableCellFontSizePt, 'color' => 'FFFFFF'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                            }
                        }
                    }
                }
                $countTbl->addRow();
                foreach ($countTable['groups'] as $gi => $group) {
                    $key = (string) ($group['color_key'] ?? ($gi === 0 ? '_total' : ($countByFields[$gi - 1] ?? '')));
                    $includePct = !empty($countTableColors[$key]['showPct']);
                    $gTotal = array_sum(array_column($group['values'], 'count'));

                    foreach ($group['values'] as $v) {
                        $subLabel = $v['label'] !== '' ? $v['label'] : $group['label'];
                        $cellTwips = $resolveCountWidth($key, $subLabel, false);
                        $pctTwips = $resolveCountWidth($key, $subLabel, true);
                        $countTbl->addCell($cellTwips, ['valign' => 'center'])->addText((string) $v['count'], ['name' => $exportFontName, 'size' => $countTableCellFontSizePt, 'color' => 'c00000'], ['alignment' => Jc::CENTER]);
                        if ($includePct) {
                            $pct = $gTotal > 0 ? round(($v['count'] / $gTotal) * 100, 2) : 0;
                            $countTbl->addCell($pctTwips, ['valign' => 'center'])->addText($pct . '%', ['name' => $exportFontName, 'size' => $countTableCellFontSizePt, 'color' => 'c00000'], ['alignment' => Jc::CENTER]);
                        }
                    }
                }
                $section->addTextBreak(1);
                $hasSummarySections = true;

                if ($sumTable !== null && !empty($sumTable['rows']) && is_array($sumTable['rows'])) {
                    $section->addPageBreak();
                    $sumHeaderCols = 1 + count((array) ($sumTable['metric_columns'] ?? [])) + count((array) ($sumTable['formula_columns'] ?? []));
                    if ($sumHeaderCols <= 1) {
                        $sumHeaderCols = 1 + count((array) ($sumTable['metric_labels'] ?? [])) + count((array) ($sumTable['formula_labels'] ?? []));
                    }
                    $sumHeaderRows = count((array) ($sumTable['rows'] ?? []));
                    $sumHeaderDensity = $sumHeaderRows + (int) ceil(max(2, $sumHeaderCols) * 1.8) + ($orientationConfig === 'landscape' ? 4 : 0);
                    $sumHeaderLogoHeight = $sumHeaderDensity >= 34 ? 28 : 34;
                    $sumHeaderTitlePt = max(11, min($titleFontSizePt, $sumHeaderDensity >= 34 ? 13 : 14));
                    $sumHeaderDatePt = $sumHeaderDensity >= 34 ? 8 : 9;

                    $hdrTbl = $section->addTable([
                        'borderSize' => 0,
                        'borderColor' => 'FFFFFF',
                        'cellMarginTop' => 0,
                        'cellMarginBottom' => 0,
                        'cellMarginLeft' => 0,
                        'cellMarginRight' => 0,
                    ]);
                    if ($hasLogo) {
                        $hdrLogoRow = $hdrTbl->addRow(800);
                        $hdrLogoCell = $hdrLogoRow->addCell($usableTableTwips, ['gridSpan' => 2, 'valign' => 'bottom', 'borderSize' => 0, 'borderColor' => 'FFFFFF']);
                        $hdrLogoRun = $hdrLogoCell->addTextRun(['alignment' => Jc::START]);
                        $hdrLogoRun->addImage($logoPath, ['height' => $sumHeaderLogoHeight]);
                    }

                    $hdrTbl->addRow();
                    $hdrTitleCell = $hdrTbl->addCell($usableTableTwips, ['gridSpan' => 2, 'borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMarginTop' => 40]);
                    $hdrTitleCell->addText($title, ['name' => $exportFontName, 'bold' => true, 'size' => $sumHeaderTitlePt, 'color' => '861E34'], ['alignment' => $jc, 'spaceAfter' => 30]);

                    $hdrTbl->addRow();
                    $hdrDateCell = $hdrTbl->addCell($usableTableTwips, ['gridSpan' => 2, 'valign' => 'bottom', 'borderSize' => 0, 'borderColor' => 'FFFFFF']);
                    $hdrDateCell->addText('Fecha y hora de corte: '.$fechaCorteStr, ['name' => $exportFontName, 'size' => $sumHeaderDatePt], ['alignment' => Jc::END, 'spaceAfter' => 80]);

                    $section->addTextBreak(1);
                }
            }

            if ($sumTable !== null && !empty($sumTable['rows']) && is_array($sumTable['rows'])) {
                $sumGroupLabel = (string) ($sumTable['group_label'] ?? 'Grupo');
                $sumMetricColumns = is_array($sumTable['metric_columns'] ?? null) ? $sumTable['metric_columns'] : [];
                $sumFormulaColumns = is_array($sumTable['formula_columns'] ?? null) ? $sumTable['formula_columns'] : [];
                $sumMetricLabels = [];
                foreach ($sumMetricColumns as $col) {
                    $sumMetricLabels[(string) ($col['id'] ?? '')] = (string) ($col['label'] ?? '');
                }
                if ($sumMetricLabels === []) {
                    $sumMetricLabels = is_array($sumTable['metric_labels'] ?? null) ? $sumTable['metric_labels'] : [];
                }
                $sumFormulaLabels = [];
                foreach ($sumFormulaColumns as $col) {
                    $sumFormulaLabels[(string) ($col['id'] ?? '')] = (string) ($col['label'] ?? '');
                }
                if ($sumFormulaLabels === []) {
                    $sumFormulaLabels = is_array($sumTable['formula_labels'] ?? null) ? $sumTable['formula_labels'] : [];
                }
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
                $sumRows = $sumTable['rows'];
                $sumGroupByCurrent = (string) ($sumTable['group_by'] ?? $sumGroupBy);
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
                $sumRowCount = count($sumRows);
                $sumLeadCount = count($sumLeadCols);
                $sumColumnCount = max(2, count($sumCombinedCols) + $sumLeadCount);
                $sumDensityScore = $sumRowCount + (int) ceil($sumColumnCount * 1.8) + ($orientationConfig === 'landscape' ? 4 : 0);
                $sumCompactLogoHeight = $sumDensityScore >= 34 ? 28 : 34;
                $sumCompactTitlePt = max(11, min($titleFontSizePt, $sumDensityScore >= 34 ? 13 : 14));
                $sumCompactDatePt = $sumDensityScore >= 34 ? 8 : 9;
                $sumHeadingPt = max(10, min($sumTitleFontSizePt, $sumDensityScore >= 34 ? 11 : 12));
                $sumHeaderCellPt = max(7, $sumHeaderFontSizePt - ($sumDensityScore >= 34 ? 3 : 2));
                $sumGroupHeaderCellPt = max(7, $sumGroupHeaderFontSizePt - ($sumDensityScore >= 34 ? 3 : 2));
                $sumCellPt = max(7, $sumTableCellFontSizePt - ($sumDensityScore >= 34 ? 2 : 1));
                $sumCellMarginTwips = $sumDensityScore >= 34 ? 30 : 50;
                $sumLeadColTwips = max(900, min(2200, (int) round($usableTableTwips * 0.16)));
                $sumLeadColsTwipsTotal = $sumLeadColTwips * max(1, $sumLeadCount);
                $sumDataColTwips = max(620, (int) floor(max(1200, $usableTableTwips - $sumLeadColsTwipsTotal) / max(1, count($sumCombinedCols))));

                $sumTitleText = $this->normalizeExportHeading($sumTitle, $headersUppercase);
                if ($sumTitleCase === 'upper') {
                    $sumTitleText = mb_strtoupper($sumTitleText);
                } elseif ($sumTitleCase === 'lower') {
                    $sumTitleText = mb_strtolower($sumTitleText);
                    $first = mb_substr($sumTitleText, 0, 1, 'UTF-8');
                    $rest = mb_substr($sumTitleText, 1, null, 'UTF-8');
                    $sumTitleText = mb_strtoupper($first, 'UTF-8').$rest;
                }
                $sumTitleJc = match ($sumTitleAlign) {
                    'left' => Jc::START,
                    'right' => Jc::END,
                    default => Jc::CENTER,
                };

                $section->addText(
                    $sumTitleText.' '.$this->normalizeExportHeading('por '.$sumGroupLabel, $headersUppercase),
                    ['name' => $exportFontName, 'bold' => true, 'size' => $sumHeadingPt],
                    ['spaceAfter' => 90, 'alignment' => $sumTitleJc]
                );

                $sumTbl = $section->addTable([
                    'borderSize' => 6,
                    'borderColor' => '444444',
                    'cellMargin' => $sumCellMarginTwips,
                    'alignment' => $this->resolveWordTableAlignment($sumTableAlign),
                ]);
                if ($hasSumGroupHeaders) {
                    $sumTbl->addRow();
                    for ($leadIdx = 0; $leadIdx < $sumLeadCount; $leadIdx++) {
                        $sumTbl->addCell($sumLeadColTwips, ['bgColor' => $sumGroupColorHex, 'valign' => 'center']);
                    }
                    $spanCount = 0;
                    $spanGroup = null;
                    foreach ($sumCombinedCols as $idx => $col) {
                        $grp = (string) ($col['group'] ?? '');
                        if ($spanGroup === null) {
                            $spanGroup = $grp;
                            $spanCount = 1;
                        } elseif ($grp === $spanGroup) {
                            $spanCount++;
                        } else {
                            $spanGroupKey = mb_strtolower(trim((string) $spanGroup), 'UTF-8');
                            $spanBg = trim((string) $spanGroup) !== '' ? ($groupHeaderColors[$spanGroupKey] ?? '64748B') : '334155';
                            $sumTbl->addCell($sumDataColTwips * $spanCount, ['gridSpan' => $spanCount, 'bgColor' => $spanBg, 'valign' => 'center'])
                                ->addText(trim($spanGroup) === '' ? '' : (string) $spanGroup, ['name' => $exportFontName, 'bold' => true, 'size' => $sumGroupHeaderCellPt, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
                            $spanGroup = $grp;
                            $spanCount = 1;
                        }
                        if ($idx === count($sumCombinedCols) - 1) {
                            $spanGroupKey = mb_strtolower(trim((string) $spanGroup), 'UTF-8');
                            $spanBg = trim((string) $spanGroup) !== '' ? ($groupHeaderColors[$spanGroupKey] ?? '64748B') : '334155';
                            $sumTbl->addCell($sumDataColTwips * $spanCount, ['gridSpan' => $spanCount, 'bgColor' => $spanBg, 'valign' => 'center'])
                                ->addText(trim((string) $spanGroup) === '' ? '' : (string) $spanGroup, ['name' => $exportFontName, 'bold' => true, 'size' => $sumGroupHeaderCellPt, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
                        }
                    }
                }

                $sumTbl->addRow();
                foreach ($sumLeadCols as $leadCol) {
                    $sumTbl->addCell($sumLeadColTwips, ['bgColor' => $sumGroupColorHex, 'valign' => 'center'])
                        ->addText((string) ($leadCol['label'] ?? ''), ['name' => $exportFontName, 'bold' => true, 'size' => $sumHeaderCellPt, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
                }
                foreach ($sumCombinedCols as $col) {
                    $sumColGroup = trim((string) ($col['group'] ?? ''));
                    $sumColGroupKey = mb_strtolower($sumColGroup, 'UTF-8');
                    $sumColBg = $sumColGroup !== '' ? ($groupHeaderColors[$sumColGroupKey] ?? '64748B') : '475569';
                    $sumTbl->addCell($sumDataColTwips, ['bgColor' => $sumColBg, 'valign' => 'center'])
                        ->addText((string) ($col['label'] ?? ''), ['name' => $exportFontName, 'bold' => true, 'size' => $sumHeaderCellPt, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
                }

                foreach ($sumRows as $rowIndex => $row) {
                    $sumTbl->addRow();
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
                        $sumTbl->addCell($sumLeadColTwips, ['valign' => 'center'])->addText($leadText, ['name' => $exportFontName, 'size' => $sumCellPt], ['alignment' => Jc::CENTER]);
                    }

                    foreach ($sumCombinedCols as $col) {
                        $id = (string) ($col['id'] ?? '');
                        if ((string) ($col['op'] ?? 'metric') === 'metric') {
                            $v = (float) (($row['metrics'][$id] ?? 0.0));
                            $sumTbl->addCell($sumDataColTwips)->addText((string) round($v, 2), ['name' => $exportFontName, 'size' => $sumCellPt], ['alignment' => Jc::CENTER]);
                        } else {
                            $v = (float) (($row['formulas'][$id] ?? 0.0));
                            $text = (string) round($v, 2);
                            if ((string) ($col['op'] ?? '') === 'percent') {
                                $text .= '%';
                            }
                            $sumTbl->addCell($sumDataColTwips)->addText($text, ['name' => $exportFontName, 'size' => $sumCellPt], ['alignment' => Jc::CENTER]);
                        }
                    }
                }

                if (!empty($sumTable['include_totals_row'])) {
                    $sumTotalsBoldCfg = !array_key_exists('totals_bold', $sumTable) || !empty($sumTable['totals_bold']);
                    $sumTotalsTextColorCfg = (string) ($sumTable['totals_text_color'] ?? '861E34');
                    $sumTbl->addRow();
                    foreach ($sumLeadCols as $leadIdx => $leadCol) {
                        $sumTbl->addCell($sumLeadColTwips, ['valign' => 'center'])->addText(
                            $leadIdx === 0 ? $this->normalizeExportHeading('Total', $headersUppercase) : '',
                            ['name' => $exportFontName, 'size' => $sumCellPt, 'bold' => $sumTotalsBoldCfg, 'color' => $sumTotalsTextColorCfg],
                            ['alignment' => Jc::CENTER]
                        );
                    }

                    foreach ($sumCombinedCols as $col) {
                        $includeTotal = !array_key_exists('include_total', $col) || !empty($col['include_total']);
                        if (!$includeTotal) {
                            $sumTbl->addCell($sumDataColTwips)->addText('');
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

                        $text = (string) round($total, 2);
                        if ($op === 'percent') {
                            $text .= '%';
                        }

                        $sumTbl->addCell($sumDataColTwips)->addText(
                            $text,
                            ['name' => $exportFontName, 'size' => $sumCellPt, 'bold' => $sumTotalsBoldCfg, 'color' => $sumTotalsTextColorCfg],
                            ['alignment' => Jc::CENTER]
                        );
                    }
                }

                $section->addTextBreak(1);
                $hasSummarySections = true;
            }

            if ($hasSummarySections) {
                $section->addPageBreak();

                $hdrTbl = $section->addTable([
                    'borderSize' => 0,
                    'borderColor' => 'FFFFFF',
                    'cellMarginTop' => 0,
                    'cellMarginBottom' => 0,
                    'cellMarginLeft' => 0,
                    'cellMarginRight' => 0,
                ]);
                if ($hasLogo) {
                    $hdrLogoRow = $hdrTbl->addRow(800);
                    $hdrLogoCell = $hdrLogoRow->addCell($usableTableTwips, ['gridSpan' => 2, 'valign' => 'bottom', 'borderSize' => 0, 'borderColor' => 'FFFFFF']);
                    $hdrLogoRun = $hdrLogoCell->addTextRun(['alignment' => Jc::START]);
                    $hdrLogoRun->addImage($logoPath, ['height' => 52]);
                }

                $hdrTbl->addRow();
                $hdrTitleCell = $hdrTbl->addCell($usableTableTwips, ['gridSpan' => 2, 'borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMarginTop' => 80]);
                $hdrTitleCell->addText($title, ['name' => $exportFontName, 'bold' => true, 'size' => $titleFontSizePt, 'color' => '861E34'], ['alignment' => $jc, 'spaceAfter' => 40]);

                $hdrTbl->addRow();
                $hdrDateCell = $hdrTbl->addCell($usableTableTwips, ['gridSpan' => 2, 'valign' => 'bottom', 'borderSize' => 0, 'borderColor' => 'FFFFFF']);
                $hdrDateCell->addText('Fecha y hora de corte: '.$fechaCorteStr, ['name' => $exportFontName, 'size' => 9], ['alignment' => Jc::END, 'spaceAfter' => 120]);

                $section->addTextBreak(1);
                $section->addText($sectionLabel, ['name' => $exportFontName, 'bold' => true, 'size' => 11], ['spaceAfter' => 120, 'alignment' => $sectionLabelJc]);
            }

            $tblStyle = [
                'borderSize' => 6,
                'borderColor' => '444444',
                'cellMargin' => 80,
            ];

            if ($stretch) {
                $tblStyle['width'] = 100;
                $tblStyle['unit'] = 'pct';
            }

            $table = $section->addTable($tblStyle);

            // Encabezados con Grupos
            $groupSpans = [];
            foreach ($columns as $col) {
                $g = $col['group'] ?? '';
                if (!empty($groupSpans) && $groupSpans[count($groupSpans) - 1]['label'] === $g) {
                    $groupSpans[count($groupSpans) - 1]['span']++;
                } else {
                    $groupKey = mb_strtolower(trim((string) $g), 'UTF-8');
                    $groupSpans[] = ['label' => $g, 'span' => 1, 'color' => ($groupHeaderColors[$groupKey] ?? '64748B')];
                }
            }

            $hasAnyGroup = false;
            foreach ($groupSpans as $gs) { if ($gs['label'] !== '') $hasAnyGroup = true; }

            if ($hasAnyGroup) {
                $table->addRow();
                $gColIdx = 0;
                foreach ($groupSpans as $gs) {
                    $span = (int) $gs['span'];
                    $mergedTwips = 0;
                    for ($si = 0; $si < $span; $si++) {
                        $mergedTwips += $columnTwips[$gColIdx + $si] ?? 0;
                    }
                    $gColIdx += $span;
                    if ($gs['label'] !== '') {
                        $table->addCell($mergedTwips > 0 ? $mergedTwips : null, [
                            'gridSpan' => $gs['span'],
                            'bgColor' => (string) ($gs['color'] ?? '64748B'),
                            'valign' => 'center'
                        ])->addText((string) $gs['label'], ['name' => $exportFontName, 'bold' => true, 'size' => $recordsGroupHeaderFontSizePt, 'color' => 'FFFFFF'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                    } else {
                        $table->addCell($mergedTwips > 0 ? $mergedTwips : null, [
                            'gridSpan' => $gs['span'],
                            'valign' => 'center'
                        ]);
                    }
                }
            }

            $table->addRow();
            foreach ($columns as $idx => $col) {
                // Determinar color de fondo para encabezados dinámicos
                $groupName = trim((string) ($col['group'] ?? ''));
                if ($groupName !== '') {
                    $groupKey = mb_strtolower($groupName, 'UTF-8');
                    $bgIdx = $groupHeaderColors[$groupKey] ?? '64748B';
                } else {
                    $bgIdx = $this->getColumnBgColor($col, $idx);
                }
                $w = $columnTwips[$idx] ?? null;
                $headerCell = $table->addCell($w, ['bgColor' => $bgIdx, 'valign' => 'center']);
                $this->addWordMultilineText(
                    $headerCell,
                    (string) ($col['label'] ?? ''),
                    ['name' => $exportFontName, 'bold' => true, 'size' => $headerFontSizePt, 'color' => 'FFFFFF'],
                    ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
                );
            }

            // Filas
            $itemNumber = 1;
            $rowHighlightEntryIdx = 0;
            foreach ($entries as $entry) {
                $rh = $rowHighlightStyles[$rowHighlightEntryIdx] ?? ['bg_css' => '', 'text_css' => ''];
                // Ajustar alto de fila para que la imagen quede dentro de la celda.
                $hasImageInRow = false;
                foreach ($columns as $c) {
                    $k = (string) ($c['key'] ?? '');
                    if ($k === '' || in_array($k, ['item', 'microrregion', 'delegacion_numero', 'cabecera_microrregion'], true)) {
                        continue;
                    }
                    $v = $entry->data[$k] ?? null;
                    $t = (string) ($fieldTypesByKey[$k] ?? '');
                    if (count($this->resolveImageAbsolutePaths($v, $t)) > 0) {
                        $hasImageInRow = true;
                        break;
                    }
                }
                $rowHeightTwips = $hasImageInRow ? $this->pxToTwips(72 + 12) : null;
                $table->addRow($rowHeightTwips);
                foreach ($columns as $idx => $col) {
                    $key = $col['key'];
                    $isContentBold = !empty($col['content_bold']);
                    if (str_starts_with((string) $key, '__calc_')) {
                        $calcConfig = is_array($col['_calc_config'] ?? null) ? $col['_calc_config'] : [];
                        $text = $this->buildOperationsCellText((array) ($entry->data ?? []), $columns, $calcConfig, $headersUppercase);
                        $w = $columnTwips[$idx] ?? null;
                        $bdOps = $this->breakdownWordDataCellVisual($col, $text);
                        $visOps = $this->mergeWordRowBreakdownCellVisual($bdOps, (string) ($rh['bg_css'] ?? ''), (string) ($rh['text_css'] ?? ''));
                        $cellOptsOps = ['valign' => 'center'];
                        if ($visOps['bg'] !== null) {
                            $cellOptsOps['bgColor'] = $visOps['bg'];
                        }
                        $fontOps = ['name' => $exportFontName, 'size' => $cellFontSizePt, 'bold' => $isContentBold];
                        if ($visOps['text'] !== null) {
                            $fontOps['color'] = $visOps['text'];
                        }
                        $opsCell = $table->addCell($w, $cellOptsOps);
                        $this->addWordMultilineText($opsCell, $text, $fontOps, ['alignment' => Jc::CENTER]);
                        continue;
                    }
                    $fieldType = (string) ($fieldTypesByKey[$key] ?? '');
                    if ($key === 'item') {
                        $text = (string) $itemNumber;
                        $itemNumber++;
                        $w = $columnTwips[$idx] ?? null;
                        $bd0 = $this->breakdownWordDataCellVisual($col, $text);
                        $vis0 = $this->mergeWordRowBreakdownCellVisual($bd0, (string) ($rh['bg_css'] ?? ''), (string) ($rh['text_css'] ?? ''));
                        $co0 = ['valign' => 'center'];
                        if ($vis0['bg'] !== null) {
                            $co0['bgColor'] = $vis0['bg'];
                        }
                        $f0 = ['name' => $exportFontName, 'size' => $cellFontSizePt, 'bold' => $isContentBold];
                        if ($vis0['text'] !== null) {
                            $f0['color'] = $vis0['text'];
                        }
                        $table->addCell($w, $co0)->addText($text, $f0, ['alignment' => Jc::CENTER]);
                        continue;
                    }

                    if ($key === 'microrregion') {
                        $meta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));
                        $text = (string) ($meta['label'] ?? $meta->label ?? '');
                        $w = $columnTwips[$idx] ?? null;
                        $bd0 = $this->breakdownWordDataCellVisual($col, $text);
                        $vis0 = $this->mergeWordRowBreakdownCellVisual($bd0, (string) ($rh['bg_css'] ?? ''), (string) ($rh['text_css'] ?? ''));
                        $co0 = ['valign' => 'center'];
                        if ($vis0['bg'] !== null) {
                            $co0['bgColor'] = $vis0['bg'];
                        }
                        $f0 = ['name' => $exportFontName, 'size' => $cellFontSizePt, 'bold' => $isContentBold];
                        if ($vis0['text'] !== null) {
                            $f0['color'] = $vis0['text'];
                        }
                        $table->addCell($w, $co0)->addText($text, $f0, ['alignment' => Jc::CENTER]);
                        continue;
                    }
                    if ($key === 'delegacion_numero') {
                        $meta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));
                        $text = (string) ($meta['number'] ?? $meta->number ?? '');
                        $w = $columnTwips[$idx] ?? null;
                        $bd0 = $this->breakdownWordDataCellVisual($col, $text);
                        $vis0 = $this->mergeWordRowBreakdownCellVisual($bd0, (string) ($rh['bg_css'] ?? ''), (string) ($rh['text_css'] ?? ''));
                        $co0 = ['valign' => 'center'];
                        if ($vis0['bg'] !== null) {
                            $co0['bgColor'] = $vis0['bg'];
                        }
                        $f0 = ['name' => $exportFontName, 'size' => $cellFontSizePt, 'bold' => $isContentBold];
                        if ($vis0['text'] !== null) {
                            $f0['color'] = $vis0['text'];
                        }
                        $table->addCell($w, $co0)->addText($text, $f0, ['alignment' => Jc::CENTER]);
                        continue;
                    }
                    if ($key === 'cabecera_microrregion') {
                        $meta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));
                        $text = (string) ($meta['cabecera'] ?? $meta->cabecera ?? '');
                        $w = $columnTwips[$idx] ?? null;
                        $bd0 = $this->breakdownWordDataCellVisual($col, $text);
                        $vis0 = $this->mergeWordRowBreakdownCellVisual($bd0, (string) ($rh['bg_css'] ?? ''), (string) ($rh['text_css'] ?? ''));
                        $co0 = ['valign' => 'center'];
                        if ($vis0['bg'] !== null) {
                            $co0['bgColor'] = $vis0['bg'];
                        }
                        $f0 = ['name' => $exportFontName, 'size' => $cellFontSizePt, 'bold' => $isContentBold];
                        if ($vis0['text'] !== null) {
                            $f0['color'] = $vis0['text'];
                        }
                        $table->addCell($w, $co0)->addText($text, $f0, ['alignment' => Jc::CENTER]);
                        continue;
                    }

                    $val = $entry->data[$key] ?? null;
                    $val = $this->applyColumnEmptyFillValue($val, $col, $fieldType);
                    $imagePaths = $this->resolveImageAbsolutePaths($val, $fieldType);
                    if ($imagePaths !== []) {
                        $w = $columnTwips[$idx] ?? null;
                        $bdImg = $this->breakdownWordDataCellVisual($col, '');
                        $visImg = $this->mergeWordRowBreakdownCellVisual($bdImg, (string) ($rh['bg_css'] ?? ''), (string) ($rh['text_css'] ?? ''));
                        $imgCellOpts = ['valign' => 'center'];
                        if ($visImg['bg'] !== null) {
                            $imgCellOpts['bgColor'] = $visImg['bg'];
                        }
                        $imgCell = $table->addCell($w, $imgCellOpts);
                        if (count($imagePaths) === 1) {
                            $imgCell->addImage($imagePaths[0], [
                                'height' => 72,
                                'alignment' => Jc::CENTER,
                            ]);
                        } else {
                            // Keep both images in a single visual block inside the same cell.
                            $imageRun = $imgCell->addTextRun(['alignment' => Jc::CENTER]);
                            foreach ($imagePaths as $imageIndex => $imagePath) {
                                $imageRun->addImage($imagePath, ['height' => 52]);
                                if ($imageIndex < count($imagePaths) - 1) {
                                    $imageRun->addText('  ');
                                }
                            }
                        }
                    } else {
                        if (is_bool($val)) {
                            $text = $val ? 'Sí' : 'No';
                        } elseif (is_array($val)) {
                            if (array_key_exists('primary', $val) || array_key_exists('secondary', $val)) {
                                $p = $val['primary'] ?? '';
                                $text = is_scalar($p) ? (string) $p : '';
                            } else {
                                $text = implode(', ', array_map(static fn ($v) => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), $val));
                            }
                        } elseif (is_scalar($val)) {
                            $text = (string) $val;
                            if ($fieldType === 'semaforo' && trim($text) !== '') {
                                $text = TemporaryModuleFieldService::labelForSemaforo($text) ?: $text;
                            }
                        } else {
                            $text = '';
                        }
                        $w = $columnTwips[$idx] ?? null;
                        $bd = $this->breakdownWordDataCellVisual($col, $text);
                        $vis = $this->mergeWordRowBreakdownCellVisual($bd, (string) ($rh['bg_css'] ?? ''), (string) ($rh['text_css'] ?? ''));
                        $cellOpts = ['valign' => 'center'];
                        if ($vis['bg'] !== null) {
                            $cellOpts['bgColor'] = $vis['bg'];
                        }
                        $font = ['name' => $exportFontName, 'size' => $cellFontSizePt, 'bold' => $isContentBold];
                        if ($vis['text'] !== null) {
                            $font['color'] = $vis['text'];
                        }
                        $table->addCell($w, $cellOpts)->addText($text, $font, ['alignment' => Jc::CENTER]);
                    }
                }
                $rowHighlightEntryIdx++;
            }

            \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($fullPath);

            return [
                'name' => $wordFileName,
                'url' => route('temporary-modules.admin.exports.download', ['file' => $wordFileName])
            ];
        }

        // PDF
        $pdfFileName = $this->buildStandardDocumentName($temporaryModule, 'pdf');
        $fullPdfPath = $exportDir.'/'.$pdfFileName;

        $fechaCorteStr = now()->format('d/m/Y H:i');

        $countTableColorKeys = $countTable !== null && isset($countTable['groups'])
            ? array_merge(['_total'], $countByFields)
            : [];
        $countTableColors = is_array($exportConfig['count_table_colors'] ?? null) ? $exportConfig['count_table_colors'] : [];
        $countTableCellWidth = max(6, min(40, (int) ($exportConfig['count_table_cell_width'] ?? 12)));
        $columnWidthPercents = $this->fractionsToPercents($columnWidthFractions);
        $imageColumnsCount = $this->countPdfImageColumns($columns, $fieldTypesByKey);
        $shouldPreloadPdfImages = $this->shouldPreloadPdfImageData($entries->count(), $imageColumnsCount);
        $pdfImageDataByPath = $shouldPreloadPdfImages
            ? $this->buildPdfImageDataByPath($entries, $columns, $fieldTypesByKey)
            : [];

        $html = view('temporary_modules.admin.partials.export_pdf_table', [
            'title' => $title,
            'titleAlign' => $titleAlign,
            'headersUppercase' => $headersUppercase,
            'countTableAlign' => $countTableAlign,
            'sumTableAlign' => $sumTableAlign,
            'sumTitle' => $sumTitle,
            'sumTitleCase' => $sumTitleCase,
            'sumTitleAlign' => $sumTitleAlign,
            'sumTitleFontSizePx' => $sumTitleFontSizePx,
            'sumShowItem' => $sumShowItem,
            'sumItemLabel' => $this->normalizeExportHeading($sumItemLabel, $headersUppercase),
            'sumShowDelegacion' => $sumShowDelegacion,
            'sumDelegacionLabel' => $this->normalizeExportHeading($sumDelegacionLabel, $headersUppercase),
            'sumShowCabecera' => $sumShowCabecera,
            'sumCabeceraLabel' => $this->normalizeExportHeading($sumCabeceraLabel, $headersUppercase),
            'sumGroupColor' => '#'.$sumGroupColorHex,
            'sumIncludeTotalsRow' => $sumIncludeTotalsRow,
            'sumTotalsBold' => $sumTotalsBold,
            'sumTotalsTextColor' => '#'.$sumTotalsTextColorHex,
            'includeTotalsTable' => $includeTotalsTable,
            'totalsTableTitle' => $totalsTableTitle,
            'totalsTableAlign' => $totalsTableAlign,
            'totalsTable' => $totalsTable,
            'tableAlign' => $dataTableAlign,
            'sectionLabel' => $sectionLabel,
            'sectionLabelAlign' => $sectionLabelAlign,
            'fontFamily' => $exportFontName,
            'cellFontSizePx' => $cellFontSizePx,
            'headerFontSizePx' => $headerFontSizePx,
            'sumTableCellFontSizePx' => $sumTableCellFontSizePx,
            'sumTableHeaderFontSizePx' => $sumHeaderFontSizePx,
            'recordsGroupHeaderFontSizePx' => $recordsGroupHeaderFontSizePx,
            'sumGroupHeaderFontSizePx' => $sumGroupHeaderFontSizePx,
            'totalsTableCellFontSizePx' => $totalsTableCellFontSizePx,
            'totalsTableHeaderFontSizePx' => $totalsHeaderFontSizePx,
            'totalsGroupHeaderFontSizePx' => $totalsGroupHeaderFontSizePx,
            'titleFontSizePx' => $titleFontSizePx,
            'logoDataUri' => $this->buildLogoDataUri($logoPath),
            'fechaCorteStr' => $fechaCorteStr,
            'orientation' => $orientationConfig,
            'docMarginPreset' => $docMarginPreset,
            'columns' => $columns,
            'groupHeaderColors' => array_map(static fn (string $hex): string => '#'.$hex, $groupHeaderColors),
            'columnWidthPercents' => $columnWidthPercents,
            'entries' => $entries,
            'microrregionMeta' => $microrregionMeta,
            'stretch' => $stretch,
            'countTable' => $countTable,
            'sumTable' => $sumTable,
            'countTableColorKeys' => $countTableColorKeys,
            'countTableColors' => $countTableColors,
            'countTableCellWidth' => $countTableCellWidth,
            'countTableHeaderFontSizePx' => $pdfCountHeaderPx,
            'countTableCellFontSizePx' => $pdfCountCellPx,
            'fieldTypesByKey' => $fieldTypesByKey,
            'pdfImageDataByPath' => $pdfImageDataByPath,
            'rowHighlightStyles' => $rowHighlightStyles,
        ])->render();

        $dompdf = new Dompdf([
            'defaultPaperSize' => $paperSize,
            'isRemoteEnabled' => true,
            'defaultFont' => strtolower($exportFontName) === 'gilroy' ? 'gilroy' : 'Arial',
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paperSize, $orientationConfig === 'landscape' ? 'landscape' : 'portrait');
        $dompdf->render();
        file_put_contents($fullPdfPath, $dompdf->output());

        return [
            'name' => $pdfFileName,
            'url' => route('temporary-modules.admin.exports.download', ['file' => $pdfFileName])
        ];
    }

    /**
     * @param Collection $entries entries with 'data'
     * @param array<string> $countByFields
     * @param array<string, string> $fieldLabels key => label
     * @param array<string, mixed> $countTableColors key => personalization config
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
            $includeSR = !array_key_exists('showSR', $fieldCfg) || !empty($fieldCfg['showSR']);
            $includeValuesCfg = is_array($fieldCfg['includeValues'] ?? null) ? $fieldCfg['includeValues'] : [];
            $valueCounts = [];
            $labelByLower = [];
            $sinRespuestaCount = 0;
            foreach ($entries as $entry) {
                $val = $entry->data[$fieldKey] ?? null;
                if (is_array($val) && ! isset($val['primary'])) {
                    $hasAnyValue = false;
                    foreach ($val as $item) {
                        $key = is_scalar($item) ? trim((string) $item) : '';
                        if ($key !== '') {
                            $hasAnyValue = true;
                            $lower = mb_strtolower($key);
                            $valueCounts[$lower] = ($valueCounts[$lower] ?? 0) + 1;
                            if (! isset($labelByLower[$lower])) {
                                $labelByLower[$lower] = $key;
                            }
                        }
                    }
                    if (! $hasAnyValue) {
                        $sinRespuestaCount++;
                    }
                    continue;
                }
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

    private function normalizeExportHeading(string $text, bool $uppercase = false): string
    {
        $text = trim($text);

        return $uppercase && $text !== '' ? mb_strtoupper($text, 'UTF-8') : $text;
    }

    private function resolveWordTableAlignment(string $align): string
    {
        return match (strtolower(trim($align))) {
            'center' => JcTable::CENTER,
            'right' => JcTable::END,
            default => JcTable::START,
        };
    }

    private function normalizeSummaryText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'si' : 'no';
        }
        $txt = mb_strtolower(trim((string) $value), 'UTF-8');

        return $this->foldSummaryMatchAccents($txt);
    }

    private function foldSummaryMatchAccents(string $text): string
    {
        if ($text === '') {
            return '';
        }
        if (class_exists(\Normalizer::class)) {
            $n = \Normalizer::normalize($text, \Normalizer::FORM_D);
            if (is_string($n)) {
                $text = $n;
            }
        }

        return preg_replace('/\p{M}/u', '', $text) ?? $text;
    }

    /**
     * @param  array<string|int, mixed>  $map
     */
    private function resolveCountTableRow2MapValue(array $map, ?string $valueLabel): mixed
    {
        if ($valueLabel === null) {
            return null;
        }
        $trimmed = trim($valueLabel);
        if ($trimmed === '') {
            return null;
        }
        if (array_key_exists($trimmed, $map)) {
            return $map[$trimmed];
        }
        $needle = $this->foldSummaryMatchAccents(mb_strtolower($trimmed, 'UTF-8'));
        foreach ($map as $k => $v) {
            if (! is_string($k) && ! is_int($k)) {
                continue;
            }
            $kStr = trim((string) $k);
            if ($kStr === '') {
                continue;
            }
            if ($this->foldSummaryMatchAccents(mb_strtolower($kStr, 'UTF-8')) === $needle) {
                return $v;
            }
        }

        return null;
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

        if (!is_numeric($raw)) {
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
            $metricIds = array_values(array_filter(array_map('strval', (array) ($formula['metric_ids'] ?? []))));
            $sortOrder = (int) ($formula['sort_order'] ?? 0);
            $includeTotal = !array_key_exists('include_total', $formula) || !empty($formula['include_total']);
            $formulaLabels[$id] = $label;
            $formulaColumns[] = ['id' => $id, 'label' => $label, 'group' => $group, 'op' => $op, 'base_metric_id' => $baseMetricId, 'metric_ids' => $metricIds, 'include_total' => $includeTotal, 'sort_order' => $sortOrder];
        }

        $rowsByKey = [];
        $orderedKeys = [];
        foreach ($entries as $entry) {
            $entryData = (array) ($entry->data ?? []);
            $meta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));
            $mrNumber = (string) ($meta['number'] ?? $meta->number ?? '');
            $mrCabecera = (string) ($meta['cabecera'] ?? $meta->cabecera ?? '');
            if ($groupBy === 'municipio') {
                $groupLabel = $this->resolveMunicipioGroupLabel($entryData, $fieldLabels);
            } else {
                $groupLabel = (string) (($meta['label'] ?? null) ?: 'Sin microrregión');
            }
            $groupKey = $groupBy.':'.$groupLabel;
            if (!isset($rowsByKey[$groupKey])) {
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
                    if (is_array($raw) && !isset($raw['primary'])) {
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
                    if (is_array($raw) && !isset($raw['primary'])) {
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
                        if (is_array($raw) && !isset($raw['primary'])) {
                            foreach ($raw as $part) {
                                if ($this->normalizeSummaryText($part) === $target) {
                                    $matched = true;
                                    break;
                                }
                            }
                        } elseif (is_array($raw) && isset($raw['primary'])) {
                            $matched = $this->normalizeSummaryText($raw['primary'] ?? null) === $target;
                        } else {
                            $matched = $this->normalizeSummaryText($raw) === $target;
                        }
                        if ($matched) {
                            $rowsByKey[$groupKey]['metrics'][$metricId] += 1;
                        }
                    } else {
                        if (!isset($rowsByKey[$groupKey]['_unique_sets'])) {
                            $rowsByKey[$groupKey]['_unique_sets'] = [];
                        }
                        if (!isset($rowsByKey[$groupKey]['_unique_sets'][$metricId])) {
                            $rowsByKey[$groupKey]['_unique_sets'][$metricId] = [];
                        }
                        $pushUnique = function (mixed $value) use (&$rowsByKey, $groupKey, $metricId): void {
                            $key = $this->normalizeSummaryText($value);
                            if ($key !== '') {
                                $rowsByKey[$groupKey]['_unique_sets'][$metricId][$key] = true;
                            }
                        };
                        if (is_array($raw) && !isset($raw['primary'])) {
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

    /**
     * @param array<string,mixed> $sumTable
     * @return array<string,mixed>|null
     */
    private function buildTotalsStandaloneTableData(array $sumTable): ?array
    {
        $rows = is_array($sumTable['rows'] ?? null) ? $sumTable['rows'] : [];
        if ($rows === []) {
            return null;
        }

        $metricColumns = is_array($sumTable['metric_columns'] ?? null) ? $sumTable['metric_columns'] : [];
        $formulaColumns = is_array($sumTable['formula_columns'] ?? null) ? $sumTable['formula_columns'] : [];
        $metricLabels = is_array($sumTable['metric_labels'] ?? null) ? $sumTable['metric_labels'] : [];
        $formulaLabels = is_array($sumTable['formula_labels'] ?? null) ? $sumTable['formula_labels'] : [];

        $columns = [];
        foreach ($metricColumns as $col) {
            $columns[] = [
                'id' => (string) ($col['id'] ?? ''),
                'label' => (string) ($col['label'] ?? ''),
                'group' => trim((string) ($col['group'] ?? '')),
                'op' => 'metric',
                'include_total' => !array_key_exists('include_total', $col) || !empty($col['include_total']),
                'sort_order' => (int) ($col['sort_order'] ?? 0),
            ];
        }
        if ($columns === [] && $metricLabels !== []) {
            foreach ($metricLabels as $id => $label) {
                $columns[] = ['id' => (string) $id, 'label' => (string) $label, 'group' => '', 'op' => 'metric', 'include_total' => true, 'sort_order' => 0];
            }
        }
        foreach ($formulaColumns as $col) {
            $columns[] = [
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
        if ($formulaColumns === [] && $formulaLabels !== []) {
            foreach ($formulaLabels as $id => $label) {
                $columns[] = ['id' => (string) $id, 'label' => (string) $label, 'group' => '', 'op' => 'add', 'include_total' => true, 'sort_order' => 0];
            }
        }

        if ($columns === []) {
            return null;
        }

        usort($columns, static function (array $a, array $b): int {
            $sa = (int) ($a['sort_order'] ?? 0);
            $sb = (int) ($b['sort_order'] ?? 0);
            if ($sa !== $sb) {
                if ($sa === 0) return 1;
                if ($sb === 0) return -1;
                return $sa <=> $sb;
            }
            return 0;
        });

        $values = [];
        foreach ($columns as $col) {
            $includeTotal = !array_key_exists('include_total', $col) || !empty($col['include_total']);
            if (!$includeTotal) {
                $values[(string) ($col['id'] ?? '')] = 0.0;
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
                    foreach ($rows as $row) {
                        $numeratorTotal += (float) (($row['metrics'][$numeratorMetricId] ?? 0.0));
                        $baseTotal += (float) (($row['metrics'][$baseMetricId] ?? 0.0));
                    }
                }
                $total = $baseTotal !== 0.0 ? (($numeratorTotal / $baseTotal) * 100.0) : 0.0;
            } else {
                foreach ($rows as $row) {
                    if ($op === 'metric') {
                        $total += (float) (($row['metrics'][$id] ?? 0.0));
                    } else {
                        $total += (float) (($row['formulas'][$id] ?? 0.0));
                    }
                }
            }

            $values[$id] = $total;
        }

        return [
            'columns' => $columns,
            'values' => $values,
        ];
    }

    private function isOperationsValueEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_array($value)) {
            if (array_key_exists('primary', $value)) {
                return $this->isOperationsValueEmpty($value['primary']);
            }

            if ($value === []) {
                return true;
            }

            foreach ($value as $item) {
                if (! $this->isOperationsValueEmpty($item)) {
                    return false;
                }
            }

            return true;
        }

        if (is_object($value)) {
            return $this->isOperationsValueEmpty((array) $value);
        }

        return trim((string) $value) === '';
    }

    /**
     * @param array<string,mixed> $entryData
     * @param array<int,array<string,mixed>> $columns
     * @param array<string,mixed> $operationsConfig
     */
    private function buildOperationsCellText(array $entryData, array $columns, array $operationsConfig, bool $headersUppercase = false): string
    {
        $selectedKeys = is_array($operationsConfig['operation_fields'] ?? null)
            ? array_values(array_filter(array_map('strval', $operationsConfig['operation_fields']), static fn (string $key): bool => $key !== ''))
            : array_values(array_filter(
                array_map('strval', (array) ($operationsConfig['fields'] ?? [])),
                static fn (string $key): bool => $key !== ''
            ));

        if ($selectedKeys === []) {
            foreach ($columns as $column) {
                $colKey = (string) ($column['key'] ?? '');
                if ($colKey === '' || in_array($colKey, ['item', 'microrregion', 'delegacion_numero', 'cabecera_microrregion'], true) || str_starts_with($colKey, '__calc_')) {
                    continue;
                }
                $selectedKeys[] = $colKey;
            }
        }

        $operation = strtolower(trim((string) ($operationsConfig['operation'] ?? 'add')));
        if (!in_array($operation, ['add', 'subtract', 'multiply', 'percent'], true)) {
            $operation = !array_key_exists('include_percent', $operationsConfig) || !empty($operationsConfig['include_percent'])
                ? 'percent'
                : 'add';
        }
        $baseKey = trim((string) ($operationsConfig['base_field'] ?? $operationsConfig['reference_field'] ?? ''));
        $baseNum = $baseKey !== '' ? $this->extractOperationsNumeric($entryData[$baseKey] ?? null) : null;
        $baseValue = $baseNum ?? 0.0;

        $operationValues = [];
        foreach ($selectedKeys as $selectedKey) {
            if ($selectedKey === '' || $selectedKey === $baseKey) {
                continue;
            }
            $num = $this->extractOperationsNumeric($entryData[$selectedKey] ?? null);
            if ($num !== null) {
                $operationValues[] = $num;
            }
        }

        $result = null;
        if ($operation === 'add') {
            $result = $baseValue + array_sum($operationValues);
        } elseif ($operation === 'subtract') {
            $result = $baseValue - array_sum($operationValues);
        } elseif ($operation === 'multiply') {
            $product = $operationValues === [] ? 1.0 : array_reduce($operationValues, static fn (float $acc, float $n): float => $acc * $n, 1.0);
            $result = $baseValue * $product;
        } elseif ($operation === 'percent') {
            $numerator = array_sum($operationValues);
            $result = $baseValue !== 0.0 ? (($numerator / $baseValue) * 100.0) : 0.0;
        }

        if ($result === null || !is_finite($result)) {
            return '';
        }

        $rounded = round($result, 2);
        $text = rtrim(rtrim(sprintf('%.2f', $rounded), '0'), '.');
        if ($text === '-0') {
            $text = '0';
        }

        return $operation === 'percent' ? ($text.'%') : $text;
    }

    private function extractOperationsNumeric(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            if (array_key_exists('primary', $value)) {
                return $this->parseSummaryNumber($value['primary']);
            }

            $sum = 0.0;
            $hasAny = false;
            foreach ($value as $item) {
                $n = $this->parseSummaryNumber($item);
                if ($n !== null) {
                    $sum += $n;
                    $hasAny = true;
                }
            }

            return $hasAny ? $sum : null;
        }

        if (is_object($value)) {
            $arr = (array) $value;
            if (array_key_exists('primary', $arr)) {
                return $this->parseSummaryNumber($arr['primary']);
            }

            return $this->extractOperationsNumeric($arr);
        }

        return $this->parseSummaryNumber($value);
    }

    private function isEmptyExportValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_array($value)) {
            if ($value === []) {
                return true;
            }
            foreach ($value as $item) {
                if (! $this->isEmptyExportValue($item)) {
                    return false;
                }
            }

            return true;
        }
        if (is_object($value)) {
            $arr = (array) $value;
            if (array_key_exists('primary', $arr)) {
                return $this->isEmptyExportValue($arr['primary']);
            }

            return $this->isEmptyExportValue($arr);
        }

        return trim((string) $value) === '';
    }

    private function isNumericExportFieldType(string $fieldType): bool
    {
        $t = strtolower(trim($fieldType));
        if ($t === '') {
            return false;
        }

        foreach (['number', 'numeric', 'int', 'integer', 'decimal', 'float', 'double'] as $token) {
            if (str_contains($t, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $column
     */
    private function applyColumnEmptyFillValue(mixed $value, array $column, string $fieldType): mixed
    {
        $key = (string) ($column['key'] ?? '');
        if (in_array($key, ['item', 'microrregion', 'delegacion_numero', 'cabecera_microrregion'], true)) {
            return $value;
        }
        if (in_array(strtolower(trim($fieldType)), ['image', 'file', 'document', 'foto'], true)) {
            return $value;
        }
        if (! $this->isEmptyExportValue($value)) {
            return $value;
        }

        $mode = strtolower(trim((string) ($column['fill_empty_mode'] ?? 'none')));
        if (! in_array($mode, ['auto', 'custom'], true)) {
            return $value;
        }

        if ($mode === 'custom') {
            return (string) ($column['fill_empty_value'] ?? '');
        }

        return $this->isNumericExportFieldType($fieldType) ? 0 : 'S/R';
    }

    /**
     * Texto mostrado en export para una sola columna (coherente con PDF/Word).
     *
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<string, array<string, mixed>>  $colByKey
     * @param  array<string, string>  $fieldTypesByKey
     */
    private function buildSingleColumnDisplayTextForExport(
        object $entry,
        string $key,
        array $columns,
        array $colByKey,
        array $fieldTypesByKey,
        $microrregionMeta,
        int $itemNumber,
        bool $headersUppercase,
    ): string {
        if (str_starts_with($key, '__calc_')) {
            foreach ($columns as $c) {
                if ((string) ($c['key'] ?? '') === $key) {
                    $calc = is_array($c['_calc_config'] ?? null) ? $c['_calc_config'] : [];

                    return $this->buildOperationsCellText((array) ($entry->data ?? []), $columns, $calc, $headersUppercase);
                }
            }

            return '';
        }
        if ($key === 'item') {
            return (string) $itemNumber;
        }
        if ($key === 'microrregion') {
            $meta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));

            return (string) ($meta['label'] ?? $meta->label ?? '');
        }
        if ($key === 'delegacion_numero') {
            $meta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));

            return (string) ($meta['number'] ?? $meta->number ?? '');
        }
        if ($key === 'cabecera_microrregion') {
            $meta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));

            return (string) ($meta['cabecera'] ?? $meta->cabecera ?? '');
        }

        $col = $colByKey[$key] ?? ['key' => $key];
        $fieldType = (string) ($fieldTypesByKey[$key] ?? '');
        if (in_array(strtolower($fieldType), ['image', 'file', 'document', 'foto'], true)) {
            return '';
        }

        $val = $entry->data[$key] ?? null;
        $val = $this->applyColumnEmptyFillValue($val, $col, $fieldType);
        if (is_bool($val)) {
            return $val ? 'Sí' : 'No';
        }
        if (is_array($val)) {
            if (array_key_exists('primary', $val) || array_key_exists('secondary', $val)) {
                $p = $val['primary'] ?? '';

                return is_scalar($p) ? (string) $p : '';
            }

            return implode(', ', array_map(static fn ($v) => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), $val));
        }
        if (is_scalar($val)) {
            $text = (string) $val;
            if ($fieldType === 'semaforo' && trim($text) !== '') {
                $text = TemporaryModuleFieldService::labelForSemaforo($text) ?: $text;
            }

            return $text;
        }

        return '';
    }

    /**
     * @return list<array{bg_css: string, text_css: string}>
     */
    private function buildRowHighlightStyleRows(
        Collection $entries,
        array $exportConfig,
        array $columns,
        array $colByKey,
        array $fieldTypesByKey,
        $microrregionMeta,
        bool $headersUppercase,
    ): array {
        $enabled = !empty($exportConfig['row_highlight_enabled']);
        $driverKey = trim((string) ($exportConfig['row_highlight_column_key'] ?? ''));
        if (! $enabled || $driverKey === '') {
            $n = $entries->count();

            return array_fill(0, $n, ['bg_css' => '', 'text_css' => '']);
        }
        $fills = is_array($exportConfig['row_highlight_answer_fills'] ?? null) ? $exportConfig['row_highlight_answer_fills'] : [];
        $textCss = trim((string) ($exportConfig['row_highlight_text_color'] ?? ''));
        $rows = [];
        $itemNumber = 1;
        foreach ($entries as $entry) {
            $plain = $this->buildSingleColumnDisplayTextForExport(
                $entry,
                $driverKey,
                $columns,
                $colByKey,
                $fieldTypesByKey,
                $microrregionMeta,
                $itemNumber,
                $headersUppercase,
            );
            $hit = $this->resolveBreakdownAnswerFillsMap($fills, $plain);
            $bgCss = ($hit !== null && trim((string) $hit) !== '') ? trim((string) $hit) : '';
            $txt = ($bgCss !== '' && $textCss !== '') ? $textCss : '';
            $rows[] = ['bg_css' => $bgCss, 'text_css' => $txt];
            $itemNumber++;
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $fontStyle
     * @param array<string,mixed> $paragraphStyle
     */
    private function addWordMultilineText($cell, string $text, array $fontStyle = [], array $paragraphStyle = []): void
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $normalized);
        $run = $cell->addTextRun($paragraphStyle);

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $run->addTextBreak();
            }
            $run->addText($line, $fontStyle);
        }
    }

    private function transformExportColumns(array $columns, bool $uppercase = false): array
    {
        if (! $uppercase) {
            return $columns;
        }

        foreach ($columns as &$column) {
            if (isset($column['label'])) {
                $column['label'] = $this->normalizeExportHeading((string) $column['label'], true);
            }
            if (isset($column['group']) && (string) $column['group'] !== '') {
                $column['group'] = $this->normalizeExportHeading((string) $column['group'], true);
            }
        }
        unset($column);

        return $columns;
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

    private function cssColorToHex(string $color): string
    {
        $color = trim($color);
        if (preg_match('/^#([0-9A-Fa-f]{6})$/', $color, $m)) {
            return strtoupper($m[1]);
        }
        if (preg_match('/^#([0-9A-Fa-f]{8})$/', $color, $m)) {
            return strtoupper(substr($m[1], -6));
        }
        if (preg_match('/^#([0-9A-Fa-f]{3})$/', $color, $m)) {
            $r = str_repeat($m[1][0], 2);
            $g = str_repeat($m[1][1], 2);
            $b = str_repeat($m[1][2], 2);
            return strtoupper($r.$g.$b);
        }
        if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(?:\d*\.?\d+))?\s*\)$/i', $color, $m)) {
            $r = max(0, min(255, (int) $m[1]));
            $g = max(0, min(255, (int) $m[2]));
            $b = max(0, min(255, (int) $m[3]));

            return strtoupper(sprintf('%02X%02X%02X', $r, $g, $b));
        }
        $map = [
            'var(--clr-primary)' => '861E34',
            'var(--clr-secondary)' => '246257',
            'var(--clr-accent)' => 'C79B66',
            'var(--clr-text-main)' => '484747',
            'var(--clr-text-light)' => '6B6A6A',
            'var(--clr-bg)' => 'F7F7F8',
            'var(--clr-card)' => 'FFFFFF',
        ];
        return $map[$color] ?? '861E34';
    }

    private function foldBreakdownMatchAccents(string $text): string
    {
        if ($text === '') {
            return '';
        }
        if (class_exists(\Normalizer::class)) {
            $n = \Normalizer::normalize($text, \Normalizer::FORM_D);
            if (is_string($n)) {
                $text = $n;
            }
        }

        return preg_replace('/\p{M}/u', '', $text) ?? $text;
    }

    /**
     * @param  array<string|int, mixed>  $map
     */
    private function resolveBreakdownAnswerFillsMap(array $map, ?string $valueLabel): mixed
    {
        if ($valueLabel === null) {
            return null;
        }
        $trimmed = trim($valueLabel);
        if ($trimmed === '') {
            return null;
        }
        if (array_key_exists($trimmed, $map)) {
            return $map[$trimmed];
        }
        $needle = $this->foldBreakdownMatchAccents(mb_strtolower($trimmed, 'UTF-8'));
        foreach ($map as $k => $v) {
            if (! is_string($k) && ! is_int($k)) {
                continue;
            }
            $kStr = trim((string) $k);
            if ($kStr === '') {
                continue;
            }
            if ($this->foldBreakdownMatchAccents(mb_strtolower($kStr, 'UTF-8')) === $needle) {
                return $v;
            }
        }

        return null;
    }

    /**
     * @return array{bg: ?string, text: ?string} Hex RGB sin # para PhpWord
     */
    private function breakdownWordDataCellVisual(array $col, string $displayText): array
    {
        $out = ['bg' => null, 'text' => null];
        $fills = is_array($col['breakdown_answer_fills'] ?? null) ? $col['breakdown_answer_fills'] : [];
        $hit = $this->resolveBreakdownAnswerFillsMap($fills, $displayText);
        if ($hit !== null && trim((string) $hit) !== '') {
            $out['bg'] = $this->cssColorToHex((string) $hit);
            $textCss = trim((string) ($col['breakdown_data_text_color'] ?? ''));
            if ($textCss !== '') {
                $out['text'] = $this->cssColorToHex($textCss);
            }
        }

        return $out;
    }

    /**
     * @param  array{bg: ?string, text: ?string}  $bd
     * @return array{bg: ?string, text: ?string}
     */
    private function mergeWordRowBreakdownCellVisual(array $bd, string $rowBgCss, string $rowTextCss): array
    {
        if ($bd['bg'] !== null) {
            return ['bg' => $bd['bg'], 'text' => $bd['text']];
        }
        $rowBgCss = trim($rowBgCss);
        $rowTextCss = trim($rowTextCss);
        if ($rowBgCss === '') {
            return ['bg' => null, 'text' => null];
        }

        return [
            'bg' => $this->cssColorToHex($rowBgCss),
            'text' => $rowTextCss !== '' ? $this->cssColorToHex($rowTextCss) : null,
        ];
    }

    /**
     * @param array<int,mixed> $groups
     * @return array<string,string> lower group name => HEX (sin #)
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
                $map[mb_strtolower($name, 'UTF-8')] = '64748B';
                continue;
            }
            if (! is_array($group)) {
                continue;
            }
            $name = trim((string) ($group['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $map[mb_strtolower($name, 'UTF-8')] = $this->cssColorToHex((string) ($group['color'] ?? '#64748B'));
        }

        return $map;
    }

    private function getColumnBgColor(array $col, int $idx): string
    {
        // En este servicio no siempre tenemos column_config_by_key, pero podemos buscar en el config de la columna
        if (isset($col['color']) && is_string($col['color']) && $col['color'] !== '') {
            return $this->cssColorToHex($col['color']);
        }
        // Default color institucional
        return '861E34';
    }

    /**
     * Pesos alineados con la vista previa / Excel: max_width_chars (2–60) o valores por defecto por columna.
     *
     * @param list<array{key?: string, max_width_chars?: int|null}> $columns
     * @return list<float> Fracciones que suman 1.0
     */
    private function computeColumnWidthFractions(array $columns): array
    {
        $weights = [];
        foreach ($columns as $col) {
            $key = (string) ($col['key'] ?? '');
            $mw = $col['max_width_chars'] ?? null;
            if ($mw !== null && is_numeric($mw)) {
                $w = max(2, min((int) $mw, 60));
            } else {
                $w = match ($key) {
                    'item' => 4,
                    'microrregion' => 18,
                    'municipio' => 20,
                    'estatus' => 12,
                    default => 24,
                };
            }
            $weights[] = (float) $w;
        }
        $sum = array_sum($weights);
        $n = count($columns);
        if ($sum <= 0 || $n === 0) {
            return array_fill(0, $n, $n > 0 ? 1.0 / $n : 1.0);
        }
        $fractions = [];
        foreach ($weights as $w) {
            $fractions[] = $w / $sum;
        }

        return $fractions;
    }

    /**
     * @param list<float> $fractions
     * @return list<int>
     */
    private function distributeTwipsFromFractions(array $fractions, int $totalTwips): array
    {
        $n = count($fractions);
        if ($n === 0) {
            return [];
        }
        $twips = [];
        $allocated = 0;
        for ($i = 0; $i < $n; $i++) {
            if ($i === $n - 1) {
                $twips[] = max(0, $totalTwips - $allocated);
            } else {
                $t = (int) round($totalTwips * $fractions[$i]);
                $twips[] = $t;
                $allocated += $t;
            }
        }

        return $twips;
    }

    /**
     * @param list<float> $fractions
     * @return list<float> Porcentajes que suman 100
     */
    private function fractionsToPercents(array $fractions): array
    {
        $percents = array_map(static fn (float $f) => round(100.0 * $f, 4), $fractions);
        $sum = array_sum($percents);
        if ($sum > 0 && abs($sum - 100.0) > 0.0001 && $percents !== []) {
            $percents[count($percents) - 1] += (100.0 - $sum);
        }

        return $percents;
    }

    private function buildLogoDataUri(string $logoPath): ?string
    {
        if (!is_file($logoPath)) {
            return null;
        }

        $binary = @file_get_contents($logoPath);
        if ($binary === false || $binary === '') {
            return null;
        }

        $mime = @mime_content_type($logoPath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    private function normalizeCellFontSizePx(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 12;
        }

        return max(9, min(24, (int) $value));
    }

    private function cellPxToWordPt(int $px): int
    {
        return max(7, min(18, (int) round($px * 0.75)));
    }

    private function pxToTwips(int $px): int
    {
        return max(0, (int) round($px * 15));
    }

    private function resolveExportFontName(): string
    {
        $hasGilroy = false;

        foreach ([
            storage_path('fonts'),
            resource_path('fonts/Fuente Gilroy'),
            resource_path('fonts'),
        ] as $fontDir) {
            if (!is_dir($fontDir)) {
                continue;
            }

            $files = @scandir($fontDir) ?: [];
            foreach ($files as $file) {
                $name = mb_strtolower((string) $file);
                if (str_contains($name, 'gilroy') && str_ends_with($name, '.ttf')) {
                    $hasGilroy = true;
                    break 2;
                }
            }
        }

        return $hasGilroy ? 'Gilroy' : 'Arial';
    }

    private function resolveImageAbsolutePaths(mixed $value, string $fieldType): array
    {
        $rawPaths = is_array($value) ? array_filter($value) : ($value ? [(string) $value] : []);
        $resolved = [];

        foreach ($rawPaths as $path) {
            if (!is_string($path) || trim($path) === '') continue;
            $path = trim($path);

            if (!$this->isImageTypeOrValue($fieldType, $path)) continue;

            if (filter_var($path, FILTER_VALIDATE_URL)) {
                $localFromUrl = $this->tryResolveLocalPathFromUrl($path);
                if ($localFromUrl !== null) {
                    $resolved[] = $localFromUrl;
                }
                $downloaded = $this->tryDownloadImageFromSameHostUrl($path);
                if ($downloaded !== null) {
                    $resolved[] = $downloaded;
                }
                continue;
            }

            $fullPath = $this->entryDataService->resolveStoredFilePath($path);
            if (is_string($fullPath) && is_file($fullPath)) {
                $resolved[] = $fullPath;
            }
        }

        return $resolved;
    }

    private function isImageTypeOrValue(string $fieldType, string $value): bool
    {
        $type = strtolower(trim($fieldType));
        if ($type !== '' && in_array($type, ['image', 'file_image', 'image_upload'], true)) {
            return true;
        }

        $path = strtolower((string) parse_url($value, PHP_URL_PATH));
        if ($path !== '' && preg_match('/\.(png|jpe?g|gif|webp|bmp|svg)$/i', $path) === 1) {
            return true;
        }

        if (str_starts_with(strtolower($value), 'data:image/')) {
            return true;
        }

        $fullPath = $this->entryDataService->resolveStoredFilePath($value);
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

        // Casos típicos: storage/temporary-modules/... o temporary-modules/...
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8) ?: '';
        }

        if ($path === '') {
            return null;
        }

        $resolved = $this->entryDataService->resolveStoredFilePath($path);
        return (is_string($resolved) && is_file($resolved)) ? $resolved : null;
    }

    private function tryDownloadImageFromSameHostUrl(string $url): ?string
    {
        $appUrl = (string) config('app.url');
        $appHost = (string) (parse_url($appUrl, PHP_URL_HOST) ?? '');
        $urlHost = (string) (parse_url($url, PHP_URL_HOST) ?? '');

        if ($appHost === '' || $urlHost === '' || strcasecmp($appHost, $urlHost) !== 0) {
            return null;
        }

        $ctx = stream_context_create([
            'http' => ['timeout' => 6, 'follow_location' => 1],
            'https' => ['timeout' => 6, 'follow_location' => 1],
        ]);
        $bin = @file_get_contents($url, false, $ctx);
        if (!is_string($bin) || $bin === '') {
            return null;
        }

        $tmpRel = 'temporary-exports/tmp/'.Str::random(18).'.img';
        Storage::disk('local')->put($tmpRel, $bin);
        $tmpAbs = storage_path('app/'.$tmpRel);
        return is_file($tmpAbs) ? $tmpAbs : null;
    }

    private function buildPdfImageDataByPath(Collection $entries, array $columns, array $fieldTypesByKey): array
    {
        $map = [];
        $payloadByOriginal = [];
        $payloadByUrl = [];
        $payloadByFullPath = [];
        $resolvedLocalByUrl = [];

        foreach ($entries as $entry) {
            foreach ($columns as $column) {
                $key = (string) ($column['key'] ?? '');
                if ($key === '' || in_array($key, ['item', 'microrregion', 'delegacion_numero', 'cabecera_microrregion'], true)) {
                    continue;
                }

                $value = $entry->data[$key] ?? null;
                $rawPaths = is_array($value) ? array_filter($value) : ($value ? [(string) $value] : []);
                $fieldType = (string) ($fieldTypesByKey[$key] ?? '');
                $fieldTypeLower = strtolower(trim($fieldType));
                $fieldTypeIsImage = in_array($fieldTypeLower, ['image', 'file_image', 'image_upload', 'foto'], true);

                foreach ($rawPaths as $original) {
                    $original = (string)$original;
                    $raw = trim($original);
                    if ($raw === '') continue;

                    // Filtro rápido: evita resolver rutas/mime en valores que no parecen imágenes.
                    if (!$fieldTypeIsImage && !$this->isLikelyImageReference($raw)) {
                        continue;
                    }

                    if (filter_var($raw, FILTER_VALIDATE_URL)) {
                        if (array_key_exists($raw, $payloadByUrl)) {
                            $cached = $payloadByUrl[$raw];
                            foreach ($this->pdfImageLookupKeys($original) as $lookupKey) {
                                $map[$lookupKey] = $cached;
                            }
                            continue;
                        }

                        $localFromUrl = $resolvedLocalByUrl[$raw] ?? null;
                        if (!array_key_exists($raw, $resolvedLocalByUrl)) {
                            $localFromUrl = $this->tryResolveLocalPathFromUrl($raw);
                            $resolvedLocalByUrl[$raw] = $localFromUrl;
                        }

                        if ($localFromUrl !== null) {
                            $payload = $payloadByFullPath[$localFromUrl] ?? null;
                            if ($payload === null) {
                                $binary = @file_get_contents($localFromUrl);
                                if ($binary !== false && $binary !== '') {
                                    $dims = @getimagesizefromstring($binary) ?: null;
                                    $width = is_array($dims) && isset($dims[0]) ? (int) $dims[0] : null;
                                    $height = is_array($dims) && isset($dims[1]) ? (int) $dims[1] : null;
                                    $mime = is_array($dims) && isset($dims['mime']) && is_string($dims['mime']) && $dims['mime'] !== ''
                                        ? $dims['mime']
                                        : (@mime_content_type($localFromUrl) ?: 'image/jpeg');
                                    $dataUri = 'data:'.$mime.';base64,'.base64_encode($binary);
                                    $payload = ['src' => $dataUri, 'w' => $width, 'h' => $height];
                                    $payloadByFullPath[$localFromUrl] = $payload;
                                }
                            }

                            if (is_array($payload) && is_string($payload['src'] ?? null) && $payload['src'] !== '') {
                                $payloadByUrl[$raw] = $payload;
                                foreach ($this->pdfImageLookupKeys($original) as $lookupKey) {
                                    $map[$lookupKey] = $payload;
                                }
                                continue;
                            }
                        }

                        $fallbackPayload = ['src' => $raw, 'w' => null, 'h' => null];
                        foreach ($this->pdfImageLookupKeys($original) as $lookupKey) {
                            $map[$lookupKey] = $fallbackPayload;
                        }
                        continue;
                    }

                    $fullPath = $this->entryDataService->resolveStoredFilePath($raw);
                    if (!is_string($fullPath) || !is_file($fullPath)) continue;

                    if (array_key_exists($original, $payloadByOriginal)) {
                        $payload = $payloadByOriginal[$original];
                    } elseif (array_key_exists($fullPath, $payloadByFullPath)) {
                        $payload = $payloadByFullPath[$fullPath];
                    } else {
                        $binary = @file_get_contents($fullPath);
                        if ($binary === false || $binary === '') continue;
                        $dims = @getimagesizefromstring($binary) ?: null;
                        $width = is_array($dims) && isset($dims[0]) ? (int) $dims[0] : null;
                        $height = is_array($dims) && isset($dims[1]) ? (int) $dims[1] : null;
                        $mime = is_array($dims) && isset($dims['mime']) && is_string($dims['mime']) && $dims['mime'] !== ''
                            ? $dims['mime']
                            : (@mime_content_type($fullPath) ?: 'image/jpeg');
                        $dataUri = 'data:'.$mime.';base64,'.base64_encode($binary);
                        $payload = ['src' => $dataUri, 'w' => $width, 'h' => $height];
                        $payloadByFullPath[$fullPath] = $payload;
                    }

                    $payloadByOriginal[$original] = $payload;

                    foreach ($this->pdfImageLookupKeys($original) as $lookupKey) {
                        $map[$lookupKey] = $payload;
                    }
                }
            }
        }

        return $map;
    }

    private function countPdfImageColumns(array $columns, array $fieldTypesByKey): int
    {
        $count = 0;
        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $key = (string) ($column['key'] ?? '');
            if ($key === '' || in_array($key, ['item', 'microrregion', 'delegacion_numero', 'cabecera_microrregion'], true)) {
                continue;
            }

            $type = strtolower(trim((string) ($fieldTypesByKey[$key] ?? '')));
            if (in_array($type, ['image', 'file', 'document', 'foto', 'file_image', 'image_upload'], true)) {
                $count++;
                continue;
            }

            $keyLower = strtolower($key);
            if (str_contains($keyLower, 'image') || str_contains($keyLower, 'foto')) {
                $count++;
            }
        }

        return $count;
    }

    private function shouldPreloadPdfImageData(int $entryCount, int $imageColumnsCount): bool
    {
        if ($entryCount <= 0 || $imageColumnsCount <= 0) {
            return false;
        }

        $imageCells = $entryCount * $imageColumnsCount;

        // En documentos grandes, los data-uri de imágenes disparan el uso de RAM de Dompdf.
        if ($entryCount > 700 || $imageCells > 1200) {
            return false;
        }

        return true;
    }

    private function isLikelyImageReference(string $value): bool
    {
        $candidate = trim($value);
        if ($candidate === '') {
            return false;
        }

        $lower = strtolower($candidate);
        if (str_starts_with($lower, 'data:image/')) {
            return true;
        }

        $path = strtolower((string) (parse_url($candidate, PHP_URL_PATH) ?? $candidate));
        if ($path !== '' && preg_match('/\.(png|jpe?g|gif|webp|bmp|svg)$/i', $path) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function pdfImageLookupKeys(string $value): array
    {
        $keys = [];
        $original = (string) $value;

        $push = static function (string $candidate) use (&$keys): void {
            $candidate = trim($candidate);
            if ($candidate === '' || in_array($candidate, $keys, true)) {
                return;
            }
            $keys[] = $candidate;
        };

        $push($original);

        $normalized = preg_replace('/[\x00-\x1F\x7F]+/u', '', $original);
        $normalized = is_string($normalized) ? $normalized : $original;
        $normalized = preg_replace('/\s*\/\s*/u', '/', $normalized);
        $normalized = is_string($normalized) ? $normalized : $original;
        $compact = preg_replace('/\s+/u', '', $normalized);
        $compact = is_string($compact) ? $compact : $normalized;
        if (preg_match('~^temporary[\s_-]*modules/~i', $compact) === 1) {
            $compact = preg_replace('~^temporary[\s_-]*modules/~iu', 'temporary-modules/', $compact) ?? $compact;
        }
        $push($normalized);
        $push($compact);

        $push(str_replace('temporary_modules/', 'temporary-modules/', $normalized));
        $push(str_replace('temporary-modules/', 'temporary_modules/', $normalized));
        $push(str_replace('temporary_modules/', 'temporary-modules/', $compact));
        $push(str_replace('temporary-modules/', 'temporary_modules/', $compact));

        return $keys;
    }
}
