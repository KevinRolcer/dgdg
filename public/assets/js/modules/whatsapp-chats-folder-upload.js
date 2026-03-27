(function () {
    'use strict';

    function waFolderSkipPath(rel) {
        if (!rel) return true;
        var lower = rel.toLowerCase();
        if (lower.indexOf('__macosx/') !== -1) return true;
        if (lower === '.ds_store' || lower.endsWith('/.ds_store')) return true;
        if (lower === 'thumbs.db' || lower.endsWith('/thumbs.db')) return true;
        return false;
    }

    function waFolderDefaultLabelFromFiles(files) {
        for (var i = 0; i < files.length; i++) {
            var rel = files[i].webkitRelativePath || '';
            if (!rel || waFolderSkipPath(rel)) continue;
            var seg = rel.split('/')[0];
            if (seg) return seg;
        }
        return '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('waFolderUploadRoot');
        if (!root) return;

        var uploadUrl = root.getAttribute('data-upload-url');
        var finalizeUrl = root.getAttribute('data-finalize-url');
        var csrf = root.getAttribute('data-csrf') || '';
        if (!uploadUrl || !finalizeUrl || !csrf) return;

        var input = document.getElementById('waFolderInput');
        var btnPick = document.getElementById('waFolderPickBtn');
        var labelInput = document.getElementById('waFolderLabel');
        var progressWrap = document.getElementById('waFolderProgressWrap');
        var progressBar = root.querySelector('.wa-folder-progress-bar');
        var progressMeta = document.getElementById('waFolderProgressMeta');
        var statusEl = document.getElementById('waFolderStatus');
        var warnEl = document.getElementById('waFolderTabWarn');

        var uploading = false;
        var batchToken = null;

        function setProgress(pct, text) {
            if (progressBar) progressBar.style.width = Math.min(100, Math.max(0, pct)) + '%';
            if (progressMeta) progressMeta.textContent = text || '';
        }

        window.addEventListener('beforeunload', function (e) {
            if (!uploading) return;
            e.preventDefault();
            e.returnValue = '';
        });

        if (btnPick && input) {
            btnPick.addEventListener('click', function () {
                if (uploading) return;
                input.click();
            });
        }

        if (!input) return;

        input.addEventListener('change', function () {
            if (uploading) return;
            var files = Array.from(input.files || []);
            input.value = '';
            if (files.length === 0) return;

            var list = files.filter(function (f) {
                return !waFolderSkipPath(f.webkitRelativePath || '');
            });
            if (list.length === 0) {
                if (statusEl) statusEl.textContent = 'No quedaron archivos válidos (se omitieron metadatos del sistema).';
                return;
            }

            if (labelInput && !labelInput.value.trim()) {
                labelInput.value = waFolderDefaultLabelFromFiles(list);
            }

            startUploadSequence(list);
        });

        function startUploadSequence(list) {
            uploading = true;
            batchToken = crypto.randomUUID();
            if (progressWrap) progressWrap.hidden = false;
            if (warnEl) warnEl.hidden = false;
            if (statusEl) statusEl.textContent = '';
            setProgress(0, 'Preparando ' + list.length + ' archivos…');

            if (btnPick) btnPick.disabled = true;
            input.disabled = true;
            if (labelInput) labelInput.disabled = true;

            var i = 0;
            var total = list.length;

            function fail(msg) {
                uploading = false;
                if (btnPick) btnPick.disabled = false;
                input.disabled = false;
                if (labelInput) labelInput.disabled = false;
                if (statusEl) statusEl.textContent = msg || 'Error en la subida.';
                setProgress(0, '');
            }

            function uploadNext() {
                if (i >= total) {
                    finalizeBatch();
                    return;
                }
                var file = list[i];
                var rel = file.webkitRelativePath || file.name;
                var pct = Math.round(((i + 0.5) / total) * 95);
                setProgress(pct, 'Subiendo ' + (i + 1) + ' / ' + total + ': ' + rel);

                var fd = new FormData();
                fd.append('_token', csrf);
                fd.append('batch_token', batchToken);
                fd.append('relative_path', rel);
                fd.append('file', file, file.name);

                fetch(uploadUrl, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (res) {
                        return res.json().then(function (data) {
                            return { res: res, data: data };
                        });
                    })
                    .then(function (out) {
                        if (out.res.status === 422) {
                            var m =
                                (out.data && out.data.message) ||
                                (out.data && out.data.errors && JSON.stringify(out.data.errors)) ||
                                'Validación fallida';
                            fail(m);
                            return;
                        }
                        if (!out.res.ok) {
                            fail((out.data && out.data.message) || 'Error del servidor (' + out.res.status + ').');
                            return;
                        }
                        if (out.data && out.data.skipped) {
                            i++;
                            uploadNext();
                            return;
                        }
                        i++;
                        setProgress(Math.round((i / total) * 95), 'Listo ' + i + ' / ' + total);
                        uploadNext();
                    })
                    .catch(function () {
                        fail('Falló la red o el servidor. No cambies de pestaña e inténtalo de nuevo.');
                    });
            }

            function finalizeBatch() {
                setProgress(96, 'Finalizando en el servidor…');
                var body = new FormData();
                body.append('_token', csrf);
                body.append('batch_token', batchToken);
                if (labelInput && labelInput.value.trim()) {
                    body.append('label', labelInput.value.trim());
                }

                fetch(finalizeUrl, {
                    method: 'POST',
                    body: body,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (res) {
                        return res.json().then(function (data) {
                            return { res: res, data: data };
                        });
                    })
                    .then(function (out) {
                        uploading = false;
                        if (btnPick) btnPick.disabled = false;
                        input.disabled = false;
                        if (labelInput) labelInput.disabled = false;
                        if (warnEl) warnEl.hidden = true;

                        if (!out.res.ok) {
                            fail((out.data && out.data.message) || 'No se pudo finalizar.');
                            return;
                        }
                        setProgress(100, 'Listo.');
                        if (out.data.redirect) {
                            window.location.href = out.data.redirect;
                        }
                    })
                    .catch(function () {
                        fail('Error al finalizar. Revisa tu conexión.');
                    });
            }

            uploadNext();
        }
    });
})();
