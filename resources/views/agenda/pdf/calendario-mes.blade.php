<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $documentTitle }}</title>
    <style>
        {{-- A4 landscape (297×210mm); content area = 265×182mm with 14mm 16mm margins --}}
        @@page { margin: 12mm 18mm; }
        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            color: #484747;
            font-family: '{{ $pdfFontFamily }}', DejaVu Sans, Helvetica, sans-serif;
            font-size: 6.5pt;
            line-height: 1.25;
        }
        .pdf-page {
            page-break-after: always;
        }
        .pdf-page:last-child {
            page-break-after: auto;
        }

        /* ── Header ─────────────────────────────────────────── */
        .pdf-head {
            display: table;
            width: 100%;
            border-bottom: 0.45pt solid rgba(72, 71, 71, 0.18);
            padding-bottom: 2.5mm;
            margin-bottom: 0;
        }
        .pdf-head-logo-cell {
            display: table-cell;
            width: 104mm;
            vertical-align: middle;
        }
        .pdf-logo {
            display: block;
            width: 104mm;
            height: auto;
        }
        .pdf-head-text-cell {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            padding: 1mm 2mm;
        }
        .pdf-title {
            margin: 0 0 0.8mm;
            color: #5f1b2d;
            font-size: 12pt;
            font-weight: 900;
            line-height: 1.15;
        }
        .pdf-subtitle {
            color: #6b6a6a;
            font-size: 7pt;
            font-weight: 500;
        }

        /* ── Month bar + legend ──────────────────────────────── */
        .month-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin: 2mm 0 1.5mm;
            gap: 1.5em;
        }
        .month-name {
            color: #5f1b2d;
            font-size: 11pt;
            font-weight: 900;
            text-transform: uppercase;
            flex: none;
            text-align: center;
            margin: 0 auto;
        }
        .legend {
            text-align: right;
            color: #6b6a6a;
            font-size: 6pt;
            white-space: nowrap;
            flex: none;
        }
        .legend-item {
            display: inline-block;
            margin-left: 2.2mm;
        }
        .legend-dot {
            display: inline-block;
            width: 1.8mm;
            height: 1.8mm;
            border-radius: 50%;
            margin-right: 0.8mm;
            vertical-align: -0.15mm;
        }
        .dot-agenda      { background: #5f1b2d; }
        .dot-gira        { background: #246257; }
        .dot-pre_gira    { background: #c79b66; }
        .dot-personalizada { background: #8b2f47; }

        /* ── Calendar grid ───────────────────────────────────── */
        .calendar-wrap {
            max-width: 97%;
            margin: 0 auto 0 auto;
        }
        .calendar {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 0.45pt solid rgba(95, 27, 45, 0.18);
        }
        .calendar th {
            height: 6mm;
            background: #f2edf0;
            color: #5f1b2d;
            border: 0.45pt solid rgba(95, 27, 45, 0.18);
            font-size: 6.5pt;
            font-weight: 900;
            text-transform: uppercase;
        }
        .calendar td {
            width: 14.2857%;
            vertical-align: top;
            border: 0.45pt solid rgba(72, 71, 71, 0.16);
            padding: 1.1mm;
            background: #fff;
            overflow: hidden;
        }
        .calendar td.is-out {
            background: #f9f8f8;
            color: #aaa;
        }
        .day-num {
            margin: 0 0 0.7mm;
            color: #5f1b2d;
            font-size: 7pt;
            font-weight: 900;
            line-height: 1.2;
        }
        .is-out .day-num { color: #c0c0c0; }

        /* ── Events: base ────────────────────────────────────── */
        .event {
            margin: 0 0 0.7mm;
            padding: 0.9mm 1.1mm;
            border-radius: 1mm;
            background: #f7eef2;
            color: #4b4a4a;
        }
        .event--gira        { background: #edf6f3; }
        .event--pre_gira    { background: #f8f1e7; }
        .event--personalizada { background: #f8edf1; }
        .event-title {
            display: block;
            color: #5f1b2d;
            font-weight: 900;
            font-size: 6.5pt;
            line-height: 1.15;
        }
        .event--gira .event-title     { color: #246257; }
        .event--pre_gira .event-title { color: #6f522c; }
        .event-time {
            display: inline;
            color: #7a7979;
            font-weight: 700;
        }
        .event-place {
            display: block;
            margin-top: 0.3mm;
            color: #5d5c5c;
            font-size: 5.5pt;
            font-weight: 600;
            line-height: 1.1;
        }


        /* ── Density: medium (3-4 events max in day) ─────────── */
        .cal-medium .event {
            padding: 0.6mm 0.9mm;
            margin-bottom: 0.4mm;
        }
        .cal-medium .event-title { font-size: 6pt; line-height: 1.12; }
        /* SIEMPRE mostrar ubicación */
        /* .cal-medium .event-place { display: none; } */

        /* ── Density: dense (5+ events max in day) ───────────── */
        .cal-dense .event {
            padding: 0.4mm 0.8mm;
            margin-bottom: 0.3mm;
        }
        .cal-dense .event-title { font-size: 5.2pt; line-height: 1.08; }
        /* SIEMPRE mostrar ubicación */
        /* .cal-dense .event-place { display: none; } */

        /* ── Overflow indicator ──────────────────────────────── */
        .event-more {
            font-size: 5pt;
            color: #999;
            font-style: italic;
            text-align: right;
            margin-top: 0.2mm;
        }

        /* ── Empty state ─────────────────────────────────────── */
        .empty-note {
            padding: 18mm 0;
            text-align: center;
            color: #888;
            font-size: 9pt;
        }
    </style>
</head>
<body>
@if(empty($calendarPages))
    <div class="pdf-head">
        <div class="pdf-head-logo-cell">
            <img src="images/LogoSegobHorizontal.png" class="pdf-logo" alt="">
        </div>
        <div class="pdf-head-text-cell">
            <h1 class="pdf-title">{{ $documentTitle }}</h1>
            @if(!empty($documentSubtitle ?? ''))
                <div class="pdf-subtitle">{{ $documentSubtitle }}</div>
            @endif
        </div>
    </div>
    <p class="empty-note">No hay eventos con los filtros seleccionados.</p>
@else
    @foreach($calendarPages as $page)
        @php
            // ── Trim trailing rows that are entirely out-of-month ──────────────
            $allWeeks = $page['weeks'] ?? [];
            while (count($allWeeks) > 4) {
                $lastRow = end($allWeeks);
                $hasInMonth = false;
                foreach ($lastRow as $d) {
                    if (!empty($d['in_month'])) { $hasInMonth = true; break; }
                }
                if (!$hasInMonth) { array_pop($allWeeks); } else { break; }
            }
            $numWeeks = max(4, count($allWeeks));

            // ── Compute row height to fill the page exactly ───────────────────
            // A4 landscape content = 186mm; overhead (head 18mm + monthbar 5.5mm + thead 6mm + borders ~1mm) ≈ 30.5mm
            $bodyMm  = 155.5; // 186 - 30.5
            $rowH    = round($bodyMm / $numWeeks, 2); // mm per row

            // ── Max events in any single day of this month ────────────────────
            $maxE = 0;
            foreach ($allWeeks as $week) {
                foreach ($week as $day) {
                    $c = count($day['events'] ?? []);
                    if ($c > $maxE) $maxE = $c;
                }
            }

            // ── Densidad por celda y altura variable ───────────────────────────
            // 1. Contar celdas con eventos y vacías
            $eventCells = 0; $emptyCells = 0;
            foreach ($allWeeks as $week) {
                foreach ($week as $day) {
                    if (count($day['events'] ?? [])) $eventCells++; else $emptyCells++;
                }
            }
            $totalCells = $eventCells + $emptyCells;
            // 2. Definir altura: celdas vacías más pequeñas
            $bodyMm = 155.5;
            $minEmptyH = 10; // mm
            $usedMm = $emptyCells * $minEmptyH;
            $eventCellH = $eventCells ? ($bodyMm - $usedMm) / $eventCells : $bodyMm;
            $cellDensity = function($eventCount, $cellH) {
                // Altura por evento según densidad
                if ($eventCount >= 5) return ['cls' => 'cal-dense', 'eventH' => 2.5];
                if ($eventCount >= 3) return ['cls' => 'cal-medium', 'eventH' => 3.2];
                return ['cls' => '', 'eventH' => 4.8];
            };
        @endphp
        <section class="pdf-page">
            <div class="pdf-head">
                <div class="pdf-head-logo-cell">
                    <img src="images/LogoSegobHorizontal.png" class="pdf-logo" alt="">
                </div>
                <div class="pdf-head-text-cell">
                    <h1 class="pdf-title">{{ $documentTitle }}</h1>
                    @if(!empty($documentSubtitle ?? ''))
                        <div class="pdf-subtitle">{{ $documentSubtitle }}</div>
                    @endif
                </div>
            </div>


            <div class="month-bar">
                <div class="month-name" style="margin: 0 auto;">{{ $page['month_label'] ?? '' }}</div>
                @if(count($allowedKinds ?? []) > 1)
                <div class="legend">
                    @if(in_array('agenda', $allowedKinds ?? []))
                        <span class="legend-item"><span class="legend-dot dot-agenda"></span>Agenda</span>
                    @endif
                    @if(in_array('gira', $allowedKinds ?? []))
                        <span class="legend-item"><span class="legend-dot dot-gira"></span>Gira</span>
                    @endif
                    @if(in_array('pre_gira', $allowedKinds ?? []))
                        <span class="legend-item"><span class="legend-dot dot-pre_gira"></span>Pre-gira</span>
                    @endif
                    @if(in_array('personalizada', $allowedKinds ?? []))
                        <span class="legend-item"><span class="legend-dot dot-personalizada"></span>{{ $personalizadaLabel ?? 'Personalizada' }}</span>
                    @endif
                </div>
                @endif
            </div>

            <div class="calendar-wrap">
            <table class="calendar">
                <thead>
                    <tr>
                        <th>Lun</th><th>Mar</th><th>Mié</th><th>Jue</th><th>Vie</th><th>Sáb</th><th>Dom</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($allWeeks as $week)
                    <tr>
                    @foreach($week as $day)
                        @php
                            $events  = $day['events'] ?? [];
                            $cellH   = count($events) ? $eventCellH : $minEmptyH;
                            $density = $cellDensity(count($events), $cellH);
                            // Ajuste: considerar altura real de cada evento incluyendo ubicación
                            // Se asume que cada evento ocupa un poco más por la ubicación
                            // Mostrar SIEMPRE todos los eventos seleccionados, sin límite
                            $shown   = $events;
                            $clipped = 0;
                        @endphp
                        <td class="{{ !empty($day['in_month']) ? '' : 'is-out' }} {{ $density['cls'] }}"
                            style="height: {{ $cellH }}mm; overflow: hidden;">
                            <div class="day-num">{{ $day['day'] }}</div>
                            @foreach($shown as $event)
                                @php
                                    $kind = in_array(($event['kind'] ?? 'agenda'), ['agenda','gira','pre_gira','personalizada'], true)
                                        ? $event['kind'] : 'agenda';
                                @endphp
                                <div class="event event--{{ $kind }}">
                                    <span class="event-title">
                                        @if(!empty($event['time']) && $event['time'] !== 'Todo el día')
                                            <span class="event-time">{{ $event['time'] }}</span>
                                        @endif
                                        {{ $event['title'] ?? '' }}
                                    </span>
                                    @if(!empty($event['lugar']))
                                        <span class="event-place">{{ $event['lugar'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                            {{-- Nunca mostrar "+N más" --}}
                        </td>
                    @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        </section>
    @endforeach
@endif
</body>
</html>
