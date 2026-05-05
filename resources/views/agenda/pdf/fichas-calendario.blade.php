<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $documentTitle }}</title>
    <style>
        {{-- @@ escapa @ para Blade; @page en CSS no debe interpretarse como directiva @page --}}
        @@page { margin: 14mm 17mm; }
        * { box-sizing: border-box; }

        html, body {
            font-family: '{{ $pdfFontFamily }}', DejaVu Sans, Helvetica, sans-serif;
            font-size: 7.5pt;
            line-height: 1.35;
            color: #484747;
            margin: 0;
        }
        .pdf-head,
        .pdf-doc-table,
        .pdf-head h1,
        .pdf-head .sub,
        .pdf-logo,
        .empty-note,
        .card,
        .card table,
        .card td,
        .card th,
        .card p,
        .card div,
        .card span {
            font-family: '{{ $pdfFontFamily }}', DejaVu Sans, Helvetica, sans-serif;
        }
        .pdf-head {
            position: relative;
            text-align: center;
            margin-bottom: 5.5mm;
            min-height: 17mm;
            padding: 1mm 2mm 4mm 58mm;
            border-bottom: 0.4pt solid rgba(72, 71, 71, 0.2);
        }
        .pdf-doc-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .pdf-doc-table thead {
            display: table-header-group;
        }
        .pdf-doc-table tbody {
            display: table-row-group;
        }
        .pdf-doc-table > thead > tr > td,
        .pdf-doc-table > tbody > tr > td {
            padding: 0;
        }
        .pdf-content-row {
            page-break-inside: avoid;
        }
        .pdf-logo {
            position: absolute;
            left: 0;
            top: 1mm;
            width: 104mm;
            height: auto;
        }
        .pdf-head h1 {
            margin: 0 0 1.2mm;
            font-size: 11.5pt;
            font-weight: 900;
            color: #5f1b2d;
            letter-spacing: -0.02em;
            line-height: 1.25;
            word-wrap: break-word;
        }
        .pdf-head .sub {
            font-size: 8.25pt;
            color: #6b6a6a;
            font-weight: 500;
            letter-spacing: 0.02em;
        }
        .pdf-row-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: fixed;
            margin: 0 0 3.5mm;
        }
        .pdf-cell {
            vertical-align: top;
            padding: 0 2.25mm;
            width: 50%;
        }
        .pdf-cell--empty {
            padding: 0 2.25mm;
        }
        .card {
            border: 0.5pt solid rgba(72, 71, 71, 0.14);
            border-radius: 2.2mm;
            overflow: hidden;
            page-break-inside: avoid;
            margin-bottom: 2mm;
            background: #fff;
            max-width: 100%;
        }
        .card-h {
            position: relative;
            padding: 2.65mm 3.1mm 2.25mm;
            color: #fff;
            font-size: 7pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            line-height: 1.2;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        .card-h--agenda {
            background-color: #4a1f28;
            background-image: linear-gradient(180deg, rgba(40, 22, 28, 0.5), rgba(40, 22, 28, 0.35)),
                url('images/Texturas/Texturas_1A-Tlaloc_rojo.png');
        }
        .card-h--pre_gira {
            background-color: #3d3528;
            background-image: linear-gradient(180deg, rgba(45, 38, 28, 0.5), rgba(45, 38, 28, 0.35)),
                url('images/Texturas/Texturas_1A-Tlaloc_beige.png');
        }
        .card-h--gira {
            background-color: #1e3d32;
            background-image: linear-gradient(180deg, rgba(18, 32, 28, 0.5), rgba(18, 32, 28, 0.35)),
                url('images/Texturas/Texturas_1A-Tlaloc_verde.png');
        }
        .card-h--personalizada,
        .card-h--bg-rojo {
            background-color: #4a1f28;
            background-image: linear-gradient(180deg, rgba(40, 22, 28, 0.5), rgba(40, 22, 28, 0.35)),
                url('images/Texturas/Texturas_1C-Tlaloc_rojo.png');
        }
        .card-h--bg-tlaloc_a_rojo {
            background-color: #4a1f28;
            background-image: linear-gradient(180deg, rgba(40, 22, 28, 0.5), rgba(40, 22, 28, 0.35)),
                url('images/Texturas/Texturas_1A-Tlaloc_rojo.png');
        }
        .card-h--bg-beige {
            background-color: #3d3528;
            background-image: linear-gradient(180deg, rgba(45, 38, 28, 0.5), rgba(45, 38, 28, 0.35)),
                url('images/Texturas/Texturas_1C-Tlaloc_beige.png');
        }
        .card-h--bg-tlaloc_a_beige {
            background-color: #3d3528;
            background-image: linear-gradient(180deg, rgba(45, 38, 28, 0.5), rgba(45, 38, 28, 0.35)),
                url('images/Texturas/Texturas_1A-Tlaloc_beige.png');
        }
        .card-h--bg-blanco {
            background-color: #f4f4f4;
            background-image: linear-gradient(180deg, rgba(255, 255, 255, 0.32), rgba(255, 255, 255, 0.16)),
                url('images/Texturas/Texturas_1C-Tlaloc_blanco.png');
        }
        .card-h--bg-verde {
            background-color: #1e3d32;
            background-image: linear-gradient(180deg, rgba(18, 32, 28, 0.5), rgba(18, 32, 28, 0.35)),
                url('images/Texturas/Texturas_1C-Tlaloc_verde.png');
        }
        .card-h--bg-tlaloc_a_verde {
            background-color: #1e3d32;
            background-image: linear-gradient(180deg, rgba(18, 32, 28, 0.5), rgba(18, 32, 28, 0.35)),
                url('images/Texturas/Texturas_1A-Tlaloc_verde.png');
        }
        .card-h-top {
            display: table;
            width: 100%;
        }
        .card-h-left, .card-h-right {
            display: table-cell;
            vertical-align: middle;
        }
        .card-h-right {
            text-align: right;
            vertical-align: middle;
            width: 42%;
            max-width: 42%;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .card-kind {
            display: inline-block;
            font-size: 14pt;
            font-weight: 900;
            letter-spacing: 0.1em;
            line-height: 1.08;
        }
        .card-daynum {
            font-size: 23pt;
            font-weight: 900;
            line-height: 0.88;
            letter-spacing: -0.045em;
        }
        .card-h--gira .card-daynum,
        .card-h--agenda .card-daynum,
        .card-h--personalizada .card-daynum {
            color: #c79b66;
        }
        .card-h--pre_gira .card-daynum,
        .card-h--bg-beige .card-daynum,
        .card-h--bg-tlaloc_a_beige .card-daynum {
            color: #5f1b2d;
        }
        .card-h--bg-blanco .card-kind,
        .card-h--bg-blanco .card-my,
        .card-h--bg-blanco .card-time {
            color: #5f1b2d;
        }
        .card-h--bg-blanco .card-daynum {
            color: #5f1b2d;
        }
        .card-my {
            font-size: 7pt;
            font-weight: 800;
            margin-top: 0.35mm;
            padding-top: 0.15mm;
            color: #fff;
            letter-spacing: 0.06em;
            line-height: 1.25;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .card-time {
            font-size: 7.6pt;
            font-weight: 600;
            margin-top: 0.5mm;
            color: #fff;
            letter-spacing: 0.04em;
            line-height: 1.2;
            text-transform: lowercase;
        }
        .card-h--gira .card-time,
        .card-h--pre_gira .card-time {
            color: #fff;
        }
        .card-body {
            margin-top: -1.8mm;
            padding: 2.4mm 2.85mm 2.5mm;
            border-radius: 2.4mm 2.4mm 2.2mm 2.2mm;
            background-color: #fdfdfd;
            background-image: url('images/Texturas/Texturas_2C-Quetzalcoatl_blanco.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        .card-body--white-bg {
            box-shadow: 0 -7mm 14mm rgba(0, 0, 0, 0.08);
        }
        .card-title {
            font-size: 11.35pt;
            font-weight: 900;
            color: #5f1b2d;
            margin: 0 0 1.15mm;
            line-height: 1.16;
            letter-spacing: -0.025em;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .card-loc {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 1mm;
        }
        .card-loc td {
            vertical-align: top;
            padding: 0;
        }
        .card-loc-ico {
            width: 3.2mm;
            padding-right: 1mm;
            vertical-align: top;
        }
        .card-loc-img {
            display: block;
            width: 2.45mm;
            height: 3.25mm;
            margin-top: 0.55mm;
        }
        .card-loc-body {
            padding: 0;
        }
        /* Lugar: .agenda-cal-card-address */
        .card-address {
            font-size: 10pt;
            font-weight: 600;
            color: #484747;
            margin: 0 0 0.7mm;
            line-height: 1.36;
        }
        .card-desc {
            font-size: 8.35pt;
            font-weight: 400;
            color: #6b6a6a;
            margin: 0 0 0.85mm;
            line-height: 1.38;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .card-aforo {
            font-size: 6.1pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6b6a6a;
            margin: 0.85mm 0 0;
            text-align: right;
        }
        .empty-note {
            text-align: center;
            color: #888;
            padding: 8mm;
            font-size: 9pt;
        }
    </style>
</head>
<body>
    <table class="pdf-doc-table" role="presentation">
        <thead>
            <tr>
                <td>
                    <div class="pdf-head">
                        <img src="images/LogoSegobHorizontal.png" class="pdf-logo" alt="">
                        <h1>{{ $documentTitle }}</h1>
                        @if(!empty($documentSubtitle ?? ''))
                            <div class="sub">{{ $documentSubtitle }}</div>
                        @endif
                    </div>
                </td>
            </tr>
        </thead>
        <tbody>
            @if(count($rows) === 0)
                <tr>
                    <td>
                        <p class="empty-note">No hay fichas con los filtros seleccionados.</p>
                    </td>
                </tr>
            @else
                @foreach($rows as $row)
                    <tr class="pdf-content-row">
                        <td>
                            <table class="pdf-row-table" role="presentation">
                                <tr>
                                    @foreach($row as $card)
                                        @php
                                            $kind = $card['kind'] ?? 'agenda';
                                            $kindLabel = $card['kind_label'] ?? match ($kind) {
                                                'pre_gira' => 'Pre-gira',
                                                'gira' => 'Gira',
                                                'personalizada' => 'Ficha personalizada',
                                                default => 'Agenda',
                                            };
                                            $fichaBg = $kind === 'personalizada' ? (string) ($card['ficha_bg'] ?? '') : '';
                                            $fichaBgFile = $fichaBg !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $fichaBg) && file_exists(public_path('images/Texturas/'.$fichaBg.'.png')) ? $fichaBg : '';
                                            $fichaBgStyle = $fichaBgFile !== '' ? "background-image: url('images/Texturas/{$fichaBgFile}.png');" : '';
                                            $isWhiteFichaBg = $fichaBgFile !== '' && preg_match('/blanco/i', $fichaBgFile);
                                        @endphp
                                        <td class="pdf-cell" style="width: {{ 100 / $cols }}%;">
                                            <div class="card">
                                                <div class="card-h card-h--{{ $kind }}{{ $isWhiteFichaBg ? ' card-h--bg-blanco' : '' }}" style="{{ $fichaBgStyle }}">
                                                    <div class="card-h-top">
                                                        <div class="card-h-left">
                                                            <span class="card-kind">{{ $kindLabel }}</span>
                                                        </div>
                                                        <div class="card-h-right">
                                                            <div class="card-daynum">{{ $card['badge_day'] }}</div>
                                                            @if(!empty($card['month_year_label']))
                                                                <div class="card-my">{{ strtoupper($card['month_year_label']) }}</div>
                                                            @endif
                                                            @if(!empty($card['hora_ficha']))
                                                                <div class="card-time">{{ $card['hora_ficha'] }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card-body{{ $isWhiteFichaBg ? ' card-body--white-bg' : '' }}">
                                                    <p class="card-title">{{ $card['title'] }}</p>
                                                    @if(!empty($card['lugar']) || !empty($card['descripcion']))
                                                        <table class="card-loc" role="presentation">
                                                            <tr>
                                                                <td class="card-loc-ico" aria-hidden="true">
                                                                    <img src="images/agenda-pin-ubicacion.svg" class="card-loc-img" alt="">
                                                                </td>
                                                                <td class="card-loc-body">
                                                                    @if(!empty($card['lugar']))
                                                                        <p class="card-address">{{ $card['lugar'] }}</p>
                                                                    @endif
                                                                    @if(!empty($card['descripcion']))
                                                                        <p class="card-desc">{{ $card['descripcion'] }}</p>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    @endif
                                                    @if(!empty($card['aforo_label']))
                                                        <p class="card-aforo">{{ $card['aforo_label'] }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    @endforeach
                                    @for($pad = count($row); $pad < $cols; $pad++)
                                        <td class="pdf-cell pdf-cell--empty" style="width: {{ 100 / $cols }}%;"></td>
                                    @endfor
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
</body>
</html>
