@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/agenda.css') }}?v={{ time() }}">
@endpush

@php
    $pageTitle = 'Agenda Directiva';
    $hidePageHeader = true;
@endphp

@section('content')
<section class="agenda-page agenda-shell app-density-compact">
    <div class="agenda-shell-main">
        <header class="agenda-shell-head">
            <h1 class="agenda-shell-title">Agenda Directiva</h1>
            <p class="agenda-shell-desc">
                @if(!empty($soloAsignaciones))
                    Solo ves los eventos asignados a ti (actualizaciones incluidas). Para pasar pre-gira a gira o registrar actualizaciones usa Seguimiento de Agenda.
                @else
                    Gestiona asuntos, giras y pre-giras; asigna usuarios y envía invitaciones a calendario.
                @endif
            </p>
        </header>

        <article class="agenda-card agenda-card-in-shell">
            <div class="agenda-head agenda-head-actions-only">
                <div class="agenda-head-actions">
                <a href="{{ route('agenda.calendar', array_filter(['clasificacion' => $clasificacion, 'buscar' => $buscar])) }}" class="agenda-btn agenda-btn-secondary" title="Ver el mes en calendario, lista o fichas">
                    <i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Vista calendario
                </a>
                @if(!empty($puedeAsignarModuloAgenda))
                <button type="button" class="agenda-btn agenda-btn-secondary" onclick="openAgendaModuloModal()" title="Dar acceso a Agenda Directiva a Enlaces">
                    <i class="fa-solid fa-user-check"></i> Asignar módulo
                </button>
                @endif
                @if(!empty($puedeEditarAgenda))
                <button type="button" class="agenda-btn agenda-btn-secondary" onclick="openAgendaModal(null, 'gira')" title="Agregar Gira o Pre-Gira">
                    <i class="fa-solid fa-map-location-dot"></i> Gira/Pre-Gira
                </button>
                <button type="button" class="agenda-btn agenda-btn-primary" onclick="openAgendaModal(null, 'asunto')" title="Agregar nuevo asunto">
                    <i class="fa-solid fa-plus"></i> Nuevo Asunto
                </button>
                @endif
                </div>
            </div>

        @php
            $agendaFiltrosAvanzadosAbiertos = trim((string)($buscar ?? '')) !== ''
                || filled($fechaDia ?? null)
                || (int)($perPage ?? 20) !== 20;
        @endphp
        <form method="get" action="{{ route('agenda.index') }}" class="agenda-filters-form agenda-filters-compact" id="agendaFiltersForm">
            <div class="agenda-filters-row agenda-filters-row-head">
                <div class="agenda-filters-head-left">
                    <span class="agenda-filters-label">Clasificar</span>
                    <div class="tm-module-filters agenda-filters-chips" role="group" aria-label="Clasificación">
                        <input type="hidden" name="clasificacion" id="agendaFilterClasificacion" value="{{ $clasificacion }}">
                        <button type="button" class="tm-module-chip {{ $clasificacion === 'gira' ? 'is-active' : '' }}" data-agenda-clasificacion="gira">Giras</button>
                        <button type="button" class="tm-module-chip {{ $clasificacion === 'pre_gira' ? 'is-active' : '' }}" data-agenda-clasificacion="pre_gira">Pre-giras</button>
                        <button type="button" class="tm-module-chip {{ $clasificacion === 'agenda' ? 'is-active' : '' }}" data-agenda-clasificacion="agenda">Agenda</button>
                        <a href="{{ route('agenda.index') }}" class="tm-module-chip tm-btn-clear agenda-filter-clear" data-agenda-clear-filters title="Quitar clasificación y filtros extra">
                            <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i> Borrar filtro
                        </a>
                    </div>
                </div>
                <div class="agenda-filters-head-right">
                    <button type="button" class="agenda-btn agenda-btn-secondary" id="agendaBtnRefreshList" title="Volver a cargar el listado (sin recargar la página)">
                        <i class="fa-solid fa-arrows-rotate" aria-hidden="true" id="agendaBtnRefreshIcon"></i> Actualizar lista
                    </button>
                    <button type="button" class="agenda-btn agenda-btn-secondary agenda-btn-mas-filtros" id="agendaBtnMasFiltros" aria-expanded="{{ $agendaFiltrosAvanzadosAbiertos ? 'true' : 'false' }}" aria-controls="agendaFiltersAdvanced">
                        <i class="fa-solid fa-sliders" aria-hidden="true"></i> <span id="agendaBtnMasFiltrosText">{{ $agendaFiltrosAvanzadosAbiertos ? 'Menos filtros' : 'Más filtros' }}</span>
                    </button>
                </div>
            </div>
            <div class="agenda-filters-advanced {{ $agendaFiltrosAvanzadosAbiertos ? 'is-open' : '' }}" id="agendaFiltersAdvanced" @if(!$agendaFiltrosAvanzadosAbiertos) hidden @endif>
                <div class="agenda-filters-row agenda-filters-inputs agenda-filters-inputs-inline">
                    <label class="agenda-filter-field agenda-filter-buscar">
                        <span>Buscar</span>
                        <input type="search" name="buscar" id="agendaInputBuscar" value="{{ $buscar }}" placeholder="Título o descripción…" class="form-control-agenda form-control-agenda-sm" autocomplete="off">
                    </label>
                    <label class="agenda-filter-field agenda-filter-fecha">
                        <span>Un día</span>
                        <input type="date" name="fecha" id="agendaInputFecha" value="{{ $fechaDia }}" class="form-control-agenda form-control-agenda-sm" title="Eventos con fecha de inicio en este día">
                    </label>
                    <label class="agenda-filter-field agenda-filter-per-page">
                        <span>Por pág.</span>
                        <select name="per_page" id="agendaSelectPerPage" class="form-control-agenda form-control-agenda-sm">
                            @foreach([10, 20, 50, 100] as $n)
                                <option value="{{ $n }}" @selected((int)($perPage ?? 20) === $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="button" class="agenda-btn agenda-btn-clear-extra" id="agendaBtnClearExtra" title="Quitar búsqueda, fecha y volver a 20 por página">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </form>

        <div id="agendaAjaxContainer" class="agenda-ajax-container" data-agenda-index-url="{{ route('agenda.index') }}">
            <div class="agenda-ajax-loading" id="agendaAjaxLoading" hidden aria-hidden="true">Actualizando…</div>
            @include('agenda.partials.list-fragment')
        </div>
        </article>
    </div>
</section>
@endsection

@include('agenda.partials.modal')
@include('agenda.partials.modulo-modal')

{{-- Modal solo lectura para ver descripción desde la tabla --}}
<div id="agendaVerDescripcionModal" class="modal-agenda-overlay agenda-modal-ver-desc-overlay" aria-hidden="true">
    <div class="modal-agenda-content modal-agenda-ver-desc agenda-modal-ver-desc-content">
        <div class="modal-agenda-header">
            <h3>Descripción</h3>
            <button type="button" class="modal-close-btn" onclick="window.agendaCerrarVerDescripcion()" aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body-scroll modal-agenda-ver-desc-body agenda-modal-ver-desc-body" id="agendaVerDescripcionBody"></div>
        <div class="modal-agenda-footer">
            <button type="button" class="btn-agenda btn-secondary-agenda" onclick="window.agendaCerrarVerDescripcion()">Cerrar</button>
        </div>
    </div>
</div>

@push('scripts')
{{-- POST/PUT: misma base que APP_URL (evita 404 en subcarpeta) --}}
<meta name="agenda-url-store" content="{{ route('agenda.store') }}">
<meta name="agenda-url-base" content="{{ url('/agenda') }}">
{{-- URLs absolutas: fetch/modulo --}}
<meta name="agenda-modulo-enlaces" content="{{ route('agenda.modulo.enlaces') }}">
<meta name="agenda-modulo-asignar" content="{{ route('agenda.modulo.asignar') }}">
<meta name="agenda-modulo-quitar" content="{{ route('agenda.modulo.quitar') }}">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('assets/js/modules/agenda.js') }}?v={{ @filemtime(public_path('assets/js/modules/agenda.js')) ?: time() }}"></script>
<script src="{{ asset('assets/js/modules/agenda-index.js') }}?v={{ @filemtime(public_path('assets/js/modules/agenda-index.js')) ?: time() }}"></script>
@endpush
