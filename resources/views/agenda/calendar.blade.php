@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/agenda.css') }}?v={{ @filemtime(public_path('assets/css/modules/agenda.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/agenda-calendar.css') }}?v={{ @filemtime(public_path('assets/css/modules/agenda-calendar.css')) ?: time() }}">
@endpush

@php
    $pageTitle = 'Agenda — Calendario';
    $hidePageHeader = true;
    $p = $payload;
    $qBase = array_filter([
        'clasificacion' => $clasificacion,
        'buscar' => $buscar,
    ]);
    $prevUrl = route('agenda.calendar', array_merge($qBase, ['year' => $p['prev']['y'], 'month' => $p['prev']['m']]));
    $nextUrl = route('agenda.calendar', array_merge($qBase, ['year' => $p['next']['y'], 'month' => $p['next']['m']]));
    $indexUrl = route('agenda.index', array_filter([
        'clasificacion' => $clasificacion,
        'buscar' => $buscar,
    ]));
    $previewReturn = route('agenda.calendar', array_filter(array_merge(
        ['year' => $p['year'], 'month' => $p['month']],
        ['clasificacion' => $clasificacion !== '' ? $clasificacion : null, 'buscar' => $buscar !== '' ? $buscar : null]
    )));
@endphp

@section('content')
<section
    class="agenda-page agenda-shell agenda-cal-page app-density-compact"
    id="agendaCalPage"
    data-agenda-cal-base-url="{{ url('/agenda/calendario') }}"
    data-agenda-cal-fichas-pdf-url="{{ route('agenda.calendar.fichas-pdf') }}"
>
    <div class="agenda-shell-main">
        <header class="agenda-cal-top">
            <div class="agenda-cal-top-text">
                <h1 class="agenda-shell-title agenda-cal-title">Vista calendario</h1>
                <p class="agenda-shell-desc">
                    @if(!empty($soloAsignaciones))
                        Mismos eventos que en el listado (asignados a ti). Tres formas de ver el mes.
                    @else
                        Tres formas de ver el mes: cuadrícula, lista de eventos o fichas.
                    @endif
                </p>
            </div>
            <div class="agenda-cal-top-actions">
                <a href="{{ $indexUrl }}" class="agenda-btn agenda-btn-secondary">
                    <i class="fa-solid fa-list" aria-hidden="true"></i> Volver al listado
                </a>
            </div>
        </header>

        <div id="agendaCalAjaxRoot" class="agenda-cal-ajax-root" data-agenda-cal-year="{{ $p['year'] }}" data-agenda-cal-month="{{ $p['month'] }}" aria-busy="false">
            @include('agenda.partials.calendar-month-inner', [
                'p' => $p,
                'clasificacion' => $clasificacion,
                'buscar' => $buscar,
                'prevUrl' => $prevUrl,
                'nextUrl' => $nextUrl,
                'previewReturn' => $previewReturn,
            ])
        </div>
    </div>
</section>

