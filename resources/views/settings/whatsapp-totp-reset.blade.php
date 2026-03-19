@extends('settings.layout')

@section('settings_panel')
    <link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">

    @if (session('status'))
        <div class="inline-alert inline-alert-success settings-alert" role="alert">{{ session('status') }}</div>
    @endif

    <section class="settings-panel-block settings-totp-reset-block" aria-labelledby="settings-totp-reset-heading">
        <h2 class="settings-panel-heading" id="settings-totp-reset-heading">Restablecer Google Authenticator</h2>
        <p class="settings-panel-lead">
            Se borra el vínculo con tu app de códigos. La próxima vez que abras <strong>Chats WhatsApp</strong> verás un <strong>código QR nuevo</strong> para volver a registrar la cuenta.
            También deberás quitar la entrada antigua en Google Authenticator.
        </p>

        <form method="POST" action="{{ route('settings.whatsapp-totp-reset.post') }}" class="settings-totp-reset-form" id="settingsWaTotpResetForm" autocomplete="off">
            @csrf

            <div class="settings-totp-reset-field">
                <label class="settings-totp-reset-label" for="settingsWaTotpResetPwd">Contraseña de tu cuenta (administrador)</label>
                <input
                    type="password"
                    id="settingsWaTotpResetPwd"
                    name="current_password"
                    class="settings-totp-reset-input tm-input"
                    required
                    readonly
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="off"
                    spellcheck="false"
                    data-lpignore="true"
                    data-1p-ignore="true"
                    data-bwignore="true"
                    data-form-type="other"
                    inputmode="text"
                    aria-describedby="settingsWaTotpResetPwdHint"
                >
                <p class="settings-totp-reset-hint" id="settingsWaTotpResetPwdHint">
                    Por seguridad, escribe la contraseña manualmente: no está permitido pegar desde el portapapeles ni usar autocompletado del navegador en este campo.
                </p>
                @error('current_password')
                    <p class="settings-totp-reset-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="settings-totp-reset-actions">
                <button type="submit" class="tm-btn tm-btn-danger settings-totp-reset-submit">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                    Restablecer autenticador
                </button>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/modules/settings-whatsapp-totp-reset.js') }}?v={{ @filemtime(public_path('assets/js/modules/settings-whatsapp-totp-reset.js')) ?: time() }}"></script>
@endpush
