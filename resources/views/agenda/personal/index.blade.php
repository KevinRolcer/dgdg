@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/personal-agenda.css') }}?v={{ time() }}">
@endpush

@php
    $pageTitle = 'Agenda Personal';
    $hidePageHeader = true;

    // Helper to determine text contrast based on hex background
    function getContrastClass($hexColor) {
        if (!$hexColor || !str_starts_with($hexColor, '#')) return 'text-dark';

        $hexColor = str_replace('#', '', $hexColor);
        if (strlen($hexColor) == 3) {
            $r = hexdec(substr($hexColor, 0, 1) . substr($hexColor, 0, 1));
            $g = hexdec(substr($hexColor, 1, 1) . substr($hexColor, 1, 1));
            $b = hexdec(substr($hexColor, 2, 1) . substr($hexColor, 2, 1));
        } else {
            $r = hexdec(substr($hexColor, 0, 2));
            $g = hexdec(substr($hexColor, 2, 2));
            $b = hexdec(substr($hexColor, 4, 2));
        }

        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return $luminance > 0.6 ? 'text-dark' : 'text-light';
    }
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
        <header class="pa-content-header" style="margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; position: sticky; top: 0; background: var(--clr-bg); z-index: 10; padding: 10px 24px; margin-left: -24px; margin-right: -24px; border-bottom: 1px solid rgba(0,0,0,0.03);">
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

            <div class="pa-folder-grid" id="pa-folders-container">
                @foreach($folders as $folder)
                    <div class="pa-card pa-card--folder {{ getContrastClass($folder->color) }}"
                         style="background-color: {{ $folder->color ?? 'var(--pa-blue)' }}; position: relative;"
                         data-id="{{ $folder->id }}">
                        <div class="pa-folder-delete" style="position: absolute; top: 8px; right: 8px; opacity: 0; transition: opacity 0.2s; cursor: pointer;" onclick="event.stopPropagation(); deleteFolder({{ $folder->id }}, '{{ $folder->name }}')">
                            <i class="fa-solid fa-xmark" style="font-size: 0.8rem; color: rgba(0,0,0,0.3);"></i>
                        </div>
                        <div class="pa-folder-icon">
                            <i class="fa-solid {{ $folder->icon ?: 'fa-folder' }}"></i>
                        </div>
                        <h3 class="pa-card-title" style="margin: 0; font-size: 0.9rem;">{{ $folder->name }}</h3>
                        <span class="pa-folder-count-num" style="font-size: 0.65rem; opacity: 0.5;">{{ $folder->notes_count }} notas</span>
                    </div>
                @endforeach

                {{-- Nueva Carpeta --}}
                <div class="pa-card pa-card--folder pa-card--placeholder" style="border-style: dashed; padding: 12px;" onclick="openFolderModal()">
                    <div class="pa-placeholder-icon">
                        <i class="fa-solid fa-folder-plus"></i>
                    </div>
                    <span style="font-size: 0.75rem; font-weight: 700;">Nueva carpeta</span>
                </div>
            </div>
        </section>

        {{-- Notas --}}
        <section class="pa-section" id="section-notes">
            <div class="pa-section-header" id="notes-section-header">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <h3 class="pa-section-title" id="notes-section-title" style="font-size: 1rem; opacity: 0.7;">Notas Recientes</h3>
                </div>
                <div style="display: flex; gap: 8px; color: #999; align-items: center;" id="notes-calendar-filter">
                    <i class="fa-solid fa-chevron-left btn-cal-prev" style="cursor: pointer; font-size: 0.75rem;" onclick="navCalendar('prev')"></i>
                    <span style="font-size: 0.7rem; font-weight: 700; color: #333;" class="cal-current-month">{{ now()->translatedFormat('M Y') }}</span>
                    <i class="fa-solid fa-chevron-right btn-cal-next" style="cursor: pointer; font-size: 0.75rem;" onclick="navCalendar('next')"></i>
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

{{-- Hidden Folders Data for JS --}}
<script id="pa-folders-json" type="application/json">
    @json($folders)
</script>

<script>
    window.paRoutes = {
        index: "{{ route('personal-agenda.index') }}",
        store: "{{ route('personal-agenda.store') }}",
        foldersStore: "{{ route('personal-agenda.folders.store') }}",
        attachmentsDestroy: "{{ route('personal-agenda.attachments.destroy', ['attachment' => ':id']) }}",
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
