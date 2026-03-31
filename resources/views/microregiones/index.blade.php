@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="{{ asset('assets/css/modules/microregiones.css') }}?v={{ @filemtime(public_path('assets/css/modules/microregiones.css')) ?: time() }}">
@endpush

@section('content')
@php $hidePageHeader = true; @endphp
<div class="microregiones-page" id="microregionesRoot" data-data-url="{{ route('microregiones.data') }}">

    <div class="microregiones-layout">
        <div class="microregiones-map-wrap">
            <div id="microregionesMap" class="microregiones-map" role="application" aria-label="Mapa de microrregiones"></div>
            <button type="button" class="microregiones-sidebar-toggle" id="microregionesToggleSidebar" aria-expanded="true" aria-controls="microregionesSidebar">
                <span class="microregiones-sidebar-toggle-text">Ocultar panel</span>
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </button>

            <!-- Floating Detail Card -->
            <div class="microregiones-floating-detail microregiones-floating-detail--hidden" id="microregionesDetail">
                <button type="button" class="microregiones-floating-close" id="microregionesDetailClose" title="Cerrar detalles">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <div id="microregionesDetailContent"></div>
            </div>
        </div>

        <aside class="microregiones-sidebar" id="microregionesSidebar">
            <div class="microregiones-search">
                <label class="microregiones-search-label" for="microregionesSearchInput">Buscar municipio o microrregión</label>
                <div class="microregiones-search-row">
                    <input type="search" id="microregionesSearchInput" class="microregiones-search-input" placeholder="Municipio de Puebla o MR3, MR 14…" autocomplete="off">
                    <button type="button" class="microregiones-search-btn" id="microregionesSearchGo" title="Limpiar búsqueda"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <p class="microregiones-search-hint" id="microregionesSearchHint" hidden></p>
            </div>


            <div class="microregiones-accordion" id="microregionesAccordion"></div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdn.jsdelivr.net/npm/@turf/turf@6.5.0/turf.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="{{ asset('assets/js/modules/microregiones.js') }}?v={{ @filemtime(public_path('assets/js/modules/microregiones.js')) ?: time() }}" defer></script>
@endpush
