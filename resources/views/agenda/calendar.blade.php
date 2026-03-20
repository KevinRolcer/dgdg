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
<section class="agenda-page agenda-shell agenda-cal-page app-density-compact" id="agendaCalPage" data-agenda-cal-base-url="{{ url('/agenda/calendario') }}">
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
@endsection

@push('scripts')
<script src="{{ asset('assets/js/modules/agenda-calendar.js') }}?v={{ @filemtime(public_path('assets/js/modules/agenda-calendar.js')) ?: time() }}"></script>
@endpush
