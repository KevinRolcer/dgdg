@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/whatsapp-chats-index.css') }}?v={{ @filemtime(public_path('assets/css/modules/whatsapp-chats-index.css')) ?: time() }}">
@endpush

@php $hidePageHeader = true; @endphp

@section('content')
<section class="tm-page tm-shell app-density-compact">
    <div class="tm-shell-main">
        <header class="tm-shell-head">
            <h1 class="tm-shell-title">Chats WhatsApp</h1>
            <p class="tm-shell-desc">Respaldo cifrado (nuevas importaciones). Si es la primera vez en la sesión, se pedirá el código de Google Authenticator.</p>
        </header>

        <article class="content-card tm-card tm-card-in-shell">
            @if (session('status'))
                <div class="inline-alert inline-alert-success" role="alert">{{ session('status') }}</div>
            @endif

            @if (session('error'))
                <div class="inline-alert inline-alert-danger" role="alert">{{ session('error') }}</div>
            @endif

            @if ($errors->any())
                <div class="inline-alert inline-alert-danger" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div
                class="wa-folder-upload-box"
                id="waFolderUploadRoot"
                data-upload-url="{{ route('whatsapp-chats.admin.folder-upload') }}"
                data-finalize-url="{{ route('whatsapp-chats.admin.folder-finalize') }}"
                data-csrf="{{ csrf_token() }}"
            >
                <h2 class="wa-upload-section-title">Importar carpeta (export descomprimido)</h2>
                <p class="wa-upload-section-desc">
                    Descomprime el ZIP de WhatsApp en tu equipo. Luego elige la carpeta del chat (o la que contiene la carpeta del chat): los archivos se suben <strong>uno por uno</strong> para evitar timeouts.
                </p>
                <div class="wa-folder-tab-warn" id="waFolderTabWarn" role="alert" hidden>
                    <strong>No cierres ni cambies de pestaña</strong> mientras suben los archivos; si interrumpes, vuelve a elegir la carpeta.
                </div>
                <div class="wa-form-row wa-folder-actions">
                    <input
                        type="file"
                        id="waFolderInput"
                        class="wa-folder-input-hidden"
                        multiple
                        webkitdirectory
                        directory
                    >
                    <button type="button" class="tm-btn tm-btn-primary" id="waFolderPickBtn">
                        <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                        Elegir carpeta…
                    </button>
                    <label class="wa-folder-label-field">
                        <span class="wa-folder-label-text">Nombre (opcional)</span>
                        <input type="text" id="waFolderLabel" class="wa-input wa-folder-label-input" maxlength="255" placeholder="Ej. Chat trabajo" autocomplete="off">
                    </label>
                </div>
                <div class="wa-folder-progress-wrap" id="waFolderProgressWrap" hidden>
                    <div class="wa-folder-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div class="wa-folder-progress-bar"></div>
                    </div>
                    <p class="wa-folder-progress-meta" id="waFolderProgressMeta"></p>
                </div>
                <p class="wa-folder-status" id="waFolderStatus" aria-live="polite"></p>
                <p class="wa-upload-hint">
                    Cada archivo admite hasta ~{{ (int) ($maxUploadMb ?? 768) }} MB (mismo tope que el ZIP). Máx. {{ number_format((int) config('whatsapp_chats.folder_import_max_files', 25000)) }} archivos por lote.
                </p>
            </div>

            <div class="wa-upload-divider">
                <span>O</span>
            </div>

            <div class="wa-upload-box">
                <h2 class="wa-upload-section-title">Subir un solo ZIP</h2>
                <form method="POST" action="{{ route('whatsapp-chats.admin.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="wa-form-row">
                        <input type="file" name="archivo_zip" accept=".zip" required>
                        <button type="submit" class="tm-btn tm-btn-primary">
                            <i class="fa-regular fa-file-zip" aria-hidden="true"></i>
                            Subir ZIP
                        </button>
                    </div>
                    <p class="wa-upload-hint">
                        Compatible con export clásico (_chat.txt + media) y export en HTML por partes.
                        <br>
                        <strong>Tamaño máximo (app):</strong> hasta ~{{ (int) ($maxUploadMb ?? 768) }} MB (3&nbsp;GB como tope).
                    </p>
                </form>
            </div>

            <div style="height:16px"></div>

            <div class="tm-table-wrap">
                <table class="tm-table">
                    <thead>
                        <tr>
                            <th>Chat</th>
                            <th>Partes</th>
                            <th>Importado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($chats as $chat)
                            @php
                                $partsCount = (int) ($chat->message_parts_count ?? 0);
                                $st = (string) ($chat->import_status ?? 'ready');
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $chat->title }}</strong>
                                    @if ($st === 'processing')
                                        <div><span class="tm-badge" style="background:var(--clr-warning-soft,#fff3cd);color:#856404;">Procesando…</span></div>
                                    @elseif ($st === 'failed')
                                        <div><span class="tm-badge" style="background:var(--clr-danger-soft,#f8d7da);color:#721c24;">Error</span></div>
                                        @if (!empty($chat->import_error))
                                            <div><small style="color:var(--clr-text-muted)">{{ \Illuminate\Support\Str::limit($chat->import_error, 120) }}</small></div>
                                        @endif
                                    @endif
                                    @if (!empty($chat->original_zip_name))
                                        <div><small style="color:var(--clr-text-muted)">{{ $chat->original_zip_name }}</small></div>
                                    @endif
                                </td>
                                <td>{{ $st === 'ready' ? $partsCount : '—' }}</td>
                                <td>{{ $st === 'processing' ? '—' : optional($chat->imported_at)->format('d/m/Y H:i') }}</td>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        @if ($st === 'ready')
                                        <a href="{{ route('whatsapp-chats.admin.show', ['chat' => $chat->id]) }}" class="tm-btn tm-btn-outline tm-btn-sm">
                                            Ver
                                        </a>
                                        @else
                                        <span class="tm-btn tm-btn-outline tm-btn-sm" style="opacity:0.5;pointer-events:none;cursor:not-allowed;" title="{{ $st === 'processing' ? 'Aún se procesa' : 'Importación fallida' }}">Ver</span>
                                        @endif
                                        <form method="POST" action="{{ route('whatsapp-chats.admin.destroy', ['chat' => $chat->id]) }}" onsubmit="return confirm('¿Eliminar esta exportación de chat y sus archivos?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="tm-btn tm-btn-danger tm-btn-sm">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">Aún no has importado chats.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:14px;">
                {{ $chats->links() }}
            </div>
        </article>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/modules/whatsapp-chats-folder-upload.js') }}?v={{ @filemtime(public_path('assets/js/modules/whatsapp-chats-folder-upload.js')) ?: time() }}"></script>
@endpush
