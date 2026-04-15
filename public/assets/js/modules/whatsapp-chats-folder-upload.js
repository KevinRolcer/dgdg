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

    function waFolderRootName(files) {
        return waFolderDefaultLabelFromFiles(files);
    }

    function waFolderHex(buffer) {
        var view = new Uint8Array(buffer);
        var out = '';
        for (var i = 0; i < view.length; i++) {
            out += view[i].toString(16).padStart(2, '0');
        }
        return out;
    }

    /**
     * Token tipo UUID para lotes. crypto.randomUUID falta en HTTP (no seguro) y en navegadores viejos.
     */
    function waFolderRandomBatchToken() {
        var c = typeof window !== 'undefined' && window.crypto ? window.crypto : null;
        if (c && typeof c.randomUUID === 'function') {
            return c.randomUUID();
        }
        if (c && c.getRandomValues) {
            var bytes = new Uint8Array(16);
            c.getRandomValues(bytes);
            bytes[6] = (bytes[6] & 0x0f) | 0x40;
            bytes[8] = (bytes[8] & 0x3f) | 0x80;
            var hex = '';
            for (var i = 0; i < 16; i++) {
                hex += bytes[i].toString(16).padStart(2, '0');
            }
            return (
                hex.slice(0, 8) +
                '-' +
                hex.slice(8, 12) +
                '-' +
                hex.slice(12, 16) +
                '-' +
                hex.slice(16, 20) +
                '-' +
                hex.slice(20)
            );
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (ch) {
            var r = (Math.random() * 16) | 0;
            var v = ch === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    function waFolderFallbackSignature(input) {
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

    async function waFolderSignature(files) {
        var meta = files
            .map(function (file) {
                return [file.webkitRelativePath || file.name, file.size || 0, file.lastModified || 0].join('|');
            })
            .sort()
            .join('\n');

        if (window.crypto && window.crypto.subtle && window.TextEncoder) {
            var bytes = new TextEncoder().encode(meta);
            var digest = await window.crypto.subtle.digest('SHA-256', bytes);
            return waFolderHex(digest);
        }

        return waFolderFallbackSignature(meta);
    }

    function waFolderBuildBatches(files, maxFiles, targetBytes) {
        var batches = [];
        var current = [];
        var currentBytes = 0;

        files.forEach(function (file) {
            var size = file.size || 0;
            var shouldSplit = current.length > 0 && (current.length >= maxFiles || currentBytes + size > targetBytes);
            if (shouldSplit) {
                batches.push(current);
                current = [];
                currentBytes = 0;
            }
            current.push(file);
            currentBytes += size;
        });

        if (current.length > 0) {
            batches.push(current);
        }

        return batches;
    }

    function waFolderSleep(ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    }

    function waFolderResolveAdaptiveLimits(totalFiles, maxFiles, parallel) {
        var adaptiveMaxFiles = maxFiles;
        var adaptiveParallel = parallel;

        // Keep within backend validation max (configured server-side) and
        // reduce request explosion for large folders.
        if (totalFiles > 3000) {
            adaptiveMaxFiles = Math.min(maxFiles, 20);
            adaptiveParallel = Math.min(parallel, 3);
        } else if (totalFiles > 1200) {
            adaptiveMaxFiles = Math.min(maxFiles, 16);
            adaptiveParallel = Math.min(parallel, 4);
        }

        adaptiveMaxFiles = Math.max(1, adaptiveMaxFiles);
        adaptiveParallel = Math.max(1, adaptiveParallel);

        return {
            maxFiles: adaptiveMaxFiles,
            parallel: adaptiveParallel,
        };
    }

    async function waFolderParseResponse(res) {
        var text = await res.text();
        if (!text) return {};

        var lower = String(text).toLowerCase();
        var looksLikeHtml = lower.indexOf('<!doctype html') !== -1 || lower.indexOf('<html') !== -1;
        var isCloudflareTimeout = (res && res.status === 524) || lower.indexOf('error code 524') !== -1 || lower.indexOf('a timeout occurred') !== -1;
        if (looksLikeHtml) {
            if (isCloudflareTimeout) {
                return {
                    message: 'Tiempo de espera del servidor (524). La carpeta es pesada o el servidor está ocupado. Reintenta y, si persiste, sube en horas de menor carga o con lotes más pequeños.'
                };
            }
            return {
                message: 'El servidor devolvió una página de error inesperada. Reintenta en unos minutos.'
            };
        }
        try {
            return JSON.parse(text);
        } catch (_err) {
            // Evita mostrar payloads largos (HTML/stacktraces)
            var msg = String(text || '').trim();
            if (msg.length > 260) {
                msg = msg.slice(0, 260) + '…';
            }
            if (res && res.status === 524) {
                msg = 'Tiempo de espera del servidor (524). Reintenta en unos minutos.';
            }
            return { message: msg };
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('waFolderUploadRoot');
        if (!root) return;

        var uploadUrl = root.getAttribute('data-upload-url');
        var uploadChunkUrl = root.getAttribute('data-upload-chunk-url');
        var finalizeUrl = root.getAttribute('data-finalize-url');
        var csrf = root.getAttribute('data-csrf') || '';
        var requestMaxFiles = Math.max(1, parseInt(root.getAttribute('data-request-max-files') || '8', 10));
        var parallelRequests = Math.max(1, parseInt(root.getAttribute('data-parallel-requests') || '4', 10));
        var requestTargetBytes = Math.max(1024 * 1024, parseInt(root.getAttribute('data-request-target-bytes') || String(24 * 1024 * 1024), 10));
        if (!uploadUrl || !finalizeUrl || !csrf) return;

        var input = document.getElementById('waFolderInput');
        var btnPick = document.getElementById('waFolderPickBtn');
        var labelInput = document.getElementById('waFolderLabel');
        var progressWrap = document.getElementById('waFolderProgressWrap');
        var progressBar = document.getElementById('waFolderProgressBar');
        var progressMeta = document.getElementById('waFolderProgressMeta');
        var statusEl = document.getElementById('waFolderStatus');
        var warnEl = document.getElementById('waFolderTabWarn');

        var uploading = false;
        var batchToken = null;
        var waResumeContext = null;

        function emitResumeEvent(name, payload) {
            if (!waResumeContext || !waResumeContext.chatId) {
                return;
            }
            document.dispatchEvent(new CustomEvent('wa-folder-resume-' + name, {
                detail: Object.assign({
                    chatId: String(waResumeContext.chatId),
                }, payload || {}),
            }));
        }

        function clearResumeContext() {
            waResumeContext = null;
        }

        function setProgress(pct, text, extra) {
            var safePct = Math.min(100, Math.max(0, pct));
            if (progressBar) progressBar.style.width = safePct + '%';
            if (progressMeta) progressMeta.textContent = text || '';
            emitResumeEvent('progress', Object.assign({
                percent: safePct,
                text: text || '',
            }, extra || {}));
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

            startUploadSequence(list, null);
        });

        // Listen for resume upload events
        input.addEventListener('wa-resume-upload', function () {
            if (uploading) return;
            var files = window.__waResumeFiles;
            if (!files || files.length === 0) return;
            var resumePayload = (window.__waResumeContext && typeof window.__waResumeContext === 'object')
                ? window.__waResumeContext
                : null;

            var list = Array.from(files).filter(function (f) {
                return !waFolderSkipPath(f.webkitRelativePath || '');
            });
            if (list.length === 0) {
                if (statusEl) statusEl.textContent = 'No quedaron archivos válidos (se omitieron metadatos del sistema).';
                return;
            }

            if (labelInput && !labelInput.value.trim()) {
                labelInput.value = waFolderDefaultLabelFromFiles(list);
            }

            startUploadSequence(list, resumePayload);
        });

        async function startUploadSequence(list, resumePayload) {
            uploading = true;
            batchToken = waFolderRandomBatchToken();
            waResumeContext = resumePayload && resumePayload.chatId ? {
                chatId: String(resumePayload.chatId),
            } : null;
            if (progressWrap) progressWrap.hidden = false;
            if (warnEl) warnEl.hidden = false;
            if (statusEl) statusEl.textContent = '';
            emitResumeEvent('start', {
                selectedFiles: list.length,
            });
            setProgress(0, 'Preparando ' + list.length + ' archivos…', {
                selectedFiles: list.length,
            });

            if (btnPick) btnPick.disabled = true;
            input.disabled = true;
            if (labelInput) labelInput.disabled = true;
            var total = list.length;
            var rootName = waFolderRootName(list);
            var folderLabel = labelInput && labelInput.value.trim() ? labelInput.value.trim() : rootName;
            var uploadedCount = 0;
            var skippedCount = 0;
            var completedFiles = 0;
            var alreadyImported = false;

            function fail(msg) {
                var safeMsg = msg;
                if (safeMsg) {
                    var lowerMsg = String(safeMsg).toLowerCase();
                    if (lowerMsg.indexOf('<!doctype html') !== -1 || lowerMsg.indexOf('<html') !== -1) {
                        safeMsg = 'Tiempo de espera del servidor (524) o pÃ¡gina de error inesperada. Reintenta en unos minutos.';
                    }
                }
                uploading = false;
                if (btnPick) btnPick.disabled = false;
                input.disabled = false;
                if (labelInput) labelInput.disabled = false;
                if (statusEl) statusEl.textContent = safeMsg || 'Error en la subida.';
                setProgress(0, '');
                emitResumeEvent('error', {
                    message: safeMsg || 'Error en la subida.',
                });
                clearResumeContext();
            }

            async function finalizeBatch(folderSignature) {
                var maxFinalizeAttempts = 12;
                for (var attempt = 0; attempt < maxFinalizeAttempts; attempt++) {
                    setProgress(
                        96,
                        attempt
                            ? 'Servidor ocupado, reintentando finalización (' + (attempt + 1) + '/' + maxFinalizeAttempts + ')…'
                            : 'Finalizando en el servidor…'
                    );
                    var body = new FormData();
                    body.append('_token', csrf);
                    body.append('folder_signature', folderSignature);
                    body.append('folder_total_files', String(total));
                    if (folderLabel) {
                        body.append('label', folderLabel);
                    }

                    try {
                        var res = await fetch(finalizeUrl, {
                            method: 'POST',
                            body: body,
                            credentials: 'same-origin',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        var data = await waFolderParseResponse(res);

                        if (res.status === 429 && attempt < maxFinalizeAttempts - 1) {
                            var retrySec = parseInt(res.headers.get('Retry-After') || '5', 10);
                            if (isNaN(retrySec) || retrySec < 1) {
                                retrySec = 5;
                            }
                            await new Promise(function (resolve) {
                                setTimeout(resolve, Math.min(retrySec * 1000, 120000));
                            });
                            continue;
                        }

                        uploading = false;
                        if (btnPick) btnPick.disabled = false;
                        input.disabled = false;
                        if (labelInput) labelInput.disabled = false;
                        if (warnEl) warnEl.hidden = true;

                        if (!res.ok) {
                            fail((data && data.message) || 'No se pudo finalizar.');
                            return;
                        }
                        setProgress(100, alreadyImported ? 'La carpeta ya estaba importada.' : 'Listo.');
                        var doneMessage = alreadyImported
                            ? (data.message || 'La carpeta ya existia, se reutilizo la importacion previa.')
                            : ('Carga completada. ' + uploadedCount + ' archivos enviados, ' + skippedCount + ' omitidos por ya existir.');
                        if (statusEl) {
                            statusEl.textContent = alreadyImported
                                ? (data.message || 'La carpeta ya existia, se reutilizo la importacion previa.')
                                : doneMessage;
                        }
                        emitResumeEvent('complete', {
                            message: doneMessage,
                            alreadyImported: !!alreadyImported,
                            uploadedCount: uploadedCount,
                            skippedCount: skippedCount,
                            totalFiles: total,
                        });
                        clearResumeContext();
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        }
                        return;
                    } catch (_err) {
                        if (attempt < maxFinalizeAttempts - 1) {
                            await new Promise(function (resolve) {
                                setTimeout(resolve, 3000);
                            });
                            continue;
                        }
                        fail('Error al finalizar. Revisa tu conexión.');
                        return;
                    }
                }
            }

            try {
                setProgress(3, 'Calculando firma de la carpeta…');
                var folderSignature = await waFolderSignature(list);
                var limits = waFolderResolveAdaptiveLimits(total, requestMaxFiles, parallelRequests);
                var chunkBytes = Math.min(4 * 1024 * 1024, Math.max(1024 * 1024, Math.floor(requestTargetBytes / 2)));
                var largeFileThresholdBytes = Math.max(20 * 1024 * 1024, requestTargetBytes * 2);
                var normalFiles = [];
                var largeFiles = [];

                list.forEach(function (f) {
                    if (uploadChunkUrl && (f.size || 0) >= largeFileThresholdBytes) {
                        largeFiles.push(f);
                        return;
                    }
                    normalFiles.push(f);
                });

                var batches = waFolderBuildBatches(normalFiles, limits.maxFiles, requestTargetBytes);
                var fatalError = null;

                var queue = batches.map(function (batch) {
                    return { kind: 'batch', files: batch, attempts: 0 };
                });
                largeFiles.forEach(function (f) {
                    queue.push({ kind: 'chunk', file: f, attempts: 0 });
                });

                // Concurrencia efectiva: se puede reducir si el origen estÃ¡ lento (524) o saturado (throttle/locks).
                var activeParallel = Math.min(limits.parallel, queue.length || 1);
                var sequentialMode = false;
                var pauseUntil = 0;

                if (statusEl && total > 1200) {
                    statusEl.textContent = 'Carga optimizada para carpeta grande: ' + batches.length + ' lotes, ' + limits.maxFiles + ' archivos por lote.';
                }

                async function uploadBatch(batch) {
                    var fd = new FormData();
                    fd.append('_token', csrf);
                    fd.append('batch_token', batchToken);
                    fd.append('folder_signature', folderSignature);
                    fd.append('folder_total_files', String(total));
                    if (folderLabel) fd.append('label', folderLabel);
                    if (rootName) fd.append('root_name', rootName);

                    batch.forEach(function (file) {
                        fd.append('relative_paths[]', file.webkitRelativePath || file.name);
                        fd.append('file_sizes[]', String(file.size || 0));
                        fd.append('last_modifieds[]', String(file.lastModified || 0));
                        fd.append('files[]', file, file.name);
                    });

                    var res = await fetch(uploadUrl, {
                    method: 'POST',
                        body: fd,
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    var data = await waFolderParseResponse(res);
                    if (!res.ok) {
                        var e = new Error((data && data.message) || 'Error del servidor (' + res.status + ').');
                        e.status = res.status;
                        e.data = data;
                        throw e;
                    }
                    return data || {};
                }

                async function uploadChunkFile(file) {
                    if (!uploadChunkUrl) {
                        throw new Error('No hay endpoint de fragmentos.');
                    }

                    var rel = file.webkitRelativePath || file.name;
                    var totalChunks = Math.max(1, Math.ceil((file.size || 0) / chunkBytes));
                    var lastModified = file.lastModified || 0;

                    for (var chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                        var start = chunkIndex * chunkBytes;
                        var end = Math.min(file.size || 0, start + chunkBytes);
                        var blob = file.slice(start, end);

                        var fd = new FormData();
                        fd.append('_token', csrf);
                        fd.append('batch_token', batchToken);
                        fd.append('folder_signature', folderSignature);
                        fd.append('folder_total_files', String(total));
                        if (folderLabel) fd.append('label', folderLabel);
                        if (rootName) fd.append('root_name', rootName);
                        fd.append('relative_path', rel);
                        fd.append('file_size', String(file.size || 0));
                        fd.append('last_modified', String(lastModified));
                        fd.append('chunk_index', String(chunkIndex));
                        fd.append('chunk_total', String(totalChunks));
                        fd.append('chunk_offset', String(start));
                        fd.append('chunk', blob, file.name);

                        var partAttempt = 0;
                        while (true) {
                            partAttempt += 1;
                            try {
                                setProgress(
                                    Math.min(94, Math.max(5, Math.round((completedFiles / total) * 90) + 4)),
                                    'Subiendo archivo grande ' + (chunkIndex + 1) + '/' + totalChunks + ': ' + rel
                                );
                                var res = await fetch(uploadChunkUrl, {
                                    method: 'POST',
                                    body: fd,
                                    credentials: 'same-origin',
                                    headers: {
                                        Accept: 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest'
                                    }
                                });
                                var data = await waFolderParseResponse(res);
                                if (!res.ok) {
                                    var e = new Error((data && data.message) || 'Error del servidor (' + res.status + ').');
                                    e.status = res.status;
                                    e.data = data;
                                    throw e;
                                }

                                // Resume: el servidor detecta el archivo completo ya existente.
                                if ((data && data.skipped) || (data && data.already_imported)) {
                                    return data || {};
                                }
                                break;
                            } catch (err) {
                                var status = err && typeof err.status === 'number' ? err.status : 0;
                                if (!isRetryableStatus(status) || partAttempt >= 6) {
                                    throw err;
                                }
                                if (status === 524) {
                                    sequentialMode = true;
                                    pauseUntil = Date.now() + 6000;
                                }
                                await waFolderSleep(Math.min(60000, 1200 * Math.pow(2, Math.max(0, partAttempt - 1))));
                            }
                        }
                    }

                    return { uploaded: 1, skipped: 0 };
                }

                function isRetryableStatus(status) {
                    return status === 409 || status === 429 || status === 502 || status === 503 || status === 504 || status === 524;
                }

                function splitBatchFiles(files) {
                    if (!files || files.length < 2) return null;
                    var mid = Math.ceil(files.length / 2);
                    return [files.slice(0, mid), files.slice(mid)];
                }

                async function uploadBatchWithRetry(batchObj) {
                    var maxAttempts = 6;
                    batchObj.attempts = (batchObj.attempts || 0) + 1;

                    try {
                        if (batchObj.kind === 'chunk') {
                            return await uploadChunkFile(batchObj.file);
                        }
                        return await uploadBatch(batchObj.files || []);
                    } catch (err) {
                        var status = err && typeof err.status === 'number' ? err.status : 0;
                        if (!isRetryableStatus(status) || batchObj.attempts >= maxAttempts) {
                            throw err;
                        }

                        // 524: El request tardÃ³ demasiado. Reducir concurrencia y dividir lote si se puede.
                        if (status === 524) {
                            activeParallel = 1;
                            sequentialMode = true;
                            pauseUntil = Date.now() + 6000;
                            var parts = splitBatchFiles(batchObj.files || []);
                            if (parts) {
                                // Re-encolar (LIFO) para procesar el primer bloque antes.
                                queue.push({ kind: 'batch', files: parts[1], attempts: batchObj.attempts });
                                queue.push({ kind: 'batch', files: parts[0], attempts: batchObj.attempts });
                                return { __requeued: true };
                            }
                        }

                        var backoffMs = Math.min(60000, 1200 * Math.pow(2, Math.max(0, batchObj.attempts - 1)));
                        if (status === 409 && err && err.data && err.data.retryable) {
                            backoffMs = Math.min(30000, backoffMs);
                        }

                        if (statusEl) {
                            statusEl.textContent =
                                (status === 524
                                    ? 'Servidor lento (524). Reintentando con lotes mÃ¡s pequeÃ±osâ€¦'
                                    : 'Reintentando lote (' + batchObj.attempts + '/' + maxAttempts + ')â€¦');
                        }

                        await waFolderSleep(backoffMs);
                        return await uploadBatchWithRetry(batchObj);
                    }
                }

                async function worker(workerIndex) {
                    while (!fatalError && !alreadyImported) {
                        if (sequentialMode && workerIndex > 0) {
                            return;
                        }
                        var now = Date.now();
                        if (pauseUntil && now < pauseUntil) {
                            await waFolderSleep(Math.min(15000, pauseUntil - now));
                        }
                        var batchObj = queue.pop();
                        if (!batchObj) return;

                        var batch = batchObj.kind === 'chunk' ? [batchObj.file] : (batchObj.files || []);
                        var firstRel = batch[0] ? (batch[0].webkitRelativePath || batch[0].name) : 'lote';
                        setProgress(
                            Math.min(94, Math.max(5, Math.round((completedFiles / total) * 90) + 4)),
                            'Subiendoâ€¦ (' + completedFiles + '/' + total + ') ' + firstRel
                        );

                        try {
                            var data = await uploadBatchWithRetry(batchObj);
                            if (data && data.__requeued) {
                                continue;
                            }
                            if (data.already_imported) {
                                alreadyImported = true;
                                completedFiles = total;
                                if (statusEl) statusEl.textContent = data.message || 'La carpeta ya estaba importada.';
                                return;
                            }

                            uploadedCount += data.uploaded || 0;
                            skippedCount += data.skipped || 0;
                            completedFiles += batch.length;
                            setProgress(
                                Math.min(95, Math.round((completedFiles / total) * 95)),
                                'Transferidos ' + completedFiles + ' / ' + total + ' archivos. Nuevos: ' + uploadedCount + '. Omitidos: ' + skippedCount + '.'
                            );
                        } catch (err) {
                            fatalError = err;
                            return;
                        }
                    }
                }

                var workers = [];
                var workerCount = Math.min(activeParallel, queue.length || 1);
                for (var workerIndex = 0; workerIndex < workerCount; workerIndex++) {
                    workers.push(worker(workerIndex));
                }

                await Promise.all(workers);

                if (fatalError) {
                    var msg = String(fatalError && fatalError.message ? fatalError.message : '');
                    var lower = msg.toLowerCase();
                    if (lower.indexOf('<!doctype html') !== -1 || lower.indexOf('<html') !== -1 || lower.indexOf('error code 524') !== -1) {
                        msg = 'Tiempo de espera del servidor (524). Reintenta en unos minutos.';
                    }
                    fail(msg || 'Falló la red o el servidor. Vuelve a elegir la carpeta para reanudar.');
                    return;
                }

                await finalizeBatch(folderSignature);
            } catch (_err) {
                fail('No se pudo preparar la carpeta para subirla.');
            }
        }
    });
})();
