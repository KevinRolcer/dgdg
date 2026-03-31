@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/whatsapp-chats-index.css') }}?v={{ @filemtime(public_path('assets/css/modules/whatsapp-chats-index.css')) ?: time() }}">
@endpush

@php $hidePageHeader = true; @endphp

@section('content')
<section class="tm-page tm-shell app-density-compact">
    <div class="tm-shell-main">

        {{-- ALERTAS --}}
        @if (session('status'))
            <div class="inline-alert inline-alert-success" role="alert">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="inline-alert inline-alert-danger" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="inline-alert inline-alert-danger" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        {{-- BLOQUE DE IMPORTACIÓN LADO A LADO --}}
        <div class="wai-import-block">

            {{-- IZQUIERDA: CARPETA --}}
            <div id="waFolderUploadRoot"
                data-upload-url="{{ route('whatsapp-chats.admin.folder-upload') }}"
                data-finalize-url="{{ route('whatsapp-chats.admin.folder-finalize') }}"
                data-csrf="{{ csrf_token() }}"
                data-request-max-files="{{ (int) ($folderUploadRequestMaxFiles ?? 8) }}"
                data-parallel-requests="{{ (int) ($folderUploadParallelRequests ?? 4) }}"
                data-request-target-bytes="{{ (int) ($folderUploadRequestTargetBytes ?? (24 * 1024 * 1024)) }}"
            class="wai-import-panel wai-import-panel--folder"
            >
                <div class="wai-import-panel-head">
                    <div class="wai-import-icon wai-import-icon--folder">
                        <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="wai-import-title">Importar carpeta</h2>
                        <p class="wai-import-subtitle">(recomendado)</p>
                    </div>
                </div>

                <div class="wai-tab-warn" id="waFolderTabWarn" role="alert" hidden>
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <strong>No cierres ni cambies de pestaña</strong> mientras suben los archivos; si se interrumpe, vuelve a elegir la misma carpeta para reanudar.
                </div>

                <div class="wai-import-fields">
                    <input type="file" id="waFolderInput" class="wa-folder-input-hidden" multiple webkitdirectory directory>
                    <label class="wai-label-field">
                        <span class="wai-label-text">Nombre del chat (opcional)</span>
                        <input type="text" id="waFolderLabel" class="wai-text-input" maxlength="255" placeholder="Ej. Chat trabajo 2024" autocomplete="off">
                    </label>
                    <button type="button" class="wai-btn wai-btn--primary" id="waFolderPickBtn">
                        <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                        Elegir carpeta...
                    </button>
                </div>

                <div class="wai-progress-wrap" id="waFolderProgressWrap" hidden>
                    <div class="wai-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div class="wai-progress-bar" id="waFolderProgressBar"></div>
                    </div>
                    <p class="wai-progress-meta" id="waFolderProgressMeta"></p>
                </div>
                <p class="wai-folder-status" id="waFolderStatus" aria-live="polite"></p>
                <p class="wai-import-hint">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    Hasta ~{{ (int) ($maxUploadMb ?? 768) }} MB por archivo · Máx. {{ number_format((int) config('whatsapp_chats.folder_import_max_files', 25000)) }} archivos por lote
                </p>
            </div>

            {{-- DIVISOR --}}
            <div class="wai-import-divider">
                <span>O</span>
            </div>

            {{-- DERECHA: ZIP --}}
            <div class="wai-import-panel wai-import-panel--zip">
                <div class="wai-import-panel-head">
                    <div class="wai-import-icon wai-import-icon--zip">
                        <i class="fa-regular fa-file-zipper" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="wai-import-title">Subir ZIP</h2>
                        <p class="wai-import-subtitle">Archivo comprimido único</p>
                    </div>
                </div>
                <p class="wai-import-desc">
                    Compatible con export clásico <em>(_chat.txt + media)</em> y export en HTML por partes. El sistema lo descomprime y procesa automáticamente.
                </p>
                <form method="POST" action="{{ route('whatsapp-chats.admin.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="wai-import-fields">
                        <label class="wai-file-label" id="waiZipLabel">
                            <i class="fa-regular fa-file-zipper" aria-hidden="true"></i>
                            <span id="waiZipLabelText">Seleccionar archivo .zip</span>
                            <input type="file" name="archivo_zip" accept=".zip" required class="wai-file-hidden" id="waiZipInput">
                        </label>
                        <button type="submit" class="wai-btn wai-btn--accent">
                            <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                            Subir ZIP
                        </button>
                    </div>
                </form>
                <p class="wai-import-hint">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    Tamaño máximo: ~{{ (int) ($maxUploadMb ?? 768) }} MB (hasta 3 GB)
                </p>
            </div>
        </div>

        {{-- SECCIÓN: LISTA DE CHATS --}}
        <div class="wai-chats-section">
            <div class="wai-chats-head">
                <span class="wai-chats-section-title" id="waiTotalBadge">
                    {{ $chats->total() }} {{ $chats->total() === 1 ? 'chat' : 'chats' }}
                </span>

                {{-- FILTROS --}}
                <div class="wai-filters" id="waiFilters">
                    <div class="wai-search-wrap">
                        <i class="fa-solid fa-magnifying-glass wai-search-icon" aria-hidden="true"></i>
                        <input
                            type="text"
                            id="waiSearchInput"
                            class="wai-search-input"
                            placeholder="Buscar chats..."
                            autocomplete="off"
                            aria-label="Buscar chats"
                        >
                    </div>
                    <div class="wai-filter-chips" id="waiFilterChips" role="group" aria-label="Filtrar por estado">
                        <button type="button" class="wai-chip wai-chip--active" data-filter="all">Todos</button>
                        <button type="button" class="wai-chip" data-filter="ready">Listos</button>
                        <button type="button" class="wai-chip" data-filter="processing">Procesando</button>
                        <button type="button" class="wai-chip" data-filter="failed">Con error</button>
                    </div>
                </div>
            </div>

            {{-- GRID DE TARJETAS --}}
            @if ($chats->isEmpty())
                <div class="wai-empty-state">
                    <div class="wai-empty-icon">
                        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                    </div>
                    <p class="wai-empty-title">Sin chats importados</p>
                    <p class="wai-empty-desc">Usa el panel superior cargar chats.</p>
                </div>
            @else
                <div class="wai-chats-grid" id="waiChatsGrid">
                    @foreach ($chats as $chat)
                        @php
                            $partsCount = (int) ($chat->message_parts_count ?? 0);
                            $st = (string) ($chat->import_status ?? 'ready');
                            try {
                                $importedLabel = $chat->imported_at
                                    ? ($chat->imported_at instanceof \Carbon\Carbon
                                        ? $chat->imported_at->format('d/m/Y H:i')
                                        : \Carbon\Carbon::parse($chat->imported_at)->format('d/m/Y H:i'))
                                    : null;
                            } catch (\Throwable) { $importedLabel = null; }
                            $isReady      = $st === 'ready';
                            $isProcessing = $st === 'processing';
                            $isUploading  = $st === 'uploading';
                            $isFailed     = $st === 'failed';
                        @endphp

                        <article class="wai-chat-card" data-status="{{ $st }}" data-title="{{ strtolower($chat->title ?? '') }}" data-chat-id="{{ $chat->id }}">

                            {{-- Header de la tarjeta --}}
                            <div class="wai-card-head">
                                <div class="wai-card-avatar" aria-hidden="true">
                                    {{ strtoupper(mb_substr($chat->title ?? 'W', 0, 1)) }}
                                </div>
                                <div class="wai-card-info">
                                    <h3 class="wai-card-title">{{ $chat->title }}</h3>
                                    @if (!empty($chat->original_zip_name))
                                        <p class="wai-card-filename">
                                            <i class="fa-regular fa-file-zipper" aria-hidden="true"></i>
                                            {{ $chat->original_zip_name }}
                                        </p>
                                    @endif
                                </div>
                                {{-- Badge de estado --}}
                                @if ($isReady)
                                    <span class="wai-status-badge wai-status-badge--ready">
                                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Listo
                                    </span>
                                @elseif ($isProcessing)
                                    <span class="wai-status-badge wai-status-badge--processing">
                                        <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Procesando
                                    </span>
                                @elseif ($isUploading)
                                    <span class="wai-status-badge wai-status-badge--uploading">
                                        <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i> Subiendo
                                    </span>
                                @elseif ($isFailed)
                                    <span class="wai-status-badge wai-status-badge--failed">
                                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> Error
                                    </span>
                                @endif
                            </div>

                            {{-- Meta info --}}
                            <div class="wai-card-meta">
                                @if ($isReady && $partsCount > 0)
                                    <span class="wai-meta-item">
                                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                                        {{ $partsCount }} {{ $partsCount === 1 ? 'parte' : 'partes' }}
                                    </span>
                                @endif
                                @if ($importedLabel)
                                    <span class="wai-meta-item">
                                        <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                        {{ $importedLabel }}
                                    </span>
                                @endif
                                @if ($isUploading)
                                    <span class="wai-meta-item wai-meta-item--accent">
                                        <i class="fa-solid fa-upload" aria-hidden="true"></i>
                                        {{ (int) ($chat->folder_uploaded_files ?? 0) }} / {{ max(1, (int) ($chat->folder_total_files ?? 0)) }} archivos
                                    </span>
                                @endif
                                @if ($isFailed && !empty($chat->import_error))
                                    <span class="wai-meta-item wai-meta-item--danger" title="{{ is_scalar($chat->import_error) ? (string) $chat->import_error : json_encode($chat->import_error) }}">
                                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                        {{ \Illuminate\Support\Str::limit(is_scalar($chat->import_error) ? (string) $chat->import_error : json_encode($chat->import_error), 80) }}
                                    </span>
                                @endif
                            </div>


                            {{-- Popup de acciones --}}
                            <div class="wai-card-popup" role="menu" aria-label="Opciones del chat">
                                @if ($isReady)
                                    <a href="{{ route('whatsapp-chats.admin.show', ['chat' => $chat->id]) }}"
                                       class="wai-popup-item" role="menuitem">
                                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                                        Abrir chat
                                    </a>
                                @else
                                    <span class="wai-popup-item wai-popup-item--disabled" aria-disabled="true"
                                          title="{{ $isUploading ? 'Aún se reciben archivos' : ($isProcessing ? 'Aún se procesa' : 'Importación fallida') }}">
                                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                                        Abrir chat
                                    </span>
                                @endif
                                <div class="wai-popup-divider"></div>
                                <form
                                    class="js-wa-chat-delete-form"
                                    method="POST"
                                    action="{{ route('whatsapp-chats.admin.destroy', ['chat' => $chat->id]) }}"
                                    data-wa-delete-title="¿Eliminar esta exportación?"
                                    data-wa-delete-text="Se borrarán el registro y los archivos del chat en el servidor. No se puede deshacer."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="wai-popup-item wai-popup-item--danger" role="menuitem">
                                        <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>

                {{-- Paginación --}}
                <div class="wai-pagination">
                    {{ $chats->links() }}
                </div>

                {{-- Empty state para filtros JS --}}
                <div class="wai-filter-empty" id="waiFilterEmpty" hidden>
                    <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i>
                    <p>No hay chats que coincidan con tu búsqueda.</p>
                </div>
            @endif
        </div>

    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/modules/whatsapp-chats-admin-actions.js') }}?v={{ @filemtime(public_path('assets/js/modules/whatsapp-chats-admin-actions.js')) ?: time() }}"></script>
<script src="{{ asset('assets/js/modules/whatsapp-chats-folder-upload.js') }}?v={{ @filemtime(public_path('assets/js/modules/whatsapp-chats-folder-upload.js')) ?: time() }}"></script>
<script src="{{ asset('assets/js/modules/whatsapp-chats-resume-upload.js') }}?v={{ @filemtime(public_path('assets/js/modules/whatsapp-chats-resume-upload.js')) ?: time() }}"></script>
<script src="{{ asset('assets/js/modules/whatsapp-chats-index-ui.js') }}?v={{ @filemtime(public_path('assets/js/modules/whatsapp-chats-index-ui.js')) ?: time() }}"></script>
@endpush



