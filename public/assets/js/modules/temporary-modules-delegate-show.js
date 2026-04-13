    document.addEventListener('DOMContentLoaded', function () {
        var TM_SHOW = typeof window.TM_DELEGATE_SHOW_BOOT !== 'undefined' && window.TM_DELEGATE_SHOW_BOOT !== null ? window.TM_DELEGATE_SHOW_BOOT : {};
        // --- Persistent Error Log Helpers ---
        const saveImportErrors = (moduleId, errors, singleUrl) => {
            if (!errors || errors.length === 0) {
                sessionStorage.removeItem(`tm_errors_${moduleId}`);
            } else {
                sessionStorage.setItem(`tm_errors_${moduleId}`, JSON.stringify({
                    errors: errors,
                    singleUrl: singleUrl,
                    timestamp: Date.now()
                }));
            }
            updateErrorIndicator(moduleId);
        };

        const updateErrorIndicator = (moduleId) => {
            const data = sessionStorage.getItem(`tm_errors_${moduleId}`);
            const btn = document.getElementById(`tmBtnSessionErrors-${moduleId}`);
            if (!btn) return;

            if (data) {
                const parsed = JSON.parse(data);
                const count = (parsed.errors || []).length;
                if (count > 0) {
                    btn.querySelector('.tm-error-count').textContent = count;
                    btn.classList.remove('tm-hidden');
                } else {
                    btn.classList.add('tm-hidden');
                }
            } else {
                btn.classList.add('tm-hidden');
            }
        };

        // Initialize indicators on load
        document.querySelectorAll('[data-session-errors-module]').forEach(btn => {
            updateErrorIndicator(btn.getAttribute('data-session-errors-module'));
        });

        const renderSessionErrors = (modal, moduleId) => {
            const data = sessionStorage.getItem(`tm_errors_${moduleId}`);
            if (!data) return;
            const parsed = JSON.parse(data);
            const errors = parsed.errors || [];
            const singleUrl = parsed.singleUrl;

            const errSection = modal.querySelector('.tm-excel-errors-section');
            const errList = modal.querySelector('.tm-excel-errors-list');
            if (!errSection || !errList) return;

            errSection.classList.remove('tm-hidden');
            errList.innerHTML = '';
            errors.forEach((err, idx) => {
                const card = document.createElement('div');
                card.className = 'tm-error-log-card';
                card.style = 'padding:12px; border:1px solid var(--clr-border); border-radius:10px; background:var(--clr-bg); font-size:0.85rem;';

                // Renderizar campos con fallo
                const failedFields = Array.isArray(err?.failed_fields) && err.failed_fields.length > 0 ? err.failed_fields : [];
                let failedFieldsHtml = '';
                if (failedFields.length > 0) {
                    failedFieldsHtml = '<div style="margin-top:8px;"><strong style="font-size:0.75rem;">Campos con fallo:</strong><ul style="margin:6px 0 0 16px; padding:0; font-size:0.8rem; color:var(--clr-text-light);">' +
                        failedFields.map((f) => {
                            const escapeHtml = (value) => String(value ?? '')
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;')
                                .replace(/"/g, '&quot;')
                                .replace(/'/g, '&#039;');
                            return `<li><strong>${escapeHtml(f.label || f.key || 'Campo')}</strong>: ${escapeHtml(f.reason || 'No válido')}${f.received ? ` <span style="color:var(--clr-primary); font-weight:600;">(ingresó: "${escapeHtml(f.received)}")</span>` : ''}</li>`;
                        }).join('') +
                    '</ul></div>';
                }

                let suggHtml = '';
                if (err.suggestions && err.suggestions.length > 0) {
                    const cardId = `tmErrRow_${moduleId}_${idx}`;
                    const isFieldSugg = typeof err.suggestions[0] === 'string';
                    const failedKey = isFieldSugg && err.failed_fields && err.failed_fields.length === 1 ? err.failed_fields[0].key : '';
                    suggHtml = '<div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:6px;">' +
                        '<span style="width:100%; font-weight:600; font-size:0.75rem; margin-bottom:4px; display:block;">¿Quisiste decir?</span>' +
                        err.suggestions.map(s => {
                            if (isFieldSugg) {
                                const val = String(s).replace(/'/g, "\\'");
                                return `<button type="button" class="tm-btn tm-btn-sm tm-btn-outline"
                                    onclick="retryImportRow(${idx}, null, '${val}', '${singleUrl}', ${moduleId}, '${cardId}', this, ${err.row || 0}, '${failedKey}')"
                                    style="font-size:0.7rem; padding:4px 8px;">
                                    ${s}
                                </button>`;
                            }
                            return `<button type="button" class="tm-btn tm-btn-sm tm-btn-outline"
                                onclick="retryImportRow(${idx}, ${s.microrregion_id}, '${s.municipio.replace(/'/g, "\\'")}', '${singleUrl}', ${moduleId}, '${cardId}', this, ${err.row || 0})"
                                style="font-size:0.7rem; padding:4px 8px;">
                                ${s.municipio}
                            </button>`;
                        }).join('') +
                    '</div>';
                }

                card.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <div style="color:var(--clr-primary); font-weight:700;">Fila ${err.row}</div>
                        <div style="font-size:0.75rem; color:var(--clr-text-light); text-align:right;">${err.message}</div>
                    </div>
                    ${failedFieldsHtml}
                    ${suggHtml}
                `;
                card.dataset.rowData = JSON.stringify(err.data);
                card.dataset.municipioKey = String(err.municipio_key || 'municipio');
                card.id = 'tmErrRow_' + idx;
                errList.appendChild(card);
            });
        };

        // Global check for session error clicks
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-session-errors-module]');
            if (!btn) return;
            const moduleId = btn.getAttribute('data-session-errors-module');
            const modal = document.querySelector('.tm-excel-import-modal'); // ONLY ONE MODAL in show view
            if (!modal) return;

            // Open modal and show errors
            if (typeof openModal === 'function') openModal(modal, btn);
            else {
                modal.classList.add('is-open');
                const overlay = document.getElementById('appOverlay');
                if (overlay) overlay.classList.add('is-active');
            }
            renderSessionErrors(modal, moduleId);

            // Scroll to errors
            const controlsSide = modal.querySelector('.tm-excel-controls-side');
            const errSection = modal.querySelector('.tm-excel-errors-section');
            if (controlsSide && errSection) {
                setTimeout(() => {
                    controlsSide.scrollTo({ top: errSection.offsetTop, behavior: 'smooth' });
                }, 200);
            }
        });
        // ------------------------------------

        const pasteButtons = Array.from(document.querySelectorAll('[data-paste-image-button]'));
        const pasteInputs = Array.from(document.querySelectorAll('[data-paste-upload-input]'));
        const pasteUploadAreas = Array.from(document.querySelectorAll('[data-paste-upload-wrap]'));
        let activePasteInput = null;

        const extensionFromMime = function (mimeType) {
            if (mimeType === 'image/jpeg') {
                return 'jpg';
            }

            if (mimeType === 'image/png') {
                return 'png';
            }

            if (mimeType === 'image/webp') {
                return 'webp';
            }

            if (mimeType === 'image/gif') {
                return 'gif';
            }

            return 'png';
        };

        const getStatusElement = function (input) {
            if (!input || !input.id) {
                return null;
            }

            return document.getElementById('paste_status_' + input.id);
        };

        const setStatus = function (statusElement, message, hasError) {
            if (!statusElement) {
                return;
            }

            statusElement.textContent = message;
            statusElement.classList.toggle('is-error', Boolean(hasError));
            statusElement.classList.toggle('is-success', !hasError && message !== '');
        };

        const setFileInInput = function (input, file, append = false) {
            if (!input || !file || typeof DataTransfer === 'undefined') {
                return false;
            }

            const maxFiles = parseInt(input.dataset.maxFiles || '1');
            const transfer = new DataTransfer();

            if (append) {
                // Keep existing files
                Array.from(input.files).forEach(f => {
                    if (transfer.items.length < maxFiles) transfer.items.add(f);
                });
            }

            if (transfer.items.length < maxFiles) {
                transfer.items.add(file);
            } else {
                // If we reached max, we might want to replace the last one or just ignore
                // For now, let's just not add if we are at max and append is true
                if (!append) {
                    transfer.items.add(file);
                } else {
                    return false;
                }
            }

            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        };

        const setPreview = function (input) {
            const wrap = input.closest('[data-paste-upload-wrap]');
            if (!wrap) return;

            const container = wrap.querySelector('[data-inline-image-preview-container]');
            if (!container) return;

            // Clear previous previews
            container.innerHTML = '';

            const files = Array.from(input.files || []);
            if (files.length === 0) return;

            files.forEach((file, index) => {
                if (!file.type.startsWith('image/')) return;

                const previewDiv = document.createElement('div');
                previewDiv.className = 'tm-inline-image-preview tm-image-preview';
                previewDiv.style.position = 'relative';

                const img = document.createElement('img');
                img.style.maxWidth = '120px';
                img.style.maxHeight = '120px';
                img.style.borderRadius = '8px';
                img.style.objectFit = 'cover';

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'tm-image-clear';
                removeBtn.innerHTML = '&times;';
                removeBtn.style.position = 'absolute';
                removeBtn.style.top = '-8px';
                removeBtn.style.right = '-8px';

                removeBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const dt = new DataTransfer();
                    Array.from(input.files).forEach((f, i) => {
                        if (i !== index) dt.items.add(f);
                    });
                    input.files = dt.files;
                    setPreview(input);
                });

                const reader = new FileReader();
                reader.onload = function (e) {
                    img.src = e.target.result;
                    previewDiv.appendChild(img);
                    previewDiv.appendChild(removeBtn);
                    container.appendChild(previewDiv);
                };
                reader.readAsDataURL(file);
            });
        };

        const getImageFileFromFileList = function (files) {
            const list = Array.from(files || []);
            for (let index = 0; index < list.length; index += 1) {
                const file = list[index];
                if (file && String(file.type || '').indexOf('image/') === 0) {
                    return file;
                }
            }

            return null;
        };

        const assignClipboardFile = function (input, blob, append = false) {
            if (!input || !blob) {
                return false;
            }

            const mimeType = blob.type || 'image/png';
            const extension = extensionFromMime(mimeType);
            const fileName = 'pegada_' + Date.now() + '.' + extension;
            const file = new File([blob], fileName, {
                type: mimeType,
                lastModified: Date.now(),
            });

            return setFileInInput(input, file, append);
        };

        const handlePasteEvent = function (event, input) {
            const statusElement = getStatusElement(input);
            const items = event.clipboardData ? Array.from(event.clipboardData.items || []) : [];

            const imageItem = items.find(function (item) {
                return item.kind === 'file' && String(item.type || '').indexOf('image/') === 0;
            });

            if (!imageItem) {
                setStatus(statusElement, 'No se detectó una imagen en el portapapeles.', true);
                return;
            }

            const maxFiles = parseInt(input.dataset.maxFiles || '1');
            if (input.files.length >= maxFiles) {
                setStatus(statusElement, `Límite de ${maxFiles} imágenes alcanzado.`, true);
                return;
            }

            const imageFile = imageItem.getAsFile();
            const wasAssigned = assignClipboardFile(input, imageFile, true);
            if (!wasAssigned) {
                setStatus(statusElement, 'No se pudo cargar la imagen pegada.', true);
                return;
            }

            setStatus(statusElement, 'Imagen agregada correctamente.', false);
            event.preventDefault();
        };

        const handlePasteFromButton = async function (input) {
            const statusElement = getStatusElement(input);
            activePasteInput = input;

            if (!window.isSecureContext || !navigator.clipboard || typeof navigator.clipboard.read !== 'function') {
                setStatus(statusElement, 'Portapapeles bloqueado.', true);
                return;
            }

            try {
                const clipboardItems = await navigator.clipboard.read();
                let assigned = false;

                for (const clipboardItem of clipboardItems) {
                    const imageType = clipboardItem.types.find(function (type) {
                        return String(type).indexOf('image/') === 0;
                    });

                    if (!imageType) {
                        continue;
                    }

                    const blob = await clipboardItem.getType(imageType);
                    assigned = assignClipboardFile(input, blob, true);
                    if (assigned) {
                        break;
                    }
                }

                if (!assigned) {
                    setStatus(statusElement, 'No se detecto una imagen en el portapapeles.', true);
                    return;
                }

                setStatus(statusElement, 'Imagen pegada correctamente.', false);
            } catch (error) {
                setStatus(statusElement, 'No se pudo leer el portapapeles.', true);
            }
        };

        pasteInputs.forEach(function (input) {
            input.addEventListener('focus', function () {
                activePasteInput = input;
            });

            input.addEventListener('click', function () {
                activePasteInput = input;
            });

            input.addEventListener('change', function () {
                setPreview(input);
            });

            const wrap = input.closest('[data-paste-upload-wrap]');
            if (!wrap) {
                return;
            }

            // removeButton ya no se usa aqui, se maneja individualmente en setPreview
        });

        pasteButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const targetInputId = button.getAttribute('data-target-input') || '';
                const input = targetInputId ? document.getElementById(targetInputId) : null;
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }

                handlePasteFromButton(input);
            });
        });

        pasteUploadAreas.forEach(function (area) {
            const input = area.querySelector('input[type="file"][accept="image/*"]');
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            area.addEventListener('click', function (event) {
                if (event.target.closest('[data-inline-image-remove]') || event.target.closest('.tm-inline-image-preview img')) {
                    return;
                }
                activePasteInput = input;
                input.click();
            });

            area.addEventListener('focusin', function () {
                activePasteInput = input;
            });

            area.addEventListener('dragenter', function (event) {
                event.preventDefault();
                area.classList.add('is-dragover');
            });

            area.addEventListener('dragover', function (event) {
                event.preventDefault();
                area.classList.add('is-dragover');
            });

            area.addEventListener('dragleave', function () {
                area.classList.remove('is-dragover');
            });

            area.addEventListener('drop', function (event) {
                event.preventDefault();
                area.classList.remove('is-dragover');

                const files = event.dataTransfer ? Array.from(event.dataTransfer.files) : [];
                const imageFiles = files.filter(f => f.type.startsWith('image/')).slice(0, parseInt(input.dataset.maxFiles || '1'));

                if (imageFiles.length === 0) {
                    setStatus(getStatusElement(input), 'Solo imágenes.', true);
                    return;
                }

                const dt = new DataTransfer();
                imageFiles.forEach(f => dt.items.add(f));
                input.files = dt.files;

                setStatus(getStatusElement(input), 'Imágenes cargadas.', false);
                setPreview(input);
            });
        });

        Array.from(document.querySelectorAll('[data-upload-trigger]')).forEach(function (button) {
            button.addEventListener('click', function () {
                const targetId = button.getAttribute('data-target-input') || '';
                const input = targetId ? document.getElementById(targetId) : null;
                if (input instanceof HTMLInputElement) {
                    activePasteInput = input;
                    input.click();
                }
            });
        });

        document.addEventListener('paste', function (event) {
            if (!(activePasteInput instanceof HTMLInputElement)) {
                return;
            }

            handlePasteEvent(event, activePasteInput);
        });

        const microrregionSelector = document.getElementById('tmMicrorregionSelector');
        const municipioSelects = Array.from(document.querySelectorAll('.tm-municipio-select'));
        const municipiosPorMicrorregion = TM_SHOW.municipiosPorMicrorregion || {};
        const renderMunicipios = function (microrregionId) {
            if (!microrregionId || municipioSelects.length === 0) {
                return;
            }

            const municipios = Array.isArray(municipiosPorMicrorregion[microrregionId])
                ? municipiosPorMicrorregion[microrregionId]
                : [];

            municipioSelects.forEach(function (select) {
                const selectedPrevio = select.value;
                select.innerHTML = '';
                select.appendChild(new Option('Selecciona un municipio', ''));

                municipios.forEach(function (municipio) {
                    const option = new Option(municipio, municipio, false, selectedPrevio === municipio);
                    select.appendChild(option);
                });
            });
        };

        if (microrregionSelector) {
            renderMunicipios(String(microrregionSelector.value || ''));
            microrregionSelector.addEventListener('change', function () {
                renderMunicipios(String(microrregionSelector.value || ''));
            });
        }

        const imagePreviewButtons = Array.from(document.querySelectorAll('[data-open-image-preview]'));
        const imageModal = document.getElementById('tmImagePreviewModal');
        const imageModalImg = document.getElementById('tmImagePreviewImg');
        const imageModalTitle = document.getElementById('tmImagePreviewTitle');
        let lastImageOpener = null;

        const closeImageModal = function () {
            if (!imageModal) {
                return;
            }

            const activeElement = document.activeElement;
            if (activeElement instanceof HTMLElement && imageModal.contains(activeElement)) {
                activeElement.blur();
            }

            imageModal.classList.remove('is-open');
            imageModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (imageModalImg) {
                imageModalImg.removeAttribute('src');
            }

            if (lastImageOpener instanceof HTMLElement) {
                lastImageOpener.focus();
            }
        };

        imagePreviewButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (!imageModal || !imageModalImg) {
                    return;
                }

                const src = button.getAttribute('data-image-src') || '';
                const title = button.getAttribute('data-image-title') || 'Vista previa';
                if (src === '') {
                    return;
                }

                lastImageOpener = button;

                imageModalImg.src = src;
                imageModalImg.alt = title;
                if (imageModalTitle) {
                    imageModalTitle.textContent = title;
                }

                imageModal.classList.add('is-open');
                imageModal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            });
        });

        Array.from(document.querySelectorAll('[data-close-image-preview]')).forEach(function (button) {
            button.addEventListener('click', closeImageModal);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && imageModal && imageModal.classList.contains('is-open')) {
                closeImageModal();
            }
            if (event.key === 'Escape' && excelModal && excelModal.classList.contains('is-open')) {
                closeExcelModal();
            }
        });

    /* Importar Excel - Premium Logic */
    const excelModal = document.getElementById('tmImportarExcelModal');
    const excelPreviewUrl = TM_SHOW.excelPreviewUrl;
    const excelImportUrl = TM_SHOW.excelImportUrl;
    const excelUpdateUrl = TM_SHOW.excelUpdateUrl;
    const excelImportSingleUrl = TM_SHOW.excelImportSingleUrl;
    let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || TM_SHOW.csrfToken;

    async function refreshCsrfToken() {
        try {
            const r = await fetch(TM_SHOW.csrfRefreshUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (r.redirected || r.status === 401) {
                window.location.href = TM_SHOW.loginUrl;
                return false;
            }
            if (!r.ok) throw new Error('refresh failed');
            const j = await r.json();
            if (j.token) {
                csrfToken = j.token;
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.setAttribute('content', j.token);
            }
            return true;
        } catch (_) { return false; }
    }

    async function csrfFetch(url, opts = {}) {
        const res = await fetch(url, opts);
        if (res.status === 419) {
            const ok = await refreshCsrfToken();
            if (!ok) return res;
            // Rebuild body with the new token
            if (opts.body instanceof FormData) {
                opts.body.set('_token', csrfToken);
            }
            if (opts.headers && typeof opts.headers === 'object' && !(opts.headers instanceof Headers)) {
                if ('X-CSRF-TOKEN' in opts.headers) opts.headers['X-CSRF-TOKEN'] = csrfToken;
            }
            return fetch(url, opts);
        }
        return res;
    }

    async function safeJsonParse(response) {
        if (response.status === 419) {
            throw new Error('Tu sesión ha expirado. Recarga la página para continuar.');
        }
        const ct = response.headers.get('content-type') || '';
        if (!response.ok || !ct.includes('application/json')) {
            const text = await response.text().catch(() => '');
            if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                throw new Error('El servidor no pudo procesar la solicitud (posible límite de memoria o tiempo). Intenta con un archivo más pequeño.');
            }
            throw new Error(text.substring(0, 200) || ('Error del servidor: HTTP ' + response.status));
        }
        return response.json();
    }

    let excelHeaders = [];
    let excelFields = [];
    let excelSuggested = {};
    let workbookData = null;
    let excelImportedCount = 0;

    if (typeof XLSX === 'undefined') {
        const script = document.createElement('script');
        script.src = "https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js";
        document.head.appendChild(script);
    }

    const openExcelModal = function () {
        if (!excelModal) return;
        excelImportedCount = 0;

        const errPreviewEl = document.getElementById('tmExcelPreviewErr');
        if (errPreviewEl) { errPreviewEl.textContent = ''; errPreviewEl.classList.add('tm-hidden'); }
        const errImportEl = document.getElementById('tmExcelImportErr');
        if (errImportEl) { errImportEl.textContent = ''; errImportEl.classList.add('tm-hidden'); }
        const okImportEl = document.getElementById('tmExcelImportOk');
        if (okImportEl) { okImportEl.textContent = ''; okImportEl.classList.add('tm-hidden'); }

        const errSection = document.getElementById('tmExcelErrorsSection');
        if (errSection) errSection.classList.add('tm-hidden');
        const errList = document.getElementById('tmExcelErrorsList');
        if (errList) errList.innerHTML = '';

        const fileInput = document.getElementById('tmExcelFile');
        if (fileInput) fileInput.value = '';
        const nameEl = document.getElementById('tmExcelFileName');
        if (nameEl) { nameEl.textContent = ''; nameEl.classList.add('tm-hidden'); }

        const hInput = document.getElementById('tmExcelHeaderRow');
        const dInput = document.getElementById('tmExcelDataStartRow');
        if (hInput) hInput.value = '';
        if (dInput) dInput.value = '';
        workbookData = null;
        currentWorkbook = null;
        currentSheetIdx = 0;
        const sheetTabsWrap = document.getElementById('tmExcelSheetTabsWrap');
        if (sheetTabsWrap) sheetTabsWrap.style.display = 'none';
        const sheetTabsEl = document.getElementById('tmExcelSheetTabs');
        if (sheetTabsEl) sheetTabsEl.innerHTML = '';

        const inner = document.getElementById('tmExcelSheetInner');
        if (inner) inner.innerHTML = '<div style="padding:60px; text-align:center; color:var(--clr-text-light);"><i class="fa-solid fa-file-excel" style="font-size:4rem; margin-bottom:16px; opacity:0.2;"></i><p style="font-weight:600;">Vista previa del documento</p><p style="font-size:0.85rem; opacity:0.7;">Carga un archivo Excel para comenzar a marcar las columnas.</p></div>';

        const zoomBar = document.getElementById('tmExcelZoomBar');
        if (zoomBar) zoomBar.style.display = 'none';

        const badgeH = document.getElementById('badgeHeaderRow');
        const badgeD = document.getElementById('badgeDataRow');
        if (badgeH) badgeH.style.display = 'none';
        if (badgeD) badgeD.style.display = 'none';

        const controlsSide = document.querySelector('.tm-excel-controls-side');
        if (controlsSide) controlsSide.scrollTo({ top: 0, behavior: 'auto' });

        excelModal.classList.add('is-open');
        excelModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        document.getElementById('tmExcelStep1')?.classList.remove('tm-hidden');
        document.getElementById('tmExcelStep2')?.classList.add('tm-hidden');
    };

    const closeExcelModal = function () {
        if (!excelModal) return;
        excelModal.classList.remove('is-open');
        excelModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';

        if (excelImportedCount > 0) {
            var total = excelImportedCount;
            excelImportedCount = 0;

            if (typeof window.segobToast === 'function') {
                window.segobToast('success', total + ' registro(s) agregado(s) desde Excel.');
            }

            // Refrescar tabla de registros recientes via AJAX
            var recordsEl = document.getElementById('tmRecentRecords');
            if (recordsEl) {
                fetch(location.href, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.ok ? r.text() : Promise.reject(r); })
                .then(function (html) {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');
                    var fresh = doc.getElementById('tmRecentRecords');
                    if (fresh) recordsEl.innerHTML = fresh.innerHTML;
                })
                .catch(function () {});
            }
        }
    };

    document.getElementById('tmBtnImportarExcel')?.addEventListener('click', openExcelModal);
    excelModal?.querySelectorAll('[data-tm-excel-close]').forEach(function (el) {
        el.addEventListener('click', closeExcelModal);
    });

    // Helper: Excel Column Letter
    const getColLetter = (n) => {
        let s = "";
        while (n >= 0) {
            s = String.fromCharCode((n % 26) + 65) + s;
            n = Math.floor(n / 26) - 1;
        }
        return s;
    };

    let currentZoom = 1;
    const updateZoomUI = () => {
        const inner = document.getElementById('tmExcelSheetInner');
        const val = document.getElementById('tmExcelZoomVal');
        if (inner) inner.style.transform = `scale(${currentZoom})`;
        if (val) val.textContent = Math.round(currentZoom * 100) + '%';
    };

    document.getElementById('tmExcelZoomIn')?.addEventListener('click', () => {
        currentZoom = Math.min(currentZoom + 0.1, 3);
        updateZoomUI();
    });
    document.getElementById('tmExcelZoomOut')?.addEventListener('click', () => {
        currentZoom = Math.max(currentZoom - 0.1, 0.2);
        updateZoomUI();
    });
    document.getElementById('tmExcelZoomReset')?.addEventListener('click', () => {
        currentZoom = 1;
        updateZoomUI();
    });

    (() => {
        const zoomBar = document.getElementById('tmExcelZoomBar');
        if (!zoomBar) return;

        let isDraggingZoom = false;
        let offsetX, offsetY;

        zoomBar.addEventListener('mousedown', (e) => {
            if (e.target.closest('button')) return;
            isDraggingZoom = true;

            const rect = zoomBar.getBoundingClientRect();
            offsetX = e.clientX - rect.left;
            offsetY = e.clientY - rect.top;

            zoomBar.style.bottom = 'auto';
            zoomBar.style.transition = 'none';
            zoomBar.style.cursor = 'grabbing';
        });

        window.addEventListener('mousemove', (e) => {
            if (!isDraggingZoom) return;

            const parentRect = zoomBar.parentElement.getBoundingClientRect();
            const barRect = zoomBar.getBoundingClientRect();

            let newLeft = e.clientX - parentRect.left - offsetX;
            let newTop = e.clientY - parentRect.top - offsetY;

            // Bounding
            newLeft = Math.max(10, Math.min(newLeft, parentRect.width - barRect.width - 10));
            newTop = Math.max(10, Math.min(newTop, parentRect.height - barRect.height - 10));

            zoomBar.style.left = newLeft + 'px';
            zoomBar.style.top = newTop + 'px';
        });

        window.addEventListener('mouseup', () => {
            if (isDraggingZoom) {
                isDraggingZoom = false;
                zoomBar.style.transition = 'transform 0.1s ease-out';
                zoomBar.style.cursor = 'move';
            }
        });

        // Touch support
        zoomBar.addEventListener('touchstart', (e) => {
            if (e.target.closest('button')) return;
            const touch = e.touches[0];
            isDraggingZoom = true;

            const rect = zoomBar.getBoundingClientRect();
            offsetX = touch.clientX - rect.left;
            offsetY = touch.clientY - rect.top;

            zoomBar.style.bottom = 'auto';
            zoomBar.style.transition = 'none';
        }, { passive: false });

        window.addEventListener('touchmove', (e) => {
            if (!isDraggingZoom) return;
            const touch = e.touches[0];
            const parentRect = zoomBar.parentElement.getBoundingClientRect();
            const barRect = zoomBar.getBoundingClientRect();

            let newLeft = touch.clientX - parentRect.left - offsetX;
            let newTop = touch.clientY - parentRect.top - offsetY;

            newLeft = Math.max(10, Math.min(newLeft, parentRect.width - barRect.width - 10));
            newTop = Math.max(10, Math.min(newTop, parentRect.height - barRect.height - 10));

            zoomBar.style.left = newLeft + 'px';
            zoomBar.style.top = newTop + 'px';
        }, { passive: false });

        window.addEventListener('touchend', () => {
            if (isDraggingZoom) {
                isDraggingZoom = false;
                zoomBar.style.transition = 'transform 0.1s ease-out';
            }
        });
    })();

    document.getElementById('tmExcelPreviewTableWrap')?.addEventListener('wheel', (e) => {
        if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            if (e.deltaY < 0) currentZoom = Math.min(currentZoom + 0.05, 3);
            else currentZoom = Math.max(currentZoom - 0.05, 0.2);
            updateZoomUI();
        }
    }, { passive: false });

    const applyExcelPreviewThumbnails = function(thumbs) {
        if (!thumbs || !thumbs.length) return;
        const table = document.getElementById('tmExcelPreviewTable');
        if (!table) return;
        thumbs.forEach(function(t) {
            const r = parseInt(t.row, 10);
            const c = parseInt(t.col, 10);
            if (!Number.isFinite(r) || !Number.isFinite(c)) return;
            const tr = table.querySelector('tbody tr[data-row-index="' + r + '"]');
            if (!tr) return;
            const td = tr.querySelector('td:nth-child(' + (c + 2) + ')');
            if (!td || !t.data_url || String(t.data_url).indexOf('data:image/') !== 0) return;
            const img = document.createElement('img');
            img.src = t.data_url;
            img.alt = '';
            img.loading = 'lazy';
            img.style.cssText = 'max-width:88px;max-height:88px;vertical-align:middle;border-radius:6px;object-fit:contain;display:block;';
            td.replaceChildren(img);
        });
    };

    const fetchExcelPreviewThumbnails = function(file, useAutoDetect) {
        if (!file || !excelPreviewUrl) return;
        const fd = new FormData();
        fd.append('archivo_excel', file);
        fd.append('header_row', document.getElementById('tmExcelHeaderRow')?.value || '1');
        fd.append('sheet_index', currentSheetIdx);
        fd.append('auto_detect', useAutoDetect ? '1' : '0');
        fd.append('_token', csrfToken);
        csrfFetch(excelPreviewUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
            .then(function(r) { return safeJsonParse(r); })
            .then(function(j) {
                if (!j.success) return;
                if (j.preview_rows && j.preview_rows.length) {
                    workbookData = j.preview_rows;
                    renderExcelPreview(workbookData);
                    var hInput = document.getElementById('tmExcelHeaderRow');
                    var dInput = document.getElementById('tmExcelDataStartRow');
                    if (j.header_row && hInput) { hInput.value = j.header_row; }
                    if (j.data_start_row && dInput) { dInput.value = j.data_start_row; }
                    updateRowHighlights();
                }
                if (j.preview_thumbnails) applyExcelPreviewThumbnails(j.preview_thumbnails);
            })
            .catch(function() { /* silencioso: la tabla de texto ya se ve con SheetJS */ });
    };

    const renderExcelPreview = function(data) {
        const container = document.getElementById('tmExcelPreviewTableWrap');
        if (!container) return;
        if (!data || data.length === 0) {
            const inner = document.getElementById('tmExcelSheetInner');
            if (inner) inner.innerHTML = '<div style="padding:60px; text-align:center; color:var(--clr-text-light);"><i class="fa-solid fa-table" style="font-size:2.5rem; margin-bottom:12px; opacity:0.25;"></i><p>Esta hoja no contiene datos.</p></div>';
            return;
        }

        let maxCols = 0;
        data.slice(0, 50).forEach(row => { if (row && row.length > maxCols) maxCols = row.length; });

        let html = '<table class="tm-excel-preview-table" id="tmExcelPreviewTable"><thead><tr><th class="row-num"></th>';
        for (let i = 0; i < maxCols; i++) {
            html += '<th>' + getColLetter(i) + '</th>';
        }
        html += '</tr></thead><tbody>';

        data.slice(0, 100).forEach((row, rowIndex) => {
            const displayRowIndex = currentSheetStartRow + rowIndex;
            html += '<tr data-row-index="' + displayRowIndex + '"><td class="row-num">' + displayRowIndex + '</td>';
            for (let i = 0; i < maxCols; i++) {
                const cell = row[i] !== undefined && row[i] !== null ? row[i] : '';
                html += '<td>' + String(cell).substring(0, 120) + '</td>';
            }
            html += '</tr>';
        });

        html += '</tbody></table>';
        const inner = document.getElementById('tmExcelSheetInner');
        if (inner) {
            inner.innerHTML = html;
            document.getElementById('tmExcelZoomBar').style.display = 'flex';
            currentZoom = 1;
            updateZoomUI();
        }
        bindPreviewEvents();
        updateRowHighlights();
    };

    let isDraggingRow = null;

    const bindPreviewEvents = function() {
        const table = document.getElementById('tmExcelPreviewTable');
        const tbody = table?.querySelector('tbody');
        if (!tbody) return;

        const handleRowInteraction = (row, isDrag = false) => {
            const idx = parseInt(row.getAttribute('data-row-index'));
            const hInput = document.getElementById('tmExcelHeaderRow');
            const dInput = document.getElementById('tmExcelDataStartRow');
            const currH = parseInt(hInput.value);
            const currD = parseInt(dInput.value);

            if (!isDrag) {
                // Click logic (toggle/set)
                if (currH === idx) {
                    hInput.value = '';
                    if (currD === idx + 1) dInput.value = '';
                } else if (!hInput.value || idx < currH) {
                    hInput.value = idx;
                    dInput.value = idx + 1;
                } else {
                    dInput.value = idx;
                }
            } else {
                // Drag logic (direct set)
                if (isDraggingRow === 'header') {
                    hInput.value = idx;
                } else if (isDraggingRow === 'data') {
                    dInput.value = idx;
                }
            }
            updateRowHighlights();
        };

        tbody.querySelectorAll('tr').forEach(row => {
            row.addEventListener('mousedown', function(e) {
                const idx = parseInt(this.getAttribute('data-row-index'));
                const hIdx = parseInt(document.getElementById('tmExcelHeaderRow').value);
                const dIdx = parseInt(document.getElementById('tmExcelDataStartRow').value);

                if (idx === hIdx) isDraggingRow = 'header';
                else if (idx === dIdx) isDraggingRow = 'data';
                else isDraggingRow = null;

                if (isDraggingRow) {
                    e.preventDefault();
                    this.style.cursor = 'grabbing';
                }
            });

            row.addEventListener('mouseover', function() {
                if (isDraggingRow) {
                    handleRowInteraction(this, true);
                }
            });

            row.addEventListener('click', function() {
                if (!isDraggingRow) {
                    handleRowInteraction(this, false);
                }
            });

            // Touch support
            row.addEventListener('touchstart', function(e) {
                const idx = parseInt(this.getAttribute('data-row-index'));
                const hIdx = parseInt(document.getElementById('tmExcelHeaderRow').value);
                const dIdx = parseInt(document.getElementById('tmExcelDataStartRow').value);

                if (idx === hIdx) isDraggingRow = 'header';
                else if (idx === dIdx) isDraggingRow = 'data';

                if (isDraggingRow) {
                    // Prevent scroll while dragging highlight
                    e.preventDefault();
                }
            }, { passive: false });
        });

        const stopDragging = () => {
            isDraggingRow = null;
            tbody.querySelectorAll('tr').forEach(r => r.style.cursor = 'pointer');
        };

        window.addEventListener('mouseup', stopDragging);

        table.addEventListener('touchmove', function(e) {
            if (isDraggingRow) {
                e.preventDefault();
                const touch = e.touches[0];
                const target = document.elementFromPoint(touch.clientX, touch.clientY);
                const row = target?.closest('tr');
                if (row && row.getAttribute('data-row-index')) {
                    handleRowInteraction(row, true);
                }
            }
        }, { passive: false });

        table.addEventListener('touchend', stopDragging);
    };

    const updateRowHighlights = function() {
        const hIdx = parseInt(document.getElementById('tmExcelHeaderRow').value);
        const dIdx = parseInt(document.getElementById('tmExcelDataStartRow').value);

        document.querySelectorAll('#tmExcelPreviewTable tbody tr').forEach(row => {
            const idx = parseInt(row.getAttribute('data-row-index'));
            row.classList.remove('is-header-row', 'is-data-row');
            if (idx === hIdx) row.classList.add('is-header-row');
            if (idx === dIdx) row.classList.add('is-data-row');
        });

        const badgeH = document.getElementById('badgeHeaderRow');
        const badgeD = document.getElementById('badgeDataRow');
        if (badgeH) {
            if (hIdx) { badgeH.textContent = 'Encabezado: Fila ' + hIdx; badgeH.style.display = 'inline-flex'; }
            else { badgeH.style.display = 'none'; }
        }
        if (badgeD) {
            if (dIdx) { badgeD.textContent = 'Datos: Fila ' + dIdx; badgeD.style.display = 'inline-flex'; }
            else { badgeD.style.display = 'none'; }
        }
    };

    const handleExcelFile = (file) => {
        if (!file) return;
        const isPdf = file.name.toLowerCase().endsWith('.pdf');
        const nameEl = document.getElementById('tmExcelFileName');
        if (nameEl) {
            nameEl.textContent = 'Archivo: ' + file.name;
            nameEl.classList.remove('tm-hidden');
        }

        if (isPdf) {
            // PDF: no se puede leer con SheetJS, usar previsualización del servidor
            currentWorkbook = null;
            currentSheetIdx = 0;
            currentSheetStartRow = 1;
            const sheetTabsWrap = document.getElementById('tmExcelSheetTabsWrap');
            if (sheetTabsWrap) sheetTabsWrap.style.display = 'none';
            const inner = document.querySelector('.tm-excel-sheet-inner');
            if (inner) inner.innerHTML = '<div style="padding:60px; text-align:center; color:var(--clr-text-light);"><i class="fa-solid fa-file-pdf" style="font-size:2.5rem; margin-bottom:12px; opacity:0.4; color:#e74c3c;"></i><p>Archivo PDF cargado. Las columnas se detectarán automáticamente del servidor.</p></div>';
            fetchExcelPreviewThumbnails(file, true);
            const autoBtn = document.getElementById('tmExcelAutoDetect');
            if (autoBtn) autoBtn.style.display = 'inline-flex';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, {type: 'array'});
                currentWorkbook = workbook;
                currentSheetIdx = 0;
                switchToSheet(0);
                renderSheetTabs(workbook.SheetNames, 0);
                fetchExcelPreviewThumbnails(file, true);
                document.getElementById('tmExcelAutoDetect').style.display = 'inline-flex';
            } catch (err) {
                const errEl = document.getElementById('tmExcelPreviewErr');
                if (errEl) { errEl.textContent = 'Error al procesar el Excel.'; errEl.classList.remove('tm-hidden'); }
            }
        };
        reader.readAsArrayBuffer(file);
    };

    let currentWorkbook = null;
    let currentSheetIdx = 0;
    let currentSheetStartRow = 1;

    const switchToSheet = function(idx) {
        if (!currentWorkbook) return;
        const names = currentWorkbook.SheetNames;
        if (idx < 0 || idx >= names.length) return;
        currentSheetIdx = idx;
        const worksheet = currentWorkbook.Sheets[names[idx]];
        let startRow = 1;
        if (worksheet && worksheet['!ref']) {
            try {
                const range = XLSX.utils.decode_range(String(worksheet['!ref']));
                if (range && range.s && Number.isFinite(range.s.r)) {
                    startRow = range.s.r + 1;
                }
            } catch (_) {}
        }
        currentSheetStartRow = Math.max(1, startRow);
        workbookData = XLSX.utils.sheet_to_json(worksheet, { header: 1, blankrows: true, defval: '' });
        renderExcelPreview(workbookData);
    };

    const renderSheetTabs = function(sheetNames, activeIdx) {
        const wrap = document.getElementById('tmExcelSheetTabsWrap');
        const container = document.getElementById('tmExcelSheetTabs');
        if (!container || !wrap) return;
        if (!sheetNames || sheetNames.length <= 1) {
            wrap.style.display = 'none';
            container.innerHTML = '';
            return;
        }
        let html = '';
        sheetNames.forEach(function(name, idx) {
            const cls = 'tm-excel-sheet-tab-btn' + (idx === activeIdx ? ' tm-excel-sheet-tab-btn--active' : '');
            html += '<button type="button" class="' + cls + '" data-sheet-idx="' + idx + '">'
                + String(name).replace(/</g, '&lt;') + '</button>';
        });
        container.innerHTML = html;
        wrap.style.display = 'flex';
        updateSheetArrows();
    };

    const updateSheetArrows = function() {
        const tabs = document.getElementById('tmExcelSheetTabs');
        const leftArr = document.getElementById('tmSheetArrowLeft');
        const rightArr = document.getElementById('tmSheetArrowRight');
        if (!tabs || !leftArr || !rightArr) return;
        leftArr.disabled = tabs.scrollLeft <= 0;
        rightArr.disabled = tabs.scrollLeft + tabs.clientWidth >= tabs.scrollWidth - 1;
    };

    document.getElementById('tmSheetArrowLeft')?.addEventListener('click', function() {
        const tabs = document.getElementById('tmExcelSheetTabs');
        if (tabs) { tabs.scrollBy({ left: -200, behavior: 'smooth' }); setTimeout(updateSheetArrows, 350); }
    });
    document.getElementById('tmSheetArrowRight')?.addEventListener('click', function() {
        const tabs = document.getElementById('tmExcelSheetTabs');
        if (tabs) { tabs.scrollBy({ left: 200, behavior: 'smooth' }); setTimeout(updateSheetArrows, 350); }
    });
    document.getElementById('tmExcelSheetTabs')?.addEventListener('scroll', updateSheetArrows);

    document.getElementById('tmExcelSheetTabs')?.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-sheet-idx]');
        if (!btn) return;
        const idx = parseInt(btn.dataset.sheetIdx, 10);
        if (idx === currentSheetIdx) return;
        switchToSheet(idx);
        renderSheetTabs(currentWorkbook.SheetNames, idx);
        // Reset header/data row for new sheet
        const hInput = document.getElementById('tmExcelHeaderRow');
        const dInput = document.getElementById('tmExcelDataStartRow');
        if (hInput) hInput.value = '1';
        if (dInput) dInput.value = '2';
        updateRowHighlights();
        // Hide step 2 when switching sheets
        document.getElementById('tmExcelStep2')?.classList.add('tm-hidden');
    });

    const dropzone = document.getElementById('tmExcelDropzone');
    const fileInput = document.getElementById('tmExcelFile');

    dropzone?.addEventListener('click', () => fileInput?.click());
    fileInput?.addEventListener('change', (e) => handleExcelFile(e.target.files[0]));

    ['dragover', 'dragleave', 'drop'].forEach(evt => {
        dropzone?.addEventListener(evt, (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (evt === 'dragover') dropzone.classList.add('is-dragover');
            else dropzone.classList.remove('is-dragover');
            if (evt === 'drop') handleExcelFile(e.dataTransfer.files[0]);
        });
    });

    const hInput = document.getElementById('tmExcelHeaderRow');
    const dInput = document.getElementById('tmExcelDataStartRow');
    const searchAllCheck = document.getElementById('tmExcelSearchAll');
    const mrLabel = document.getElementById('tmExcelMicrorregionLabel');
    const mrSelect = document.getElementById('tmExcelMicrorregionId');
    const municipioLabel = document.getElementById('tmExcelMunicipioLabel');
    const municipioSelectImport = document.getElementById('tmExcelSelectedMunicipio');
    const autoMunicipioWrap = document.getElementById('tmExcelAutoMunicipioWrap');
    const autoMunicipioCheck = document.getElementById('tmExcelAutoIdentifyMunicipio');
    const autoMunicipioCheckUpdate = document.getElementById('tmExcelAutoIdentifyMunicipioUpdate');
    const modeTabs = Array.from(document.querySelectorAll('.tm-excel-mode-tab'));
    const modePanels = Array.from(document.querySelectorAll('.tm-excel-mode-panel'));
    let activeExcelMode = 'import';

    const getActiveMapSelector = () => activeExcelMode === 'update'
        ? '.tm-excel-map-select-update'
        : '.tm-excel-map-select-import';

    const getActiveAutoMunicipioCheck = () => activeExcelMode === 'update'
        ? autoMunicipioCheckUpdate
        : autoMunicipioCheck;

    const setExcelMode = (mode) => {
        activeExcelMode = mode === 'update' ? 'update' : 'import';
        modeTabs.forEach((tab) => {
            const isActive = tab.getAttribute('data-excel-mode') === activeExcelMode;
            tab.classList.toggle('is-active', isActive);
            tab.classList.toggle('tm-btn-primary', isActive);
            tab.classList.toggle('tm-btn-outline', !isActive);
        });
        modePanels.forEach((panel) => {
            const isActive = panel.getAttribute('data-excel-mode-panel') === activeExcelMode;
            panel.classList.toggle('tm-hidden', !isActive);
        });
        updateMunicipioImportMode();
    };

    modeTabs.forEach((tab) => {
        tab.addEventListener('click', function () {
            setExcelMode(tab.getAttribute('data-excel-mode') || 'import');
        });
    });

    function renderImportMunicipios() {
        if (!mrSelect || !municipioSelectImport) return;
        const mrId = String(mrSelect.value || '');
        const municipios = Array.isArray(municipiosPorMicrorregion[mrId]) ? municipiosPorMicrorregion[mrId] : [];
        const prev = municipioSelectImport.value;
        municipioSelectImport.innerHTML = '<option value="">Selecciona un municipio</option>';
        municipios.forEach(function (m) {
            const opt = new Option(m, m, false, prev === m);
            municipioSelectImport.appendChild(opt);
        });
    }

    function updateMunicipioImportMode() {
        const municipioField = Array.isArray(excelFields) ? excelFields.find(function (f) { return f && f.type === 'municipio'; }) : null;
        const municipioMapSelect = municipioField
            ? document.querySelector(getActiveMapSelector() + '[data-field-key="' + String(municipioField.key || '').replace(/"/g, '') + '"]')
            : null;
        const hasMunicipioColumnMapped = !!(municipioMapSelect && municipioMapSelect.value !== '');
        if (autoMunicipioWrap) autoMunicipioWrap.style.display = municipioMapSelect ? '' : 'none';
        const activeAutoCheck = getActiveAutoMunicipioCheck();
        if (activeAutoCheck) {
            activeAutoCheck.disabled = !hasMunicipioColumnMapped;
            if (!hasMunicipioColumnMapped) activeAutoCheck.checked = false;
            if (hasMunicipioColumnMapped && activeAutoCheck.dataset.userTouched !== '1') {
                activeAutoCheck.checked = true;
            }
        }
        const useManualMunicipio = !hasMunicipioColumnMapped || !activeAutoCheck || !activeAutoCheck.checked;
        if (municipioLabel) {
            const shouldShow = !searchAllCheck?.checked && useManualMunicipio;
            municipioLabel.style.display = shouldShow ? 'block' : 'none';
        }
    }

    hInput?.addEventListener('input', updateRowHighlights);
    dInput?.addEventListener('input', updateRowHighlights);

    searchAllCheck?.addEventListener('change', function() {
        if (mrLabel) mrLabel.style.display = this.checked ? 'none' : 'block';
        renderImportMunicipios();
        updateMunicipioImportMode();
    });
    mrSelect?.addEventListener('change', function () {
        renderImportMunicipios();
        updateMunicipioImportMode();
    });
    autoMunicipioCheck?.addEventListener('change', function () { this.dataset.userTouched = '1'; updateMunicipioImportMode(); });
    autoMunicipioCheckUpdate?.addEventListener('change', function () { this.dataset.userTouched = '1'; updateMunicipioImportMode(); });
    renderImportMunicipios();
    updateMunicipioImportMode();

    document.getElementById('tmExcelAutoDetect')?.addEventListener('click', function() {
        if (!workbookData) return;
        let found = currentSheetStartRow;
        for (let i = 0; i < workbookData.length; i++) {
            if ((workbookData[i] || []).filter(c => c !== null && c !== '').length >= 3) {
                found = currentSheetStartRow + i;
                break;
            }
        }
        document.getElementById('tmExcelHeaderRow').value = found;
        document.getElementById('tmExcelDataStartRow').value = found + 1;
        updateRowHighlights();
    });

    document.getElementById('tmExcelLeerColumnas')?.addEventListener('click', function () {
        const btn = this;
        const errSection = document.getElementById('tmExcelErrorsSection');
        if (errSection) errSection.classList.add('tm-hidden');
        const errList = document.getElementById('tmExcelErrorsList');
        if (errList) errList.innerHTML = '';

        const file = document.getElementById('tmExcelFile')?.files[0];
        if (!file) return;

        // Loading state
        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Leyendo columnas…';

        const fd = new FormData();
        fd.append('archivo_excel', file);
        fd.append('header_row', document.getElementById('tmExcelHeaderRow')?.value || '1');
        fd.append('sheet_index', currentSheetIdx);
        fd.append('auto_detect', '0');
        fd.append('_token', csrfToken);

        csrfFetch(excelPreviewUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
            .then(r => safeJsonParse(r))
            .then(j => {
                if (!j.success) throw new Error(j.message);
                excelHeaders = j.headers || [];
                excelFields = j.fields || [];
                excelSuggested = j.suggested_map || {};
                const warnings = Array.isArray(j.warnings) ? j.warnings.filter(Boolean) : [];
                if (warnings.length) {
                    const detectNote = document.getElementById('tmExcelDetectNote');
                    if (detectNote) {
                        detectNote.textContent = warnings.join(' ');
                        detectNote.classList.remove('tm-hidden');
                    }
                }
                if (j.preview_thumbnails) applyExcelPreviewThumbnails(j.preview_thumbnails);

                const updateMappedColumns = () => {
                    document.querySelectorAll('.is-mapped-column').forEach(el => el.classList.remove('is-mapped-column'));
                    document.querySelectorAll(getActiveMapSelector()).forEach(sel => {
                        if (sel.value === '') return;
                        const idx = parseInt(sel.value, 10);
                        const th = document.querySelector(`.tm-excel-preview-table thead th:nth-child(${idx + 2})`);
                        if (th) th.classList.add('is-mapped-column');
                        document.querySelectorAll(`.tm-excel-preview-table tbody tr`).forEach(tr => {
                            const td = tr.querySelector(`td:nth-child(${idx + 2})`);
                            if (td) td.classList.add('is-mapped-column');
                        });
                    });
                };

                const updateMatchingKeyPriorities = () => {
                    let rank = 1;
                    document.querySelectorAll('.tm-excel-map-select-update').forEach(sel => {
                        const key = sel.getAttribute('data-field-key');
                        if (!key) return;
                        const checkbox = document.querySelector('.tm-excel-match-key[data-field-key="' + String(key).replace(/"/g, '') + '"]');
                        const badge = document.querySelector('.tm-excel-match-order[data-field-key="' + String(key).replace(/"/g, '') + '"]');
                        if (!checkbox || !badge) return;

                        if (sel.value === '') {
                            checkbox.checked = false;
                            checkbox.disabled = true;
                            badge.style.display = 'none';
                            badge.textContent = '';
                            return;
                        }

                        checkbox.disabled = false;
                        if (checkbox.checked) {
                            badge.textContent = 'Base ' + rank;
                            badge.style.display = 'inline-flex';
                            rank++;
                        } else {
                            badge.style.display = 'none';
                            badge.textContent = '';
                        }
                    });
                };

                const friendlyTypes = {
                    'text': 'Texto',
                    'image': 'Imagen/Foto',
                    'select': 'Lista de opciones',
                    'geopoint': 'Ubicación (GPS)',
                    'date': 'Fecha',
                    'number': 'Número',
                    'url': 'Enlace web',
                    'textarea': 'Texto largo'
                };

                const tbodyImport = document.getElementById('tmExcelMapBodyImport');
                const tbodyUpdate = document.getElementById('tmExcelMapBodyUpdate');
                if (tbodyImport && tbodyUpdate) {
                    tbodyImport.innerHTML = '';
                    tbodyUpdate.innerHTML = '';
                    excelFields.forEach(f => {
                        const trImport = document.createElement('tr');
                        const trUpdate = document.createElement('tr');
                        const sug = excelSuggested[f.key];
                        const friendly = friendlyTypes[f.type] || f.type;
                        let opts = '<option value="">— No importar —</option>';
                        excelHeaders.forEach(h => {
                            const sel = (sug === h.index) ? ' selected' : '';
                            opts += '<option value="' + h.index + '"' + sel + '>' + (h.letter + ': ' + (h.label || '(vacío)')).replace(/</g, '') + '</option>';
                        });
                        trImport.innerHTML = '<td title="Tipo: ' + friendly + '" style="cursor:help; font-weight:600;">' + String(f.label).replace(/</g, '') + (f.is_required ? ' *' : '') + '</td><td><select class="tm-excel-map-select-import" data-field-key="' + String(f.key).replace(/"/g, '') + '">' + opts + '</select></td>';
                        trUpdate.innerHTML = '<td title="Tipo: ' + friendly + '" style="cursor:help; font-weight:600; width:38%;">' + String(f.label).replace(/</g, '') + (f.is_required ? ' *' : '') + '</td><td><div style="display:flex; flex-direction:column; gap:8px;"><div style="display:flex; flex-direction:column; gap:4px;"><span style="font-size:10px; color:var(--clr-text-light);">Columna del Excel</span><select class="tm-excel-map-select-update" data-field-key="' + String(f.key).replace(/"/g, '') + '" style="width:100%; min-width:0;">' + opts + '</select></div><div style="display:flex; flex-wrap:wrap; gap:12px 18px; align-items:center;"><label style="display:flex; align-items:center; gap:6px; font-size:12px; line-height:1.2;"><input type="checkbox" class="tm-excel-match-key" data-field-key="' + String(f.key).replace(/"/g, '') + '"><span>Usar como base</span></label><span class="tm-excel-match-order" data-field-key="' + String(f.key).replace(/"/g, '') + '" style="display:none; padding:2px 8px; border-radius:999px; background:rgba(47,111,237,0.12); color:#2f6fed; font-weight:700;"></span><label style="display:flex; align-items:center; gap:6px; font-size:12px; line-height:1.2;"><input type="checkbox" class="tm-excel-update-key" data-field-key="' + String(f.key).replace(/"/g, '') + '"><span>Actualizar</span></label></div></div></td>';
                        tbodyImport.appendChild(trImport);
                        tbodyUpdate.appendChild(trUpdate);
                    });

                    document.querySelectorAll('.tm-excel-map-select-import, .tm-excel-map-select-update').forEach(sel => {
                        sel.addEventListener('change', function () {
                            updateMappedColumns();
                            updateMunicipioImportMode();
                            updateMatchingKeyPriorities();
                        });
                    });
                    document.querySelectorAll('.tm-excel-match-key').forEach(chk => {
                        chk.addEventListener('change', function () {
                            updateMatchingKeyPriorities();
                        });
                    });
                    updateMappedColumns();
                    updateMunicipioImportMode();
                    updateMatchingKeyPriorities();
                }
                setExcelMode(activeExcelMode);
                document.getElementById('tmExcelStep2')?.classList.remove('tm-hidden');

                // Desplazar suavemente hacia abajo dentro del contenedor lateral
                const controlsSide = document.querySelector('.tm-excel-controls-side');
                if (controlsSide) {
                    setTimeout(() => {
                        controlsSide.scrollTo({
                            top: controlsSide.scrollHeight,
                            behavior: 'smooth'
                        });
                    }, 100);
                }

                const stepNote = document.getElementById('tmExcelStepNote');
                if (stepNote) stepNote.textContent = 'Relaciona cada campo del módulo con una columna de tu Excel.';
            }).catch(e => {
                const err = document.getElementById('tmExcelPreviewErr');
                if (err) { err.textContent = e.message; err.classList.remove('tm-hidden'); }
            }).finally(() => {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            });
    });

    document.getElementById('tmExcelVolver')?.addEventListener('click', () => {
        document.getElementById('tmExcelStep2')?.classList.add('tm-hidden');
        const controlsSide = document.querySelector('.tm-excel-controls-side');
        if (controlsSide) controlsSide.scrollTo({ top: 0, behavior: 'smooth' });

        const stepNote = document.getElementById('tmExcelStepNote');
        if (stepNote) stepNote.innerHTML = 'Haz clic en una fila para marcar <strong>Encabezado</strong> y <strong>Datos</strong>.';
    });

    document.getElementById('tmExcelImportar')?.addEventListener('click', function () {
        const errSection = document.getElementById('tmExcelErrorsSection');
        if (errSection) errSection.classList.add('tm-hidden');
        const errList = document.getElementById('tmExcelErrorsList');
        if (errList) errList.innerHTML = '';

        const file = document.getElementById('tmExcelFile')?.files[0];
        if (!file) return;
        const mapping = {};
        document.querySelectorAll('.tm-excel-map-select-import').forEach(sel => {
            const key = sel.getAttribute('data-field-key');
            if (key) mapping[key] = sel.value === '' ? null : parseInt(sel.value, 10);
        });
        const municipioLabelVisible = municipioLabel && municipioLabel.style.display !== 'none';
        if (municipioLabelVisible && municipioSelectImport && !municipioSelectImport.value) {
            const errEl = document.getElementById('tmExcelImportErr');
            if (errEl) {
                errEl.textContent = 'Selecciona un municipio de destino o mapea la columna Municipio para identificarlo automáticamente.';
                errEl.classList.remove('tm-hidden');
            }
            return;
        }
        const fd = new FormData();
        fd.append('archivo_excel', file);
        fd.append('header_row', document.getElementById('tmExcelHeaderRow')?.value || '1');
        fd.append('data_start_row', document.getElementById('tmExcelDataStartRow')?.value || '2');
        fd.append('mapping', JSON.stringify(mapping));
        fd.append('all_microrregions', document.getElementById('tmExcelSearchAll')?.checked ? '1' : '0');
        fd.append('selected_microrregion_id', document.getElementById('tmExcelMicrorregionId')?.value || '');
        fd.append('selected_municipio', document.getElementById('tmExcelSelectedMunicipio')?.value || '');
        fd.append('auto_identify_municipio', document.getElementById('tmExcelAutoIdentifyMunicipio')?.checked ? '1' : '0');
        fd.append('sheet_index', currentSheetIdx);
        fd.append('_token', csrfToken);

        csrfFetch(excelImportUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
            .then(r => safeJsonParse(r))
            .then(j => {
                const okEl = document.getElementById('tmExcelImportOk');
                const errEl = document.getElementById('tmExcelImportErr');
                if (okEl) okEl.classList.add('tm-hidden');
                if (errEl) errEl.classList.add('tm-hidden');

                if (!j.success) throw new Error(j.message);

                // Mostrar errores detallados si existen
                const errSection = document.getElementById('tmExcelErrorsSection');
                const errList = document.getElementById('tmExcelErrorsList');
                if (j.row_errors && j.row_errors.length > 0) {
                    errSection?.classList.remove('tm-hidden');
                    if (errList) {
                        errList.innerHTML = '';
                        j.row_errors.forEach((err, idx) => {
                            const card = document.createElement('div');
                            card.className = 'tm-error-log-card';
                            card.style = 'padding:12px; border:1px solid var(--clr-border); border-radius:10px; background:var(--clr-bg); font-size:0.85rem;';

                            // Renderizar campos con fallo
                            const failedFields = Array.isArray(err?.failed_fields) && err.failed_fields.length > 0 ? err.failed_fields : [];
                            let failedFieldsHtml = '';
                            if (failedFields.length > 0) {
                                failedFieldsHtml = '<div style="margin-top:8px;"><strong style="font-size:0.75rem;">Campos con fallo:</strong><ul style="margin:6px 0 0 16px; padding:0; font-size:0.8rem; color:var(--clr-text-light);">' +
                                    failedFields.map((f) => {
                                        const escapeHtml = (value) => String(value ?? '')
                                            .replace(/&/g, '&amp;')
                                            .replace(/</g, '&lt;')
                                            .replace(/>/g, '&gt;')
                                            .replace(/"/g, '&quot;')
                                            .replace(/'/g, '&#039;');
                                        return `<li><strong>${escapeHtml(f.label || f.key || 'Campo')}</strong>: ${escapeHtml(f.reason || 'No válido')}${f.received ? ` <span style="color:var(--clr-primary); font-weight:600;">(ingresó: "${escapeHtml(f.received)}")</span>` : ''}</li>`;
                                    }).join('') +
                                '</ul></div>';
                            }

                            let suggHtml = '';
                            if (err.suggestions && err.suggestions.length > 0) {
                                const cardId = 'tmErrRow_' + idx;
                                const moduleId = String(TM_SHOW.moduleId || '');
                                const isFieldSugg = typeof err.suggestions[0] === 'string';
                                const failedKey = isFieldSugg && err.failed_fields && err.failed_fields.length === 1 ? err.failed_fields[0].key : '';
                                suggHtml = '<div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:6px;">' +
                                    '<span style="width:100%; font-weight:600; font-size:0.75rem; margin-bottom:4px; display:block;">¿Quisiste decir?</span>' +
                                    err.suggestions.map(s => {
                                        if (isFieldSugg) {
                                            const val = String(s).replace(/'/g, "\\'");
                                            return `<button type="button" class="tm-btn tm-btn-sm tm-btn-outline"
                                                onclick="retryImportRow(${idx}, null, '${val}', null, ${moduleId}, '${cardId}', this, ${err.row || 0}, '${failedKey}')"
                                                style="font-size:0.7rem; padding:4px 8px;">
                                                ${s}
                                            </button>`;
                                        }
                                        return `<button type="button" class="tm-btn tm-btn-sm tm-btn-outline"
                                            onclick="retryImportRow(${idx}, ${s.microrregion_id}, '${s.municipio.replace(/'/g, "\\'")}'  , null, ${moduleId}, '${cardId}', this, ${err.row || 0})"
                                            style="font-size:0.7rem; padding:4px 8px;">
                                            ${s.municipio}
                                        </button>`;
                                    }).join('') +
                                '</div>';
                            }

                            card.innerHTML = `
                                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                    <div style="color:var(--clr-primary); font-weight:700;">Fila ${err.row}</div>
                                    <div style="font-size:0.75rem; color:var(--clr-text-light); text-align:right;">${err.message}</div>
                                </div>
                                ${failedFieldsHtml}
                                ${suggHtml}
                            `;
                            card.dataset.rowData = JSON.stringify(err.data);
                            card.dataset.municipioKey = String(err.municipio_key || 'municipio');
                            card.id = 'tmErrRow_' + idx;
                            errList.appendChild(card);
                        });

                        // Scroll suave a la zona de errores dentro del contenedor lateral
                        const controlsSide = document.querySelector('.tm-excel-controls-side');
                        if (controlsSide) {
                            setTimeout(() => {
                                controlsSide.scrollTo({ top: errSection.offsetTop, behavior: 'smooth' });
                            }, 100);
                        }
                    }
                }

                const warnings = Array.isArray(j.warnings) ? j.warnings.filter(Boolean) : [];
                const msg = warnings.length ? ((j.message || '') + ' ' + warnings.join(' ')).trim() : j.message;

                // Acumular registros importados
                if (j.imported > 0) excelImportedCount += j.imported;

                // Persistir errores en sesión
                if (j.row_errors && j.row_errors.length > 0) {
                    const moduleId = String(TM_SHOW.moduleId || '');
                    saveImportErrors(moduleId, j.row_errors, excelImportSingleUrl);
                }

                Swal.fire({ title: '¡Completado!', text: msg, icon: 'success', confirmButtonText: 'Aceptar' })
                    .then(() => {
                        if (j.skipped === 0) {
                            const moduleId = String(TM_SHOW.moduleId || '');
                            saveImportErrors(moduleId, [], ''); // Limpiar si todo fue ok
                        }
                    });
            }).catch(e => {
                const err = document.getElementById('tmExcelImportErr');
                if (err) { err.textContent = e.message; err.classList.remove('tm-hidden'); }
            });
    });

    // Botón "Actualizar registros existentes"
    document.getElementById('tmExcelActualizarExistentes')?.addEventListener('click', function () {
        const errSection = document.getElementById('tmExcelErrorsSection');
        if (errSection) errSection.classList.add('tm-hidden');
        const errList = document.getElementById('tmExcelErrorsList');
        if (errList) errList.innerHTML = '';

        const file = document.getElementById('tmExcelFile')?.files[0];
        if (!file) return;
        const mapping = {};
        document.querySelectorAll('.tm-excel-map-select-update').forEach(sel => {
            const key = sel.getAttribute('data-field-key');
            if (key) mapping[key] = sel.value === '' ? null : parseInt(sel.value, 10);
        });
        const matchingKeys = [];
        document.querySelectorAll('.tm-excel-match-key:checked').forEach(chk => {
            const key = chk.getAttribute('data-field-key');
            if (!key) return;
            if (!Object.prototype.hasOwnProperty.call(mapping, key)) return;
            if (mapping[key] === null) return;
            matchingKeys.push(key);
        });
        if (matchingKeys.length === 0) {
            const err = document.getElementById('tmExcelImportErr');
            if (err) {
                err.textContent = 'Selecciona al menos una columna base mapeada para identificar coincidencias.';
                err.classList.remove('tm-hidden');
            }
            return;
        }
        const updateKeys = [];
        document.querySelectorAll('.tm-excel-update-key:checked').forEach(chk => {
            const key = chk.getAttribute('data-field-key');
            if (!key) return;
            if (!Object.prototype.hasOwnProperty.call(mapping, key)) return;
            if (mapping[key] === null) return;
            if (matchingKeys.includes(key)) return;
            updateKeys.push(key);
        });
        if (updateKeys.length === 0) {
            const err = document.getElementById('tmExcelImportErr');
            if (err) {
                err.textContent = 'Selecciona al menos una columna a actualizar (distinta de las columnas base).';
                err.classList.remove('tm-hidden');
            }
            return;
        }
        const fd = new FormData();
        fd.append('archivo_excel', file);
        fd.append('header_row', document.getElementById('tmExcelHeaderRow')?.value || '1');
        fd.append('data_start_row', document.getElementById('tmExcelDataStartRow')?.value || '2');
        fd.append('mapping', JSON.stringify(mapping));
        fd.append('matching_keys', JSON.stringify(matchingKeys));
        fd.append('update_keys', JSON.stringify(updateKeys));
        fd.append('all_microrregions', document.getElementById('tmExcelSearchAll')?.checked ? '1' : '0');
        fd.append('selected_microrregion_id', document.getElementById('tmExcelMicrorregionId')?.value || '');
        fd.append('selected_municipio', document.getElementById('tmExcelSelectedMunicipio')?.value || '');
        fd.append('auto_identify_municipio', document.getElementById('tmExcelAutoIdentifyMunicipioUpdate')?.checked ? '1' : '0');
        fd.append('sheet_index', currentSheetIdx);
        fd.append('_token', csrfToken);

        const btn = this;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Actualizando...';

        csrfFetch(excelUpdateUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
            .then(r => safeJsonParse(r))
            .then(j => {
                const okEl = document.getElementById('tmExcelImportOk');
                const errEl = document.getElementById('tmExcelImportErr');
                if (okEl) okEl.classList.add('tm-hidden');
                if (errEl) errEl.classList.add('tm-hidden');

                if (!j.success) throw new Error(j.message);

                if (j.row_errors && j.row_errors.length > 0) {
                    const errSection = document.getElementById('tmExcelErrorsSection');
                    const errList = document.getElementById('tmExcelErrorsList');
                    errSection?.classList.remove('tm-hidden');
                    if (errList) {
                        errList.innerHTML = '';
                        j.row_errors.forEach((err) => {
                            const card = document.createElement('div');
                            card.className = 'tm-error-log-card';
                            card.style = 'padding:12px; border:1px solid var(--clr-border); border-radius:10px; background:var(--clr-bg); font-size:0.85rem;';
                            card.innerHTML = '<div style="font-weight:600; color:var(--clr-text-light); margin-bottom:4px;">Fila ' + (err.row || '?') + '</div><div>' + String(err.message || '').replace(/</g, '&lt;') + '</div>';
                            errList.appendChild(card);
                        });
                    }
                }

                if (j.updated > 0) excelImportedCount += j.updated;

                Swal.fire({ title: '¡Completado!', text: j.message, icon: j.updated > 0 ? 'success' : 'info', confirmButtonText: 'Aceptar' });
            }).catch(e => {
                const err = document.getElementById('tmExcelImportErr');
                if (err) { err.textContent = e.message; err.classList.remove('tm-hidden'); }
            }).finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
    });

    function renderShowErrorCardHtml(err, idx, moduleId, cardId) {
        const escapeH = (v) => String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
        const failedFields = Array.isArray(err?.failed_fields) && err.failed_fields.length > 0 ? err.failed_fields : [];
        // Contar campos que tienen sugerencias (para modo multi-corrección)
        const fieldsWithSugg = failedFields.filter(f => Array.isArray(f.suggestions) && f.suggestions.length > 0).length;
        const isMultiCorrect = fieldsWithSugg >= 2;

        let failedFieldsHtml = '';
        if (failedFields.length > 0) {
            failedFieldsHtml = '<div style="margin-top:8px;"><strong style="font-size:0.75rem;">Campos con fallo:</strong><ul style="margin:6px 0 0 16px; padding:0; font-size:0.8rem; color:var(--clr-text-light);">' +
                failedFields.map(f => {
                    let fieldSugg = '';
                    if (Array.isArray(f.suggestions) && f.suggestions.length > 0) {
                        fieldSugg = '<div style="margin-top:6px; display:flex; flex-wrap:wrap; gap:4px;">' +
                            '<span style="width:100%; font-size:0.65rem; color:var(--clr-primary); font-weight:700;">VALORES DISPONIBLES:</span>' +
                            f.suggestions.map(s => {
                                return `<button type="button" class="tm-btn tm-btn-sm tm-btn-outline ${isMultiCorrect ? 'tm-show-stage-btn' : 'tm-sugg-btn'}"
                                    data-idx="${idx}" data-val="${escapeH(s)}" data-key="${escapeH(f.key)}"
                                    data-mid="${moduleId}" data-cid="${escapeH(cardId)}" data-row="${err.row || 0}"
                                    style="font-size:0.68rem; padding:3px 6px; font-weight:600; text-transform:uppercase;">
                                    ${escapeH(s)}
                                </button>`;
                            }).join('') +
                        '</div>';
                    }
                    return `<li style="margin-bottom:8px;"><strong>${escapeH(f.label || f.key || 'Campo')}</strong>: ${escapeH(f.reason || 'No válido')}${f.received ? ` <span style="color:var(--clr-primary); font-weight:600;">(ingresó: "${escapeH(f.received)}")</span>` : ''}${fieldSugg}</li>`;
                }).join('') +
            '</ul></div>';
        }
        let suggHtml = '';
        if (!failedFields.some(f => f.suggestions && f.suggestions.length > 0) && err.suggestions && err.suggestions.length > 0) {
            suggHtml = '<div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:6px;">' +
                '<span style="width:100%; font-weight:600; font-size:0.75rem; margin-bottom:4px; display:block;">¿Quisiste decir?</span>' +
                err.suggestions.map(s => {
                    if (typeof s === 'string') {
                        return `<button type="button" class="tm-btn tm-btn-sm tm-btn-outline tm-sugg-btn"
                            data-idx="${idx}" data-val="${escapeH(s)}"
                            data-mid="${moduleId}" data-cid="${escapeH(cardId)}" data-row="${err.row || 0}"
                            style="font-size:0.7rem; padding:4px 8px;">${escapeH(s)}</button>`;
                    }
                    return `<button type="button" class="tm-btn tm-btn-sm tm-btn-outline tm-sugg-btn"
                        data-idx="${idx}" data-val="${escapeH(s.municipio)}" data-mr="${s.microrregion_id}"
                        data-mid="${moduleId}" data-cid="${escapeH(cardId)}" data-row="${err.row || 0}"
                        style="font-size:0.7rem; padding:4px 8px;">${escapeH(s.municipio)}</button>`;
                }).join('') +
            '</div>';
        }
        const saveBtnHtml = isMultiCorrect
            ? `<button type="button" class="tm-btn tm-btn-sm tm-show-save-staged-btn" data-cid="${escapeH(cardId)}" data-idx="${idx}" data-mid="${moduleId}" data-row="${err.row || 0}" style="margin-top:10px; padding:5px 14px; font-size:0.78rem; background:var(--clr-secondary,#2e7d32); color:#fff; border:0; border-radius:6px; cursor:pointer; display:none; font-weight:700;"><i class="fa-solid fa-check"></i> Guardar correcciones</button>`
            : '';
        return `
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div style="color:var(--clr-primary); font-weight:700;">Fila ${err.row}</div>
                <div style="font-size:0.75rem; color:var(--clr-text-light); text-align:right;">${escapeH(err.message)}</div>
            </div>
            ${failedFieldsHtml}
            ${suggHtml}
            ${saveBtnHtml}
        `;
    }

    // Delegated click handler for suggestion buttons — single-field mode (immediate send)
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.tm-sugg-btn');
        if (!btn) return;
        const ds = btn.dataset;
        const mrId = ds.mr ? Number(ds.mr) : null;
        retryImportRow(Number(ds.idx), mrId, ds.val, null, Number(ds.mid), ds.cid, btn, Number(ds.row), ds.key || null);
    });

    // Delegated click handler for staging buttons — multi-field mode (stage locally)
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.tm-show-stage-btn');
        if (!btn) return;
        const ds = btn.dataset;
        const card = document.getElementById(ds.cid);
        if (!card) return;

        // Deseleccionar otros botones del mismo campo
        card.querySelectorAll(`.tm-show-stage-btn[data-key="${CSS.escape(ds.key)}"]`).forEach(b => {
            b.style.background = '';
            b.style.color = '';
            b.style.borderColor = '';
        });
        // Marcar este como seleccionado
        btn.style.background = 'var(--clr-secondary, #2e7d32)';
        btn.style.color = '#fff';
        btn.style.borderColor = 'var(--clr-secondary, #2e7d32)';

        // Guardar corrección en el dataset de la tarjeta
        const staged = JSON.parse(card.dataset.stagedCorrections || '{}');
        staged[ds.key] = { val: ds.val, mr: ds.mr || null };
        card.dataset.stagedCorrections = JSON.stringify(staged);

        // Mostrar botón "Guardar correcciones"
        const saveBtn = card.querySelector('.tm-show-save-staged-btn');
        if (saveBtn) saveBtn.style.display = '';
    });

    // Delegated click handler for "Guardar correcciones" button
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.tm-show-save-staged-btn');
        if (!btn) return;
        const ds = btn.dataset;
        const card = document.getElementById(ds.cid);
        if (!card) return;

        const staged = JSON.parse(card.dataset.stagedCorrections || '{}');
        if (Object.keys(staged).length === 0) return;

        const rowData = JSON.parse(card.dataset.rowData);
        let mrId = null;
        for (const [key, corr] of Object.entries(staged)) {
            const wasArray = Array.isArray(rowData[key]);
            rowData[key] = wasArray ? [corr.val] : corr.val;
            if (corr.mr) mrId = Number(corr.mr);
        }

        retryImportRow(Number(ds.idx), mrId, null, null, Number(ds.mid), ds.cid, btn, Number(ds.row), null, rowData);
    });

    window.retryImportRow = function(errIdx, microrregionId, correctedValue, singleUrl, moduleId, cardId, buttonEl, rowNumber, fieldKey, prebuiltRowData) {
        const card = document.getElementById(cardId || ('tmErrRow_' + errIdx));
        if (!card) return;

        let rowData;
        if (prebuiltRowData) {
            rowData = prebuiltRowData;
        } else {
            rowData = JSON.parse(card.dataset.rowData);
            const resolvedKey = fieldKey || String(card.dataset.municipioKey || 'municipio');
            // Si el valor original era un array (multiselect), envolver la corrección en array
            const wasArray = Array.isArray(rowData[resolvedKey]);
            rowData[resolvedKey] = wasArray ? [correctedValue] : correctedValue;
        }
        // Limpiar correcciones staged al enviar
        delete card.dataset.stagedCorrections;

        const btn = buttonEl || event.target;
        const oldText = btn.innerText;
        btn.disabled = true;
        btn.innerText = 'Cargando...';

        const payload = { data: rowData };
        if (microrregionId != null) {
            payload.microrregion_id = microrregionId;
        }

        csrfFetch(singleUrl || excelImportSingleUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
        .then(r => safeJsonParse(r))
        .then(j => {
            if (j.success) {
                excelImportedCount++;
                card.style.opacity = '0.5';
                card.style.pointerEvents = 'none';
                btn.innerText = '✓ Importado';
                btn.style.background = 'var(--clr-secondary)';
                btn.style.color = '#fff';

                // Si se pasó moduleId y rowNumber, remover este error de la sesión usando el número de fila (identificador único)
                if (moduleId && rowNumber) {
                    const data = sessionStorage.getItem(`tm_errors_${moduleId}`);
                    if (data) {
                        const parsed = JSON.parse(data);
                        // Usar el número de fila como identificador único, no el índice
                        parsed.errors = (parsed.errors || []).filter((e) => e.row !== rowNumber);
                        saveImportErrors(moduleId, parsed.errors, parsed.singleUrl);
                    }
                }
            } else if (j.error && j.error.failed_fields && j.error.failed_fields.length > 0) {
                btn.disabled = false;
                btn.innerText = oldText;
                j.error.row = rowNumber || j.error.row || 'Manual';
                card.dataset.rowData = JSON.stringify(j.error.data || rowData);
                if (j.error.municipio_key) card.dataset.municipioKey = j.error.municipio_key;
                const mid = moduleId || String(TM_SHOW.moduleId || '');
                const cid = cardId || ('tmErrRow_' + errIdx);
                card.innerHTML = renderShowErrorCardHtml(j.error, errIdx, mid, cid);
                card.style.opacity = '1';
                card.style.pointerEvents = 'auto';
            } else {
                throw new Error(j.message || 'Error al reintentar');
            }
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerText = oldText;
            Swal.fire('Error', e.message, 'error');
        });
    };

    // Global Drag & Drop for Excel / PDF
    const globalOverlay = document.getElementById('tmGlobalExcelDropOverlay');
    let dragCounter = 0;
    const isImportModalOpen = () => !!(excelModal && excelModal.classList.contains('is-open'));

    window.addEventListener('dragenter', (e) => {
        if (!isImportModalOpen()) return;
        if (e.dataTransfer?.types?.includes('Files')) {
            dragCounter++;
            globalOverlay?.classList.add('is-active');
        }
    });

    window.addEventListener('dragleave', (e) => {
        if (!isImportModalOpen()) return;
        dragCounter--;
        if (dragCounter <= 0) {
            globalOverlay?.classList.remove('is-active');
            dragCounter = 0;
        }
    });

    window.addEventListener('dragover', (e) => {
        if (!isImportModalOpen()) return;
        e.preventDefault();
    });

    window.addEventListener('drop', (e) => {
        if (!isImportModalOpen()) return;
        e.preventDefault();
        dragCounter = 0;
        globalOverlay?.classList.remove('is-active');

        const file = e.dataTransfer.files[0];
        if (!file) return;

        const name = (file.name || '').toLowerCase();
        if (!name.endsWith('.xlsx') && !name.endsWith('.xls') && !name.endsWith('.csv') && !name.endsWith('.pdf')) return;

        if (excelModal) {
            if (!excelModal.classList.contains('is-open')) openExcelModal();
            handleExcelFile(file);
        }
    });

    });
