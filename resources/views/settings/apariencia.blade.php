@extends('settings.layout')

@section('settings_panel')
    {{-- Una sola columna de contenido: sin caja dentro de caja; filas = separadores ligeros --}}
    <section class="settings-panel-block settings-appearance-panel" aria-labelledby="settings-appearance-heading">
        <h2 class="settings-panel-heading" id="settings-appearance-heading">Modo oscuro</h2>
        <p class="settings-panel-lead">Se aplica en todo el sistema y se guarda en este navegador.</p>

        <div class="settings-field-row">
            <div class="settings-row-text">
                <strong>Activar modo oscuro</strong>
                <span>Reduce el brillo en pantallas.</span>
            </div>
            <label class="settings-toggle">
                <input type="checkbox" id="settingsThemeDark" name="theme_dark" value="1" aria-describedby="settingsThemeHint">
                <span class="settings-toggle-track" aria-hidden="true"></span>
            </label>
        </div>

        <div class="settings-dark-variants" id="settingsDarkVariants" hidden>
            <p class="settings-dark-variants-label" id="settingsVariantsLabel">Gama del modo oscuro</p>
            <div class="settings-variant-row" role="radiogroup" aria-labelledby="settingsVariantsLabel">
                <button type="button" class="settings-variant-btn" data-variant="deep" aria-pressed="false">
                    <span class="settings-variant-swatch settings-variant-swatch--deep" aria-hidden="true"></span>
                    <span class="settings-variant-name">Profundo</span>
                </button>
                <button type="button" class="settings-variant-btn" data-variant="soft" aria-pressed="false">
                    <span class="settings-variant-swatch settings-variant-swatch--soft" aria-hidden="true"></span>
                    <span class="settings-variant-name">Gris tenue</span>
                </button>
                <button type="button" class="settings-variant-btn" data-variant="slate" aria-pressed="false">
                    <span class="settings-variant-swatch settings-variant-swatch--slate" aria-hidden="true"></span>
                    <span class="settings-variant-name">Pizarra</span>
                </button>
            </div>
        </div>

        <p class="settings-hint" id="settingsThemeHint">La preferencia se guarda solo en este dispositivo y navegador.</p>
    </section>
@endsection

@push('scripts')
@include('settings.partials.theme-script')
@endpush
