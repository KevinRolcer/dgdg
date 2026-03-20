{{-- Bloque completo que se sustituye vía AJAX al cambiar de mes: toolbar (mes + pestañas), filtros, 3 vistas. --}}
{{-- Variables: $p, $clasificacion, $buscar, $prevUrl, $nextUrl, $previewReturn --}}
<div class="agenda-cal-toolbar agenda-cal-toolbar-dynamic">
    <div class="agenda-cal-month-nav" role="group" aria-label="Mes">
        <a href="{{ $prevUrl }}" class="agenda-cal-nav-btn agenda-cal-nav-ajax" title="Mes anterior" aria-label="Mes anterior" data-agenda-cal-nav="1">
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        </a>
        <h2 class="agenda-cal-month-label">{{ mb_convert_case($p['month_label'], MB_CASE_TITLE, 'UTF-8') }}</h2>
        <a href="{{ $nextUrl }}" class="agenda-cal-nav-btn agenda-cal-nav-ajax" title="Mes siguiente" aria-label="Mes siguiente" data-agenda-cal-nav="1">
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        </a>
    </div>

    <div class="agenda-cal-view-tabs" role="tablist" aria-label="Tipo de vista">
        <button type="button" class="agenda-cal-tab is-active" role="tab" aria-selected="true" data-agenda-cal-tab="mes" id="agendaCalTabMes">Mes</button>
        <button type="button" class="agenda-cal-tab" role="tab" aria-selected="false" data-agenda-cal-tab="lista" id="agendaCalTabLista">Eventos</button>
        <button type="button" class="agenda-cal-tab" role="tab" aria-selected="false" data-agenda-cal-tab="fichas" id="agendaCalTabFichas">Fichas</button>
    </div>
</div>

@if($clasificacion !== '' || $buscar !== '')
    <p class="agenda-cal-filter-note">
        Filtros activos desde el listado:
        @if($clasificacion !== '')
            <strong>{{ match($clasificacion) { 'gira' => 'Giras', 'pre_gira' => 'Pre-giras', 'agenda' => 'Agenda', default => $clasificacion } }}</strong>
        @endif
        @if($buscar !== '')
            @if($clasificacion !== '') · @endif
            búsqueda «{{ $buscar }}»
        @endif
        — <a href="{{ route('agenda.calendar', ['year' => $p['year'], 'month' => $p['month']]) }}">Quitar filtros</a>
    </p>
@endif

<div class="agenda-cal-panels-stack">
    <div class="agenda-cal-panel is-active" role="tabpanel" aria-labelledby="agendaCalTabMes" data-agenda-cal-panel="mes">
        <div class="agenda-cal-grid-wrap">
            <div class="agenda-cal-dow" aria-hidden="true">
                @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dow)
                    <div class="agenda-cal-dow-cell">{{ $dow }}</div>
                @endforeach
            </div>
            @foreach ($p['grid_weeks'] as $week)
                <div class="agenda-cal-week">
                    @foreach ($week as $cell)
                        <div class="agenda-cal-day {{ !$cell['in_month'] ? 'is-other-month' : '' }} {{ !empty($cell['is_today']) ? 'is-today' : '' }}">
                            <div class="agenda-cal-day-num">{{ $cell['in_month'] ? $cell['day'] : '' }}</div>
                            <div class="agenda-cal-day-events">
                                @foreach ($cell['events'] as $ev)
                                    <div class="agenda-cal-event-chip" title="{{ e($ev['title']) }}">
                                        <a href="{{ route('agenda.show', ['agenda' => $ev['agenda_id'], 'return' => $previewReturn]) }}" class="agenda-cal-event-link">{{ e(\Illuminate\Support\Str::limit($ev['title'], 28)) }}</a>
                                        <span class="agenda-cal-event-time">{{ e($ev['time']) }}</span>
                                    </div>
                                @endforeach
                                @if(($cell['events_more'] ?? 0) > 0)
                                    <div class="agenda-cal-more">Y {{ $cell['events_more'] }} más…</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    <div class="agenda-cal-panel" role="tabpanel" aria-labelledby="agendaCalTabLista" data-agenda-cal-panel="lista" hidden>
        <div class="agenda-cal-list">
            <h3 class="agenda-cal-list-month">{{ mb_convert_case($p['month_label'], MB_CASE_TITLE, 'UTF-8') }}</h3>
            <div class="agenda-cal-list-scroll" tabindex="0" role="region" aria-label="Lista de eventos del mes">
                @forelse ($p['list_rows'] as $row)
                    <article class="agenda-cal-list-row {{ ($row['date'] ?? '') === ($p['today'] ?? '') ? 'is-today-row' : '' }}">
                        <div class="agenda-cal-list-date">
                            <span class="agenda-cal-list-dow">{{ $row['weekday'] }}</span>
                            <span class="agenda-cal-list-day {{ ($row['date'] ?? '') === ($p['today'] ?? '') ? 'is-accent' : '' }}">{{ $row['day'] }}</span>
                        </div>
                        <div class="agenda-cal-list-meta">
                            <div class="agenda-cal-list-line">
                                <i class="fa-regular fa-clock agenda-cal-list-ico" aria-hidden="true"></i>
                                <span>{{ e($row['time_label']) }}</span>
                            </div>
                            <div class="agenda-cal-list-line">
                                <i class="fa-solid fa-location-dot agenda-cal-list-ico" aria-hidden="true"></i>
                                <span>{{ e($row['lugar']) }}</span>
                            </div>
                        </div>
                        <div class="agenda-cal-list-main">
                            <a href="{{ route('agenda.show', ['agenda' => $row['agenda_id'], 'return' => $previewReturn]) }}" class="agenda-cal-list-title">{{ e($row['title']) }}</a>
                        </div>
                    </article>
                @empty
                    <p class="agenda-cal-empty">No hay eventos en este mes con los filtros actuales.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="agenda-cal-panel" role="tabpanel" aria-labelledby="agendaCalTabFichas" data-agenda-cal-panel="fichas" hidden>
        <div class="agenda-cal-cards">
            @forelse ($p['cards'] as $card)
                <article class="agenda-cal-card">
                    <div class="agenda-cal-card-head">
                        <span class="agenda-cal-card-range">{{ e($card['range_label']) }}</span>
                        <span class="agenda-cal-card-badge" aria-hidden="true">{{ $card['badge_day'] }}</span>
                    </div>
                    <div class="agenda-cal-card-body">
                        <a href="{{ route('agenda.show', ['agenda' => $card['agenda_id'], 'return' => $previewReturn]) }}" class="agenda-cal-card-title">• {{ e($card['title']) }}</a>
                        @if(!empty($card['descripcion']))
                            <div class="agenda-cal-card-desc-wrap">
                                <div
                                    class="agenda-cal-card-desc-inner"
                                    data-agenda-cal-desc-inner
                                    tabindex="0"
                                    role="region"
                                    aria-label="Detalle del evento"
                                >
                                    <p class="agenda-cal-card-desc">{{ e($card['descripcion']) }}</p>
                                </div>
                                <button
                                    type="button"
                                    class="agenda-cal-card-more"
                                    data-agenda-cal-desc-more
                                    hidden
                                    aria-expanded="false"
                                >
                                    • Ver más
                                </button>
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <p class="agenda-cal-empty">No hay eventos en este mes con los filtros actuales.</p>
            @endforelse
        </div>
    </div>
</div>
