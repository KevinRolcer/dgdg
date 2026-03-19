<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Section as SectionStyle;
use Dompdf\Dompdf;

class TemporaryModuleWordPdfService
{
    /**
     * @param string $format 'word' or 'pdf'
     * @param array|null $exportConfig
     * @return array{name: string, url: string}
     * @throws \Exception
     */
    public function export(int $moduleId, string $format, ?array $exportConfig = null): array
    {
        $temporaryModule = TemporaryModule::query()->findOrFail($moduleId);
        $fileName = trim((string) $temporaryModule->name) !== '' ? $temporaryModule->name : 'Módulo '.$moduleId;
        
        $columnsCfg = is_array($exportConfig) && isset($exportConfig['columns']) && is_array($exportConfig['columns'])
            ? $exportConfig['columns']
            : [];
            
        // Si no hay configuracion de columnas, tomar todas como es el caso por defecto.
        if ($columnsCfg === []) {
             $cols = [];
             foreach ($temporaryModule->fields as $field) {
                 $cols[] = ['key' => $field->key, 'label' => (string) ($field->label ?? $field->key)];
             }
             if(count($cols) > 0) {
                 $columnsCfg = $cols;
             }
        }

        $columnMap = [];
        foreach ($columnsCfg as $col) {
            if (!is_array($col)) {
                continue;
            }
            $key = (string) ($col['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $columnMap[$key] = [
                'key' => $key,
                'label' => (string) ($col['label'] ?? $key),
                'color' => (string) ($col['color'] ?? ''),
            ];
        }
        $columns = array_values($columnMap);
        if ($columns === []) {
            throw new \Exception('No hay columnas seleccionadas para el reporte.');
        }

        $totalCols = count($columns);
        $stretch = ($exportConfig['table_align'] ?? 'left') === 'stretch';

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
            ->orderBy('submitted_at')
            ->get(['microrregion_id', 'data', 'submitted_at']);

        $baseSlug = Str::slug($fileName, '_') ?: 'modulo_temporal_'.$temporaryModule->id;
        $exportDir = storage_path('app/public/temporary-exports');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $title = (string) ($exportConfig['title'] ?? $fileName);
        $orientationConfig = ($exportConfig['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';

        $includeCountTable = !empty($exportConfig['include_count_table']);
        $countByFields = $includeCountTable && is_array($exportConfig['count_by_fields'] ?? null)
            ? array_values(array_filter(array_map('strval', $exportConfig['count_by_fields'])))
            : [];
        $countTable = null;
        if ($includeCountTable) {
            $fieldLabels = [];
            foreach ($columnMap as $k => $c) {
                $fieldLabels[$k] = $c['label'];
            }
            $countTable = $this->buildCountTableData($entries, $countByFields, $fieldLabels);
        }

        if ($format === 'word') {
            $wordFileName = $baseSlug.'_'.now()->format('Ymd_His').'.docx';
            $fullPath = $exportDir.'/'.$wordFileName;

            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            $phpWord->setDefaultFontName('Calibri');
            $phpWord->setDefaultFontSize(10);
            $orientation = $orientationConfig === 'landscape'
                ? \PhpOffice\PhpWord\Style\Section::ORIENTATION_LANDSCAPE
                : \PhpOffice\PhpWord\Style\Section::ORIENTATION_PORTRAIT;
            $section = $phpWord->addSection([
                'orientation' => $orientation,
                'marginTop' => 1134,
                'marginBottom' => 1134,
                'marginLeft' => 1134,
                'marginRight' => 1134,
            ]);

            $align = (string) ($exportConfig['title_align'] ?? 'center');
            $jc = match ($align) {
                'left' => \PhpOffice\PhpWord\SimpleType\Jc::START,
                'right' => \PhpOffice\PhpWord\SimpleType\Jc::END,
                default => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
            };
            $section->addText($title, ['bold' => true, 'size' => 14, 'color' => '861E34'], ['alignment' => $jc, 'spaceAfter' => 100]);
            
            $fechaCorteStr = now()->format('d/m/Y H:i');
            $section->addText('Fecha y hora de corte: ' . $fechaCorteStr, ['size' => 9], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END, 'spaceAfter' => 200]);
            
            $section->addTextBreak(1);

            if ($countTable !== null && isset($countTable['groups'])) {
                $countTblStyle = ['borderSize' => 6, 'borderColor' => '444444', 'cellMargin' => 80];
                $countTbl = $section->addTable($countTblStyle);
                $countTableColors = is_array($exportConfig['count_table_colors'] ?? null) ? $exportConfig['count_table_colors'] : [];
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
                $countTbl->addRow();
                foreach ($countTable['groups'] as $gi => $group) {
                    $key = $gi === 0 ? '_total' : ($countByFields[$gi - 1] ?? '');
                    $bgHex = $resolveCountColor($key, 1);
                    $includePct = !empty($countTableColors[$key]['showPct']);
                    $numValues = count($group['values']);
                    $span = $includePct ? $numValues * 2 : $numValues;
                    $isRedundant = ($gi === 0 || ($numValues === 1 && (trim((string)($group['values'][0]['label'] ?? '')) === '' || trim((string)($group['values'][0]['label'] ?? '')) === trim((string)($group['label'] ?? '')))));
                    
                    $cellStyle = ['gridSpan' => $span, 'bgColor' => $bgHex, 'valign' => 'center'];
                    if ($isRedundant && !$includePct) {
                        $cellStyle['vMerge'] = 'restart';
                    }
                    
                    $cell = $countTbl->addCell(null, $cellStyle);
                    $cell->addText((string) $group['label'], ['bold' => true, 'color' => 'FFFFFF'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                }
                $countTbl->addRow();
                foreach ($countTable['groups'] as $gi => $group) {
                    $key = $gi === 0 ? '_total' : ($countByFields[$gi - 1] ?? '');
                    $includePct = !empty($countTableColors[$key]['showPct']);
                    
                    foreach ($group['values'] as $v) {
                        $subLabel = $v['label'] !== '' ? $v['label'] : $group['label'];
                        $bgHex = $resolveCountColor($key, 2, $subLabel);
                        
                        $isRedundant = ($gi === 0 || (count($group['values']) === 1 && (trim((string)$v['label']) === '' || trim((string)$v['label']) === trim((string)$group['label']))));
                        
                        if ($includePct) {
                            $countTbl->addCell(null, ['bgColor' => $bgHex, 'valign' => 'center'])->addText($isRedundant ? 'Cantidad' : (string) $subLabel, ['bold' => true, 'size' => 8, 'color' => 'FFFFFF'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                            $countTbl->addCell(null, ['bgColor' => $bgHex, 'valign' => 'center'])->addText('%', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                        } else {
                            if ($isRedundant) {
                                $countTbl->addCell(null, ['bgColor' => $bgHex, 'vMerge' => 'continue']);
                            } else {
                                $countTbl->addCell(null, ['bgColor' => $bgHex, 'valign' => 'center'])->addText((string)$subLabel, ['bold' => true, 'color' => 'FFFFFF'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
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
                        $countTbl->addCell()->addText((string) $v['count'], ['color' => 'c00000']);
                        if ($includePct) {
                            $pct = $gTotal > 0 ? round(($v['count'] / $gTotal) * 100, 2) : 0;
                            $countTbl->addCell()->addText($pct . '%', ['color' => 'c00000', 'size' => 8]);
                        }
                    }
                }
                $section->addTextBreak(1);
                $section->addText('Desglose', ['bold' => true, 'size' => 11], ['spaceAfter' => 120]);
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

            $dynTwips = null;
            if ($stretch && $totalCols > 0) {
                // Aprox ancho total disponible en horizontal A4 son 9000 twips (depende de márgenes)
                $dynTwips = (int) round(9000 / $totalCols);
            }

            // Encabezados
            $table->addRow();
            foreach ($columns as $idx => $col) {
                // Determinar color de fondo para encabezados dinámicos
                $bgIdx = $this->getColumnBgColor($col, $idx);
                $table->addCell($dynTwips, ['bgColor' => $bgIdx, 'valign' => 'center'])->addText((string) $col['label'], ['bold' => true, 'color' => 'FFFFFF'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
            }

            // Filas
            $itemNumber = 1;
            foreach ($entries as $entry) {
                $table->addRow();
                foreach ($columns as $col) {
                    $key = $col['key'];
                    if ($key === 'item') {
                        $text = (string) $itemNumber;
                        $itemNumber++;
                    } elseif ($key === 'microrregion') {
                        $meta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));
                        $text = (string) ($meta['label'] ?? $meta->label ?? '');
                    } else {
                        $val = $entry->data[$key] ?? null;
                        if (is_bool($val)) {
                            $text = $val ? 'Sí' : 'No';
                        } elseif (is_array($val)) {
                            $text = implode(', ', array_map(static fn ($v) => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), $val));
                        } elseif (is_scalar($val)) {
                            $text = (string) $val;
                        } else {
                            $text = '';
                        }
                    }
                    $table->addCell($dynTwips)->addText($text);
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
        $html = view('temporary_modules.admin.partials.export_pdf_table', [
            'title' => $title,
            'fechaCorteStr' => $fechaCorteStr,
            'orientation' => $orientationConfig,
            'columns' => $columns,
            'entries' => $entries,
            'microrregionMeta' => $microrregionMeta,
            'stretch' => $stretch,
            'countTable' => $countTable,
            'countTableColorKeys' => $countTableColorKeys,
            'countTableColors' => $countTableColors,
        ])->render();

        $dompdf = new Dompdf([
            'defaultPaperSize' => 'a4',
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

    /**
     * @param Collection $entries entries with 'data'
     * @param array<string> $countByFields
     * @param array<string, string> $fieldLabels key => label
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
}
