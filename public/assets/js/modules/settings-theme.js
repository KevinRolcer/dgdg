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
        if (VARIANTS.indexOf(variant) === -1) {
            variant = 'deep';
        }
        root.classList.add('theme-dark--' + variant);
    }

    function syncVariantUI(active) {
        if (VARIANTS.indexOf(active) === -1) {
            active = 'deep';
        }
        variantBtns.forEach(function (btn) {
            var variant = btn.getAttribute('data-variant');
            var isActive = variant === active;
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function applyTheme(isDark, variant) {
        var root = document.documentElement;
        var activeVariant = variant || localStorage.getItem(KEY_VARIANT) || 'deep';

        if (isDark) {
            root.classList.add('theme-dark');
            applyVariantClass(activeVariant);
            if (input) {
                input.checked = true;
            }
            if (variantsWrap) {
                variantsWrap.hidden = false;
            }
        } else {
            root.classList.remove('theme-dark', 'theme-dark--deep', 'theme-dark--soft', 'theme-dark--slate');
            if (input) {
                input.checked = false;
            }
            if (variantsWrap) {
                variantsWrap.hidden = true;
            }
        }

        syncVariantUI(activeVariant);
    }

    if (!input) {
        return;
    }

    var isDark = localStorage.getItem(KEY_THEME) === 'dark';
    var variant = localStorage.getItem(KEY_VARIANT) || 'deep';
    applyTheme(isDark, variant);

    input.addEventListener('change', function () {
        if (input.checked) {
            localStorage.setItem(KEY_THEME, 'dark');
            var currentVariant = localStorage.getItem(KEY_VARIANT) || 'deep';
            if (VARIANTS.indexOf(currentVariant) === -1) {
                currentVariant = 'deep';
            }
            localStorage.setItem(KEY_VARIANT, currentVariant);
            applyTheme(true, currentVariant);
            return;
        }

        localStorage.setItem(KEY_THEME, 'light');
        applyTheme(false);
    });

    variantBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!input.checked) {
                return;
            }
            var nextVariant = btn.getAttribute('data-variant');
            if (VARIANTS.indexOf(nextVariant) === -1) {
                return;
            }
            localStorage.setItem(KEY_VARIANT, nextVariant);
            applyVariantClass(nextVariant);
            syncVariantUI(nextVariant);
        });
    });
})();
