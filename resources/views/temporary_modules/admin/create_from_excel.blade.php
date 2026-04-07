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

                            <p class="tm-seed-hint-block">Selecciona columnas para crear campos y define su tipo:</p>
                            <div id="tmSeedFieldMapRows" class="tm-seed-map-rows"></div>
                            <input type="hidden" name="field_columns" id="tmSeedFieldColumns" value="">
                            <input type="hidden" name="field_types" id="tmSeedFieldTypes" value="{}">
                            <input type="hidden" name="field_options" id="tmSeedFieldOptions" value="{}">
                        </div>
                        <aside class="tm-seed-importer-side">
                            <h5 class="tm-seed-importer-side-title"><i class="fa-solid fa-wand-magic-sparkles"></i> Recomendaciones</h5>
                            <ul class="tm-seed-importer-tips">
                                <li>Usa <strong>Texto</strong> para valores libres y descripciones.</li>
                                <li>Usa <strong>Número</strong> para metas, montos o cantidades.</li>
                                <li>Usa <strong>Lista</strong> cuando tengas valores repetidos (estatus, avance).</li>
                                <li>Si una columna tiene varios valores por celda, usa <strong>Selección múltiple</strong>.</li>
                                <li>Con <strong>Municipio</strong> se normaliza contra el catálogo global (incluye municipios de todas las microrregiones).</li>
                            </ul>
                        </aside>
                    </div>

                    <div class="tm-seed-actions">
                        <button type="submit" class="tm-btn tm-btn-primary tm-seed-submit" id="tmSeedSubmit" disabled>
                            <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i> Crear módulo y registros
                        </button>
                    </div>
                </div>
            </div>
        </form>
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
        semaforo: 'Semáforo'
    };

    let currentSheetIndex = 0;

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
            const row = document.createElement('label');
            row.className = 'tm-seed-map-row';
            row.innerHTML = ''
                + '<div class="tm-seed-map-col tm-seed-map-col--pick">'
                + '  <input type="checkbox" class="tm-seed-fc" value="' + h.index + '" ' + (shouldCheck ? 'checked' : '') + '>'
                + '  <span class="tm-seed-map-col-label">' + h.letter + ' — ' + escapeHtml(h.label || '(vacío)') + '</span>'
                + '</div>'
                + '<div class="tm-seed-map-col tm-seed-map-col--type">'
                + '  <select class="tm-input tm-seed-field-type" data-col-idx="' + h.index + '">' + typeOptions + '</select>'
                + '</div>'
                + '<div class="tm-seed-map-col tm-seed-map-col--options">'
                + '  <input type="text" class="tm-input tm-seed-field-options" data-col-idx="' + h.index + '" placeholder="Opciones separadas por coma">'
                + '</div>';
            fieldMapRows.appendChild(row);
            const typeSel = row.querySelector('.tm-seed-field-type');
            if (typeSel) typeSel.value = type;
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
            if (!checkbox || !typeSel || !optionsInput) return;
            const allowOptions = ['select', 'multiselect'].indexOf(typeSel.value) !== -1;
            optionsInput.disabled = !checkbox.checked || !allowOptions;
            if (!allowOptions && optionsInput.value.trim() !== '') {
                optionsInput.value = '';
            }
            if (typeSel.value === 'municipio') {
                optionsInput.placeholder = 'Normalización automática por catálogo';
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
        fieldMapRows.querySelectorAll('.tm-seed-map-row').forEach(function (row) {
            const checkbox = row.querySelector('.tm-seed-fc');
            const typeSel = row.querySelector('.tm-seed-field-type');
            const optionsInput = row.querySelector('.tm-seed-field-options');
            if (!checkbox || !typeSel || !optionsInput || !checkbox.checked) return;
            const idx = parseInt(checkbox.value, 10);
            types[idx] = typeSel.value || 'text';
            options[idx] = optionsInput.value || '';
        });
        fieldColumnsInput.value = JSON.stringify(checked);
        fieldTypesInput.value = JSON.stringify(types);
        fieldOptionsInput.value = JSON.stringify(options);
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
            syncFieldColumns();
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
