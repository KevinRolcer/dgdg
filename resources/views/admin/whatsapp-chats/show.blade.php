@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/whatsapp-chats-show.css') }}?v={{ @filemtime(public_path('assets/css/modules/whatsapp-chats-show.css')) ?: time() }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
@endpush

@php $hidePageHeader = true; @endphp

@section('content')
<section class="tm-page tm-shell app-density-compact">
    <div class="tm-shell-main">
        <header class="tm-shell-head wa-preview-page-head">
            <div>
                <h1 class="tm-shell-title">WhatsApp: {{ $chat->title }}</h1>
                <p class="tm-shell-desc">
                    @if ($waPreviewMode === 'txt')
                        Vista previa desde TXT: filtros a la izquierda, búsqueda y coincidencias a la derecha.
                    @else
                        Vista por partes HTML del ZIP. La búsqueda en texto está disponible si el export incluye <code>_chat.txt</code> parseable.
                    @endif
                </p>
            </div>
            <div class="wa-preview-head-actions">
                <form method="POST" action="{{ route('whatsapp-chats.admin.destroy', ['chat' => $chat->id]) }}" onsubmit="return confirm('¿Eliminar esta exportación de chat y sus archivos?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="tm-btn tm-btn-danger tm-btn-sm">Eliminar</button>
                </form>
                <a href="{{ route('whatsapp-chats.admin.index') }}" class="tm-btn tm-btn-outline tm-btn-sm">Volver</a>
            </div>
        </header>

        <article class="content-card tm-card tm-card-in-shell wa-preview-card">
            <div
                class="wa-preview-layout"
                id="waPreviewRoot"
                data-wa-preview-mode="{{ $waPreviewMode }}"
            >
                <div class="wa-preview-cols">
                    {{-- Izquierda: filtros (TXT) o partes HTML --}}
                    <aside class="wa-preview-side wa-preview-side--left" aria-label="Filtros y partes">
                        @if ($waPreviewMode === 'txt')
                            <div class="wa-side-card wa-side-card--filters">
                                <h2 class="wa-side-title">Filtros</h2>

                                <div class="wa-field">
                                    <label class="wa-label" for="waFilterDateFrom">Fecha desde</label>
                                    <input type="text" id="waFilterDateFrom" class="wa-input" placeholder="Seleccionar…" autocomplete="off">
                                </div>
                                <div class="wa-field">
                                    <label class="wa-label" for="waFilterDateTo">Fecha hasta</label>
                                    <input type="text" id="waFilterDateTo" class="wa-input" placeholder="Seleccionar…" autocomplete="off">
                                </div>

                                <div class="wa-field">
                                    <label class="wa-label" for="waFilterAuthor">Autor</label>
                                    <select id="waFilterAuthor" class="wa-input wa-select">
                                        <option value="">Todos los autores</option>
                                        @foreach (($txtMessages ?? collect())->pluck('author')->unique()->filter() as $authorName)
                                            <option value="{{ $authorName }}">{{ $authorName }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <label class="wa-check">
                                    <input type="checkbox" id="waFilterMediaOnly" value="1">
                                    <span>Solo mensajes con archivo multimedia</span>
                                </label>

                                <label class="wa-check">
                                    <input type="checkbox" id="waFilterLongOnly" value="1">
                                    <span>Solo texto largo (más de 200 caracteres)</span>
                                </label>

                                <button type="button" class="wa-btn wa-btn-secondary wa-filter-reset" id="waFilterReset">
                                    Restablecer filtros
                                </button>
                            </div>
                        @else
                            <div class="wa-side-card wa-side-card--parts">
                                <h2 class="wa-side-title">Partes del ZIP</h2>
                                <p class="wa-side-hint">Cada parte es un fragmento HTML del export.</p>
                                <div class="wa-parts-vertical" role="tablist" aria-label="Partes del chat">
                                    @foreach ($messageParts as $idx => $relPath)
                                        @php $isActive = ($idx === 0); @endphp
                                        <button
                                            type="button"
                                            class="wa-part-btn wa-part-btn--block {{ $isActive ? 'wa-active-part' : '' }}"
                                            data-part-index="{{ $idx }}"
                                            data-part-url="{{ $messageUrls->values()->all()[$idx] ?? '' }}"
                                        >
                                            Parte {{ $idx + 1 }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </aside>

                    {{-- Centro: hilo --}}
                    <main class="wa-preview-main" aria-label="Vista del chat">
                        @if ($waPreviewMode === 'html')
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
                                    title="Vista del chat importado"
                                ></iframe>
                            </div>
                        @else
                            <div class="wa-chat-txt-wrap" id="waChatTxtWrap" role="log" aria-live="polite">
                                {{-- Messages rendered by JS --}}
                                <div id="waChatSentinel" class="wa-sentinel"></div>
                            </div>

                            <script id="waMessagesData" type="application/json">
                                @json($txtMessages ?? [])
                            </script>
                        @endif
                    </main>

                    {{-- Derecha: búsqueda + coincidencias --}}
                    <aside class="wa-preview-side wa-preview-side--right" aria-label="Búsqueda">
                        @if ($waPreviewMode === 'txt')
                            <div class="wa-side-card wa-side-card--search">
                                <h2 class="wa-side-title">Buscar</h2>
                                <div class="wa-search-field">
                                    <input
                                        type="search"
                                        id="waSearchInput"
                                        class="wa-search-input"
                                        placeholder="Texto en mensajes…"
                                        autocomplete="off"
                                        spellcheck="false"
                                    >
                                    <span class="wa-search-icon" aria-hidden="true">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                                    </span>
                                </div>
                                <p class="wa-search-meta" id="waSearchMeta">Escribe para ver coincidencias entre los mensajes visibles (según filtros).</p>
                                <div class="wa-search-results" id="waSearchResults" role="listbox" aria-label="Coincidencias">
                                    <div id="waSearchSentinel" class="wa-sentinel"></div>
                                </div>
                            </div>
                        @else
                            <div class="wa-side-card wa-side-card--search wa-side-card--muted">
                                <h2 class="wa-side-title">Buscar en texto</h2>
                                <p class="wa-side-hint">
                                    No disponible en vista HTML. Si necesitas buscar y filtrar por fecha o número de mensaje, importa un ZIP que incluya el archivo de conversación en formato TXT (<code>_chat.txt</code>).
                                </p>
                            </div>
                        @endif
                    </aside>
                </div>
            </div>
        </article>
    </div>
</section>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/l10n/es.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="{{ asset('assets/js/modules/whatsapp-chats-show.js') }}?v={{ @filemtime(public_path('assets/js/modules/whatsapp-chats-show.js')) ?: time() }}"></script>
@endpush
@endsection
