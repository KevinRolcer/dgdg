<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\SimpleType\Jc;
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

        $columns = $this->transformExportColumns($columns, $headersUppercase);

        $columnWidthFractions = $this->computeColumnWidthFractions($columns);
        $usableTableTwips = $orientationConfig === 'landscape' ? 14570 : 9638;
        $columnTwips = $this->distributeTwipsFromFractions($columnWidthFractions, $usableTableTwips);

        $includeCountTable = !empty($exportConfig['include_count_table']);
        $countByFields = $includeCountTable && is_array($exportConfig['count_by_fields'] ?? null)
            ? array_values(array_filter(array_map('strval', $exportConfig['count_by_fields'])))
            : [];
        $countTableColors = is_array($exportConfig['count_table_colors'] ?? null) ? $exportConfig['count_table_colors'] : [];
        $countTable = null;
        if ($includeCountTable) {
            $fieldLabels = [];
            foreach ($columns as $column) {
                $fieldLabels[(string) ($column['key'] ?? '')] = (string) ($column['label'] ?? '');
            }
            $countTable = $this->buildCountTableData($entries, $countByFields, $fieldLabels, $countTableColors);
            $countTable = $this->transformCountTableLabels($countTable, $headersUppercase);
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

            if ($countTable !== null && isset($countTable['groups'])) {
                $countTblStyle = ['borderSize' => 6, 'borderColor' => '444444', 'cellMargin' => 80];
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
                    $groupSpans[] = ['label' => $g, 'span' => 1];
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
                            'bgColor' => '64748B',
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
                    if ($this->resolveImageAbsolutePath($v, $t) !== null) {
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
                    $imagePath = $this->resolveImageAbsolutePath($val, $fieldType);
                    if ($imagePath !== null) {
                        $w = $columnTwips[$idx] ?? null;
                        $imgCell = $table->addCell($w, ['valign' => 'center']);
                        $imgCell->addImage($imagePath, [
                            'height' => 72,
                            'alignment' => Jc::CENTER,
                        ]);
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
            'countTableAlign' => $countTableAlign,
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
            'columnWidthPercents' => $columnWidthPercents,
            'entries' => $entries,
            'microrregionMeta' => $microrregionMeta,
            'stretch' => $stretch,
            'countTable' => $countTable,
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

    private function resolveImageAbsolutePath(mixed $value, string $fieldType): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (!$this->isImageTypeOrValue($fieldType, $raw)) {
            return null;
        }

        // Si viene como URL (p.ej. /storage/... o https://dominio/storage/...), intentamos resolver a ruta local
        // para poder incrustar (PhpWord requiere path local).
        if (filter_var($raw, FILTER_VALIDATE_URL)) {
            $localFromUrl = $this->tryResolveLocalPathFromUrl($raw);
            if ($localFromUrl !== null) {
                return $localFromUrl;
            }
            // Evitar SSRF: solo descargamos si es del mismo host que la app
            $downloaded = $this->tryDownloadImageFromSameHostUrl($raw);
            if ($downloaded !== null) {
                return $downloaded;
            }
            return null;
        }

        $fullPath = $this->entryDataService->resolveStoredFilePath($raw);
        if (!is_string($fullPath) || !is_file($fullPath)) {
            return null;
        }

        return $fullPath;
    }

    private function pxToTwips(int $px): int
    {
        // Aproximación: 96dpi → 1px ≈ 15 twips (1440 twips / 96 px).
        return max(0, (int) round($px * 15));
    }

    private function isImageTypeOrValue(string $fieldType, string $value): bool
    {
        $ft = strtolower(trim($fieldType));
        if ($ft === 'image' || $ft === 'foto' || $ft === 'photo') {
            return true;
        }

        $extension = strtolower((string) pathinfo(parse_url($value, PHP_URL_PATH) ?? $value, PATHINFO_EXTENSION));
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
            return true;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
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

        foreach ($entries as $entry) {
            foreach ($columns as $column) {
                $key = (string) ($column['key'] ?? '');
                if ($key === '' || $key === 'item' || $key === 'microrregion') {
                    continue;
                }

                $value = $entry->data[$key] ?? null;
                if (!is_scalar($value)) {
                    continue;
                }

                $original = (string) $value;
                $raw = trim($original);
                if ($raw === '') {
                    continue;
                }

                $fieldType = (string) ($fieldTypesByKey[$key] ?? '');

                if (filter_var($raw, FILTER_VALIDATE_URL)) {
                    if (!$this->isImageTypeOrValue($fieldType, $raw)) {
                        continue;
                    }

                    // Preferir inline base64 si la URL mapea a archivo local.
                    $localFromUrl = $this->tryResolveLocalPathFromUrl($raw);
                    if ($localFromUrl !== null) {
                        $binary = @file_get_contents($localFromUrl);
                        if ($binary !== false && $binary !== '') {
                            $mime = @mime_content_type($localFromUrl) ?: 'image/jpeg';
                            $dataUri = 'data:'.$mime.';base64,'.base64_encode($binary);
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

                if (!$this->isImageTypeOrValue($fieldType, $raw)) {
                    continue;
                }

                $fullPath = $this->entryDataService->resolveStoredFilePath($raw);
                if (!is_string($fullPath) || !is_file($fullPath)) {
                    continue;
                }

                $binary = @file_get_contents($fullPath);
                if ($binary === false || $binary === '') {
                    continue;
                }

                $mime = @mime_content_type($fullPath) ?: 'image/jpeg';
                $dataUri = 'data:'.$mime.';base64,'.base64_encode($binary);

                foreach ($this->pdfImageLookupKeys($original) as $lookupKey) {
                    $map[$lookupKey] = $dataUri;
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
