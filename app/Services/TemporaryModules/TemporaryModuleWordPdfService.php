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

    /**
     * @param string $format 'word' or 'pdf'
     * @param array|null $exportConfig
     * @return array{name: string, url: string}
     * @throws \Exception
     */
    public function export(int $moduleId, string $format, ?array $exportConfig = null): array
    {
        // Tamaño de letra para encabezados de columnas
        $headerFontSizePx = isset($exportConfig['headerFontPx']) ? max(9, min(28, (int) $exportConfig['headerFontPx'])) : 12;
        $headerFontSizePt = max(7, min(21, (int) round($headerFontSizePx * 0.75)));
    {
        $temporaryModule = TemporaryModule::query()->findOrFail($moduleId);
        $fileName = trim((string) $temporaryModule->name) !== '' ? $temporaryModule->name : 'Módulo '.$moduleId;

        // PDF/Word export can be expensive with many rows/images; relax runtime constraints.
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1024M');
        $columnsCfg = is_array($exportConfig) && isset($exportConfig['columns']) && is_array($exportConfig['columns'])
            ? $exportConfig['columns']
            : [];

        // Si no hay configuracion de columnas, tomar todas como es el caso por defecto.
        $dbFieldLabels = $temporaryModule->fields->pluck('label', 'key')->all();

        if ($columnsCfg === []) {
             $cols = [];
             // Agregar columna virtual de Ítem
             $cols[] = ['key' => 'item', 'label' => 'Ítem', 'color' => ''];
             // Agregar columna virtual de Microrregión
             $cols[] = ['key' => 'microrregion', 'label' => 'Microrregión', 'color' => ''];
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
            ];
        }
        $columns = array_values($columnMap);
        if ($columns === []) {
            throw new \Exception('No hay columnas seleccionadas para el reporte.');
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

        $baseSlug = Str::slug($fileName, '_') ?: 'modulo_temporal_'.$temporaryModule->id;
        $exportDir = storage_path('app/public/temporary-exports');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $titleUppercase = !empty($exportConfig['title_uppercase']);
        $headersUppercase = !empty($exportConfig['headers_uppercase']);
        $title = $this->normalizeExportHeading((string) ($exportConfig['title'] ?? $fileName), $titleUppercase);
        $orientationConfig = ($exportConfig['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
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
        $sumGroupColorHex = $this->cssColorToHex((string) ($exportConfig['sum_group_color'] ?? 'var(--clr-primary)'));
        $sumIncludeTotalsRow = !empty($exportConfig['include_sum_totals_row']);
        $sumTotalsBold = !array_key_exists('sum_totals_bold', $exportConfig) || !empty($exportConfig['sum_totals_bold']);
        $sumTotalsTextColorHex = $this->cssColorToHex((string) ($exportConfig['sum_totals_text_color'] ?? 'var(--clr-primary)'));
        $dataTableAlign = strtolower((string) ($exportConfig['table_align'] ?? 'left'));
        if (!in_array($dataTableAlign, ['left', 'center', 'right', 'stretch'], true)) {
            $dataTableAlign = 'left';
        }
        $cellFontSizePx = $this->normalizeCellFontSizePx($exportConfig['cell_font_size_px'] ?? null);
        $cellFontSizePt = $this->cellPxToWordPt($cellFontSizePx);
        $titleFontSizePx = max(10, min(36, (int) ($exportConfig['title_font_size_px'] ?? 18)));
        $titleFontSizePt = max(8, min(27, (int) round($titleFontSizePx * 0.75)));
        $exportFontName = $this->resolveExportFontName();
        $logoPath = public_path('images/LogoSegobHorizontal.png');
        $hasLogo = is_file($logoPath);
        $groupHeaderColors = $this->resolveGroupHeaderColorMap(is_array($exportConfig['groups'] ?? null) ? $exportConfig['groups'] : []);

        $columns = $this->transformExportColumns($columns, $headersUppercase);

        $columnWidthFractions = $this->computeColumnWidthFractions($columns);
        $usableTableTwips = $orientationConfig === 'landscape' ? 14570 : 9638;
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

        if ($format === 'word') {
            $wordFileName = $baseSlug.'_'.now()->format('Ymd_His').'.docx';
            $fullPath = $exportDir.'/'.$wordFileName;

            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            $phpWord->setDefaultFontName($exportFontName);
            $phpWord->setDefaultFontSize(10);
            $orientation = $orientationConfig === 'landscape'
                ? \PhpOffice\PhpWord\Style\Section::ORIENTATION_LANDSCAPE
                : \PhpOffice\PhpWord\Style\Section::ORIENTATION_PORTRAIT;
            $section = $phpWord->addSection([
                'orientation' => $orientation,
                'marginTop' => $docMarginPreset === 'none' ? 0 : ($docMarginPreset === 'normal' ? 1134 : 720),
                'marginBottom' => $docMarginPreset === 'none' ? 0 : ($docMarginPreset === 'normal' ? 1134 : 720),
                'marginLeft' => $docMarginPreset === 'none' ? 0 : ($docMarginPreset === 'normal' ? 1134 : 720),
                'marginRight' => $docMarginPreset === 'none' ? 0 : ($docMarginPreset === 'normal' ? 1134 : 720),
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
                        if ($valueLabel !== null && isset($c['row2Values']) && is_array($c['row2Values'])) {
                            $css = $c['row2Values'][$valueLabel] ?? $c['row2Values'][mb_strtolower($valueLabel)] ?? null;
                            if ($css !== null && $css !== '') {
                                return $this->cssColorToHex($css);
                            }
                        }
                        return $this->cssColorToHex($c['row2'] ?? '#2d5a27');
                    }
                    return $rowNum === 1 ? '861E34' : '2d5a27';
                };
                $resolveCountWidth = function (string $key, ?string $valueLabel, bool $forPct = false) use ($countTableColors, $baseCountCellWidthCh, $chToTwips): int {
                    $widthCh = $baseCountCellWidthCh;
                    $cfg = $countTableColors[$key] ?? null;
                    if (is_array($cfg) && $valueLabel !== null && isset($cfg['row2Widths']) && is_array($cfg['row2Widths'])) {
                        $raw = $cfg['row2Widths'][$valueLabel] ?? $cfg['row2Widths'][mb_strtolower($valueLabel)] ?? null;
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
                    $key = $gi === 0 ? '_total' : ($countByFields[$gi - 1] ?? '');
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
                    $cell->addText((string) $group['label'], ['name' => $exportFontName, 'bold' => true, 'size' => $cellFontSizePt, 'color' => 'FFFFFF'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                }
                $countTbl->addRow();
                foreach ($countTable['groups'] as $gi => $group) {
                    $key = $gi === 0 ? '_total' : ($countByFields[$gi - 1] ?? '');
                    $includePct = !empty($countTableColors[$key]['showPct']);

                    foreach ($group['values'] as $v) {
                        $subLabel = $v['label'] !== '' ? $v['label'] : $group['label'];
                        $bgHex = $resolveCountColor($key, 2, $subLabel);

                        $isRedundant = ($gi === 0 || (count($group['values']) === 1 && (trim((string)$v['label']) === '' || trim((string)$v['label']) === trim((string)$group['label']))));
                        $cellTwips = $resolveCountWidth($key, $subLabel, false);
                        $pctTwips = $resolveCountWidth($key, $subLabel, true);

                        if ($includePct) {
                            $countTbl->addCell($cellTwips, ['bgColor' => $bgHex, 'valign' => 'center'])->addText($isRedundant ? 'Cantidad' : (string) $subLabel, ['name' => $exportFontName, 'bold' => true, 'size' => $cellFontSizePt, 'color' => 'FFFFFF'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                            $countTbl->addCell($pctTwips, ['bgColor' => $bgHex, 'valign' => 'center'])->addText('%', ['name' => $exportFontName, 'bold' => true, 'size' => $cellFontSizePt, 'color' => 'FFFFFF'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                        } else {
                            if ($isRedundant) {
                                $countTbl->addCell($cellTwips, ['bgColor' => $bgHex, 'vMerge' => 'continue']);
                            } else {
                                $countTbl->addCell($cellTwips, ['bgColor' => $bgHex, 'valign' => 'center'])->addText((string)$subLabel, ['name' => $exportFontName, 'bold' => true, 'size' => $cellFontSizePt, 'color' => 'FFFFFF'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                            }
                        }
                    }
                }
                $countTbl->addRow();
                foreach ($countTable['groups'] as $gi => $group) {
                    $key = $gi === 0 ? '_total' : ($countByFields[$gi - 1] ?? '');
                    $includePct = !empty($countTableColors[$key]['showPct']);
                    $gTotal = array_sum(array_column($group['values'], 'count'));

                    foreach ($group['values'] as $v) {
                        $subLabel = $v['label'] !== '' ? $v['label'] : $group['label'];
                        $cellTwips = $resolveCountWidth($key, $subLabel, false);
                        $pctTwips = $resolveCountWidth($key, $subLabel, true);
                        $countTbl->addCell($cellTwips)->addText((string) $v['count'], ['name' => $exportFontName, 'size' => $cellFontSizePt, 'color' => 'c00000']);
                        if ($includePct) {
                            $pct = $gTotal > 0 ? round(($v['count'] / $gTotal) * 100, 2) : 0;
                            $countTbl->addCell($pctTwips)->addText($pct . '%', ['name' => $exportFontName, 'size' => $cellFontSizePt, 'color' => 'c00000']);
                        }
                    }
                }
                $section->addTextBreak(1);
                $hasSummarySections = true;
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
                    ['name' => $exportFontName, 'bold' => true, 'size' => $sumTitleFontSizePt],
                    ['spaceAfter' => 120, 'alignment' => $sumTitleJc]
                );

                $sumTbl = $section->addTable([
                    'borderSize' => 6,
                    'borderColor' => '444444',
                    'cellMargin' => 80,
                    'alignment' => $this->resolveWordTableAlignment($sumTableAlign),
                ]);
                if ($hasSumGroupHeaders) {
                    $sumTbl->addRow();
                    $sumTbl->addCell(2200, ['bgColor' => $sumGroupColorHex, 'valign' => 'center']);
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
                            $sumTbl->addCell(1600 * $spanCount, ['gridSpan' => $spanCount, 'bgColor' => '334155', 'valign' => 'center'])
                                ->addText(trim($spanGroup) === '' ? '' : (string) $spanGroup, ['name' => $exportFontName, 'bold' => true, 'size' => $cellFontSizePt, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
                            $spanGroup = $grp;
                            $spanCount = 1;
                        }
                        if ($idx === count($sumCombinedCols) - 1) {
                            $sumTbl->addCell(1600 * $spanCount, ['gridSpan' => $spanCount, 'bgColor' => '334155', 'valign' => 'center'])
                                ->addText(trim((string) $spanGroup) === '' ? '' : (string) $spanGroup, ['name' => $exportFontName, 'bold' => true, 'size' => $cellFontSizePt, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
                        }
                    }
                }

                $sumTbl->addRow();
                $sumTbl->addCell(2200, ['bgColor' => $sumGroupColorHex, 'valign' => 'center'])
                    ->addText((string) $sumGroupLabel, ['name' => $exportFontName, 'bold' => true, 'size' => $cellFontSizePt, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
                foreach ($sumCombinedCols as $col) {
                    $sumTbl->addCell(1600, ['bgColor' => '475569', 'valign' => 'center'])
                        ->addText((string) ($col['label'] ?? ''), ['name' => $exportFontName, 'bold' => true, 'size' => $cellFontSizePt, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
                }

                foreach ($sumRows as $row) {
                    $sumTbl->addRow();
                    $sumTbl->addCell(2200)->addText((string) ($row['group'] ?? ''), ['name' => $exportFontName, 'size' => $cellFontSizePt]);

                    foreach ($sumCombinedCols as $col) {
                        $id = (string) ($col['id'] ?? '');
                        if ((string) ($col['op'] ?? 'metric') === 'metric') {
                            $v = (float) (($row['metrics'][$id] ?? 0.0));
                            $sumTbl->addCell(1600)->addText((string) round($v, 2), ['name' => $exportFontName, 'size' => $cellFontSizePt], ['alignment' => Jc::CENTER]);
                        } else {
                            $v = (float) (($row['formulas'][$id] ?? 0.0));
                            $text = (string) round($v, 2);
                            if ((string) ($col['op'] ?? '') === 'percent') {
                                $text .= '%';
                            }
                            $sumTbl->addCell(1600)->addText($text, ['name' => $exportFontName, 'size' => $cellFontSizePt], ['alignment' => Jc::CENTER]);
                        }
                    }
                }

                if (!empty($sumTable['include_totals_row'])) {
                    $sumTotalsBoldCfg = !array_key_exists('totals_bold', $sumTable) || !empty($sumTable['totals_bold']);
                    $sumTotalsTextColorCfg = (string) ($sumTable['totals_text_color'] ?? '861E34');
                    $sumTbl->addRow();
                    $sumTbl->addCell(2200)->addText(
                        $this->normalizeExportHeading('Total', $headersUppercase),
                        ['name' => $exportFontName, 'size' => $cellFontSizePt, 'bold' => $sumTotalsBoldCfg, 'color' => $sumTotalsTextColorCfg]
                    );

                    foreach ($sumCombinedCols as $col) {
                        $includeTotal = !array_key_exists('include_total', $col) || !empty($col['include_total']);
                        if (!$includeTotal) {
                            $sumTbl->addCell(1600)->addText('');
                            continue;
                        }

                        $id = (string) ($col['id'] ?? '');
                        $total = 0.0;
                        foreach ($sumRows as $row) {
                            if ((string) ($col['op'] ?? 'metric') === 'metric') {
                                $total += (float) (($row['metrics'][$id] ?? 0.0));
                            } else {
                                $total += (float) (($row['formulas'][$id] ?? 0.0));
                            }
                        }

                        $text = (string) round($total, 2);
                        if ((string) ($col['op'] ?? '') === 'percent') {
                            $text .= '%';
                        }

                        $sumTbl->addCell(1600)->addText(
                            $text,
                            ['name' => $exportFontName, 'size' => $cellFontSizePt, 'bold' => $sumTotalsBoldCfg, 'color' => $sumTotalsTextColorCfg],
                            ['alignment' => Jc::CENTER]
                        );
                    }
                }

                $section->addTextBreak(1);
                $hasSummarySections = true;
            }

            if ($hasSummarySections) {
                $section->addText($this->normalizeExportHeading('Desglose', $headersUppercase), ['name' => $exportFontName, 'bold' => true, 'size' => 11], ['spaceAfter' => 120]);
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
                        ])->addText((string) $gs['label'], ['bold' => true, 'color' => 'FFFFFF'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
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
                $bgIdx = $this->getColumnBgColor($col, $idx);
                $w = $columnTwips[$idx] ?? null;
                $table->addCell($w, ['bgColor' => $bgIdx, 'valign' => 'center'])->addText((string) $col['label'], ['name' => $exportFontName, 'bold' => true, 'size' => $headerFontSizePt, 'color' => 'FFFFFF'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
            }

            // Filas
            $itemNumber = 1;
            foreach ($entries as $entry) {
                // Ajustar alto de fila para que la imagen quede dentro de la celda.
                $hasImageInRow = false;
                foreach ($columns as $c) {
                    $k = (string) ($c['key'] ?? '');
                    if ($k === '' || $k === 'item' || $k === 'microrregion') {
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
                    $fieldType = (string) ($fieldTypesByKey[$key] ?? '');
                    if ($key === 'item') {
                        $text = (string) $itemNumber;
                        $itemNumber++;
                        $w = $columnTwips[$idx] ?? null;
                        $table->addCell($w)->addText($text, ['name' => $exportFontName, 'size' => $cellFontSizePt]);
                        continue;
                    }

                    if ($key === 'microrregion') {
                        $meta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));
                        $text = (string) ($meta['label'] ?? $meta->label ?? '');
                        $w = $columnTwips[$idx] ?? null;
                        $table->addCell($w)->addText($text, ['name' => $exportFontName, 'size' => $cellFontSizePt]);
                        continue;
                    }

                    $val = $entry->data[$key] ?? null;
                    $imagePaths = $this->resolveImageAbsolutePaths($val, $fieldType);
                    if ($imagePaths !== []) {
                        $w = $columnTwips[$idx] ?? null;
                        $imgCell = $table->addCell($w, ['valign' => 'center']);
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
                            $text = implode(', ', array_map(static fn ($v) => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), $val));
                        } elseif (is_scalar($val)) {
                            $text = (string) $val;
                        } else {
                            $text = '';
                        }
                        $w = $columnTwips[$idx] ?? null;
                        $table->addCell($w)->addText($text, ['name' => $exportFontName, 'size' => $cellFontSizePt]);
                    }
                }
            }

            \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($fullPath);

            return [
                'name' => $wordFileName,
                'url' => route('temporary-modules.admin.exports.download', ['file' => $wordFileName])
            ];
        }

        // PDF
        $pdfFileName = $baseSlug.'_'.now()->format('Ymd_His').'.pdf';
        $fullPdfPath = $exportDir.'/'.$pdfFileName;

        $fechaCorteStr = now()->format('d/m/Y H:i');

        $countTableColorKeys = $countTable !== null && isset($countTable['groups'])
            ? array_merge(['_total'], $countByFields)
            : [];
        $countTableColors = is_array($exportConfig['count_table_colors'] ?? null) ? $exportConfig['count_table_colors'] : [];
        $countTableCellWidth = max(6, min(40, (int) ($exportConfig['count_table_cell_width'] ?? 12)));
        $columnWidthPercents = $this->fractionsToPercents($columnWidthFractions);
        $pdfImageDataByPath = $this->buildPdfImageDataByPath($entries, $columns, $fieldTypesByKey);

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
            'sumGroupColor' => '#'.$sumGroupColorHex,
            'sumIncludeTotalsRow' => $sumIncludeTotalsRow,
            'sumTotalsBold' => $sumTotalsBold,
            'sumTotalsTextColor' => '#'.$sumTotalsTextColorHex,
            'tableAlign' => $dataTableAlign,
            'sectionLabel' => $this->normalizeExportHeading('Desglose', $headersUppercase),
            'fontFamily' => $exportFontName,
            'cellFontSizePx' => $cellFontSizePx,
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
            'fieldTypesByKey' => $fieldTypesByKey,
            'pdfImageDataByPath' => $pdfImageDataByPath,
        ])->render();

        $dompdf = new Dompdf([
            'defaultPaperSize' => 'A4',
            'isRemoteEnabled' => true,
            'defaultFont' => strtolower($exportFontName) === 'gilroy' ? 'gilroy' : 'Arial',
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientationConfig === 'landscape' ? 'landscape' : 'portrait');
        $dompdf->render();
        file_put_contents($fullPdfPath, $dompdf->output());

        return [
            'name' => $pdfFileName,
            'url' => route('temporary-modules.admin.exports.download', ['file' => $pdfFileName])
        ];
    }
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
            ['label' => 'Total de registros', 'values' => [['label' => '', 'count' => $total]]],
        ];

        foreach ($countByFields as $fieldKey) {
            $fieldCfg = is_array($countTableColors[$fieldKey] ?? null) ? $countTableColors[$fieldKey] : [];
            $includeSR = !array_key_exists('showSR', $fieldCfg) || !empty($fieldCfg['showSR']);
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
                $values[] = ['label' => $labelByLower[$lower] ?? $lower, 'count' => $count];
            }
            if ($includeSR && $sinRespuestaCount > 0) {
                $values[] = ['label' => 'No aplica', 'count' => $sinRespuestaCount];
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
    * @return array{group_label:string,metric_columns:array<int,array{id:string,label:string,group:string,include_total:bool}>,formula_columns:array<int,array{id:string,label:string,group:string,op:string,base_metric_id:string,include_total:bool}>,metric_labels:array<string,string>,formula_labels:array<string,string>,rows:array<int,array{group:string,metrics:array<string,float>,formulas:array<string,float>}>}
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
            $formulaColumns[] = ['id' => $id, 'label' => $label, 'group' => $group, 'op' => $op, 'base_metric_id' => $baseMetricId, 'include_total' => $includeTotal, 'sort_order' => $sortOrder];
        }

        $rowsByKey = [];
        $orderedKeys = [];
        foreach ($entries as $entry) {
            $entryData = (array) ($entry->data ?? []);
            if ($groupBy === 'municipio') {
                $groupLabel = $this->resolveMunicipioGroupLabel($entryData, $fieldLabels);
            } else {
                $meta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));
                $groupLabel = (string) (($meta['label'] ?? null) ?: 'Sin microrregión');
            }
            $groupKey = $groupBy.':'.$groupLabel;
            if (!isset($rowsByKey[$groupKey])) {
                $orderedKeys[] = $groupKey;
                $rowsByKey[$groupKey] = ['group' => $groupLabel, 'metrics' => [], 'formulas' => []];
                foreach ($sumMetrics as $metric) {
                    $rowsByKey[$groupKey]['metrics'][(string) $metric['id']] = 0.0;
                }
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
            'group_label' => $groupBy === 'municipio' ? 'Municipio' : 'Microrregión',
            'metric_columns' => $metricColumns,
            'formula_columns' => $formulaColumns,
            'metric_labels' => $metricLabels,
            'formula_labels' => $formulaLabels,
            'rows' => $rows,
        ];
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
        if (preg_match('/^#([0-9A-Fa-f]{3})$/', $color, $m)) {
            $r = str_repeat($m[1][0], 2);
            $g = str_repeat($m[1][1], 2);
            $b = str_repeat($m[1][2], 2);
            return strtoupper($r.$g.$b);
        }
        $map = [
            'var(--clr-primary)' => '861E34',
            'var(--clr-secondary)' => '2d5a27',
            'var(--clr-accent)' => 'c9a227',
        ];
        return $map[$color] ?? '861E34';
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
        $dataUriByOriginal = [];
        $dataUriByUrl = [];
        $dataUriByFullPath = [];
        $resolvedLocalByUrl = [];

        foreach ($entries as $entry) {
            foreach ($columns as $column) {
                $key = (string) ($column['key'] ?? '');
                if ($key === '' || $key === 'item' || $key === 'microrregion') {
                    continue;
                }

                $value = $entry->data[$key] ?? null;
                $rawPaths = is_array($value) ? array_filter($value) : ($value ? [(string) $value] : []);
                $fieldType = (string) ($fieldTypesByKey[$key] ?? '');

                foreach ($rawPaths as $original) {
                    $original = (string)$original;
                    $raw = trim($original);
                    if ($raw === '') continue;

                    if (filter_var($raw, FILTER_VALIDATE_URL)) {
                        if (!$this->isImageTypeOrValue($fieldType, $raw)) continue;

                        if (array_key_exists($raw, $dataUriByUrl)) {
                            $cached = $dataUriByUrl[$raw];
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
                            $dataUri = $dataUriByFullPath[$localFromUrl] ?? null;
                            if ($dataUri === null) {
                                $binary = @file_get_contents($localFromUrl);
                                if ($binary !== false && $binary !== '') {
                                    $mime = @mime_content_type($localFromUrl) ?: 'image/jpeg';
                                    $dataUri = 'data:'.$mime.';base64,'.base64_encode($binary);
                                    $dataUriByFullPath[$localFromUrl] = $dataUri;
                                }
                            }

                            if (is_string($dataUri) && $dataUri !== '') {
                                $dataUriByUrl[$raw] = $dataUri;
                                foreach ($this->pdfImageLookupKeys($original) as $lookupKey) {
                                    $map[$lookupKey] = $dataUri;
                                }
                                continue;
                            }
                        }

                        foreach ($this->pdfImageLookupKeys($original) as $lookupKey) {
                            $map[$lookupKey] = $raw;
                        }
                        continue;
                    }

                    if (!$this->isImageTypeOrValue($fieldType, $raw)) continue;

                    $fullPath = $this->entryDataService->resolveStoredFilePath($raw);
                    if (!is_string($fullPath) || !is_file($fullPath)) continue;

                    if (array_key_exists($original, $dataUriByOriginal)) {
                        $dataUri = $dataUriByOriginal[$original];
                    } elseif (array_key_exists($fullPath, $dataUriByFullPath)) {
                        $dataUri = $dataUriByFullPath[$fullPath];
                    } else {
                        $binary = @file_get_contents($fullPath);
                        if ($binary === false || $binary === '') continue;
                        $mime = @mime_content_type($fullPath) ?: 'image/jpeg';
                        $dataUri = 'data:'.$mime.';base64,'.base64_encode($binary);
                        $dataUriByFullPath[$fullPath] = $dataUri;
                    }

                    $dataUriByOriginal[$original] = $dataUri;

                    foreach ($this->pdfImageLookupKeys($original) as $lookupKey) {
                        $map[$lookupKey] = $dataUri;
                    }
                }
            }
        }

        return $map;
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
