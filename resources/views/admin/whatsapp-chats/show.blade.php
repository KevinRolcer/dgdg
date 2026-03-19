@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/whatsapp-chats-show.css') }}?v={{ @filemtime(public_path('assets/css/modules/whatsapp-chats-show.css')) ?: time() }}">
@endpush

@php $hidePageHeader = true; @endphp

@section('content')
<section class="tm-page tm-shell app-density-compact">
    <div class="tm-shell-main">
        <header class="tm-shell-head">
            <h1 class="tm-shell-title">WhatsApp: {{ $chat->title }}</h1>
            <p class="tm-shell-desc">Vista previa por partes del ZIP importado.</p>
        </header>

        <article class="content-card tm-card tm-card-in-shell">
            <div class="wa-chat-shell">
                <div class="wa-parts" role="tablist" aria-label="Partes del chat">
                    @foreach ($messageParts as $idx => $relPath)
                        @php $isActive = ($idx === 0); @endphp
                        <button
                            type="button"
                            class="tm-btn tm-btn-outline tm-btn-sm wa-part-btn {{ $isActive ? 'wa-active-part' : '' }}"
                            data-part-index="{{ $idx }}"
                            data-part-url="{{ $messageUrls->values()->all()[$idx] ?? '' }}"
                        >
                            Parte {{ $idx + 1 }}
                        </button>
                    @endforeach

                    <form method="POST" action="{{ route('whatsapp-chats.admin.destroy', ['chat' => $chat->id]) }}" onsubmit="return confirm('¿Eliminar esta exportación de chat y sus archivos?');" style="margin-left:auto;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="tm-btn tm-btn-danger tm-btn-sm">Eliminar</button>
                    </form>
                    <a href="{{ route('whatsapp-chats.admin.index') }}" class="tm-btn tm-btn-outline tm-btn-sm">
                        Volver
                    </a>
                </div>

                @php
                    $messageUrlsArr = $messageUrls->values()->all();
                    $activeUrl = $messageUrlsArr[0] ?? null;
                    $isTxtMode = is_string($txtPartPath ?? null) && !empty($txtPartPath);
                @endphp
                @if ($isTxtMode)
                    <div class="wa-chat-txt-wrap" id="waChatTxtWrap">
                        @forelse(($txtMessages ?? collect()) as $msg)
                            <article class="wa-msg">
                                <div class="wa-msg-head">
                                    <div>
                                        <span class="wa-msg-num">#{{ $msg['index'] }}</span>
                                        <span class="wa-msg-author">{{ $msg['author'] }}</span>
                                    </div>
                                    <span class="wa-msg-time">{{ $msg['datetime_raw'] }}</span>
                                </div>
                                <div class="wa-msg-body">{{ $msg['text'] }}</div>
                                @if (!empty($msg['media_filename']))
                                    <div class="wa-media">
                                        @if (!empty($msg['media_url']) && ($msg['media_kind'] ?? null) === 'image')
                                            <a href="{{ $msg['media_url'] }}" target="_blank" rel="noopener noreferrer">
                                                <img
                                                    src="{{ $msg['media_url'] }}"
                                                    alt="{{ $msg['media_filename'] }}"
                                                    loading="lazy"
                                                    class="{{ !empty($msg['media_is_sticker']) ? 'wa-sticker' : '' }}"
                                                >
                                            </a>
                                        @elseif (!empty($msg['media_url']) && ($msg['media_kind'] ?? null) === 'video')
                                            <video controls preload="metadata">
                                                <source src="{{ $msg['media_url'] }}">
                                            </video>
                                        @elseif (!empty($msg['media_url']) && ($msg['media_kind'] ?? null) === 'audio')
                                            <audio controls preload="metadata">
                                                <source src="{{ $msg['media_url'] }}">
                                            </audio>
                                        @elseif (!empty($msg['media_url']))
                                            <a class="wa-media-file" href="{{ $msg['media_url'] }}" target="_blank" rel="noopener noreferrer">
                                                Abrir archivo: {{ $msg['media_filename'] }}
                                            </a>
                                        @else
                                            <span class="wa-media-file" style="color:var(--clr-text-muted);">
                                                Archivo referido en mensaje no encontrado en ZIP: {{ $msg['media_filename'] }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </article>
                        @empty
                            <p style="color:var(--clr-text-muted);">No se pudieron parsear mensajes desde el archivo TXT.</p>
                        @endforelse
                    </div>
                @else
                    <div class="wa-iframe-wrap">
                        <iframe
                            id="waChatIframe"
                            class="wa-iframe"
                            src="{{ $activeUrl }}"
                            loading="lazy"
                            title="Vista del chat importado"
                        ></iframe>
                    </div>
                @endif
            </div>
        </article>
    </div>
</section>

@push('scripts')
<script src="{{ asset('assets/js/modules/whatsapp-chats-show.js') }}?v={{ @filemtime(public_path('assets/js/modules/whatsapp-chats-show.js')) ?: time() }}"></script>
@endpush
@endsection

