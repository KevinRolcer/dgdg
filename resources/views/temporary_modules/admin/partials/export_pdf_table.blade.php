@php
    use Illuminate\Support\Arr;
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
        table {
            width: 100% !important;
            max-width: 100% !important;
            border-collapse: collapse;
            table-layout: fixed; /* Forzamos fixed para evitar que las celdas empujen la tabla fuera de la hoja */
        }
        th, td {
            border: 1px solid #999;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
            word-break: break-all;
            overflow: hidden;
        }
        th {
            background: #861E34;
            color: #fff;
            font-weight: bold;
            text-align: center;
        }
        tbody tr:nth-child(even) td {
            background: #fdfdfd;
        }
        .count-table {
            table-layout: auto; /* La tabla de conteo suele ser pequeña, auto está bien */
            margin-bottom: 20px;
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
    $vars = ['var(--clr-primary)' => '#861E34', 'var(--clr-secondary)' => '#2d5a27', 'var(--clr-accent)' => '#c9a227'];
    $resolveCss = function ($css) use ($vars) {
        return isset($vars[$css]) ? $vars[$css] : (str_starts_with($css ?? '', '#') ? $css : '#861E34');
    };
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
<table>
    <thead>
    <tr>
        @foreach ($columns as $idx => $col)
            @php
                $bg = !empty($col['color']) ? $resolveCss($col['color']) : '#861E34';
                $key = $col['key'] ?? '';
                $wStyle = '';
                if ($key === 'item') $wStyle = 'width: 30px;';
                elseif ($key === 'microrregion') $wStyle = 'width: 80px;';
                elseif ($key === 'municipio') $wStyle = 'width: 80px;';
            @endphp
            <th style="background-color: {{ $bg }}; color: #fff; text-align: center; vertical-align: middle; {{ $wStyle }}">
                {{ $col['label'] }}
            </th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @php $itemNumber = 1; @endphp
    @foreach ($entries as $entry)
        <tr>
            @foreach ($columns as $col)
                @php $key = $col['key']; @endphp
                @if ($key === 'item')
                    <td>{{ $itemNumber }}</td>
                    @php $itemNumber++; @endphp
                @elseif ($key === 'microrregion')
                    @php
                        $meta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));
                        $text = $meta['label'] ?? ($meta->label ?? 'Sin microrregión');
                    @endphp
                    <td>{{ $text }}</td>
                @else
                    @php
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
                    @endphp
                    <td>{{ $text }}</td>
                @endif
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>

