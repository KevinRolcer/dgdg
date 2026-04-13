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
window.TM_ADMIN_SEED_EXCEL_BOOT = {
    previewUrl: @json($tmSeedPreviewUrlForJs),
    csrfRefreshUrl: @json($tmCsrfRefreshUrlForJs),
    loginUrl: @json($tmLoginUrlForJs),
};
</script>
<script src="{{ asset('assets/js/modules/temporary-modules-admin-seed-excel.js') }}?v={{ @filemtime(public_path('assets/js/modules/temporary-modules-admin-seed-excel.js')) ?: time() }}"></script>
@endpush
