<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $documentTitle }}</title>
    {{-- Imágenes: rutas relativas a public/ (Dompdf::setBasePath(public_path())). Fuentes: FontMetrics::registerFont en AgendaController. --}}
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
        /* Dompdf aplica estilos de agente a h1/h2… (suele forzar serif); forzar la misma familia que body. */
        .pdf-head,
        .pdf-head h1,
        .pdf-head .sub,
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
            text-align: center;
            margin-bottom: 5mm;
            padding: 0 2mm 3mm;
            border-bottom: 0.4pt solid rgba(72, 71, 71, 0.2);
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
        /* Tabla fija: Dompdf maneja mal floats + %; evita desbordes a la derecha */
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
                url('images/Texturas_1A-Tlaloc_rojo.png');
        }
        .card-h--pre_gira {
            background-color: #3d3528;
            background-image: linear-gradient(180deg, rgba(45, 38, 28, 0.5), rgba(45, 38, 28, 0.35)),
                url('images/Texturas_1A-Tlaloc_beige.png');
        }
        .card-h--gira {
            background-color: #1e3d32;
            background-image: linear-gradient(180deg, rgba(18, 32, 28, 0.5), rgba(18, 32, 28, 0.35)),
                url('images/Texturas_1A-Tlaloc_verde.png');
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
        .card-h--agenda .card-daynum {
            color: #c79b66;
        }
        .card-h--pre_gira .card-daynum {
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
            font-size: 6pt;
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
            background-image: url('images/Texturas_2C-Quetzalcoatl_blanco.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        /* Título: más presencia (Black 900); interlineado ajustado para caber en A4 */
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
        /* Etiqueta: .agenda-cal-card-desc-label */
        .card-lbl {
            font-size: 5.75pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #6b6a6a;
            margin: 0 0 0.45mm;
        }
        /* Lugar: .agenda-cal-card-address */
        .card-address {
            font-size: 7.1pt;
            font-weight: 600;
            color: #484747;
            margin: 0 0 0.85mm;
            line-height: 1.36;
        }
        /* Detalle: .agenda-cal-card-desc */
        .card-desc {
            font-size: 6.95pt;
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
    <div class="pdf-head">
        <h1>{{ $documentTitle }}</h1>
        @if(!empty($filtersNote))
            <div class="sub">{{ $filtersNote }}</div>
        @endif
    </div>

    @if(count($rows) === 0)
        <p class="empty-note">No hay fichas con los filtros seleccionados.</p>
    @else
        @foreach($rows as $row)
            <table class="pdf-row-table">
            <tr>
                @foreach($row as $card)
                    @php
                        $kind = $card['kind'] ?? 'agenda';
                        $kindLabel = match ($kind) {
                            'pre_gira' => 'Pre-gira',
                            'gira' => 'Gira',
                            default => 'Agenda',
                        };
                    @endphp
                    <td class="pdf-cell" style="width: {{ 100 / $cols }}%;">
                        <div class="card">
                            <div class="card-h card-h--{{ $kind }}">
                                <div class="card-h-top">
                                    <div class="card-h-left">
                                        <span class="card-kind">{{ $kindLabel }}</span>
                                    </div>
                                    <div class="card-h-right">
                                        <div class="card-daynum">{{ $card['badge_day'] }}</div>
                                        @if(!empty($card['month_year_label']))
                                            <div class="card-my">{{ strtoupper($card['month_year_label']) }}</div>
                                        @endif
                                        @if(!empty($card['hora_ficha']) && in_array($kind, ['gira', 'pre_gira'], true))
                                            <div class="card-time">{{ $card['hora_ficha'] }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="card-title">{{ $card['title'] }}</p>
                                @if(!empty($card['lugar']) || !empty($card['descripcion']))
                                    <table class="card-loc" role="presentation">
                                        <tr>
                                            <td class="card-loc-ico" aria-hidden="true">
                                                <img src="images/agenda-pin-ubicacion.svg" class="card-loc-img" alt="">
                                            </td>
                                            <td class="card-loc-body">
                                                <p class="card-lbl">Ubicación y detalle</p>
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
        @endforeach
    @endif
</body>
</html>
