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
            font-size: 11px;
            margin: 24px;
        }
        h1 {
            font-size: 18px;
            color: #861E34;
            text-align: center;
            margin: 0 0 12px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            @if(isset($stretch) && $stretch)
            table-layout: fixed;
            @endif
        }
        th, td {
            border: 1px solid #999;
            padding: 4px 6px;
            vertical-align: top;
            word-wrap: break-word;
            word-break: break-all;
        }
        th {
            background: #861E34;
            color: #fff;
            font-weight: bold;
            text-align: left;
        }
        tbody tr:nth-child(even) td {
            background: #f7f7f7;
        }
    </style>
</head>
<body>
<h1>{{ $title }}</h1>
<table>
    <thead>
    <tr>
        @foreach ($columns as $col)
            <th>{{ $col['label'] }}</th>
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

