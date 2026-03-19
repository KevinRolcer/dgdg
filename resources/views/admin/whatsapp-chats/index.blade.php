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

            @if ($errors->any())
                <div class="inline-alert inline-alert-danger" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="wa-upload-box">
                <form method="POST" action="{{ route('whatsapp-chats.admin.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="wa-form-row">
                        <input type="file" name="archivo_zip" accept=".zip" required>
                        <button type="submit" class="tm-btn tm-btn-primary">
                            <i class="fa-regular fa-file-zip" aria-hidden="true"></i>
                            Subir ZIP
                        </button>
                    </div>
                    <p style="margin:10px 0 0;color:var(--clr-text-muted);font-size:0.82rem;">
                        Compatible con export clásico (_chat.txt + media) y export en HTML por partes.
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
                                $partsCount = is_array($chat->message_parts) ? count($chat->message_parts) : 0;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $chat->title }}</strong>
                                    @if (!empty($chat->original_zip_name))
                                        <div><small style="color:var(--clr-text-muted)">{{ $chat->original_zip_name }}</small></div>
                                    @endif
                                </td>
                                <td>{{ $partsCount }}</td>
                                <td>{{ optional($chat->imported_at)->format('d/m/Y H:i') }}</td>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <a href="{{ route('whatsapp-chats.admin.show', ['chat' => $chat->id]) }}" class="tm-btn tm-btn-outline tm-btn-sm">
                                            Ver
                                        </a>
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

