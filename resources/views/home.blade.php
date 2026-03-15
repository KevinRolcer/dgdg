@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/home.css') }}?v={{ @filemtime(public_path('assets/css/modules/home.css')) ?: time() }}">
@endpush

@section('content')
@php
    $hidePageHeader = true;
@endphp
<div class="home-shell app-density-compact">
    <div class="home-shell-main">
        <header class="home-shell-main-head">
            <h1 class="home-shell-main-title">Inicio</h1>
            <p class="home-shell-main-desc">Resumen y calendario de eventos asignados.</p>
        </header>

        @if (session('status'))
            <div class="home-inline-alert home-inline-alert-success" role="alert">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="home-inline-alert home-inline-alert-error" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="home-inline-alert home-inline-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="home-panel-block" aria-labelledby="home-welcome-heading">
            <h2 class="home-panel-heading" id="home-welcome-heading">Bienvenido</h2>
            <p class="home-panel-lead">Desde aquí puedes consultar el calendario de eventos y acceder a los módulos del sistema.</p>
        </section>
    </div>

    <aside class="home-shell-aside" id="calendarCard" aria-label="Calendario de eventos">
        <script type="application/json" id="homeCalendarAgendaDays">@json($agendaDays ?? [])</script>
        <header class="home-aside-head">
            <h3 class="home-aside-title">Calendario de Eventos</h3>
            <button type="button" class="home-aside-close" id="calendarDrawerClose" aria-label="Cerrar calendario">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>

        <div class="home-calendar-view-switch" role="tablist" aria-label="Lista y filtros de eventos">
            <button type="button" class="home-calendar-view-btn" data-calendar-view="past">Anteriores</button>
            <button type="button" class="home-calendar-view-btn is-active" data-calendar-view="month">Mes</button>
            <button type="button" class="home-calendar-view-btn" data-calendar-view="upcoming">Próximos</button>
        </div>

        <section class="home-calendar-panel" data-calendar-panel="past">
            <ul class="home-calendar-event-list">
                @forelse ($pastEvents as $event)
                    <li>
                        <strong>{{ $event['summary'] }}</strong>
                        <small>{{ $event['starts_at']->format('d/m/Y H:i') }} - {{ $event['ends_at']->format('H:i') }}</small>
                    </li>
                @empty
                    <li><strong>Sin eventos anteriores asignados.</strong></li>
                @endforelse
            </ul>
        </section>

        <section class="home-calendar-panel is-active" data-calendar-panel="month">
            <div class="home-calendar-toolbar">
                <button type="button" class="home-calendar-nav-btn" id="calendarPrev" aria-label="Mes anterior">
                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                </button>
                <strong id="calendarMonthLabel">Mes</strong>
                <button type="button" class="home-calendar-nav-btn" id="calendarNext" aria-label="Mes siguiente">
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </button>
            </div>
            <div class="home-calendar-weekdays" aria-hidden="true">
                <span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span><span>Dom</span>
            </div>
            <div class="calendar-grid" id="calendarGrid"></div>
        </section>

        <section class="home-calendar-panel" data-calendar-panel="upcoming">
            <ul class="home-calendar-event-list">
                @forelse ($upcomingEvents as $event)
                    <li>
                        <strong>{{ $event['summary'] }}</strong>
                        <small>{{ $event['starts_at']->format('d/m/Y H:i') }} - {{ $event['ends_at']->format('H:i') }}</small>
                    </li>
                @empty
                    <li><strong>Sin eventos próximos asignados.</strong></li>
                @endforelse
            </ul>
        </section>
    </aside>

    <button type="button" class="home-calendar-mobile-tab" id="calendarDrawerToggle" aria-expanded="false" aria-controls="calendarCard" aria-label="Mostrar u ocultar calendario">
        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
        <span class="calendar-mobile-tab-open">Eventos</span>
    </button>
    <button type="button" class="home-calendar-mobile-backdrop" id="calendarMobileBackdrop" aria-label="Cerrar calendario"></button>
</div>
@endsection
