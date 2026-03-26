@php
    $fieldTypesByKey = $fieldTypesByKey ?? [];
    $varsCss = ['var(--clr-primary)' => '#861E34', 'var(--clr-secondary)' => '#2d5a27', 'var(--clr-accent)' => '#c9a227'];
    $resolveCss = function ($css) use ($varsCss) {
        return isset($varsCss[$css]) ? $varsCss[$css] : (str_starts_with($css ?? '', '#') ? $css : '#861E34');
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 9px;
            margin: 15px;
            color: #333;
        }
        h1 {
            font-size: 16px;
            color: #861E34;
            text-align: center;
            margin: 0 0 8px 0;
        }
        table.data-table {
            width: 100% !important;
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
            word-wrap: break-word;
            overflow: visible;
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
            width: 100% !important;
            border-collapse: collapse;
            table-layout: auto;
            margin-bottom: 20px;
        }
        .count-table tr {
            page-break-inside: avoid;
        }
        .count-table th { background: #861E34; color: #fff; text-align: center; font-size: 8px; }
        .count-table td { text-align: center; color: #c00; font-weight: bold; font-size: 10px; }
    </style>
</head>
<body>
<h1 style="margin-bottom: 2px;">{{ $title }}</h1>
@if(isset($fechaCorteStr))
    <p style="text-align: right; margin: 0 0 10px 0; font-size: 10px;">Fecha y hora de corte: {{ $fechaCorteStr }}</p>
@endif

@if(!empty($countTable) && isset($countTable['groups']))
@php
    $countTableColorKeys = $countTableColorKeys ?? [];
    $countTableColors = $countTableColors ?? [];
    $countTableResolveColor = function ($index, $rowNum, $valueLabel = null) use ($countTableColorKeys, $countTableColors, $resolveCss) {
        $key = $countTableColorKeys[$index] ?? null;
        if ($key === null) return $rowNum === 1 ? '#861E34' : '#2d5a27';
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
@endphp
<table class="count-table" style="margin-bottom: 16px;">
    <thead>
    <tr>
        @foreach ($countTable['groups'] as $gi => $group)
            @php
                $key = $countTableColorKeys[$gi] ?? '';
                $includePct = !empty($countTableColors[$key]['showPct']);
                $numValues = count($group['values']);
                $span = $includePct ? $numValues * 2 : $numValues;
                $isRedundant = ($gi === 0 || ($numValues === 1 && (trim((string)($group['values'][0]['label'] ?? '')) === '' || trim((string)($group['values'][0]['label'] ?? '')) === trim((string)($group['label'] ?? '')))));
            @endphp
            <th colspan="{{ $span }}" @if($isRedundant && !$includePct) rowspan="2" @endif style="background-color: {{ $countTableResolveColor($gi, 1) }}; color: #fff;">{{ $group['label'] }}</th>
        @endforeach
    </tr>
    <tr>
        @foreach ($countTable['groups'] as $gi => $group)
            @php
                $key = $countTableColorKeys[$gi] ?? '';
                $includePct = !empty($countTableColors[$key]['showPct']);
                $numValues = count($group['values']);
                $isRedundant = ($gi === 0 || ($numValues === 1 && (trim((string)($group['values'][0]['label'] ?? '')) === '' || trim((string)($group['values'][0]['label'] ?? '')) === trim((string)($group['label'] ?? '')))));
            @endphp
            @foreach ($group['values'] as $v)
                @php $subLabel = $v['label'] !== '' ? $v['label'] : $group['label']; @endphp
                @if($isRedundant && !$includePct)
                    @continue
                @endif
                <th @if($includePct) colspan="2" @endif style="background-color: {{ $countTableResolveColor($gi, 2, $subLabel) }}; color: #fff;">
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
                $key = $countTableColorKeys[$gi] ?? '';
                $includePct = !empty($countTableColors[$key]['showPct']);
                $gTotal = array_sum(array_column($group['values'], 'count'));
            @endphp
            @foreach ($group['values'] as $v)
                <td>{{ $v['count'] }}</td>
                @if($includePct)
                    <td style="font-size: 9px; color: #666;">
                        {{ $gTotal > 0 ? round(($v['count'] / $gTotal) * 100, 2) : 0 }}%
                    </td>
                @endif
            @endforeach
        @endforeach
    </tr>
    </tbody>
</table>
<p style="font-weight: bold; margin: 8px 0 4px 0;">Desglose</p>
@endif

@php
    $tempEntries = $entries instanceof \Illuminate\Support\Collection ? $entries->values()->all() : array_values($entries);
    $colHeaders = $columns;
    $itemNumber = 1;
@endphp

@if (count($tempEntries) === 0)
    <p style="margin-top: 8px;">Sin registros.</p>
@else
    <table class="data-table">
        <thead>
        <tr>
            @foreach ($colHeaders as $col)
                @php
                    $bg    = !empty($col['color']) ? $resolveCss($col['color']) : '#861E34';
                    $key   = $col['key'] ?? '';
                    $wStyle = '';
                    if ($key === 'item')         $wStyle = 'width: 30pt;';
                    elseif ($key === 'microrregion') $wStyle = 'width: 90pt;';
                    elseif ($key === 'municipio')    $wStyle = 'width: 90pt;';
                    elseif ($key === 'estatus')      $wStyle = 'width: 60pt;';
                @endphp
                <th style="background-color: {{ $bg }}; color: #fff; text-align: center; vertical-align: middle; {{ $wStyle }}">
                    {{ $col['label'] }}
                </th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach ($tempEntries as $entry)
            <tr>
                @foreach ($colHeaders as $col)
                    @php $key = $col['key']; @endphp
                    @if ($key === 'item')
                        <td style="width: 30pt; text-align: center;">{{ $itemNumber }}</td>
                        @php $itemNumber++; @endphp
                    @elseif ($key === 'microrregion')
                        @php
                            $lMeta  = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));
                            $lMrTxt = $lMeta['label'] ?? ($lMeta->label ?? 'Sin microrregión');
                        @endphp
                        {{-- Sin rowspan: Dompdf al partir la tabla entre páginas rompía columnas --}}
                        <td style="vertical-align: middle; text-align: center; width: 90pt;">{{ $lMrTxt }}</td>
                    @else
                        @php
                            $val = $entry->data[$key] ?? null;
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
                            $tdW = '';
                            if ($key === 'municipio') $tdW = 'width: 90pt; text-align: left;';
                            if ($key === 'estatus')   $tdW = 'width: 60pt; text-align: center;';
                        @endphp
                        <td style="{{ $tdW }}">{{ $cellText }}</td>
                    @endif
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
</body>
</html>