{{-- Modal fuera del bloque AJAX para que no se pierda al cambiar de mes --}}
<div id="agendaCalFichasPrintModal" class="agenda-cal-print-modal" hidden aria-hidden="true">
    <div class="agenda-cal-print-modal__backdrop" data-agenda-cal-print-close tabindex="-1"></div>
    <div
        class="agenda-cal-print-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="agendaCalFichasPrintModalTitle"
    >
        <header class="agenda-cal-print-modal__head">
            <h2 id="agendaCalFichasPrintModalTitle" class="agenda-cal-print-modal__title">Exportar fichas a PDF</h2>
            <button type="button" class="agenda-cal-print-modal__x" data-agenda-cal-print-close aria-label="Cerrar">&times;</button>
        </header>
        <form id="agendaCalFichasPrintForm" class="agenda-cal-print-modal__form">
            <div class="agenda-cal-print-kinds-box">
                <span class="agenda-cal-print-legend agenda-cal-print-legend--inline">Encabezado del PDF</span>
                <label class="agenda-cal-print-input-label">
                    <span>Título</span>
                    <input type="text" name="pdf_title" class="agenda-cal-print-text-input" maxlength="120" placeholder="Fichas de agenda">
                </label>
                <label class="agenda-cal-print-input-label">
                    <span>Nombre del archivo</span>
                    <input type="text" name="pdf_filename" class="agenda-cal-print-text-input" maxlength="160" placeholder="fichas-agenda.pdf">
                </label>
                <label class="agenda-cal-print-input-label">
                    <span>Subtítulo</span>
                    <input type="text" name="pdf_subtitle" class="agenda-cal-print-text-input" maxlength="180" placeholder="Periodo, área o nota opcional">
                </label>
            </div>

            <fieldset class="agenda-cal-print-fieldset" id="agendaCalPrintScopeFieldset">
                <legend class="agenda-cal-print-legend">Eventos a incluir</legend>
                <p class="agenda-cal-print-hint">Mismos filtros que la vista (tipo y búsqueda). El PDF incluye el contenido completo de cada ficha, sin líneas de encargado ni de quien registró el evento.</p>
                <label class="agenda-cal-print-radio"><input type="radio" name="scope" value="all"> Todos los eventos cargados (según filtros y permisos)</label>
                <label class="agenda-cal-print-radio"><input type="radio" name="scope" value="current_month" checked> Solo el mes que estoy viendo en el calendario</label>
                <label class="agenda-cal-print-radio"><input type="radio" name="scope" value="custom_months"> Varios meses</label>
            </fieldset>

            {{-- Sección exclusiva para template=calendar: nota y etiqueta personalizada --}}
            <div id="agendaCalPrintCalendarSection" hidden>
                <div class="agenda-cal-print-kinds-box">
                    <p class="agenda-cal-print-hint">Calendario mensual: selecciona los meses abajo. Cada mes se imprime en una hoja aparte.</p>
                </div>
                <div class="agenda-cal-print-kinds-box">
                    <span class="agenda-cal-print-legend agenda-cal-print-legend--inline">Etiqueta de fichas personalizadas</span>
                    <label class="agenda-cal-print-input-label">
                        <span>Nombre en la leyenda de color</span>
                        <input type="text" name="personalizada_label" id="agendaCalPersonalizadaLabel" class="agenda-cal-print-text-input" maxlength="80" placeholder="Personalizada">
                    </label>
                </div>
            </div>

            <div id="agendaCalPrintCustomMonthsBox" class="agenda-cal-print-custom-box" hidden>
                <span class="agenda-cal-print-legend agenda-cal-print-legend--inline">Meses elegidos</span>
                <p class="agenda-cal-print-hint">Selecciona mes y año, pulsa Agregar. Puedes quitar entradas si te equivocas.</p>
                <div class="agenda-cal-print-month-row">
                    <input type="month" id="agendaCalPrintMonthInput" class="agenda-cal-print-month-input" aria-label="Mes y año a agregar">
                    <button type="button" class="agenda-btn agenda-btn-secondary agenda-cal-print-mini" id="agendaCalPrintMonthAdd">Agregar</button>
                </div>
                <ul id="agendaCalPrintMonthList" class="agenda-cal-print-month-list" aria-label="Lista de meses"></ul>
                <input type="hidden" name="custom_months_json" id="agendaCalCustomMonthsJson" value="[]">
            </div>

            <div class="agenda-cal-print-kinds-box">
                <span class="agenda-cal-print-legend agenda-cal-print-legend--inline">Formato de ficha</span>
                <p class="agenda-cal-print-hint">Elige el estilo visual del PDF.</p>
                <label class="agenda-cal-print-radio"><input type="radio" name="template" value="summary" checked> Ficha Resumen (Listado compacto)</label>
                <label class="agenda-cal-print-radio"><input type="radio" name="template" value="individual"> Ficha Individual (Una por página, estilizada)</label>
                <label class="agenda-cal-print-radio"><input type="radio" name="template" value="calendar"> Calendario mensual (Mes)</label>
            </div>

            <div class="agenda-cal-print-kinds-box">
                <span class="agenda-cal-print-legend agenda-cal-print-legend--inline">Incluir tipos</span>
                <p class="agenda-cal-print-hint">Puedes elegir una o varias categorías.</p>
                <label class="agenda-cal-print-check"><input type="checkbox" name="kind_gira" value="1" checked> Gira</label>
                <label class="agenda-cal-print-check"><input type="checkbox" name="kind_pre_gira" value="1" checked> Pre-gira</label>
                <label class="agenda-cal-print-check"><input type="checkbox" name="kind_agenda" value="1" checked> Agenda</label>
                <label class="agenda-cal-print-check"><input type="checkbox" name="kind_personalizada" value="1" checked> Fichas personalizadas</label>
            </div>

            <fieldset class="agenda-cal-print-fieldset">
                <legend class="agenda-cal-print-legend">Orientación (A4)</legend>
                <label class="agenda-cal-print-radio"><input type="radio" name="orientation" value="portrait" checked> Vertical (retrato)</label>
                <label class="agenda-cal-print-radio"><input type="radio" name="orientation" value="landscape"> Horizontal (paisaje)</label>
            </fieldset>

            <p class="agenda-cal-print-error" id="agendaCalPrintError" hidden role="alert"></p>

            <footer class="agenda-cal-print-modal__foot">
                <button type="button" class="agenda-btn agenda-btn-secondary" data-agenda-cal-print-close>Cancelar</button>
                <button type="submit" class="agenda-btn agenda-btn-primary" id="agendaCalPrintSubmit">
                    <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                    Generar PDF
                </button>
            </footer>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/modules/agenda-calendar.js') }}?v={{ @filemtime(public_path('assets/js/modules/agenda-calendar.js')) ?: time() }}"></script>
@endpush
