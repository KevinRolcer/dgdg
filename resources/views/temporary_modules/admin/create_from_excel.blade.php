@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
@endpush

@section('content')
<section class="tm-page">
    <article class="content-card tm-card tm-seed-excel-page">
        @if ($errors->any())
            <div class="inline-alert inline-alert-error tm-seed-alert" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="tm-head tm-head-stack" style="margin-bottom:4px;">
            <span></span>
            <a href="{{ route('temporary-modules.admin.index') }}" class="tm-btn">Volver</a>
        </div>

        <form action="{{ route('temporary-modules.admin.seed-store') }}" method="POST" enctype="multipart/form-data" id="tmSeedForm" class="tm-seed-form">
            @csrf

            {{-- Paso 1: solo archivo y detección (sin duplicar nombre/vigencia) --}}
            <div class="tm-seed-panel">
                <div class="tm-seed-panel-head">
                    <span class="tm-seed-step-badge">1</span>
                    <div>
                        <h3 class="tm-seed-panel-title">Archivo y tabla</h3>
                        <p class="tm-seed-panel-desc">Detectamos encabezados (N°, MR, Municipio, Acción…). Requiere <code>.xlsx</code> / <code>.xls</code>.</p>
                    </div>
                </div>
                <div class="tm-seed-panel-body">
                    <label class="tm-inline-check tm-seed-check">
                        <input type="checkbox" id="tmSeedAutoDetect" checked> Detectar tabla automáticamente
                    </label>
                    <div class="tm-excel-grid tm-seed-grid-tight">
                        <label class="tm-seed-label">Fila encabezados
                            <input type="number" name="header_row" id="tmSeedHeaderRow" value="{{ old('header_row', 1) }}" min="1" max="500" class="tm-input">
                        </label>
                        <label class="tm-seed-label">Primera fila de ítems
                            <input type="number" name="data_start_row" id="tmSeedDataRow" value="{{ old('data_start_row', 2) }}" min="2" max="50000" class="tm-input">
                        </label>
                    </div>
                    <div class="tm-seed-file-zone">
                        <label class="tm-seed-file-label">
                            <span class="tm-seed-file-icon" aria-hidden="true"><i class="fa-regular fa-file-excel"></i></span>
                            <span class="tm-seed-file-text">Archivo Excel</span>
                            <input type="file" name="archivo_excel" id="tmSeedFile" accept=".xlsx,.xls" required class="tm-seed-file-input">
                        </label>
                    </div>
                    <button type="button" class="tm-btn tm-btn-primary tm-seed-read-btn" id="tmSeedReadHeaders">
                        <i class="fa-solid fa-table-columns" aria-hidden="true"></i> Leer encabezados
                    </button>
                    <div id="tmSeedDetectNote" class="tm-seed-note tm-seed-note--ok tm-hidden" role="status"></div>
                    <div id="tmSeedPreviewErr" class="tm-seed-note tm-seed-note--err tm-hidden"></div>
                    <div id="tmSeedSheetTabs" class="tm-seed-sheet-tabs tm-hidden"></div>
                    <div id="tmSeedPreviewWrap" class="tm-seed-preview-wrap tm-hidden">
                        <div class="tm-seed-preview-title"><i class="fa-regular fa-eye"></i> Vista previa del documento</div>
                        <div class="tm-seed-preview-table-wrap" id="tmSeedPreviewTableWrap">
                            <div class="tm-seed-preview-empty">Carga un archivo y pulsa "Leer encabezados" para ver la tabla.</div>
                        </div>
                    </div>
                </div>
            </div>

            <input type="hidden" name="sheet_index" id="tmSeedSheetIndex" value="0">

            {{-- Paso 2: datos del módulo + mapeo (visible tras leer Excel) --}}
            <div id="tmSeedMapping" class="tm-hidden tm-seed-panel">
                <div class="tm-seed-panel-head">
                    <span class="tm-seed-step-badge">2</span>
                    <div>
                        <h3 class="tm-seed-panel-title">Crear módulo y columnas</h3>
                        <p class="tm-seed-panel-desc">Nombre, vigencia y qué columna es municipio / MR / campos del módulo.</p>
                    </div>
                </div>
                <div class="tm-seed-panel-body">
                    <div class="tm-seed-module-fields">
                        <label class="tm-seed-label tm-seed-label--full">
                            Nombre del módulo <span class="tm-seed-req">*</span>
                            <input type="text" name="name" id="tmSeedName" value="{{ old('name') }}" required class="tm-input" placeholder="Ej. Acciones microrregionales 2025" autocomplete="off">
                        </label>
                        <label class="tm-seed-label tm-seed-label--full">
                            Descripción <span class="tm-seed-opt">(opcional)</span>
                            <textarea name="description" id="tmSeedDescription" rows="2" class="tm-input tm-textarea" placeholder="Notas internas o contexto del módulo">{{ old('description') }}</textarea>
                        </label>
                        <div class="tm-seed-date-row">
                            <label class="tm-seed-label">
                                Visible hasta
                                <input type="date" name="expires_at" id="tmSeedExpires" value="{{ old('expires_at') }}" class="tm-input">
                            </label>
                            <label class="tm-inline-check tm-seed-check tm-seed-indef">
                                <input type="checkbox" name="is_indefinite" value="1" id="tmSeedIndef" @checked(old('is_indefinite'))> Indefinido
                            </label>
                        </div>
                    </div>

                    <hr class="tm-seed-divider">

                    <h4 class="tm-seed-subtitle">Mapeo de columnas</h4>
                    <p class="tm-seed-hint-block tm-seed-hint-block--intro">Selecciona las columnas que serán campos del módulo, define su tipo y, para listas, se propondrán opciones automáticamente desde los datos del Excel.</p>
                    <div class="tm-seed-importer-layout">
                        <div class="tm-seed-importer-main">
                            <label class="tm-inline-check tm-seed-check tm-seed-check--block">
                                <input type="checkbox" id="tmSeedMrOnly"> Sin columna <strong>Municipio</strong> (solo microregión)
                            </label>
                            <div class="tm-excel-grid">
                                <label class="tm-seed-label" id="tmSeedWrapMun">Municipio <span class="tm-seed-hint">(principal)</span>
                                    <select name="col_municipio" id="tmSeedColMun" class="tm-input"></select>
                                </label>
                                <label class="tm-seed-label" id="tmSeedWrapMr">Microregión <span class="tm-seed-hint" id="tmSeedMrHint">(opcional)</span>
                                    <select name="col_microrregion" id="tmSeedColMr" class="tm-input"></select>
                                </label>
                            </div>
                            <input type="hidden" name="col_municipio" id="tmSeedMunSentinel" value="-1" disabled>

                            <div class="tm-seed-map-head" aria-hidden="true">
                                <span>Columna</span>
                                <span>Tipo</span>
                                <span>Opciones</span>
                            </div>
                            <div id="tmSeedFieldMapRows" class="tm-seed-map-rows"></div>
                            <input type="hidden" name="field_columns" id="tmSeedFieldColumns" value="">
                            <input type="hidden" name="field_types" id="tmSeedFieldTypes" value="{}">
                            <input type="hidden" name="field_options" id="tmSeedFieldOptions" value="{}">
                            <input type="hidden" name="field_unifications" id="tmSeedFieldUnifications" value="{}">
                        </div>
                    </div>

                    <div class="tm-seed-actions">
                        <button type="submit" class="tm-btn tm-btn-primary tm-seed-submit" id="tmSeedSubmit" disabled>
                            <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i> Crear módulo y registros
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div class="tm-modal tm-seed-unif-modal" id="tmSeedUnifModal" aria-hidden="true">
            <div class="tm-modal-backdrop" data-close="1"></div>
            <div class="tm-modal-dialog tm-seed-unif-dialog" role="dialog" aria-modal="true" aria-labelledby="tmSeedUnifTitle">
                <div class="tm-modal-head">
                    <div>
                        <h3 id="tmSeedUnifTitle">Unificar respuestas</h3>
                        <p class="tm-modal-subtitle" id="tmSeedUnifSubtitle">Elige valores de origen y su valor unificado.</p>
                    </div>
                    <button type="button" class="tm-modal-close" id="tmSeedUnifClose" aria-label="Cerrar modal"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="tm-modal-body">
                    <div class="tm-seed-unif-grid">
                        <label class="tm-seed-label">Origen
                            <select id="tmSeedUnifFrom" class="tm-input"></select>
                        </label>
                        <label class="tm-seed-label">Unificar como
                            <select id="tmSeedUnifTo" class="tm-input"></select>
                        </label>
                    </div>
                    <div class="tm-seed-unif-actions">
                        <button type="button" class="tm-btn tm-btn-primary" id="tmSeedUnifAddRule">
                            <i class="fa-solid fa-plus"></i> Agregar regla
                        </button>
                    </div>
                    <div id="tmSeedUnifRulesEmpty" class="tm-seed-preview-empty">No hay reglas configuradas para esta columna.</div>
                    <div id="tmSeedUnifRulesList" class="tm-seed-unif-rules tm-hidden"></div>
                </div>
            </div>
        </div>
    </article>
