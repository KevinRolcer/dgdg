/**
 * Campo de contraseña para restablecer TOTP: sin pegar, sin atajos de pegado, sin autofill agresivo.
 */
(function () {
    'use strict';

    function isPasteShortcut(e) {
        var key = (e.key || '').toLowerCase();
        if (e.ctrlKey || e.metaKey) {
            if (key === 'v') return true;
            if (key === 'insert') return true;
        }
        if (e.shiftKey && key === 'insert') return true;
        return false;
    }

    function init() {
        var form = document.getElementById('settingsWaTotpResetForm');
        var pwd = document.getElementById('settingsWaTotpResetPwd');
        if (!form || !pwd) return;

        // Quitar readonly solo al interactuar (reduce autofill al cargar)
        function unlockField() {
            pwd.removeAttribute('readonly');
        }
        pwd.addEventListener('focus', unlockField);
        pwd.addEventListener('pointerdown', unlockField);
        pwd.addEventListener('touchstart', unlockField, { passive: true });

        pwd.addEventListener('paste', function (e) {
            e.preventDefault();
        });
        pwd.addEventListener('drop', function (e) {
            e.preventDefault();
        });
        pwd.addEventListener('dragover', function (e) {
            e.preventDefault();
        });
        pwd.addEventListener('copy', function (e) {
            e.preventDefault();
        });
        pwd.addEventListener('cut', function (e) {
            e.preventDefault();
        });
        pwd.addEventListener('contextmenu', function (e) {
            e.preventDefault();
        });

        pwd.addEventListener('keydown', function (e) {
            if (isPasteShortcut(e)) {
                e.preventDefault();
            }
        });

        // Al enviar, vaciar el campo en el cliente (la sesión no guarda la contraseña en old por diseño)
        form.addEventListener('submit', function () {
            window.setTimeout(function () {
                pwd.value = '';
            }, 0);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
