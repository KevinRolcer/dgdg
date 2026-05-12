document.addEventListener('DOMContentLoaded', function () {
    const previewUrl = window.TM_ADMIN_SEED_EXCEL_BOOT.previewUrl;
    let csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function csrfFetch(url, opts = {}) {
        const res = await fetch(url, opts);
        if (res.status === 419) {
            try {
                const r = await fetch(window.TM_ADMIN_SEED_EXCEL_BOOT.csrfRefreshUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                if (r.redirected || r.status === 401) { window.location.href = window.TM_ADMIN_SEED_EXCEL_BOOT.loginUrl; return res; }
                if (r.ok) { const j = await r.json(); if (j.token) { csrf = j.token; const m = document.querySelector('meta[name="csrf-token"]'); if (m) m.setAttribute('content', j.token); } }
            } catch (_) {}
            if (opts.body instanceof FormData) opts.body.set('_token', csrf);
            if (opts.headers && typeof opts.headers === 'object' && !(opts.headers instanceof Headers) && 'X-CSRF-TOKEN' in opts.headers) opts.headers['X-CSRF-TOKEN'] = csrf;
            return fetch(url, opts);
        }
        return res;
    }

    async function safeJsonParse(response) {
        if (response.status === 419) {
            throw new Error('Tu sesión ha expirado. Recarga la página para continuar.');
        }
        var ct = response.headers.get('content-type') || '';
        if (!response.ok || !ct.includes('application/json')) {
            var text = await response.text().catch(function() { return ''; });
            if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                throw new Error('El servidor no pudo procesar la solicitud (posible límite de memoria o tiempo). Intenta con un archivo más pequeño.');
            }
            throw new Error(text.substring(0, 200) || ('Error del servidor: HTTP ' + response.status));
        }
        return response.json();
    }
    const fileInput = document.getElementById('tmSeedFile');
    const headerRowInput = document.getElementById('tmSeedHeaderRow');
    const dataRowInput = document.getElementById('tmSeedDataRow');
    const errEl = document.getElementById('tmSeedPreviewErr');
    const mappingEl = document.getElementById('tmSeedMapping');
    const colMr = document.getElementById('tmSeedColMr');
    const colMun = document.getElementById('tmSeedColMun');
    const fieldMapRows = document.getElementById('tmSeedFieldMapRows');
    const fieldColumnsInput = document.getElementById('tmSeedFieldColumns');
    const fieldTypesInput = document.getElementById('tmSeedFieldTypes');
    const fieldOptionsInput = document.getElementById('tmSeedFieldOptions');
    const fieldUnificationsInput = document.getElementById('tmSeedFieldUnifications');
    const previewWrap = document.getElementById('tmSeedPreviewWrap');
    const previewTableWrap = document.getElementById('tmSeedPreviewTableWrap');
    const submitBtn = document.getElementById('tmSeedSubmit');
    const indef = document.getElementById('tmSeedIndef');
    const expires = document.getElementById('tmSeedExpires');
    const mrOnlyChk = document.getElementById('tmSeedMrOnly');
    const munSentinel = document.getElementById('tmSeedMunSentinel');
    const wrapMun = document.getElementById('tmSeedWrapMun');
    const wrapMr = document.getElementById('tmSeedWrapMr');
    const mrHint = document.getElementById('tmSeedMrHint');
    const sheetTabsEl = document.getElementById('tmSeedSheetTabs');
    const sheetIndexInput = document.getElementById('tmSeedSheetIndex');
    const detectNoteEl = document.getElementById('tmSeedDetectNote');
    const autoDetectChk = document.getElementById('tmSeedAutoDetect');
    const unifModal = document.getElementById('tmSeedUnifModal');
    const unifFrom = document.getElementById('tmSeedUnifFrom');
    const unifTo = document.getElementById('tmSeedUnifTo');
    const unifAddRule = document.getElementById('tmSeedUnifAddRule');
    const unifRulesList = document.getElementById('tmSeedUnifRulesList');
    const unifRulesEmpty = document.getElementById('tmSeedUnifRulesEmpty');
    const unifSubtitle = document.getElementById('tmSeedUnifSubtitle');
    const unifClose = document.getElementById('tmSeedUnifClose');
    const fieldTypesCatalog = {
        text: 'Texto',
        textarea: 'Texto largo',
        number: 'Número',
        date: 'Fecha',
        datetime: 'Fecha y hora',
        select: 'Lista',
        multiselect: 'Selección múltiple',
        municipio: 'Municipio (catálogo)',
        boolean: 'Sí / No',
        semaforo: 'Semáforo',
        document: 'Documento (PDF / DOCX)'
    };

    let currentSheetIndex = 0;
    let currentColumnSuggestions = {};
    let activeUnifRow = null;

    function parseUnificationRules(raw) {
        const text = String(raw || '').trim();
        if (!text) return [];
        const lines = text.split(/\r?\n/).map(function (x) { return x.trim(); }).filter(Boolean);
        const rules = [];
        lines.forEach(function (line) {
            const parts = line.split(/\s*(?:=>|->|=)\s*/);
            if (parts.length < 2) return;
            const from = String(parts[0] || '').trim();
            const to = String(parts.slice(1).join('=') || '').trim();
            if (!from || !to) return;
            rules.push({ from: from, to: to });
        });
        return rules;
    }

    function serializeUnificationRules(rules) {
        return rules.map(function (r) { return String(r.from).trim() + ' => ' + String(r.to).trim(); }).join('\n');
    }

    function getUnifHiddenInput(row) {
        return row ? row.querySelector('.tm-seed-field-unifications') : null;
    }

    function getUnifButton(row) {
        return row ? row.querySelector('.tm-seed-field-unif-open') : null;
    }

    function updateUnifButtonLabel(row) {
        const hidden = getUnifHiddenInput(row);
        const btn = getUnifButton(row);
        if (!hidden || !btn) return;
        const count = parseUnificationRules(hidden.value).length;
        btn.innerHTML = '<i class="fa-solid fa-shuffle"></i> Unificar respuestas' + (count > 0 ? ' (' + count + ')' : '');
    }

    function collectRowOptionValues(row) {
        const checkbox = row.querySelector('.tm-seed-fc');
        const typeSel = row.querySelector('.tm-seed-field-type');
        const optionsInput = row.querySelector('.tm-seed-field-options');
        if (!checkbox || !typeSel || !optionsInput) return [];
        const colIdx = parseInt(checkbox.value || '-1', 10);
        const suggested = getSuggestedOptionsForColumn(colIdx, typeSel.value || 'select');
        const typed = String(optionsInput.value || '')
            .split(/[\r\n,;|]+/)
            .map(function (v) { return normalizeOptionLabel(v); })
            .filter(Boolean);
        const union = [];
        const seen = new Set();
        suggested.concat(typed).forEach(function (value) {
            const label = normalizeOptionLabel(value);
            const key = normalizeOptionCompareKey(label);
            if (!label || !key || seen.has(key)) return;
            seen.add(key);
            union.push(label);
        });
        return union;
    }

    function fillUnifSelects(options, selectedFrom, selectedTo) {
        const build = function (selected) {
            let html = '<option value="">Selecciona opción…</option>';
            options.forEach(function (opt) {
                const isSel = selected && normalizeOptionCompareKey(selected) === normalizeOptionCompareKey(opt);
                html += '<option value="' + escapeHtml(opt) + '" ' + (isSel ? 'selected' : '') + '>' + escapeHtml(opt) + '</option>';
            });
            return html;
        };
        unifFrom.innerHTML = build(selectedFrom || '');
        unifTo.innerHTML = build(selectedTo || '');
    }

    function renderUnifRulesInModal(row) {
        const hidden = getUnifHiddenInput(row);
        if (!hidden) return;
        const rules = parseUnificationRules(hidden.value);
        if (rules.length === 0) {
            unifRulesList.classList.add('tm-hidden');
            unifRulesList.innerHTML = '';
            unifRulesEmpty.classList.remove('tm-hidden');
        } else {
            unifRulesEmpty.classList.add('tm-hidden');
            unifRulesList.classList.remove('tm-hidden');
            unifRulesList.innerHTML = rules.map(function (rule, idx) {
                return ''
                    + '<div class="tm-seed-unif-rule" data-rule-idx="' + idx + '">'
                    + '  <span class="tm-seed-unif-rule-from">' + escapeHtml(rule.from) + '</span>'
                    + '  <i class="fa-solid fa-arrow-right"></i>'
                    + '  <span class="tm-seed-unif-rule-to">' + escapeHtml(rule.to) + '</span>'
                    + '  <button type="button" class="tm-btn tm-btn-danger tm-seed-unif-remove" data-rule-idx="' + idx + '">Quitar</button>'
                    + '</div>';
            }).join('');
        }
        updateUnifButtonLabel(row);
    }

    function openUnifModalForRow(row) {
        const typeSel = row.querySelector('.tm-seed-field-type');
        if (!typeSel || (typeSel.value !== 'select' && typeSel.value !== 'multiselect')) return;
        activeUnifRow = row;
        const label = row.querySelector('.tm-seed-map-col-label')?.textContent?.trim() || 'Columna seleccionada';
        unifSubtitle.textContent = 'Reglas para: ' + label;
        const opts = collectRowOptionValues(row);
        fillUnifSelects(opts, '', '');
        renderUnifRulesInModal(row);
        unifModal.classList.add('is-open');
        unifModal.setAttribute('aria-hidden', 'false');
    }

    function closeUnifModal() {
        activeUnifRow = null;
        unifModal.classList.remove('is-open');
        unifModal.setAttribute('aria-hidden', 'true');
    }

    function syncMrMunMode() {
        const mrOnly = mrOnlyChk.checked;
        wrapMun.style.display = mrOnly ? 'none' : '';
        colMun.disabled = mrOnly;
        munSentinel.disabled = !mrOnly;
        colMr.required = mrOnly;
        mrHint.textContent = mrOnly ? '(obligatoria)' : '(opcional)';
    }
    mrOnlyChk.addEventListener('change', syncMrMunMode);

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function inferFieldType(label) {
        const n = String(label || '').toUpperCase();
        if (/MUNICIPIO|LOCALIDAD|COMUNIDAD/.test(n)) return 'municipio';
        if (/FECHA|DIA|MES|AÑO/.test(n)) return 'date';
        if (/HORA|F\.\s*HORA|TIMESTAMP/.test(n)) return 'datetime';
        if (/TOTAL|CANTIDAD|META|NUMERO|NÚMERO|PORCENTAJE|MONTO|IMPORTE/.test(n)) return 'number';
        if (/ESTATUS|ESTADO|SEM[ÁA]FORO|NIVEL/.test(n)) return 'select';
        if (/OBSERVACION|DESCRIPCION|DETALLE|COMENTARIO/.test(n)) return 'textarea';

        return 'text';
    }

    function normalizeOptionCompareKey(value) {
        let base = String(value || '')
            .trim()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ');

        const singularize = function (token) {
            const t = String(token || '');
            if (t.length <= 3) return t;
            if (/ces$/.test(t) && t.length > 4) return t.slice(0, -3) + 'z';
            if (/[aeiou]s$/.test(t) && t.length > 4) return t.slice(0, -1);
            if (/[bcdfghjklmnñpqrstvwxyz]es$/.test(t) && t.length > 5) return t.slice(0, -2);
            return t;
        };

        base = base
            .split(' ')
            .filter(Boolean)
            .map(singularize)
            .join(' ');

        return base.replace(/(?<=\p{L})\s+(?=\d)|(?<=\d)\s+(?=\p{L})/gu, '');
    }

    function normalizeOptionLabel(value) {
        const cleaned = String(value || '').trim().replace(/\s+/g, ' ');
        if (!cleaned) return '';
        return cleaned
            .toLowerCase()
            .replace(/(^|\s)([a-záéíóúñü])/g, function (_, prefix, chr) {
                return prefix + chr.toUpperCase();
            });
    }

    function getSuggestedOptionsForColumn(colIdx, type) {
        const data = currentColumnSuggestions[String(colIdx)] || currentColumnSuggestions[colIdx] || null;
        if (!data || typeof data !== 'object') return [];
        const key = type === 'multiselect' ? 'multiselect' : 'select';
        const source = Array.isArray(data[key]) ? data[key] : [];
        const unique = [];
        const seen = new Set();
        source.forEach(function (raw) {
            const label = normalizeOptionLabel(raw);
            if (!label) return;
            const norm = normalizeOptionCompareKey(label);
            if (!norm || seen.has(norm)) return;
            seen.add(norm);
            unique.push(label);
        });
        return unique;
    }

    function fillAutoOptionsIfNeeded(row, force) {
        const checkbox = row.querySelector('.tm-seed-fc');
        const typeSel = row.querySelector('.tm-seed-field-type');
        const optionsInput = row.querySelector('.tm-seed-field-options');
        if (!checkbox || !typeSel || !optionsInput || !checkbox.checked) return;
        const type = typeSel.value;
        if (type !== 'select' && type !== 'multiselect') return;
        const hasManualText = optionsInput.dataset.manual === '1';
        if (!force && hasManualText) return;
        if (!force && optionsInput.value.trim() !== '') return;
        const colIdx = parseInt(typeSel.getAttribute('data-col-idx') || checkbox.value || '-1', 10);
        if (colIdx < 0) return;
        const suggestions = getSuggestedOptionsForColumn(colIdx, type);
        if (suggestions.length === 0) return;
        optionsInput.value = suggestions.join(', ');
        optionsInput.dataset.auto = '1';
        optionsInput.dataset.manual = '0';
    }

    function fillSelects(headers) {
        const opts = headers.map(function (h) {
            return '<option value="' + h.index + '">' + h.letter + ' — ' + escapeHtml(h.label || '(vacío)') + '</option>';
        }).join('');
        colMr.innerHTML = '<option value="-1">— Ninguna —</option>' + opts;
        colMun.innerHTML = opts;
        colMr.dataset.set = '';
        colMun.dataset.set = '';
        let preferredDelegacionCol = null;
        let fallbackDelegacionCol = null;
        headers.forEach(function (h) {
            const n = (h.label || '').toUpperCase();
            if (/MICRORREGION|MICRORREGI|MR\b/.test(n) && !colMr.dataset.set) { colMr.value = String(h.index); colMr.dataset.set = '1'; }
            if (/NOMBRE.*DE\s+LA\s+DELEGACION|NOMBRE.*DELEGACI[ÓO]N|DELEGACI[ÓO]N.*NOMBRE/.test(n) && preferredDelegacionCol === null) {
                preferredDelegacionCol = h.index;
            } else if (/DELEGACION|DELEGACI[ÓO]N/.test(n) && fallbackDelegacionCol === null) {
                fallbackDelegacionCol = h.index;
            }
            if (/MUNICIPIO|MUNICIP/.test(n) && !colMun.dataset.set) { colMun.value = String(h.index); colMun.dataset.set = '1'; }
        });
        if (!colMr.dataset.set && preferredDelegacionCol !== null) {
            colMr.value = String(preferredDelegacionCol);
            colMr.dataset.set = '1';
        } else if (!colMr.dataset.set && fallbackDelegacionCol !== null) {
            colMr.value = String(fallbackDelegacionCol);
            colMr.dataset.set = '1';
        }
        if (!colMr.dataset.set) colMr.value = '-1';
        if (!colMun.dataset.set && colMun.options.length) colMun.selectedIndex = 0;
        syncMrMunMode();

        const typeOptions = Object.keys(fieldTypesCatalog).map(function (key) {
            return '<option value="' + key + '">' + fieldTypesCatalog[key] + '</option>';
        }).join('');

        fieldMapRows.innerHTML = '';
        headers.forEach(function (h) {
            const norm = String(h.label || '').toUpperCase();
            const isGeoColumn = /MICRORREGION|MICRORREGI|\bMR\b|MUNICIPIO|MUNICIP|DELEGACION|DELEGACI[ÓO]N/.test(norm);
            const isFolioColumn = /^N\s*[°#.]?$|^NO\.?$|^NUMERO$|^NÚMERO$/.test(norm.replace(/\s+/g, ''));
            const shouldCheck = !isGeoColumn && !isFolioColumn;
            const type = inferFieldType(h.label || '');
            const row = document.createElement('div');
            row.className = 'tm-seed-map-row';
            row.innerHTML = ''
                + '<div class="tm-seed-map-col tm-seed-map-col--pick">'
                + '  <input type="checkbox" class="tm-seed-fc" value="' + h.index + '" ' + (shouldCheck ? 'checked' : '') + '>'
                + '  <span class="tm-seed-map-col-label">' + h.letter + ' — ' + escapeHtml(h.label || '(vacío)') + '</span>'
                + '  <span class="tm-seed-map-sort-actions">'
                + '    <button type="button" class="tm-btn tm-seed-map-move" data-move-up title="Subir campo">↑</button>'
                + '    <button type="button" class="tm-btn tm-seed-map-move" data-move-down title="Bajar campo">↓</button>'
                + '  </span>'
                + '</div>'
                + '<div class="tm-seed-map-col tm-seed-map-col--type">'
                + '  <select class="tm-input tm-seed-field-type" data-col-idx="' + h.index + '">' + typeOptions + '</select>'
                + '</div>'
                + '<div class="tm-seed-map-col tm-seed-map-col--options">'
                + '  <input type="text" class="tm-input tm-seed-field-options" data-col-idx="' + h.index + '" placeholder="Opciones separadas por coma">'
                + '  <button type="button" class="tm-btn tm-seed-field-unif-open"><i class="fa-solid fa-shuffle"></i> Unificar respuestas</button>'
                + '  <input type="hidden" class="tm-seed-field-unifications" value="">'
                + '</div>';
            fieldMapRows.appendChild(row);
            const typeSel = row.querySelector('.tm-seed-field-type');
            if (typeSel) typeSel.value = type;
            updateUnifButtonLabel(row);
        });

        mappingEl.classList.remove('tm-hidden');
        mappingEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        syncFieldColumns();
        updateOptionsVisibility();
    }

    function updateOptionsVisibility() {
        fieldMapRows.querySelectorAll('.tm-seed-map-row').forEach(function (row) {
            const checkbox = row.querySelector('.tm-seed-fc');
            const typeSel = row.querySelector('.tm-seed-field-type');
            const optionsInput = row.querySelector('.tm-seed-field-options');
            const unifInput = row.querySelector('.tm-seed-field-unifications');
            const unifBtn = row.querySelector('.tm-seed-field-unif-open');
            if (!checkbox || !typeSel || !optionsInput || !unifInput || !unifBtn) return;
            const allowOptions = ['select', 'multiselect'].indexOf(typeSel.value) !== -1;
            const showConfig = checkbox.checked && allowOptions;
            optionsInput.disabled = !showConfig;
            unifInput.disabled = !showConfig;
            unifBtn.disabled = !showConfig;
            optionsInput.classList.toggle('tm-hidden', !showConfig);
            unifBtn.classList.toggle('tm-hidden', !showConfig);
            if (!allowOptions && optionsInput.value.trim() !== '') {
                optionsInput.value = '';
                optionsInput.dataset.auto = '0';
                optionsInput.dataset.manual = '0';
            }
            if (!allowOptions && unifInput.value.trim() !== '') {
                unifInput.value = '';
                updateUnifButtonLabel(row);
            }
            if (typeSel.value === 'municipio') {
                optionsInput.placeholder = 'Normalización automática por catálogo';
            } else if (typeSel.value === 'select' || typeSel.value === 'multiselect') {
                optionsInput.placeholder = 'Autogenerado desde columna (editable)';
                fillAutoOptionsIfNeeded(row, true);
            } else {
                optionsInput.placeholder = 'Opciones separadas por coma';
            }
            row.classList.toggle('is-active', checkbox.checked);
        });
    }

    function renderPreviewTable(headers, rows, headerRow, dataStartRow) {
        if (!Array.isArray(rows) || rows.length === 0) {
            previewTableWrap.innerHTML = '<div class="tm-seed-preview-empty">No se encontraron filas para vista previa.</div>';
            previewWrap.classList.remove('tm-hidden');
            return;
        }
        const visibleCols = Math.min(60, headers.length || 12);
        let html = '<table class="tm-excel-preview-table"><thead><tr><th class="row-num">Fila</th>';
        for (let c = 0; c < visibleCols; c++) {
            const head = headers[c];
            const title = head ? (head.letter + ' — ' + (head.label || '(vacío)')) : ('Col ' + (c + 1));
            const colIdx = head ? head.index : c;
            html += '<th data-col-idx="' + colIdx + '">' + escapeHtml(title) + '</th>';
        }
        html += '</tr></thead><tbody>';
        rows.forEach(function (r) {
            const isHeader = Number(r.row) === Number(headerRow);
            const isData = Number(r.row) >= Number(dataStartRow);
            const trClass = isHeader ? 'is-header-row' : (isData ? 'is-data-row' : '');
            html += '<tr class="' + trClass + '"><td class="row-num">' + escapeHtml(r.row) + '</td>';
            for (let c = 0; c < visibleCols; c++) {
                const cell = Array.isArray(r.cells) ? (r.cells[c] || '') : '';
                const head = headers[c];
                const colIdx = head ? head.index : c;
                html += '<td data-col-idx="' + colIdx + '">' + escapeHtml(cell) + '</td>';
            }
            html += '</tr>';
        });
        html += '</tbody></table>';
        previewTableWrap.innerHTML = html;
        previewWrap.classList.remove('tm-hidden');
        paintMappedColumns();
    }

    function paintMappedColumns() {
        const mapped = Array.from(fieldMapRows.querySelectorAll('.tm-seed-fc:checked')).map(function (el) { return parseInt(el.value, 10); });
        previewTableWrap.querySelectorAll('[data-col-idx]').forEach(function (cell) {
            const idx = parseInt(cell.getAttribute('data-col-idx'), 10);
            cell.classList.toggle('is-mapped-column', mapped.indexOf(idx) !== -1);
        });
    }

    function syncFieldColumns() {
        const checked = Array.from(fieldMapRows.querySelectorAll('.tm-seed-fc:checked')).map(function (c) { return parseInt(c.value, 10); });
        const types = {};
        const options = {};
        const unifications = {};
        fieldMapRows.querySelectorAll('.tm-seed-map-row').forEach(function (row) {
            const checkbox = row.querySelector('.tm-seed-fc');
            const typeSel = row.querySelector('.tm-seed-field-type');
            const optionsInput = row.querySelector('.tm-seed-field-options');
            const unifInput = row.querySelector('.tm-seed-field-unifications');
            if (!checkbox || !typeSel || !optionsInput || !unifInput || !checkbox.checked) return;
            const idx = parseInt(checkbox.value, 10);
            types[idx] = typeSel.value || 'text';
            options[idx] = optionsInput.value || '';
            unifications[idx] = unifInput.value || '';
        });
        fieldColumnsInput.value = JSON.stringify(checked);
        fieldTypesInput.value = JSON.stringify(types);
        fieldOptionsInput.value = JSON.stringify(options);
        fieldUnificationsInput.value = JSON.stringify(unifications);
        submitBtn.disabled = checked.length === 0;
        paintMappedColumns();
    }

    fieldMapRows.addEventListener('change', function (event) {
        if (event.target.classList.contains('tm-seed-field-type') || event.target.classList.contains('tm-seed-fc')) {
            updateOptionsVisibility();
        }
        syncFieldColumns();
    });
    fieldMapRows.addEventListener('input', function (event) {
        if (event.target.classList.contains('tm-seed-field-options')) {
            event.target.dataset.manual = '1';
            event.target.dataset.auto = '0';
            syncFieldColumns();
        }
    });

    fieldMapRows.addEventListener('click', function (event) {
        const moveBtn = event.target.closest('[data-move-up], [data-move-down]');
        if (moveBtn) {
            const row = moveBtn.closest('.tm-seed-map-row');
            if (!row) return;
            if (moveBtn.hasAttribute('data-move-up')) {
                const prev = row.previousElementSibling;
                if (prev) fieldMapRows.insertBefore(row, prev);
            } else {
                const next = row.nextElementSibling;
                if (next) fieldMapRows.insertBefore(next, row);
            }
            syncFieldColumns();
            updateOptionsVisibility();
            return;
        }

        const btn = event.target.closest('.tm-seed-field-unif-open');
        if (!btn) return;
        const row = btn.closest('.tm-seed-map-row');
        if (!row) return;
        openUnifModalForRow(row);
    });

    unifAddRule.addEventListener('click', function () {
        if (!activeUnifRow) return;
        const from = normalizeOptionLabel(unifFrom.value || '');
        const to = normalizeOptionLabel(unifTo.value || '');
        if (!from || !to) {
            return;
        }
        const hidden = getUnifHiddenInput(activeUnifRow);
        if (!hidden) return;
        const rules = parseUnificationRules(hidden.value);
        const key = normalizeOptionCompareKey(from);
        const filtered = rules.filter(function (r) { return normalizeOptionCompareKey(r.from) !== key; });
        filtered.push({ from: from, to: to });
        hidden.value = serializeUnificationRules(filtered);
        renderUnifRulesInModal(activeUnifRow);
        syncFieldColumns();
        unifFrom.value = '';
        unifTo.value = '';
        unifFrom.focus();
    });

    unifRulesList.addEventListener('click', function (event) {
        const btn = event.target.closest('.tm-seed-unif-remove');
        if (!btn || !activeUnifRow) return;
        const idx = parseInt(btn.getAttribute('data-rule-idx') || '-1', 10);
        if (idx < 0) return;
        const hidden = getUnifHiddenInput(activeUnifRow);
        if (!hidden) return;
        const rules = parseUnificationRules(hidden.value);
        rules.splice(idx, 1);
        hidden.value = serializeUnificationRules(rules);
        renderUnifRulesInModal(activeUnifRow);
        syncFieldColumns();
    });

    unifModal.addEventListener('click', function (event) {
        if (event.target.matches('[data-close="1"]')) {
            closeUnifModal();
        }
    });
    unifClose.addEventListener('click', closeUnifModal);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && unifModal.classList.contains('is-open')) {
            closeUnifModal();
        }
    });

    function renderSheetTabs(sheetNames, activeIndex) {
        if (!sheetNames || sheetNames.length <= 1) {
            sheetTabsEl.classList.add('tm-hidden');
            sheetTabsEl.innerHTML = '';
            return;
        }
        var html = '<div class="tm-seed-sheet-tabs-label"><i class="fa-solid fa-file-excel"></i> Pestañas del archivo:</div><div class="tm-seed-sheet-tabs-list">';
        sheetNames.forEach(function (name, idx) {
            var cls = 'tm-seed-sheet-tab' + (idx === activeIndex ? ' tm-seed-sheet-tab--active' : '');
            html += '<button type="button" class="' + cls + '" data-sheet-idx="' + idx + '">'
                + '<i class="fa-regular fa-file-lines"></i> '
                + String(name).replace(/</g, '&lt;')
                + '</button>';
        });
        html += '</div>';
        sheetTabsEl.innerHTML = html;
        sheetTabsEl.classList.remove('tm-hidden');
    }

    sheetTabsEl.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-sheet-idx]');
        if (!btn) return;
        var idx = parseInt(btn.dataset.sheetIdx, 10);
        if (idx === currentSheetIndex) return;
        currentSheetIndex = idx;
        sheetIndexInput.value = String(idx);
        // Mark active visually immediately
        sheetTabsEl.querySelectorAll('.tm-seed-sheet-tab').forEach(function (t) {
            t.classList.toggle('tm-seed-sheet-tab--active', parseInt(t.dataset.sheetIdx, 10) === idx);
        });
        // Re-read headers for this sheet
        readHeaders(idx);
    });

    let currentHeaders = [];

    function readHeaders(sheetIdx) {
        var f = fileInput.files[0];
        if (!f) { errEl.textContent = 'Selecciona un archivo Excel.'; errEl.classList.remove('tm-hidden'); return; }
        errEl.classList.add('tm-hidden');
        detectNoteEl.classList.add('tm-hidden');

        // Loading state
        var readBtn = document.getElementById('tmSeedReadHeaders');
        var origText = readBtn ? readBtn.innerHTML : '';
        if (readBtn) { readBtn.disabled = true; readBtn.innerHTML = '<i class=\"fa-solid fa-spinner fa-spin\"></i> Leyendo…'; }

        var fd = new FormData();
        fd.append('archivo_excel', f);
        fd.append('header_row', headerRowInput.value || '1');
        fd.append('auto_detect', autoDetectChk.checked ? '1' : '0');
        fd.append('sheet_index', String(sheetIdx));
        // Por defecto NO analizamos opciones; eso ocurre bajo demanda con el
        // botón "Analizar opciones". Mantiene este request liviano.
        fd.append('analyze_options', '0');
        fd.append('_token', csrf);
        csrfFetch(previewUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return safeJsonParse(r); })
            .then(function (j) {
                if (!j.success) { errEl.textContent = j.message || 'Error al leer.'; errEl.classList.remove('tm-hidden'); return; }
                if (typeof j.header_row === 'number') headerRowInput.value = String(j.header_row);
                if (typeof j.data_start_row === 'number') dataRowInput.value = String(j.data_start_row);
                if (j.detection_note) {
                    detectNoteEl.textContent = j.detection_note;
                    detectNoteEl.classList.remove('tm-hidden');
                }
                if (j.sheet_names) {
                    renderSheetTabs(j.sheet_names, typeof j.sheet_index === 'number' ? j.sheet_index : sheetIdx);
                }
                currentColumnSuggestions = (j.column_suggestions && typeof j.column_suggestions === 'object') ? j.column_suggestions : {};
                currentHeaders = Array.isArray(j.headers) ? j.headers : [];
                colMr.dataset.set = '';
                colMun.dataset.set = '';
                fillSelects(currentHeaders);
                renderPreviewTable(currentHeaders, j.preview_rows || [], j.header_row || 1, j.data_start_row || ((j.header_row || 1) + 1));
                // Mostrar la barra para "Analizar opciones" si el backend la
                // dejó pendiente. Restablece estado al re-leer.
                showAnalyzeBar(!!j.suggestions_pending);
            }).catch(function (e) {
                errEl.textContent = e.message || 'Error al procesar el archivo.';
                errEl.classList.remove('tm-hidden');
            }).finally(function () {
                if (readBtn) { readBtn.disabled = false; readBtn.innerHTML = origText; }
            });
    }

    function showAnalyzeBar(visible) {
        var bar = document.getElementById('tmSeedAnalyzeBar');
        if (!bar) return;
        bar.classList.toggle('tm-hidden', !visible);
        var note = document.getElementById('tmSeedAnalyzeNote');
        if (note) {
            note.classList.add('tm-hidden');
            note.textContent = '';
        }
        var btn = document.getElementById('tmSeedAnalyzeBtn');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-magnifying-glass-chart" aria-hidden="true"></i> Analizar opciones';
        }
    }

    function analyzeOptions() {
        var f = fileInput.files[0];
        if (!f) return;
        var btn = document.getElementById('tmSeedAnalyzeBtn');
        var note = document.getElementById('tmSeedAnalyzeNote');
        var url = (window.TM_ADMIN_SEED_EXCEL_BOOT && window.TM_ADMIN_SEED_EXCEL_BOOT.analyzeOptionsUrl) || '';
        if (!url) return;
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Analizando opciones…'; }
        if (note) {
            note.classList.remove('tm-hidden');
            note.textContent = 'Esto puede tardar unos segundos en archivos grandes.';
        }
        var fd = new FormData();
        fd.append('archivo_excel', f);
        fd.append('header_row', headerRowInput.value || '1');
        fd.append('sheet_index', sheetIndexInput.value || '0');
        fd.append('headers', JSON.stringify(currentHeaders || []));
        fd.append('_token', csrf);
        csrfFetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return safeJsonParse(r); })
            .then(function (j) {
                if (!j.success) {
                    if (note) note.textContent = j.message || 'No fue posible analizar.';
                    return;
                }
                currentColumnSuggestions = (j.column_suggestions && typeof j.column_suggestions === 'object') ? j.column_suggestions : {};
                if (note) { note.textContent = 'Listo. Las opciones por columna se aplicarán a campos tipo "Lista" / "Selección múltiple".'; }
                if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i> Opciones analizadas'; }
                // Re-aplicar sugerencias a filas tipo Lista/Selección múltiple
                // que no tengan opciones manuales.
                if (fieldMapRows) {
                    fieldMapRows.querySelectorAll('.tm-seed-map-row').forEach(function (row) {
                        try { fillAutoOptionsIfNeeded(row, true); } catch (_) {}
                    });
                }
            }).catch(function (e) {
                if (note) note.textContent = e.message || 'Error al analizar opciones.';
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-magnifying-glass-chart" aria-hidden="true"></i> Reintentar análisis'; }
            });
    }

    document.getElementById('tmSeedReadHeaders').addEventListener('click', function () {
        currentSheetIndex = parseInt(sheetIndexInput.value || '0', 10);
        readHeaders(currentSheetIndex);
    });

    var analyzeBtnEl = document.getElementById('tmSeedAnalyzeBtn');
    if (analyzeBtnEl) analyzeBtnEl.addEventListener('click', analyzeOptions);

    indef.addEventListener('change', function () {
        expires.disabled = indef.checked;
        expires.required = !indef.checked;
    });
    expires.disabled = indef.checked;
    expires.required = !indef.checked;

    // ====================================================================
    //  Configuración "Evento cifrado" (mismo UX que crear módulo normal)
    // ====================================================================
    const encryptedToggle = document.getElementById('tmSeedEncryptedToggle');
    const encryptedConfigBtn = document.getElementById('tmSeedEncryptedConfigBtn');
    const encryptedDurationInput = document.getElementById('tmSeedEncryptedEditDuration');
    const encryptedPdfPassInput = document.getElementById('tmSeedEncryptedPdfPassword');
    const encryptedPdfPassConfirmInput = document.getElementById('tmSeedEncryptedPdfPasswordConfirm');

    function tmSeedToggleEncryptedConfig() {
        if (encryptedConfigBtn) {
            encryptedConfigBtn.style.display = (encryptedToggle && encryptedToggle.checked) ? '' : 'none';
        }
    }
    if (encryptedToggle) encryptedToggle.addEventListener('change', tmSeedToggleEncryptedConfig);
    if (encryptedConfigBtn) {
        encryptedConfigBtn.addEventListener('click', function () {
            if (typeof Swal === 'undefined') return;
            Swal.fire({
                title: 'Configurar evento cifrado',
                html:
                    '<label style="display:grid;gap:6px;text-align:left;margin-bottom:10px;"><span>Tiempo de edición autorizado</span><select id="tmSeedSwalDuration" class="swal2-select" style="width:100%;margin:0;"><option value="1">1 hora</option><option value="2">2 horas</option><option value="3">3 horas</option><option value="24">1 día</option></select></label>' +
                    '<label style="display:grid;gap:6px;text-align:left;margin-bottom:10px;"><span>Contraseña para PDF</span><input id="tmSeedSwalPdfPass" type="password" class="swal2-input" style="width:100%;margin:0;" autocomplete="new-password"></label>' +
                    '<label style="display:grid;gap:6px;text-align:left;"><span>Confirmar contraseña</span><input id="tmSeedSwalPdfPassConfirm" type="password" class="swal2-input" style="width:100%;margin:0;" autocomplete="new-password"></label>',
                didOpen: function () {
                    document.getElementById('tmSeedSwalDuration').value = encryptedDurationInput ? String(encryptedDurationInput.value || '1') : '1';
                },
                preConfirm: function () {
                    const duration = document.getElementById('tmSeedSwalDuration').value || '1';
                    const pass = document.getElementById('tmSeedSwalPdfPass').value || '';
                    const confirm = document.getElementById('tmSeedSwalPdfPassConfirm').value || '';
                    if (pass !== '' && pass.length < 4) {
                        Swal.showValidationMessage('La contraseña debe tener al menos 4 caracteres.');
                        return false;
                    }
                    if (pass !== confirm) {
                        Swal.showValidationMessage('La confirmación no coincide.');
                        return false;
                    }
                    return { duration: duration, pass: pass };
                },
                confirmButtonText: 'Guardar',
                showCancelButton: true,
                cancelButtonText: 'Cancelar'
            }).then(function (result) {
                if (!result.isConfirmed || !result.value) return;
                if (encryptedDurationInput) encryptedDurationInput.value = result.value.duration;
                if (encryptedPdfPassInput) encryptedPdfPassInput.value = result.value.pass || '';
                if (encryptedPdfPassConfirmInput) encryptedPdfPassConfirmInput.value = result.value.pass || '';
            });
        });
    }
    tmSeedToggleEncryptedConfig();

    // ====================================================================
    //  Submit AJAX + procesamiento por lotes para archivos grandes (XLSX)
    // ====================================================================
    const seedForm = document.getElementById('tmSeedForm');
    const progressWrap = document.getElementById('tmSeedProgressWrap');
    const progressBar = document.getElementById('tmSeedProgressBar');
    const progressPct = document.getElementById('tmSeedProgressPct');
    const progressDetail = document.getElementById('tmSeedProgressDetail');
    const progressTitle = document.getElementById('tmSeedProgressTitle');
    const progressCancel = document.getElementById('tmSeedProgressCancel');
    let deferredCancelUrl = null;
    let deferredCancelled = false;
    let tmSeedIsProcessing = false; // true mientras hay subida o lotes en curso

    function setProcessingFlag(isOn) {
        tmSeedIsProcessing = !!isOn;
    }

    // Advertencia nativa del navegador al cerrar / recargar / salir si hay
    // proceso en curso. El usuario decide si confirma o no.
    window.addEventListener('beforeunload', function (e) {
        if (!tmSeedIsProcessing) return;
        const msg = 'La carga del módulo está en curso. Si sales ahora se cancelará el proceso y se borrarán los registros parciales.';
        e.preventDefault();
        e.returnValue = msg; // Chrome / Firefox
        return msg; // Safari
    });

    // Cuando el usuario confirma salir (pagehide), avisamos al backend para
    // borrar archivo + módulo parcial. Usamos sendBeacon porque las
    // peticiones normales se cancelan al cerrar pestaña.
    window.addEventListener('pagehide', function () {
        if (!tmSeedIsProcessing || !deferredCancelUrl) return;
        try {
            const fd = new FormData();
            fd.append('_token', csrf);
            if (navigator.sendBeacon) {
                navigator.sendBeacon(deferredCancelUrl, fd);
            } else {
                // Fallback síncrono (poco común hoy en día)
                const xhr = new XMLHttpRequest();
                xhr.open('POST', deferredCancelUrl, false);
                xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.send(fd);
            }
        } catch (_) { /* ignora errores de cleanup */ }
    });

    function showProgress(title) {
        if (!progressWrap) return;
        progressWrap.classList.remove('tm-hidden', 'is-error', 'is-success');
        if (progressTitle) progressTitle.textContent = title || 'Procesando registros…';
        setProgress(0, 'Subiendo archivo…');
    }
    function setProgress(pct, detail) {
        if (!progressBar || !progressPct) return;
        const safe = Math.max(0, Math.min(100, Math.round(pct)));
        progressBar.style.width = safe + '%';
        progressPct.textContent = safe + '%';
        if (progressDetail && typeof detail === 'string') progressDetail.textContent = detail;
    }
    function setProgressError(msg) {
        if (!progressWrap) return;
        progressWrap.classList.add('is-error');
        progressWrap.classList.remove('is-success');
        if (progressDetail) progressDetail.textContent = msg;
        if (progressCancel) progressCancel.textContent = 'Cerrar';
        setProcessingFlag(false);
    }
    function setProgressSuccess(msg) {
        if (!progressWrap) return;
        progressWrap.classList.add('is-success');
        progressWrap.classList.remove('is-error');
        setProgress(100, msg);
        if (progressCancel) progressCancel.style.display = 'none';
        setProcessingFlag(false);
    }
    function hideProgress() {
        if (!progressWrap) return;
        progressWrap.classList.add('tm-hidden');
        progressWrap.classList.remove('is-error', 'is-success');
    }

    if (progressCancel) {
        progressCancel.addEventListener('click', async function () {
            if (progressCancel.textContent === 'Cerrar') {
                hideProgress();
                deferredCancelUrl = null;
                if (submitBtn) submitBtn.disabled = false;
                setProcessingFlag(false);
                return;
            }
            if (!deferredCancelUrl) return;
            if (!confirm('¿Cancelar la carga? Se borrarán los registros parciales.')) return;
            deferredCancelled = true;
            setProcessingFlag(false); // ya no preguntar al salir
            try {
                const fd = new FormData();
                fd.append('_token', csrf);
                await csrfFetch(deferredCancelUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            } catch (_) {}
            setProgressError('Carga cancelada por el usuario.');
        });
    }

    async function postFormJson(url, formData) {
        const res = await csrfFetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const ct = res.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
            const text = await res.text().catch(function () { return ''; });
            if (!res.ok) throw new Error(text.substring(0, 300) || ('HTTP ' + res.status));
            return { __nonJson: true, status: res.status, text: text };
        }
        const data = await res.json();
        if (!res.ok) {
            const msg = data && data.message ? data.message : ('HTTP ' + res.status);
            throw new Error(msg);
        }
        return data;
    }

    /**
     * POST con XMLHttpRequest para poder reportar progreso de subida en
     * tiempo real (fetch() no expone xhr.upload.onprogress).
     * onProgress recibe (loaded, totalHint) donde totalHint es e.total del XHR
     * o 0 si no es computable (usar tamaño del archivo como respaldo).
     * uploadTotalHint: tamaño del archivo (p. ej. file.size) para porcentaje
     * fiable cuando lengthComputable es false o solo hay un evento al final.
     */
    function postFormJsonWithProgress(url, formData, onProgress, uploadTotalHint) {
        return new Promise(function (resolve, reject) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
            xhr.withCredentials = true;
            const hint = typeof uploadTotalHint === 'number' && uploadTotalHint > 0 ? uploadTotalHint : 0;
            xhr.upload.onloadstart = function () {
                if (typeof onProgress === 'function') {
                    onProgress(0, hint);
                }
            };
            xhr.upload.onprogress = function (e) {
                if (typeof onProgress === 'function') {
                    const xhrTotal = e.lengthComputable && (e.total || 0) > 0 ? e.total : 0;
                    const total = xhrTotal > 0 ? xhrTotal : hint;
                    onProgress(e.loaded || 0, total);
                }
            };
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                const ct = xhr.getResponseHeader('Content-Type') || '';
                if (!ct.includes('application/json')) {
                    if (xhr.status === 0) {
                        return reject(new Error('La conexión se interrumpió (red suspendida, equipo en reposo o pestaña en segundo plano). Reintenta sin cambiar de pestaña.'));
                    }
                    if (xhr.status === 419) {
                        return reject(new Error('Tu sesión expiró. Recarga la página.'));
                    }
                    return reject(new Error(xhr.responseText.substring(0, 300) || ('HTTP ' + xhr.status)));
                }
                let data;
                try {
                    data = JSON.parse(xhr.responseText);
                } catch (_) {
                    return reject(new Error('Respuesta inválida del servidor.'));
                }
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(data);
                } else {
                    reject(new Error(data && data.message ? data.message : ('HTTP ' + xhr.status)));
                }
            };
            xhr.onerror = function () {
                reject(new Error('Error de red: la conexión se cortó (p. ej. ERR_NETWORK_IO_SUSPENDED si el navegador suspendió la red).'));
            };
            xhr.ontimeout = function () { reject(new Error('La subida tardó demasiado (tiempo de espera agotado).')); };
            xhr.timeout = 0;
            xhr.send(formData);
        });
    }

    function sleep(ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    }

    /**
     * Fallos típicos de Chrome/Chromium: pestaña en segundo plano, suspensión,
     * ERR_NETWORK_IO_SUSPENDED (no siempre llega al mensaje del Error).
     */
    function isRetryableSeedNetworkError(err) {
        const msg = String(err && err.message ? err.message : err || '').toLowerCase();
        if (!msg) return true;
        if (msg.indexOf('abort') >= 0) return false;
        if (msg.indexOf('sesión') >= 0 && msg.indexOf('expir') >= 0) return false;
        if (msg.indexOf('respuesta inválida') >= 0) return false;
        return (
            msg.indexOf('no se pudo conectar') >= 0 ||
            msg.indexOf('error de red') >= 0 ||
            msg.indexOf('interrumpió') >= 0 ||
            msg.indexOf('suspend') >= 0 ||
            msg.indexOf('io_suspended') >= 0 ||
            msg.indexOf('failed to fetch') >= 0 ||
            msg.indexOf('networkerror') >= 0 ||
            msg.indexOf('network changed') >= 0 ||
            msg.indexOf('load failed') >= 0
        );
    }

    function humanizeSeedUploadError(err) {
        const base = err && err.message ? err.message : String(err);
        if (!isRetryableSeedNetworkError(err)) {
            return base;
        }
        return base + ' Conviene dejar esta pestaña al frente y evitar suspensión del equipo mientras se envía el archivo y el servidor prepara el módulo (varios minutos en archivos grandes).';
    }

    function formatBytes(bytes) {
        if (!isFinite(bytes) || bytes <= 0) return '0 B';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    async function processBatchesLoop(deferred) {
        deferredCancelUrl = deferred.cancel_url;
        deferredCancelled = false;
        const totalRows = parseInt(deferred.total_rows, 10) || 0; // 0 = desconocido
        const batchUrl = deferred.batch_url;
        let processedTotal = 0;
        let hasMore = true;

        if (progressTitle) progressTitle.textContent = 'Procesando registros…';
        const initialDetail = totalRows > 0
            ? ('Procesando 0 / ~' + totalRows + ' filas…')
            : 'Procesando filas…';
        setProgress(0, initialDetail);

        while (hasMore) {
            if (deferredCancelled) return;
            const fd = new FormData();
            fd.append('_token', csrf);
            fd.append('batch_size', String(deferred.batch_size || 1000));
            let result;
            try {
                result = await postFormJson(batchUrl, fd);
            } catch (err) {
                setProgressError('Error procesando lote: ' + (err && err.message ? err.message : err));
                throw err;
            }
            processedTotal = parseInt(result.processed_total, 10) || processedTotal;
            hasMore = !!result.has_more;
            const stats = result.stats || {};

            let pct;
            let detail;
            if (totalRows > 0) {
                pct = Math.min(99, (processedTotal / totalRows) * 100);
                detail = 'Procesando ' + processedTotal + ' / ~' + totalRows + ' filas · ' +
                    'creados: ' + (stats.created || 0) + ' · ' +
                    'descartados: ' + (stats.discarded || 0);
            } else {
                // Total desconocido: avance suave hacia 95% sin nunca llegar
                pct = Math.min(95, 5 + (processedTotal / (processedTotal + 500)) * 95);
                detail = 'Procesando ' + processedTotal + ' filas · ' +
                    'creados: ' + (stats.created || 0) + ' · ' +
                    'descartados: ' + (stats.discarded || 0);
            }
            setProgress(pct, detail);
            if (!hasMore) break;
        }
        if (deferredCancelled) return;

        // Finalizar
        if (progressTitle) progressTitle.textContent = 'Finalizando…';
        setProgress(100, 'Sincronizando delegados y cerrando módulo…');
        const finFd = new FormData();
        finFd.append('_token', csrf);
        let finalResult;
        try {
            finalResult = await postFormJson(deferred.finalize_url, finFd);
        } catch (err) {
            setProgressError('Error al finalizar: ' + (err && err.message ? err.message : err));
            throw err;
        }
        setProgressSuccess('¡Listo! ' + (finalResult.stats && finalResult.stats.created ? finalResult.stats.created : 0) + ' registro(s) creados. Redirigiendo…');
        setTimeout(function () {
            window.location.href = finalResult.redirect_url || deferred.redirect_url;
        }, 900);
    }

    seedForm.addEventListener('submit', async function (ev) {
        syncFieldColumns();

        // Solo intercepta cuando hay un .xlsx (las únicas que soporta Spout).
        const file = fileInput && fileInput.files && fileInput.files[0];
        const isXlsx = file && /\.xlsx$/i.test(file.name);
        if (!file || !isXlsx) {
            // Submit nativo (XLS o sin archivo → flujo síncrono original).
            return;
        }

        ev.preventDefault();
        if (submitBtn) submitBtn.disabled = true;
        setProcessingFlag(true); // activa la advertencia de "salir"

        const totalBytes = file.size;
        showProgress('Subiendo archivo…');
        setProgress(0, 'Subiendo archivo… 0 / ' + formatBytes(totalBytes));

        const progressCb = function (loaded, total) {
            const knownTotal = total > 0 ? total : totalBytes;
            const clampedLoaded = knownTotal > 0 ? Math.min(loaded, knownTotal) : loaded;
            const rawPct = knownTotal > 0 ? (clampedLoaded / knownTotal) * 100 : 0;
            const pct = knownTotal > 0 ? Math.min(99, rawPct) : 0;
            let detail = 'Subiendo archivo… ' + formatBytes(clampedLoaded) + ' / ' + formatBytes(knownTotal);
            if (knownTotal > 0 && clampedLoaded >= knownTotal) {
                detail += ' · Esperando respuesta del servidor…';
            }
            setProgress(pct, detail);
        };

        let wakeLockHandle = null;
        try {
            try {
                if (navigator.wakeLock && typeof navigator.wakeLock.request === 'function') {
                    wakeLockHandle = await navigator.wakeLock.request('screen');
                }
            } catch (_) { /* no soportado o denegado */ }

            const maxAttempts = 3;
            let initial;
            try {
                for (let attempt = 1; attempt <= maxAttempts; attempt++) {
                    if (attempt > 1) {
                        if (progressTitle) progressTitle.textContent = 'Reintentando subida…';
                        setProgress(0, 'Intento ' + attempt + ' de ' + maxAttempts + ' · Mantén esta pestaña visible…');
                        await sleep(900 * attempt);
                    }
                    const fd = new FormData(seedForm);
                    fd.set('defer_processing', '1');
                    try {
                        initial = await postFormJsonWithProgress(seedForm.action, fd, progressCb, totalBytes);
                        break;
                    } catch (err) {
                        if (attempt < maxAttempts && isRetryableSeedNetworkError(err)) {
                            continue;
                        }
                        throw err;
                    }
                }
            } catch (err) {
                setProgressError('Error: ' + humanizeSeedUploadError(err));
                if (submitBtn) submitBtn.disabled = false;
                return;
            }

            // Subida completa → reinicia barra para fase de procesamiento
            if (progressTitle) progressTitle.textContent = 'Preparando módulo…';
            setProgress(0, 'Archivo recibido. Preparando módulo…');

            if (!initial || initial.__nonJson) {
                setProgressError('Respuesta inesperada del servidor.');
                if (submitBtn) submitBtn.disabled = false;
                return;
            }
            if (initial.success === false) {
                setProgressError(initial.message || 'No se pudo iniciar la carga.');
                if (submitBtn) submitBtn.disabled = false;
                return;
            }

            if (initial.deferred) {
                try {
                    await processBatchesLoop(initial);
                } catch (_) {
                    if (submitBtn) submitBtn.disabled = false;
                }
                return;
            }

            // No deferida (archivo pequeño) → si nos devolvieron redirect_url lo seguimos
            setProcessingFlag(false);
            if (initial.redirect_url) {
                window.location.href = initial.redirect_url;
                return;
            }
            window.location.reload();
        } finally {
            if (wakeLockHandle && typeof wakeLockHandle.release === 'function') {
                wakeLockHandle.release().catch(function () {});
            }
        }
    });
});
