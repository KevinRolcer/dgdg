    document.addEventListener('DOMContentLoaded', function () {
        const createTemplateSwal = function () {
            if (typeof Swal === 'undefined') {
                return null;
            }

            return Swal.mixin({
                buttonsStyling: false,
                customClass: {
                    popup: 'tm-swal-popup',
                    title: 'tm-swal-title',
                    htmlContainer: 'tm-swal-text',
                    confirmButton: 'tm-swal-confirm',
                    denyButton: 'tm-swal-deny',
                    cancelButton: 'tm-swal-cancel'
                }
            });
        };

        const openButtons = Array.from(document.querySelectorAll('[data-open-module-preview]'));
        const clearButtons = Array.from(document.querySelectorAll('[data-clear-module-entries]'));
        const imagePreviewButtons = Array.from(document.querySelectorAll('[data-open-image-preview]'));
        const textToggleButtons = Array.from(document.querySelectorAll('[data-text-toggle]'));
        const cellExpandButtons = Array.from(document.querySelectorAll('[data-cell-expand]'));
        const exportButtons = Array.from(document.querySelectorAll('[data-open-export-options]'));
        const seedLogModal = document.getElementById('tmSeedDiscardLogModal');
        const seedLogTbody = document.getElementById('tmSeedDiscardLogTbody');
        const seedLogModuleEl = document.getElementById('tmSeedDiscardLogModule');
        const seedLogEmpty = document.getElementById('tmSeedDiscardLogEmpty');
        const seedLogTableWrap = document.getElementById('tmSeedDiscardLogTableWrap');

        function openSeedDiscardLog(moduleName, jsonId) {
            if (!seedLogModal || !seedLogTbody) return;
            var el = document.getElementById(jsonId);
            var list = [];
            try {
                list = el && el.textContent ? JSON.parse(el.textContent) : [];
            } catch (e) {
                list = [];
            }
            if (!Array.isArray(list)) list = [];
            seedLogModuleEl.textContent = moduleName || 'Módulo';
            seedLogTbody.innerHTML = '';
            if (list.length === 0) {
                seedLogEmpty.hidden = false;
                seedLogTableWrap.hidden = true;
            } else {
                seedLogEmpty.hidden = true;
                seedLogTableWrap.hidden = false;
                list.forEach(function (row) {
                    var tr = document.createElement('tr');
                    var esc = function (s) {
                        if (s == null || s === '') return '—';
                        var d = document.createElement('div');
                        d.textContent = String(s);
                        return d.innerHTML;
                    };
                    tr.innerHTML =
                        '<td>' + esc(row.row) + '</td>' +
                        '<td>' + esc(row.reason) + '</td>' +
                        '<td>' + esc(row.microrregion) + '</td>' +
                        '<td>' + esc(row.municipio) + '</td>' +
                        '<td class="tm-seed-log-accion">' + esc(row.accion) + '</td>';
                    seedLogTbody.appendChild(tr);
                });
            }
            seedLogModal.classList.add('is-open');
            seedLogModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }
        function closeSeedDiscardLog() {
            if (!seedLogModal) return;
            seedLogModal.classList.remove('is-open');
            seedLogModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
        document.querySelectorAll('[data-tm-seed-log-open]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openSeedDiscardLog(btn.getAttribute('data-module-name'), btn.getAttribute('data-json-id'));
            });
        });
        document.querySelectorAll('[data-tm-seed-log-close]').forEach(function (el) {
            el.addEventListener('click', closeSeedDiscardLog);
        });

        const analysisModal = document.getElementById('tmAnalysisWordModal');
        const analysisForm = document.getElementById('tmAnalysisWordForm');
        const analysisPreviewDoc = document.getElementById('tmAnalysisPreviewDoc');
        const analysisLoading = document.getElementById('tmAnalysisPreviewLoading');

        function openAnalysisWordModal(previewUrl, wordUrl) {
            if (!analysisModal || !analysisForm) return;
            window._tmAnalysisPreviewUrl = previewUrl;
            window._tmAnalysisWordUrl = wordUrl;
            analysisForm.setAttribute('action', wordUrl);
            analysisModal.classList.add('is-open');
            analysisModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            loadAnalysisPreview(previewUrl);
        }
        function closeAnalysisWordModal() {
            if (!analysisModal) return;
            analysisModal.classList.remove('is-open');
            analysisModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
        function loadAnalysisPreview(baseUrl) {
            if (!analysisPreviewDoc) return;
            var sum = document.getElementById('tmAnalysisIncludeSummary');
            var mr = document.getElementById('tmAnalysisIncludeMrTable');
            var rows = document.getElementById('tmAnalysisCustomRows');
            var cols = document.getElementById('tmAnalysisCustomCols');
            var u = baseUrl + '?include_summary=' + (sum && sum.checked ? '1' : '0')
                + '&include_mr_table=' + (mr && mr.checked ? '1' : '0')
                + '&custom_rows=' + (rows ? rows.value : '0')
                + '&custom_cols=' + (cols ? cols.value : '0');
            if (analysisLoading) { analysisLoading.hidden = false; analysisLoading.style.display = 'block'; }
            fetch(u, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (analysisLoading) { analysisLoading.hidden = true; analysisLoading.style.display = 'none'; }
                    var html = '<div class="tm-analysis-doc-inner">';
                    html += '<h4 class="tm-analysis-doc-title">' + escapeHtml(data.module_name || 'Módulo') + '</h4>';
                    html += '<p class="tm-analysis-doc-sub">Análisis general</p>';
                    if (data.summary) {
                        html += '<table class="tm-analysis-mini-table">';
                        Object.keys(data.summary).forEach(function (k) {
                            html += '<tr><th>' + escapeHtml(k) + '</th><td>' + escapeHtml(String(data.summary[k])) + '</td></tr>';
                        });
                        html += '</table>';
                    }
                    if (data.mr_table && data.mr_headers) {
                        html += '<table class="tm-analysis-big-table"><thead><tr>';
                        data.mr_headers.forEach(function (h) { html += '<th>' + escapeHtml(h) + '</th>'; });
                        html += '</tr></thead><tbody>';
                        data.mr_table.slice(0, 8).forEach(function (row) {
                            html += '<tr>';
                            html += '<td>' + escapeHtml(String(row.microrregion || '')).substring(0, 40) + '</td>';
                            html += '<td>' + escapeHtml(String(row.registros)) + '</td>';
                            html += '<td>' + escapeHtml(String(row.municipios_capturados)) + '</td>';
                            html += '<td>' + escapeHtml(String(row.lista_capturados || '').substring(0, 80)) + '</td>';
                            html += '<td>' + escapeHtml(String(row.faltantes_count)) + '</td>';
                            html += '<td>' + escapeHtml(String(row.lista_faltantes || '').substring(0, 80)) + '</td>';
                            html += '</tr>';
                        });
                        if (data.mr_table.length > 8) html += '<tr><td colspan="6">… +' + (data.mr_table.length - 8) + ' filas</td></tr>';
                        html += '</tbody></table>';
                    }
                    if (data.custom_table && data.custom_table.rows) {
                        html += '<p class="tm-analysis-doc-sub">Tabla personalizada</p><table class="tm-analysis-grid-table">';
                        for (var i = 0; i < data.custom_table.rows; i++) {
                            html += '<tr>';
                            for (var j = 0; j < data.custom_table.cols; j++) html += '<td>&nbsp;</td>';
                            html += '</tr>';
                        }
                        html += '</table>';
                    }
                    html += '</div>';
                    analysisPreviewDoc.innerHTML = html;
                })
                .catch(function () {
                    if (analysisLoading) { analysisLoading.hidden = true; }
                    analysisPreviewDoc.innerHTML = '<p class="tm-analysis-err">No se pudo cargar la vista previa.</p>';
                });
        }
        var wordPersonalizeModal = document.getElementById('tmAnalysisWordPersonalizeModal');
        var wordPersonalizeForm = document.getElementById('tmAnalysisWordPersonalizeForm');
        var wordPersonalizePreview = document.getElementById('tmWordPersonalizePreview');
        var tmWordSlotKeys = new Array(12).fill('');
        function tmWordColumnKeysJson() {
            return JSON.stringify(tmWordSlotKeys.filter(function (k) { return k; }));
        }
        function tmWordRebuildPalette(fields) {
            var pal = document.getElementById('tmWordFieldPalette');
            if (!pal || !fields || !fields.length) return;
            pal.innerHTML = '';
            fields.forEach(function (f) {
                var chip = document.createElement('span');
                chip.className = 'tm-word-field-chip';
                chip.draggable = true;
                chip.setAttribute('data-field-key', f.key);
                chip.setAttribute('data-field-label', f.label);
                chip.setAttribute('data-field-type', f.type);
                chip.textContent = f.label + ' (' + f.type + ')';
                chip.addEventListener('dragstart', function (e) {
                    e.dataTransfer.setData('text/plain', f.key);
                    e.dataTransfer.setData('application/x-tm-field-label', f.label);
                });
                pal.appendChild(chip);
            });
        }
        function tmWordRebuildSlots(referenceRow, fieldsByKey) {
            var wrap = document.getElementById('tmWordColumnSlots');
            if (!wrap) return;
            wrap.innerHTML = '';
            for (var i = 0; i < 12; i++) {
                (function (idx) {
                    var slot = document.createElement('div');
                    slot.className = 'tm-word-slot' + (tmWordSlotKeys[idx] ? ' is-filled' : '');
                    slot.setAttribute('data-slot-index', idx);
                    var key = tmWordSlotKeys[idx];
                    var ref = key && referenceRow ? referenceRow[key] : '';
                    var label = key && fieldsByKey[key] ? fieldsByKey[key].label : '';
                    var slotBody = key
                        ? '<strong class="tm-word-slot-label">' + escapeHtml(label || key) + '</strong>'
                            + '<div class="tm-word-slot-ref" title="' + escapeHtml(ref || '') + '">' + escapeHtml(ref || '—') + '</div>'
                            + '<button type="button" class="tm-word-slot-clear" aria-label="Quitar">&times;</button>'
                        : '<span class="tm-word-slot-drop">Soltar campo aquí</span>';
                    slot.innerHTML = '<span class="tm-word-slot-num">' + (idx + 1) + '</span>'
                        + '<div class="tm-word-slot-body">' + slotBody + '</div>';
                    slot.addEventListener('dragover', function (e) { e.preventDefault(); slot.classList.add('is-dragover'); });
                    slot.addEventListener('dragleave', function () { slot.classList.remove('is-dragover'); });
                    slot.addEventListener('drop', function (e) {
                        e.preventDefault();
                        slot.classList.remove('is-dragover');
                        var k = e.dataTransfer.getData('text/plain');
                        if (!k) return;
                        tmWordSlotKeys[idx] = k;
                        loadWordPersonalizePreview();
                    });
                    var clr = slot.querySelector('.tm-word-slot-clear');
                    if (clr) clr.addEventListener('click', function (e) {
                        e.stopPropagation();
                        tmWordSlotKeys[idx] = '';
                        loadWordPersonalizePreview();
                    });
                    wrap.appendChild(slot);
                })(i);
            }
        }
        function applyWordPreviewSheetOrientation(orient) {
            orient = orient === 'landscape' ? 'landscape' : 'portrait';
            var modal = document.getElementById('tmAnalysisWordPersonalizeModal');
            var a4 = modal && modal.querySelector('.tm-analysis-word-preview-a4');
            var wordPage = document.getElementById('tmWordPreviewPage');
            if (a4) {
                a4.classList.toggle('tm-word-a4-landscape', orient === 'landscape');
                a4.classList.toggle('tm-word-a4-portrait', orient !== 'landscape');
            }
            if (wordPage) {
                wordPage.classList.remove('tm-word-preview-page--portrait', 'tm-word-preview-page--landscape');
                wordPage.classList.add(orient === 'landscape' ? 'tm-word-preview-page--landscape' : 'tm-word-preview-page--portrait');
            }
            if (wordPersonalizeModal) { applyWordPageZoom(wordPersonalizeModal._wordPreviewZoom || 100); }
        }
        function renderWordPreviewInto(el, data) {
            if (!el || !data) return;
            var align = data.title_align || 'center';
            var orient = (data.page_layout && data.page_layout.orientation) || 'portrait';
            if (orient !== 'landscape' && orient !== 'portrait') { orient = 'portrait'; }
            var btnOrient = document.querySelector('#tmAnalysisWordPersonalizeModal .tm-word-orient-btn.is-active');
            if (btnOrient) {
                var btnVal = btnOrient.getAttribute('data-word-orient');
                if (btnVal === 'landscape' || btnVal === 'portrait') { orient = btnVal; }
            }
            var tblAlign = (data.table_align || 'left');
            if (['left', 'center', 'right', 'stretch'].indexOf(tblAlign) < 0) { tblAlign = 'left'; }
            var tBtn = document.querySelector('#tmAnalysisWordPersonalizeModal .tm-word-table-align-btns .tm-export-align-btn.is-active');
            if (tBtn && tBtn.getAttribute('data-word-table-align')) { tblAlign = tBtn.getAttribute('data-word-table-align'); }
            var stretch = tblAlign === 'stretch';
            var ts = data.table_style || {};
            var fontPt = Math.min(12, Math.max(7, parseInt(ts.font_pt, 10) || 9));
            var padPx = Math.min(16, Math.max(2, parseInt(ts.cell_pad_px, 10) || 6));
            var maxPx = Math.min(280, Math.max(72, parseInt(ts.cell_max_px, 10) || 140));
            var cellStyle = stretch
                ? ('font-size:' + fontPt + 'pt;padding:' + padPx + 'px;box-sizing:border-box;vertical-align:top;word-break:break-word;')
                : ('font-size:' + fontPt + 'pt;padding:' + padPx + 'px;max-width:' + maxPx + 'px;width:' + maxPx + 'px;min-width:72px;box-sizing:border-box;overflow:hidden;text-overflow:ellipsis;vertical-align:top;');
            var thStyle = cellStyle + 'font-weight:700;';
            var mrColW = [112, 56, 56, maxPx, 48, maxPx];
            var wrapCls = 'tm-word-table-block tm-word-table-block--' + tblAlign;
            var inner = '';
            inner += '<div class="tm-word-preview-sheet tm-word-preview-sheet--' + orient + '">';
            inner += '<div class="tm-analysis-doc-inner tm-word-overlay-inner tm-word-tables-fixed tm-word-tables-align-' + tblAlign + '" style="text-align:' + align + ';--tw-table-font:' + fontPt + 'pt;--tw-cell-pad:' + padPx + 'px;--tw-cell-max:' + maxPx + 'px">';
            inner += '<h4 class="tm-analysis-doc-title" style="text-align:' + align + '">' + escapeHtml(data.doc_title || data.module_name || 'Módulo') + '</h4>';
            if (data.subtitle) inner += '<p class="tm-analysis-doc-sub" style="text-align:' + align + '">' + escapeHtml(data.subtitle) + '</p>';
            inner += '<p class="tm-analysis-doc-sub">Vista previa · ' + (orient === 'landscape' ? 'Horizontal' : 'Vertical') + '</p>';
            inner += '<div class="tm-word-preview-body-inner" style="margin-top:8px">';
            if (data.summary) {
                inner += '<p class="tm-word-prev-section">Resumen</p><div class="' + wrapCls + '"><div class="tm-word-table-scroll' + (stretch ? ' tm-word-table-scroll--stretch' : '') + '"><table class="tm-analysis-mini-table' + (stretch ? ' tm-word-table-stretch' : ' tm-word-table-fixed') + '"><colgroup><col' + (stretch ? '' : ' style="width:11em"') + '><col' + (stretch ? '' : ' style="width:14em"') + '></colgroup>';
                Object.keys(data.summary).forEach(function (k) {
                    inner += '<tr><th style="' + thStyle + (stretch ? '' : 'width:11em') + '">' + escapeHtml(k) + '</th><td style="' + cellStyle + (stretch ? '' : 'width:14em') + '">' + escapeHtml(String(data.summary[k])) + '</td></tr>';
                });
                inner += '</table></div></div>';
            }
            if (data.mr_table && data.mr_headers) {
                inner += '<p class="tm-word-prev-section">Microregiones</p><div class="' + wrapCls + '"><div class="tm-word-table-scroll' + (stretch ? ' tm-word-table-scroll--stretch' : '') + '"><table class="tm-analysis-big-table' + (stretch ? ' tm-word-table-stretch' : ' tm-word-table-fixed') + '"><thead><tr>';
                data.mr_headers.forEach(function (h, i) {
                    inner += '<th style="' + thStyle + (stretch ? '' : 'width:' + mrColW[i] + 'px') + '">' + escapeHtml(h) + '</th>';
                });
                inner += '</tr></thead><tbody>';
                data.mr_table.slice(0, 5).forEach(function (row) {
                    inner += '<tr>';
                    inner += '<td style="' + cellStyle + (stretch ? '' : 'width:' + mrColW[0] + 'px') + '">' + escapeHtml(String(row.microrregion || '').substring(0, 48)) + '</td>';
                    inner += '<td style="' + cellStyle + (stretch ? '' : 'width:' + mrColW[1] + 'px') + '">' + escapeHtml(String(row.registros)) + '</td>';
                    inner += '<td style="' + cellStyle + (stretch ? '' : 'width:' + mrColW[2] + 'px') + '">' + escapeHtml(String(row.municipios_capturados)) + '</td>';
                    inner += '<td style="' + cellStyle + (stretch ? '' : 'width:' + mrColW[3] + 'px') + '">' + escapeHtml(String(row.lista_capturados || '').substring(0, 80)) + '</td>';
                    inner += '<td style="' + cellStyle + (stretch ? '' : 'width:' + mrColW[4] + 'px') + '">' + escapeHtml(String(row.faltantes_count)) + '</td>';
                    inner += '<td style="' + cellStyle + (stretch ? '' : 'width:' + mrColW[5] + 'px') + '">' + escapeHtml(String(row.lista_faltantes || '').substring(0, 80)) + '</td>';
                    inner += '</tr>';
                });
                if (data.mr_table.length > 5) inner += '<tr><td colspan="6" style="' + cellStyle + '">… +' + (data.mr_table.length - 5) + ' filas</td></tr>';
                inner += '</tbody></table></div></div>';
            }
            if (data.dynamic_table && data.dynamic_table.headers && data.dynamic_table.headers.length) {
                var acc = data.dynamic_table.accounting_summary || [];
                if (acc.length) {
                    inner += '<p class="tm-word-prev-section">Resumen (indicadores)</p><div class="' + wrapCls + '"><div class="tm-word-table-scroll"><table class="tm-word-accounting-summary"><tr>';
                    acc.forEach(function (k) { inner += '<th>' + escapeHtml(k.label) + '</th>'; });
                    inner += '</tr><tr>';
                    acc.forEach(function (k) { inner += '<td class="tm-word-accounting-val">' + escapeHtml(String(k.value)) + '</td>'; });
                    inner += '</tr></table></div></div>';
                }
                inner += '<p class="tm-word-prev-section">Desglose por registro</p><div class="' + wrapCls + '"><div class="tm-word-dyn-scroll' + (stretch ? ' tm-word-table-scroll--stretch' : '') + '"><table class="tm-word-dyn-table' + (stretch ? ' tm-word-table-stretch' : ' tm-word-table-fixed') + '"><thead><tr>';
                data.dynamic_table.headers.forEach(function (h) { inner += '<th style="' + thStyle + '">' + escapeHtml(h) + '</th>'; });
                inner += '</tr></thead><tbody>';
                (data.dynamic_table.rows || []).slice(0, 8).forEach(function (row) {
                    inner += '<tr>';
                    row.forEach(function (cell) { inner += '<td style="' + cellStyle + '">' + escapeHtml(String(cell)) + '</td>'; });
                    inner += '</tr>';
                });
                var trow = data.dynamic_table.totals_row;
                if (trow && trow.length === data.dynamic_table.headers.length) {
                    inner += '<tr class="tm-word-totals-row">';
                    trow.forEach(function (cell) { inner += '<td style="' + thStyle + 'background:#fef2f2;color:#b91c1c">' + escapeHtml(String(cell)) + '</td>'; });
                    inner += '</tr>';
                }
                inner += '</tbody></table></div></div>';
            }
            inner += '</div></div></div>';
            el.innerHTML = inner;
            applyWordPreviewSheetOrientation(orient);
        }
        var WORD_PREVIEW_ZOOM_STEPS = [50, 75, 100, 125, 150, 175, 200];
        function applyWordPageZoom(level) {
            var pageEl = document.getElementById('tmWordPreviewPage');
            var valueEl = document.getElementById('tmWordZoomValue');
            if (!pageEl) { return; }
            var steps = WORD_PREVIEW_ZOOM_STEPS;
            var zoom = Math.min(Math.max(level, steps[0]), steps[steps.length - 1]);
            if (wordPersonalizeModal) { wordPersonalizeModal._wordPreviewZoom = zoom; }
            pageEl.style.transform = 'scale(' + (zoom / 100) + ')';
            pageEl.style.transformOrigin = 'top center';
            if (valueEl) { valueEl.textContent = zoom; }
        }
        function wordPreviewZoomStep(delta) {
            if (!wordPersonalizeModal) { return; }
            var steps = WORD_PREVIEW_ZOOM_STEPS;
            var cur = wordPersonalizeModal._wordPreviewZoom || 100;
            var idx = steps.indexOf(cur);
            if (idx < 0) { idx = steps.indexOf(100); }
            idx = Math.min(Math.max(idx + delta, 0), steps.length - 1);
            applyWordPageZoom(steps[idx]);
        }
        function setupWordPreviewZoom() {
            if (!wordPersonalizeModal) { return; }
            var steps = WORD_PREVIEW_ZOOM_STEPS;
            var out = wordPersonalizeModal.querySelector('[data-word-zoom-out]');
            var inn = wordPersonalizeModal.querySelector('[data-word-zoom-in]');
            var reset = wordPersonalizeModal.querySelector('[data-word-zoom-reset]');
            applyWordPageZoom(wordPersonalizeModal._wordPreviewZoom || 100);
            if (out) {
                out.onclick = function () {
                    var idx = steps.indexOf(wordPersonalizeModal._wordPreviewZoom || 100);
                    if (idx <= 0) { idx = 0; } else { idx -= 1; }
                    applyWordPageZoom(steps[idx]);
                };
            }
            if (inn) {
                inn.onclick = function () {
                    var idx = steps.indexOf(wordPersonalizeModal._wordPreviewZoom || 100);
                    if (idx < 0) { idx = steps.indexOf(100); }
                    if (idx >= steps.length - 1) { idx = steps.length - 1; } else { idx += 1; }
                    applyWordPageZoom(steps[idx]);
                };
            }
            if (reset) {
                reset.onclick = function () { applyWordPageZoom(100); };
            }
            var a4Area = document.getElementById('tmWordPreviewA4Area');
            if (a4Area && !a4Area._tmWordWheelZoomBound) {
                a4Area._tmWordWheelZoomBound = true;
                a4Area.addEventListener('wheel', function (e) {
                    var pinch = e.ctrlKey || e.metaKey;
                    if (!pinch) { return; }
                    e.preventDefault();
                    e.stopPropagation();
                    var dy = e.deltaY;
                    if (dy > 0) { wordPreviewZoomStep(-1); }
                    else if (dy < 0) { wordPreviewZoomStep(1); }
                }, { passive: false });
            }
        }
        function tmWordSlotKeysList() {
            return tmWordSlotKeys.filter(function (k) { return k; });
        }
        function tmWordSummaryKpiJson() {
            if (!wordPersonalizeModal) { return '[]'; }
            var kpi = wordPersonalizeModal._summaryKpiKeys;
            if (!kpi || !kpi.length) { return JSON.stringify(tmWordSlotKeysList().slice(0, 6)); }
            return JSON.stringify(kpi);
        }
        function tmWordTotalsColumnJson() {
            if (!wordPersonalizeModal) { return '[]'; }
            var t = wordPersonalizeModal._totalsColumnKeys;
            if (!t || !t.length) { return JSON.stringify(tmWordSlotKeysList()); }
            return JSON.stringify(t);
        }
        function tmWordRebuildAccountingUI(fieldsByKey) {
            var wrap = document.getElementById('tmWordAccountingFields');
            if (!wrap || !wordPersonalizeModal) { return; }
            if (!wordPersonalizeModal._summaryKpiKeys) { wordPersonalizeModal._summaryKpiKeys = tmWordSlotKeysList().slice(0, 6); }
            if (!wordPersonalizeModal._totalsColumnKeys) { wordPersonalizeModal._totalsColumnKeys = tmWordSlotKeysList().slice(); }
            var keys = tmWordSlotKeysList();
            wrap.innerHTML = '';
            if (!keys.length) {
                wrap.innerHTML = '<p class="tm-analysis-hint">Asigna columnas en las ranuras para activar KPIs y totales.</p>';
                return;
            }
            keys.forEach(function (key) {
                var f = fieldsByKey[key] || { label: key, type: 'text', canonical: 'text' };
                var row = document.createElement('div');
                row.className = 'tm-word-acc-row';
                var kpiOn = wordPersonalizeModal._summaryKpiKeys.indexOf(key) >= 0;
                var totOn = wordPersonalizeModal._totalsColumnKeys.indexOf(key) >= 0;
                var numOrBool = f.type === 'bool' || f.canonical === 'bool' || ['number', 'integer', 'float'].indexOf(f.type) >= 0;
                row.innerHTML = '<span class="tm-word-acc-label">' + escapeHtml(f.label) + '</span>' +
                    '<label class="tm-analysis-check tm-word-acc-kpi"><input type="checkbox" data-acc-kpi="' + escapeHtml(key) + '"' + (kpiOn ? ' checked' : '') + '> KPI resumen</label>' +
                    '<label class="tm-analysis-check tm-word-acc-tot"' + (numOrBool ? '' : ' title="Solo numéricos o Sí/No"') + '><input type="checkbox" data-acc-total="' + escapeHtml(key) + '"' + (totOn && numOrBool ? ' checked' : '') + (numOrBool ? '' : ' disabled') + '> Total pie</label>';
                wrap.appendChild(row);
            });
            wrap.querySelectorAll('[data-acc-kpi]').forEach(function (inp) {
                inp.addEventListener('change', function () {
                    var k = inp.getAttribute('data-acc-kpi');
                    var a = wordPersonalizeModal._summaryKpiKeys;
                    if (inp.checked && a.indexOf(k) < 0) { a.push(k); }
                    if (!inp.checked) { wordPersonalizeModal._summaryKpiKeys = a.filter(function (x) { return x !== k; }); }
                    loadWordPersonalizePreview();
                });
            });
            wrap.querySelectorAll('[data-acc-total]').forEach(function (inp) {
                if (inp.disabled) { return; }
                inp.addEventListener('change', function () {
                    var k = inp.getAttribute('data-acc-total');
                    var a = wordPersonalizeModal._totalsColumnKeys;
                    if (inp.checked && a.indexOf(k) < 0) { a.push(k); }
                    if (!inp.checked) { wordPersonalizeModal._totalsColumnKeys = a.filter(function (x) { return x !== k; }); }
                    loadWordPersonalizePreview();
                });
            });
        }
        function loadWordPersonalizePreview() {
            var baseUrl = window._tmAnalysisPreviewUrl;
            if (!baseUrl || !wordPersonalizePreview) return;
            var title = document.getElementById('tmWordDocTitle');
            var sub = document.getElementById('tmWordSubtitle');
            var alignBtn = document.querySelector('#tmAnalysisWordPersonalizeModal .tm-export-align-btn.is-active');
            var align = alignBtn ? alignBtn.getAttribute('data-word-title-align') : 'center';
            var orientBtn = document.querySelector('#tmAnalysisWordPersonalizeModal .tm-word-orient-btn.is-active');
            var orient = orientBtn ? orientBtn.getAttribute('data-word-orient') : 'portrait';
            var sum = document.getElementById('tmWordIncludeSummary');
            var mr = document.getElementById('tmWordIncludeMrTable');
            var q = baseUrl.indexOf('?') === -1 ? '?' : '&';
            var u = baseUrl + q + 'include_summary=' + (sum && sum.checked ? '1' : '0')
                + '&include_mr_table=' + (mr && mr.checked ? '1' : '0')
                + '&doc_title=' + encodeURIComponent(title ? title.value : '')
                + '&title_align=' + encodeURIComponent(align)
                + '&subtitle=' + encodeURIComponent(sub ? sub.value : '')
                + '&orientation=' + encodeURIComponent(orient)
                + '&column_keys=' + encodeURIComponent(tmWordColumnKeysJson())
                + '&table_font_pt=' + encodeURIComponent(document.getElementById('tmWordTableFontPt') ? document.getElementById('tmWordTableFontPt').value : '9')
                + '&table_cell_pad=' + encodeURIComponent(document.getElementById('tmWordTableCellPad') ? document.getElementById('tmWordTableCellPad').value : '6')
                + '&table_cell_max_px=' + encodeURIComponent(document.getElementById('tmWordTableCellMax') ? document.getElementById('tmWordTableCellMax').value : '140')
                + '&include_dynamic_table=' + (document.getElementById('tmWordIncludeDynamic') && document.getElementById('tmWordIncludeDynamic').checked ? '1' : '0')
                + '&table_align=' + encodeURIComponent((document.querySelector('#tmAnalysisWordPersonalizeModal .tm-word-table-align-btns .tm-export-align-btn.is-active') || {}).getAttribute ? (document.querySelector('#tmAnalysisWordPersonalizeModal .tm-word-table-align-btns .tm-export-align-btn.is-active').getAttribute('data-word-table-align') || 'left') : 'left')
                + '&summary_kpi_keys=' + encodeURIComponent(tmWordSummaryKpiJson())
                + '&totals_column_keys=' + encodeURIComponent(tmWordTotalsColumnJson())
                + '&_=' + Date.now();
            applyWordPreviewSheetOrientation(orient);
            wordPersonalizePreview.innerHTML = '<div class="tm-analysis-preview-loading">Actualizando…</div>';
            fetch(u, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (title && !title.value.trim() && data.module_name) {
                        title.value = 'Análisis general — ' + data.module_name;
                    }
                    var fields = data.exportable_fields || [];
                    var byKey = {};
                    fields.forEach(function (f) { byKey[f.key] = f; });
                    tmWordRebuildPalette(fields);
                    tmWordRebuildSlots(data.reference_row || {}, byKey);
                    tmWordRebuildAccountingUI(byKey);
                    renderWordPreviewInto(wordPersonalizePreview, data);
                })
                .catch(function () { wordPersonalizePreview.innerHTML = '<p class="tm-analysis-err">Error vista previa</p>'; });
        }
        function openWordPersonalizeModal() {
            if (!wordPersonalizeModal || !wordPersonalizeForm) return;
            wordPersonalizeForm.setAttribute('action', window._tmAnalysisWordUrl || '');
            var s = document.getElementById('tmAnalysisIncludeSummary');
            var m = document.getElementById('tmAnalysisIncludeMrTable');
            if (document.getElementById('tmWordIncludeSummary')) document.getElementById('tmWordIncludeSummary').checked = s ? s.checked : true;
            if (document.getElementById('tmWordIncludeMrTable')) document.getElementById('tmWordIncludeMrTable').checked = m ? m.checked : true;
            if (document.getElementById('tmWordIncludeDynamic') && !document.getElementById('tmWordIncludeDynamic').dataset.touched) document.getElementById('tmWordIncludeDynamic').checked = true;
            var titleIn = document.getElementById('tmWordDocTitle');
            if (titleIn && !titleIn.value.trim()) titleIn.value = '';
            wordPersonalizeModal.classList.add('is-open');
            wordPersonalizeModal.setAttribute('aria-hidden', 'false');
            wordPersonalizeModal._wordPreviewZoom = 100;
            wordPersonalizeModal._summaryKpiKeys = null;
            wordPersonalizeModal._totalsColumnKeys = null;
            setupWordPreviewZoom();
            loadWordPersonalizePreview();
        }
        function closeWordPersonalizeModal() {
            if (!wordPersonalizeModal) return;
            wordPersonalizeModal.classList.remove('is-open');
            wordPersonalizeModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
        document.getElementById('tmAnalysisOpenPersonalize') && document.getElementById('tmAnalysisOpenPersonalize').addEventListener('click', openWordPersonalizeModal);
        if (wordPersonalizeModal) {
            wordPersonalizeModal.querySelectorAll('[data-close-analysis-word-personalize]').forEach(function (el) {
                el.addEventListener('click', closeWordPersonalizeModal);
            });
            document.querySelectorAll('[data-word-title-align]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('[data-word-title-align]').forEach(function (b) { b.classList.remove('is-active'); });
                    btn.classList.add('is-active');
                });
            });
            wordPersonalizeModal.querySelectorAll('[data-word-orient]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    wordPersonalizeModal.querySelectorAll('[data-word-orient]').forEach(function (b) { b.classList.remove('is-active'); });
                    btn.classList.add('is-active');
                    var o = btn.getAttribute('data-word-orient') || 'portrait';
                    applyWordPreviewSheetOrientation(o);
                    loadWordPersonalizePreview();
                });
            });
            document.getElementById('tmWordRefreshPreviewBtn') && document.getElementById('tmWordRefreshPreviewBtn').addEventListener('click', loadWordPersonalizePreview);
            wordPersonalizeForm && wordPersonalizeForm.addEventListener('submit', function () {
                document.getElementById('tmWordFormSummary').value = document.getElementById('tmWordIncludeSummary').checked ? '1' : '0';
                document.getElementById('tmWordFormMrTable').value = document.getElementById('tmWordIncludeMrTable').checked ? '1' : '0';
                document.getElementById('tmWordFormDynamic').value = document.getElementById('tmWordIncludeDynamic') && document.getElementById('tmWordIncludeDynamic').checked ? '1' : '0';
                document.getElementById('tmWordFormDocTitle').value = document.getElementById('tmWordDocTitle').value || '';
                var ab = document.querySelector('#tmAnalysisWordPersonalizeModal .tm-export-title-align .tm-export-align-btn.is-active');
                document.getElementById('tmWordFormTitleAlign').value = ab ? ab.getAttribute('data-word-title-align') : 'center';
                var tab = document.querySelector('#tmAnalysisWordPersonalizeModal .tm-word-table-align-btns .tm-export-align-btn.is-active');
                document.getElementById('tmWordFormTableAlign').value = tab ? tab.getAttribute('data-word-table-align') : 'left';
                document.getElementById('tmWordFormSubtitle').value = document.getElementById('tmWordSubtitle').value || '';
                var ob = document.querySelector('#tmAnalysisWordPersonalizeModal .tm-word-orient-btn.is-active');
                document.getElementById('tmWordFormOrientation').value = ob ? ob.getAttribute('data-word-orient') : 'portrait';
                document.getElementById('tmWordFormColumnKeys').value = tmWordColumnKeysJson();
                document.getElementById('tmWordFormTableFontPt').value = document.getElementById('tmWordTableFontPt') ? document.getElementById('tmWordTableFontPt').value : '9';
                document.getElementById('tmWordFormTableCellPad').value = document.getElementById('tmWordTableCellPad') ? document.getElementById('tmWordTableCellPad').value : '6';
                document.getElementById('tmWordFormTableCellMax').value = document.getElementById('tmWordTableCellMax') ? document.getElementById('tmWordTableCellMax').value : '140';
                document.getElementById('tmWordFormSummaryKpiKeys').value = tmWordSummaryKpiJson();
                document.getElementById('tmWordFormTotalsColumnKeys').value = tmWordTotalsColumnJson();
            });
            ['tmWordTableFontPt', 'tmWordTableCellPad', 'tmWordTableCellMax'].forEach(function (id) {
                var n = document.getElementById(id);
                if (n) {
                    n.addEventListener('change', function () { loadWordPersonalizePreview(); });
                    if (id === 'tmWordTableCellMax') n.addEventListener('input', function () { clearTimeout(n._tmDeb); n._tmDeb = setTimeout(loadWordPersonalizePreview, 400); });
                }
            });
            wordPersonalizeModal.querySelectorAll('.tm-word-table-align-btns [data-word-table-align]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    wordPersonalizeModal.querySelectorAll('.tm-word-table-align-btns [data-word-table-align]').forEach(function (b) { b.classList.remove('is-active'); });
                    btn.classList.add('is-active');
                    loadWordPersonalizePreview();
                });
            });
            ['tmWordIncludeSummary', 'tmWordIncludeMrTable', 'tmWordIncludeDynamic'].forEach(function (id) {
                var c = document.getElementById(id);
                if (c) {
                    c.addEventListener('change', function () { if (id === 'tmWordIncludeDynamic') c.dataset.touched = '1'; loadWordPersonalizePreview(); });
                }
            });
        }
        function escapeHtml(s) {
            if (!s) return '';
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }
        if (analysisModal) {
            analysisModal.querySelectorAll('[data-close-analysis-word]').forEach(function (el) {
                el.addEventListener('click', closeAnalysisWordModal);
            });
            var refPrev = function () { if (window._tmAnalysisPreviewUrl) loadAnalysisPreview(window._tmAnalysisPreviewUrl); };
            document.getElementById('tmAnalysisRefreshPreview') && document.getElementById('tmAnalysisRefreshPreview').addEventListener('click', refPrev);
            document.getElementById('tmAnalysisBuildGridBtn') && document.getElementById('tmAnalysisBuildGridBtn').addEventListener('click', refPrev);
            analysisForm && analysisForm.addEventListener('submit', function () {
                document.getElementById('tmAnalysisFormSummary').value = document.getElementById('tmAnalysisIncludeSummary').checked ? '1' : '0';
                document.getElementById('tmAnalysisFormMrTable').value = document.getElementById('tmAnalysisIncludeMrTable').checked ? '1' : '0';
                document.getElementById('tmAnalysisFormRows').value = document.getElementById('tmAnalysisCustomRows').value || '0';
                document.getElementById('tmAnalysisFormCols').value = document.getElementById('tmAnalysisCustomCols').value || '0';
            });
        }

        const personalizeModal = document.getElementById('tmExportPersonalizeModal');
        const imageModal = document.getElementById('tmImagePreviewModal');
        const imageModalImg = document.getElementById('tmImagePreviewImg');
        const imageModalTitle = document.getElementById('tmImagePreviewTitle');
        let lastImageOpener = null;
        const templateSwal = createTemplateSwal();

        const closeModal = function (modal) {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };

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
            if (imageModalImg) {
                imageModalImg.removeAttribute('src');
            }

            const hasAnyModuleModalOpen = Array.from(document.querySelectorAll('.tm-modal.is-open'))
                .some(function (modal) { return modal !== imageModal; });
            if (!hasAnyModuleModalOpen) {
                document.body.style.overflow = '';
            }

            if (lastImageOpener instanceof HTMLElement) {
                lastImageOpener.focus();
            }
        };

        openButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const modalId = button.getAttribute('data-open-module-preview');
                const modal = modalId ? document.getElementById(modalId) : null;
                if (!modal) {
                    return;
                }

                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            });
        });

        textToggleButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const wrap = button.closest('[data-text-wrap]');
                const content = wrap ? wrap.querySelector('[data-text-content]') : null;
                if (!(content instanceof HTMLElement)) {
                    return;
                }

                const isCollapsed = content.classList.contains('is-collapsed');
                content.classList.toggle('is-collapsed', !isCollapsed);
                button.textContent = isCollapsed ? 'Ver menos' : 'Ver mas';
            });
        });

        cellExpandButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const colIndex = parseInt(button.getAttribute('data-col-index') || '', 10);
                const row = button.closest('tr');
                if (!row || Number.isNaN(colIndex)) {
                    return;
                }

                const targetCell = row.querySelector('td[data-admin-col="' + String(colIndex) + '"]');
                if (!(targetCell instanceof HTMLTableCellElement)) {
                    return;
                }

                const wasExpanded = targetCell.classList.contains('is-expanded-cell');
                const cells = Array.from(row.querySelectorAll('td[data-admin-col]'));

                cells.forEach(function (cell) {
                    cell.classList.remove('is-expanded-cell', 'is-condensed-cell');
                });

                if (!wasExpanded) {
                    targetCell.classList.add('is-expanded-cell');
                    cells.forEach(function (cell) {
                        if (cell !== targetCell) {
                            cell.classList.add('is-condensed-cell');
                        }
                    });
                }
            });
        });

        clearButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const formId = button.getAttribute('data-form-id');
                const moduleName = button.getAttribute('data-module-name') || 'este módulo';
                const form = formId ? document.getElementById(formId) : null;
                if (!form) {
                    return;
                }

                const submitAction = function () {
                    form.submit();
                };

                if (!templateSwal) {
                    submitAction();
                    return;
                }

                templateSwal.fire({
                    title: '¿Vaciar registros de ' + moduleName + '?',
                    text: 'Se eliminarán todos los registros capturados de "' + moduleName + '". Esta acción no se puede deshacer.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, vaciar',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true
                }).then(function (result) {
                    if (result.isConfirmed) {
                        submitAction();
                    }
                });
            });
        });

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

        Array.from(document.querySelectorAll('.tm-modal')).forEach(function (modal) {
            Array.from(modal.querySelectorAll('[data-close-module-preview]')).forEach(function (button) {
                button.addEventListener('click', function () {
                    closeModal(modal);
                });
            });
        });

        Array.from(document.querySelectorAll('[data-close-image-preview]')).forEach(function (button) {
            button.addEventListener('click', closeImageModal);
        });

        const TEMPLATE_COLORS = [
            { name: 'Primario', value: 'var(--clr-primary)' },
            { name: 'Secundario', value: 'var(--clr-secondary)' },
            { name: 'Acento', value: 'var(--clr-accent)' },
            { name: 'Texto principal', value: 'var(--clr-text-main)' },
            { name: 'Texto claro', value: 'var(--clr-text-light)' },
            { name: 'Fondo', value: 'var(--clr-bg)' },
            { name: 'Tarjeta', value: 'var(--clr-card)' }
        ];

        const closePersonalizeModal = function () {
            if (!personalizeModal) { return; }
            personalizeModal.classList.remove('is-open');
            personalizeModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };

        Array.from(document.querySelectorAll('[data-close-export-personalize]')).forEach(function (el) {
            el.addEventListener('click', closePersonalizeModal);
        });

        if (personalizeModal) {
            personalizeModal.addEventListener('click', function (e) {
                var alignBtn = e.target.closest('.tm-export-align-btn');
                if (alignBtn) {
                    var cols = personalizeModal._personalizeColumns;
                    if (!cols) { return; }
                    personalizeModal.querySelectorAll('.tm-export-align-btn').forEach(function (b) { b.classList.remove('is-active'); });
                    alignBtn.classList.add('is-active');
                    var columnsEl = document.getElementById('tmExportPersonalizeColumns');
                    var previewEl = document.getElementById('tmExportPersonalizePreview');
                    if (columnsEl && previewEl) { buildPersonalizePreview(reorderColumnsList(columnsEl, cols), previewEl); }
                    return;
                }

                var orientBtn = e.target.closest('.tm-export-orient-btn');
                if (orientBtn) {
                    var orientation = orientBtn.getAttribute('data-orientation') || 'portrait';
                    personalizeModal.querySelectorAll('.tm-export-orient-btn').forEach(function (b) { b.classList.remove('is-active'); });
                    orientBtn.classList.add('is-active');
                    var page = document.getElementById('tmExportPreviewPage');
                    if (page) {
                        page.classList.toggle('is-landscape', orientation === 'landscape');
                    }
                }
            });
        }

        function buildPersonalizeColumnsList(columns, container) {
            container.innerHTML = '';
            var colorMenuHtml = TEMPLATE_COLORS.map(function (c, i) {
                return '<button type="button" class="tm-export-color-option' + (i === 0 ? ' is-active' : '') + '" data-color="' + escapeHtml(c.value) + '">' +
                    '<span class="tm-export-color-swatch" style="background-color:' + escapeHtml(c.value) + '"></span>' +
                    '<span class="tm-export-color-name">' + escapeHtml(c.name) + '</span></button>';
            }).join('');
            columns.forEach(function (col, index) {
                var item = document.createElement('div');
                item.className = 'tm-export-personalize-col' + (col.is_image ? ' is-image' : '');
                item.setAttribute('role', 'listitem');
                item.dataset.key = col.key;
                item.dataset.index = String(index);
                item.draggable = true;
                var mid = '';
                if (col.is_image) {
                    mid = '<div class="tm-export-col-image-opts">' +
                        '<label>Ancho <input type="number" min="40" max="400" value="120" class="tm-export-image-width" data-key="' + escapeHtml(col.key) + '"></label>' +
                        '<label>Alto <input type="number" min="30" max="300" value="' + String(col.image_height || 80) + '" class="tm-export-image-height" data-key="' + escapeHtml(col.key) + '"></label></div>';
                } else {
                    var approx = col.max_width_chars || 24;
                    if (!Number.isFinite(approx)) { approx = 24; }
                    approx = Math.max(2, Math.min(approx, 60));
                    mid = '<label class="tm-export-col-width-preview" data-width-hint="' + escapeHtml(String(approx)) + '">' +
                        '<span>Ancho (ch)</span>' +
                        '<input type="number" class="tm-export-col-width-input" min="2" max="60" value="' + String(approx) + '" data-key="' + escapeHtml(col.key) + '">' +
                        '</label>';
                }
                item.innerHTML =
                    '<span class="tm-export-drag-handle" aria-hidden="true">&#9776;</span>' +
                    '<span class="tm-export-col-label">' + escapeHtml(col.label) + '</span>' +
                    '<div class="tm-export-col-color">' +
                    '<button type="button" class="tm-export-color-trigger" data-color="' + escapeHtml(TEMPLATE_COLORS[0].value) + '" aria-haspopup="listbox" aria-expanded="false">' +
                    '<span class="tm-export-color-swatch" style="background-color:' + escapeHtml(TEMPLATE_COLORS[0].value) + '"></span></button>' +
                    '<div class="tm-export-color-menu" role="listbox" hidden>' + colorMenuHtml + '</div></div>' +
                    mid +
                    '<button type="button" class="tm-export-omit-btn" title="Omitir en el reporte" aria-label="Omitir">&times;</button>';
                container.appendChild(item);
            });
        }

        function buildCountTableColorList(container, countByFieldsEl, previewEntries) {
            if (!container) { return; }
            var savedColors = {};
            container.querySelectorAll('.tm-export-count-table-color-item').forEach(function (row) {
                var k = row.getAttribute('data-key');
                if (!k) { return; }
                var t1 = row.querySelector('.tm-export-color-trigger[data-row="1"]');
                var t2 = row.querySelector('.tm-export-color-trigger[data-row="2"]');
                var pctCheck = row.querySelector('.tm-export-count-pct-check');
                savedColors[k] = {
                    row1: (t1 && t1.getAttribute('data-color')) ? t1.getAttribute('data-color') : 'var(--clr-primary)',
                    row2: (t2 && t2.getAttribute('data-color')) ? t2.getAttribute('data-color') : 'var(--clr-secondary)',
                    showPct: !!(pctCheck && pctCheck.checked),
                    row2Values: {}
                };
                row.querySelectorAll('.tm-export-count-table-value-color').forEach(function (vrow) {
                    var v = vrow.getAttribute('data-value');
                    var vt = vrow.querySelector('.tm-export-color-trigger');
                    if (v && vt) { savedColors[k].row2Values[v] = vt.getAttribute('data-color') || 'var(--clr-secondary)'; }
                });
            });
            var colorMenuHtml = TEMPLATE_COLORS.map(function (c, i) {
                return '<button type="button" class="tm-export-color-option' + (i === 0 ? ' is-active' : '') + '" data-color="' + escapeHtml(c.value) + '">' +
                    '<span class="tm-export-color-swatch" style="background-color:' + escapeHtml(c.value) + '"></span>' +
                    '<span class="tm-export-color-name">' + escapeHtml(c.name) + '</span></button>';
            }).join('');
            var defaultRow1 = TEMPLATE_COLORS[0].value;
            var defaultRow2 = TEMPLATE_COLORS[1].value;
            var oneColorBlock = function (key, label, colors, valueLabels) {
                var c1 = (colors && colors.row1) ? colors.row1 : defaultRow1;
                var c2 = (colors && colors.row2) ? colors.row2 : defaultRow2;
                var showPct = !!(colors && colors.showPct);
                var row2Values = (colors && colors.row2Values) ? colors.row2Values : {};
                var block = '<span class="tm-export-col-label">' + escapeHtml(label) + '</span>' +
                    '<div class="tm-export-count-table-two-colors">' +
                    '<div class="tm-export-col-color" title="Fila 1: títulos de grupo">' +
                    '<span class="tm-export-color-row-label">Fila 1</span>' +
                    '<button type="button" class="tm-export-color-trigger" data-row="1" data-color="' + escapeHtml(c1) + '" aria-haspopup="listbox" aria-expanded="false">' +
                    '<span class="tm-export-color-swatch" style="background-color:' + escapeHtml(c1) + '"></span></button>' +
                    '<div class="tm-export-color-menu" role="listbox" hidden>' + colorMenuHtml + '</div></div>' +
                    '<div class="tm-export-col-color" title="Fila 2: subtítulos (valor por defecto)">' +
                    '<span class="tm-export-color-row-label">Fila 2</span>' +
                    '<button type="button" class="tm-export-color-trigger" data-row="2" data-color="' + escapeHtml(c2) + '" aria-haspopup="listbox" aria-expanded="false">' +
                    '<span class="tm-export-color-swatch" style="background-color:' + escapeHtml(c2) + '"></span></button>' +
                    '<div class="tm-export-color-menu" role="listbox" hidden>' + colorMenuHtml + '</div></div>' +
                    '<label class="tm-export-count-pct-item-check" title="Incluir columna de porcentaje (%) para este campo" style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:0.75rem;color:var(--clr-text-main);margin-left:auto;">' +
                    '<input type="checkbox" class="tm-export-count-pct-check"' + (showPct ? ' checked' : '') + '> %' +
                    '</label>' +
                    '</div>';
                if (valueLabels && valueLabels.length > 0) {
                    block += '<div class="tm-export-count-table-row2-values"><span class="tm-export-color-row-label">Fila 2 por valor:</span><div class="tm-export-count-table-value-colors">';
                    valueLabels.forEach(function (vlabel) {
                        var vc = row2Values[vlabel] || defaultRow2;
                        block += '<div class="tm-export-count-table-value-color" data-value="' + escapeHtml(vlabel) + '">' +
                            '<span class="tm-export-value-label">' + escapeHtml(vlabel) + '</span>' +
                            '<button type="button" class="tm-export-color-trigger" data-color="' + escapeHtml(vc) + '" aria-haspopup="listbox" aria-expanded="false">' +
                            '<span class="tm-export-color-swatch" style="background-color:' + escapeHtml(vc) + '"></span></button>' +
                            '<div class="tm-export-color-menu" role="listbox" hidden>' + colorMenuHtml + '</div></div>';
                    });
                    block += '</div></div>';
                }
                return block;
            };
            var getValueLabelsForField = function (key, entries) {
                var seen = {};
                var list = [];
                if (!entries || !entries.length) { return list; }
                entries.forEach(function (e) {
                    var v = (e.data && e.data[key]) !== undefined ? e.data[key] : null;
                    var label = (typeof v === 'boolean') ? (v ? 'Sí' : 'No') : (v != null ? String(v).trim() : '');
                    if (label !== '') {
                        var lower = label.toLowerCase();
                        if (!seen[lower]) { seen[lower] = label; list.push(label); }
                    }
                });
                list.sort(function (a, b) { return a.localeCompare(b, undefined, { sensitivity: 'base' }); });
                return list;
            };
            container.innerHTML = '';
            var totalRow = document.createElement('div');
            totalRow.className = 'tm-export-count-table-color-item tm-export-personalize-col';
            totalRow.setAttribute('role', 'listitem');
            totalRow.dataset.key = '_total';
            totalRow.innerHTML = oneColorBlock('_total', 'Total de registros', savedColors['_total'], null);
            container.appendChild(totalRow);
            if (countByFieldsEl) {
                countByFieldsEl.querySelectorAll('input[type="checkbox"]:checked').forEach(function (cb) {
                    var key = cb.getAttribute('data-count-key') || cb.value;
                    var labelEl = cb.closest('label');
                    var label = (labelEl && labelEl.textContent) ? labelEl.textContent.replace(/^\s+|\s+$/g, '') : key;
                    if (!key) { return; }
                    var valueLabels = getValueLabelsForField(key, previewEntries);
                    var row = document.createElement('div');
                    row.className = 'tm-export-count-table-color-item tm-export-personalize-col';
                    row.setAttribute('role', 'listitem');
                    row.dataset.key = key;
                    row.innerHTML = oneColorBlock(key, label, savedColors[key], valueLabels);
                    container.appendChild(row);
                });
            }
        }

        function getPersonalizeState() {
            var container = document.getElementById('tmExportPersonalizeColumns');
            if (!container) {
                return { title: '', titleAlign: 'center', columns: [], sampleRow: {} };
            }
            const titleEl = document.getElementById('tmExportPersonalizeTitle');
            const alignBtn = document.querySelector('.tm-export-align-btn.is-active');
            const titleAlign = (alignBtn && alignBtn.getAttribute('data-title-align')) || 'center';
            const items = Array.from(container.querySelectorAll('.tm-export-personalize-col'));
            const columns = items.map(function (item) {
                const key = item.dataset.key || '';
                const colorTrigger = item.querySelector('.tm-export-color-trigger');
                const color = colorTrigger ? (colorTrigger.getAttribute('data-color') || 'var(--clr-primary)') : 'var(--clr-primary)';
                let imageWidth = 120, imageHeight = 80;
                const w = item.querySelector('.tm-export-image-width');
                const h = item.querySelector('.tm-export-image-height');
                if (w && h) {
                    imageWidth = parseInt(w.value, 10) || 120;
                    imageHeight = parseInt(h.value, 10) || 80;
                }
                return { key, color, imageWidth, imageHeight };
            });
            var countTableColors = {};
            var countColorList = document.getElementById('tmExportCountTableColorList');
            if (countColorList) {
                countColorList.querySelectorAll('.tm-export-count-table-color-item').forEach(function (row) {
                    var k = row.getAttribute('data-key');
                    if (!k) { return; }
                    var t1 = row.querySelector('.tm-export-color-trigger[data-row="1"]');
                    var t2 = row.querySelector('.tm-export-color-trigger[data-row="2"]');
                    var obj = {
                        row1: (t1 && t1.getAttribute('data-color')) ? t1.getAttribute('data-color') : 'var(--clr-primary)',
                        row2: (t2 && t2.getAttribute('data-color')) ? t2.getAttribute('data-color') : 'var(--clr-secondary)'
                    };
                    var row2Values = {};
                    row.querySelectorAll('.tm-export-count-table-value-color').forEach(function (vrow) {
                        var v = vrow.getAttribute('data-value');
                        var vt = vrow.querySelector('.tm-export-color-trigger');
                        if (v && vt) { row2Values[v] = vt.getAttribute('data-color') || 'var(--clr-secondary)'; }
                    });
                    if (Object.keys(row2Values).length) { obj.row2Values = row2Values; }
                    var pctCheck = row.querySelector('.tm-export-count-pct-check');
                    obj.showPct = !!(pctCheck && pctCheck.checked);
                    countTableColors[k] = obj;
                });
            }
            var countTableCellWidthEl = document.getElementById('tmExportCountTableCellWidth');
            var countTableCellWidth = (countTableCellWidthEl && countTableCellWidthEl.value) ? (parseInt(countTableCellWidthEl.value, 10) || 12) : 12;
            return { title: titleEl ? titleEl.value : '', titleAlign: titleAlign, columns: columns, countTableColors: countTableColors, countTableCellWidth: countTableCellWidth };
        }

        function readSampleRowFromPreview(previewEl) {
            const sample = {};
            if (!previewEl) { return sample; }
            const cells = previewEl.querySelectorAll('.tm-export-preview-data-cell[data-key]');
            cells.forEach(function (cell) {
                const key = cell.getAttribute('data-key');
                if (key) { sample[key] = (cell.textContent || '').trim(); }
            });
            return sample;
        }

        function formatPreviewCellValue(val) {
            if (val === null || val === undefined) { return ''; }
            if (typeof val === 'boolean') { return val ? 'Sí' : 'No'; }
            if (Array.isArray(val)) { return val.map(function (v) { return typeof v === 'object' ? JSON.stringify(v) : String(v); }).join(', '); }
            return String(val);
        }

        function buildPersonalizePreview(columns, previewEl, sampleRow, previewEntries, microrregionMeta) {
            if (!previewEl) { return; }
            var modal = previewEl.closest && previewEl.closest('.tm-modal');
            var entries = previewEntries || (modal && modal._previewEntries);
            var meta = microrregionMeta || (modal && modal._previewMicrorregionMeta) || {};
            const savedRow = sampleRow || (entries && entries.length ? null : readSampleRowFromPreview(previewEl));
            const state = getPersonalizeState();
            const colorMap = {};
            state.columns.forEach(function (c) { colorMap[c.key] = c.color; });
            const titleAlign = state.titleAlign || 'center';
            const titleStyle = 'text-align:' + (titleAlign === 'left' ? 'left' : titleAlign === 'right' ? 'right' : 'center');
            var countTableHtml = '';
            var root = modal || document;
            var includeCountEl = root.querySelector ? root.querySelector('#tmExportIncludeCountTable') : document.getElementById('tmExportIncludeCountTable');
            var includePercentagesEl = root.querySelector ? root.querySelector('#tmExportIncludePercentages') : document.getElementById('tmExportIncludePercentages');
            var countByFieldsEl = root.querySelector ? root.querySelector('#tmExportCountByFields') : document.getElementById('tmExportCountByFields');
            if (includeCountEl && includeCountEl.checked && countByFieldsEl) {
                var totalCount = Array.isArray(entries) ? entries.length : 0;
                var groups = [{ label: 'Total de registros', values: [{ label: '', count: totalCount }] }];
                countByFieldsEl.querySelectorAll('input[type="checkbox"]:checked').forEach(function (cb) {
                    var key = cb.getAttribute('data-count-key') || cb.value;
                    if (!key) { return; }
                    var labelEl = cb.closest('label');
                    var fieldLabel = (labelEl && labelEl.textContent) ? labelEl.textContent.replace(/^\s+|\s+$/g, '') : key;
                    var byVal = {};
                    var labelByLower = {};
                    if (Array.isArray(entries)) {
                        entries.forEach(function (e) {
                            var v = (e.data && e.data[key]) !== undefined ? e.data[key] : null;
                            var k = (typeof v === 'boolean') ? (v ? 'Sí' : 'No') : (v != null ? String(v) : '');
                            if (k !== '') {
                                var lower = k.toLowerCase();
                                byVal[lower] = (byVal[lower] || 0) + 1;
                                if (!labelByLower[lower]) { labelByLower[lower] = k; }
                            }
                        });
                    }
                    var values = [];
                    Object.keys(byVal).sort().forEach(function (lower) {
                        values.push({ label: labelByLower[lower] || lower, count: byVal[lower] });
                    });
                    if (values.length) { groups.push({ label: fieldLabel, values: values }); }
                });
                if (groups.length > 0) {
                    var countTableKeys = [];
                    if (root.querySelector) {
                        var colorListEl = root.querySelector('#tmExportCountTableColorList');
                        if (colorListEl) {
                            colorListEl.querySelectorAll('.tm-export-count-table-color-item').forEach(function (r) {
                                var k = r.getAttribute('data-key');
                                if (k) { countTableKeys.push(k); }
                            });
                        }
                    }
                    var countTableColors = state.countTableColors || {};
                    var getCountColor = function (groupIndex, rowNum, valueLabel) {
                        var key = countTableKeys[groupIndex];
                        if (!key) { return rowNum === 1 ? '#861e34' : '#2d5a27'; }
                        var c = countTableColors[key];
                        if (typeof c === 'string') { return c; }
                        if (rowNum === 1) { return (c && c.row1) ? c.row1 : '#861e34'; }
                        if (valueLabel && c && c.row2Values && (c.row2Values[valueLabel] || c.row2Values[valueLabel.toLowerCase()])) {
                            return c.row2Values[valueLabel] || c.row2Values[valueLabel.toLowerCase()];
                        }
                        return (c && c.row2) ? c.row2 : '#2d5a27';
                    };

                    const cellW = state.countTableCellWidth || 12;
                    const pctCellW = Math.max(6, Math.floor(cellW * 0.7));

                    countTableHtml = '<table class="tm-export-preview-count-table" style="table-layout:fixed;width:auto;">';
                    countTableHtml += '<thead><tr>';
                    groups.forEach(function (g, gi) {
                        var bg = getCountColor(gi, 1);
                        var key = countTableKeys[gi] || (gi === 0 ? '_total' : '');
                        var groupCfg = countTableColors[key] || {};
                        var showPct = !!groupCfg.showPct;

                        var isRedundant = (g.values.length === 1 && (String(g.values[0].label).trim() === '' || String(g.values[0].label).trim() === String(g.label).trim())) || key === '_total';
                        var rs = (isRedundant && !showPct) ? ' rowspan="2"' : '';
                        var cs = showPct ? g.values.length * 2 : g.values.length;
                        countTableHtml += '<th class="tm-export-preview-count-group-header" ' + rs + ' colspan="' + cs + '" style="background-color:' + escapeHtml(bg) + '">' + escapeHtml(g.label) + '</th>';
                    });
                    countTableHtml += '</tr><tr>';
                    groups.forEach(function (g, gi) {
                        var key = countTableKeys[gi] || (gi === 0 ? '_total' : '');
                        var groupCfg = countTableColors[key] || {};
                        var showPct = !!groupCfg.showPct;

                        var isRedundant = (g.values.length === 1 && (String(g.values[0].label).trim() === '' || String(g.values[0].label).trim() === String(g.label).trim())) || key === '_total';
                        if (isRedundant && !showPct) {
                            return; // Saltar esta columna en la segunda fila si se combinó verticalmente y NO hay porcentajes
                        }
                        g.values.forEach(function (v) {
                            var subLabel = v.label !== '' ? v.label : g.label;
                            var bg = getCountColor(gi, 2, subLabel);
                            if (showPct && isRedundant) {
                                countTableHtml += '<th class="tm-export-preview-count-value-header" style="background-color:' + escapeHtml(bg) + ';width:' + cellW + 'ch;">Cantidad</th>';
                                countTableHtml += '<th class="tm-export-preview-count-value-header" style="background-color:' + escapeHtml(bg) + ';width:' + pctCellW + 'ch;font-size:0.75rem;">%</th>';
                            } else {
                                countTableHtml += '<th class="tm-export-preview-count-value-header" style="background-color:' + escapeHtml(bg) + ';width:' + cellW + 'ch;min-width:' + cellW + 'ch;max-width:' + cellW + 'ch;overflow:hidden;text-overflow:ellipsis;">' + escapeHtml(subLabel) + '</th>';
                                if (showPct) {
                                    countTableHtml += '<th class="tm-export-preview-count-value-header" style="background-color:' + escapeHtml(bg) + ';width:' + pctCellW + 'ch;font-size:0.75rem;">%</th>';
                                }
                            }
                        });
                    });
                    countTableHtml += '</tr></thead><tbody><tr>';
                    groups.forEach(function (g, gi) {
                        var key = countTableKeys[gi] || (gi === 0 ? '_total' : '');
                        var groupCfg = countTableColors[key] || {};
                        var showPct = !!groupCfg.showPct;

                        var gTotal = 0;
                        g.values.forEach(function(ev) { gTotal += ev.count; });
                        g.values.forEach(function (v) {
                            countTableHtml += '<td class="tm-export-preview-count-value" style="width:' + cellW + 'ch;min-width:' + cellW + 'ch;max-width:' + cellW + 'ch;overflow:hidden;text-overflow:ellipsis;">' + escapeHtml(String(v.count)) + '</td>';
                            if (showPct) {
                                var pct = gTotal > 0 ? Math.round((v.count / gTotal) * 100) : 0;
                                countTableHtml += '<td class="tm-export-preview-count-value" style="width:' + pctCellW + 'ch;font-size:0.75rem;color:#666;">' + pct + '%</td>';
                            }
                        });
                    });
                    countTableHtml += '</tr></tbody></table>';
                }
            }
            const totalColSpan = columns.length;
            const dateStr = 'Fecha y hora de corte: ' + new Date().toLocaleString();
            
            let html = '';
            
            // Área de Título y Fecha (superior)
            html += '<div class="tm-export-preview-header" style="width:100%;margin-bottom:15px;border-bottom:1px solid #eee;padding-bottom:10px;">';
            html += '<div class="tm-export-preview-title" style="' + titleStyle + ';text-align:center;">' + escapeHtml(state.title || 'Título') + '</div>';
            html += '<div class="tm-export-preview-date" style="text-align:right;font-size:0.8rem;color:#666;margin-top:8px;">' + escapeHtml(dateStr) + '</div>';
            html += '</div>';

            // Tabla de Conteo (Resumen)
            html += countTableHtml;

            // Tabla de Datos (Desglose)
            html += '<table class="tm-export-preview-table" style="table-layout:fixed;width:auto;border-collapse:collapse;margin-top:10px;">';
            
            // Fila de Desglose (dentro de la tabla para alineación)
            html += '<tr class="tm-export-preview-row">';
            html += '<td class="tm-export-preview-cell tm-export-preview-desglose-label" style="font-weight:600;padding:12px 0 6px 0;border-left:0;border-right:0;border-bottom:0;" colspan="' + totalColSpan + '">Desglose</td>';
            html += '</tr>';

            // Encabezados
            html += '<tr class="tm-export-preview-row tm-export-preview-header">';
            columns.forEach(function (col) {
                const color = colorMap[col.key] || '#861e34';
                if (col.is_image) {
                    const c = state.columns.find(function (x) { return x.key === col.key; }) || {};
                    const w = (c.imageWidth || 120) + 'px';
                    const h = (c.imageHeight || 80) + 'px';
                    html += '<th class="tm-export-preview-cell tm-export-preview-header-cell tm-export-preview-image-cell" style="background-color:' + escapeHtml(color) + ';width:' + w + ';height:' + h + ';min-width:' + w + ';min-height:' + h + '"><span class="tm-export-preview-image-placeholder">Imagen</span></th>';
                } else {
                    const ch = Math.min(col.max_width_chars || 24, 60);
                    html += '<th class="tm-export-preview-cell tm-export-preview-header-cell" style="background-color:' + escapeHtml(color) + ';width:' + ch + 'ch">' + escapeHtml(col.label) + '</th>';
                }
            });
            html += '</tr>';

            if (Array.isArray(entries) && entries.length > 0) {
                var itemNum = 1;
                entries.forEach(function (entry) {
                    var mrLabel = (meta[entry.microrregion_id] && meta[entry.microrregion_id].label) ? meta[entry.microrregion_id].label : 'Sin microrregión';
                    html += '<tr class="tm-export-preview-row tm-export-preview-data">';
                    columns.forEach(function (col) {
                        const cellColor = '#f5f5f5';
                        if (col.is_image) {
                            const c = state.columns.find(function (x) { return x.key === col.key; }) || {};
                            const w = (c.imageWidth || 120) + 'px';
                            const h = (c.imageHeight || 80) + 'px';
                            html += '<td class="tm-export-preview-cell tm-export-preview-data-cell tm-export-preview-image-cell" style="width:' + w + ';height:' + h + ';min-width:' + w + ';min-height:' + h + ';background:#f0f0f0"><span class="tm-export-preview-image-placeholder">—</span></td>';
                        } else {
                            const ch = Math.min(col.max_width_chars || 24, 60);
                            var val = '';
                            if (col.key === 'item') { val = String(itemNum++); } else if (col.key === 'microrregion') { val = mrLabel; } else { val = formatPreviewCellValue(entry.data && entry.data[col.key]); }
                            html += '<td class="tm-export-preview-cell tm-export-preview-data-cell" style="width:' + ch + 'ch;background:' + escapeHtml(cellColor) + '">' + escapeHtml(val) + '</td>';
                        }
                    });
                    html += '</tr>';
                });
            } else {
                html += '<tr class="tm-export-preview-row tm-export-preview-data">';
                columns.forEach(function (col) {
                    const cellColor = '#f5f5f5';
                    if (col.is_image) {
                        const c = state.columns.find(function (x) { return x.key === col.key; }) || {};
                        const w = (c.imageWidth || 120) + 'px';
                        const h = (c.imageHeight || 80) + 'px';
                        html += '<td class="tm-export-preview-cell tm-export-preview-data-cell tm-export-preview-image-cell" data-key="' + escapeHtml(col.key) + '" style="width:' + w + ';height:' + h + ';min-width:' + w + ';min-height:' + h + ';background:#f0f0f0"><span class="tm-export-preview-image-placeholder">—</span></td>';
                    } else {
                        const ch = Math.min(col.max_width_chars || 24, 60);
                        const val = (savedRow && savedRow[col.key] !== undefined) ? escapeHtml(savedRow[col.key]) : '';
                        html += '<td class="tm-export-preview-cell tm-export-preview-data-cell" data-key="' + escapeHtml(col.key) + '" contenteditable="true" style="width:' + ch + 'ch;background:' + escapeHtml(cellColor) + '" data-placeholder="Ejemplo">' + val + '</td>';
                    }
                });
                html += '</tr>';
            }
            html += '</table>';
            previewEl.innerHTML = html;
        }

        function reorderColumnsList(container, columns) {
            const map = {};
            columns.forEach(function (c) { if (c && c.key) { map[c.key] = c; } });
            const ordered = [];
            Array.from(container.querySelectorAll('.tm-export-personalize-col')).forEach(function (item) {
                const key = item.dataset.key || '';
                const base = map[key];
                if (!base) { return; }
                const col = Object.assign({}, base);
                if (!col.is_image) {
                    const widthInput = item.querySelector('.tm-export-col-width-input');
                    if (widthInput) {
                        var raw = parseInt(widthInput.value, 10);
                        if (!Number.isNaN(raw)) {
                            col.max_width_chars = Math.max(2, Math.min(raw, 60));
                        }
                    }
                }
                ordered.push(col);
            });
            return ordered.length ? ordered : columns.slice();
        }

        function updateRestoreVisibility(columnsEl, originalColumns, restoreWrap) {
            if (!restoreWrap) { return; }
            const current = columnsEl.querySelectorAll('.tm-export-personalize-col').length;
            restoreWrap.hidden = current >= originalColumns.length;
        }

        var PREVIEW_ZOOM_STEPS = [50, 75, 100, 125, 150, 175, 200];
        function applyPreviewZoom(level) {
            var pageEl = document.getElementById('tmExportPreviewPage');
            var valueEl = document.getElementById('tmExportZoomValue');
            if (!pageEl) { return; }
            var zoom = Math.min(Math.max(level, PREVIEW_ZOOM_STEPS[0]), PREVIEW_ZOOM_STEPS[PREVIEW_ZOOM_STEPS.length - 1]);
            if (personalizeModal) { personalizeModal._previewZoom = zoom; }
            pageEl.style.transform = 'scale(' + (zoom / 100) + ')';
            pageEl.style.transformOrigin = 'top left';
            if (valueEl) { valueEl.textContent = zoom; }
        }
        function setupPreviewZoom() {
            var zoomWrap = document.getElementById('tmExportPreviewZoomWrap');
            var zoomOutBtn = personalizeModal && personalizeModal.querySelector('[data-zoom-out]');
            var zoomInBtn = personalizeModal && personalizeModal.querySelector('[data-zoom-in]');
            var resetBtn = personalizeModal && personalizeModal.querySelector('.tm-export-zoom-reset');
            var current = (personalizeModal && personalizeModal._previewZoom) || 100;
            applyPreviewZoom(current);
            if (zoomOutBtn) {
                zoomOutBtn.onclick = function () {
                    var idx = PREVIEW_ZOOM_STEPS.indexOf((personalizeModal && personalizeModal._previewZoom) || 100);
                    if (idx <= 0) { idx = 0; } else { idx -= 1; }
                    applyPreviewZoom(PREVIEW_ZOOM_STEPS[idx]);
                };
            }
            if (zoomInBtn) {
                zoomInBtn.onclick = function () {
                    var idx = PREVIEW_ZOOM_STEPS.indexOf((personalizeModal && personalizeModal._previewZoom) || 100);
                    if (idx < 0) { idx = PREVIEW_ZOOM_STEPS.indexOf(100); }
                    if (idx >= PREVIEW_ZOOM_STEPS.length - 1) { idx = PREVIEW_ZOOM_STEPS.length - 1; } else { idx += 1; }
                    applyPreviewZoom(PREVIEW_ZOOM_STEPS[idx]);
                };
            }
            if (resetBtn) {
                resetBtn.onclick = function () { applyPreviewZoom(100); };
            }
            var a4Area = document.getElementById('tmExportPreviewA4Area');
            if (a4Area && !a4Area._tmExcelWheelZoomBound) {
                a4Area._tmExcelWheelZoomBound = true;
                a4Area.addEventListener('wheel', function (event) {
                    var pinch = event.ctrlKey || event.metaKey;
                    if (!pinch) { return; }
                    event.preventDefault();
                    event.stopPropagation();
                    var steps = PREVIEW_ZOOM_STEPS;
                    var currentZoom = (personalizeModal && personalizeModal._previewZoom) || 100;
                    var idx = steps.indexOf(currentZoom);
                    if (idx < 0) { idx = steps.indexOf(100); }
                    if (event.deltaY > 0) {
                        // alejar
                        idx = Math.max(0, idx - 1);
                    } else if (event.deltaY < 0) {
                        // acercar
                        idx = Math.min(steps.length - 1, idx + 1);
                    } else {
                        return;
                    }
                    applyPreviewZoom(steps[idx]);
                }, { passive: false });
            }
        }

        function attachPersonalizeColumnListeners(columnsEl, columns, previewEl, restoreWrap) {
            columnsEl.querySelectorAll('.tm-export-color-trigger').forEach(function (trigger) {
                trigger.addEventListener('click', function () {
                    const menu = trigger.nextElementSibling;
                    if (!(menu instanceof HTMLElement)) { return; }
                    const isOpen = !menu.hidden;
                    Array.from(columnsEl.querySelectorAll('.tm-export-color-menu')).forEach(function (m) { m.hidden = true; });
                    trigger.setAttribute('aria-expanded', String(!isOpen));
                    menu.hidden = isOpen;
                });
            });

            columnsEl.querySelectorAll('.tm-export-color-menu').forEach(function (menu) {
                menu.addEventListener('click', function (e) {
                    const option = e.target.closest('.tm-export-color-option');
                    if (!option) { return; }
                    const color = option.getAttribute('data-color') || '';
                    const parentCol = menu.closest('.tm-export-personalize-col');
                    const trigger = parentCol ? parentCol.querySelector('.tm-export-color-trigger') : null;
                    if (parentCol && trigger) {
                        parentCol.querySelectorAll('.tm-export-color-option').forEach(function (opt) { opt.classList.remove('is-active'); });
                        option.classList.add('is-active');
                        trigger.setAttribute('data-color', color);
                        const swatch = trigger.querySelector('.tm-export-color-swatch');
                        if (swatch instanceof HTMLElement) {
                            swatch.style.backgroundColor = color;
                        }
                    }
                    menu.hidden = true;
                    buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                });
            });

            let draggedItem = null;
            columnsEl.querySelectorAll('.tm-export-personalize-col').forEach(function (item) {
                item.addEventListener('dragstart', function (e) {
                    draggedItem = item;
                    e.dataTransfer.setData('text/plain', item.dataset.key);
                    item.classList.add('is-dragging');
                });
                item.addEventListener('dragend', function () {
                    item.classList.remove('is-dragging');
                    draggedItem = null;
                });
                item.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    if (draggedItem && draggedItem !== item) {
                        item.classList.add('is-drag-over');
                    }
                });
                item.addEventListener('dragleave', function () { item.classList.remove('is-drag-over'); });
                item.addEventListener('drop', function (e) {
                    e.preventDefault();
                    item.classList.remove('is-drag-over');
                    if (!draggedItem || draggedItem === item) { return; }
                    const all = Array.from(columnsEl.querySelectorAll('.tm-export-personalize-col'));
                    const idx = all.indexOf(item);
                    const dragIdx = all.indexOf(draggedItem);
                    if (idx === -1 || dragIdx === -1) { return; }
                    if (idx < dragIdx) {
                        columnsEl.insertBefore(draggedItem, item);
                    } else {
                        columnsEl.insertBefore(draggedItem, item.nextSibling);
                    }
                    buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                });
            });
            updateRestoreVisibility(columnsEl, columns, restoreWrap);
        }

        function openExportPersonalizeModal(structureUrl, exportUrl) {
            if (!structureUrl || !exportUrl || !personalizeModal) { return; }
            const loadingEl = document.getElementById('tmExportPersonalizeLoading');
            const contentEl = document.getElementById('tmExportPersonalizeContent');
            const columnsEl = document.getElementById('tmExportPersonalizeColumns');
            const previewEl = document.getElementById('tmExportPersonalizePreview');
            const titleEl = document.getElementById('tmExportPersonalizeTitle');
            const applyExcelBtn = document.getElementById('tmExportApplyExcel');
            const applyPdfBtn = document.getElementById('tmExportApplyPdf');
            const applyWordBtn = document.getElementById('tmExportApplyWord');
            const restoreWrap = document.getElementById('tmExportRestoreWrap');
            const restoreBtn = document.getElementById('tmExportRestoreBtn');
            const includeCountTableEl = document.getElementById('tmExportIncludeCountTable');
            const countByWrapEl = document.getElementById('tmExportCountByWrap');
            const countByFieldsEl = document.getElementById('tmExportCountByFields');
            const countTableColorListEl = document.getElementById('tmExportCountTableColorList');

            if (loadingEl) { loadingEl.hidden = false; }
            if (contentEl) { contentEl.hidden = true; }
            personalizeModal.classList.add('is-open');
            personalizeModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            fetch(structureUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(new Error('Error al cargar')); })
                .then(function (data) {
                    let columns = Array.isArray(data.columns) ? data.columns : [];
                    if (personalizeModal) {
                        personalizeModal._personalizeColumns = columns;
                        personalizeModal._previewEntries = Array.isArray(data.entries) ? data.entries : [];
                        personalizeModal._previewMicrorregionMeta = data.microrregion_meta && typeof data.microrregion_meta === 'object' ? data.microrregion_meta : {};
                    }
                    var countableColumns = columns.filter(function (c) {
                        var k = (c && c.key) ? c.key : '';
                        return k !== 'item' && k !== 'microrregion' && !c.is_image;
                    });
                    if (personalizeModal) { personalizeModal._countableColumns = countableColumns; }
                    if (titleEl) { titleEl.value = data.title || ''; }
                    if (countByFieldsEl) {
                        countByFieldsEl.innerHTML = '';
                        countableColumns.forEach(function (col) {
                            var label = document.createElement('label');
                            label.className = 'tm-export-count-by-check';
                            var cb = document.createElement('input');
                            cb.type = 'checkbox';
                            cb.setAttribute('data-count-key', col.key || '');
                            cb.value = col.key || '';
                            label.appendChild(cb);
                            label.appendChild(document.createTextNode(' ' + (col.label || col.key || '')));
                            countByFieldsEl.appendChild(label);
                        });
                    }
                    if (includeCountTableEl && countByWrapEl) {
                        countByWrapEl.hidden = !includeCountTableEl.checked;
                        includeCountTableEl.addEventListener('change', function () {
                            countByWrapEl.hidden = !includeCountTableEl.checked;
                            buildCountTableColorList(countTableColorListEl, countByFieldsEl, personalizeModal._previewEntries);
                            buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl, undefined, personalizeModal._previewEntries, personalizeModal._previewMicrorregionMeta);
                        });
                    }
                    var countColorsCollapsible = document.getElementById('tmExportCountColorsCollapsible');
                    var countColorsToggle = document.getElementById('tmExportCountColorsToggle');
                    if (countColorsCollapsible && countColorsToggle) {
                        countColorsToggle.addEventListener('click', function () {
                            var isOpen = countColorsCollapsible.classList.toggle('is-open');
                            countColorsToggle.setAttribute('aria-expanded', String(isOpen));
                        });
                    }
                    if (countByFieldsEl) {
                        countByFieldsEl.addEventListener('change', function () {
                            buildCountTableColorList(countTableColorListEl, countByFieldsEl, personalizeModal._previewEntries);
                            buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl, undefined, personalizeModal._previewEntries, personalizeModal._previewMicrorregionMeta);
                        });
                    }
                    buildPersonalizeColumnsList(columns, columnsEl);
                    buildCountTableColorList(countTableColorListEl, countByFieldsEl, personalizeModal._previewEntries);
                    if (personalizeModal && !personalizeModal._countTableColorListenersBound) {
                        personalizeModal._countTableColorListenersBound = true;
                        personalizeModal.addEventListener('click', function (e) {
                            var colorList = document.getElementById('tmExportCountTableColorList');
                            if (!colorList || !colorList.contains(e.target)) { return; }
                            var trigger = e.target.closest('.tm-export-color-trigger');
                            var option = e.target.closest('.tm-export-color-option');
                            if (trigger) {
                                var menu = trigger.nextElementSibling;
                                if (!(menu instanceof HTMLElement)) { return; }
                                var isOpen = !menu.hidden;
                                Array.from(personalizeModal.querySelectorAll('.tm-export-color-menu')).forEach(function (m) { m.hidden = true; });
                                trigger.setAttribute('aria-expanded', String(!isOpen));
                                menu.hidden = isOpen;
                                if (!isOpen && menu.scrollIntoView) {
                                    setTimeout(function () { menu.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }, 0);
                                }
                            } else if (option) {
                                var color = option.getAttribute('data-color') || '';
                                var menu = option.closest('.tm-export-color-menu');
                                var colorCell = menu ? menu.closest('.tm-export-col-color') : null;
                                if (!colorCell) { colorCell = menu ? menu.closest('.tm-export-count-table-value-color') : null; }
                                var tr = colorCell ? colorCell.querySelector('.tm-export-color-trigger') : null;
                                if (colorCell && tr) {
                                    colorCell.querySelectorAll('.tm-export-color-option').forEach(function (opt) { opt.classList.remove('is-active'); });
                                    option.classList.add('is-active');
                                    tr.setAttribute('data-color', color);
                                    var swatch = tr.querySelector('.tm-export-color-swatch');
                                    if (swatch instanceof HTMLElement) { swatch.style.backgroundColor = color; }
                                }
                                if (menu) { menu.hidden = true; }
                                buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl, undefined, personalizeModal._previewEntries, personalizeModal._previewMicrorregionMeta);
                            } else if (e.target.closest('.tm-export-count-pct-check')) {
                                buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl, undefined, personalizeModal._previewEntries, personalizeModal._previewMicrorregionMeta);
                            }
                        });
                    }
                    buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl, undefined, personalizeModal._previewEntries, personalizeModal._previewMicrorregionMeta);
                    if (restoreWrap) { restoreWrap.hidden = true; }
                    if (personalizeModal) { personalizeModal._previewZoom = 100; }
                    setupPreviewZoom();

                    columnsEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    columnsEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    columnsEl.addEventListener('click', function (e) {
                        const omitBtn = e.target.closest('.tm-export-omit-btn');
                        if (omitBtn) {
                            const row = omitBtn.closest('.tm-export-personalize-col');
                            if (row) {
                                row.remove();
                                buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                                updateRestoreVisibility(columnsEl, columns, restoreWrap);
                            }
                        }
                    });
                    attachPersonalizeColumnListeners(columnsEl, columns, previewEl, restoreWrap);

                    if (titleEl) {
                        titleEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }

                    if (restoreBtn && restoreWrap) {
                        restoreBtn.onclick = function () {
                            buildPersonalizeColumnsList(columns, columnsEl);
                            buildPersonalizePreview(columns, previewEl);
                            restoreWrap.hidden = true;
                            attachPersonalizeColumnListeners(columnsEl, columns, previewEl, restoreWrap);
                        };
                    }

                    function applyExport(format) {
                            const orderedCols = reorderColumnsList(columnsEl, columns);
                            const state = getPersonalizeState();
                            const orientBtn = personalizeModal.querySelector('.tm-export-orient-btn.is-active');
                            const orientation = orientBtn ? (orientBtn.getAttribute('data-orientation') || 'portrait') : 'portrait';

                            var includeCountTable = !!(includeCountTableEl && includeCountTableEl.checked);
                            var countByFields = [];
                            if (includeCountTable && countByFieldsEl) {
                                countByFieldsEl.querySelectorAll('input[type="checkbox"]:checked').forEach(function (cb) {
                                    var k = cb.getAttribute('data-count-key') || cb.value;
                                    if (k) { countByFields.push(k); }
                                });
                            }
                            const cfg = {
                                title: state.title || '',
                                title_align: state.titleAlign || 'center',
                                orientation: orientation,
                                include_count_table: includeCountTable,
                                count_by_fields: countByFields,
                                count_table_colors: state.countTableColors || {},
                                count_table_cell_width: state.countTableCellWidth || 12,
                                columns: orderedCols.map(function (col) {
                                    const colState = state.columns.find(function (c) { return c.key === col.key; }) || {};
                                    return {
                                        key: col.key,
                                        color: colState.color || 'var(--clr-primary)',
                                        image_width: colState.imageWidth || null,
                                        image_height: colState.imageHeight || null,
                                        max_width_chars: col.max_width_chars || null
                                    };
                                })
                            };

                            const separator = exportUrl.indexOf('?') === -1 ? '?' : '&';
                            const cfgParam = encodeURIComponent(JSON.stringify(cfg));
                            const fmt = format || 'excel';
                            closePersonalizeModal();
                            window.location.href = exportUrl + separator + 'mode=single&analysis=0&format=' + encodeURIComponent(fmt) + '&cfg=' + cfgParam;
                    }

                    if (applyExcelBtn && exportUrl) {
                        applyExcelBtn.onclick = function () { applyExport('excel'); };
                    }
                    if (applyWordBtn && exportUrl) {
                        applyWordBtn.onclick = function () { applyExport('word'); };
                    }
                    if (applyPdfBtn && exportUrl) {
                        applyPdfBtn.onclick = function () { applyExport('pdf'); };
                    }
                })
                .catch(function () {
                    if (loadingEl) { loadingEl.textContent = 'No se pudo cargar la estructura.'; }
                })
                .finally(function () {
                    if (loadingEl) { loadingEl.hidden = true; }
                    if (contentEl) { contentEl.hidden = false; }
                });
        }

        exportButtons.forEach(function (exportButton) {
            exportButton.addEventListener('click', function () {
                const exportUrl = exportButton.getAttribute('data-export-url');
                if (!exportUrl) { return; }
                var exportBtnRef = exportButton;
                if (!templateSwal) {
                    const separator = exportUrl.indexOf('?') === -1 ? '?' : '&';
                    window.location.href = exportUrl + separator + 'mode=single&analysis=0';
                    return;
                }
                templateSwal.fire({
                    title: 'Tipo de exportación',
                    html: '<div class="tm-swal-export-options" style="text-align:left">'
                        + '<p style="margin:0 0 .5rem;font-size:.8rem;color:#64748b;">Elige el formato. «Personalizar» permite columnas y diseño (Excel, Word o PDF de tabla).</p>'
                        + '<label style="display:flex;gap:.5rem;align-items:flex-start;margin-bottom:.55rem;cursor:pointer;">'
                        + '<input type="radio" name="tm-export-choice" value="single" checked style="margin-top:.2rem;"> '
                        + '<span><strong>Excel — Una sola hoja</strong><br><small style="color:#64748b">Todos los registros en una hoja.</small></span>'
                        + '</label>'
                        + '<label style="display:flex;gap:.5rem;align-items:flex-start;margin-bottom:.55rem;cursor:pointer;">'
                        + '<input type="radio" name="tm-export-choice" value="mr" style="margin-top:.2rem;"> '
                        + '<span><strong>Excel — 1 hoja por microregión</strong><br><small style="color:#64748b">Una página por microregión.</small></span>'
                        + '</label>'
                        + '<label style="display:flex;gap:.5rem;align-items:flex-start;margin-bottom:.55rem;cursor:pointer;">'
                        + '<input type="radio" name="tm-export-choice" value="word_table" style="margin-top:.2rem;"> '
                        + '<span><strong>Word — Tabla de registros</strong><br><small style="color:#64748b">.docx con los mismos datos que el Excel (una tabla).</small></span>'
                        + '</label>'
                        + '<label style="display:flex;gap:.5rem;align-items:flex-start;margin-bottom:.55rem;cursor:pointer;">'
                        + '<input type="radio" name="tm-export-choice" value="pdf_table" style="margin-top:.2rem;"> '
                        + '<span><strong>PDF — Tabla de registros</strong><br><small style="color:#64748b">.pdf con la tabla de registros.</small></span>'
                        + '</label>'
                        + '<label style="display:flex;gap:.5rem;align-items:flex-start;margin-bottom:.65rem;cursor:pointer;">'
                        + '<input type="radio" name="tm-export-choice" value="analysis_word" style="margin-top:.2rem;"> '
                        + '<span><strong>Informe de análisis (Word)</strong><br><small style="color:#64748b">Documento .docx con resumen y tablas de análisis (distinto a la tabla simple).</small></span>'
                        + '</label>'
                        + '<hr style="margin:.65rem 0;border:none;border-top:1px solid #e2e8f0;">'
                        + '<p style="margin:0;"><button type="button" class="tm-btn tm-btn-outline tm-swal-personalize-btn">Personalizar columnas y diseño</button></p>'
                        + '</div>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Exportar',
                    cancelButtonText: 'Cancelar',
                    didOpen: function () {
                        var personalizeBtn = document.querySelector('.swal2-html-container .tm-swal-personalize-btn');
                        if (personalizeBtn) {
                            personalizeBtn.addEventListener('click', function () {
                                var choice = document.querySelector('input[name="tm-export-choice"]:checked');
                                var val = choice ? choice.value : 'single';
                                Swal.close();
                                if (val === 'analysis_word') {
                                    var previewUrl = exportBtnRef.getAttribute('data-analysis-preview-url');
                                    var wordUrl = exportBtnRef.getAttribute('data-analysis-word-url');
                                    if (previewUrl && wordUrl) {
                                        window._tmAnalysisPreviewUrl = previewUrl;
                                        window._tmAnalysisWordUrl = wordUrl;
                                        var wpf = document.getElementById('tmAnalysisWordPersonalizeForm');
                                        if (wpf) { wpf.setAttribute('action', wordUrl); }
                                        document.body.style.overflow = 'hidden';
                                        openWordPersonalizeModal();
                                    }
                                    return;
                                }
                                var structureUrl = exportBtnRef.getAttribute('data-structure-url');
                                var expUrl = exportBtnRef.getAttribute('data-export-url');
                                if (structureUrl && expUrl) { openExportPersonalizeModal(structureUrl, expUrl); }
                            });
                        }
                    },
                    preConfirm: function () {
                        const choice = document.querySelector('input[name="tm-export-choice"]:checked');
                        const v = choice ? choice.value : 'single';
                        return { choice: v };
                    }
                }).then(function (result) {
                    if (!result.isConfirmed) { return; }
                    var choice = result.value && result.value.choice;
                    if (choice === 'word_table' || choice === 'pdf_table') {
                        const separator = exportUrl.indexOf('?') === -1 ? '?' : '&';
                        const fmt = choice === 'pdf_table' ? 'pdf' : 'word';
                        window.location.href = exportUrl + separator + 'mode=single&analysis=0&format=' + encodeURIComponent(fmt);
                        return;
                    }
                    if (choice === 'analysis_word') {
                        var previewUrl = exportBtnRef.getAttribute('data-analysis-preview-url');
                        var wordUrl = exportBtnRef.getAttribute('data-analysis-word-url');
                        if (previewUrl && wordUrl) {
                            window._tmAnalysisPreviewUrl = previewUrl;
                            window._tmAnalysisWordUrl = wordUrl;
                            var wpf = document.getElementById('tmAnalysisWordPersonalizeForm');
                            if (wpf) { wpf.setAttribute('action', wordUrl); }
                            document.body.style.overflow = 'hidden';
                            openWordPersonalizeModal();
                        }
                        return;
                    }
                    const mode = choice === 'mr' ? 'mr' : 'single';
                    const separator = exportUrl.indexOf('?') === -1 ? '?' : '&';
                    window.location.href = exportUrl + separator + 'mode=' + mode + '&analysis=0';
                });
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            if (personalizeModal && personalizeModal.classList.contains('is-open')) {
                closePersonalizeModal();
                return;
            }

            if (imageModal && imageModal.classList.contains('is-open')) {
                closeImageModal();
                return;
            }

            Array.from(document.querySelectorAll('.tm-modal.is-open')).forEach(function (modal) {
                closeModal(modal);
            });
        });
    });
