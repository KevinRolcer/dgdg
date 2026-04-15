(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('waFolderUploadRoot');
        if (!root || !root.getAttribute('data-upload-url') || !root.getAttribute('data-csrf')) return;

        var mainInput = document.getElementById('waFolderInput');
        if (!mainInput) return;

        var resumeBtns = Array.from(document.querySelectorAll('.js-wa-resume-upload-btn'));
        if (!resumeBtns.length) return;

        var activeButtons = new Map();

        function normalizeLower(value) {
            return String(value || '').toLowerCase().trim();
        }

        function showError(message) {
            var msg = String(message || 'No se pudo reanudar.');
            var swal = window.Swal;
            if (swal && typeof swal.fire === 'function') {
                swal.fire({
                    icon: 'error',
                    title: 'No se pudo reanudar',
                    text: msg
                });
                return;
            }
            alert(msg);
        }

        function fallbackSignature(input) {
            var hashA = 2166136261;
            var hashB = 16777619;
            for (var i = 0; i < input.length; i++) {
                var code = input.charCodeAt(i);
                hashA ^= code;
                hashA = Math.imul(hashA, 16777619);
                hashB ^= code;
                hashB = Math.imul(hashB, 1099511627 & 0xffffffff);
            }
            var left = (hashA >>> 0).toString(16).padStart(8, '0');
            var right = (hashB >>> 0).toString(16).padStart(8, '0');
            return (left + right + left + right + left + right + left + right).slice(0, 64);
        }

        function calculateFolderSignature(files) {
            var meta = files
                .map(function (file) {
                    return [file.webkitRelativePath || file.name, file.size || 0, file.lastModified || 0].join('|');
                })
                .sort()
                .join('\n');

            if (window.crypto && window.crypto.subtle && window.TextEncoder) {
                var bytes = new TextEncoder().encode(meta);
                return window.crypto.subtle.digest('SHA-256', bytes).then(function (digest) {
                    var hex = '';
                    var view = new Uint8Array(digest);
                    for (var i = 0; i < view.length; i++) {
                        hex += view[i].toString(16).padStart(2, '0');
                    }
                    return hex;
                }).catch(function () {
                    return fallbackSignature(meta);
                });
            }

            return Promise.resolve(fallbackSignature(meta));
        }

        function filterValidFiles(files) {
            return files.filter(function (f) {
                var rel = f.webkitRelativePath || '';
                if (!rel) return false;
                var lower = rel.toLowerCase();
                if (lower.indexOf('__macosx/') !== -1) return false;
                if (lower === '.ds_store' || lower.endsWith('/.ds_store')) return false;
                if (lower === 'thumbs.db' || lower.endsWith('/thumbs.db')) return false;
                return true;
            });
        }

        function detectRootName(files) {
            for (var i = 0; i < files.length; i++) {
                var rel = files[i].webkitRelativePath || '';
                if (!rel) continue;
                var rootName = rel.split('/')[0];
                if (rootName) return rootName;
            }
            return '';
        }

        function setButtonBusy(btn, busy) {
            if (!btn) return;
            btn.disabled = !!busy;
            btn.classList.toggle('wai-btn--disabled', !!busy);
        }

        function updateCardProgress(chatId, payload) {
            if (!chatId) return;
            var card = document.querySelector('.wai-chat-card[data-chat-id="' + String(chatId) + '"]');
            if (!card) return;

            var wrap = card.querySelector('.js-wa-resume-progress');
            var bar = card.querySelector('.js-wa-resume-progress-bar');
            var text = card.querySelector('.js-wa-resume-progress-text');
            if (!wrap || !bar || !text) return;

            var percent = Math.max(0, Math.min(100, parseInt(payload.percent || 0, 10)));
            bar.style.width = percent + '%';

            var track = wrap.querySelector('.wai-resume-progress-track');
            if (track) {
                track.setAttribute('aria-valuenow', String(percent));
            }
            if (payload.text) {
                text.textContent = String(payload.text);
            }
        }

        function releaseButton(chatId) {
            var btn = activeButtons.get(String(chatId));
            if (!btn) return;
            setButtonBusy(btn, false);
            activeButtons.delete(String(chatId));
        }

        function bindResumeButton(btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var expectedSignature = normalizeLower(btn.getAttribute('data-folder-signature'));
                var expectedRootName = normalizeLower(btn.getAttribute('data-folder-root-name'));
                var uploadedFiles = parseInt(btn.getAttribute('data-folder-uploaded-files') || '0', 10);
                var chatId = String(btn.getAttribute('data-chat-id') || '').trim();

                if (!chatId || !expectedSignature) {
                    showError('No se pudo leer la información de la importación a reanudar.');
                    return;
                }

                var picker = document.createElement('input');
                picker.type = 'file';
                picker.multiple = true;
                picker.webkitdirectory = true;
                picker.directory = true;

                picker.addEventListener('change', function () {
                    var files = Array.from(picker.files || []);
                    if (!files.length) return;

                    var list = filterValidFiles(files);
                    if (!list.length) {
                        showError('No quedaron archivos válidos en la carpeta seleccionada.');
                        return;
                    }

                    if (uploadedFiles > 0 && list.length < uploadedFiles) {
                        showError('La carpeta seleccionada tiene menos archivos que los ya registrados en la carga original.');
                        return;
                    }

                    var selectedRootName = normalizeLower(detectRootName(list));
                    if (expectedRootName && selectedRootName && selectedRootName !== expectedRootName) {
                        showError('Selecciona la misma carpeta base de la carga original para poder reanudar.');
                        return;
                    }

                    setButtonBusy(btn, true);
                    activeButtons.set(chatId, btn);

                    calculateFolderSignature(list).then(function (signature) {
                        if (normalizeLower(signature) !== expectedSignature) {
                            releaseButton(chatId);
                            showError('La carpeta no coincide con la original. Verifica nombres/archivos y selecciona la misma carpeta.');
                            return;
                        }

                        updateCardProgress(chatId, {
                            percent: 1,
                            text: 'Carpeta validada. Reanudando carga...'
                        });

                        window.__waResumeContext = { chatId: chatId };
                        window.__waResumeFiles = list;
                        mainInput.dispatchEvent(new Event('wa-resume-upload', { bubbles: true }));

                        setTimeout(function () {
                            delete window.__waResumeFiles;
                        }, 1000);
                    }).catch(function () {
                        releaseButton(chatId);
                        showError('No se pudo validar la carpeta seleccionada.');
                    });
                });

                picker.click();
            });
        }

        resumeBtns.forEach(bindResumeButton);

        document.addEventListener('wa-folder-resume-start', function (event) {
            var detail = event && event.detail ? event.detail : {};
            if (!detail.chatId) return;
            updateCardProgress(detail.chatId, {
                percent: 0,
                text: 'Preparando reanudación...'
            });
        });

        document.addEventListener('wa-folder-resume-progress', function (event) {
            var detail = event && event.detail ? event.detail : {};
            if (!detail.chatId) return;
            updateCardProgress(detail.chatId, {
                percent: detail.percent,
                text: detail.text
            });
        });

        document.addEventListener('wa-folder-resume-error', function (event) {
            var detail = event && event.detail ? event.detail : {};
            if (!detail.chatId) return;
            updateCardProgress(detail.chatId, {
                percent: 0,
                text: detail.message || 'Error al reanudar la carga.'
            });
            releaseButton(detail.chatId);
        });

        document.addEventListener('wa-folder-resume-complete', function (event) {
            var detail = event && event.detail ? event.detail : {};
            if (!detail.chatId) return;
            updateCardProgress(detail.chatId, {
                percent: 100,
                text: detail.message || 'Reanudación completada.'
            });
            releaseButton(detail.chatId);
        });
    });
})();
