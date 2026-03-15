@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/tm-analysis-word.css') }}?v={{ @filemtime(public_path('assets/css/tm-analysis-word.css')) ?: time() }}">
@endpush

@php
    $hidePageHeader = true;
@endphp

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endpush

@section('content')
<section class="tm-page tm-shell app-density-compact">
    <div class="tm-shell-main">
        <header class="tm-shell-head">
            <h1 class="tm-shell-title">Registros de eventos temporales</h1>
            <p class="tm-shell-desc">Visualiza módulos con registros capturados, exportación a Excel y análisis en Word.</p>
        </header>

        @if (session('status'))
            <div class="inline-alert inline-alert-success" role="alert">{{ session('status') }}</div>
        @endif

        <article class="content-card tm-card tm-card-in-shell">
        <div class="tm-head">
            <div>
                <p class="tm-head-desc-only">Filtra por módulo y exporta datos o análisis.</p>
            </div>
            <div class="tm-inline-actions">
                <a href="{{ route('temporary-modules.admin.index') }}" class="tm-btn">Gestión de módulos</a>
                <a href="{{ route('temporary-modules.admin.create') }}" class="tm-btn tm-btn-primary">Nuevo módulo</a>
            </div>
        </div>

        <div class="tm-table-wrap tm-table-wrap-scroll">
            <table class="tm-table">
                <thead>
                    <tr>
                        <th>Módulo</th>
                        <th>Vigencia</th>
                        <th>Registros</th>
                        <th>Campos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($modules as $module)
                        <tr>
                            <td>
                                <strong>{{ $module->name }}</strong>
                                @php
                                    $moduleDescription = (string) ($module->description ?: 'Sin descripcion adicional.');
                                    $isLongModuleDescription = mb_strlen($moduleDescription) > 120;
                                @endphp
                                @if ($isLongModuleDescription)
                                    <small class="tm-cell-text-wrap" data-text-wrap>
                                        <span class="tm-cell-text is-collapsed" data-text-content>{{ $moduleDescription }}</span>
                                        <button type="button" class="tm-cell-text-toggle" data-text-toggle>Ver mas</button>
                                    </small>
                                @else
                                    <small>{{ $moduleDescription }}</small>
                                @endif
                            </td>
                            <td>{{ optional($module->expires_at)->format('d/m/Y H:i') ?? 'Sin límite' }}</td>
                            <td>{{ $module->entries_count }}</td>
                            <td>{{ $module->fields_count }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="tm-btn tm-btn-success"
                                    data-open-export-options
                                    data-export-url="{{ route('temporary-modules.admin.export', $module->id) }}"
                                    data-structure-url="{{ route('temporary-modules.admin.export-preview-structure', $module->id) }}"
                                    data-analysis-preview-url="{{ route('temporary-modules.admin.analysis-preview', $module->id) }}"
                                    data-analysis-word-url="{{ route('temporary-modules.admin.export-analysis-word', $module->id) }}"
                                >
                                    Exportar Excel
                                </button>
                                @if (!is_null($module->seed_discard_log))
                                    <script type="application/json" id="tm-seed-discard-{{ $module->id }}">{!! json_encode($module->seed_discard_log ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
                                    <button
                                        type="button"
                                        class="tm-btn tm-btn-secondary"
                                        data-tm-seed-log-open
                                        data-module-name="{{ e($module->name) }}"
                                        data-json-id="tm-seed-discard-{{ $module->id }}"
                                    >
                                        Log
                                    </button>
                                @endif
                                <button
                                    type="button"
                                    class="tm-btn"
                                    data-open-module-preview="admin-preview-{{ $module->id }}"
                                >
                                    Vista previa
                                </button>
                                <form method="POST" action="{{ route('temporary-modules.admin.clear-entries', $module->id) }}" class="tm-inline-form" id="tmClearEntriesForm-{{ $module->id }}">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="button"
                                        class="tm-btn tm-btn-danger"
                                        data-clear-module-entries
                                        data-form-id="tmClearEntriesForm-{{ $module->id }}"
                                        data-module-name="{{ $module->name }}"
                                    >
                                        Vaciar registros
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No hay módulos con registros capturados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (method_exists($modules, 'links'))
            <div class="tm-pagination-wrap tm-pagination--footer">
                {{ $modules->withQueryString()->links('vendor.pagination.tm') }}
            </div>
        @endif
        </article>
    </div>

    @foreach ($modules as $module)
        <div class="tm-modal" id="admin-preview-{{ $module->id }}" aria-hidden="true" role="dialog" aria-modal="true">
            <div class="tm-modal-backdrop" data-close-module-preview></div>
            <div class="tm-modal-dialog tm-modal-dialog-admin-preview">
                <div class="tm-modal-head">
                    <h3>{{ $module->name }}</h3>
                    <button type="button" class="tm-modal-close" data-close-module-preview aria-label="Cerrar">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="tm-modal-body">
                    @php
                        $moduleDescription = (string) ($module->description ?: 'Sin descripcion adicional.');
                        $isLongModuleDescription = mb_strlen($moduleDescription) > 180;
                    @endphp
                    @if ($isLongModuleDescription)
                        <p class="tm-cell-text-wrap" data-text-wrap>
                            <span class="tm-cell-text is-collapsed" data-text-content>{{ $moduleDescription }}</span>
                            <button type="button" class="tm-cell-text-toggle" data-text-toggle>Ver mas</button>
                        </p>
                    @else
                        <p>{{ $moduleDescription }}</p>
                    @endif

                    <h4>Registros de delegados</h4>
                    @can('Enlace')
                    <div class="tm-enlace-eliminar-registro mb-3">
                        <button type="button" class="tm-btn tm-btn-danger" id="btnEliminarRegistroEnlace">Eliminar registro de Municipios contestados hoy</button>
                    </div>
                    @endcan
                    <div class="tm-table-wrap tm-table-wrap-admin-preview">
                        <table class="tm-table tm-admin-preview-table">
                            <thead>
                                <tr>
                                    <th>Delegado</th>
                                    <th>Fecha</th>
                                    @foreach ($module->fields as $field)
                                        <th>
                                            <span class="tm-admin-col-title" title="{{ $field->label }}">{{ $field->label }}</span>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($module->entries as $entry)
                                    <tr>
                                        <td>{{ $entry->user->name ?? 'Sin usuario' }}</td>
                                        <td>{{ optional($entry->submitted_at)->format('d/m/Y H:i') }}</td>
                                        @foreach ($module->fields as $field)
                                            @php
                                                $cell = $entry->data[$field->key] ?? null;
                                                $columnIndex = $loop->index + 3;
                                            @endphp
                                            <td data-admin-col="{{ $columnIndex }}">
                                                @if (in_array($field->type, ['file', 'image'], true) && is_string($cell) && $cell !== '')
                                                    <button
                                                        type="button"
                                                        class="tm-thumb-link"
                                                        data-open-image-preview
                                                        data-image-src="{{ route('temporary-modules.entry-file.preview', ['module' => $module->id, 'entry' => $entry->id, 'fieldKey' => $field->key]) }}"
                                                        data-image-title="{{ $field->label }}"
                                                        title="Ver imagen"
                                                    >
                                                        <i class="fa fa-image" aria-hidden="true"></i> Ver imagen
                                                    </button>
                                                @elseif (is_bool($cell))
                                                    {{ $cell ? 'Sí' : 'No' }}
                                                @else
                                                    @php
                                                        if (is_array($cell)) {
                                                            $displayText = implode(', ', array_map(function ($item) {
                                                                return is_scalar($item)
                                                                    ? (string) $item
                                                                    : json_encode($item, JSON_UNESCAPED_UNICODE);
                                                            }, $cell));
                                                        } elseif (is_scalar($cell)) {
                                                            $displayText = (string) $cell;
                                                        } else {
                                                            $displayText = '-';
                                                        }
                                                        $displayText = trim($displayText) !== '' ? $displayText : '-';
                                                        $isLongText = mb_strlen($displayText) > 120;
                                                    @endphp
                                                    <span class="tm-cell-text-wrap tm-cell-text-wrap-admin" data-text-wrap>
                                                        <span class="tm-cell-text is-collapsed" data-text-content>{{ $displayText }}</span>
                                                        <span class="tm-cell-actions">
                                                            @if ($isLongText)
                                                                <button type="button" class="tm-cell-text-toggle" data-text-toggle>Ver mas</button>
                                                            @endif
                                                            <button
                                                                type="button"
                                                                class="tm-cell-expand-toggle"
                                                                data-cell-expand
                                                                data-col-index="{{ $columnIndex }}"
                                                                title="Expandir celda"
                                                                aria-label="Expandir celda"
                                                            >
                                                                <i class="fa-solid fa-left-right" aria-hidden="true"></i>
                                                            </button>
                                                        </span>
                                                    </span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $module->fields->count() + 2 }}">Sin registros capturados todavía.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <div class="tm-modal" id="tmImagePreviewModal" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="tm-modal-backdrop" data-close-image-preview></div>
        <div class="tm-modal-dialog tm-image-modal-dialog">
            <div class="tm-modal-head">
                <h3 id="tmImagePreviewTitle">Vista previa</h3>
                <button type="button" class="tm-modal-close" data-close-image-preview aria-label="Cerrar">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="tm-modal-body">
                <img src="" alt="Vista previa" id="tmImagePreviewImg" class="tm-image-modal-preview">
            </div>
        </div>
    </div>

    <div class="tm-modal tm-export-personalize-modal" id="tmExportPersonalizeModal" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="tm-modal-backdrop" data-close-export-personalize></div>
        <div class="tm-modal-dialog tm-export-personalize-dialog">
            <div class="tm-modal-head">
                <h3>Personalizar vista previa del Excel</h3>
                <button type="button" class="tm-modal-close" data-close-export-personalize aria-label="Cerrar">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="tm-modal-body">
                <div class="tm-export-personalize-loading" id="tmExportPersonalizeLoading">Cargando estructura…</div>
                <div class="tm-export-personalize-content" id="tmExportPersonalizeContent" hidden>
                    <div class="tm-export-personalize-form">
                        <div class="tm-export-personalize-field">
                            <label for="tmExportPersonalizeTitle">Título del documento</label>
                            <input type="text" id="tmExportPersonalizeTitle" class="tm-input" placeholder="Nombre del módulo">
                        </div>
                    <div class="tm-export-personalize-field tm-export-title-align">
                        <span class="tm-export-label-inline">Alineación del título</span>
                        <div class="tm-export-align-btns" role="group" aria-label="Alineación del título">
                            <button type="button" class="tm-export-align-btn" data-title-align="left">Izq</button>
                            <button type="button" class="tm-export-align-btn is-active" data-title-align="center">Centro</button>
                            <button type="button" class="tm-export-align-btn" data-title-align="right">Der</button>
                        </div>
                    </div>
                        <p class="tm-export-personalize-hint">Arrastra las columnas para cambiar el orden. Usa &times; para omitir una columna del reporte.</p>
                        <div class="tm-export-personalize-columns" id="tmExportPersonalizeColumns" role="list"></div>
                        <p class="tm-export-restore-wrap" id="tmExportRestoreWrap" hidden>
                            <button type="button" class="tm-export-restore-btn" id="tmExportRestoreBtn">Restaurar todas las columnas</button>
                        </p>
                    </div>
                    <div class="tm-export-personalize-preview-wrap">
                        <div class="tm-export-preview-toolbar">
                            <span class="tm-export-preview-label">Vista previa</span>
                            <div class="tm-export-toolbar-controls">
                                <div class="tm-export-zoom-btns">
                                    <button type="button" class="tm-export-zoom-btn" data-zoom-out title="Alejar" aria-label="Alejar">−</button>
                                    <button type="button" class="tm-export-zoom-reset" title="100%" aria-label="Zoom 100%"><span id="tmExportZoomValue">100</span>%</button>
                                    <button type="button" class="tm-export-zoom-btn" data-zoom-in title="Acercar" aria-label="Acercar">+</button>
                                </div>
                                <div class="tm-export-orient-group" role="group" aria-label="Orientación de la hoja">
                                    <button type="button" class="tm-export-orient-btn is-active" data-orientation="portrait">Vertical</button>
                                    <button type="button" class="tm-export-orient-btn" data-orientation="landscape">Horizontal</button>
                                </div>
                            </div>
                        </div>
                        <div class="tm-export-preview-a4">
                            <div class="tm-export-preview-page" id="tmExportPreviewPage">
                                <div class="tm-export-preview-zoom-wrap" id="tmExportPreviewZoomWrap">
                                    <div class="tm-export-personalize-preview" id="tmExportPersonalizePreview"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tm-modal-foot">
                <button type="button" class="tm-btn tm-btn-outline" data-close-export-personalize>Cancelar</button>
                <button type="button" class="tm-btn tm-btn-success" id="tmExportPersonalizeApply">Exportar con esta configuración</button>
            </div>
        </div>
    </div>

    {{-- Informe de análisis (Word): vista previa + opciones --}}
    <div class="tm-modal tm-analysis-word-modal" id="tmAnalysisWordModal" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="tm-modal-backdrop" data-close-analysis-word></div>
        <div class="tm-modal-dialog tm-analysis-word-dialog">
            <div class="tm-modal-head tm-analysis-word-head-row">
                <h3>Informe de análisis (Word)</h3>
                <div class="tm-analysis-word-head-actions">
                    <button type="button" class="tm-btn tm-btn-primary" id="tmAnalysisOpenPersonalize" title="Título, alineación y vista previa del .docx">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Editar y exportar
                    </button>
                    <button type="button" class="tm-modal-close" data-close-analysis-word aria-label="Cerrar">&times;</button>
                </div>
            </div>
            <div class="tm-modal-body tm-analysis-word-body">
                <aside class="tm-analysis-sidebar">
                    <p class="tm-analysis-sidebar-title">Qué incluir</p>
                    <label class="tm-analysis-check"><input type="checkbox" id="tmAnalysisIncludeSummary" checked> Resumen (totales)</label>
                    <label class="tm-analysis-check"><input type="checkbox" id="tmAnalysisIncludeMrTable" checked> Tabla por microregión / municipios</label>
                    <hr class="tm-analysis-hr">
                    <p class="tm-analysis-sidebar-title">Tabla vacía extra</p>
                    <div class="tm-analysis-grid-inputs">
                        <label>Filas <input type="number" id="tmAnalysisCustomRows" min="0" max="30" value="0" class="tm-input tm-input-sm"></label>
                        <label>Columnas <input type="number" id="tmAnalysisCustomCols" min="0" max="12" value="0" class="tm-input tm-input-sm"></label>
                    </div>
                    <button type="button" class="tm-btn tm-btn-outline tm-analysis-build-grid" id="tmAnalysisBuildGridBtn">Crear tabla N×M en vista previa</button>
                    <button type="button" class="tm-btn tm-btn-outline" id="tmAnalysisRefreshPreview">Actualizar vista previa</button>
                    <p class="tm-analysis-hint">Basado en el mismo criterio que el antiguo Excel de análisis. El Excel de datos ya no incluye esa hoja.</p>
                </aside>
                <div class="tm-analysis-preview-panel">
                    <div class="tm-analysis-preview-label">Vista previa (estilo informe)</div>
                    <div class="tm-analysis-preview-doc" id="tmAnalysisPreviewDoc">
                        <div class="tm-analysis-preview-loading" id="tmAnalysisPreviewLoading">Cargando…</div>
                    </div>
                </div>
            </div>
            <div class="tm-modal-foot">
                <button type="button" class="tm-btn tm-btn-outline" data-close-analysis-word>Cancelar</button>
                <form method="POST" id="tmAnalysisWordForm" class="tm-inline-form">
                    @csrf
                    <input type="hidden" name="include_summary" id="tmAnalysisFormSummary" value="1">
                    <input type="hidden" name="include_mr_table" id="tmAnalysisFormMrTable" value="1">
                    <input type="hidden" name="custom_rows" id="tmAnalysisFormRows" value="0">
                    <input type="hidden" name="custom_cols" id="tmAnalysisFormCols" value="0">
                    <button type="submit" class="tm-btn tm-btn-success">Generar Word (notificación al terminar)</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Personalizar informe Word (.docx) — se abre con «Editar y exportar» --}}
    <div class="tm-modal tm-export-personalize-modal tm-analysis-word-personalize-modal" id="tmAnalysisWordPersonalizeModal" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="tm-modal-backdrop" data-close-analysis-word-personalize></div>
        <div class="tm-modal-dialog tm-export-personalize-dialog tm-analysis-word-personalize-dialog">
            <div class="tm-modal-head">
                <h3>Personalizar informe Word (.docx)</h3>
                <button type="button" class="tm-modal-close" data-close-analysis-word-personalize aria-label="Cerrar">&times;</button>
            </div>
            <div class="tm-modal-body tm-analysis-word-personalize-body">
                <div class="tm-export-personalize-content tm-analysis-word-personalize-content">
                    <div class="tm-export-personalize-form tm-analysis-word-personalize-form">
                        <div class="tm-export-personalize-field">
                            <label for="tmWordDocTitle">Título del documento</label>
                            <input type="text" id="tmWordDocTitle" class="tm-input" placeholder="Ej. Análisis general — nombre del módulo">
                        </div>
                        <div class="tm-export-personalize-field">
                            <label for="tmWordSubtitle">Subtítulo (opcional)</label>
                            <input type="text" id="tmWordSubtitle" class="tm-input" placeholder="Línea bajo el título">
                        </div>
                        <div class="tm-export-personalize-field tm-export-title-align">
                            <span class="tm-export-label-inline">Alineación del título</span>
                            <div class="tm-export-align-btns" role="group">
                                <button type="button" class="tm-export-align-btn" data-word-title-align="left">Izq</button>
                                <button type="button" class="tm-export-align-btn is-active" data-word-title-align="center">Centro</button>
                                <button type="button" class="tm-export-align-btn" data-word-title-align="right">Der</button>
                            </div>
                        </div>
                        <p class="tm-analysis-sidebar-title" style="margin-top:14px;">Qué tablas incluir en el informe</p>
                        <label class="tm-analysis-check"><input type="checkbox" id="tmWordIncludeSummary" checked> Resumen (totales)</label>
                        <label class="tm-analysis-check"><input type="checkbox" id="tmWordIncludeMrTable" checked> Tabla microregión / municipios</label>
                        <label class="tm-analysis-check"><input type="checkbox" id="tmWordIncludeDynamic" checked> Tabla por registro (columnas elegidas)</label>
                        <p class="tm-analysis-sidebar-title" style="margin-top:12px;">Alineación / ancho de tablas</p>
                        <div class="tm-export-align-btns tm-word-table-align-btns" role="group" aria-label="Alineación tablas" style="flex-wrap:wrap;margin-bottom:10px;">
                            <button type="button" class="tm-export-align-btn is-active" data-word-table-align="left">Izquierda</button>
                            <button type="button" class="tm-export-align-btn" data-word-table-align="center">Centrada</button>
                            <button type="button" class="tm-export-align-btn" data-word-table-align="right">Derecha</button>
                            <button type="button" class="tm-export-align-btn" data-word-table-align="stretch" title="Ocupa todo el ancho útil">Ajustar ancho</button>
                        </div>
                        <p class="tm-analysis-sidebar-title" style="margin-top:12px;">Tablas (texto y celdas)</p>
                        <div class="tm-word-table-opts" style="display:grid;gap:8px;margin-bottom:10px;">
                            <label class="tm-analysis-hint" style="display:flex;align-items:center;gap:8px;margin:0;">Texto (pt)
                                <select id="tmWordTableFontPt" class="tm-input tm-input-sm" style="max-width:80px;">
                                    @foreach ([7,8,9,10,11,12] as $pt)<option value="{{ $pt }}" {{ $pt === 9 ? 'selected' : '' }}>{{ $pt }}</option>@endforeach
                                </select>
                            </label>
                            <label class="tm-analysis-hint" style="display:flex;align-items:center;gap:8px;margin:0;">Relleno celdas (px)
                                <select id="tmWordTableCellPad" class="tm-input tm-input-sm" style="max-width:80px;">
                                    @foreach ([3,4,6,8,10,12] as $px)<option value="{{ $px }}" {{ $px === 6 ? 'selected' : '' }}>{{ $px }}</option>@endforeach
                                </select>
                            </label>
                            <label class="tm-analysis-hint" style="display:flex;align-items:center;gap:8px;margin:0;">Ancho máx. celda dinámica (px)
                                <input type="number" id="tmWordTableCellMax" class="tm-input tm-input-sm" min="72" max="280" value="140" style="max-width:80px;">
                            </label>
                        </div>
                        <p class="tm-analysis-hint" style="margin:10px 0 6px;">Tabla dinámica: arrastra cada campo a una columna (máx. 12). Referencia = primer registro.</p>
                        <div class="tm-word-field-palette" id="tmWordFieldPalette" aria-label="Campos del módulo"></div>
                        <div class="tm-word-column-slots-wrap">
                            <span class="tm-export-label-inline">Columnas a exportar</span>
                            <div class="tm-word-column-slots" id="tmWordColumnSlots"></div>
                        </div>
                        <p class="tm-analysis-sidebar-title" style="margin-top:12px;">Estándar contable (desglose)</p>
                        <p class="tm-analysis-hint" style="margin:0 0 6px;">Arriba: KPIs en una fila. Abajo de la tabla: totales (suma números ≥0; en Sí/No solo cuenta <strong>Sí</strong>).</p>
                        <div id="tmWordAccountingFields" class="tm-word-accounting-fields"></div>
                        <button type="button" class="tm-btn tm-btn-outline" id="tmWordRefreshPreviewBtn" style="width:100%;margin-top:10px;">Actualizar vista previa</button>
                    </div>
                    <div class="tm-export-personalize-preview-wrap tm-analysis-word-preview-wrap">
                        <div class="tm-export-preview-toolbar">
                            <span class="tm-export-preview-label">Vista previa A4 <span class="tm-word-preview-zoom-hint" title="Pellizco en trackpad o Ctrl + rueda">· zoom con trackpad</span></span>
                            <div class="tm-export-toolbar-controls" aria-label="Zoom y orientación">
                                <div class="tm-export-zoom-btns">
                                    <button type="button" class="tm-export-zoom-btn" data-word-zoom-out title="Alejar" aria-label="Alejar">−</button>
                                    <button type="button" class="tm-export-zoom-reset" data-word-zoom-reset title="100%" aria-label="Zoom 100%"><span id="tmWordZoomValue">100</span>%</button>
                                    <button type="button" class="tm-export-zoom-btn" data-word-zoom-in title="Acercar" aria-label="Acercar">+</button>
                                </div>
                                <div class="tm-word-orient-btns tm-export-orient-group" role="group" aria-label="Orientación de la hoja">
                                    <button type="button" class="tm-word-orient-btn is-active" data-word-orient="portrait">Vertical</button>
                                    <button type="button" class="tm-word-orient-btn" data-word-orient="landscape">Horizontal</button>
                                </div>
                            </div>
                        </div>
                        <div class="tm-export-preview-a4 tm-analysis-word-preview-a4" id="tmWordPreviewA4Area" title="Hoja A4 · Ctrl + rueda o pellizco para zoom">
                            <div class="tm-word-preview-page tm-word-preview-page--portrait" id="tmWordPreviewPage">
                                <div class="tm-export-preview-zoom-wrap tm-word-preview-zoom-wrap" id="tmWordPreviewZoomWrap">
                                    <div class="tm-analysis-preview-doc tm-analysis-preview-doc--personalize" id="tmWordPersonalizePreview"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tm-modal-foot">
                <button type="button" class="tm-btn tm-btn-outline" data-close-analysis-word-personalize>Volver</button>
                <form method="POST" id="tmAnalysisWordPersonalizeForm" class="tm-inline-form">
                    @csrf
                    <input type="hidden" name="include_summary" id="tmWordFormSummary" value="1">
                    <input type="hidden" name="include_mr_table" id="tmWordFormMrTable" value="1">
                    <input type="hidden" name="include_dynamic_table" id="tmWordFormDynamic" value="1">
                    <input type="hidden" name="table_align" id="tmWordFormTableAlign" value="left">
                    <input type="hidden" name="doc_title" id="tmWordFormDocTitle" value="">
                    <input type="hidden" name="title_align" id="tmWordFormTitleAlign" value="center">
                    <input type="hidden" name="subtitle" id="tmWordFormSubtitle" value="">
                    <input type="hidden" name="orientation" id="tmWordFormOrientation" value="portrait">
                    <input type="hidden" name="column_keys" id="tmWordFormColumnKeys" value="[]">
                    <input type="hidden" name="table_font_pt" id="tmWordFormTableFontPt" value="9">
                    <input type="hidden" name="table_cell_pad" id="tmWordFormTableCellPad" value="6">
                    <input type="hidden" name="table_cell_max_px" id="tmWordFormTableCellMax" value="140">
                    <input type="hidden" name="summary_kpi_keys" id="tmWordFormSummaryKpiKeys" value="[]">
                    <input type="hidden" name="totals_column_keys" id="tmWordFormTotalsColumnKeys" value="[]">
                    <button type="submit" class="tm-btn tm-btn-success">Generar Word con esta configuración</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Log de filas descartadas al crear módulo desde Excel (solo si ya exportó) --}}
    <div class="tm-modal" id="tmSeedDiscardLogModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="tmSeedDiscardLogTitle">
        <div class="tm-modal-backdrop" data-tm-seed-log-close></div>
        <div class="tm-modal-dialog tm-seed-log-dialog">
            <div class="tm-modal-head">
                <h3 id="tmSeedDiscardLogTitle">Log — filas no cargadas</h3>
                <button type="button" class="tm-modal-close" data-tm-seed-log-close aria-label="Cerrar">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="tm-modal-body tm-seed-log-body">
                <p class="tm-seed-log-module" id="tmSeedDiscardLogModule"></p>
                <p class="tm-muted" id="tmSeedDiscardLogEmpty" hidden>No hay filas descartadas registradas (módulo anterior a esta función o sin omisiones).</p>
                <div class="tm-table-wrap tm-seed-log-table-wrap" id="tmSeedDiscardLogTableWrap" hidden>
                    <table class="tm-table tm-table-sm">
                        <thead>
                            <tr>
                                <th>Fila Excel</th>
                                <th>Motivo</th>
                                <th>MR</th>
                                <th>Municipio (celda)</th>
                                <th>Acción / texto</th>
                            </tr>
                        </thead>
                        <tbody id="tmSeedDiscardLogTbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
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
                    var mw = Math.min(col.max_width_chars || 10, 40);
                    var approx = String(col.max_width_chars || 10);
                    mid = '<div class="tm-export-col-width-preview" style="min-width:' + mw + 'ch" data-width-hint="' + escapeHtml(approx) + '">' +
                        '<span class="tm-export-col-width-num">' + approx + '</span> ch</div>';
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
            return { title: titleEl ? titleEl.value : '', titleAlign: titleAlign, columns: columns };
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

        function buildPersonalizePreview(columns, previewEl, sampleRow) {
            if (!previewEl) { return; }
            const savedRow = sampleRow || readSampleRowFromPreview(previewEl);
            const state = getPersonalizeState();
            const colorMap = {};
            state.columns.forEach(function (c) { colorMap[c.key] = c.color; });
            const titleAlign = state.titleAlign || 'center';
            const titleStyle = 'text-align:' + (titleAlign === 'left' ? 'left' : titleAlign === 'right' ? 'right' : 'center');
            let html = '<div class="tm-export-preview-table"><div class="tm-export-preview-row tm-export-preview-title"><div class="tm-export-preview-cell tm-export-preview-title-cell" style="' + titleStyle + '" colspan="' + columns.length + '">' + escapeHtml(state.title || 'Título') + '</div></div><div class="tm-export-preview-row tm-export-preview-header">';
            columns.forEach(function (col) {
                const color = colorMap[col.key] || '#861e34';
                if (col.is_image) {
                    const c = state.columns.find(function (x) { return x.key === col.key; }) || {};
                    const w = (c.imageWidth || 120) + 'px';
                    const h = (c.imageHeight || 80) + 'px';
                    html += '<div class="tm-export-preview-cell tm-export-preview-header-cell tm-export-preview-image-cell" style="background-color:' + escapeHtml(color) + ';width:' + w + ';height:' + h + ';min-width:' + w + ';min-height:' + h + '"><span class="tm-export-preview-image-placeholder">Imagen</span></div>';
                } else {
                    const ch = Math.min(col.max_width_chars || 10, 40);
                    html += '<div class="tm-export-preview-cell tm-export-preview-header-cell" style="background-color:' + escapeHtml(color) + ';min-width:' + ch + 'ch">' + escapeHtml(col.label) + '</div>';
                }
            });
            html += '</div><div class="tm-export-preview-row tm-export-preview-data">';
            columns.forEach(function (col) {
                const color = colorMap[col.key] || 'var(--clr-primary)';
                const cellColor = '#f5f5f5';
                if (col.is_image) {
                    const c = state.columns.find(function (x) { return x.key === col.key; }) || {};
                    const w = (c.imageWidth || 120) + 'px';
                    const h = (c.imageHeight || 80) + 'px';
                    html += '<div class="tm-export-preview-cell tm-export-preview-data-cell tm-export-preview-image-cell" data-key="' + escapeHtml(col.key) + '" style="width:' + w + ';height:' + h + ';min-width:' + w + ';min-height:' + h + ';background:#f0f0f0"><span class="tm-export-preview-image-placeholder">—</span></div>';
                } else {
                    const ch = Math.min(col.max_width_chars || 10, 40);
                    const val = savedRow[col.key] !== undefined ? escapeHtml(savedRow[col.key]) : '';
                    html += '<div class="tm-export-preview-cell tm-export-preview-data-cell" data-key="' + escapeHtml(col.key) + '" contenteditable="true" style="min-width:' + ch + 'ch;background:' + escapeHtml(cellColor) + '" data-placeholder="Ejemplo">' + val + '</div>';
                }
            });
            html += '</div></div>';
            previewEl.innerHTML = html;
        }

        function reorderColumnsList(container, columns) {
            const order = Array.from(container.querySelectorAll('.tm-export-personalize-col')).map(function (item) {
                return columns.find(function (c) { return c.key === item.dataset.key; });
            }).filter(Boolean);
            return order.length ? order : columns.slice();
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
            const applyBtn = document.getElementById('tmExportPersonalizeApply');
            const restoreWrap = document.getElementById('tmExportRestoreWrap');
            const restoreBtn = document.getElementById('tmExportRestoreBtn');

            if (loadingEl) { loadingEl.hidden = false; }
            if (contentEl) { contentEl.hidden = true; }
            personalizeModal.classList.add('is-open');
            personalizeModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            fetch(structureUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(new Error('Error al cargar')); })
                .then(function (data) {
                    let columns = Array.isArray(data.columns) ? data.columns : [];
                    if (personalizeModal) { personalizeModal._personalizeColumns = columns; }
                    if (titleEl) { titleEl.value = data.title || ''; }
                    buildPersonalizeColumnsList(columns, columnsEl);
                    buildPersonalizePreview(columns, previewEl);
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

                    if (applyBtn && exportUrl) {
                        applyBtn.onclick = function () {
                            const orderedCols = reorderColumnsList(columnsEl, columns);
                            const state = getPersonalizeState();
                            const orientBtn = personalizeModal.querySelector('.tm-export-orient-btn.is-active');
                            const orientation = orientBtn ? (orientBtn.getAttribute('data-orientation') || 'portrait') : 'portrait';

                            const cfg = {
                                title: state.title || '',
                                title_align: state.titleAlign || 'center',
                                orientation: orientation,
                                columns: orderedCols.map(function (col) {
                                    const colState = state.columns.find(function (c) { return c.key === col.key; }) || {};
                                    return {
                                        key: col.key,
                                        color: colState.color || 'var(--clr-primary)',
                                        image_width: colState.imageWidth || null,
                                        image_height: colState.imageHeight || null,
                                        // width in caracteres aproximados, si viene del API
                                        max_width_chars: col.max_width_chars || null
                                    };
                                })
                            };

                            const separator = exportUrl.indexOf('?') === -1 ? '?' : '&';
                            const cfgParam = encodeURIComponent(JSON.stringify(cfg));
                            closePersonalizeModal();
                            window.location.href = exportUrl + separator + 'mode=single&analysis=0&cfg=' + cfgParam;
                        };
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
                        + '<p style="margin:0 0 .5rem;font-size:.8rem;color:#64748b;">Elige qué generar. «Personalizar» abre el editor según tu elección (Excel o Word).</p>'
                        + '<label style="display:flex;gap:.5rem;align-items:flex-start;margin-bottom:.55rem;cursor:pointer;">'
                        + '<input type="radio" name="tm-export-choice" value="single" checked style="margin-top:.2rem;"> '
                        + '<span><strong>Excel — Una sola hoja</strong><br><small style="color:#64748b">Todos los registros en una hoja.</small></span>'
                        + '</label>'
                        + '<label style="display:flex;gap:.5rem;align-items:flex-start;margin-bottom:.55rem;cursor:pointer;">'
                        + '<input type="radio" name="tm-export-choice" value="mr" style="margin-top:.2rem;"> '
                        + '<span><strong>Excel — 1 hoja por microregión</strong><br><small style="color:#64748b">Una página por microregión.</small></span>'
                        + '</label>'
                        + '<label style="display:flex;gap:.5rem;align-items:flex-start;margin-bottom:.65rem;cursor:pointer;">'
                        + '<input type="radio" name="tm-export-choice" value="word" style="margin-top:.2rem;"> '
                        + '<span><strong>Informe de análisis (Word)</strong><br><small style="color:#64748b">Documento .docx con resumen y tablas (mismo criterio que el antiguo Excel de análisis).</small></span>'
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
                                if (val === 'word') {
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
                    if (choice === 'word') {
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
</script>
@endpush
