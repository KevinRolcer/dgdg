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

                    <p class="tm-seed-hint-block">Campos del módulo (Acción, estatus, etc.):</p>
                    <div id="tmSeedFieldChecks" class="tm-seed-field-checks"></div>
                    <input type="hidden" name="field_columns" id="tmSeedFieldColumns" value="">

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
    const fieldChecks = document.getElementById('tmSeedFieldChecks');
    const fieldColumnsInput = document.getElementById('tmSeedFieldColumns');
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

    function fillSelects(headers) {
        const opts = headers.map(function (h) {
            return '<option value="' + h.index + '">' + h.letter + ' — ' + String(h.label || '(vacío)').replace(/</g, '&lt;') + '</option>';
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
        fieldChecks.innerHTML = '';
        headers.forEach(function (h) {
            const id = 'fc_' + h.index;
            const row = document.createElement('label');
            row.className = 'tm-seed-fc-label';
            row.innerHTML = '<input type="checkbox" class="tm-seed-fc" value="' + h.index + '" id="' + id + '"> <span>' + h.letter + ' — ' + String(h.label || '').replace(/</g, '&lt;') + '</span>';
            fieldChecks.appendChild(row);
        });
        mappingEl.classList.remove('tm-hidden');
        mappingEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        syncFieldColumns();
    }

    function syncFieldColumns() {
        const checked = Array.from(document.querySelectorAll('.tm-seed-fc:checked')).map(function (c) { return parseInt(c.value, 10); });
        fieldColumnsInput.value = JSON.stringify(checked);
        submitBtn.disabled = checked.length === 0;
    }

    fieldChecks.addEventListener('change', syncFieldColumns);

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
            }).catch(function (e) {
                errEl.textContent = e.message || 'Error al procesar el archivo.';
                errEl.classList.remove('tm-hidden');
            }).finally(function () {
                if (readBtn) { readBtn.disabled = false; readBtn.innerHTML = origText; }
            });
    }

    document.getElementById('tmSeedReadHeaders').addEventListener('click', function () {
        currentSheetIndex = 0;
        sheetIndexInput.value = '0';
        readHeaders(0);
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
