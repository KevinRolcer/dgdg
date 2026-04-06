@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="{{ asset('assets/css/modules/microregiones.css') }}?v={{ @filemtime(public_path('assets/css/modules/microregiones.css')) ?: time() }}">
@endpush

@section('content')
@php $hidePageHeader = true; @endphp
{{-- JSON incrustado: el mapa carga aunque /microregiones/data falle por proxy/WAF --}}
<script type="application/json" id="microregionesMapBootstrap">@json($microregionesBootstrap ?? ['microrregiones' => []])</script>
<div class="microregiones-page" id="microregionesRoot" data-data-url="{{ route('microregiones.map-datos', [], false) }}" data-search-url="{{ route('microregiones.map-search', [], false) }}" data-search-url-alt="{{ route('microregiones.search', [], false) }}">

    <div class="microregiones-layout" id="microregionesLayout">
        <div class="microregiones-map-wrap">
            <div id="microregionesMap" class="microregiones-map" role="application" aria-label="Mapa de microrregiones"></div>
            <button type="button" class="microregiones-sidebar-toggle" id="microregionesToggleSidebar" aria-expanded="true" aria-controls="microregionesSidebar">
                <span class="microregiones-sidebar-toggle-text">Ocultar panel</span>
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </button>

            <div class="microregiones-floating-detail microregiones-floating-detail--hidden" id="microregionesDetail">
                <button type="button" class="microregiones-floating-close" id="microregionesDetailClose" title="Cerrar detalles">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <div id="microregionesDetailContent"></div>
            </div>
        </div>

        <aside class="microregiones-sidebar" id="microregionesSidebar" aria-label="Panel de búsqueda y microrregiones">
            <div class="microregiones-sidebar-mobile-bar">
                <span class="microregiones-sidebar-mobile-title">Microrregiones</span>
                <button type="button" class="microregiones-sidebar-mobile-close" id="microregionesSidebarMobileClose" title="Cerrar panel" aria-label="Cerrar panel">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            @if(($municipioBoundaryCount ?? 1) === 0)
            <div class="microregiones-boundaries-missing" role="status">
                <strong>Sin límites municipales en el servidor.</strong>
                Los pins se muestran, pero los polígonos requieren geometrías en BD. Ejecute en el servidor:
                <code class="microregiones-boundaries-missing-code">php artisan microregiones:fetch-boundaries</code>
            </div>
            @endif
            <div class="microregiones-search">
                <label class="microregiones-search-label" for="microregionesSearchInput">Buscar municipio o microrregión</label>
                <div class="microregiones-search-row">
                    <input type="search" id="microregionesSearchInput" class="microregiones-search-input" placeholder="Municipio de Puebla o MR3, MR 14..." autocomplete="off">
                    <button type="button" class="microregiones-search-btn" id="microregionesSearchGo" hidden title="Vista del estado de Puebla (alejar mapa)" aria-label="Vista del estado de Puebla"><i class="fa-solid fa-map" aria-hidden="true"></i></button>
                </div>
                <div class="microregiones-search-results" id="microregionesSearchResults" hidden></div>
                <div class="microregiones-search-pinned" id="microregionesSearchPinned" hidden>
                    <button type="button" class="microregiones-search-pinned-focus" id="microregionesSearchPinnedFocus" title="Volver a mostrar en el mapa" aria-label="Volver a enfocar en el mapa el resultado de búsqueda fijado">
                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                        <span class="microregiones-search-pinned-label" id="microregionesSearchPinnedLabel"></span>
                    </button>
                    <button type="button" class="microregiones-search-pinned-clear" id="microregionesSearchPinnedClear" title="Quitar resultado fijado" aria-label="Quitar resultado fijado del panel">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <p class="microregiones-search-hint microregiones-search-hint--status" id="microregionesSearchHint" hidden></p>
            </div>

            <div class="microregiones-accordion" id="microregionesAccordion"></div>
        </aside>
    </div>

    <button type="button" class="microregiones-mobile-drawer-tab" id="microregionesMobileDrawerTab" aria-expanded="false" aria-controls="microregionesSidebar" aria-label="Abrir panel de microrregiones y búsqueda">
        <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
        <span class="microregiones-mobile-drawer-tab-label">Panel</span>
    </button>
    <button type="button" class="microregiones-mobile-drawer-backdrop" id="microregionesMobileBackdrop" aria-label="Cerrar panel" tabindex="-1"></button>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdn.jsdelivr.net/npm/@turf/turf@6.5.0/turf.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="{{ asset('assets/js/modules/microregiones.js') }}?v={{ @filemtime(public_path('assets/js/modules/microregiones.js')) ?: time() }}" defer></script>
@endpush
