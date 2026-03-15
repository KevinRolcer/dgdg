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
