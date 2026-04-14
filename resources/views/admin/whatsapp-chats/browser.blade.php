@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}&t={{ time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/whatsapp-chats-show.css') }}?v={{ @filemtime(public_path('assets/css/modules/whatsapp-chats-show.css')) ?: time() }}&t={{ time() }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
@endpush

@php $hidePageHeader = true; @endphp

@section('content')
<section class="tm-page tm-shell app-density-compact">
    <div class="tm-shell-main">

        <header class="wa-preview-page-head">
            <div class="wa-preview-head-left">
                <a href="{{ route('whatsapp-chats.admin.index') }}" class="wa-back-btn" title="Volver a la lista">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                </a>
                <div class="wa-preview-head-info">
                    <h1 class="wa-preview-title">
                        <i class="fa-brands fa-whatsapp wa-preview-title-icon" aria-hidden="true"></i>
                        Chats WhatsApp
                    </h1>
                    <p class="wa-preview-subtitle">
                        Abre un chat para comenzar a leer los mensajes
                    </p>
                </div>
            </div>
            <div class="wa-preview-head-actions">
                <button type="button" class="wa-panel-toggle-btn" id="wasPanelToggle" title="Mostrar / ocultar panel derecho" aria-expanded="false" aria-controls="wasRightPanel">
                    <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                    <span class="wa-panel-toggle-label">Filtros &amp; Búsqueda</span>
                </button>
            </div>
        </header>

        <div class="wa-workspace wa-workspace--browser wa-workspace--sidebar-open" id="waPreviewRoot" data-wa-preview-mode="browser">
            <nav class="wa-workspace-sidebar" id="waChatsSidebar" aria-label="Vista previa de chats">
                <div class="wa-sidebar-top">
                    <div class="wa-sidebar-top-row">
                        <div class="wa-sidebar-search">
                            <i class="fa-solid fa-magnifying-glass wa-sidebar-search-icon" aria-hidden="true"></i>
                            <input
                                id="waSidebarSearchInput"
                                type="search"
                                class="wa-sidebar-search-input"
                                placeholder="Buscar chats"
                                autocomplete="off"
                            >
                        </div>
                        <a href="{{ route('whatsapp-chats.admin.browser') }}" class="wa-sidebar-action-btn" title="Ver todos los chats">
                            <i class="fa-solid fa-comments" aria-hidden="true"></i>
                        </a>
                        <button type="button" class="wa-sidebar-action-btn" id="waChatsCollapseBtn" title="Comprimir / expandir lista" aria-expanded="true">
                            <i class="fa-solid fa-angles-left" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="wa-sidebar-list" id="waSidebarList">
                    @foreach (($chatsList ?? collect()) as $sideChat)
                        @php
                            $sideStatus = (string) ($sideChat->import_status ?? 'ready');
                            $sideReady = $sideStatus === 'ready';
                            try {
                                $sideImportedLabel = $sideChat->imported_at
                                    ? ($sideChat->imported_at instanceof \Carbon\Carbon
                                        ? $sideChat->imported_at->format('d/m/Y')
                                        : \Carbon\Carbon::parse($sideChat->imported_at)->format('d/m/Y'))
                                    : null;
                            } catch (\Throwable) { $sideImportedLabel = null; }
                        @endphp

                        <a
                            href="{{ $sideReady ? route('whatsapp-chats.admin.show', ['chat' => $sideChat->id]) : route('whatsapp-chats.admin.index') }}"
                            class="wa-chatlist-item {{ $sideReady ? '' : 'wa-chatlist-item--disabled' }}"
                            data-title="{{ strtolower($sideChat->title ?? '') }}"
                            @if (! $sideReady) aria-disabled="true" @endif
                            title="{{ $sideReady ? 'Abrir chat' : 'Este chat aún no está listo' }}"
                        >
                            <div class="wa-chatlist-avatar" aria-hidden="true"></div>
                            <div class="wa-chatlist-main">
                                <div class="wa-chatlist-row">
                                    <span class="wa-chatlist-title">{{ $sideChat->title }}</span>
                                    @if ($sideImportedLabel)
                                        <span class="wa-chatlist-date">{{ $sideImportedLabel }}</span>
                                    @endif
                                </div>
                                <div class="wa-chatlist-sub">
                                    @if (! $sideReady)
                                        <span class="wa-chatlist-pill wa-chatlist-pill--muted">{{ $sideStatus === 'failed' ? 'Error' : 'Procesando' }}</span>
                                    @elseif (!empty($sideChat->original_zip_name))
                                        <span class="wa-chatlist-snippet">{{ \Illuminate\Support\Str::limit((string) $sideChat->original_zip_name, 44) }}</span>
                                    @else
                                        <span class="wa-chatlist-snippet">&nbsp;</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </nav>

            <div class="wa-workspace-chat" aria-label="Área de chat">
                <div class="wa-chat-empty">
                    <div class="wa-chat-empty-icon" aria-hidden="true">
                        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                    </div>
                    <p class="wa-chat-empty-title">Abre un chat para comenzar</p>
                    <p class="wa-chat-empty-sub">Selecciona un chat de la lista para leer los mensajes.</p>
                </div>
            </div>

            <aside class="wa-workspace-panel" id="wasRightPanel" aria-label="Filtros y búsqueda">
                <div class="wa-panel-tabs" role="tablist">
                    <button type="button" class="wa-panel-tab wa-panel-tab--active" role="tab" aria-selected="true">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        Info
                    </button>
                </div>
                <div class="wa-panel-body">
                    <div class="wa-filter-section">
                        <p class="wa-filter-section-label">
                            <i class="fa-solid fa-list" aria-hidden="true"></i>
                            Lista de chats
                        </p>
                        <p class="wa-chatlist-help">
                            Usa el buscador de la izquierda para filtrar chats por nombre.
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/l10n/es.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="{{ asset('assets/js/modules/whatsapp-chats-show.js') }}?v={{ @filemtime(public_path('assets/js/modules/whatsapp-chats-show.js')) ?: time() }}&t={{ time() }}"></script>
<script src="{{ asset('assets/js/modules/whatsapp-chats-show-ui.js') }}?v={{ @filemtime(public_path('assets/js/modules/whatsapp-chats-show-ui.js')) ?: time() }}&t={{ time() }}"></script>
@endpush

