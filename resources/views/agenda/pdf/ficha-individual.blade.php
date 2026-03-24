<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $documentTitle }}</title>
    <style>
        {{-- @@ evita que Blade interprete @page --}}
        @@page {
            margin: 0;
            size: a4 portrait;
        }
        * { box-sizing: border-box; }

        html, body {
            font-family: '{{ $pdfFontFamily }}', DejaVu Sans, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
        }

        /*
         * Una ficha = una hoja; hijos absolutos no alargan el flujo (evita 2ª página).
         * Misma geometría que la vista previa web (.agenda-ficha-card-body): top 50%, -110mm, 220mm + flex vertical.
         */
        .ficha {
            width: 210mm;
            height: 297mm;
            min-height: 297mm;
            max-height: 297mm;
            position: relative;
            overflow: hidden;
            margin: 0 auto;
            padding: 0;
            page-break-after: always;
            page-break-inside: avoid;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .ficha:last-child {
            page-break-after: auto;
        }

        .ficha--pre_gira {
            background-color: #f8f8f8;
            background-image: url('images/Texturas_1C-Tlaloc_blanco.png');
        }
        .ficha--gira {
            background-color: #f0f7f4;
            background-image: url('images/Texturas_1C-Tlaloc_verde.png');
        }
        .ficha--agenda {
            background-color: #fdf2f2;
            background-image: url('images/Texturas_1C-Tlaloc_rojo.png');
        }

        .ficha__body-wrap {
            position: absolute;
            top: 50%;
            left: 14mm;
            right: 14mm;
            margin-top: -110mm;
            height: 220mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .ficha__body-inner {
            width: 100%;
            text-align: center;
        }

        .ficha__body-cell {
            text-align: center;
            width: 100%;
        }

        .ficha--sparse .ficha__body-cell {
            /* Centrado optimizado para contenido escaso */
        }

        .ficha__logo {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 10mm;
            height: auto;
            text-align: center;
            z-index: 10;
        }

        .ficha__logo img {
            display: block;
            /* Se amplia de 20mm a 28mm para mayor impacto visual */
            max-height: 28mm;
            width: auto;
            margin: 0 auto;
        }

        .eyebrow {
            font-size: 13pt;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: #6b6a6a;
            margin: 0 0 4mm;
        }

        .ficha--gira .eyebrow,
        .ficha--agenda .eyebrow {
            color: #eee;
        }

        .title {
            font-size: 24pt;
            font-weight: 900;
            line-height: 1.15;
            margin: 0 0 8mm;
            letter-spacing: -0.01em;
            text-transform: uppercase;
            width: 100%;
            max-width: 100%;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .ficha--pre_gira .title { color: #3a3a3a; }
        .ficha--gira .title,
        .ficha--agenda .title { color: #f0c38e; }

        .ficha--sparse .eyebrow {
            font-size: 15pt;
            margin-bottom: 7mm;
        }
        .ficha--sparse .title {
            font-size: 32pt;
            margin-bottom: 9mm;
            line-height: 1.06;
        }
        .ficha--sparse .date-box .date-text {
            font-size: 19pt;
        }
        .ficha--sparse .date-box .date-time {
            font-size: 17pt;
        }
        .ficha--sparse .label {
            font-size: 9pt;
        }
        .ficha--sparse .value {
            font-size: 15pt;
        }

        .detail-group {
            margin-bottom: 3.5mm;
            margin-top: 0;
        }

        .label {
            font-size: 8.2pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #888;
            margin: 0 0 1.2mm;
        }
        .ficha--gira .label,
        .ficha--agenda .label {
            color: #ccc;
        }

        .value {
            font-size: 13pt;
            font-weight: 600;
            color: #444;
            max-width: 100%;
            margin: 0 auto;
            word-wrap: break-word;
        }
        .ficha--gira .value,
        .ficha--agenda .value {
            color: #fff;
        }

        .description {
            font-size: 12pt;
            font-weight: 400;
            line-height: 1.42;
            color: #555;
            max-width: 98%;
            margin: 3.5mm auto 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .ficha--gira .description,
        .ficha--agenda .description {
            color: #eee;
        }

        .date-box {
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            padding: 3mm 8mm;
            margin: 4mm auto;
            width: fit-content;
            min-width: 180px;
        }
        .ficha--gira .date-box,
        .ficha--agenda .date-box {
            border-color: rgba(255, 255, 255, 0.22);
        }

        .date-box .date-text {
            font-size: 16pt;
            font-weight: 800;
            color: #333;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .date-box .date-time {
            font-size: 15pt;
            font-weight: 600;
            color: #333;
            text-transform: lowercase;
            letter-spacing: 0.04em;
            margin-top: 1.8mm;
        }
        .ficha--gira .date-text,
        .ficha--agenda .date-text {
            color: #fff;
        }
        .ficha--gira .date-time,
        .ficha--agenda .date-time {
            color: rgba(255, 255, 255, 0.92);
        }

        .aforo-line {
            margin-top: 3.5mm;
            font-size: 10pt;
            font-weight: 700;
            color: #777;
            text-transform: uppercase;
        }
        .ficha--gira .aforo-line,
        .ficha--agenda .aforo-line {
            color: rgba(255, 255, 255, 0.85);
        }
    </style>
</head>
<body>
@foreach ($rows as $row)
    @foreach ($row as $card)
        @php
            $kind = $card['kind'] ?? 'agenda';
            $kindLabel = match ($kind) {
                'pre_gira' => 'Pre-Gira',
                'gira' => 'Gira',
                default => 'Agenda',
            };
            $logoVersion = ($kind === 'pre_gira') ? '1' : '2';
            $logoFile = "Gobierno de Puebla_{$logoVersion}-Versión vertical.png";
            $textBulk = mb_strlen(trim((string) ($card['title'] ?? '')))
                + mb_strlen(trim((string) ($card['lugar'] ?? '')))
                + mb_strlen(trim((string) ($card['descripcion'] ?? '')));
            $sparseContent = $textBulk < 220;
        @endphp
        <div class="ficha ficha--{{ $kind }}{{ $sparseContent ? ' ficha--sparse' : '' }}">
            <div class="ficha__body-wrap">
                <div class="ficha__body-inner">
                    <div class="ficha__body-cell">
                        <div class="eyebrow">{{ $kindLabel }}</div>
                        <h1 class="title">{{ $card['title'] }}</h1>

                        @if (! empty($card['lugar']))
                            <div class="detail-group">
                                <div class="label">Ubicación</div>
                                <div class="value">{{ $card['lugar'] }}</div>
                            </div>
                        @endif

                        <div class="date-box">
                            <div class="date-text">{{ $card['badge_day'] }} DE {{ strtoupper($card['month_year_label'] ?? '') }}</div>
                            @if (! empty($card['hora_ficha']))
                                <div class="date-time">{{ $card['hora_ficha'] }}</div>
                            @endif
                        </div>

                        @if (! empty($card['descripcion']))
                            <div class="description">{{ $card['descripcion'] }}</div>
                        @endif

                        @if (! empty($card['aforo_label']))
                            <div class="aforo-line">{{ $card['aforo_label'] }}</div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="ficha__logo">
                <img src="images/{{ $logoFile }}" alt="">
            </div>
        </div>
    @endforeach
@endforeach
</body>
</html>
