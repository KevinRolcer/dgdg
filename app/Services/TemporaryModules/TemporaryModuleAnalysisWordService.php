<?php

namespace App\Services\TemporaryModules;

use App\Models\Microrregione;
use App\Models\TemporaryModule;
use App\Models\TemporaryModuleEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Section as SectionStyle;

/**
 * Informe Word: resumen opcional, tabla microrregiones opcional, y tabla dinámica por columnas (campos).
 */
class TemporaryModuleAnalysisWordService
{
    private const MAX_DYNAMIC_COLS = 12;

    private const PREVIEW_ENTRY_LIMIT = 25;

    public function __construct(
        private readonly TemporaryModuleFieldService $fieldService,
    ) {}

    private function resolveMicrorregionSortDirection(array $config): string
    {
        $direction = strtolower(trim((string) ($config['microrregion_sort'] ?? 'asc')));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';
    }

    private function sortEntriesByMicrorregion(\Illuminate\Support\Collection $entries, string $direction): \Illuminate\Support\Collection
    {
        $descending = strtolower($direction) === 'desc';

        return $entries->sort(function ($left, $right) use ($descending) {
            $leftNumber = (int) ($left->microrregion->microrregion ?? 999999);
            $rightNumber = (int) ($right->microrregion->microrregion ?? 999999);

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
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function buildPreviewPayload(int $moduleId, array $config): array
    {
        $module = TemporaryModule::query()->with(['fields' => fn ($q) => $q->orderBy('sort_order')])->findOrFail($moduleId);
        $includeSummary = (bool) ($config['include_summary'] ?? true);
        $includeMrTable = (bool) ($config['include_mr_table'] ?? true);
        $includeDynamic = (bool) ($config['include_dynamic_table'] ?? true);
        $orientation = strtolower(trim((string) ($config['orientation'] ?? 'portrait'))) === 'landscape' ? 'landscape' : 'portrait';
        $sortDirection = $this->resolveMicrorregionSortDirection($config);

        $columnKeys = $this->normalizeColumnKeys($config['column_keys'] ?? []);
        $tableStyle = $this->normalizeTableStyle($config);
        $tableAlign = $this->normalizeTableAlign($config);

        $docTitle = trim((string) ($config['doc_title'] ?? '')) ?: ('Análisis general — '.($module->name ?: 'Módulo '.$module->id));
        $out = [
            'module_name' => (string) $module->name,
            'doc_title' => $docTitle,
            'title_align' => in_array(($config['title_align'] ?? 'center'), ['left', 'center', 'right'], true) ? $config['title_align'] : 'center',
            'subtitle' => trim((string) ($config['subtitle'] ?? '')),
            'preview_sheet_bg_url' => null,
            'page_layout' => [
                'orientation' => $orientation,
                'paper' => 'A4',
                'uses_template' => false,
            ],
            'table_style' => $tableStyle,
            'table_align' => $tableAlign,
            'exportable_fields' => $this->exportableFieldsList($module),
        ];

        if ($includeSummary || $includeMrTable) {
            $built = $this->buildAnalysisArrays($module, $sortDirection);
            if ($includeSummary) {
                $out['summary'] = $built['summary'];
            }
            if ($includeMrTable) {
                $out['mr_table'] = $built['rows'];
                $out['mr_headers'] = $built['headers'];
            }
        }

        $summaryKpis = $this->normalizeColumnKeys($config['summary_kpi_keys'] ?? []);
        $totalsKeys = $this->normalizeColumnKeys($config['totals_column_keys'] ?? []);
        $dyn = $this->buildDynamicTablePayload($module, $columnKeys, self::PREVIEW_ENTRY_LIMIT, $summaryKpis, $totalsKeys, $sortDirection);
        $out['reference_row'] = $dyn['reference_row'] ?? null;
        if ($includeDynamic && $columnKeys !== []) {
            $out['dynamic_table'] = $dyn;
        } else {
            $out['dynamic_table'] = null;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{font_pt:int, cell_pad_px:int, cell_max_px:int, cell_margin_twips:int}
     */
    private function normalizeTableStyle(array $config): array
    {
        $fontPt = max(7, min(12, (int) ($config['table_font_pt'] ?? 9)));
        $padPx = max(2, min(16, (int) ($config['table_cell_pad'] ?? 6)));
        $maxPx = max(72, min(280, (int) ($config['table_cell_max_px'] ?? 140)));

        return [
            'font_pt' => $fontPt,
            'cell_pad_px' => $padPx,
            'cell_max_px' => $maxPx,
            'cell_margin_twips' => max(30, min(200, (int) round($padPx * 15))),
        ];
    }

    /** @return 'left'|'center'|'right'|'stretch' */
    private function normalizeTableAlign(array $config): string
    {
        $a = strtolower(trim((string) ($config['table_align'] ?? 'left')));

        return in_array($a, ['left', 'center', 'right', 'stretch'], true) ? $a : 'left';
    }

    /**
     * @return array<string, mixed>
     */
    private function tableStyleForWord(bool $stretch): array
    {
        $base = [];
        if ($stretch) {
            $base['width'] = 100;
            $base['unit'] = 'pct';
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function exportWord(int $moduleId, array $config): array
    {
        $module = TemporaryModule::query()->with(['fields' => fn ($q) => $q->orderBy('sort_order')])->findOrFail($moduleId);
        $includeSummary = (bool) ($config['include_summary'] ?? true);
        $includeMrTable = (bool) ($config['include_mr_table'] ?? true);
        $includeDynamic = (bool) ($config['include_dynamic_table'] ?? true);
        $sortDirection = $this->resolveMicrorregionSortDirection($config);
        $tableAlign = $this->normalizeTableAlign($config);
        $stretch = $tableAlign === 'stretch';
        $tblBase = $this->tableStyleForWord($stretch);
        $orientation = strtolower(trim((string) ($config['orientation'] ?? 'portrait'))) === 'landscape'
            ? SectionStyle::ORIENTATION_LANDSCAPE
            : SectionStyle::ORIENTATION_PORTRAIT;
        $columnKeys = $this->normalizeColumnKeys($config['column_keys'] ?? []);
        $ts = $this->normalizeTableStyle($config);
        $ft = $ts['font_pt'];
        $cellTwips = $ts['cell_margin_twips'];
        $titleFontSizePx = max(10, min(36, (int) ($config['title_font_size_px'] ?? 18)));
        $titleFontSizePt = max(8, min(27, (int) round($titleFontSizePx * 0.75)));
        $exportFontName = $this->resolveExportFontName();
        $logoPath = public_path('images/LogoSegobHorizontal.png');
        $hasLogo = is_file($logoPath);

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName($exportFontName);
        $phpWord->setDefaultFontSize(10);
        $section = $phpWord->addSection([
            'orientation' => $orientation,
            'marginTop' => 1134,
            'marginBottom' => 1134,
            'marginLeft' => 1134,
            'marginRight' => 1134,
        ]);

        $docTitle = trim((string) ($config['doc_title'] ?? '')) ?: ('Análisis general — '.($module->name ?: 'Módulo '.$module->id));
        $align = (string) ($config['title_align'] ?? 'center');
        $titleJc = match ($align) {
            'left' => Jc::START,
            'right' => Jc::END,
            default => Jc::CENTER,
        };

        if ($hasLogo) {
        }

        $fechaCorteStr = now()->format('d/m/Y H:i');
        $subtitle = trim((string) ($config['subtitle'] ?? ''));
        $hdrWidth = $orientation === SectionStyle::ORIENTATION_LANDSCAPE ? 14570 : 9638;
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
            $hdrLogoCell = $hdrLogoRow->addCell($hdrWidth, ['gridSpan' => 2, 'valign' => 'bottom', 'borderSize' => 0, 'borderColor' => 'FFFFFF']);
            $hdrLogoRun = $hdrLogoCell->addTextRun(['alignment' => Jc::START]);
            $hdrLogoRun->addImage($logoPath, ['height' => 52]);
        }

        $hdrTbl->addRow();
        $hdrTitleCell = $hdrTbl->addCell($hdrWidth, ['gridSpan' => 2, 'borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMarginTop' => 80]);
        $hdrTitleCell->addText($docTitle, ['name' => $exportFontName, 'bold' => true, 'size' => $titleFontSizePt, 'color' => '861E34'], ['alignment' => $titleJc, 'spaceAfter' => $subtitle !== '' ? 40 : 80]);
        if ($subtitle !== '') {
            $hdrTitleCell->addText($subtitle, ['name' => $exportFontName, 'size' => 10], ['alignment' => $titleJc, 'spaceAfter' => 80]);
        }

        $hdrTbl->addRow();
        $hdrDateCell = $hdrTbl->addCell($hdrWidth, ['gridSpan' => 2, 'valign' => 'bottom', 'borderSize' => 0, 'borderColor' => 'FFFFFF']);
        $hdrDateCell->addText('Fecha y hora de corte: '.$fechaCorteStr, ['name' => $exportFontName, 'size' => 9], ['alignment' => Jc::END, 'spaceAfter' => 120]);
        $section->addTextBreak(1);

        if ($includeSummary || $includeMrTable) {
            $built = $this->buildAnalysisArrays($module, $sortDirection);
            if ($includeSummary && $built['summary'] !== []) {
                $tableSum = $section->addTable(array_merge(['borderSize' => 6, 'borderColor' => '861E34', 'cellMargin' => $cellTwips], $tblBase));
                foreach ($built['summary'] as $label => $value) {
                    $tableSum->addRow();
                    $tableSum->addCell(3500)->addText((string) $label, ['name' => $exportFontName, 'bold' => true, 'size' => $ft]);
                    $tableSum->addCell(8000)->addText((string) $value, ['name' => $exportFontName, 'size' => $ft]);
                }
                $section->addTextBreak(1);
            }
            if ($includeMrTable && $built['rows'] !== []) {
                $t2 = $section->addTable(array_merge(['borderSize' => 6, 'borderColor' => '861E34', 'cellMargin' => $cellTwips], $tblBase));
                $t2->addRow();
                foreach ($built['headers'] as $h) {
                    $t2->addCell(2000, ['bgColor' => '861E34', 'valign' => 'center'])
                        ->addText($h, ['name' => $exportFontName, 'bold' => true, 'size' => $ft, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
                }
                foreach ($built['rows'] as $r) {
                    $t2->addRow();
                    $t2->addCell(3200)->addText(Str::limit((string) $r['microrregion'], 80), ['name' => $exportFontName, 'size' => $ft]);
                    $t2->addCell(1000)->addText((string) $r['registros'], ['name' => $exportFontName, 'size' => $ft], ['alignment' => Jc::CENTER]);
                    $t2->addCell(1000)->addText((string) $r['municipios_capturados'], ['name' => $exportFontName, 'size' => $ft], ['alignment' => Jc::CENTER]);
                    $t2->addCell(4000)->addText(Str::limit((string) $r['lista_capturados'], 400), ['name' => $exportFontName, 'size' => $ft]);
                    $t2->addCell(900)->addText((string) $r['faltantes_count'], ['name' => $exportFontName, 'size' => $ft], ['alignment' => Jc::CENTER]);
                    $t2->addCell(4000)->addText(Str::limit((string) $r['lista_faltantes'], 400), ['name' => $exportFontName, 'size' => $ft]);
                }
                $section->addTextBreak(1);
            }
        }

        if ($includeDynamic && $columnKeys !== []) {
            $summaryKpis = $this->normalizeColumnKeys($config['summary_kpi_keys'] ?? []);
            $totalsKeys = $this->normalizeColumnKeys($config['totals_column_keys'] ?? []);
            $full = $this->buildDynamicTablePayload($module, $columnKeys, 5000, $summaryKpis, $totalsKeys, $sortDirection);
            $headers = $full['headers'];
            $rows = $full['rows'];
            if ($headers !== []) {
                $accSum = $full['accounting_summary'] ?? [];
                if ($accSum !== []) {
                    $section->addText('Resumen (indicadores)', ['name' => $exportFontName, 'bold' => true, 'size' => 10], ['spaceAfter' => 80]);
                    $sumTbl = $section->addTable(array_merge(['borderSize' => 6, 'borderColor' => '861E34', 'cellMargin' => $cellTwips], $tblBase));
                    $sumTbl->addRow();
                    foreach ($accSum as $kpi) {
                        $sumTbl->addCell(2000)->addText((string) ($kpi['label'] ?? ''), ['name' => $exportFontName, 'bold' => true, 'size' => $ft, 'color' => '861E34']);
                    }
                    $sumTbl->addRow();
                    foreach ($accSum as $kpi) {
                        $sumTbl->addCell(2000)->addText((string) ($kpi['value'] ?? ''), ['name' => $exportFontName, 'bold' => true, 'size' => $ft, 'color' => 'C00000']);
                    }
                    $section->addTextBreak(1);
                }
                $section->addText('Desglose por registro', ['name' => $exportFontName, 'bold' => true, 'size' => 11], ['spaceAfter' => 120, 'alignment' => $titleJc]);
                $dynTwips = max(1400, min(3400, (int) round($ts['cell_max_px'] * 38)));
                if ($stretch && count($headers) > 0) {
                    $dynTwips = (int) round(9000 / count($headers));
                }
                $tbl = $section->addTable(array_merge(['borderSize' => 4, 'borderColor' => '444444', 'cellMargin' => $cellTwips], $tblBase));
                $tbl->addRow();
                foreach ($headers as $h) {
                    $tbl->addCell($dynTwips, ['bgColor' => '861E34', 'valign' => 'center'])
                        ->addText($h, ['name' => $exportFontName, 'bold' => true, 'size' => $ft, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
                }
                foreach ($rows as $row) {
                    $tbl->addRow();
                    foreach ($row as $cell) {
                        $tbl->addCell($dynTwips)->addText(Str::limit((string) $cell, 2000), ['name' => $exportFontName, 'size' => $ft]);
                    }
                }
                $totalsRow = $full['totals_row'] ?? null;
                if (is_array($totalsRow) && count($totalsRow) === count($headers)) {
                    $tbl->addRow();
                    foreach ($totalsRow as $i => $cell) {
                        $tbl->addCell($dynTwips)->addText(Str::limit((string) $cell, 500), ['name' => $exportFontName, 'bold' => true, 'size' => $ft, 'color' => 'C00000']);
                    }
                }
            }
        }

        $baseName = Str::slug((string) $module->name, '_') ?: 'modulo_'.$module->id;
        $fileName = $baseName.'_analisis_'.now()->format('Ymd_His').'.docx';
        $exportDir = storage_path('app/public/temporary-exports');
        if (! is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        IOFactory::createWriter($phpWord, 'Word2007')->save($exportDir.'/'.$fileName);

        return [
            'name' => $fileName,
            'url' => route('temporary-modules.admin.exports.download', ['file' => $fileName]),
        ];
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

    /**
     * @return list<string>
     */
    private function normalizeColumnKeys(mixed $keys): array
    {
        if (is_string($keys)) {
            $decoded = json_decode($keys, true);
            $keys = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($keys)) {
            return [];
        }
        $out = [];
        foreach ($keys as $k) {
            $k = preg_replace('/[^a-z0-9_\-]/i', '', (string) $k);
            if ($k !== '' && ! in_array($k, $out, true)) {
                $out[] = $k;
            }
            if (count($out) >= self::MAX_DYNAMIC_COLS) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<array{key:string,label:string,type:string,canonical:string}>
     */
    private function exportableFieldsList(TemporaryModule $module): array
    {
        $list = [];
        foreach ($module->fields as $field) {
            $type = (string) $field->type;
            $list[] = [
                'key' => (string) $field->key,
                'label' => (string) $field->label,
                'type' => $type,
                'canonical' => $this->fieldService->canonicalFieldType($type),
            ];
        }

        return $list;
    }

    /**
     * @param  list<string>  $columnKeys
     * @param  list<string>  $summaryKpiKeys  columnas a mostrar como KPI arriba (subset de columnKeys)
     * @param  list<string>  $totalsColumnKeys columnas con total al pie (suma numérica o conteo Sí)
     * @return array{headers:list<string>,rows:list<list<string>>,reference_row:?array<string,string>,accounting_summary:list<array{label:string,value:string}>,totals_row:?list<string>}
     */
    private function buildDynamicTablePayload(TemporaryModule $module, array $columnKeys, int $entryLimit, array $summaryKpiKeys = [], array $totalsColumnKeys = [], string $sortDirection = 'asc'): array
    {
        $fieldsByKey = $module->fields->keyBy('key');
        $headers = [];
        foreach ($columnKeys as $key) {
            $f = $fieldsByKey->get($key);
            $headers[] = $f ? (string) $f->label : $key;
        }
        if ($headers === []) {
            return ['headers' => [], 'rows' => [], 'reference_row' => null, 'accounting_summary' => [], 'totals_row' => null];
        }

        $entries = TemporaryModuleEntry::query()
            ->where('temporary_module_id', $module->id)
            ->with(['user:id,name', 'microrregion:id,microrregion,cabecera'])
            ->leftJoin('microrregiones', 'microrregiones.id', '=', 'temporary_module_entries.microrregion_id')
            ->orderByRaw(
                'CASE WHEN temporary_module_entries.microrregion_id IS NULL THEN 1 ELSE 0 END, '.
                'CAST(COALESCE(microrregiones.microrregion, 0) AS UNSIGNED) '.strtoupper($sortDirection).', '.
                'temporary_module_entries.submitted_at DESC'
            )
            ->select('temporary_module_entries.*')
            ->limit($entryLimit)
            ->get();
        $entries = $this->sortEntriesByMicrorregion($entries, $sortDirection);

        $n = $entries->count();
        $referenceRow = null;
        $rows = [];
        $rawMatrix = [];
        foreach ($entries as $idx => $entry) {
            $row = [];
            $rawRow = [];
            foreach ($columnKeys as $key) {
                $f = $fieldsByKey->get($key);
                $raw = $entry->data[$key] ?? null;
                $rawRow[$key] = $raw;
                $text = $f ? $this->formatCellValue($raw, (string) $f->type) : $this->formatCellValue($raw, 'text');
                $row[] = $text;
            }
            $rawMatrix[] = $rawRow;
            if ($idx === 0) {
                $referenceRow = array_combine($columnKeys, $row) ?: null;
            }
            $meta = [
                $entry->user->name ?? '—',
                $entry->submitted_at ? $entry->submitted_at->format('d/m/Y H:i') : '—',
                $entry->microrregion->microrregion ?? '—',
            ];
            array_unshift($row, ...$meta);
            $rows[] = $row;
        }

        array_unshift($headers, 'Delegado', 'Fecha registro', 'Microrregión');

        $summaryKpiKeys = array_values(array_intersect($summaryKpiKeys, $columnKeys));
        if ($summaryKpiKeys === []) {
            $summaryKpiKeys = array_slice($columnKeys, 0, 6);
        }
        $accountingSummary = [
            ['label' => 'Total registros', 'value' => (string) $n],
        ];
        $kpiAdded = 0;
        foreach ($summaryKpiKeys as $key) {
            if ($kpiAdded >= 10) {
                break;
            }
            if ($key === '' || ! in_array($key, $columnKeys, true)) {
                continue;
            }
            $f = $fieldsByKey->get($key);
            if (! $f) {
                continue;
            }
            $type = (string) $f->type;
            $canon = $this->fieldService->canonicalFieldType($type);
            $label = (string) $f->label;
            if ($type === 'bool' || $canon === 'bool') {
                $cnt = 0;
                foreach ($rawMatrix as $rawRow) {
                    if ($this->isAffirmativeRaw($rawRow[$key] ?? null)) {
                        $cnt++;
                    }
                }
                $accountingSummary[] = ['label' => $label.' (Sí)', 'value' => (string) $cnt];
                $kpiAdded++;
            } else {
                $sum = $this->sumNumericColumn($rawMatrix, $key);
                if ($sum !== null) {
                    $accountingSummary[] = ['label' => 'Σ '.$label, 'value' => $this->formatNumberTotal($sum)];
                    $kpiAdded++;
                }
            }
        }

        $totalsColumnKeys = array_values(array_intersect($totalsColumnKeys, $columnKeys));
        if ($totalsColumnKeys === []) {
            foreach ($columnKeys as $key) {
                $f = $fieldsByKey->get($key);
                if (! $f) {
                    continue;
                }
                $canon = $this->fieldService->canonicalFieldType((string) $f->type);
                if ($f->type === 'bool' || $canon === 'bool' || $this->sumNumericColumn($rawMatrix, $key) !== null) {
                    $totalsColumnKeys[] = $key;
                }
            }
        }
        $totalsByKey = [];
        foreach ($totalsColumnKeys as $key) {
            $f = $fieldsByKey->get($key);
            if (! $f) {
                continue;
            }
            $canon = $this->fieldService->canonicalFieldType((string) $f->type);
            if ($f->type === 'bool' || $canon === 'bool') {
                $c = 0;
                foreach ($rawMatrix as $rawRow) {
                    if ($this->isAffirmativeRaw($rawRow[$key] ?? null)) {
                        $c++;
                    }
                }
                $totalsByKey[$key] = (string) $c;
            } else {
                $sum = $this->sumNumericColumn($rawMatrix, $key);
                $totalsByKey[$key] = $sum !== null ? $this->formatNumberTotal($sum) : '—';
            }
        }

        $totalsRow = ['TOTALES', '', ''];
        foreach ($columnKeys as $key) {
            $totalsRow[] = $totalsByKey[$key] ?? '—';
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'reference_row' => $referenceRow,
            'accounting_summary' => $accountingSummary,
            'totals_row' => $totalsRow,
        ];
    }

    private function isAffirmativeRaw(mixed $raw): bool
    {
        if ($raw === true || $raw === 1 || $raw === '1') {
            return true;
        }
        if (is_string($raw)) {
            $s = mb_strtolower(trim($raw));

            return in_array($s, ['sí', 'si', 'yes', 'true', '1', 's'], true);
        }

        return false;
    }

    /** Suma solo valores numéricos ≥ 0 (ignora negaciones como No). */
    private function sumNumericColumn(array $rawMatrix, string $key): ?float
    {
        $sum = 0.0;
        $any = false;
        foreach ($rawMatrix as $rawRow) {
            $v = $rawRow[$key] ?? null;
            if ($v === null || $v === '' || $v === false) {
                continue;
            }
            if (is_bool($v)) {
                continue;
            }
            if (is_numeric($v)) {
                $n = (float) $v;
                if ($n >= 0) {
                    $sum += $n;
                    $any = true;
                }

                continue;
            }
            if (is_string($v) && preg_match('/^\s*(\d+(?:[.,]\d+)?)\s*$/', $v, $m)) {
                $n = (float) str_replace(',', '.', $m[1]);
                if ($n >= 0) {
                    $sum += $n;
                    $any = true;
                }
            }
        }

        return $any ? $sum : null;
    }

    private function formatNumberTotal(float $n): string
    {
        if (abs($n - round($n)) < 0.0001) {
            return (string) (int) round($n);
        }

        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    private function formatCellValue(mixed $value, string $type): string
    {
        $t = $this->fieldService->canonicalFieldType($type);
        if ($value === null || $value === '') {
            return '—';
        }
        if ($type === 'bool' || $t === 'bool') {
            return $value ? 'Sí' : 'No';
        }
        if (in_array($t, ['image', 'file'], true)) {
            $files = is_array($value) ? array_filter($value) : ($value ? [(string) $value] : []);
            if (empty($files)) {
                return '—';
            }
            if (count($files) > 1) {
                return '['.count($files).' '.(($t === 'image' || $t === 'foto') ? 'Imágenes' : 'Archivos').' adjuntos]';
            }
            $val = (string) reset($files);
            if (filter_var($val, FILTER_VALIDATE_URL)) {
                return '[Enlace adjunto]';
            }
            $base = basename(str_replace('\\', '/', $val));
            return ($base !== '' && $base !== '.') ? $base : (($t === 'image' || $t === 'foto') ? '[Imagen adjunta]' : '[Archivo adjunto]');
        }
        if ($t === 'categoria' && is_array($value)) {
            $parts = [];
            foreach ($value as $k => $v) {
                if (is_array($v)) {
                    $parts[] = $k.': '.implode(', ', array_map('strval', $v));
                } else {
                    $parts[] = $k.': '.(string) $v;
                }
            }

            return implode(' | ', $parts) ?: '—';
        }
        if ($t === 'seccion' && is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '—';
        }
        if (is_array($value)) {
            return implode(', ', array_map(function ($x) {
                return is_scalar($x) ? (string) $x : json_encode($x, JSON_UNESCAPED_UNICODE);
            }, $value));
        }
        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }
        if ($type === 'semaforo' && is_string($value)) {
            $lab = TemporaryModuleFieldService::labelForSemaforo($value);

            return $lab !== '' ? $lab : '—';
        }

        return trim((string) $value) !== '' ? (string) $value : '—';
    }

    /**
     * @return array{summary: array<string,string>, headers: list<string>, rows: list<array<string,mixed>>}
     */
    private function buildAnalysisArrays(TemporaryModule $temporaryModule, string $sortDirection = 'asc'): array
    {
        $municipioFieldKey = null;
        foreach ($temporaryModule->fields as $field) {
            if ($this->fieldService->canonicalFieldType((string) $field->type) === 'municipio') {
                $municipioFieldKey = $field->key;
                break;
            }
        }

        $totalRegistros = $temporaryModule->entries()->count();
        $microrregionesCapturadas = $temporaryModule->entries()
            ->withoutGlobalScopes()
            ->reorder()
            ->select('microrregion_id')
            ->distinct()
            ->count('microrregion_id');

        $municipiosCapturadosGlobalVals = [];
        if ($municipioFieldKey) {
            $temporaryModule->entries()->select('data')->chunk(500, function ($entries) use (&$municipiosCapturadosGlobalVals, $municipioFieldKey) {
                foreach ($entries as $entry) {
                    $val = $entry->data[$municipioFieldKey] ?? null;
                    if ($val) {
                        $municipiosCapturadosGlobalVals[] = mb_strtolower(trim((string) $val));
                    }
                }
            });
            $municipiosCapturadosGlobalVals = array_unique($municipiosCapturadosGlobalVals);
        }

        $globalMunicipiosCapturadosNombres = [];
        $headers = ['Microrregión', 'Total registros', 'Municipios capturados', 'Lista capturados', 'Faltantes (n)', 'Lista faltantes'];
        $rows = [];

        $registrosPorMicrorregion = $temporaryModule->entries()
            ->reorder()
            ->select('microrregion_id', DB::raw('COUNT(*) as total'))
            ->groupBy('microrregion_id')
            ->pluck('total', 'microrregion_id');

        $direction = strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';
        $allMicrorregiones = Microrregione::with('municipios')->orderBy('microrregion', $direction)->get();

        foreach ($allMicrorregiones as $mr) {
            $cantidadRegistros = (int) ($registrosPorMicrorregion[$mr->id] ?? 0);
            $nombreMr = trim('MR '.($mr->microrregion ?? '').' — '.($mr->cabecera ?? ''));

            $capturadosArray = [];
            $faltantesArray = [];
            $municipiosCapturados = 0;

            if ($municipioFieldKey) {
                foreach ($mr->municipios as $muni) {
                    $muniIdStr = (string) $muni->id;
                    $muniNombreStr = mb_strtolower(trim($muni->municipio));
                    if (in_array($muniIdStr, $municipiosCapturadosGlobalVals, true) || in_array($muniNombreStr, $municipiosCapturadosGlobalVals, true)) {
                        $capturadosArray[] = $muni->municipio;
                        $globalMunicipiosCapturadosNombres[] = $muni->municipio;
                        $municipiosCapturados++;
                    } else {
                        $faltantesArray[] = $muni->municipio;
                    }
                }
            } else {
                $municipiosCapturados = 0;
                $capturadosArray = ['N/A'];
                $faltantesArray = ['Sin campo municipio'];
            }

            $rows[] = [
                'microrregion' => $nombreMr,
                'registros' => $cantidadRegistros,
                'municipios_capturados' => $municipioFieldKey ? $municipiosCapturados : 'N/A',
                'lista_capturados' => implode(', ', $capturadosArray),
                'faltantes_count' => $municipioFieldKey ? count($faltantesArray) : 'N/A',
                'lista_faltantes' => implode(', ', $faltantesArray),
            ];
        }

        $summary = [
            'Total de registros' => (string) $totalRegistros,
            'Microrregiones con captura' => (string) $microrregionesCapturadas,
            'Municipios distintos (catálogo)' => $municipioFieldKey
                ? (string) count(array_unique($globalMunicipiosCapturadosNombres))
                : 'N/A',
        ];

        return ['summary' => $summary, 'headers' => $headers, 'rows' => $rows];
    }
}
