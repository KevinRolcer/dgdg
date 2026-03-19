@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/whatsapp-totp.css') }}?v={{ @filemtime(public_path('assets/css/modules/whatsapp-totp.css')) ?: time() }}">
@endpush

@php
    $oldCodeDigits = preg_replace('/\D/', '', (string) old('code', ''));
@endphp

@section('content')
<section class="wa-totp-page tm-page tm-shell app-density-compact">
    <div class="tm-shell-main">
        <div class="wa-totp-card">
            <header class="tm-shell-head wa-totp-card-head">
                <h1 class="tm-shell-title">{{ $pageTitle ?? 'Autenticación' }}</h1>
                <p class="tm-shell-desc">{{ $pageDescription ?? '' }}</p>
            </header>

            @if ($errors->any())
                <div class="inline-alert inline-alert-danger" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="waTotpForm" method="POST" action="{{ route('whatsapp-chats.admin.totp.post') }}" autocomplete="off">
                @csrf
                <input type="hidden" name="redirect" value="{{ old('redirect', $redirect ?? '') }}">
                <input type="hidden" name="code" id="waTotpCodeHidden" value="{{ old('code') }}" class="wa-totp-hidden-code" tabindex="-1" aria-hidden="true">

                @if (!empty($needsSetup) && !empty($qrSvg) && !empty($manualSecret))
                    @php
                        $secretFormatted = trim(chunk_split(strtoupper($manualSecret), 4, ' '));
                    @endphp
                    <div class="wa-totp-section">
                        <h2 class="wa-totp-section-title">
                            <i class="fa-solid fa-qrcode" aria-hidden="true"></i>
                            Escanear código QR
                        </h2>
                        <p class="wa-totp-section-desc">
                            Abre Google Authenticator y añade una cuenta escaneando el código. Si no puedes usar la cámara, introduce la clave manualmente.
                        </p>
                        <div class="wa-totp-qr-grid">
                            <div class="wa-totp-qr-frame" role="img" aria-label="Código QR para Google Authenticator">
                                {!! $qrSvg !!}
                            </div>
                            <div class="wa-totp-manual">
                                <p class="wa-totp-manual-hint">¿No puedes escanear? Añade la cuenta con esta clave:</p>
                                <label class="wa-totp-manual-label" for="waTotpSecretDisplay">Clave secreta (Base32)</label>
                                <div class="wa-totp-secret-row">
                                    <input type="text" id="waTotpSecretDisplay" class="wa-totp-secret-input" readonly value="{{ $secretFormatted }}" aria-readonly="true">
                                    <input type="hidden" id="waTotpSecretRaw" value="{{ $manualSecret }}">
                                    <button type="button" class="wa-totp-copy-btn" id="waTotpCopySecret">
                                        <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                        <span class="wa-totp-copy-label">Copiar clave</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p class="wa-totp-meta">
                            Cuenta en la app: <strong>{{ $totpIssuer }}</strong> — <strong>{{ $totpHolder }}</strong>
                        </p>
                    </div>
                @endif

                <div class="wa-totp-section">
                    <h2 class="wa-totp-section-title">
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        Código de verificación
                    </h2>
                    <p class="wa-totp-section-desc">
                        Introduce el código de 6 dígitos que muestra Google Authenticator.
                    </p>
                    <span id="waTotpDigitsLabel" class="wa-totp-sr-hint">Código de un solo uso de 6 dígitos</span>
                    <div class="wa-totp-digits" role="group" aria-labelledby="waTotpDigitsLabel">
                        @for ($i = 0; $i < 6; $i++)
                            <input
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="1"
                                class="wa-totp-digit"
                                aria-label="Dígito {{ $i + 1 }} de 6"
                                value="{{ strlen($oldCodeDigits) > $i ? $oldCodeDigits[$i] : '' }}"
                                @if($i === 0) autocomplete="one-time-code" @endif
                            >
                        @endfor
                    </div>
                </div>

                <div class="wa-totp-footer">
                    <a href="{{ route('home') }}" class="tm-btn">Cancelar</a>
                    <button type="submit" class="tm-btn tm-btn-primary wa-totp-submit">
                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                        Verificar
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/modules/whatsapp-totp.js') }}?v={{ @filemtime(public_path('assets/js/modules/whatsapp-totp.js')) ?: time() }}"></script>
@endpush
