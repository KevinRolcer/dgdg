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
<script src="{{ asset('assets/js/modules/temporary-modules-seed-discard-log.js') }}?v={{ @filemtime(public_path('assets/js/modules/temporary-modules-seed-discard-log.js')) ?: time() }}"></script>
@endpush

@section('content')
<section class="tm-page tm-shell app-density-compact">
    <div class="tm-shell-main">


        @if (session('status'))
            <div class="inline-alert inline-alert-success" role="alert">{{ session('status') }}</div>
        @endif

        <article class="content-card tm-card tm-card-in-shell" style="border: none; box-shadow: none;">
        <div class="tm-head tm-head--actions-only">
            <div class="tm-inline-actions">
                <a href="{{ route('temporary-modules.admin.index') }}" class="tm-btn">Gestión de módulos</a>
                <button
                    type="button"
                    class="tm-btn tm-btn-secondary"
                    data-open-tm-admin-activity
                    title="Registros recientes por módulo (misma búsqueda que la tabla)"
                >
                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Actividad
                </button>
                <a href="{{ route('temporary-modules.admin.create') }}" class="tm-btn tm-btn-primary">Nuevo módulo</a>
            </div>
        </div>

        <form class="tm-admin-module-search" method="get" action="{{ route('temporary-modules.admin.records') }}" role="search">
            <label class="tm-admin-module-search__label" for="tm-admin-records-q">Buscar módulo</label>
            <div class="tm-admin-module-search__row">
                <input type="search" id="tm-admin-records-q" name="q" value="{{ $searchQuery }}" autocomplete="off" placeholder="Nombre o descripción..." class="tm-admin-module-search__input">
                <button type="submit" class="tm-btn tm-btn-primary tm-admin-module-search__submit">Buscar</button>
                @if ($searchQuery !== '')
                    <a href="{{ route('temporary-modules.admin.records') }}" class="tm-btn tm-btn-ghost tm-admin-module-search__clear">Limpiar</a>
                @endif
            </div>
        </form>

        <div class="tm-table-wrap tm-table-wrap-scroll">
            <table class="tm-table">
                <thead>
                    <tr>
                        <th style="width: 45%;">Módulo</th>
                        <th style="width: 20%;">Vigencia</th>
                        <th style="width: 10%;">Registros</th>
                        <th style="width: 25%;">Acciones</th>
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
                            <td>
                                <button
                                    type="button"
                                    class="tm-btn tm-btn-success"
                                    data-open-export-options
                                    data-export-url="{{ route('temporary-modules.admin.export', $module->id) }}"
                                    data-export-entries="{{ (int) $module->entries_count }}"
                                    data-structure-url="{{ route('temporary-modules.admin.export-preview-structure', $module->id) }}"
                                    data-analysis-preview-url="{{ route('temporary-modules.admin.analysis-preview', $module->id) }}"
                                    data-analysis-word-url="{{ route('temporary-modules.admin.export-analysis-word', $module->id) }}"
                                    title="Exportar (Excel, PDF, Word o informe)"
                                >
                                    <i class="fa-solid fa-file-excel"></i>
                                </button>
                                @if (!is_null($module->seed_discard_log))
                                    <script type="application/json" id="tm-seed-discard-{{ $module->id }}">{!! json_encode($module->seed_discard_log ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
                                    <button
                                        type="button"
                                        class="tm-btn tm-btn-secondary"
                                        data-tm-seed-log-open
                                        data-module-name="{{ e($module->name) }}"
                                        data-json-id="tm-seed-discard-{{ $module->id }}"
                                        data-register-url="{{ route('temporary-modules.admin.seed-discard-register', $module->id) }}"
                                        data-search-url="{{ route('temporary-modules.admin.seed-discard-search-municipios', $module->id) }}"
                                        title="Log de descartados"
                                    >
                                        <i class="fa-solid fa-clipboard-list"></i>
                                    </button>
                                @endif
                                <button
                                    type="button"
                                    class="tm-btn"
                                    data-open-module-preview="admin-preview-{{ $module->id }}"
                                    title="Vista previa"
                                >
                                    <i class="fa-solid fa-eye"></i>
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
                                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                @if ($searchQuery !== '')
                                    No hay módulos con registros que coincidan con «{{ $searchQuery }}».
                                @else
                                    No hay módulos con registros capturados.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($modules instanceof \Illuminate\Pagination\LengthAwarePaginator)
            {{ $modules->links('vendor.pagination.tm') }}
        @endif
        </article>
    </div>

    @foreach ($modules as $module)
        <div
            class="tm-modal"
            id="admin-preview-{{ $module->id }}"
            aria-hidden="true"
            role="dialog"
            aria-modal="true"
            data-preview-url="{{ route('temporary-modules.admin.records-modal-fragment', $module->id) }}"
        >
            <div class="tm-modal-backdrop" data-close-module-preview></div>
            <div class="tm-modal-dialog tm-modal-dialog-admin-preview">
                <div class="tm-modal-head">
                    <h3>{{ $module->name }}</h3>
                    <button type="button" class="tm-modal-close" data-close-module-preview aria-label="Cerrar">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="tm-modal-body" data-modal-lazy-body>
                    {{-- Cargado al abrir el modal --}}
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
                <h3>Todos los registros</h3>
                <div class="tm-export-save-actions tm-export-save-actions--head">
                    <button type="button" class="tm-btn tm-btn-primary tm-btn-sm" id="tmExportSaveConfigTop">Guardar configuración</button>
                    <button type="button" class="tm-btn tm-btn-outline tm-btn-sm" id="tmExportClearConfigTop" title="Limpiar configuración guardada" aria-label="Limpiar configuración guardada">
                        <i class="fa-solid fa-broom" aria-hidden="true"></i>
                    </button>
                </div>
                <button type="button" class="tm-modal-close" data-close-export-personalize aria-label="Cerrar">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="tm-modal-body">
                <div class="tm-export-personalize-loading" id="tmExportPersonalizeLoading">Cargando estructura...</div>
                <div class="tm-export-personalize-content" id="tmExportPersonalizeContent" hidden>
                    <div class="tm-export-personalize-form tm-export-side-panel">
                        <nav class="tm-export-side-tabs" role="tablist" aria-label="Secciones de personalización">
                            <button type="button" role="tab" class="tm-export-side-tab is-active" data-tm-export-side-tab="tm-export-sec-title" aria-selected="true">Título</button>
                            <button type="button" role="tab" class="tm-export-side-tab" data-tm-export-side-tab="tm-export-sec-layout">Tablas</button>
                            <button type="button" role="tab" class="tm-export-side-tab" data-tm-export-side-tab="tm-export-sec-columns">Columnas</button>
                            <button type="button" role="tab" class="tm-export-side-tab" data-tm-export-side-tab="tm-export-sec-export">Exportar</button>
                        </nav>
                        <div class="tm-export-side-scroll">
                        <section id="tm-export-sec-title" class="tm-export-side-section">
                            <h4 class="tm-export-side-section__title"><span class="tm-export-side-section__icon" aria-hidden="true"><i class="fa-solid fa-heading"></i></span> Título y tabla de registros</h4>
                            <p class="tm-export-side-section__lead">Portada del documento y tipografía de la tabla principal de datos.</p>
                        <div class="tm-export-personalize-field-row tm-export-title-row-group">
                            <div class="tm-export-personalize-field tm-export-field-title-input">
                                <label for="tmExportPersonalizeTitle">Título del documento</label>
                                <input type="text" id="tmExportPersonalizeTitle" class="tm-input" placeholder="Nombre del módulo">
                            </div>
                            <div class="tm-export-case-toggle-wrap">
                                <label class="tm-export-case-toggle" title="Convertir el título a MAYÚSCULAS">
                                    <input type="checkbox" id="tmExportTitleUppercase" value="1">
                                    <span class="tm-export-case-icon">Az</span>
                                </label>
                            </div>
                        </div>
                    <div class="tm-export-personalize-field tm-export-title-align">
                        <span class="tm-export-label-inline">Alineación del título</span>
                        <div class="tm-export-align-btns tm-title-align-group" id="tmExportTitleAlignGroup" role="group" aria-label="Alineación del título">
                            <button type="button" class="tm-export-align-btn" data-title-align="left">Izquierda</button>
                            <button type="button" class="tm-export-align-btn is-active" data-title-align="center">Centro</button>
                            <button type="button" class="tm-export-align-btn" data-title-align="right">Derecha</button>
                        </div>
                    </div>
                    <div class="tm-export-field-row tm-export-field-row--fonts">
                        <div class="tm-export-personalize-field">
                            <label for="tmExportTitleFontSize" title="Tamaño de letra del título">Título (px)</label>
                            <input type="number" id="tmExportTitleFontSize" class="tm-input tm-input--num-compact" min="10" max="36" value="18">
                        </div>
                        <div class="tm-export-personalize-field">
                            <label for="tmExportCellFontSize" title="Celdas de la tabla de registros">Registros: celdas (px)</label>
                            <input type="number" id="tmExportCellFontSize" class="tm-input tm-input--num-compact" min="9" max="24" value="12">
                        </div>
                        <div class="tm-export-personalize-field">
                            <label for="tmExportHeaderFontSize" title="Encabezados de la tabla de registros">Registros: encabezados (px)</label>
                            <input type="number" id="tmExportHeaderFontSize" class="tm-input tm-input--num-compact" min="9" max="28" value="12">
                        </div>
                        <div class="tm-export-personalize-field">
                            <label for="tmExportRecordsGroupHeaderFontSize" title="Fila de grupos en registros">Registros: grupos (px)</label>
                            <input type="number" id="tmExportRecordsGroupHeaderFontSize" class="tm-input tm-input--num-compact" min="9" max="48" value="12">
                        </div>
                    </div>
                    <div class="tm-export-case-toggle-row-sub">
                        <label class="tm-export-case-toggle tm-export-case-toggle--sub" title="Convertir encabezados y títulos de tabla a MAYÚSCULAS">
                            <input type="checkbox" id="tmExportHeadersUppercase" value="1">
                            <span class="tm-export-case-icon">Az</span>
                            <small>Encabezados de tablas en MAYÚSCULAS</small>
                        </label>
                    </div>
                    <p class="tm-analysis-hint tm-export-font-hint">Las demás tablas (conteo, sumatoria, totales, desglose) se configuran en la pestaña <strong>Tablas</strong>.</p>
                        </section>
                        <section id="tm-export-sec-layout" class="tm-export-side-section">
                            <h4 class="tm-export-side-section__title"><span class="tm-export-side-section__icon" aria-hidden="true"><i class="fa-solid fa-table"></i></span> Tablas en la vista previa</h4>
                            <p class="tm-export-side-section__lead">Cada bloque agrupa las opciones de una tabla. Clic derecho o ⋮ en la cabecera para medidas y tipografía extra.</p>

                    <div class="tm-export-config-card" data-tm-export-card="general">
                        <div class="tm-export-config-card__head">
                            <span class="tm-export-config-card__title"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Documento y tabla de registros</span>
                        </div>
                        <div class="tm-export-config-card__body">
                    <div class="tm-export-personalize-field">
                        <label for="tmExportMicrorregionSort">Orden por número de microrregión</label>
                        <select id="tmExportMicrorregionSort" class="tm-input">
                            <option value="asc">Ascendente</option>
                            <option value="desc">Descendente</option>
                        </select>
                        <p class="tm-analysis-hint" style="margin-top:6px;">La vista previa y el archivo exportado seguirán este orden por MR.</p>
                    </div>
                    <div class="tm-export-personalize-field">
                        <label class="tm-export-count-table-toggle">
                            <input type="checkbox" id="tmExportRowHighlightEnabled" value="1">
                            Sombrear toda la fila según una columna
                        </label>
                        <div id="tmExportRowHighlightWrap" hidden style="margin-top:8px;">
                            <label for="tmExportRowHighlightColumn" style="display:block;margin-bottom:4px;">Columna que define el color de la fila</label>
                            <select id="tmExportRowHighlightColumn" class="tm-input" style="max-width:100%;"></select>
                            <div style="margin-top:8px;">
                                <button type="button" class="tm-btn tm-btn-sm tm-btn-outline" id="tmExportRowHighlightConfigureBtn">Colores por respuesta…</button>
                            </div>
                            <p class="tm-analysis-hint" style="margin-top:6px;">Se aplica a PDF, Word y Excel. Las celdas con “sombrear respuestas” en la misma columna siguen teniendo prioridad sobre el color de fila.</p>
                        </div>
                    </div>
                    <div class="tm-export-personalize-field">
                        <label for="tmExportDocMarginPreset">Márgenes del documento</label>
                        <select id="tmExportDocMarginPreset" class="tm-input">
                            <option value="normal">Normal</option>
                            <option value="compact" selected>Compacto</option>
                            <option value="none">Sin margen</option>
                        </select>
                        <p class="tm-analysis-hint" style="margin-top:6px;">Aplica a Word/PDF y a la vista previa A4.</p>
                    </div>
                    <div class="tm-export-personalize-field">
                        <label class="tm-export-count-table-toggle">
                            <input type="checkbox" id="tmExportIncludeCalculatedColumns" value="1">
                            Incluir columnas calculadas en la tabla de registros
                        </label>
                        <div id="tmExportCalculatedColumnsWrap" hidden style="margin-top:8px; display:grid; gap:8px;">
                            <div class="tm-export-sum-block tm-export-calc-block">
                                <div class="tm-export-groups-wrap__head tm-export-calc-head">
                                    <span class="tm-export-label-inline">Columnas calculadas</span>
                                    <button type="button" class="tm-btn tm-btn-sm tm-btn-outline" id="tmExportAddCalculatedColumnBtn">+ Columna</button>
                                </div>
                                <div id="tmExportCalculatedColumnsList" class="tm-export-sum-list tm-export-calc-list"></div>
                            </div>
                            <p class="tm-analysis-hint" style="margin-top:4px;">Agrega 1 o más columnas calculadas. Define campos, peso por campo y columna de referencia para reglas de falta/sobra.</p>
                        </div>
                    </div>
                        </div>
                    </div>

                    <div class="tm-export-config-card" data-tm-export-card="count">
                        <div class="tm-export-config-card__head tm-export-config-card__head--menu" data-tm-export-card-advanced="tmExportCountAdvanced">
                            <span class="tm-export-config-card__title"><i class="fa-solid fa-hashtag" aria-hidden="true"></i> Tabla de conteo</span>
                            <button type="button" class="tm-export-config-card__menu-btn" aria-haspopup="true" aria-expanded="false" aria-controls="tmExportCountAdvanced" title="Mostrar u ocultar medidas PDF y alineación (también clic derecho en la cabecera)"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>
                        </div>
                        <div class="tm-export-config-card__body">
                    <div class="tm-export-personalize-field tm-export-count-table-section">
                        <label class="tm-export-count-table-toggle">
                            <input type="checkbox" id="tmExportIncludeCountTable" value="1">
                            Incluir tabla de conteo general arriba de la tabla de registros
                        </label>
                        <div class="tm-export-count-by-wrap" id="tmExportCountByWrap" hidden>
                            <span class="tm-export-label-inline">Conteo por valor de:</span>
                            <div class="tm-export-count-by-fields" id="tmExportCountByFields" role="group"></div>
                            <div class="tm-export-personalize-field" style="max-width: 320px; margin-top: 8px;">
                                <label for="tmExportCountTotalLabel">Etiqueta de total</label>
                                <input type="text" id="tmExportCountTotalLabel" class="tm-input" value="Total de registros" placeholder="Total de registros">
                            </div>
                            <div class="tm-export-count-table-colors-collapsible" id="tmExportCountColorsCollapsible">
                                <button type="button" class="tm-export-count-colors-header" id="tmExportCountColorsToggle" aria-expanded="false" aria-controls="tmExportCountTableColorListWrap">
                                    <span class="tm-export-count-colors-arrow" aria-hidden="true">▶</span>
                                    <span class="tm-export-count-colors-label">Colores</span>
                                </button>
                                <div class="tm-export-count-table-colors-wrap" id="tmExportCountTableColorListWrap">
                                    <span class="tm-export-label-inline">Colores de la tabla de conteo:</span>
                                    <div class="tm-export-count-table-color-list" id="tmExportCountTableColorList" role="list"></div>
                                </div>
                            </div>
                            <div id="tmExportCountAdvanced" class="tm-export-config-advanced" hidden data-tm-export-advanced>
                                <p class="tm-export-config-advanced__label">Medidas y alineación (PDF)</p>
                            <div class="tm-export-count-table-sizes">
                                <span class="tm-export-label-inline">Tamaños de las celdas:</span>
                                <label class="tm-export-count-cell-width-label">
                                    Ancho (ch):
                                    <input type="number" id="tmExportCountTableCellWidth" class="tm-export-count-cell-width-input tm-input tm-input--large" style="width: 80px;" min="6" max="40" value="12" aria-label="Ancho de columnas de la tabla de conteo en caracteres">
                                </label>
                                <label class="tm-export-count-cell-width-label">
                                    Encabezados PDF (px):
                                    <input type="number" id="tmExportCountTableHeaderFontSize" class="tm-input tm-input--large" style="width: 70px;" min="7" max="36" value="8" aria-label="Tamaño de letra de encabezados de la tabla de conteo en PDF">
                                </label>
                                <label class="tm-export-count-cell-width-label">
                                    Registros PDF (px):
                                    <input type="number" id="tmExportCountTableCellFontSize" class="tm-input tm-input--large" style="width: 70px;" min="7" max="24" value="10" aria-label="Tamaño de letra de los valores de la tabla de conteo en PDF">
                                </label>
                            </div>
                            <div class="tm-export-personalize-field tm-export-count-table-align-group">
                                <span class="tm-export-label-inline">Alineación de tabla de conteo</span>
                                <div class="tm-export-align-btns tm-export-count-table-align-btns" id="tmExportCountAlignGroup" role="group" aria-label="Alineación de la tabla de conteo">
                                    <button type="button" class="tm-export-align-btn is-active" data-count-table-align="left">Izquierda</button>
                                    <button type="button" class="tm-export-align-btn" data-count-table-align="center">Centro</button>
                                    <button type="button" class="tm-export-align-btn" data-count-table-align="right">Derecha</button>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>
                        </div>
                    </div>

                    <div class="tm-export-config-card" data-tm-export-card="sum">
                        <div class="tm-export-config-card__head tm-export-config-card__head--menu" data-tm-export-card-advanced="tmExportSumTypographyAdvanced">
                            <span class="tm-export-config-card__title"><i class="fa-solid fa-sigma" aria-hidden="true"></i> Tabla de sumatoria (agrupada)</span>
                            <button type="button" class="tm-export-config-card__menu-btn" aria-haspopup="true" aria-expanded="false" aria-controls="tmExportSumTypographyAdvanced" title="Mostrar u ocultar tipografía de la tabla de sumatoria (también clic derecho en la cabecera)"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>
                        </div>
                        <div class="tm-export-config-card__body">
                    <div class="tm-export-personalize-field tm-export-sum-table-section">
                        <label class="tm-export-count-table-toggle">
                            <input type="checkbox" id="tmExportIncludeSumTable" value="1">
                            Incluir tabla de sumatoria (agrupada)
                        </label>
                        <div class="tm-export-sum-wrap" id="tmExportSumWrap" hidden>
                            <div class="tm-export-personalize-field-row tm-export-title-row-group" style="margin-top:4px;">
                                <div class="tm-export-personalize-field tm-export-field-title-input">
                                    <label for="tmExportSumTitle">Título de sumatoria</label>
                                    <input type="text" id="tmExportSumTitle" class="tm-input" placeholder="Sumatoria">
                                </div>
                                <div class="tm-export-case-toggle-wrap">
                                    <label class="tm-export-case-toggle" title="Convertir el título de sumatoria a MAYÚSCULAS">
                                        <input type="checkbox" id="tmExportSumTitleUppercase" value="1">
                                        <span class="tm-export-case-icon">Az</span>
                                    </label>
                                </div>
                            </div>
                            <div class="tm-export-personalize-field" style="max-width: 220px;">
                                <label for="tmExportSumTitleFontSize">Título de sumatoria (px)</label>
                                <input type="number" id="tmExportSumTitleFontSize" class="tm-input tm-input--num-compact" min="10" max="36" value="14">
                            </div>
                            <div class="tm-export-personalize-field tm-export-count-table-align-group">
                                <span class="tm-export-label-inline">Alineación del título de sumatoria</span>
                                <div class="tm-export-align-btns" id="tmExportSumTitleAlignGroup" role="group" aria-label="Alineación del título de sumatoria">
                                    <button type="button" class="tm-export-align-btn" data-sum-title-align="left">Izquierda</button>
                                    <button type="button" class="tm-export-align-btn is-active" data-sum-title-align="center">Centro</button>
                                    <button type="button" class="tm-export-align-btn" data-sum-title-align="right">Derecha</button>
                                </div>
                            </div>

                            <div id="tmExportSumTypographyAdvanced" class="tm-export-config-advanced" hidden data-tm-export-advanced>
                                <p class="tm-export-config-advanced__label">Tipografía de la tabla de sumatoria</p>
                                <div class="tm-export-field-row tm-export-field-row--fonts">
                                    <div class="tm-export-personalize-field">
                                        <label for="tmExportSumCellFontSize" title="Celdas de sumatoria">Sumatoria: celdas (px)</label>
                                        <input type="number" id="tmExportSumCellFontSize" class="tm-input tm-input--num-compact" min="9" max="24" value="12">
                                    </div>
                                    <div class="tm-export-personalize-field">
                                        <label for="tmExportSumHeaderFontSize" title="Encabezados de sumatoria">Sumatoria: encabezados (px)</label>
                                        <input type="number" id="tmExportSumHeaderFontSize" class="tm-input tm-input--num-compact" min="9" max="28" value="12">
                                    </div>
                                    <div class="tm-export-personalize-field">
                                        <label for="tmExportSumGroupHeaderFontSize" title="Fila de grupos en sumatoria">Sumatoria: grupos (px)</label>
                                        <input type="number" id="tmExportSumGroupHeaderFontSize" class="tm-input tm-input--num-compact" min="9" max="48" value="12">
                                    </div>
                                </div>
                            </div>

                            <label class="tm-seed-label">
                                Agrupar por
                                <select id="tmExportSumGroupBy" class="tm-input">
                                    <option value="microrregion">Microrregión</option>
                                    <option value="municipio">Municipio</option>
                                </select>
                            </label>

                            <div class="tm-export-sum-block" style="margin-top:8px;">
                                <div class="tm-export-groups-wrap__head">
                                    <span class="tm-export-label-inline">Columnas iniciales de sumatoria</span>
                                </div>
                                <div class="tm-export-field-row tm-export-field-row--fonts">
                                    <div class="tm-export-personalize-field">
                                        <label class="tm-export-count-table-toggle" style="margin-bottom:4px;">
                                            <input type="checkbox" id="tmExportSumShowItemCol" value="1" checked>
                                            Mostrar columna #
                                        </label>
                                        <input type="text" id="tmExportSumItemLabel" class="tm-input" value="#" placeholder="#">
                                    </div>
                                    <div class="tm-export-personalize-field">
                                        <label class="tm-export-count-table-toggle" style="margin-bottom:4px;">
                                            <input type="checkbox" id="tmExportSumShowDelegacionCol" value="1" checked>
                                            Mostrar Delegación (número MR)
                                        </label>
                                        <input type="text" id="tmExportSumDelegacionLabel" class="tm-input" value="Delegación" placeholder="Delegación">
                                    </div>
                                    <div class="tm-export-personalize-field">
                                        <label class="tm-export-count-table-toggle" style="margin-bottom:4px;">
                                            <input type="checkbox" id="tmExportSumShowCabeceraCol" value="1" checked>
                                            Mostrar Cabecera (MR)
                                        </label>
                                        <input type="text" id="tmExportSumCabeceraLabel" class="tm-input" value="Cabecera" placeholder="Cabecera">
                                    </div>
                                </div>
                            </div>

                            <div class="tm-export-personalize-field">
                                <span class="tm-export-label-inline">Color encabezado de Microrregión/Municipio</span>
                                <div class="tm-export-col-color" id="tmExportSumGroupColorWrap">
                                    <button type="button" class="tm-export-color-trigger" id="tmExportSumGroupColorTrigger" data-color="var(--clr-primary)" aria-haspopup="listbox" aria-expanded="false" title="Color del encabezado de la primera columna">
                                        <span class="tm-export-color-swatch" style="background-color:var(--clr-primary)"></span>
                                    </button>
                                    <div class="tm-export-color-menu" id="tmExportSumGroupColorMenu" role="listbox" hidden></div>
                                </div>
                            </div>

                            <div class="tm-export-sum-row tm-export-sum-row--sum-config">
                                <label class="tm-export-count-table-toggle">
                                    <input type="checkbox" id="tmExportSumIncludeTotalsRow" value="1">
                                    Incluir fila final de totales
                                </label>
                                <label class="tm-export-count-table-toggle">
                                    <input type="checkbox" id="tmExportSumTotalsBold" value="1" checked>
                                    Totales en negrita
                                </label>
                            </div>
                            <div class="tm-export-personalize-field">
                                <span class="tm-export-label-inline">Color de texto de fila de totales</span>
                                <div class="tm-export-col-color" id="tmExportSumTotalsColorWrap">
                                    <button type="button" class="tm-export-color-trigger" id="tmExportSumTotalsColorTrigger" data-color="var(--clr-primary)" aria-haspopup="listbox" aria-expanded="false" title="Color del texto de la fila de totales">
                                        <span class="tm-export-color-swatch" style="background-color:var(--clr-primary)"></span>
                                    </button>
                                    <div class="tm-export-color-menu" id="tmExportSumTotalsColorMenu" role="listbox" hidden></div>
                                </div>
                            </div>

                            <div class="tm-export-personalize-field tm-export-count-table-align-group">
                                <span class="tm-export-label-inline">Alineación de tabla de sumatoria</span>
                                <div class="tm-export-align-btns tm-export-sum-table-align-btns" id="tmExportSumAlignGroup" role="group" aria-label="Alineación de la tabla de sumatoria">
                                    <button type="button" class="tm-export-align-btn is-active" data-sum-table-align="left">Izquierda</button>
                                    <button type="button" class="tm-export-align-btn" data-sum-table-align="center">Centro</button>
                                    <button type="button" class="tm-export-align-btn" data-sum-table-align="right">Derecha</button>
                                </div>
                            </div>

                            <div class="tm-export-sum-block">
                                <div class="tm-export-groups-wrap__head">
                                    <span class="tm-export-label-inline">Métricas base</span>
                                    <button type="button" class="tm-btn tm-btn-sm tm-btn-outline" id="tmExportAddSumMetricBtn">+ Métrica</button>
                                </div>
                                <div id="tmExportSumMetricsList" class="tm-export-sum-list"></div>
                            </div>

                            <div class="tm-export-sum-block">
                                <div class="tm-export-groups-wrap__head">
                                    <span class="tm-export-label-inline">Columnas calculadas</span>
                                    <button type="button" class="tm-btn tm-btn-sm tm-btn-outline" id="tmExportAddSumFormulaBtn">+ Cálculo</button>
                                </div>
                                <div id="tmExportSumFormulasList" class="tm-export-sum-list"></div>
                                <p class="tm-analysis-hint" style="margin-top:6px;">Puedes asignar grupo a cada columna. También puedes crear porcentaje: elige métrica base (100%) y la métrica numerador para calcular su %.</p>
                            </div>
                        </div>
                    </div>
                        </div>
                    </div>

                    <div class="tm-export-config-card tm-export-config-card--nested" id="tmExportTotalsIndepCard" data-tm-export-card="totals" hidden>
                        <div class="tm-export-config-card__head tm-export-config-card__head--menu" data-tm-export-card-advanced="tmExportTotalsTypographyAdvanced">
                            <span class="tm-export-config-card__title"><i class="fa-solid fa-table-list" aria-hidden="true"></i> Tabla de totales independiente</span>
                            <button type="button" class="tm-export-config-card__menu-btn" aria-haspopup="true" aria-expanded="false" aria-controls="tmExportTotalsTypographyAdvanced" title="Mostrar u ocultar tipografía de la tabla de totales (también clic derecho en la cabecera)"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>
                        </div>
                        <div class="tm-export-config-card__body">
                            <p class="tm-analysis-hint" style="margin:0 0 8px;">Activa la sumatoria arriba; esta opción anexa los totales como tabla aparte.</p>
                            <div class="tm-export-sum-row tm-export-sum-row--sum-config">
                                <label class="tm-export-count-table-toggle">
                                    <input type="checkbox" id="tmExportIncludeTotalsTable" value="1">
                                    Anexar fila de totales como tabla independiente
                                </label>
                            </div>
                            <div class="tm-export-personalize-field">
                                <label for="tmExportTotalsTableTitle">Título de tabla de totales</label>
                                <input type="text" id="tmExportTotalsTableTitle" class="tm-input" placeholder="Totales">
                            </div>
                            <div class="tm-export-sum-block" id="tmExportTotalsTableWrap" hidden>
                                <div class="tm-export-personalize-field tm-export-count-table-align-group">
                                    <span class="tm-export-label-inline">Alineación de tabla de totales</span>
                                    <div class="tm-export-align-btns" id="tmExportTotalsTableAlignGroup" role="group" aria-label="Alineación de la tabla de totales">
                                        <button type="button" class="tm-export-align-btn is-active" data-totals-table-align="left">Izquierda</button>
                                        <button type="button" class="tm-export-align-btn" data-totals-table-align="center">Centro</button>
                                        <button type="button" class="tm-export-align-btn" data-totals-table-align="right">Derecha</button>
                                    </div>
                                </div>
                            </div>
                            <div id="tmExportTotalsTypographyAdvanced" class="tm-export-config-advanced" hidden data-tm-export-advanced>
                                <p class="tm-export-config-advanced__label">Tipografía de la tabla de totales</p>
                                <div class="tm-export-field-row tm-export-field-row--fonts">
                                    <div class="tm-export-personalize-field">
                                        <label for="tmExportTotalsCellFontSize">Totales: celdas (px)</label>
                                        <input type="number" id="tmExportTotalsCellFontSize" class="tm-input tm-input--num-compact" min="9" max="24" value="12">
                                    </div>
                                    <div class="tm-export-personalize-field">
                                        <label for="tmExportTotalsHeaderFontSize">Totales: encabezados (px)</label>
                                        <input type="number" id="tmExportTotalsHeaderFontSize" class="tm-input tm-input--num-compact" min="9" max="48" value="12">
                                    </div>
                                    <div class="tm-export-personalize-field">
                                        <label for="tmExportTotalsGroupHeaderFontSize">Totales: grupos (px)</label>
                                        <input type="number" id="tmExportTotalsGroupHeaderFontSize" class="tm-input tm-input--num-compact" min="9" max="48" value="12">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tm-export-config-card" data-tm-export-card="breakdown">
                        <div class="tm-export-config-card__head">
                            <span class="tm-export-config-card__title"><i class="fa-solid fa-list-ul" aria-hidden="true"></i> Tabla de desglose</span>
                        </div>
                        <div class="tm-export-config-card__body">
                            <p class="tm-analysis-hint" style="margin:0 0 8px;">Aparece cuando una columna usa desglose. Los colores por respuesta se configuran por columna en la pestaña Columnas.</p>
                            <div class="tm-export-personalize-field">
                                <label for="tmExportSectionLabel">Título encima del desglose</label>
                                <input type="text" id="tmExportSectionLabel" class="tm-input" placeholder="Desglose">
                            </div>
                            <div class="tm-export-personalize-field tm-export-count-table-align-group">
                                <span class="tm-export-label-inline">Alineación del título de desglose</span>
                                <div class="tm-export-align-btns" id="tmExportSectionLabelAlignGroup" role="group" aria-label="Alineación del título de desglose">
                                    <button type="button" class="tm-export-align-btn is-active" data-section-label-align="left">Izquierda</button>
                                    <button type="button" class="tm-export-align-btn" data-section-label-align="center">Centro</button>
                                    <button type="button" class="tm-export-align-btn" data-section-label-align="right">Derecha</button>
                                </div>
                            </div>
                        </div>
                    </div>
                        </section>
                        <section id="tm-export-sec-columns" class="tm-export-side-section">
                            <h4 class="tm-export-side-section__title"><span class="tm-export-side-section__icon" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span> Grupos y columnas</h4>
                            <p class="tm-export-side-section__lead">Misma disposición que verás en Excel, Word o PDF de datos.</p>
                        <div class="tm-export-groups-wrap tm-export-groups-wrap--panel">
                            <div class="tm-export-groups-wrap__head">
                                <span class="tm-export-label-inline">Grupos de columnas (fila superior)</span>
                                <button type="button" class="tm-btn tm-btn-sm tm-btn-outline" id="tmExportAddGroupBtn">+ Añadir grupo</button>
                            </div>
                            <div class="tm-export-personalize-field tm-export-data-table-align-group">
                                <span class="tm-export-label-inline">Alineación de tabla de datos</span>
                                <div class="tm-export-align-btns tm-export-data-table-align-btns" id="tmExportDataAlignGroup" role="group" aria-label="Alineación de la tabla de datos">
                                    <button type="button" class="tm-export-align-btn is-active" data-data-table-align="left">Izquierda</button>
                                    <button type="button" class="tm-export-align-btn" data-data-table-align="center">Centro</button>
                                    <button type="button" class="tm-export-align-btn" data-data-table-align="right">Derecha</button>
                                </div>
                            </div>
                            <div id="tmExportGroupsList" class="tm-export-groups-list">
                                <p class="tm-analysis-hint" id="tmExportNoGroupsHint">No hay grupos creados. Define grupos para agrupar campos bajo un mismo título.</p>
                            </div>
                        </div>

                        <p class="tm-export-personalize-hint">Arrastra columnas para reordenar. Usa &times; para omitir. Asigna un grupo a cada columna si aplica.</p>
                        <div class="tm-export-personalize-columns" id="tmExportPersonalizeColumns" role="list"></div>
                        <p class="tm-export-restore-wrap" id="tmExportRestoreWrap" hidden>
                            <button type="button" class="tm-export-restore-btn" id="tmExportRestoreBtn">Restaurar todas las columnas</button>
                        </p>
                        <div class="tm-export-restore-wrap" id="tmExportOmittedWrap" hidden>
                            <button type="button" class="tm-export-restore-btn" id="tmExportOmittedToggle" aria-expanded="false" aria-controls="tmExportOmittedList">Ver columnas eliminadas (0)</button>
                            <div id="tmExportOmittedList" hidden style="margin-top:8px; display:grid; gap:6px;"></div>
                        </div>
                        </section>
                        <section id="tm-export-sec-export" class="tm-export-side-section">
                            <h4 class="tm-export-side-section__title"><span class="tm-export-side-section__icon" aria-hidden="true"><i class="fa-solid fa-file-export"></i></span> Descargar</h4>
                            <p class="tm-export-side-section__lead">Los formatos de esta sección usan la tabla de registros (no el informe de análisis).</p>
                        <div class="tm-export-personalize-export-block">
                            <div class="tm-export-format-cards">
                                <div class="tm-export-format-card tm-export-format-card--excel">
                                    <div class="tm-export-format-card__head">
                                        <i class="fa-solid fa-file-excel tm-export-format-card__badge" aria-hidden="true"></i>
                                        <div>
                                            <span class="tm-export-format-card__name">Excel</span>
                                            <span class="tm-export-format-card__desc">Todos los registros; una o varias hojas.</span>
                                        </div>
                                    </div>
                                    <div class="tm-export-format-card__actions">
                                        <button type="button" class="tm-btn tm-btn-success tm-export-format-card__btn" id="tmExportApplyExcelSingle" title="Todos los registros en una sola hoja">Una hoja</button>
                                        <button type="button" class="tm-btn tm-btn-success tm-export-format-card__btn tm-export-btn-excel-mr" id="tmExportApplyExcelMr" title="Una hoja por microrregión">Por microrregión</button>
                                    </div>
                                </div>
                                <div class="tm-export-format-card tm-export-format-card--word">
                                    <div class="tm-export-format-card__head">
                                        <i class="fa-solid fa-file-word tm-export-format-card__badge" aria-hidden="true"></i>
                                        <div>
                                            <span class="tm-export-format-card__name">Word (.docx)</span>
                                            <span class="tm-export-format-card__desc">Tabla de datos con el diseño de la vista previa.</span>
                                        </div>
                                    </div>
                                    <div class="tm-export-format-card__actions">
                                        <button type="button" class="tm-btn tm-btn-primary tm-btn-word tm-export-format-card__btn tm-export-format-card__btn--full" id="tmExportApplyWordTable" title="Exportar a Word">Descargar Word</button>
                                    </div>
                                </div>
                                <div class="tm-export-format-card tm-export-format-card--pdf">
                                    <div class="tm-export-format-card__head">
                                        <i class="fa-solid fa-file-pdf tm-export-format-card__badge" aria-hidden="true"></i>
                                        <div>
                                            <span class="tm-export-format-card__name">PDF</span>
                                            <span class="tm-export-format-card__desc">Misma vista que la previsualización A4.</span>
                                        </div>
                                    </div>
                                    <div class="tm-export-format-card__actions">
                                        <button type="button" class="tm-btn tm-btn-danger tm-export-format-card__btn tm-export-format-card__btn--full" id="tmExportApplyPdfTable" title="Exportar a PDF">Descargar PDF</button>
                                    </div>
                                </div>
                            </div>
                            <div class="tm-export-analysis-callout">
                                <div class="tm-export-analysis-callout__text">
                                    <strong>Informe de análisis (Word)</strong>
                                    <span>Resumen, tablas por microrregión y columnas dinámicas (.docx). Es otro documento, independiente de la tabla de registros.</span>
                                </div>
                                <button type="button" class="tm-btn tm-btn-outline tm-export-analysis-callout__btn" id="tmExportOpenAnalysisReport" title="Abrir personalización del informe">
                                    <i class="fa-solid fa-chart-simple" aria-hidden="true"></i> Ir al informe
                                </button>
                            </div>
                        </div>
                        </section>
                        </div>
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
                                <label class="tm-export-paper-size" for="tmExportPaperSize" title="Tipo de hoja para PDF y Word">
                                    Hoja
                                    <select id="tmExportPaperSize" class="tm-input tm-export-paper-size-select" aria-label="Tipo de hoja">
                                        <option value="letter">Carta</option>
                                        <option value="legal">Oficio</option>
                                    </select>
                                </label>
                                <div class="tm-export-orient-group" role="group" aria-label="Orientación de la hoja">
                                    <button type="button" class="tm-export-orient-btn is-active" data-orientation="portrait">Vertical</button>
                                    <button type="button" class="tm-export-orient-btn" data-orientation="landscape">Horizontal</button>
                                </div>
                            </div>
                        </div>
                        <div class="tm-export-preview-a4" id="tmExportPreviewA4Area">
                            <div class="tm-export-preview-page" id="tmExportPreviewPage">
                                <div class="tm-export-preview-zoom-wrap" id="tmExportPreviewZoomWrap">
                                    <div class="tm-export-personalize-preview" id="tmExportPersonalizePreview"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tm-modal-foot tm-export-modal-foot tm-export-modal-foot--personalize">
                <button type="button" class="tm-btn tm-btn-outline" data-close-export-personalize>Cancelar</button>
                <div class="tm-export-save-actions">
                    <button type="button" class="tm-btn tm-btn-primary" id="tmExportSaveConfig">Guardar configuración</button>
                    <button type="button" class="tm-btn tm-btn-outline" id="tmExportClearConfig" title="Limpiar configuración guardada" aria-label="Limpiar configuración guardada">
                        <i class="fa-solid fa-broom" aria-hidden="true"></i>
                    </button>
                </div>
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
                <aside class="tm-analysis-sidebar tm-analysis-sidebar--sheets">
                    <p class="tm-analysis-sidebar-kicker">Vista rápida</p>
                    <div class="tm-analysis-sidebar-section">
                        <p class="tm-analysis-sidebar-title"><i class="fa-solid fa-list-check" aria-hidden="true"></i> Qué incluir</p>
                        <label class="tm-analysis-check"><input type="checkbox" id="tmAnalysisIncludeSummary" checked> <span>Resumen (totales)</span></label>
                        <label class="tm-analysis-check"><input type="checkbox" id="tmAnalysisIncludeMrTable" checked> <span>Tabla por microrregión / municipios</span></label>
                    </div>
                    <div class="tm-analysis-sidebar-section">
                        <p class="tm-analysis-sidebar-title"><i class="fa-solid fa-table-cells" aria-hidden="true"></i> Tabla vacía extra</p>
                        <p class="tm-analysis-sidebar-lead">Añade una cuadrícula vacía solo en la vista previa.</p>
                        <div class="tm-analysis-grid-inputs">
                            <label>Filas <input type="number" id="tmAnalysisCustomRows" min="0" max="30" value="0" class="tm-input tm-input-sm"></label>
                            <label>Columnas <input type="number" id="tmAnalysisCustomCols" min="0" max="12" value="0" class="tm-input tm-input-sm"></label>
                        </div>
                        <button type="button" class="tm-btn tm-btn-outline tm-analysis-build-grid" id="tmAnalysisBuildGridBtn">Crear tabla N×M</button>
                    </div>
                    <div class="tm-analysis-sidebar-actions">
                        <button type="button" class="tm-btn tm-btn-primary tm-analysis-sidebar-actions__primary" id="tmAnalysisRefreshPreview"><i class="fa-solid fa-rotate" aria-hidden="true"></i> Actualizar vista previa</button>
                    </div>
                    <p class="tm-analysis-hint tm-analysis-hint--sidebar">Mismo criterio que el antiguo Excel de análisis. Para título avanzado y tablas dinámicas usa «Editar y exportar».</p>
                </aside>
                <div class="tm-analysis-preview-panel">
                    <div class="tm-analysis-preview-label">Vista previa (estilo informe)</div>
                    <div class="tm-analysis-preview-doc" id="tmAnalysisPreviewDoc">
                        <div class="tm-analysis-preview-loading" id="tmAnalysisPreviewLoading">Cargando...</div>
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

    {{-- Personalizar informe Word (.docx) - se abre con «Editar y exportar» --}}
    <div class="tm-modal tm-export-personalize-modal tm-analysis-word-personalize-modal" id="tmAnalysisWordPersonalizeModal" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="tm-modal-backdrop" data-close-analysis-word-personalize></div>
        <div class="tm-modal-dialog tm-export-personalize-dialog tm-analysis-word-personalize-dialog">
            <div class="tm-modal-head">
                <h3>Resumen y tablas de análisis (.docx)</h3>
                <button type="button" class="tm-modal-close" data-close-analysis-word-personalize aria-label="Cerrar">&times;</button>
            </div>
            <div class="tm-modal-body tm-analysis-word-personalize-body">
                <div class="tm-export-personalize-content tm-analysis-word-personalize-content">
                    <div class="tm-export-personalize-form tm-analysis-word-personalize-form tm-export-side-panel tm-word-side-panel">
                        <p class="tm-export-side-panel-intro">Informe de análisis (.docx): resumen y tablas. Las pestañas agrupan opciones como en un panel lateral.</p>
                        <nav class="tm-export-side-tabs tm-word-side-tabs" role="tablist" aria-label="Secciones del informe Word">
                            <button type="button" role="tab" class="tm-export-side-tab is-active" data-tm-word-side-tab="tm-word-sec-doc" aria-selected="true">Documento</button>
                            <button type="button" role="tab" class="tm-export-side-tab" data-tm-word-side-tab="tm-word-sec-tables">Tablas</button>
                            <button type="button" role="tab" class="tm-export-side-tab" data-tm-word-side-tab="tm-word-sec-columns">Columnas</button>
                        </nav>
                        <div class="tm-export-side-scroll">
                        <section id="tm-word-sec-doc" class="tm-export-side-section">
                            <h4 class="tm-export-side-section__title"><span class="tm-export-side-section__icon" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span> Portada y orden</h4>
                        <div class="tm-export-personalize-field">
                            <label for="tmWordDocTitle">Título del documento</label>
                            <input type="text" id="tmWordDocTitle" class="tm-input" placeholder="Ej. Análisis general - nombre del módulo">
                        </div>
                        <div class="tm-export-personalize-field">
                            <label for="tmWordSubtitle">Subtítulo (opcional)</label>
                            <input type="text" id="tmWordSubtitle" class="tm-input" placeholder="Línea bajo el título">
                        </div>
                        <div class="tm-export-personalize-field tm-export-title-align">
                            <span class="tm-export-label-inline">Alineación del título</span>
                            <div class="tm-export-align-btns" role="group">
                                <button type="button" class="tm-export-align-btn" data-word-title-align="left">Izquierda</button>
                                <button type="button" class="tm-export-align-btn is-active" data-word-title-align="center">Centro</button>
                                <button type="button" class="tm-export-align-btn" data-word-title-align="right">Derecha</button>
                            </div>
                        </div>
                        <div class="tm-export-personalize-field">
                            <label for="tmWordTitleFontPx" title="Tamaño de letra del título">Título (px)</label>
                            <input type="number" id="tmWordTitleFontPx" class="tm-input tm-input--num-compact" min="10" max="36" value="18">
                        </div>
                        <div class="tm-export-personalize-field">
                            <label for="tmWordMicrorregionSort">Orden por número de microrregión</label>
                            <select id="tmWordMicrorregionSort" class="tm-input">
                                <option value="asc">Ascendente</option>
                                <option value="desc">Descendente</option>
                            </select>
                        </div>
                        </section>
                        <section id="tm-word-sec-tables" class="tm-export-side-section">
                            <h4 class="tm-export-side-section__title"><span class="tm-export-side-section__icon" aria-hidden="true"><i class="fa-solid fa-table"></i></span> Tablas del informe</h4>
                        <p class="tm-analysis-sidebar-title tm-analysis-sidebar-title--in-panel">Qué incluir</p>
                        <label class="tm-analysis-check"><input type="checkbox" id="tmWordIncludeSummary" checked> <span>Resumen (totales)</span></label>
                        <label class="tm-analysis-check"><input type="checkbox" id="tmWordIncludeMrTable" checked> <span>Tabla microrregión / municipios</span></label>
                        <label class="tm-analysis-check"><input type="checkbox" id="tmWordIncludeDynamic" checked> <span>Tabla por registro (columnas elegidas)</span></label>
                        <p class="tm-analysis-sidebar-title tm-analysis-sidebar-title--in-panel">Alineación y ancho</p>
                        <div class="tm-export-align-btns tm-word-table-align-btns" role="group" aria-label="Alineación tablas">
                            <button type="button" class="tm-export-align-btn is-active" data-word-table-align="left">Izquierda</button>
                            <button type="button" class="tm-export-align-btn" data-word-table-align="center">Centrada</button>
                            <button type="button" class="tm-export-align-btn" data-word-table-align="right">Derecha</button>
                            <button type="button" class="tm-export-align-btn" data-word-table-align="stretch" title="Ocupa todo el ancho útil">Ajustar ancho</button>
                        </div>
                        <p class="tm-analysis-sidebar-title tm-analysis-sidebar-title--in-panel">Texto y celdas</p>
                        <div class="tm-word-table-opts">
                            <label class="tm-word-table-opts__row">Texto (pt)
                                <select id="tmWordTableFontPt" class="tm-input tm-input-sm">
                                    @foreach ([7,8,9,10,11,12] as $pt)<option value="{{ $pt }}" {{ $pt === 9 ? 'selected' : '' }}>{{ $pt }}</option>@endforeach
                                </select>
                            </label>
                            <label class="tm-word-table-opts__row">Relleno celdas (px)
                                <select id="tmWordTableCellPad" class="tm-input tm-input-sm">
                                    @foreach ([3,4,6,8,10,12] as $px)<option value="{{ $px }}" {{ $px === 6 ? 'selected' : '' }}>{{ $px }}</option>@endforeach
                                </select>
                            </label>
                            <label class="tm-word-table-opts__row">Ancho máx. celda dinámica (px)
                                <input type="number" id="tmWordTableCellMax" class="tm-input tm-input-sm" min="72" max="280" value="140">
                            </label>
                        </div>
                        </section>
                        <section id="tm-word-sec-columns" class="tm-export-side-section">
                            <h4 class="tm-export-side-section__title"><span class="tm-export-side-section__icon" aria-hidden="true"><i class="fa-solid fa-columns"></i></span> Columnas dinámicas y KPIs</h4>
                        <p class="tm-analysis-hint tm-analysis-hint--tight">Arrastra cada campo a una columna (máx. 12). Referencia = primer registro.</p>
                        <div class="tm-word-field-palette" id="tmWordFieldPalette" aria-label="Campos del módulo"></div>
                        <div class="tm-word-column-slots-wrap">
                            <span class="tm-export-label-inline">Columnas a exportar</span>
                            <div class="tm-word-column-slots" id="tmWordColumnSlots"></div>
                        </div>
                        <p class="tm-analysis-sidebar-title tm-analysis-sidebar-title--in-panel">Estándar contable (desglose)</p>
                        <p class="tm-analysis-hint tm-analysis-hint--tight">Arriba: KPIs en una fila. Abajo: totales (suma números ≥0; en Sí/No solo cuenta <strong>Sí</strong>).</p>
                        <div id="tmWordAccountingFields" class="tm-word-accounting-fields"></div>
                        <button type="button" class="tm-btn tm-btn-primary tm-word-refresh-sticky" id="tmWordRefreshPreviewBtn"><i class="fa-solid fa-rotate" aria-hidden="true"></i> Actualizar vista previa</button>
                        </section>
                        </div>
                    </div>
                    <div class="tm-export-personalize-preview-wrap tm-analysis-word-preview-wrap">
                        <div class="tm-export-preview-toolbar">
                            <span class="tm-export-preview-label">Vista previa A4</span>
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
                    <input type="hidden" name="title_font_size_px" id="tmWordFormTitleFontPx" value="18">
                    <input type="hidden" name="microrregion_sort" id="tmWordFormMicrorregionSort" value="asc">
                    <button type="submit" class="tm-btn tm-btn-success">Generar Word con esta configuración</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Actividad: registros recientes por módulo (plegables) --}}
    <div
        class="tm-modal"
        id="tmAdminActivityModal"
        aria-hidden="true"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tmAdminActivityTitle"
    >
        <div class="tm-modal-backdrop" data-close-module-preview></div>
        <div class="tm-modal-dialog tm-modal-dialog-activity">
            <div class="tm-modal-head">
                <div class="tm-modal-head-stack">
                    <h3 id="tmAdminActivityTitle">Actividad — registros recientes</h3>
                    <p class="tm-modal-subtitle tm-muted">Por módulo (hasta 40 entradas recientes cada uno). Usa la misma búsqueda que arriba.</p>
                </div>
                <button type="button" class="tm-modal-close" data-close-module-preview aria-label="Cerrar">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="tm-modal-body tm-admin-activity-modal-body">
                <div class="tm-admin-activity-modal-toolbar">
                    <button type="button" class="tm-btn tm-btn-sm tm-btn-outline" id="tmAdminActivityExpandAll">Desplegar todo</button>
                    <button type="button" class="tm-btn tm-btn-sm tm-btn-outline" id="tmAdminActivityCollapseAll">Plegar todo</button>
                </div>
                @forelse ($activityFeed ?? [] as $am)
                    <details class="tm-admin-activity-module-details">
                        <summary>
                            <span class="tm-admin-activity-module-title">{{ $am->name }}</span>
                            <span class="tm-muted tm-admin-activity-module-count">{{ $am->entries->count() }} mostrado(s)</span>
                        </summary>
                        <ul class="tm-admin-activity-list">
                            @foreach ($am->entries as $ent)
                                <li>
                                    <span class="tm-admin-activity-li-main">
                                        <strong>#{{ $ent->id }}</strong>
                                        · Envío {{ $ent->submitted_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
                                        · Modif. {{ $ent->updated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
                                        @if ($ent->microrregion)
                                            <span>· MR {{ $ent->microrregion->microrregion }}</span>
                                        @endif
                                        @if ($ent->user)
                                            <span>· {{ $ent->user->name }}</span>
                                        @endif
                                    </span>
                                    @can('Modulos-Temporales-Admin')
                                        <span class="tm-admin-activity-li-actions">
                                            <form
                                                method="POST"
                                                action="{{ route('temporary-modules.admin.entry.destroy', [$am->id, $ent->id]) }}"
                                                class="tm-inline-form"
                                                data-confirm-delete-activity-entry
                                                data-entry-summary="{{ e('Registro #'.$ent->id.' del módulo «'.$am->name.'»') }}"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="tm-btn tm-btn-sm tm-btn-danger"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                            </form>
                                        </span>
                                    @endcan
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @empty
                    <p class="tm-muted">No hay módulos con registros{{ $searchQuery !== '' ? ' que coincidan con la búsqueda' : '' }}.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Log de filas descartadas al crear módulo desde Excel (solo si ya exportó) --}}
    <div
        class="tm-modal"
        id="tmSeedDiscardLogModal"
        aria-hidden="true"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tmSeedDiscardLogTitle"
        data-csrf-token="{{ csrf_token() }}"
    >
        <div class="tm-modal-backdrop" data-tm-seed-log-close></div>
        <div class="tm-modal-dialog tm-seed-log-dialog">
            <div class="tm-modal-head">
                <h3 id="tmSeedDiscardLogTitle">Log - filas no cargadas</h3>
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
                                <th>Enlazar municipio</th>
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
window.TM_ADMIN_RECORDS_BOOT = {
            exportPreviewLogoUrl: @json(asset('images/LogoSegobHorizontal.png')),
            exportUserConfigBase: @json(rtrim(route('temporary-modules.admin.index'), '/\\')),
            csrfToken: @json(csrf_token()),
        };
</script>
<script src="{{ asset('assets/js/modules/temporary-modules-admin-records.js') }}?v={{ @filemtime(public_path('assets/js/modules/temporary-modules-admin-records.js')) ?: time() }}"></script>
@endpush
