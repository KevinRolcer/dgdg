(function () {
    'use strict';

    /**
     * Resume upload functionality for interrupted folder uploads
     * Allows users to re-select and continue uploading a partially completed chat folder
     */
    document.addEventListener('DOMContentLoaded', function () {
        var resumeBtns = document.querySelectorAll('.js-wa-resume-upload-btn');
        if (!resumeBtns.length) return;

        var root = document.getElementById('waFolderUploadRoot');
        if (!root) return;

        if (!root.getAttribute('data-upload-url') || !root.getAttribute('data-csrf')) return;

        resumeBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var expectedSignature = btn.getAttribute('data-folder-signature');

                if (!expectedSignature) {
                    alert('No se pudo obtener la información de la carpeta a reanudar.');
                    return;
                }

                // Create hidden input for folder picker
                var resumeInput = document.createElement('input');
                resumeInput.type = 'file';
                resumeInput.multiple = true;
                resumeInput.webkitdirectory = true;
                resumeInput.directory = true;

                resumeInput.addEventListener('change', function () {
                    var files = Array.from(resumeInput.files || []);

                    if (files.length === 0) {
                        return;
                    }

                    // Filter out system files
                    var list = files.filter(function (f) {
                        var rel = f.webkitRelativePath || '';
                        var lower = rel.toLowerCase();
                        if (lower.indexOf('__macosx/') !== -1) return false;
                        if (lower === '.ds_store' || lower.endsWith('/.ds_store')) return false;
                        if (lower === 'thumbs.db' || lower.endsWith('/thumbs.db')) return false;
                        return !!rel;
                    });

                    if (list.length === 0) {
                        alert('No quedaron archivos válidos en la carpeta.');
                        return;
                    }

                    // Calculate signature of selected folder to verify it's the same one
                    calculateFolderSignature(list, function (newSignature) {
                        if (newSignature !== expectedSignature) {
                            var proceed = confirm(
                                'La carpeta seleccionada no coincide con la original.\n\n' +
                                'Esto puede significar:\n' +
                                '• Es una carpeta diferente\n' +
                                '• Se agregaron o removieron archivos\n\n' +
                                '¿Deseas continuar y crear una importación nueva independiente?\n\n' +
                                'Recuerda: No se reutilizarán los archivos ya cargados.'
                            );
                            if (!proceed) {
                                return;
                            }
                        }

                        // Trigger upload flow (works for both resume and new independent upload)
                        triggerUploadFlow(list);
                    });
                });

                // Trigger folder picker
                resumeInput.click();
            });
        });

        function calculateFolderSignature(files, callback) {
            var meta = files
                .map(function (file) {
                    return [file.webkitRelativePath || file.name, file.size || 0, file.lastModified || 0].join('|');
                })
                .sort()
                .join('\n');

            if (window.crypto && window.crypto.subtle && window.TextEncoder) {
                var bytes = new TextEncoder().encode(meta);
                window.crypto.subtle.digest('SHA-256', bytes).then(function (digest) {
                    var hex = '';
                    var view = new Uint8Array(digest);
                    for (var i = 0; i < view.length; i++) {
                        hex += view[i].toString(16).padStart(2, '0');
                    }
                    callback(hex);
                }).catch(function () {
                    callback(fallbackSignature(meta));
                });
            } else {
                callback(fallbackSignature(meta));
            }
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

        function triggerUploadFlow(files) {
            // Scroll to upload area
            var uploadBox = document.getElementById('waFolderUploadRoot');
            if (uploadBox) {
                setTimeout(function () {
                    uploadBox.scrollIntoView({ behavior: 'smooth' });
                }, 100);
            }

            // Store files in a global variable for the main upload script to pick up
            window.__waResumeFiles = files;

            // Get the main folder input
            var mainInput = document.getElementById('waFolderInput');
            if (!mainInput) return;

            // Trigger a custom event that the main script can listen to
            var event = new Event('wa-resume-upload', { bubbles: true });
            mainInput.dispatchEvent(event);

            // Clean up after a delay
            setTimeout(function () {
                delete window.__waResumeFiles;
            }, 1000);
        }
    });
})();
