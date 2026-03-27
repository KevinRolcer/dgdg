@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/personal-agenda.css') }}?v={{ time() }}">
@endpush

@php
    $pageTitle = 'Agenda Personal';
    $hidePageHeader = true;
@endphp

@section('content')
<div class="personal-agenda-page">
    {{-- Sidebar Reducida --}}
    <aside class="pa-sidebar is-collapsed" id="pa-sidebar">
        <div class="pa-sidebar-toggle" id="btn-sidebar-collapse">
            <i class="fa-solid fa-angles-right"></i>
        </div>

        <button class="pa-btn-add" onclick="openPersonalNoteModal()">
            <i class="fa-solid fa-plus"></i>
            <span>Nueva Nota</span>
        </button>

        <nav class="pa-nav">
            <a href="#" class="pa-nav-item is-active" data-filter="all">
                <i class="fa-regular fa-lightbulb"></i>
                <span>Mis Notas</span>
            </a>
            <a href="#" class="pa-nav-item" data-filter="folders">
                <i class="fa-regular fa-folder-open"></i>
                <span>Carpetas</span>
            </a>
            <a href="#" class="pa-nav-item" data-filter="calendar">
                <i class="fa-regular fa-calendar-days"></i>
                <span>Calendario</span>
            </a>
            <a href="#" class="pa-nav-item" data-filter="archive">
                <i class="fa-regular fa-bookmark"></i>
                <span>Archivo</span>
            </a>
            <a href="#" class="pa-nav-item" data-filter="trash">
                <i class="fa-regular fa-trash-can"></i>
                <span>Papelera</span>
            </a>
        </nav>
    </aside>

    {{-- Contenido Principal --}}
    <main class="pa-main">
        {{-- Cabecera Ultra Compacta - Buscador + Filtros --}}
        <header class="pa-content-header">
            <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                <div class="pa-search-wrapper" style="margin: 0; width: 220px; flex-shrink: 0;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" class="pa-search-input" placeholder="Buscar notas..." id="pa-search">
                </div>

                <div class="pa-tabs-pills">
                    <span class="pa-tab-pill" data-tab="todays">Hoy</span>
                    <span class="pa-tab-pill is-active" data-tab="week">Semana</span>
                    <span class="pa-tab-pill" data-tab="month">Mes</span>
                </div>
            </div>

            <div style="display: flex; gap: 8px;">
                <button class="pa-btn-primary" onclick="openPersonalNoteModal()" title="Nueva Nota" style="border-radius: 50%; width: 40px; height: 40px; padding: 0; justify-content: center;">
                    <i class="fa-solid fa-plus" style="font-size: 1.1rem; margin: 0;"></i>
                </button>
            </div>
        </header>

        {{-- Breadcrumb & View Toggle (Explorer Bar) --}}
        <div class="pa-explorer-bar" id="pa-explorer-bar" style="display: none;">
            <nav class="pa-breadcrumb" id="pa-breadcrumb">
                <a href="#" class="pa-breadcrumb-item pa-breadcrumb-root" data-folder-id="">
                    <i class="fa-solid fa-house" style="font-size: 0.7rem;"></i> Mis Notas
                </a>
            </nav>

        </div>

        {{-- Carpetas --}}
        <section class="pa-section" id="section-folders">
            <div class="pa-section-header">
                <h3 class="pa-section-title" style="font-size: 1rem; opacity: 0.7;">Carpetas Recientes</h3>
                <span style="font-size: 0.75rem; color: var(--clr-text-light); cursor: pointer;" onclick="document.querySelector('[data-filter=\'folders\']').click()">Ver todas <i class="fa-solid fa-chevron-right"></i></span>
            </div>

            <div class="pa-slider-container pa-slider-container--folders" id="pa-folders-slider-container">
                <button type="button" class="pa-slider-btn pa-slider-btn--left" onclick="slideFolders('left')" aria-label="Ver carpetas anteriores">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <div class="pa-folder-grid" id="pa-folders-container">
                    @include('agenda.personal.partials.folders_grid', ['folders' => $folders])
                </div>
                <button type="button" class="pa-slider-btn pa-slider-btn--right" onclick="slideFolders('right')" aria-label="Ver carpetas siguientes">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </section>

        {{-- Carpetas archivadas (solo vista Archivo) --}}
        <section class="pa-section" id="section-archived-folders" style="{{ request('filter') === 'archive' ? '' : 'display: none;' }}">
            <div class="pa-section-header">
                <h3 class="pa-section-title" style="font-size: 1rem; opacity: 0.7;">Carpetas archivadas</h3>
            </div>
            <div class="pa-slider-container pa-slider-container--folders" id="pa-archived-folders-slider">
                <button type="button" class="pa-slider-btn pa-slider-btn--left" onclick="slideArchivedFolders('left')" aria-label="Carpetas anteriores">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <div class="pa-folder-grid" id="pa-archived-folders-container">
                    @include('agenda.personal.partials.archived_folders_grid', ['folders' => $archivedFolders])
                </div>
                <button type="button" class="pa-slider-btn pa-slider-btn--right" onclick="slideArchivedFolders('right')" aria-label="Carpetas siguientes">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </section>

        {{-- Notas (is-mis-notas-home: solo Ver Todo + mes; sin filtros extendidos hasta que cambie el nav) --}}
        <section class="pa-section is-mis-notas-home" id="section-notes">
            <div class="pa-section-header" id="notes-section-header" style="flex-wrap: wrap; gap: 15px;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <h3 class="pa-section-title" id="notes-section-title" style="font-size: 1rem; opacity: 0.7; margin: 0;">Notas Recientes</h3>
                </div>

                {{-- Filtros: en Mis notas (inicio) solo Ver Todo + navegador de mes; prioridad/carpeta/fecha en calendario y carpetas --}}
                <div class="pa-advanced-filters" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <button class="pa-filter-pill is-active" data-filter-type="all-notes" id="filter-all-notes">
                        Ver Todo
                    </button>

                    <div id="pa-filters-extended" class="pa-filters-extended">
                        <div class="pa-filter-pill-group pa-filter-pill-group--priority">
                            <span class="pa-filter-label">Prioridad</span>
                            <button type="button" class="pa-filter-pill pa-filter-pill--priority pa-filter-pill--high" data-priority="high" title="Solo prioridad alta">
                                <span class="pa-pri-swatch" aria-hidden="true"></span>
                                <span class="pa-pri-text">Alta</span>
                            </button>
                            <button type="button" class="pa-filter-pill pa-filter-pill--priority pa-filter-pill--medium" data-priority="medium" title="Solo prioridad media">
                                <span class="pa-pri-swatch" aria-hidden="true"></span>
                                <span class="pa-pri-text">Media</span>
                            </button>
                            <button type="button" class="pa-filter-pill pa-filter-pill--priority pa-filter-pill--low" data-priority="low" title="Solo prioridad baja">
                                <span class="pa-pri-swatch" aria-hidden="true"></span>
                                <span class="pa-pri-text">Baja</span>
                            </button>
                        </div>

                        <div class="pa-filter-pill-group">
                            <span class="pa-filter-label">Carpeta:</span>
                            <select class="pa-filter-select-pill" id="filter-folder">
                                <option value="">Todas</option>
                                @foreach($folders as $folder)
                                    <option value="{{ $folder->id }}">{{ $folder->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="pa-filter-pill-group">
                            <span class="pa-filter-label">Creado el:</span>
                            <input type="date" class="pa-filter-date-pill" id="filter-creation-date">
                        </div>
                    </div>
                </div>

                <div class="pa-notes-calendar-meta" id="notes-calendar-filter">

                    <button type="button" class="pa-cal-toggle-btn" id="pa-calendar-toggle-btn" aria-expanded="true">
                            Ocultar
                    </button>
                    <i class="fa-solid fa-chevron-left btn-cal-prev" role="button" tabindex="0" aria-label="Mes anterior" onclick="navCalendar('prev')"></i>
                    <span class="cal-current-month">{{ mb_strtoupper(now()->translatedFormat('M Y')) }}</span>
                    <i class="fa-solid fa-chevron-right btn-cal-next" role="button" tabindex="0" aria-label="Mes siguiente" onclick="navCalendar('next')"></i>
                    
                </div>
            </div>

            <div class="pa-slider-container" id="pa-slider-container">
                <button class="pa-slider-btn pa-slider-btn--left" onclick="slideNotes('left')">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <div class="pa-notes-grid" id="pa-notes-container">
                    @include('agenda.personal.partials.notes_grid', ['notes' => $notes, 'filter' => 'all'])
                </div>
                <button class="pa-slider-btn pa-slider-btn--right" onclick="slideNotes('right')">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </section>
    </main>
</div>

{{-- JSON con flags HEX: evita </script> u otros caracteres en nombres que rompen el parser JS (Unexpected end of input). --}}
<script id="pa-folders-json" type="application/json">
{!! json_encode($folders, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) !!}
</script>

<script>
    window.paRoutes = {
        {{-- URL absoluta: si solo se usa /personal-agenda, fetch apunta al dominio raíz y falla (404) con la app en subcarpeta. --}}
        index: @json(route('personal-agenda.index')),
        store: "{{ route('personal-agenda.store') }}",
        foldersStore: "{{ route('personal-agenda.folders.store') }}",
        foldersUpdate: "{{ route('personal-agenda.folders.update', ['folder' => ':id']) }}",
        foldersArchive: "{{ route('personal-agenda.folders.archive', ['folder' => ':id']) }}",
        foldersRestore: "{{ preg_replace('#/[0-9]+/restore$#', '/:id/restore', route('personal-agenda.folders.restore', ['folderId' => 999999])) }}",
        foldersPin: "{{ route('personal-agenda.folders.pin', ['folder' => ':id']) }}",
        foldersDestroy: "{{ route('personal-agenda.folders.destroy', ['folder' => ':id']) }}",
        attachmentsDestroy: "{{ route('personal-agenda.attachments.destroy', ['attachment' => ':id']) }}",
        attachmentsServe: "{{ route('personal-agenda.attachments.serve', ['attachment' => ':id']) }}",
        decrypt: "{{ route('personal-agenda.decrypt', ['note' => ':id']) }}",
        archive: "{{ route('personal-agenda.archive', ['note' => ':id']) }}",
        restore: "{{ route('personal-agenda.restore', ['id' => ':id']) }}",
        move: "{{ route('personal-agenda.move', ['note' => ':id']) }}",
        update: "{{ route('personal-agenda.update', ['note' => ':id']) }}",
        destroy: "{{ route('personal-agenda.destroy', ['note' => ':id']) }}",
    };
</script>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('assets/js/modules/personal-agenda.js') }}?v={{ time() }}"></script>
@endpush