</section>
@endsection

@push('scripts')
@php
    $tmSeedPreviewUrlForJs = route('temporary-modules.admin.seed-preview');
    $tmCsrfRefreshUrlForJs = route('csrf.refresh');
    $tmLoginUrlForJs = route('login');
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    const previewUrl = @json($tmSeedPreviewUrlForJs);
    let csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function csrfFetch(url, opts = {}) {
        const res = await fetch(url, opts);
        if (res.status === 419) {
            try {
                const r = await fetch(@json($tmCsrfRefreshUrlForJs), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                if (r.redirected || r.status === 401) { window.location.href = @json($tmLoginUrlForJs); return res; }
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
        headers.forEach(function (h) {
            const n = (h.label || '').toUpperCase();
            if (/MICRORREGION|MICRORREGI|MR\b/.test(n) && !colMr.dataset.set) { colMr.value = String(h.index); colMr.dataset.set = '1'; }
            if (/MUNICIPIO|MUNICIP/.test(n) && !colMun.dataset.set) { colMun.value = String(h.index); colMun.dataset.set = '1'; }
        });
        if (!colMr.dataset.set) colMr.value = '-1';
        if (!colMun.dataset.set && colMun.options.length) colMun.selectedIndex = 0;
        syncMrMunMode();

        const typeOptions = Object.keys(fieldTypesCatalog).map(function (key) {
            return '<option value="' + key + '">' + fieldTypesCatalog[key] + '</option>';
        }).join('');

        fieldMapRows.innerHTML = '';
        headers.forEach(function (h) {
            const norm = String(h.label || '').toUpperCase();
            const isGeoColumn = /MICRORREGION|MICRORREGI|\bMR\b|MUNICIPIO|MUNICIP/.test(norm);
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
        const visibleCols = Math.min(24, headers.length || 12);
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
                colMr.dataset.set = '';
                colMun.dataset.set = '';
                fillSelects(j.headers || []);
                renderPreviewTable(j.headers || [], j.preview_rows || [], j.header_row || 1, j.data_start_row || ((j.header_row || 1) + 1));
            }).catch(function (e) {
                errEl.textContent = e.message || 'Error al procesar el archivo.';
                errEl.classList.remove('tm-hidden');
            }).finally(function () {
                if (readBtn) { readBtn.disabled = false; readBtn.innerHTML = origText; }
            });
    }

    document.getElementById('tmSeedReadHeaders').addEventListener('click', function () {
        currentSheetIndex = parseInt(sheetIndexInput.value || '0', 10);
        readHeaders(currentSheetIndex);
    });

    indef.addEventListener('change', function () {
        expires.disabled = indef.checked;
        expires.required = !indef.checked;
    });
    expires.disabled = indef.checked;
    expires.required = !indef.checked;

    document.getElementById('tmSeedForm').addEventListener('submit', function () { syncFieldColumns(); });
});
</script>
@endpush
