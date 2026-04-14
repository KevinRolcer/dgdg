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

        {{-- HEADER COMPACTO --}}
        <header class="wa-preview-page-head">
            <div class="wa-preview-head-left">
                <a href="{{ route('whatsapp-chats.admin.index') }}" class="wa-back-btn" title="Volver a la lista">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                </a>
                <div class="wa-preview-head-info">
                    <h1 class="wa-preview-title">
                        <i class="fa-brands fa-whatsapp wa-preview-title-icon" aria-hidden="true"></i>
                        {{ $chat->title }}
                    </h1>
                    <p class="wa-preview-subtitle">
                        @if ($waPreviewMode === 'txt')
                            <span class="wa-head-badge wa-head-badge--txt">
                                <i class="fa-solid fa-file-lines" aria-hidden="true"></i> TXT
                            </span>
                            {{ $txtMessages->count() }} mensajes cargados
                        @else
                            <span class="wa-head-badge wa-head-badge--html">
                                <i class="fa-solid fa-code" aria-hidden="true"></i> HTML
                            </span>
                            Vista por partes HTML del ZIP
                        @endif
                    </p>
                </div>
            </div>
            <div class="wa-preview-head-actions">
                <button type="button" class="wa-panel-toggle-btn wa-chatlist-toggle-btn" id="waChatsToggle" title="Ver todos los chats" aria-expanded="false" aria-controls="waChatsSidebar">
                    <i class="fa-solid fa-comments" aria-hidden="true"></i>
                    <span class="wa-panel-toggle-label">Ver todos los chats</span>
                </button>
                <button type="button" class="wa-panel-toggle-btn" id="wasPanelToggle" title="Mostrar / ocultar panel derecho" aria-expanded="true" aria-controls="wasRightPanel">
                    <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                    <span class="wa-panel-toggle-label">Filtros &amp; Búsqueda</span>
                </button>
                <form
                    class="js-wa-chat-delete-form"
                    method="POST"
                    action="{{ route('whatsapp-chats.admin.destroy', ['chat' => $chat->id]) }}"
                    data-wa-delete-title="¿Eliminar esta exportación?"
                    data-wa-delete-text="Se borrarán el registro y los archivos del chat en el servidor. No se puede deshacer."
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="wa-action-btn wa-action-btn--danger" title="Eliminar chat">
                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
        </header>

        @if (!empty($txtPreviewSkippedLargeFile))
            <div class="inline-alert inline-alert-warning wa-inline-alert-warning" role="status">
                El archivo TXT supera {{ (int) ($txtPreviewMaxFileMb ?? 15) }} MB. Usa las <strong>partes HTML</strong>. El respaldo completo está en el servidor.
            </div>
        @endif

        {{-- LAYOUT PRINCIPAL: CHAT IZQUIERDA + PANEL DERECHA --}}
        <div
            class="wa-workspace"
            id="waPreviewRoot"
            data-wa-preview-mode="{{ $waPreviewMode }}"
        >
            {{-- COLUMNA IZQUIERDA: LISTA / VISTA PREVIA (tipo WhatsApp Web) --}}
            <nav class="wa-workspace-sidebar" id="waChatsSidebar" aria-label="Vista previa de chats">
                <div class="wa-sidebar-top">
                    <div class="wa-sidebar-search">
                        <input
                            id="waSidebarSearchInput"
                            type="search"
                            class="wa-sidebar-search-input"
                            placeholder="Buscar chats"
                            autocomplete="off"
                        >
                    </div>
                </div>
                <div class="wa-sidebar-list" id="waSidebarList">
                    @foreach (($chatsList ?? collect()) as $sideChat)
                        @php
                            $sideStatus = (string) ($sideChat->import_status ?? 'ready');
                            $sideReady = $sideStatus === 'ready';
                            $sideActive = (int) $sideChat->id === (int) $chat->id;
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
                            class="wa-chatlist-item {{ $sideActive ? 'wa-chatlist-item--active' : '' }} {{ $sideReady ? '' : 'wa-chatlist-item--disabled' }}"
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

            {{-- COLUMNA IZQUIERDA: CHAT --}}
            <div class="wa-workspace-chat" aria-label="Área de chat">

                @if ($waPreviewMode === 'html')
                    {{-- SELECTOR DE PARTES (horizontal cuando HTML) --}}
                    @if(count($messageParts) > 1)
                    <div class="wa-parts-bar" role="tablist" aria-label="Partes del chat">
                        @foreach ($messageParts as $idx => $relPath)
                            @php $isActive = ($idx === 0); @endphp
                            <button
                                type="button"
                                role="tab"
                                class="wa-part-tab {{ $isActive ? 'wa-active-part' : '' }}"
                                data-part-index="{{ $idx }}"
                                data-part-url="{{ $messageUrls->values()->all()[$idx] ?? '' }}"
                                aria-selected="{{ $isActive ? 'true' : 'false' }}"
                            >
                                <i class="fa-solid fa-file-code" aria-hidden="true"></i>
                                Parte {{ $idx + 1 }}
                            </button>
                        @endforeach
                    </div>
                    @endif
                    <div class="wa-iframe-wrap">
                        @php
                            $messageUrlsArr = $messageUrls->values()->all();
                            $activeUrl = $messageUrlsArr[0] ?? null;
                        @endphp
                        <iframe
                            id="waChatIframe"
                            class="wa-iframe"
                            src="{{ $activeUrl }}"
                            loading="lazy"
                            title="Vista del chat"
                        ></iframe>
                    </div>

                @else
                    {{-- BARRA DE ESTADÍSTICAS --}}
                    <div class="wa-stats-bar" id="waStatsBar" role="status" aria-live="polite">
                        <div class="wa-stats-group" id="waStatsVisible" hidden>
                            <i class="fa-solid fa-filter wa-stats-icon wa-stats-icon--accent" aria-hidden="true"></i>
                            <span id="waStatsVisibleCount" class="wa-stats-num wa-stats-num--accent">0</span>
                            <span class="wa-stats-lbl">visibles</span>
                        </div>
                        <div class="wa-stats-group">
                            <i class="fa-solid fa-users wa-stats-icon" aria-hidden="true"></i>
                            <span class="wa-stats-num">{{ ($txtMessages ?? collect())->pluck('author')->unique()->filter()->count() }}</span>
                            <span class="wa-stats-lbl">participantes</span>
                        </div>
                    </div>

                    {{-- ÁREA DE MENSAJES --}}
                    <div class="wa-chat-txt-wrap" id="waChatTxtWrap" role="log" aria-live="polite">
                        <div id="waChatSentinel" class="wa-sentinel"></div>
                    </div>

                    <script id="waMessagesData" type="application/json">
                        @json($txtMessages ?? [])
                    </script>
                @endif
            </div>

            {{-- COLUMNA DERECHA: PANEL DE FILTROS Y BÚSQUEDA --}}
            <aside class="wa-workspace-panel" id="wasRightPanel" aria-label="Filtros y búsqueda">

                {{-- TABS DEL PANEL --}}
                <div class="wa-panel-tabs" role="tablist">
                    @if ($waPreviewMode === 'txt')
                    <button
                        type="button"
                        class="wa-panel-tab wa-panel-tab--active"
                        role="tab"
                        id="wasTabFilterBtn"
                        aria-selected="true"
                        aria-controls="wasTabFilterContent"
                    >
                        <i class="fa-solid fa-filter" aria-hidden="true"></i>
                        Filtros
                    </button>
                    <button
                        type="button"
                        class="wa-panel-tab"
                        role="tab"
                        id="wasTabSearchBtn"
                        aria-selected="false"
                        aria-controls="wasTabSearchContent"
                    >
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        Buscar
                    </button>
                    @else
                    <button
                        type="button"
                        class="wa-panel-tab wa-panel-tab--active"
                        role="tab"
                        id="wasTabPartsBtn"
                        aria-selected="true"
                        aria-controls="wasTabPartsContent"
                    >
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                        Partes ({{ count($messageParts) }})
                    </button>
                    @endif
                </div>

                {{-- CONTENIDO DE TABS --}}
                <div class="wa-panel-body">

                    @if ($waPreviewMode === 'txt')

                    {{-- TAB: FILTROS --}}
                    <div
                        class="wa-tab-content wa-tab-content--active"
                        role="tabpanel"
                        id="wasTabFilterContent"
                        aria-labelledby="wasTabFilterBtn"
                    >
                        <div class="wa-filter-section">
                            <p class="wa-filter-section-label">
                                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                Rango de fechas
                            </p>
                            <div class="wa-field-row">
                                <div class="wa-field">
                                    <label class="wa-label" for="waFilterDateFrom">Desde</label>
                                    <div class="wa-input-wrap">
                                        <input type="text" id="waFilterDateFrom" class="wa-input wa-input-has-icon" placeholder="dd/mm/yyyy" autocomplete="off">
                                    </div>
                                </div>
                                <div class="wa-field">
                                    <label class="wa-label" for="waFilterDateTo">Hasta</label>
                                    <div class="wa-input-wrap">
                                        <input type="text" id="waFilterDateTo" class="wa-input wa-input-has-icon" placeholder="dd/mm/yyyy" autocomplete="off">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="wa-filter-section">
                            <p class="wa-filter-section-label">
                                <i class="fa-solid fa-user" aria-hidden="true"></i>
                                Participante
                            </p>
                            <div class="wa-input-wrap">
                                <i class="fa-solid fa-chevron-down wa-select-arrow" aria-hidden="true"></i>
                                <select id="waFilterAuthor" class="wa-input wa-select">
                                    <option value="">Todos los participantes</option>
                                    @foreach (($txtMessages ?? collect())->pluck('author')->unique()->filter() as $authorName)
                                        <option value="{{ $authorName }}">{{ $authorName }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="wa-filter-section">
                            <p class="wa-filter-section-label">
                                <i class="fa-solid fa-tag" aria-hidden="true"></i>
                                Tipo de contenido
                            </p>
                            <div class="wa-toggle-group">
                                <label class="wa-toggle-item" id="waToggleMediaLabel">
                                    <input type="checkbox" id="waFilterMediaOnly" value="1" class="wa-toggle-input">
                                    <span class="wa-toggle-chip">
                                        <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                                        Con archivos
                                    </span>
                                </label>
                                <label class="wa-toggle-item" id="waToggleLongLabel">
                                    <input type="checkbox" id="waFilterLongOnly" value="1" class="wa-toggle-input">
                                    <span class="wa-toggle-chip">
                                        <i class="fa-solid fa-align-left" aria-hidden="true"></i>
                                        Texto largo
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="wa-filter-actions">
                            <button type="button" class="wa-filter-reset-btn" id="waFilterReset">
                                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                                Restablecer filtros
                            </button>
                        </div>

                        {{-- RESUMEN DE FILTROS ACTIVOS --}}
                        <div class="wa-active-filters" id="waActiveFilters" hidden>
                            <p class="wa-active-filters-title">
                                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                Filtros activos
                            </p>
                            <div class="wa-active-filters-chips" id="waActiveFiltersChips"></div>
                        </div>
                    </div>

                    {{-- TAB: BÚSQUEDA --}}
                    <div
                        class="wa-tab-content"
                        role="tabpanel"
                        id="wasTabSearchContent"
                        aria-labelledby="wasTabSearchBtn"
                        hidden
                    >
                        <div class="wa-search-wrap">
                            <div class="wa-search-field">
                                <i class="fa-solid fa-magnifying-glass wa-search-icon" aria-hidden="true"></i>
                                <input
                                    type="search"
                                    id="waSearchInput"
                                    class="wa-search-input"
                                    placeholder="Buscar en mensajes…"
                                    autocomplete="off"
                                    spellcheck="false"
                                >
                            </div>
                            <p class="wa-search-meta" id="waSearchMeta">Escribe para buscar…</p>
                            <div class="wa-search-results" id="waSearchResults" role="listbox" aria-label="Resultados de búsqueda">
                                <div id="waSearchSentinel" class="wa-sentinel"></div>
                            </div>
                        </div>
                    </div>

                    @else
                    {{-- TAB: PARTES HTML --}}
                    <div
                        class="wa-tab-content wa-tab-content--active"
                        role="tabpanel"
                        id="wasTabPartsContent"
                        aria-labelledby="wasTabPartsBtn"
                    >
                        <div class="wa-search-wrap">
                            <p class="wa-panel-hint">
                                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                Selecciona una parte para visualizarla en el visor de chat.
                            </p>
                            <div class="wa-parts-list" role="tablist" aria-label="Partes del chat">
                                @foreach ($messageParts as $idx => $relPath)
                                    @php $isActive = ($idx === 0); @endphp
                                    <button
                                        type="button"
                                        class="wa-part-card {{ $isActive ? 'wa-active-part' : '' }}"
                                        data-part-index="{{ $idx }}"
                                        data-part-url="{{ $messageUrls->values()->all()[$idx] ?? '' }}"
                                    >
                                        <span class="wa-part-card-num">{{ $idx + 1 }}</span>
                                        <span class="wa-part-card-label">Parte {{ $idx + 1 }}</span>
                                        <i class="fa-solid fa-chevron-right wa-part-card-arrow" aria-hidden="true"></i>
                                    </button>
                                @endforeach
                            </div>

                            <div class="wa-side-card--search wa-side-card--muted wa-side-card--search-gap">
                                <p class="wa-panel-hint">
                                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                    La búsqueda en texto solo está disponible en modo TXT. Importa un ZIP con <code>_chat.txt</code> para habilitarla.
                                </p>
                            </div>
                        </div>
                    </div>
                    @endif

                </div>
            </aside>
        </div>

    </div>
</section>

@push('scripts')
<script src="{{ asset('assets/js/modules/whatsapp-chats-admin-actions.js') }}?v={{ @filemtime(public_path('assets/js/modules/whatsapp-chats-admin-actions.js')) ?: time() }}&t={{ time() }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/l10n/es.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="{{ asset('assets/js/modules/whatsapp-chats-show.js') }}?v={{ @filemtime(public_path('assets/js/modules/whatsapp-chats-show.js')) ?: time() }}&t={{ time() }}"></script>
<script src="{{ asset('assets/js/modules/whatsapp-chats-show-ui.js') }}?v={{ @filemtime(public_path('assets/js/modules/whatsapp-chats-show-ui.js')) ?: time() }}"></script>
@endpush
@endsection
