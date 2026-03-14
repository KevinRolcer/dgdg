@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/settings.css') }}?v={{ @filemtime(public_path('assets/css/modules/settings.css')) ?: time() }}">
@endpush

@section('content')
<article class="content-card settings-card">
    <header class="settings-card-head">
        <h2 class="settings-card-title">Apariencia</h2>
        <p class="settings-card-desc">El modo oscuro se aplica en todo el sistema y se recuerda aunque cierres sesión o entres desde otra pestaña en este mismo navegador.</p>
    </header>

    <div class="settings-row">
        <div class="settings-row-text">
            <strong>Modo oscuro</strong>
            <span>Reduce el brillo.</span>
        </div>
        <label class="settings-toggle">
            <input type="checkbox" id="settingsThemeDark" name="theme_dark" value="1" aria-describedby="settingsThemeHint">
            <span class="settings-toggle-track" aria-hidden="true"></span>
        </label>
    </div>

    <div class="settings-dark-variants" id="settingsDarkVariants" hidden>
        <p class="settings-dark-variants-label" id="settingsVariantsLabel">Gama del modo oscuro</p>
        <div class="settings-variant-row" role="radiogroup" aria-labelledby="settingsVariantsLabel">
            <button type="button" class="settings-variant-btn" data-variant="deep" title="Profundo — casi negro" aria-pressed="false">
                <span class="settings-variant-swatch settings-variant-swatch--deep" aria-hidden="true"></span>
                <span class="settings-variant-name">Profundo</span>
            </button>
            <button type="button" class="settings-variant-btn" data-variant="soft" title="Gris tenue — más suave" aria-pressed="false">
                <span class="settings-variant-swatch settings-variant-swatch--soft" aria-hidden="true"></span>
                <span class="settings-variant-name">Gris tenue</span>
            </button>
            <button type="button" class="settings-variant-btn" data-variant="slate" title="Pizarra — tono azulado" aria-pressed="false">
                <span class="settings-variant-swatch settings-variant-swatch--slate" aria-hidden="true"></span>
                <span class="settings-variant-name">Pizarra</span>
            </button>
        </div>
    </div>

    <p class="settings-hint" id="settingsThemeHint">La preferencia se guarda solo en este dispositivo y navegador.</p>
</article>
@endsection

@push('scripts')
<script>
(function () {
    var KEY_THEME = 'segob_theme';
    var KEY_VARIANT = 'segob_dark_variant';
    var VARIANTS = ['deep', 'soft', 'slate'];
    var input = document.getElementById('settingsThemeDark');
    var variantsWrap = document.getElementById('settingsDarkVariants');
    var variantBtns = Array.prototype.slice.call(document.querySelectorAll('.settings-variant-btn'));

    function applyVariantClass(variant) {
        var root = document.documentElement;
        root.classList.remove('theme-dark--deep', 'theme-dark--soft', 'theme-dark--slate');
        if (VARIANTS.indexOf(variant) === -1) variant = 'deep';
        root.classList.add('theme-dark--' + variant);
    }

    function applyTheme(isDark, variant) {
        var root = document.documentElement;
        if (isDark) {
            root.classList.add('theme-dark');
            applyVariantClass(variant || localStorage.getItem(KEY_VARIANT) || 'deep');
            if (input) input.checked = true;
            if (variantsWrap) variantsWrap.hidden = false;
        } else {
            root.classList.remove('theme-dark', 'theme-dark--deep', 'theme-dark--soft', 'theme-dark--slate');
            if (input) input.checked = false;
            if (variantsWrap) variantsWrap.hidden = true;
        }
        syncVariantUI(variant || localStorage.getItem(KEY_VARIANT) || 'deep');
    }

    function syncVariantUI(active) {
        if (VARIANTS.indexOf(active) === -1) active = 'deep';
        variantBtns.forEach(function (btn) {
            var v = btn.getAttribute('data-variant');
            var on = v === active;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    if (!input) return;

    var isDark = localStorage.getItem(KEY_THEME) === 'dark';
    var variant = localStorage.getItem(KEY_VARIANT) || 'deep';
    applyTheme(isDark, variant);

    input.addEventListener('change', function () {
        if (input.checked) {
            localStorage.setItem(KEY_THEME, 'dark');
            var v = localStorage.getItem(KEY_VARIANT) || 'deep';
            if (VARIANTS.indexOf(v) === -1) v = 'deep';
            localStorage.setItem(KEY_VARIANT, v);
            applyTheme(true, v);
        } else {
            localStorage.setItem(KEY_THEME, 'light');
            applyTheme(false);
        }
    });

    variantBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!input.checked) return;
            var v = btn.getAttribute('data-variant');
            if (VARIANTS.indexOf(v) === -1) return;
            localStorage.setItem(KEY_VARIANT, v);
            applyVariantClass(v);
            syncVariantUI(v);
        });
    });
})();
</script>
@endpush
