@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/home.css') }}">
@endpush

@section('content')
    <section class="home-layout">
        <article class="content-card home-main-card">
            @if (session('status'))
                <div class="inline-alert inline-alert-success" role="alert">{{ session('status') }}</div>
            @endif

            @if (session('error'))
                <div class="inline-alert inline-alert-error" role="alert">{{ session('error') }}</div>
            @endif

            @if ($errors->any())
                <div class="inline-alert inline-alert-error" role="alert">{{ $errors->first() }}</div>
            @endif

        </article>

        <aside class="content-card calendar-card" id="calendarCard">
            <header class="calendar-card-header">
                <h3>Calendario de Eventos</h3>
                <button type="button" class="calendar-drawer-close" id="calendarDrawerClose" aria-label="Cerrar calendario">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </header>

            <div class="calendar-view-switch" role="tablist" aria-label="Cambiar vista de calendario">
                <button type="button" class="calendar-view-btn is-active" data-calendar-view="month">Mes</button>
                <button type="button" class="calendar-view-btn" data-calendar-view="upcoming">Próximos</button>
                <button type="button" class="calendar-view-btn" data-calendar-view="past">Anteriores</button>
            </div>

            <section class="calendar-panel is-active" data-calendar-panel="month">
                <div class="calendar-toolbar">
                    <button type="button" class="calendar-nav-btn" id="calendarPrev" aria-label="Mes anterior">
                        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                    </button>
                    <strong id="calendarMonthLabel">Mes</strong>
                    <button type="button" class="calendar-nav-btn" id="calendarNext" aria-label="Mes siguiente">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="calendar-weekdays" aria-hidden="true">
                    <span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span><span>Dom</span>
                </div>
                <div class="calendar-grid" id="calendarGrid"></div>
            </section>

            <section class="calendar-panel" data-calendar-panel="upcoming">
                <ul class="calendar-event-list">
                    @forelse ($upcomingEvents as $event)
                        <li>
                            <strong>{{ $event['summary'] }}</strong>
                            <small>{{ $event['starts_at']->format('d/m/Y H:i') }} - {{ $event['ends_at']->format('H:i') }}</small>
                        </li>
                    @empty
                        <li><strong>Sin eventos próximos.</strong></li>
                    @endforelse
                </ul>
            </section>

            <section class="calendar-panel" data-calendar-panel="past">
                <ul class="calendar-event-list">
                    @forelse ($pastEvents as $event)
                        <li>
                            <strong>{{ $event['summary'] }}</strong>
                            <small>{{ $event['starts_at']->format('d/m/Y H:i') }} - {{ $event['ends_at']->format('H:i') }}</small>
                        </li>
                    @empty
                        <li><strong>Sin eventos anteriores.</strong></li>
                    @endforelse
                </ul>
            </section>
        </aside>

        <button type="button" class="calendar-mobile-tab" id="calendarDrawerToggle" aria-expanded="false" aria-controls="calendarCard" aria-label="Mostrar u ocultar calendario">
            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
            <span class="calendar-mobile-tab-open">Eventos</span>
        </button>

        <button type="button" class="calendar-mobile-backdrop" id="calendarMobileBackdrop" aria-label="Cerrar calendario"></button>
    </section>
@endsection
