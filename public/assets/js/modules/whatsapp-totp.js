/**
 * TOTP UI: 6 celdas + campo oculto `code` para el formulario.
 */
(function () {
    'use strict';

    function digitsOnly(s) {
        return (s || '').replace(/\D/g, '').slice(0, 6);
    }

    function init() {
        var form = document.getElementById('waTotpForm');
        if (!form) return;

        var hidden = document.getElementById('waTotpCodeHidden');
        var inputs = form.querySelectorAll('.wa-totp-digit');
        if (!hidden || !inputs.length) return;

        function gather() {
            var v = '';
            inputs.forEach(function (el) {
                v += el.value.replace(/\D/g, '');
            });
            return v.slice(0, 6);
        }

        function syncHidden() {
            hidden.value = gather();
        }

        function setFromString(str) {
            var d = digitsOnly(str);
            for (var i = 0; i < inputs.length; i++) {
                inputs[i].value = d[i] || '';
            }
            syncHidden();
            var focusIndex = Math.min(Math.max(d.length, 0), inputs.length - 1);
            if (d.length >= inputs.length) {
                focusIndex = inputs.length - 1;
            }
            inputs[focusIndex].focus();
        }

        inputs.forEach(function (el, idx) {
            el.addEventListener('input', function () {
                var raw = el.value.replace(/\D/g, '');
                if (raw.length > 1) {
                    setFromString(raw + gather().slice(idx + 1));
                    return;
                }
                el.value = raw;
                syncHidden();
                if (raw && idx < inputs.length - 1) {
                    inputs[idx + 1].focus();
                }
            });

            el.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !el.value && idx > 0) {
                    inputs[idx - 1].focus();
                    inputs[idx - 1].value = '';
                    syncHidden();
                    e.preventDefault();
                }
            });

            el.addEventListener('paste', function (e) {
                e.preventDefault();
                var text = (e.clipboardData || window.clipboardData).getData('text') || '';
                setFromString(text);
            });
        });

        form.addEventListener('submit', function (e) {
            syncHidden();
            if (hidden.value.length !== 6) {
                e.preventDefault();
                var firstEmpty = Array.prototype.findIndex.call(inputs, function (inp) {
                    return inp.value.length === 0;
                });
                if (firstEmpty >= 0) inputs[firstEmpty].focus();
                else inputs[0].focus();
            }
        });

        // Copiar secreto manual
        var copyBtn = document.getElementById('waTotpCopySecret');
        var secretEl = document.getElementById('waTotpSecretRaw');
        if (copyBtn && secretEl) {
            copyBtn.addEventListener('click', function () {
                var text = secretEl.value || '';
                function done() {
                    copyBtn.classList.add('is-done');
                    var label = copyBtn.querySelector('.wa-totp-copy-label');
                    if (label) label.textContent = 'Copiado';
                    window.setTimeout(function () {
                        copyBtn.classList.remove('is-done');
                        if (label) label.textContent = 'Copiar clave';
                    }, 2000);
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done).catch(function () {
                        secretEl.select();
                        document.execCommand('copy');
                        done();
                    });
                } else {
                    secretEl.select();
                    document.execCommand('copy');
                    done();
                }
            });
        }

        syncHidden();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
