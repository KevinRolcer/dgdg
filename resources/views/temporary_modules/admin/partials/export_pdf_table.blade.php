@php
    $fieldTypesByKey = $fieldTypesByKey ?? [];
    $pdfImageDataByPath = $pdfImageDataByPath ?? [];
    $operationsValueIsEmpty = function ($value) use (&$operationsValueIsEmpty): bool {
        if ($value === null) {
            return true;
        }
        if (is_array($value)) {
            if (array_key_exists('primary', $value)) {
                return $operationsValueIsEmpty($value['primary']);
            }
            if ($value === []) {
                return true;
            }
            foreach ($value as $item) {
                if (!$operationsValueIsEmpty($item)) {
                    return false;
                }
            }
            return true;
        }
        if (is_object($value)) {
            return $operationsValueIsEmpty((array) $value);
        }
        return trim((string) $value) === '';
    };
    $operationsParseNumber = function ($value): ?float {
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

        return is_numeric($raw) ? (float) $raw : null;
    };
    $operationsExtractNumber = function ($value) use (&$operationsExtractNumber, $operationsParseNumber): ?float {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            if (array_key_exists('primary', $value)) {
                return $operationsParseNumber($value['primary']);
            }
            $sum = 0.0;
            $hasAny = false;
            foreach ($value as $item) {
                $n = $operationsParseNumber($item);
                if ($n !== null) {
                    $sum += $n;
                    $hasAny = true;
                }
            }

            return $hasAny ? $sum : null;
        }
        if (is_object($value)) {
            return $operationsExtractNumber((array) $value);
        }

        return $operationsParseNumber($value);
    };
    $isNumericFieldType = function ($fieldType): bool {
        $t = strtolower(trim((string) $fieldType));
        if ($t === '') {
            return false;
        }
        foreach (['number', 'numeric', 'int', 'integer', 'decimal', 'float', 'double'] as $token) {
            if (str_contains($t, $token)) {
                return true;
            }
        }

        return false;
    };
    $fontFamily = $fontFamily ?? 'Gilroy';
    $docMarginPreset = strtolower((string) ($docMarginPreset ?? 'compact'));
    if (!in_array($docMarginPreset, ['normal', 'compact', 'none'], true)) {
        $docMarginPreset = 'compact';
    }
    $pageMarginCss = match ($docMarginPreset) {
        'none' => '0mm',
        'normal' => '15mm 15mm 15mm 15mm',
        default => '12mm 12mm 10mm 12mm',
    };
    $recordsCellFontSizePx = max(9, min(24, (int) ($cellFontSizePx ?? 12)));
    $recordsHeaderFontSizePx = isset($headerFontSizePx) ? max(9, min(28, (int) $headerFontSizePx)) : 12;
    $recordsGroupHeaderFontSizePx = max(9, min(48, (int) ($recordsGroupHeaderFontSizePx ?? $recordsHeaderFontSizePx)));
    $sumTableCellFontSizePx = max(9, min(24, (int) ($sumTableCellFontSizePx ?? $recordsCellFontSizePx)));
    $sumTableHeaderFontSizePx = max(9, min(28, (int) ($sumTableHeaderFontSizePx ?? $recordsHeaderFontSizePx)));
    $sumGroupHeaderFontSizePx = max(9, min(48, (int) ($sumGroupHeaderFontSizePx ?? $sumTableHeaderFontSizePx)));
    $totalsTableCellFontSizePx = max(9, min(24, (int) ($totalsTableCellFontSizePx ?? $sumTableCellFontSizePx)));
    $totalsTableHeaderFontSizePx = max(9, min(48, (int) ($totalsTableHeaderFontSizePx ?? $sumTableHeaderFontSizePx)));
    $totalsGroupHeaderFontSizePx = max(9, min(48, (int) ($totalsGroupHeaderFontSizePx ?? $totalsTableHeaderFontSizePx)));
    $titleFontSizePx = max(10, min(36, (int) ($titleFontSizePx ?? 18)));
    $varsCss = [
        'var(--clr-primary)' => '#861E34',
        'var(--clr-secondary)' => '#246257',
        'var(--clr-accent)' => '#C79B66',
        'var(--clr-text-main)' => '#484747',
        'var(--clr-text-light)' => '#6B6A6A',
        'var(--clr-bg)' => '#F7F7F8',
        'var(--clr-card)' => '#FFFFFF',
    ];
    $resolveCss = function ($css) use ($varsCss) {
        return isset($varsCss[$css]) ? $varsCss[$css] : (str_starts_with($css ?? '', '#') ? $css : '#861E34');
    };
    $countTableAlign = strtolower((string) ($countTableAlign ?? 'left'));
    if (!in_array($countTableAlign, ['left', 'center', 'right'], true)) {
        $countTableAlign = 'left';
    }
    $sumTableAlign = strtolower((string) ($sumTableAlign ?? $countTableAlign));
    if (!in_array($sumTableAlign, ['left', 'center', 'right'], true)) {
        $sumTableAlign = $countTableAlign;
    }
    $sectionLabelAlign = strtolower((string) ($sectionLabelAlign ?? 'left'));
    if (!in_array($sectionLabelAlign, ['left', 'center', 'right'], true)) {
        $sectionLabelAlign = 'left';
    }
    $dataTableAlign = strtolower((string) ($tableAlign ?? 'left'));
    if (!in_array($dataTableAlign, ['left', 'center', 'right', 'stretch'], true)) {
        $dataTableAlign = 'left';
    }

    $countTableHeaderFontSizePx = max(7, min(24, (int) ($countTableHeaderFontSizePx ?? 8)));
    $countTableCellFontSizePx = max(7, min(24, (int) ($countTableCellFontSizePx ?? 10)));
    $countTablePctFontSizePx = max(6, min(22, (int) round($countTableCellFontSizePx * 0.9)));

    $preferredUnits = 0;
    foreach (($columns ?? []) as $col) {
        $maxChars = (int) ($col['max_width_chars'] ?? 24);
        $maxChars = max(6, min($maxChars, 60));
        $preferredUnits += $maxChars;
    }
    $forceFullWidthDataTable = count($columns ?? []) >= 6 || $preferredUnits > 110;

    $stretch = !empty($stretch) || $dataTableAlign === 'stretch';

    $countTableStyle = match ($countTableAlign) {
        'center' => 'width:auto; margin: 0 auto 16px auto;',
        'right' => 'width:auto; margin: 0 0 16px auto;',
        default => 'width:auto; margin: 0 auto 16px 0;',
    };
    $countTableWrapStyle = match ($countTableAlign) {
        'center' => 'text-align:center; margin-bottom: 16px;',
        'right' => 'text-align:right; margin-bottom: 16px;',
        default => 'text-align:left; margin-bottom: 16px;',
    };
    $countTableInlineStyle = 'display:inline-table; width:auto; table-layout:fixed; margin:0;';
    $countTableCellWidth = max(6, min(40, (int) ($countTableCellWidth ?? 12)));
    $sumTableWrapStyle = match ($sumTableAlign) {
        'center' => 'text-align:center; margin-bottom: 16px;',
        'right' => 'text-align:right; margin-bottom: 16px;',
        default => 'text-align:left; margin-bottom: 16px;',
    };
    $sumTableStyle = match ($sumTableAlign) {
        'center' => 'width:auto; margin: 0 auto 16px auto;',
        'right' => 'width:auto; margin: 0 0 16px auto;',
        default => 'width:auto; margin: 0 auto 16px 0;',
    };
    $sumGroupColor = trim((string) ($sumGroupColor ?? 'var(--clr-primary)'));
    $summaryCompactLogoHeightPx = 36;
    $summaryCompactTitlePx = max(12, min($titleFontSizePx - 3, 16));
    $summaryCompactDatePx = max(8, min(10, $summaryCompactTitlePx - 5));

    if ($forceFullWidthDataTable || $stretch || $dataTableAlign === 'left') {
        $dataTableStyle = 'width:100%; margin-top:10px;';
    } elseif ($dataTableAlign === 'center') {
        $dataTableStyle = 'width:auto; display:table; margin:10px auto 0 auto;';
    } else {
        $dataTableStyle = 'width:auto; display:table; margin:10px 0 0 auto;';
    }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            margin: {{ $pageMarginCss }};
        }
        * { box-sizing: border-box; }
        body {
            font-family: {{ $fontFamily }}, Arial, DejaVu Sans, sans-serif;
            font-size: 9px;
            margin: 0;
            color: #333;
        }
        h1 {
            font-size: {{ $titleFontSizePx }}px;
            color: #861E34;
            text-align: center;
            margin: 0 0 8px 0;
        }
        .doc-head-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 4px 0;
        }
        .doc-head-table td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }
        .doc-head-logo-cell {
            width: 62px;
            text-align: left;
            padding-right: 8px;
        }
        .doc-head-logo {
            max-height: 52px;
            width: auto;
            display: block;
        }
        .doc-head-title-cell h1 {
            margin: 0;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        /* Repetir encabezados del desglose en cada página impresa (Dompdf) */
        table.data-table thead {
            display: table-header-group;
        }
        table.data-table tbody {
            display: table-row-group;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px;
            vertical-align: middle;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: anywhere;
            word-break: break-word;
            overflow: visible;
            font-size: {{ $recordsCellFontSizePx }}px;
        }
        th {
            font-size: {{ $recordsHeaderFontSizePx }}px !important;
        }
        /*
         * Evitar page-break-inside: avoid en todas las filas: con Dompdf provoca saltos
         * prematuros y páginas medio vacías. Las filas altas pueden partirse si hace falta.
         */
        table.data-table tbody tr {
            page-break-inside: auto;
        }
        th {
            background: #861E34;
            color: #fff;
            font-weight: bold;
            text-align: center;
        }
        table.data-table tbody tr:nth-child(even) td {
            background: #fdfdfd;
        }
        /* Tabla de conteo: misma regla que antes (selector global `table` + `.count-table`) */
        .count-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 20px;
        }
        .count-table tr {
            page-break-inside: avoid;
        }
        .count-table th { background: #861E34; color: #fff; text-align: center; font-size: var(--count-header-fs, 8px); }
        .count-table td { text-align: center; color: #c00; font-weight: bold; font-size: var(--count-cell-fs, 10px); }
        .count-table th,
        .count-table td {
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.1;
        }
        .sum-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 16px;
        }
        .sum-table th {
            background: #475569;
            color: #fff;
            text-align: center;
            font-size: var(--sum-header-fs, 9px) !important;
            padding: var(--sum-cell-pad, 4px);
            line-height: 1.08;
        }
        .sum-table td {
            text-align: center;
            font-size: var(--sum-cell-fs, 10px);
            padding: var(--sum-cell-pad, 4px);
            line-height: 1.08;
        }
        .summary-page-break {
            page-break-before: always;
            margin-top: 0;
        }
    </style>
</head>
<body>
<table class="doc-head-table" role="presentation">
    @if (!empty($logoDataUri))
    <tr>
        <td colspan="2" style="text-align: left; vertical-align: bottom; padding-bottom: 2px;">
            <img class="doc-head-logo" src="{{ $logoDataUri }}" alt="Gobierno de Puebla" style="max-height: {{ $summaryCompactLogoHeightPx }}px;">
        </td>
    </tr>
    <tr>
        <td colspan="2" style="padding-top: 6px; padding-bottom: 3px;">
            <h1 style="text-align: {{ ($titleAlign ?? 'center') === 'left' ? 'left' : (($titleAlign ?? 'center') === 'right' ? 'right' : 'center') }}; margin-bottom: 0; font-size: {{ $summaryCompactTitlePx }}px;">{{ $title }}</h1>
        </td>
    </tr>
    <tr>
        <td colspan="2" style="text-align: right; font-size: {{ $summaryCompactDatePx }}px; padding-bottom: 6px;">
            @if(isset($fechaCorteStr))Fecha y hora de corte: {{ $fechaCorteStr }}@endif
        </td>
    </tr>
    @else
    <tr>
        <td class="doc-head-title-cell" style="padding-bottom: 4px;">
            <h1 style="text-align: {{ ($titleAlign ?? 'center') === 'left' ? 'left' : (($titleAlign ?? 'center') === 'right' ? 'right' : 'center') }}; margin-bottom: 0; font-size: {{ $summaryCompactTitlePx }}px;">{{ $title }}</h1>
        </td>
    </tr>
    @if(isset($fechaCorteStr))
    <tr>
        <td style="text-align: right; font-size: {{ $summaryCompactDatePx }}px; padding-bottom: 6px;">Fecha y hora de corte: {{ $fechaCorteStr }}</td>
    </tr>
    @endif
    @endif
</table>

@if(!empty($includeTotalsTable) && !empty($totalsTable) && is_array($totalsTable) && !empty($totalsTable['columns']))
@php
    $totalsTableAlign = 'center';
    $totalsTableTitle = trim((string) ($totalsTableTitle ?? 'Totales'));
    if ($totalsTableTitle === '') { $totalsTableTitle = 'Totales'; }
    if (!empty($headersUppercase)) { $totalsTableTitle = mb_strtoupper($totalsTableTitle); }
    $totalsWrapStyle = 'text-align:center; margin-bottom: 16px;';
    $totalsStyle = 'width:auto; margin: 0 auto 16px auto;';
    $totalsCols = is_array($totalsTable['columns'] ?? null) ? $totalsTable['columns'] : [];
    $totalsVals = is_array($totalsTable['values'] ?? null) ? $totalsTable['values'] : [];
    $totalsHasGroups = collect($totalsCols)->contains(fn ($c) => ((string) ($c['group'] ?? '')) !== '');
    $totalsGroupSpans = [];
    if ($totalsHasGroups) {
        foreach ($totalsCols as $col) {
            $g = (string) ($col['group'] ?? '');
            if (!empty($totalsGroupSpans) && $totalsGroupSpans[count($totalsGroupSpans) - 1]['label'] === $g) {
                $totalsGroupSpans[count($totalsGroupSpans) - 1]['span']++;
            } else {
                $totalsGroupSpans[] = ['label' => $g, 'span' => 1];
            }
        }
    }
@endphp
<p style="font-weight: bold; margin: 6px 0 6px 0; text-align: {{ $totalsTableAlign }}; font-size: {{ $sumTitleFontSizePx ?? 14 }}px;">{{ $totalsTableTitle }}</p>
<div style="{{ $totalsWrapStyle }}">
    <table class="sum-table" style="{{ $totalsStyle }} {{ $countTableInlineStyle }} --sum-header-fs: {{ $totalsTableHeaderFontSizePx }}px; --sum-cell-fs: {{ $totalsTableCellFontSizePx }}px; --sum-cell-pad: 3px 4px;">
        <thead>
        @if($totalsHasGroups)
            <tr>
                <th style="background:{{ $sumGroupColor }};color:#fff;"></th>
                @foreach($totalsGroupSpans as $gs)
                    @php
                        $tGroupKey = mb_strtolower(trim((string) ($gs['label'] ?? '')));
                        $tGroupBg = $gs['label'] !== '' ? (($groupHeaderColors[$tGroupKey] ?? '#64748b')) : '#334155';
                    @endphp
                    <th colspan="{{ $gs['span'] }}" style="background:{{ $tGroupBg }};color:#fff;font-size:{{ $totalsGroupHeaderFontSizePx }}px !important;">{{ $gs['label'] }}</th>
                @endforeach
            </tr>
        @endif
            <tr>
                <th style="background:{{ $sumGroupColor }};color:#fff;">{{ !empty($headersUppercase) ? mb_strtoupper('Total') : 'Total' }}</th>
                @foreach ($totalsCols as $col)
                    @php
                        $totalsHeadGroup = trim((string) ($col['group'] ?? ''));
                        $totalsHeadGroupKey = mb_strtolower($totalsHeadGroup);
                        $totalsHeadBg = $totalsHeadGroup !== '' ? ($groupHeaderColors[$totalsHeadGroupKey] ?? '#64748b') : '#475569';
                    @endphp
                    <th style="background:{{ $totalsHeadBg }};color:#fff;">{{ $col['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="color: {{ '#'.((string) ($sumTable['totals_text_color'] ?? '861E34')) }}; {{ (!array_key_exists('totals_bold', $sumTable ?? []) || !empty($sumTable['totals_bold'])) ? 'font-weight:700;' : '' }}">{{ !empty($headersUppercase) ? mb_strtoupper('Total') : 'Total' }}</td>
                @foreach ($totalsCols as $col)
                    @php
                        $id = (string) ($col['id'] ?? '');
                        $v = (float) ($totalsVals[$id] ?? 0.0);
                        $txt = round($v, 2);
                        if ((string) ($col['op'] ?? '') === 'percent') { $txt = $txt.'%'; }
                    @endphp
                    <td style="color: {{ '#'.((string) ($sumTable['totals_text_color'] ?? '861E34')) }}; {{ (!array_key_exists('totals_bold', $sumTable ?? []) || !empty($sumTable['totals_bold'])) ? 'font-weight:700;' : '' }}">{{ $txt }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>
</div>

@php
    $hasCountSummary = !empty($countTable) && isset($countTable['groups']);
    $hasSumSummary = !empty($sumTable) && !empty($sumTable['rows']) && is_array($sumTable['rows']);
@endphp
@if($hasCountSummary || $hasSumSummary)
<div class="summary-page-break"></div>
<table class="doc-head-table" role="presentation">
    @if (!empty($logoDataUri))
    <tr>
        <td colspan="2" style="text-align: left; vertical-align: bottom; padding-bottom: 2px;">
            <img class="doc-head-logo" src="{{ $logoDataUri }}" alt="Gobierno de Puebla" style="max-height: {{ $summaryCompactLogoHeightPx }}px;">
        </td>
    </tr>
    <tr>
        <td colspan="2" style="padding-top: 6px; padding-bottom: 3px;">
            <h1 style="text-align: {{ ($titleAlign ?? 'center') === 'left' ? 'left' : (($titleAlign ?? 'center') === 'right' ? 'right' : 'center') }}; margin-bottom: 0; font-size: {{ $summaryCompactTitlePx }}px;">{{ $title }}</h1>
        </td>
    </tr>
    <tr>
        <td colspan="2" style="text-align: right; font-size: {{ $summaryCompactDatePx }}px; padding-bottom: 6px;">
            @if(isset($fechaCorteStr))Fecha y hora de corte: {{ $fechaCorteStr }}@endif
        </td>
    </tr>
    @else
    <tr>
        <td class="doc-head-title-cell" style="padding-bottom: 4px;">
            <h1 style="text-align: {{ ($titleAlign ?? 'center') === 'left' ? 'left' : (($titleAlign ?? 'center') === 'right' ? 'right' : 'center') }}; margin-bottom: 0; font-size: {{ $summaryCompactTitlePx }}px;">{{ $title }}</h1>
        </td>
    </tr>
    @if(isset($fechaCorteStr))
    <tr>
        <td style="text-align: right; font-size: {{ $summaryCompactDatePx }}px; padding-bottom: 6px;">Fecha y hora de corte: {{ $fechaCorteStr }}</td>
    </tr>
    @endif
    @endif
</table>
@endif
@endif

@if(!empty($countTable) && isset($countTable['groups']))
@php
    $countTableColorKeys = $countTableColorKeys ?? [];
    $countTableColors = $countTableColors ?? [];
    $countTableResolveColor = function (string $colorKey, $rowNum, $valueLabel = null) use ($countTableColors, $resolveCss) {
        $key = trim($colorKey);
        if ($key === '') {
            return $rowNum === 1 ? '#861E34' : '#2d5a27';
        }
        $c = $countTableColors[$key] ?? null;
        if (is_array($c)) {
            if ($rowNum === 1) return $resolveCss($c['row1'] ?? '#861E34');
            if ($valueLabel !== null && isset($c['row2Values'][$valueLabel])) return $resolveCss($c['row2Values'][$valueLabel]);
            if ($valueLabel !== null && isset($c['row2Values']) && is_array($c['row2Values'])) {
                $lower = mb_strtolower($valueLabel);
                foreach ($c['row2Values'] as $k => $v) { if (mb_strtolower($k) === $lower) return $resolveCss($v); }
            }
            return $resolveCss($c['row2'] ?? '#2d5a27');
        }
        if (is_string($c) && $c !== '') return $resolveCss($c);
        return $rowNum === 1 ? '#861E34' : '#2d5a27';
    };
    $countTableResolveWidth = function (string $colorKey, $valueLabel) use ($countTableColors, $countTableCellWidth) {
        $key = trim($colorKey);
        if ($key === '') {
            return $countTableCellWidth;
        }
        $c = $countTableColors[$key] ?? null;
        if (!is_array($c) || !isset($c['row2Widths']) || !is_array($c['row2Widths'])) {
            return $countTableCellWidth;
        }
        $raw = $c['row2Widths'][$valueLabel] ?? $c['row2Widths'][mb_strtolower((string) $valueLabel)] ?? null;
        if ($raw === null) {
            return $countTableCellWidth;
        }
        $n = (int) $raw;
        return max(6, min(40, $n));
    };
@endphp
<div style="{{ $countTableWrapStyle }}">
<table class="count-table" style="{{ $countTableStyle }} {{ $countTableInlineStyle }} --count-header-fs: {{ $countTableHeaderFontSizePx }}px; --count-cell-fs: {{ $countTableCellFontSizePx }}px;">
    <thead>
    <tr>
        @foreach ($countTable['groups'] as $gi => $group)
            @php
                $countColorKey = (string) ($group['color_key'] ?? '');
                if ($countColorKey === '' && isset($countTableColorKeys[$gi])) {
                    $countColorKey = (string) $countTableColorKeys[$gi];
                }
                $includePct = !empty($countTableColors[$countColorKey]['showPct']);
                $numValues = count($group['values']);
                $span = $includePct ? $numValues * 2 : $numValues;
                $isRedundant = ($gi === 0 || ($numValues === 1 && (trim((string)($group['values'][0]['label'] ?? '')) === '' || trim((string)($group['values'][0]['label'] ?? '')) === trim((string)($group['label'] ?? '')))));
            @endphp
            <th colspan="{{ $span }}" @if($isRedundant && !$includePct) rowspan="2" @endif style="background-color: {{ $countTableResolveColor($countColorKey, 1) }}; color: #fff;">{{ $group['label'] }}</th>
        @endforeach
    </tr>
    <tr>
        @foreach ($countTable['groups'] as $gi => $group)
            @php
                $countColorKey = (string) ($group['color_key'] ?? '');
                if ($countColorKey === '' && isset($countTableColorKeys[$gi])) {
                    $countColorKey = (string) $countTableColorKeys[$gi];
                }
                $includePct = !empty($countTableColors[$countColorKey]['showPct']);
                $numValues = count($group['values']);
                $isRedundant = ($gi === 0 || ($numValues === 1 && (trim((string)($group['values'][0]['label'] ?? '')) === '' || trim((string)($group['values'][0]['label'] ?? '')) === trim((string)($group['label'] ?? '')))));
            @endphp
            @foreach ($group['values'] as $v)
                @php $subLabel = $v['label'] !== '' ? $v['label'] : $group['label']; @endphp
                @php $valueW = $countTableResolveWidth($countColorKey, $subLabel); @endphp
                @if($isRedundant && !$includePct)
                    @continue
                @endif
                <th @if($includePct) colspan="2" @endif style="background-color: {{ $countTableResolveColor($countColorKey, 2, $subLabel) }}; color: #fff; width: {{ $includePct ? ($valueW * 1.7) : $valueW }}ch; min-width: {{ $valueW }}ch; white-space: normal; overflow-wrap: anywhere; word-break: break-word; line-height: 1.1;">
                    {{ $isRedundant && $includePct ? 'Cantidad' : $subLabel }}
                </th>
            @endforeach
        @endforeach
    </tr>
    </thead>
    <tbody>
    <tr>
        @foreach ($countTable['groups'] as $gi => $group)
            @php
                $countColorKey = (string) ($group['color_key'] ?? '');
                if ($countColorKey === '' && isset($countTableColorKeys[$gi])) {
                    $countColorKey = (string) $countTableColorKeys[$gi];
                }
                $includePct = !empty($countTableColors[$countColorKey]['showPct']);
                $gTotal = array_sum(array_column($group['values'], 'count'));
            @endphp
            @foreach ($group['values'] as $v)
                @php $subLabel = $v['label'] !== '' ? $v['label'] : $group['label']; @endphp
                @php $valueW = $countTableResolveWidth($countColorKey, $subLabel); @endphp
                <td style="width: {{ $valueW }}ch; min-width: {{ $valueW }}ch; white-space: normal; overflow-wrap: anywhere; word-break: break-word;">{{ $v['count'] }}</td>
                @if($includePct)
                    <td style="width: {{ max(6, (int) floor($valueW * 0.7)) }}ch; font-size: {{ $countTablePctFontSizePx }}px; color: #666; white-space: normal;">
                        {{ $gTotal > 0 ? round(($v['count'] / $gTotal) * 100, 2) : 0 }}%
                    </td>
                @endif
            @endforeach
        @endforeach
    </tr>
    </tbody>
</table>
</div>

@if((!empty($countTable) && isset($countTable['groups'])) && (!empty($sumTable) && !empty($sumTable['rows']) && is_array($sumTable['rows'])))
<div class="summary-page-break"></div>
<table class="doc-head-table" role="presentation">
    @if (!empty($logoDataUri))
    <tr>
        <td colspan="2" style="text-align: left; vertical-align: bottom; padding-bottom: 2px;">
            <img class="doc-head-logo" src="{{ $logoDataUri }}" alt="Gobierno de Puebla">
        </td>
    </tr>
    <tr>
        <td colspan="2" style="padding-top: 10px; padding-bottom: 4px;">
            <h1 style="text-align: {{ ($titleAlign ?? 'center') === 'left' ? 'left' : (($titleAlign ?? 'center') === 'right' ? 'right' : 'center') }}; margin-bottom: 0;">{{ $title }}</h1>
        </td>
    </tr>
    <tr>
        <td colspan="2" style="text-align: right; font-size: 10px; padding-bottom: 10px;">
            @if(isset($fechaCorteStr))Fecha y hora de corte: {{ $fechaCorteStr }}@endif
        </td>
    </tr>
    @else
    <tr>
        <td class="doc-head-title-cell" style="padding-bottom: 4px;">
            <h1 style="text-align: {{ ($titleAlign ?? 'center') === 'left' ? 'left' : (($titleAlign ?? 'center') === 'right' ? 'right' : 'center') }}; margin-bottom: 0;">{{ $title }}</h1>
        </td>
    </tr>
    @if(isset($fechaCorteStr))
    <tr>
        <td style="text-align: right; font-size: 10px; padding-bottom: 10px;">Fecha y hora de corte: {{ $fechaCorteStr }}</td>
    </tr>
    @endif
    @endif
</table>
@endif
@endif

@if(!empty($sumTable) && !empty($sumTable['rows']) && is_array($sumTable['rows']))
@php
    $sumTitle = trim((string) ($sumTitle ?? 'Sumatoria'));
    if ($sumTitle === '') { $sumTitle = 'Sumatoria'; }
    $sumTitleCase = strtolower((string) ($sumTitleCase ?? 'normal'));
    if (!in_array($sumTitleCase, ['normal', 'upper', 'lower'], true)) { $sumTitleCase = 'normal'; }
    $sumTitleAlign = strtolower((string) ($sumTitleAlign ?? 'center'));
    if (!in_array($sumTitleAlign, ['left', 'center', 'right'], true)) { $sumTitleAlign = 'center'; }
    $sumTitleFontSizePx = max(10, min(36, (int) ($sumTitleFontSizePx ?? 14)));
    $sumShowItem = !array_key_exists('sumShowItem', get_defined_vars()) ? true : !empty($sumShowItem);
    $sumItemLabel = trim((string) ($sumItemLabel ?? '#'));
    if ($sumItemLabel === '') { $sumItemLabel = '#'; }
    $sumShowDelegacion = !array_key_exists('sumShowDelegacion', get_defined_vars()) ? true : !empty($sumShowDelegacion);
    $sumDelegacionLabel = trim((string) ($sumDelegacionLabel ?? 'Delegación'));
    if ($sumDelegacionLabel === '') { $sumDelegacionLabel = 'Delegación'; }
    $sumShowCabecera = !array_key_exists('sumShowCabecera', get_defined_vars()) ? true : !empty($sumShowCabecera);
    $sumCabeceraLabel = trim((string) ($sumCabeceraLabel ?? 'Cabecera'));
    if ($sumCabeceraLabel === '') { $sumCabeceraLabel = 'Cabecera'; }
    $sumHeadingText = $sumTitle.' por '.($sumTable['group_label'] ?? 'Grupo');
    if (!empty($headersUppercase)) {
        $sumHeadingText = mb_strtoupper($sumHeadingText);
    }
    if ($sumTitleCase === 'upper') {
        $sumHeadingText = mb_strtoupper($sumHeadingText);
    } elseif ($sumTitleCase === 'lower') {
        $sumHeadingText = mb_strtolower($sumHeadingText);
        $sumHeadingText = mb_strtoupper(mb_substr($sumHeadingText, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($sumHeadingText, 1, null, 'UTF-8');
    }
    $sumRawMetricCols = count((array) ($sumTable['metric_columns'] ?? []));
    $sumRawFormulaCols = count((array) ($sumTable['formula_columns'] ?? []));
    if (($sumRawMetricCols + $sumRawFormulaCols) === 0) {
        $sumRawMetricCols = count((array) ($sumTable['metric_labels'] ?? []));
        $sumRawFormulaCols = count((array) ($sumTable['formula_labels'] ?? []));
    }
    $sumRowCount = count($sumTable['rows'] ?? []);
    $sumBy = (string) ($sumTable['group_by'] ?? 'microrregion');
    $sumLeadCount = 0;
    if ($sumShowItem) { $sumLeadCount++; }
    if ($sumBy === 'microrregion') {
        if ($sumShowDelegacion) { $sumLeadCount++; }
        if ($sumShowCabecera) { $sumLeadCount++; }
    } else {
        $sumLeadCount++;
        if ($sumShowDelegacion) { $sumLeadCount++; }
        if ($sumShowCabecera) { $sumLeadCount++; }
    }
    $sumLeadCount = max(1, $sumLeadCount);
    $sumColumnCount = max(2, $sumLeadCount + $sumRawMetricCols + $sumRawFormulaCols);
    $sumDensityScore = $sumRowCount + (int) ceil($sumColumnCount * 1.8) + (($orientation ?? 'portrait') === 'landscape' ? 4 : 0);
    $sumHeadingFontSizePx = max(10, min($sumTitleFontSizePx, $sumDensityScore >= 34 ? 11 : 12));
    $sumHeaderFontPx = max(7, $sumTableHeaderFontSizePx - ($sumDensityScore >= 34 ? 3 : 2));
    $sumCellFontPx = max(7, $sumTableCellFontSizePx - ($sumDensityScore >= 34 ? 2 : 1));
    $sumCellPadding = $sumDensityScore >= 34 ? '2px 3px' : '3px 4px';
@endphp
<p style="font-weight: bold; margin: 4px 0 4px 0; text-align: {{ $sumTitleAlign }}; font-size: {{ $sumHeadingFontSizePx }}px;">{{ $sumHeadingText }}</p>
@php
    $sumMetricColumns = is_array($sumTable['metric_columns'] ?? null) ? $sumTable['metric_columns'] : [];
    $sumFormulaColumns = is_array($sumTable['formula_columns'] ?? null) ? $sumTable['formula_columns'] : [];
    $sumCombinedColumns = [];
    foreach ($sumMetricColumns as $col) {
        $sumCombinedColumns[] = [
            'id' => (string) ($col['id'] ?? ''),
            'label' => (string) ($col['label'] ?? ''),
            'group' => trim((string) ($col['group'] ?? '')),
            'op' => 'metric',
            'include_total' => !array_key_exists('include_total', $col) || !empty($col['include_total']),
            'sort_order' => (int) ($col['sort_order'] ?? 0),
        ];
    }
    if ($sumCombinedColumns === [] && is_array($sumTable['metric_labels'] ?? null)) {
        foreach (($sumTable['metric_labels'] ?? []) as $id => $label) {
            $sumCombinedColumns[] = ['id' => (string) $id, 'label' => (string) $label, 'group' => '', 'op' => 'metric', 'include_total' => true, 'sort_order' => 0];
        }
    }
    foreach ($sumFormulaColumns as $col) {
        $sumCombinedColumns[] = [
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
    if ($sumFormulaColumns === [] && is_array($sumTable['formula_labels'] ?? null)) {
        foreach (($sumTable['formula_labels'] ?? []) as $id => $label) {
            $sumCombinedColumns[] = ['id' => (string) $id, 'label' => (string) $label, 'group' => '', 'op' => 'add', 'include_total' => true, 'sort_order' => 0];
        }
    }
    if ($sumCombinedColumns !== []) {
        usort($sumCombinedColumns, static function (array $a, array $b): int {
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
    $sumHasGroups = collect($sumCombinedColumns)->contains(fn ($c) => ((string) ($c['group'] ?? '')) !== '');
    $sumIncludeTotalsRow = !empty($sumIncludeTotalsRow) || !empty($sumTable['include_totals_row']);
    $sumTotalsBold = !array_key_exists('sumTotalsBold', get_defined_vars()) ? (!array_key_exists('totals_bold', $sumTable) || !empty($sumTable['totals_bold'])) : !empty($sumTotalsBold);
    $sumTotalsTextColor = (string) ($sumTotalsTextColor ?? ('#'.((string) ($sumTable['totals_text_color'] ?? '861E34'))));
    $sumGroupHeaderColors = is_array($groupHeaderColors ?? null) ? $groupHeaderColors : [];
    $sumLeadColumns = [];
    if ($sumShowItem) {
        $sumLeadColumns[] = ['key' => 'item', 'label' => $sumItemLabel];
    }
    if (($sumTable['group_by'] ?? 'microrregion') === 'microrregion') {
        if ($sumShowDelegacion) {
            $sumLeadColumns[] = ['key' => 'delegacion_numero', 'label' => $sumDelegacionLabel];
        }
        if ($sumShowCabecera) {
            $sumLeadColumns[] = ['key' => 'cabecera_microrregion', 'label' => $sumCabeceraLabel];
        }
    } else {
        $sumLeadColumns[] = ['key' => 'group', 'label' => (string) ($sumTable['group_label'] ?? 'Grupo')];
        if ($sumShowDelegacion) {
            $sumLeadColumns[] = ['key' => 'delegacion_numero', 'label' => $sumDelegacionLabel];
        }
        if ($sumShowCabecera) {
            $sumLeadColumns[] = ['key' => 'cabecera_microrregion', 'label' => $sumCabeceraLabel];
        }
    }
    if ($sumLeadColumns === []) {
        $sumLeadColumns[] = ['key' => 'group', 'label' => (string) ($sumTable['group_label'] ?? 'Grupo')];
    }
    $sumGroupSpans = [];
    if ($sumHasGroups) {
        foreach ($sumCombinedColumns as $col) {
            $g = (string) ($col['group'] ?? '');
            if (!empty($sumGroupSpans) && $sumGroupSpans[count($sumGroupSpans) - 1]['label'] === $g) {
                $sumGroupSpans[count($sumGroupSpans) - 1]['span']++;
            } else {
                $sumGroupSpans[] = ['label' => $g, 'span' => 1];
            }
        }
    }
    $sumTwoColumnsActive = !empty($sumTwoColumns ?? false) && count($sumTable['rows'] ?? []) >= 2;
    $sumAllRows = $sumTable['rows'] ?? [];
    $sumMidPoint = $sumTwoColumnsActive ? (int) ceil(count($sumAllRows) / 2) : count($sumAllRows);
    $sumRenderHalves = $sumTwoColumnsActive
        ? [array_slice($sumAllRows, 0, $sumMidPoint), array_slice($sumAllRows, $sumMidPoint)]
        : [$sumAllRows];
@endphp
@foreach($sumRenderHalves as $sumHalfRows)
    @if($sumTwoColumnsActive && $loop->first)
    <div style="display:flex;gap:8px;align-items:flex-start;">
    @endif
    @if(!$sumTwoColumnsActive && $loop->first)
    <div style="{{ $sumTableWrapStyle }}">
    @endif
    @if($sumTwoColumnsActive)
    <div style="flex:1;overflow:auto;">
    <table class="sum-table" style="width:100%; {{ $countTableInlineStyle }} --sum-header-fs: {{ $sumHeaderFontPx }}px; --sum-cell-fs: {{ $sumCellFontPx }}px; --sum-cell-pad: {{ $sumCellPadding }};">
    @else
    <table class="sum-table" style="{{ $sumTableStyle }} {{ $countTableInlineStyle }} --sum-header-fs: {{ $sumHeaderFontPx }}px; --sum-cell-fs: {{ $sumCellFontPx }}px; --sum-cell-pad: {{ $sumCellPadding }};">
    @endif
    <thead>
    @if($sumHasGroups)
    <tr>
        @foreach($sumLeadColumns as $lead)
            <th style="background:{{ $sumGroupColor }};color:#fff;"></th>
        @endforeach
        @foreach($sumGroupSpans as $gs)
            @php
                $sumGroupKey = mb_strtolower(trim((string) ($gs['label'] ?? '')));
                $sumGroupBg = $gs['label'] !== '' ? ($sumGroupHeaderColors[$sumGroupKey] ?? '#64748b') : '#334155';
            @endphp
            <th colspan="{{ $gs['span'] }}" style="background:{{ $sumGroupBg }};color:#fff;font-size:{{ $sumGroupHeaderFontSizePx }}px !important;">{{ $gs['label'] }}</th>
        @endforeach
    </tr>
    @endif
    <tr>
        @foreach($sumLeadColumns as $lead)
            <th style="background:{{ $sumGroupColor }};color:#fff;">{{ $lead['label'] }}</th>
        @endforeach
        @foreach ($sumCombinedColumns as $col)
            @php
                $sumHeadGroup = trim((string) ($col['group'] ?? ''));
                $sumHeadGroupKey = mb_strtolower($sumHeadGroup);
                $sumHeadBg = $sumHeadGroup !== '' ? ($sumGroupHeaderColors[$sumHeadGroupKey] ?? '#64748b') : '#475569';
            @endphp
            <th style="background:{{ $sumHeadBg }};color:#fff;">{{ $col['label'] }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach ($sumHalfRows as $row)
        <tr>
            @foreach($sumLeadColumns as $leadIndex => $lead)
                @php
                    $leadKey = (string) ($lead['key'] ?? 'group');
                    if ($leadKey === 'item') {
                        $leadVal = (string) ($loop->parent->index + 1);
                    } elseif ($leadKey === 'delegacion_numero') {
                        $leadVal = (string) ($row['mr_number'] ?? '');
                    } elseif ($leadKey === 'cabecera_microrregion') {
                        $leadVal = (string) ($row['mr_cabecera'] ?? '');
                    } else {
                        $leadVal = (string) ($row['group'] ?? '');
                    }
                @endphp
                <td>{{ $leadVal }}</td>
            @endforeach
            @foreach ($sumCombinedColumns as $col)
                @php
                    $id = (string) ($col['id'] ?? '');
                    $isMetric = (string) ($col['op'] ?? 'metric') === 'metric';
                    $val = $isMetric
                        ? (float) (($row['metrics'][$id] ?? 0.0))
                        : (float) (($row['formulas'][$id] ?? 0.0));
                    $txt = round($val, 2);
                    if (!$isMetric && (string) ($col['op'] ?? '') === 'percent') {
                        $txt = $txt.'%';
                    }
                @endphp
                <td>{{ $txt }}</td>
            @endforeach
        </tr>
    @endforeach
    @if($sumIncludeTotalsRow)
        @php
            $totalsLabel = !empty($headersUppercase) ? mb_strtoupper('Total') : 'Total';
        @endphp
        <tr>
            @foreach($sumLeadColumns as $leadIndex => $lead)
                <td style="color: {{ $sumTotalsTextColor }}; {{ $sumTotalsBold ? 'font-weight:700;' : '' }}">{{ $leadIndex === 0 ? $totalsLabel : '' }}</td>
            @endforeach
            @foreach ($sumCombinedColumns as $col)
                @php
                    $includeTotal = !array_key_exists('include_total', $col) || !empty($col['include_total']);
                    $totalVal = 0.0;
                    if ($includeTotal) {
                        $id = (string) ($col['id'] ?? '');
                        $op = (string) ($col['op'] ?? 'metric');
                        if ($op === 'percent') {
                            $metricIds = array_values(array_map('strval', (array) ($col['metric_ids'] ?? [])));
                            $numeratorMetricId = (string) ($metricIds[0] ?? '');
                            $baseMetricId = (string) ($col['base_metric_id'] ?? '');
                            $numeratorTotal = 0.0;
                            $baseTotal = 0.0;
                            if ($numeratorMetricId !== '' && $baseMetricId !== '') {
                                foreach ($sumHalfRows as $sumRow) {
                                    $numeratorTotal += (float) (($sumRow['metrics'][$numeratorMetricId] ?? 0.0));
                                    $baseTotal += (float) (($sumRow['metrics'][$baseMetricId] ?? 0.0));
                                }
                            }
                            $totalVal = $baseTotal !== 0.0 ? (($numeratorTotal / $baseTotal) * 100.0) : 0.0;
                        } else {
                            foreach ($sumHalfRows as $sumRow) {
                                $isMetric = $op === 'metric';
                                $totalVal += $isMetric
                                    ? (float) (($sumRow['metrics'][$id] ?? 0.0))
                                    : (float) (($sumRow['formulas'][$id] ?? 0.0));
                            }
                        }
                    }
                    $totalTxt = $includeTotal ? (string) round($totalVal, 2) : '';
                    if ($includeTotal && (string) ($col['op'] ?? '') === 'percent') {
                        $totalTxt .= '%';
                    }
                @endphp
                <td style="color: {{ $sumTotalsTextColor }}; {{ $sumTotalsBold ? 'font-weight:700;' : '' }}">{{ $totalTxt }}</td>
            @endforeach
        </tr>
    @endif
    </tbody>
</table>
    @if($sumTwoColumnsActive)
    </div>
    @endif
    @if(!$sumTwoColumnsActive && $loop->last)
    </div>
    @endif
    @if($sumTwoColumnsActive && $loop->last)
    </div>
    @endif
@endforeach
@endif

@php
    $hasSummaryContent = (!empty($includeTotalsTable) && !empty($totalsTable) && is_array($totalsTable) && !empty($totalsTable['columns']))
        || (!empty($countTable) && isset($countTable['groups']))
        || (!empty($sumTable) && !empty($sumTable['rows']) && is_array($sumTable['rows']));
@endphp

@if($hasSummaryContent)
<div class="summary-page-break"></div>
<table class="doc-head-table" role="presentation">
    @if (!empty($logoDataUri))
    <tr>
        <td colspan="2" style="text-align: left; vertical-align: bottom; padding-bottom: 2px;">
            <img class="doc-head-logo" src="{{ $logoDataUri }}" alt="Gobierno de Puebla">
        </td>
    </tr>
    <tr>
        <td colspan="2" style="padding-top: 10px; padding-bottom: 4px;">
            <h1 style="text-align: {{ ($titleAlign ?? 'center') === 'left' ? 'left' : (($titleAlign ?? 'center') === 'right' ? 'right' : 'center') }}; margin-bottom: 0;">{{ $title }}</h1>
        </td>
    </tr>
    <tr>
        <td colspan="2" style="text-align: right; font-size: 10px; padding-bottom: 10px;">
            @if(isset($fechaCorteStr))Fecha y hora de corte: {{ $fechaCorteStr }}@endif
        </td>
    </tr>
    @else
    <tr>
        <td class="doc-head-title-cell" style="padding-bottom: 4px;">
            <h1 style="text-align: {{ ($titleAlign ?? 'center') === 'left' ? 'left' : (($titleAlign ?? 'center') === 'right' ? 'right' : 'center') }}; margin-bottom: 0;">{{ $title }}</h1>
        </td>
    </tr>
    @if(isset($fechaCorteStr))
    <tr>
        <td style="text-align: right; font-size: 10px; padding-bottom: 10px;">Fecha y hora de corte: {{ $fechaCorteStr }}</td>
    </tr>
    @endif
    @endif
</table>
<p style="font-weight: bold; margin: 8px 0 4px 0; text-align: {{ $sectionLabelAlign }};">{{ $sectionLabel ?? 'Desglose' }}</p>
@endif

@php
    $tempEntries = $entries instanceof \Illuminate\Support\Collection ? $entries->values()->all() : array_values($entries);
    $colHeaders = $columns;
    $itemNumber = 1;
    $nCols = count($colHeaders);
    $columnWidthPercents = $columnWidthPercents ?? [];
    if ($nCols > 0 && (count($columnWidthPercents) !== $nCols)) {
        $columnWidthPercents = array_fill(0, $nCols, 100 / $nCols);
    }

    $groupSpans = [];
    $groupHeaderColors = is_array($groupHeaderColors ?? null) ? $groupHeaderColors : [];
    foreach ($colHeaders as $col) {
        $g = $col['group'] ?? '';
        if (!empty($groupSpans) && $groupSpans[count($groupSpans) - 1]['label'] === $g) {
            $groupSpans[count($groupSpans) - 1]['span']++;
        } else {
            $groupSpans[] = ['label' => $g, 'span' => 1];
        }
    }
    $hasAnyGroup = false;
    foreach ($groupSpans as $gs) { if ($gs['label'] !== '') $hasAnyGroup = true; }
@endphp

@if (count($tempEntries) === 0)
    <p style="margin-top: 8px;">Sin registros.</p>
@else
    <table class="data-table" style="{{ $dataTableStyle }}">
        <thead>
        @if($hasAnyGroup)
            <tr>
                @php $gColIdx = 0; @endphp
                @foreach($groupSpans as $gs)
                    @php
                        $span = (int) $gs['span'];
                        $pct = 0.0;
                        for ($si = 0; $si < $span; $si++) {
                            $pct += (float) ($columnWidthPercents[$gColIdx + $si] ?? 0);
                        }
                        $gColIdx += $span;
                    @endphp
                    @php
                        $groupKey = mb_strtolower(trim((string) ($gs['label'] ?? '')));
                        $groupBg = $gs['label'] !== '' ? ($groupHeaderColors[$groupKey] ?? '#64748b') : 'transparent';
                    @endphp
                    <th colspan="{{ $gs['span'] }}" style="background-color: {{ $groupBg }}; color: #fff; border: {{ $gs['label'] !== '' ? '1px solid #000' : 'none' }}; font-size: {{ $recordsGroupHeaderFontSizePx }}px !important;">
                        {!! nl2br(e((string) ($gs['label'] ?? ''))) !!}
                    </th>
                @endforeach
            </tr>
        @endif
        <tr>
            @foreach ($colHeaders as $col)
                @php
                    $groupLabel = trim((string) ($col['group'] ?? ''));
                    $groupKey = mb_strtolower($groupLabel);
                    $bg = $groupLabel !== ''
                        ? ($groupHeaderColors[$groupKey] ?? '#64748b')
                        : (!empty($col['color']) ? $resolveCss($col['color']) : '#861E34');
                    $pct   = (float) ($columnWidthPercents[$loop->index] ?? (100 / max(1, $nCols)));
                    $wStyle = 'width: '.$pct.'%;';
                @endphp
                <th style="background-color: {{ $bg }}; color: #fff; text-align: center; vertical-align: middle; {{ $wStyle }}">
                    {!! nl2br(e((string) ($col['label'] ?? ''))) !!}
                </th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach ($tempEntries as $entry)
            <tr>
                @foreach ($colHeaders as $col)
                    @php
                        $key = $col['key'];
                        $pct = (float) ($columnWidthPercents[$loop->index] ?? (100 / max(1, $nCols)));
                        $baseW = 'width: '.$pct.'%;';
                        $cellBoldStyle = !empty($col['content_bold']) ? 'font-weight:700;' : '';
                    @endphp
                    @if ($key === 'item')
                        <td style="{{ $baseW }} text-align: center; {{ $cellBoldStyle }}">{{ $itemNumber }}</td>
                        @php $itemNumber++; @endphp
                    @elseif ($key === 'microrregion')
                        @php
                            $lMeta  = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));
                            $lMrTxt = $lMeta['label'] ?? ($lMeta->label ?? 'Sin microrregión');
                        @endphp
                        {{-- Sin rowspan: Dompdf al partir la tabla entre páginas rompía columnas --}}
                        <td style="vertical-align: middle; text-align: center; {{ $baseW }} {{ $cellBoldStyle }}">{{ $lMrTxt }}</td>
                    @elseif ($key === 'delegacion_numero')
                        @php
                            $lMeta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));
                            $lMrNumber = (string) ($lMeta['number'] ?? ($lMeta->number ?? ''));
                        @endphp
                        <td style="vertical-align: middle; text-align: center; {{ $baseW }} {{ $cellBoldStyle }}">{{ $lMrNumber }}</td>
                    @elseif ($key === 'cabecera_microrregion')
                        @php
                            $lMeta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));
                            $lMrCabecera = (string) ($lMeta['cabecera'] ?? ($lMeta->cabecera ?? ''));
                        @endphp
                        <td style="vertical-align: middle; text-align: center; {{ $baseW }} {{ $cellBoldStyle }}">{{ $lMrCabecera }}</td>
                    @elseif (
                        is_string($key)
                        && str_starts_with($key, '__calc_')
                    )
                        @php
                            $calcCfg = is_array($col['_calc_config'] ?? null) ? $col['_calc_config'] : [];
                            $operationsOp = strtolower(trim((string) ($calcCfg['operation'] ?? 'add')));
                            if (!in_array($operationsOp, ['add', 'subtract', 'multiply', 'percent'], true)) {
                                $operationsOp = !array_key_exists('include_percent', $calcCfg) || !empty($calcCfg['include_percent']) ? 'percent' : 'add';
                            }
                            $operationsBaseKey = trim((string) ($calcCfg['base_field'] ?? $calcCfg['reference_field'] ?? ''));
                            $operationsSelectedKeys = is_array($calcCfg['operation_fields'] ?? null)
                                ? array_values(array_filter(array_map('strval', $calcCfg['operation_fields']), static fn (string $k): bool => $k !== ''))
                                : (is_array($calcCfg['fields'] ?? null)
                                    ? array_values(array_filter(array_map('strval', $calcCfg['fields']), static fn (string $k): bool => $k !== ''))
                                    : []);
                            $entryData = (array) ($entry->data ?? []);
                            $effectiveSelected = $operationsSelectedKeys;
                            if ($effectiveSelected === []) {
                                foreach ($colHeaders as $opCol) {
                                    $opKey = (string) ($opCol['key'] ?? '');
                                    if ($opKey === '' || in_array($opKey, ['item', 'microrregion', 'delegacion_numero', 'cabecera_microrregion'], true) || str_starts_with($opKey, '__calc_')) {
                                        continue;
                                    }
                                    $effectiveSelected[] = $opKey;
                                }
                            }
                            $baseRaw = $operationsBaseKey !== '' ? ($entryData[$operationsBaseKey] ?? null) : null;
                            $baseNum = $operationsExtractNumber($baseRaw);
                            $baseVal = $baseNum !== null ? $baseNum : 0.0;
                            $operationNums = [];
                            foreach ($effectiveSelected as $selectedKey) {
                                if ($selectedKey === '' || $selectedKey === $operationsBaseKey) {
                                    continue;
                                }
                                $n = $operationsExtractNumber($entryData[$selectedKey] ?? null);
                                if ($n !== null) {
                                    $operationNums[] = $n;
                                }
                            }
                            $operationsResult = null;
                            if ($operationsOp === 'add') {
                                $operationsResult = $baseVal + array_sum($operationNums);
                            } elseif ($operationsOp === 'subtract') {
                                $operationsResult = $baseVal - array_sum($operationNums);
                            } elseif ($operationsOp === 'multiply') {
                                $operationsResult = $baseVal * (empty($operationNums) ? 1.0 : array_reduce($operationNums, static fn (float $acc, float $n): float => $acc * $n, 1.0));
                            } elseif ($operationsOp === 'percent') {
                                $numerator = array_sum($operationNums);
                                $operationsResult = $baseVal != 0.0 ? (($numerator / $baseVal) * 100.0) : 0.0;
                            }

                            if ($operationsResult === null || !is_finite($operationsResult)) {
                                $operationsText = '';
                            } else {
                                $rounded = round($operationsResult, 2);
                                $operationsText = rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');
                                if ($operationsText === '-0') { $operationsText = '0'; }
                                if ($operationsOp === 'percent') { $operationsText .= '%'; }
                            }
                        @endphp
                        <td style="{{ $baseW }} text-align: center; vertical-align: middle; {{ $cellBoldStyle }}">{!! nl2br(e($operationsText)) !!}</td>
                    @else
                        @php
                            $val = $entry->data[$key] ?? null;
                            $fillMode = strtolower(trim((string) ($col['fill_empty_mode'] ?? 'none')));
                            $fillValue = (string) ($col['fill_empty_value'] ?? '');
                            $fieldType = (string) ($fieldTypesByKey[$key] ?? '');
                            $isImageType = $fieldType === 'image' || $fieldType === 'file' || $fieldType === 'foto';
                            if (!$isImageType && !in_array((string) $key, ['item', 'microrregion', 'delegacion_numero', 'cabecera_microrregion'], true) && in_array($fillMode, ['auto', 'custom'], true) && $operationsValueIsEmpty($val)) {
                                if ($fillMode === 'custom') {
                                    $val = $fillValue;
                                } else {
                                    $val = $isNumericFieldType($fieldType) ? 0 : 'S/R';
                                }
                            }
                            if (is_bool($val)) {
                                $cellText = $val ? 'Sí' : 'No';
                            } elseif (is_array($val)) {
                                $cellText = implode(', ', array_map(static fn ($v) => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), $val));
                            } elseif (is_scalar($val)) {
                                $cellText = (($fieldTypesByKey[$key] ?? '') === 'semaforo')
                                    ? (\App\Services\TemporaryModules\TemporaryModuleFieldService::labelForSemaforo((string) $val) ?: (string) $val)
                                    : (string) $val;
                            } else {
                                $cellText = '';
                            }
                            $imageSources = [];
                            $rawMediaValues = is_array($val)
                                ? array_values(array_filter($val, fn ($item) => is_string($item) && trim($item) !== ''))
                                : ((is_string($val) && trim($val) !== '') ? [trim($val)] : []);

                            if ($isImageType) {
                                foreach ($rawMediaValues as $rawMediaValue) {
                                    $lookupRaw = (string) $rawMediaValue;
                                    $lookupTrimmed = trim($lookupRaw);
                                    if ($lookupTrimmed === '') {
                                        continue;
                                    }

                                    $lookupNormalized = preg_replace('/[\x00-\x1F\x7F]+/u', '', $lookupTrimmed);
                                    $lookupNormalized = is_string($lookupNormalized) ? $lookupNormalized : $lookupTrimmed;
                                    $lookupNormalized = preg_replace('/\s*\/\s*/u', '/', $lookupNormalized);
                                    $lookupNormalized = is_string($lookupNormalized) ? $lookupNormalized : $lookupTrimmed;
                                    $lookupCompact = preg_replace('/\s+/u', '', $lookupNormalized);
                                    $lookupCompact = is_string($lookupCompact) ? $lookupCompact : $lookupNormalized;
                                    if (preg_match('~^temporary[\s_-]*modules/~iu', $lookupCompact)) {
                                        $lookupCompact = preg_replace('~^temporary[\s_-]*modules/~iu', 'temporary-modules/', $lookupCompact) ?? $lookupCompact;
                                    }
                                    $lookupAltA = str_replace('temporary_modules/', 'temporary-modules/', $lookupNormalized);
                                    $lookupAltB = str_replace('temporary-modules/', 'temporary_modules/', $lookupNormalized);
                                    $lookupAltC = str_replace('temporary_modules/', 'temporary-modules/', $lookupCompact);
                                    $lookupAltD = str_replace('temporary-modules/', 'temporary_modules/', $lookupCompact);

                                    $resolvedSource = $pdfImageDataByPath[$lookupRaw]
                                        ?? $pdfImageDataByPath[$lookupTrimmed]
                                        ?? $pdfImageDataByPath[$lookupNormalized]
                                        ?? $pdfImageDataByPath[$lookupCompact]
                                        ?? $pdfImageDataByPath[$lookupAltA]
                                        ?? $pdfImageDataByPath[$lookupAltB]
                                        ?? $pdfImageDataByPath[$lookupAltC]
                                        ?? $pdfImageDataByPath[$lookupAltD]
                                        ?? null;

                                    if (is_string($resolvedSource) && $resolvedSource !== '' && !in_array($resolvedSource, $imageSources, true)) {
                                        $imageSources[] = $resolvedSource;
                                    }
                                }
                            }
                            $imageFallbackLabel = '';
                            if ($isImageType && empty($imageSources) && !empty($rawMediaValues)) {
                                $imageFallbackLabel = count($rawMediaValues) > 1 ? 'Imágenes adjuntas' : 'Imagen adjunta';
                            }
                            $tdAlign = 'text-align: center; vertical-align: middle;';
                            $thumbWidth = count($imageSources) > 1 ? 52 : 110;
                            $thumbHeight = count($imageSources) > 1 ? 52 : 85;
                        @endphp
                        <td style="{{ $baseW }} {{ $tdAlign }} {{ $cellBoldStyle }}">
                            @if(!empty($imageSources))
                                <div style="display:block; text-align:center; white-space:nowrap;">
                                    @foreach ($imageSources as $imageSource)
                                        <img src="{{ $imageSource }}" alt="Imagen" style="max-width: {{ $thumbWidth }}px; max-height: {{ $thumbHeight }}px; display:inline-block; margin:2px; vertical-align:middle;">
                                    @endforeach
                                </div>
                            @elseif($imageFallbackLabel !== '')
                                <span style="font-size:8px; color:#6b7280;">{{ $imageFallbackLabel }}</span>
                            @else
                                {{ $cellText }}
                            @endif
                        </td>
                    @endif
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
</body>
</html>
