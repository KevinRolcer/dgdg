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
                                        Vaciar registros
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

                    @can('Enlace')
                    <div class="tm-enlace-eliminar-registro mb-3">
                        <button type="button" class="tm-btn tm-btn-danger" id="btnEliminarRegistroEnlace">Eliminar registro de Municipios contestados hoy</button>
                    </div>
                    @endcan
                    @php
                        $municipiosMap = \App\Models\Municipio::with('microrregion')->get()->mapWithKeys(function ($m) {
                            $norm = \Illuminate\Support\Str::slug($m->nombre, '');
                            $mrName = $m->microrregion ? 'Microrregión ' . $m->microrregion->microrregion . ' - ' . $m->microrregion->cabecera : 'Sin microrregión asignada';
                            return [$norm => $mrName];
                        })->toArray();

                        $moduleFields = $module->fields;

                        $getMunicipioName = function ($entry) use ($moduleFields) {
                            $data = is_array($entry->data) ? $entry->data : (array)$entry->data;

                            // Iterate through all fields looking for "municipio"
                            foreach ($moduleFields as $field) {
                                $label = $field->label ?? $field['label'] ?? '';
                                $key = $field->key ?? $field['key'] ?? '';

                                if (stripos($label, 'municipio') !== false || stripos((string)$key, 'municipio') !== false) {
                                    $val = $data[$key] ?? null;
                                    if (is_string($val) && trim($val) !== '') {
                                        return trim($val);
                                    }
                                }
                            }
                            return 'Sin municipio especificado';
                        };

                        $getMicrorregionName = function ($entry) use ($getMunicipioName, $municipiosMap) {
                            // Option 1: It has an explicit microrregion relation already saved
                            if ($entry->microrregion && $entry->microrregion->microrregion) {
                                return 'Microrregión ' . $entry->microrregion->microrregion . ' - ' . $entry->microrregion->cabecera;
                            }
                            // Option 2: Fallback to mapping the text municipio from $entry->data
                            $mpioName = $getMunicipioName($entry);
                            if ($mpioName !== 'Sin municipio especificado') {
                                $norm = \Illuminate\Support\Str::slug($mpioName, '');
                                if (isset($municipiosMap[$norm])) {
                                    return $municipiosMap[$norm];
                                }
                            }
                            return 'Sin microrregión asignada';
                        };

                        // First group by Microrregión (Using explicit ID or mapped from string)
                        $groupedByMr = collect($module->entries)->groupBy($getMicrorregionName);

                        // Calculate unique municipalities
                        $uniqueMpiosCount = collect($module->entries)->map($getMunicipioName)->filter(function($mp) {
                            return $mp !== 'Sin municipio especificado';
                        })->unique()->count();
                    @endphp

                    <div style="margin-bottom: 24px; display: flex; flex-wrap: wrap; gap: 24px;">
                        <h4 style="color: var(--clr-accent, #c79b66); margin: 0; font-weight: 600;">
                            <i class="fa-solid fa-list-ol"></i> Total de Registros: {{ $module->entries->count() }}
                        </h4>
                        <h4 style="color: var(--clr-accent, #c79b66); margin: 0; font-weight: 600;">
                            <i class="fa-solid fa-map"></i> Municipios registrados: {{ $uniqueMpiosCount }}
                        </h4>
                    </div>

                    @if ($module->entries->isEmpty())
                        <div class="tm-table-wrap tm-table-wrap-admin-preview">
                            <p style="text-align: center; padding: 20px;">Sin registros capturados todavía.</p>
                        </div>
                    @else
                        <div class="tm-admin-preview-accordion">
                            @foreach ($groupedByMr as $mrName => $mrEntries)
                                @php
                                    $mrLatestEntry = $mrEntries->sortByDesc('submitted_at')->first();
                                    $mrUsers = $mrEntries->pluck('user.name')->filter()->unique()->implode(', ');

                                    // Second group by Municipio
                                    $groupedByMpio = $mrEntries->groupBy($getMunicipioName);
                                @endphp
                                <details class="tm-admin-preview-card">
                                    <summary class="tm-admin-preview-summary">
                                        <div class="tm-preview-summary-header">
                                            <strong class="tm-preview-mr-name">{{ $mrName }}</strong>
                                            <span class="tm-badge tm-badge-primary" style="margin-left:8px;">{{ $mrEntries->count() }} registro(s)</span>
                                        </div>
                                        <div class="tm-preview-summary-meta">
                                            <span title="Usuario(s)"><i class="fa fa-user"></i> {{ $mrUsers ?: 'Sin usuario' }}</span>
                                            <span title="Última captura"><i class="fa fa-calendar"></i> {{ optional($mrLatestEntry->submitted_at)->format('d/m/Y H:i') }}</span>
                                        </div>
                                        <div class="tm-preview-summary-icon">
                                            <i class="fa-solid fa-chevron-down"></i>
                                        </div>
                                    </summary>

                                    <div class="tm-admin-preview-detail tm-admin-preview-mpio-container">
                                        @foreach ($groupedByMpio as $mpioName => $mpioEntries)
                                            <details class="tm-mpio-group-details">
                                                <summary class="tm-mpio-summary">
                                                    <div class="tm-mpio-summary-header">
                                                        <i class="fa-solid fa-map-location-dot"></i> <strong>{{ $mpioName }}</strong>
                                                        <span class="tm-badge tm-badge-secondary" style="margin-left: 8px;">{{ $mpioEntries->count() }} registro(s)</span>
                                                    </div>
                                                    <i class="fa-solid fa-chevron-down tm-mpio-chevron"></i>
                                                </summary>
                                                <div class="tm-mpio-detail-content">
                                                    <div class="tm-records-grid">
                                                        @foreach ($mpioEntries as $entry)
                                                            <div class="tm-record-item">
                                                            <div class="tm-record-item-header">
                                                                <span class="tm-record-user"><i class="fa-solid fa-user-circle"></i> {{ $entry->user->name ?? 'Sin usuario' }}</span>
                                                                <span class="tm-record-date"><i class="fa-regular fa-clock"></i> {{ optional($entry->submitted_at)->format('d/m/Y H:i') }}</span>
                                                            </div>
                                                            <div class="tm-record-item-body">
                                                                @foreach ($module->fields as $field)
                                                                    @php
                                                                        $cell = $entry->data[$field->key] ?? null;
                                                                    @endphp
                                                                    <div class="tm-record-field">
                                                                        <div class="tm-record-label">{{ $field->label }}</div>
                                                                        <div class="tm-record-value">
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
                                                                            @elseif ($field->type === 'semaforo' && is_string($cell) && $cell !== '')
                                                                                @php $semLab = \App\Services\TemporaryModules\TemporaryModuleFieldService::labelForSemaforo($cell); @endphp
                                                                                <span class="tm-semaforo-badge tm-semaforo-badge--{{ $cell }}" title="{{ $semLab }}">{{ $semLab }}</span>
                                                                            @else
                                                                                @php
                                                                                    if (is_array($cell)) {
                                                                                        $displayText = implode(', ', array_map(function ($item) {
                                                                                            return is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE);
                                                                                        }, $cell));
                                                                                    } elseif (is_scalar($cell)) {
                                                                                        $displayText = (string) $cell;
                                                                                    } else {
                                                                                        $displayText = '-';
                                                                                    }
                                                                                    $displayText = trim($displayText) !== '' ? $displayText : '-';
                                                                                @endphp
                                                                                <span class="tm-cell-text-wrap" data-text-wrap>
                                                                                    <span class="tm-cell-text is-collapsed" data-text-content>{{ $displayText }}</span>
                                                                                    @if (mb_strlen($displayText) > 120)
                                                                                        <span class="tm-cell-actions">
                                                                                            <button type="button" class="tm-cell-text-toggle" data-text-toggle>Ver mas</button>
                                                                                        </span>
                                                                                    @endif
                                                                                </span>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                            </details>
                                        @endforeach
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    @endif
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
                            <button type="button" role="tab" class="tm-export-side-tab" data-tm-export-side-tab="tm-export-sec-layout">Tabla</button>
                            <button type="button" role="tab" class="tm-export-side-tab" data-tm-export-side-tab="tm-export-sec-columns">Columnas</button>
                            <button type="button" role="tab" class="tm-export-side-tab" data-tm-export-side-tab="tm-export-sec-export">Exportar</button>
                        </nav>
                        <div class="tm-export-side-scroll">
                        <section id="tm-export-sec-title" class="tm-export-side-section">
                            <h4 class="tm-export-side-section__title"><span class="tm-export-side-section__icon" aria-hidden="true"><i class="fa-solid fa-heading"></i></span> Título y tipografía</h4>
                            <p class="tm-export-side-section__lead">Encabezado del documento y tamaños de fuente.</p>
                        <div class="tm-export-personalize-field">
                            <label for="tmExportPersonalizeTitle">Título del documento</label>
                            <input type="text" id="tmExportPersonalizeTitle" class="tm-input" placeholder="Nombre del módulo">
                        </div>
                    <div class="tm-export-personalize-field">
                        <label class="tm-export-count-table-toggle">
                            <input type="checkbox" id="tmExportTitleUppercase" value="1">
                            Convertir el título del documento a MAYÚSCULAS
                        </label>
                    </div>
                    <div class="tm-export-personalize-field">
                        <label class="tm-export-count-table-toggle">
                            <input type="checkbox" id="tmExportHeadersUppercase" value="1">
                            Convertir encabezados y títulos de tabla a MAYÚSCULAS
                        </label>
                    </div>
                    <div class="tm-export-personalize-field tm-export-title-align">
                        <span class="tm-export-label-inline">Alineación del título</span>
                        <div class="tm-export-align-btns" role="group" aria-label="Alineación del título">
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
                            <label for="tmExportCellFontSize" title="Tamaño de letra en celdas de datos">Celdas (px)</label>
                            <input type="number" id="tmExportCellFontSize" class="tm-input tm-input--num-compact" min="9" max="24" value="12">
                        </div>
                        <div class="tm-export-personalize-field">
                            <label for="tmExportHeaderFontSize" title="Tamaño de letra de encabezados de columnas">Encabezados (px)</label>
                            <input type="number" id="tmExportHeaderFontSize" class="tm-input tm-input--num-compact" min="9" max="28" value="12">
                        </div>
                    </div>
                    <p class="tm-analysis-hint tm-export-font-hint">Celdas: contenido de la tabla. Encabezados: fila de títulos de columnas.</p>
                        </section>
                        <section id="tm-export-sec-layout" class="tm-export-side-section">
                            <h4 class="tm-export-side-section__title"><span class="tm-export-side-section__icon" aria-hidden="true"><i class="fa-solid fa-table"></i></span> Tabla principal y conteos</h4>
                            <p class="tm-export-side-section__lead">Orden por microrregión y tabla de conteo opcional.</p>
                    <div class="tm-export-personalize-field">
                        <label for="tmExportMicrorregionSort">Orden por número de microrregión</label>
                        <select id="tmExportMicrorregionSort" class="tm-input">
                            <option value="asc">Ascendente</option>
                            <option value="desc">Descendente</option>
                        </select>
                        <p class="tm-analysis-hint" style="margin-top:6px;">La vista previa y el archivo exportado seguirán este orden por MR.</p>
                    </div>
                    <div class="tm-export-personalize-field tm-export-count-table-section">
                        <label class="tm-export-count-table-toggle">
                            <input type="checkbox" id="tmExportIncludeCountTable" value="1">
                            Incluir tabla de conteo general arriba de la tabla de registros
                        </label>
                        <div class="tm-export-count-by-wrap" id="tmExportCountByWrap" hidden>
                            <div class="tm-export-count-table-sizes">
                                <span class="tm-export-label-inline">Tamaños de las celdas:</span>
                                <label class="tm-export-count-cell-width-label">
                                    Ancho columnas (ch):
                                    <input type="number" id="tmExportCountTableCellWidth" class="tm-export-count-cell-width-input" min="6" max="40" value="12" aria-label="Ancho de columnas de la tabla de conteo en caracteres">
                                </label>
                            </div>
                            <span class="tm-export-label-inline">Conteo por valor de:</span>
                            <div class="tm-export-count-by-fields" id="tmExportCountByFields" role="group"></div>
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
                            <div id="tmExportGroupsList" class="tm-export-groups-list">
                                <p class="tm-analysis-hint" id="tmExportNoGroupsHint">No hay grupos creados. Define grupos para agrupar campos bajo un mismo título.</p>
                            </div>
                        </div>

                        <p class="tm-export-personalize-hint">Arrastra columnas para reordenar. Usa &times; para omitir. Asigna un grupo a cada columna si aplica.</p>
                        <div class="tm-export-personalize-columns" id="tmExportPersonalizeColumns" role="list"></div>
                        <p class="tm-export-restore-wrap" id="tmExportRestoreWrap" hidden>
                            <button type="button" class="tm-export-restore-btn" id="tmExportRestoreBtn">Restaurar todas las columnas</button>
                        </p>
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
                <button type="button" class="tm-btn tm-btn-primary" id="tmExportSaveConfig">Guardar configuración</button>
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
        const TM_EXPORT_PREVIEW_LOGO_URL = @json(asset('images/LogoSegobHorizontal.png'));

        function openSeedDiscardLog(moduleName, jsonId, registerUrl) {
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
            var csrf = seedLogModal.getAttribute('data-csrf-token') || '';
            if (list.length === 0) {
                seedLogEmpty.hidden = false;
                seedLogTableWrap.hidden = true;
                seedLogTbody.innerHTML = '';
            } else {
                seedLogEmpty.hidden = true;
                seedLogTableWrap.hidden = false;
                if (window.tmSeedDiscardLog && registerUrl) {
                    window.tmSeedDiscardLog.renderRows(seedLogTbody, list, {
                        registerUrl: registerUrl,
                        csrfToken: csrf,
                        jsonScriptEl: el,
                        onUpdateList: function () {},
                        onEmpty: function () {
                            seedLogEmpty.hidden = false;
                            seedLogTableWrap.hidden = true;
                        },
                    });
                } else {
                    seedLogTbody.innerHTML = '';
                    list.forEach(function (row) {
                        var tr = document.createElement('tr');
                        var esc = function (s) {
                            if (s == null || s === '') return '-';
                            var d = document.createElement('div');
                            d.textContent = String(s);
                            return d.innerHTML;
                        };
                        tr.innerHTML =
                            '<td>' + esc(row.row) + '</td>' +
                            '<td>' + esc(row.reason) + '</td>' +
                            '<td>' + esc(row.microrregion) + '</td>' +
                            '<td>' + esc(row.municipio) + '</td>' +
                            '<td class="tm-seed-log-accion">' + esc(row.accion) + '</td>' +
                            '<td>-</td>';
                        seedLogTbody.appendChild(tr);
                    });
                }
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
                openSeedDiscardLog(
                    btn.getAttribute('data-module-name'),
                    btn.getAttribute('data-json-id'),
                    btn.getAttribute('data-register-url') || ''
                );
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
                    var now = new Date();
                    var dateStr = now.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' +
                                 now.getHours().toString().padStart(2, '0') + ':' +
                                 now.getMinutes().toString().padStart(2, '0');
                    var html = '<div class="tm-analysis-doc-inner">';
                    html += '<h4 class="tm-analysis-doc-title">' + escapeHtml(data.module_name || 'Módulo') + '</h4>';
                    html += '<p class="tm-analysis-doc-sub" style="text-align:right">Fecha y hora de corte: ' + escapeHtml(dateStr) + '</p>';
                    html += '<p class="tm-analysis-doc-sub">Análisis general (Preliminar)</p>';
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
                        if (data.mr_table.length > 8) html += '<tr><td colspan="6">... +' + (data.mr_table.length - 8) + ' filas</td></tr>';
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
                            + '<div class="tm-word-slot-ref" title="' + escapeHtml(ref || '') + '">' + escapeHtml(ref || '-') + '</div>'
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
            var now = new Date();
            var dateStrFixed = now.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' +
                               now.getHours().toString().padStart(2, '0') + ':' +
                               now.getMinutes().toString().padStart(2, '0');

            inner += '<div class="tm-word-preview-sheet tm-word-preview-sheet--' + orient + '">';
            inner += '<div class="tm-analysis-doc-inner tm-word-overlay-inner tm-word-tables-fixed tm-word-tables-align-' + tblAlign + '" style="text-align:' + align + ';--tw-table-font:' + fontPt + 'pt;--tw-cell-pad:' + padPx + 'px;--tw-cell-max:' + maxPx + 'px">';
            inner += '<h4 class="tm-analysis-doc-title" style="text-align:' + align + '">' + escapeHtml(data.doc_title || data.module_name || 'Módulo') + '</h4>';
            inner += '<p class="tm-analysis-doc-sub" style="text-align:right">Fecha y hora de corte: ' + escapeHtml(dateStrFixed) + '</p>';
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
                if (data.mr_table.length > 5) inner += '<tr><td colspan="6" style="' + cellStyle + '">... +' + (data.mr_table.length - 5) + ' filas</td></tr>';
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
            var mrSortEl = document.getElementById('tmWordMicrorregionSort');
            var mrSort = mrSortEl ? mrSortEl.value : 'asc';
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
                + '&title_font_size_px=' + encodeURIComponent(document.getElementById('tmWordTitleFontPx') ? document.getElementById('tmWordTitleFontPx').value : '18')
                + '&microrregion_sort=' + encodeURIComponent(mrSort === 'desc' ? 'desc' : 'asc')
                + '&_=' + Date.now();
            applyWordPreviewSheetOrientation(orient);
            wordPersonalizePreview.innerHTML = '<div class="tm-analysis-preview-loading">Actualizando...</div>';
            fetch(u, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (title && !title.value.trim() && data.module_name) {
                        title.value = 'Análisis general - ' + data.module_name;
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
            if (document.getElementById('tmWordMicrorregionSort') && !document.getElementById('tmWordMicrorregionSort').dataset.touched) document.getElementById('tmWordMicrorregionSort').value = 'asc';
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
            wordPersonalizeModal.addEventListener('click', function (e) {
                var wtab = e.target.closest('[data-tm-word-side-tab]');
                if (wtab && wordPersonalizeModal.contains(wtab)) {
                    var wid = wtab.getAttribute('data-tm-word-side-tab');
                    var wsec = wid ? document.getElementById(wid) : null;
                    if (wsec) {
                        wordPersonalizeModal.querySelectorAll('.tm-word-side-tabs .tm-export-side-tab').forEach(function (t) {
                            var on = t === wtab;
                            t.classList.toggle('is-active', on);
                            t.setAttribute('aria-selected', on ? 'true' : 'false');
                        });
                        wsec.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                    return;
                }
            });
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
                document.getElementById('tmWordFormTitleFontPx').value = document.getElementById('tmWordTitleFontPx') ? (document.getElementById('tmWordTitleFontPx').value || '18') : '18';
                document.getElementById('tmWordFormMicrorregionSort').value = document.getElementById('tmWordMicrorregionSort') ? ((document.getElementById('tmWordMicrorregionSort').value === 'desc') ? 'desc' : 'asc') : 'asc';
            });
            ['tmWordTableFontPt', 'tmWordTableCellPad', 'tmWordTableCellMax', 'tmWordTitleFontPx', 'tmWordMicrorregionSort'].forEach(function (id) {
                var n = document.getElementById(id);
                if (n) {
                    n.addEventListener('change', function () {
                        if (id === 'tmWordMicrorregionSort') { n.dataset.touched = '1'; }
                        loadWordPersonalizePreview();
                    });
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

        /** Base64 UTF-8: evita que caracteres no Latin-1 rompan btoa y reduce corrupción del JSON en POST largos. */
        function b64EncodeUtf8(str) {
            return btoa(unescape(encodeURIComponent(str)));
        }

        /** Exportar con cfg grande: GET trunca la URL; POST usa Base64 para que el JSON llegue intacto al servidor. */
        function submitTemporaryModuleExportPost(actionUrl, format, mode, cfgObject) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = actionUrl;
            form.style.display = 'none';
            form.setAttribute('accept-charset', 'UTF-8');
            var csrf = document.querySelector('meta[name="csrf-token"]');
            if (csrf && csrf.getAttribute('content')) {
                var tok = document.createElement('input');
                tok.type = 'hidden';
                tok.name = '_token';
                tok.value = csrf.getAttribute('content');
                form.appendChild(tok);
            }
            function add(name, value) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = name;
                inp.value = value;
                form.appendChild(inp);
            }
            add('format', format || 'excel');
            add('mode', mode || 'single');
            add('cfg', b64EncodeUtf8(JSON.stringify(cfgObject)));
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        Array.from(document.querySelectorAll('[data-close-export-personalize]')).forEach(function (el) {
            el.addEventListener('click', closePersonalizeModal);
        });

        if (personalizeModal) {
            personalizeModal.addEventListener('click', function (e) {
                var sideTab = e.target.closest('[data-tm-export-side-tab]');
                if (sideTab && personalizeModal.contains(sideTab)) {
                    var secId = sideTab.getAttribute('data-tm-export-side-tab');
                    var secEl = secId ? document.getElementById(secId) : null;
                    if (secEl) {
                        personalizeModal.querySelectorAll('.tm-export-side-tabs .tm-export-side-tab').forEach(function (t) {
                            var on = t === sideTab;
                            t.classList.toggle('is-active', on);
                            t.setAttribute('aria-selected', on ? 'true' : 'false');
                        });
                        secEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                    return;
                }
                var analysisJump = e.target.closest('#tmExportOpenAnalysisReport');
                if (analysisJump) {
                    var refBtn = personalizeModal._exportButtonRef;
                    if (refBtn) {
                        var previewUrl = refBtn.getAttribute('data-analysis-preview-url');
                        var wordUrl = refBtn.getAttribute('data-analysis-word-url');
                        if (previewUrl && wordUrl) {
                            closePersonalizeModal();
                            window._tmAnalysisPreviewUrl = previewUrl;
                            window._tmAnalysisWordUrl = wordUrl;
                            var wpf = document.getElementById('tmAnalysisWordPersonalizeForm');
                            if (wpf) { wpf.setAttribute('action', wordUrl); }
                            document.body.style.overflow = 'hidden';
                            openWordPersonalizeModal();
                        }
                    }
                    return;
                }
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

        function renderExportGroups() {
            var listEl = document.getElementById('tmExportGroupsList');
            var hintEl = document.getElementById('tmExportNoGroupsHint');
            if (!listEl) return;
            var groups = (personalizeModal && personalizeModal._exportGroups) || [];
            listEl.innerHTML = '';
            if (groups.length === 0) {
                if (hintEl) hintEl.hidden = false;
                return;
            }
            if (hintEl) hintEl.hidden = true;
            groups.forEach(function (g) {
                var tag = document.createElement('div');
                tag.className = 'tm-export-group-tag';
                tag.style.cssText = 'background: #f1f5f9; border: 1px solid #cbd5e1; padding: 4px 10px; border-radius: 16px; display: flex; align-items: center; gap: 8px; font-size: 0.8rem;';
                tag.innerHTML = '<strong>' + escapeHtml(g) + '</strong>' +
                    '<button type="button" class="tm-export-group-remove" data-group="' + escapeHtml(g) + '" style="border:none; background:none; cursor:pointer; color:#ef4444; font-weight:bold; padding:0 4px;">&times;</button>';
                listEl.appendChild(tag);
            });
            // Re-render columns list to update selects
            var columnsEl = document.getElementById('tmExportPersonalizeColumns');
            if (columnsEl && personalizeModal && personalizeModal._personalizeColumns) {
                buildPersonalizeColumnsList(reorderColumnsList(columnsEl, personalizeModal._personalizeColumns), columnsEl);
            }
        }

        function buildPersonalizeColumnsList(columns, container) {
            container.innerHTML = '';
            var groups = (personalizeModal && personalizeModal._exportGroups) || [];
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

                var groupSelect = '<select class="tm-input tm-export-col-group-select" data-key="' + escapeHtml(col.key) + '">' +
                    '<option value="">Sin grupo</option>' +
                    groups.map(function(g) {
                        return '<option value="' + escapeHtml(g) + '"' + (col.group === g ? ' selected' : '') + '>' + escapeHtml(g) + '</option>';
                    }).join('') +
                    '</select>';

                var secondaryRow = '';
                if (col.is_image) {
                    secondaryRow =
                        '<div class="tm-export-col-row tm-export-col-row--secondary tm-export-col-row--image">' +
                        '<label class="tm-export-col-field tm-export-col-field--stretch">' +
                        '<span class="tm-export-col-field__lab">Grupo</span>' + groupSelect + '</label>' +
                        '<label class="tm-export-col-field">' +
                        '<span class="tm-export-col-field__lab">Ancho (px)</span>' +
                        '<input type="number" min="40" max="400" value="' + String(col.image_width || 120) + '" class="tm-input tm-input--num-compact tm-export-image-width" data-key="' + escapeHtml(col.key) + '">' +
                        '</label>' +
                        '<label class="tm-export-col-field">' +
                        '<span class="tm-export-col-field__lab">Alto (px)</span>' +
                        '<input type="number" min="30" max="300" value="' + String(col.image_height || 80) + '" class="tm-input tm-input--num-compact tm-export-image-height" data-key="' + escapeHtml(col.key) + '">' +
                        '</label></div>';
                } else {
                    var approx = col.max_width_chars || 24;
                    if (!Number.isFinite(approx)) { approx = 24; }
                    approx = Math.max(2, Math.min(approx, 60));
                    secondaryRow =
                        '<div class="tm-export-col-row tm-export-col-row--secondary">' +
                        '<label class="tm-export-col-field tm-export-col-field--stretch">' +
                        '<span class="tm-export-col-field__lab">Grupo</span>' + groupSelect + '</label>' +
                        '<label class="tm-export-col-field tm-export-col-field--wch">' +
                        '<span class="tm-export-col-field__lab">Ancho (ch)</span>' +
                        '<input type="number" class="tm-input tm-input--num-compact tm-export-col-width-input" min="2" max="60" value="' + String(approx) + '" data-key="' + escapeHtml(col.key) + '" data-width-hint="' + escapeHtml(String(approx)) + '">' +
                        '</label></div>';
                }
                var currentLabel = (col.label != null && String(col.label).trim() !== '') ? String(col.label) : String(col.key || '');
                item.innerHTML =
                    '<span class="tm-export-drag-handle" aria-hidden="true" title="Arrastrar para reordenar">&#9776;</span>' +
                    '<div class="tm-export-col-main">' +
                        '<div class="tm-export-col-row tm-export-col-row--primary">' +
                        '<label class="tm-export-col-field tm-export-col-field--grow">' +
                        '<span class="tm-export-col-field__lab">Columna</span>' +
                        '<input type="text" class="tm-input tm-export-col-label-input" data-key="' + escapeHtml(col.key) + '" value="' + escapeHtml(currentLabel) + '" placeholder="Nombre en el export">' +
                        '</label>' +
                        '<div class="tm-export-col-toolbar">' +
                        '<div class="tm-export-col-color">' +
                        '<button type="button" class="tm-export-color-trigger" data-color="' + escapeHtml(col.color || TEMPLATE_COLORS[0].value) + '" aria-haspopup="listbox" aria-expanded="false" title="Color del encabezado">' +
                        '<span class="tm-export-color-swatch" style="background-color:' + escapeHtml(col.color || TEMPLATE_COLORS[0].value) + '"></span></button>' +
                        '<div class="tm-export-color-menu" role="listbox" hidden>' + colorMenuHtml + '</div></div>' +
                        '<button type="button" class="tm-export-omit-btn" title="Omitir columna" aria-label="Omitir columna">&times;</button>' +
                        '</div></div>' +
                        secondaryRow +
                    '</div>';
                container.appendChild(item);
            });
        }

        function tmExportDraftStorageKey(exportUrl) {
            return 'tm_export_draft_v1:' + (exportUrl || '');
        }

        function applyColumnDraftVisuals(columnsEl, cfgColumns) {
            if (!columnsEl || !cfgColumns || !cfgColumns.length) { return; }
            cfgColumns.forEach(function (sc) {
                if (!sc || !sc.key) { return; }
                var item = null;
                columnsEl.querySelectorAll('.tm-export-personalize-col').forEach(function (el) {
                    if (el.dataset.key === sc.key) { item = el; }
                });
                if (!item) { return; }
                var tr = item.querySelector('.tm-export-color-trigger');
                if (tr && sc.color) {
                    tr.setAttribute('data-color', sc.color);
                    var sw = tr.querySelector('.tm-export-color-swatch');
                    if (sw) { sw.style.backgroundColor = sc.color; }
                    item.querySelectorAll('.tm-export-color-option').forEach(function (opt) {
                        opt.classList.toggle('is-active', (opt.getAttribute('data-color') || '') === sc.color);
                    });
                }
                var gSel = item.querySelector('.tm-export-col-group-select');
                if (gSel && sc.group) { gSel.value = sc.group; }
                var labelInput = item.querySelector('.tm-export-col-label-input');
                if (labelInput && sc.label != null) { labelInput.value = String(sc.label); }
                var iw = item.querySelector('.tm-export-image-width');
                var ih = item.querySelector('.tm-export-image-height');
                if (iw && sc.image_width != null) { iw.value = String(sc.image_width); }
                if (ih && sc.image_height != null) { ih.value = String(sc.image_height); }
                var winp = item.querySelector('.tm-export-col-width-input');
                if (winp && sc.max_width_chars != null) {
                    var n = parseInt(sc.max_width_chars, 10);
                    if (!Number.isNaN(n)) { winp.value = String(Math.max(2, Math.min(60, n))); }
                }
            });
        }

        function buildCountTableColorList(container, countByFieldsEl, previewEntries, externalPreset) {
            if (!container) { return; }
            var savedColors = {};
            if (externalPreset && typeof externalPreset === 'object') {
                Object.keys(externalPreset).forEach(function (k) {
                    var o = externalPreset[k];
                    savedColors[k] = o && typeof o === 'object' ? Object.assign({}, o) : {};
                });
            } else {
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
                    showSR: true,
                    row2Values: {}
                };
                var srCheck = row.querySelector('.tm-export-count-sr-check');
                if (srCheck) { savedColors[k].showSR = !!srCheck.checked; }
                row.querySelectorAll('.tm-export-count-table-value-color').forEach(function (vrow) {
                    var v = vrow.getAttribute('data-value');
                    var vt = vrow.querySelector('.tm-export-color-trigger');
                    if (v && vt) { savedColors[k].row2Values[v] = vt.getAttribute('data-color') || 'var(--clr-secondary)'; }
                });
            });
            }
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
                var showSR = key === '_total' ? false : !(colors && colors.showSR === false);
                var row2Values = (colors && colors.row2Values) ? colors.row2Values : {};
                var srControl = key === '_total' ? '' :
                    '<label class="tm-export-count-pct-item-check" title="Incluir No aplica para este campo" style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:0.75rem;color:var(--clr-text-main);">' +
                    '<input type="checkbox" class="tm-export-count-sr-check"' + (showSR ? ' checked' : '') + '> No aplica' +
                    '</label>';
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
                    srControl +
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
            var modal = document.getElementById('tmExportPersonalizeModal');
            var container = modal ? modal.querySelector('#tmExportPersonalizeColumns') : document.getElementById('tmExportPersonalizeColumns');
            if (!container) {
                return { title: '', titleAlign: 'center', titleUppercase: false, headersUppercase: false, columns: [], sampleRow: {}, countTableColors: {}, countTableCellWidth: 12, cellFontPx: 12, titleFontPx: 18, microrregionSort: 'asc' };
            }
            const titleEl = modal ? modal.querySelector('#tmExportPersonalizeTitle') : document.getElementById('tmExportPersonalizeTitle');
            const titleUppercaseEl = modal ? modal.querySelector('#tmExportTitleUppercase') : document.getElementById('tmExportTitleUppercase');
            const headersUppercaseEl = modal ? modal.querySelector('#tmExportHeadersUppercase') : document.getElementById('tmExportHeadersUppercase');
            const cellFontEl = modal ? modal.querySelector('#tmExportCellFontSize') : document.getElementById('tmExportCellFontSize');
            const titleFontEl = modal ? modal.querySelector('#tmExportTitleFontSize') : document.getElementById('tmExportTitleFontSize');
            const headerFontEl = modal ? modal.querySelector('#tmExportHeaderFontSize') : document.getElementById('tmExportHeaderFontSize');
            const microrregionSortEl = modal ? modal.querySelector('#tmExportMicrorregionSort') : document.getElementById('tmExportMicrorregionSort');
            const alignBtn = modal ? modal.querySelector('.tm-export-title-align .tm-export-align-btn.is-active') : null;
            const titleAlign = (alignBtn && alignBtn.getAttribute('data-title-align')) || 'center';
            const cellFontPx = cellFontEl && cellFontEl.value ? Math.max(9, Math.min(24, parseInt(cellFontEl.value, 10) || 12)) : 12;
            const titleFontPx = titleFontEl && titleFontEl.value ? Math.max(10, Math.min(36, parseInt(titleFontEl.value, 10) || 18)) : 18;
            const headerFontPx = headerFontEl && headerFontEl.value ? Math.max(9, Math.min(28, parseInt(headerFontEl.value, 10) || 12)) : 12;
            const items = Array.from(container.children).filter(function (el) {
                return el.classList && el.classList.contains('tm-export-personalize-col');
            });
            const columns = items.map(function (item) {
                const key = item.dataset.key || '';
                const colorTrigger = item.querySelector('.tm-export-color-trigger');
                const color = colorTrigger ? (colorTrigger.getAttribute('data-color') || 'var(--clr-primary)') : 'var(--clr-primary)';
                const groupSel = item.querySelector('.tm-export-col-group-select');
                const group = groupSel ? groupSel.value : '';
                const labelInput = item.querySelector('.tm-export-col-label-input');
                const label = labelInput && String(labelInput.value || '').trim() !== ''
                    ? String(labelInput.value).trim()
                    : key;
                let imageWidth = 120, imageHeight = 80;
                const w = item.querySelector('.tm-export-image-width');
                const h = item.querySelector('.tm-export-image-height');
                if (w && h) {
                    imageWidth = parseInt(w.value, 10) || 120;
                    imageHeight = parseInt(h.value, 10) || 80;
                }
                return { key, label, color, imageWidth, imageHeight, group };
            });
            var countTableColors = {};
            var countColorList = modal ? modal.querySelector('#tmExportCountTableColorList') : document.getElementById('tmExportCountTableColorList');
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
                    var srCheck = row.querySelector('.tm-export-count-sr-check');
                    if (srCheck) { obj.showSR = !!srCheck.checked; }
                    countTableColors[k] = obj;
                });
            }
            var countTableCellWidthEl = modal ? modal.querySelector('#tmExportCountTableCellWidth') : document.getElementById('tmExportCountTableCellWidth');
            var countTableCellWidth = (countTableCellWidthEl && countTableCellWidthEl.value) ? (parseInt(countTableCellWidthEl.value, 10) || 12) : 12;
            var groups = (personalizeModal && personalizeModal._exportGroups) || [];
            return {
                title: titleEl ? titleEl.value : '',
                titleAlign: titleAlign,
                titleUppercase: !!(titleUppercaseEl && titleUppercaseEl.checked),
                headersUppercase: !!(headersUppercaseEl && headersUppercaseEl.checked),
                columns: columns,
                countTableColors: countTableColors,
                countTableCellWidth: countTableCellWidth,
                cellFontPx: cellFontPx,
                titleFontPx: titleFontPx,
                headerFontPx: headerFontPx,
                groups: groups,
                microrregionSort: (microrregionSortEl && microrregionSortEl.value === 'desc') ? 'desc' : 'asc'
            };
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

        function getPreviewMicrorregionNumber(entry, meta) {
            if (!entry || !meta) { return Number.MAX_SAFE_INTEGER; }
            var info = meta[entry.microrregion_id] || {};
            var raw = info.number != null ? String(info.number) : '';
            var parsed = parseInt(raw, 10);

            return Number.isNaN(parsed) ? Number.MAX_SAFE_INTEGER : parsed;
        }

        function sortPreviewEntriesByMicrorregion(entries, meta, direction) {
            if (!Array.isArray(entries)) { return []; }
            var dir = direction === 'desc' ? -1 : 1;

            return entries.slice().sort(function (left, right) {
                var leftNumber = getPreviewMicrorregionNumber(left, meta);
                var rightNumber = getPreviewMicrorregionNumber(right, meta);

                if (leftNumber !== rightNumber) {
                    return (leftNumber - rightNumber) * dir;
                }

                var leftKey = left && left.microrregion_id != null ? String(left.microrregion_id) : '';
                var rightKey = right && right.microrregion_id != null ? String(right.microrregion_id) : '';

                return leftKey.localeCompare(rightKey) * dir;
            });
        }

        function formatPreviewCellValue(val) {
            if (val === null || val === undefined) { return ''; }
            if (typeof val === 'boolean') { return val ? 'Sí' : 'No'; }
            if (Array.isArray(val)) { return val.map(function (v) { return typeof v === 'object' ? JSON.stringify(v) : String(v); }).join(', '); }
            return String(val);
        }

        function normalizeExportHeadingText(text, uppercase) {
            var value = text == null ? '' : String(text);
            return uppercase ? value.toLocaleUpperCase() : value;
        }

        function buildPersonalizePreview(columns, previewEl, sampleRow, previewEntries, microrregionMeta) {
            if (!previewEl) { return; }
            var modal = previewEl.closest && previewEl.closest('.tm-modal');
            var entries = previewEntries || (modal && modal._previewEntries);
            var meta = microrregionMeta || (modal && modal._previewMicrorregionMeta) || {};
            const savedRow = sampleRow || (entries && entries.length ? null : readSampleRowFromPreview(previewEl));
            const state = getPersonalizeState();
            entries = sortPreviewEntriesByMicrorregion(entries, meta, state.microrregionSort);
            const colorMap = {};
            state.columns.forEach(function (c) { colorMap[c.key] = c.color; });
            const cellFontPx = state.cellFontPx || 12;
            const titleFontPx = state.titleFontPx || 18;
            const titleAlign = state.titleAlign || 'center';
            const titleUppercase = !!state.titleUppercase;
            const headersUppercase = !!state.headersUppercase;
            const titleStyle = 'text-align:' + (titleAlign === 'left' ? 'left' : titleAlign === 'right' ? 'right' : 'center');
            var countTableHtml = '';
            var root = modal || document;
            var includeCountEl = root.querySelector ? root.querySelector('#tmExportIncludeCountTable') : document.getElementById('tmExportIncludeCountTable');
            var includePercentagesEl = root.querySelector ? root.querySelector('#tmExportIncludePercentages') : document.getElementById('tmExportIncludePercentages');
            var countByFieldsEl = root.querySelector ? root.querySelector('#tmExportCountByFields') : document.getElementById('tmExportCountByFields');
            if (includeCountEl && includeCountEl.checked && countByFieldsEl) {
                var totalCount = Array.isArray(entries) ? entries.length : 0;
                var currentLabelsByKey = {};
                columns.forEach(function (col) {
                    if (col && col.key) { currentLabelsByKey[col.key] = col.label || col.key; }
                });
                var groups = [{ label: normalizeExportHeadingText('Total de registros', headersUppercase), values: [{ label: '', count: totalCount }] }];
                countByFieldsEl.querySelectorAll('input[type="checkbox"]:checked').forEach(function (cb) {
                    var key = cb.getAttribute('data-count-key') || cb.value;
                    if (!key) { return; }
                    var labelEl = cb.closest('label');
                    var fieldLabel = currentLabelsByKey[key]
                        || ((labelEl && labelEl.textContent) ? labelEl.textContent.replace(/^\s+|\s+$/g, '') : key);
                    var groupCfg = (state.countTableColors && state.countTableColors[key]) ? state.countTableColors[key] : {};
                    var includeSR = key === '_total' ? false : !(groupCfg && groupCfg.showSR === false);
                    var byVal = {};
                    var labelByLower = {};
                    var sinRespuesta = 0;
                    if (Array.isArray(entries)) {
                        entries.forEach(function (e) {
                            var v = (e.data && e.data[key]) !== undefined ? e.data[key] : null;

                            if (Array.isArray(v)) {
                                var hasAnyArrayValue = false;
                                v.forEach(function (item) {
                                    var itemLabel = '';
                                    if (typeof item === 'boolean') {
                                        itemLabel = item ? 'Sí' : 'No';
                                    } else if (item != null) {
                                        itemLabel = String(item).trim();
                                    }
                                    if (itemLabel !== '') {
                                        hasAnyArrayValue = true;
                                        var itemLower = itemLabel.toLowerCase();
                                        byVal[itemLower] = (byVal[itemLower] || 0) + 1;
                                        if (!labelByLower[itemLower]) { labelByLower[itemLower] = itemLabel; }
                                    }
                                });
                                if (!hasAnyArrayValue) {
                                    sinRespuesta++;
                                }
                                return;
                            }

                            if (v && typeof v === 'object' && Object.prototype.hasOwnProperty.call(v, 'primary')) {
                                v = v.primary;
                            }

                            var k = '';
                            if (typeof v === 'boolean') {
                                k = v ? 'Sí' : 'No';
                            } else if (v != null) {
                                k = String(v).trim();
                            }

                            if (k === '') {
                                sinRespuesta++;
                                return;
                            }

                            var lower = k.toLowerCase();
                            byVal[lower] = (byVal[lower] || 0) + 1;
                            if (!labelByLower[lower]) { labelByLower[lower] = k; }
                        });
                    }
                    var values = [];
                    Object.keys(byVal).sort().forEach(function (lower) {
                        values.push({ label: normalizeExportHeadingText(labelByLower[lower] || lower, headersUppercase), count: byVal[lower] });
                    });
                    if (includeSR && sinRespuesta > 0) {
                        values.push({ label: normalizeExportHeadingText('No aplica', headersUppercase), count: sinRespuesta });
                    }
                    if (values.length) { groups.push({ label: normalizeExportHeadingText(fieldLabel, headersUppercase), values: values }); }
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
                                countTableHtml += '<th class="tm-export-preview-count-group-header" ' + rs + ' colspan="' + cs + '" style="background-color:' + escapeHtml(bg) + '">' + escapeHtml(normalizeExportHeadingText(g.label, headersUppercase)) + '</th>';
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
                                countTableHtml += '<th class="tm-export-preview-count-value-header" style="background-color:' + escapeHtml(bg) + ';width:' + cellW + 'ch;">' + escapeHtml(normalizeExportHeadingText('Cantidad', headersUppercase)) + '</th>';
                                countTableHtml += '<th class="tm-export-preview-count-value-header" style="background-color:' + escapeHtml(bg) + ';width:' + pctCellW + 'ch;font-size:0.75rem;">%</th>';
                            } else {
                                countTableHtml += '<th class="tm-export-preview-count-value-header" style="background-color:' + escapeHtml(bg) + ';width:' + cellW + 'ch;min-width:' + cellW + 'ch;max-width:' + cellW + 'ch;overflow:hidden;text-overflow:ellipsis;">' + escapeHtml(normalizeExportHeadingText(subLabel, headersUppercase)) + '</th>';
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
            var now = new Date();
            var dateStrFormatted = now.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' +
                                   now.getHours().toString().padStart(2, '0') + ':' +
                                   now.getMinutes().toString().padStart(2, '0');
            const dateStr = 'Fecha y hora de corte: ' + dateStrFormatted;

            let html = '';

            // Área de Título y Fecha (superior)
            html += '<div class="tm-export-preview-header" style="width:100%;margin-bottom:15px;border-bottom:1px solid #eee;padding-bottom:10px;">';
            html += '<table style="width:100%;border-collapse:collapse;table-layout:fixed;">';
            if (TM_EXPORT_PREVIEW_LOGO_URL) {
                html += '<tr>';
                html += '<td style="width:100%;vertical-align:bottom;padding-bottom:2px;">'
                    + '<img src="' + escapeHtml(TM_EXPORT_PREVIEW_LOGO_URL) + '" alt="Gobierno de Puebla" style="height:52px;width:auto;display:block;">'
                    + '</td>';
                html += '</tr>';
                html += '<tr><td style="padding-top:6px;">';
                html += '<div class="tm-export-preview-title" style="' + titleStyle + ';font-size:' + titleFontPx + 'px;font-weight:bold;">' + escapeHtml(normalizeExportHeadingText(state.title || 'Título', titleUppercase)) + '</div>';
                html += '<div class="tm-export-preview-subtitle tm-export-preview-fecha-corte" style="text-align:right;font-size:0.85rem;margin-top:2px;">' + escapeHtml(dateStr) + '</div>';
                html += '</td></tr>';
            } else {
                html += '<tr><td>';
                html += '<div class="tm-export-preview-title" style="' + titleStyle + ';font-size:' + titleFontPx + 'px;font-weight:bold;">' + escapeHtml(normalizeExportHeadingText(state.title || 'Título', titleUppercase)) + '</div>';
                html += '<div class="tm-export-preview-subtitle tm-export-preview-fecha-corte" style="text-align:right;font-size:0.85rem;margin-top:2px;">' + escapeHtml(dateStr) + '</div>';
                html += '</td></tr>';
            }
            html += '</table>';
            html += '</div>';

            // Tabla de Conteo (Resumen)
            html += countTableHtml;

            // Tabla de Datos (Desglose)
            html += '<table class="tm-export-preview-table" style="table-layout:fixed;width:auto;border-collapse:collapse;margin-top:10px;">';

            // Fila de Desglose (dentro de la tabla para alineación)
            html += '<tr class="tm-export-preview-row">';
            html += '<td class="tm-export-preview-cell tm-export-preview-desglose-label" style="font-weight:600;padding:12px 0 6px 0;border-left:0;border-right:0;border-bottom:0;" colspan="' + totalColSpan + '">' + escapeHtml(normalizeExportHeadingText('Desglose', headersUppercase)) + '</td>';
            html += '</tr>';

            // Encabezados (con Grupos si aplica)
            var groupSpans = [];
            columns.forEach(function (col) {
                var g = col.group || '';
                if (groupSpans.length > 0 && groupSpans[groupSpans.length - 1].label === g) {
                    groupSpans[groupSpans.length - 1].span++;
                } else {
                    groupSpans.push({ label: g, span: 1 });
                }
            });

            var hasAnyGroup = groupSpans.some(function (gs) { return gs.label !== ''; });
            if (hasAnyGroup) {
                html += '<tr class="tm-export-preview-row tm-export-preview-group-header">';
                groupSpans.forEach(function (gs) {
                    var style = gs.label ? 'background-color:#64748b; color:white; border:1px solid #475569; font-weight:bold; border-bottom:none;' : 'border:none;';
                    html += '<th class="tm-export-preview-cell" colspan="' + gs.span + '" style="' + style + '">' + (gs.label ? escapeHtml(normalizeExportHeadingText(gs.label, headersUppercase)) : '') + '</th>';
                });
                html += '</tr>';
            }

            html += '<tr class="tm-export-preview-row tm-export-preview-header">';
            columns.forEach(function (col) {
                const color = colorMap[col.key] || '#861e34';
                if (col.is_image) {
                    const c = state.columns.find(function (x) { return x.key === col.key; }) || {};
                    const w = (c.imageWidth || 120) + 'px';
                    const h = (c.imageHeight || 80) + 'px';
                    html += '<th class="tm-export-preview-cell tm-export-preview-header-cell tm-export-preview-image-cell" style="background-color:' + escapeHtml(color) + ';width:' + w + ';height:' + h + ';min-width:' + w + ';min-height:' + h + '"><span class="tm-export-preview-image-placeholder">' + escapeHtml(normalizeExportHeadingText('Imagen', headersUppercase)) + '</span></th>';
                } else {
                    const ch = Math.min(col.max_width_chars || 24, 60);
                    html += '<th class="tm-export-preview-cell tm-export-preview-header-cell" style="background-color:' + escapeHtml(color) + ';width:' + ch + 'ch">' + escapeHtml(normalizeExportHeadingText(col.label, headersUppercase)) + '</th>';
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
                            html += '<td class="tm-export-preview-cell tm-export-preview-data-cell tm-export-preview-image-cell" style="width:' + w + ';height:' + h + ';min-width:' + w + ';min-height:' + h + ';background:#f0f0f0"><span class="tm-export-preview-image-placeholder">-</span></td>';
                        } else {
                            const ch = Math.min(col.max_width_chars || 24, 60);
                            var val = '';
                            if (col.key === 'item') { val = String(itemNum++); } else if (col.key === 'microrregion') { val = mrLabel; } else { val = formatPreviewCellValue(entry.data && entry.data[col.key]); }
                            html += '<td class="tm-export-preview-cell tm-export-preview-data-cell" style="width:' + ch + 'ch;background:' + escapeHtml(cellColor) + ';font-size:' + cellFontPx + 'px;">' + escapeHtml(val) + '</td>';
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
                        html += '<td class="tm-export-preview-cell tm-export-preview-data-cell tm-export-preview-image-cell" data-key="' + escapeHtml(col.key) + '" style="width:' + w + ';height:' + h + ';min-width:' + w + ';min-height:' + h + ';background:#f0f0f0"><span class="tm-export-preview-image-placeholder">-</span></td>';
                    } else {
                        const ch = Math.min(col.max_width_chars || 24, 60);
                        const val = (savedRow && savedRow[col.key] !== undefined) ? escapeHtml(savedRow[col.key]) : '';
                        html += '<td class="tm-export-preview-cell tm-export-preview-data-cell" data-key="' + escapeHtml(col.key) + '" contenteditable="true" style="width:' + ch + 'ch;background:' + escapeHtml(cellColor) + ';font-size:' + cellFontPx + 'px;" data-placeholder="Ejemplo">' + val + '</td>';
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
            Array.from(container.children).forEach(function (item) {
                if (!item.classList || !item.classList.contains('tm-export-personalize-col')) { return; }
                const key = item.dataset.key || '';
                const base = map[key];
                if (!base) { return; }
                const col = Object.assign({}, base);
                const labelInput = item.querySelector('.tm-export-col-label-input');
                if (labelInput) {
                    var customLabel = String(labelInput.value || '').trim();
                    col.label = customLabel !== '' ? customLabel : (base.label || key);
                }

                const groupSel = item.querySelector('.tm-export-col-group-select');
                if (groupSel) { col.group = groupSel.value; }

                if (!col.is_image) {
                    const widthInput = item.querySelector('.tm-export-col-width-input');
                    if (widthInput) {
                        var raw = parseInt(widthInput.value, 10);
                        if (!Number.isNaN(raw)) {
                            col.max_width_chars = Math.max(2, Math.min(raw, 60));
                        }
                    }
                }
                const colorTrigger = item.querySelector('.tm-export-color-trigger');
                if (colorTrigger) { col.color = colorTrigger.getAttribute('data-color') || 'var(--clr-primary)'; }

                ordered.push(col);
            });
            return ordered.length ? ordered : columns.slice();
        }

        function updateRestoreVisibility(columnsEl, originalColumns, restoreWrap) {
            if (!restoreWrap) { return; }
            const current = Array.from(columnsEl.children).filter(function (el) {
                return el.classList && el.classList.contains('tm-export-personalize-col');
            }).length;
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
            const titleUppercaseEl = document.getElementById('tmExportTitleUppercase');
            const headersUppercaseEl = document.getElementById('tmExportHeadersUppercase');
            const cellFontEl = document.getElementById('tmExportCellFontSize');
            const titleFontEl = document.getElementById('tmExportTitleFontSize');
            const applyExcelSingleBtn = document.getElementById('tmExportApplyExcelSingle');
            const applyExcelMrBtn = document.getElementById('tmExportApplyExcelMr');
            const applyWordTableBtn = document.getElementById('tmExportApplyWordTable');
            const applyPdfTableBtn = document.getElementById('tmExportApplyPdfTable');
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
                    var draftCfg = null;
                    try {
                        var rawDraft = localStorage.getItem(tmExportDraftStorageKey(exportUrl));
                        if (rawDraft) {
                            var parsedDraft = JSON.parse(rawDraft);
                            if (parsedDraft && parsedDraft.v === 1 && parsedDraft.cfg && parsedDraft.cfg.columns && parsedDraft.cfg.columns.length) {
                                draftCfg = parsedDraft.cfg;
                            }
                        }
                    } catch (eDraft) {}

                    if (personalizeModal) {
                        personalizeModal._exportGroups = [];
                    }

                    if (draftCfg) {
                        if (titleEl) { titleEl.value = draftCfg.title != null ? String(draftCfg.title) : (data.title || ''); }
                        if (titleUppercaseEl) { titleUppercaseEl.checked = !!draftCfg.title_uppercase; }
                        if (headersUppercaseEl) { headersUppercaseEl.checked = !!draftCfg.headers_uppercase; }
                        var mrSortEl = document.getElementById('tmExportMicrorregionSort');
                        if (mrSortEl) { mrSortEl.value = draftCfg.microrregion_sort === 'desc' ? 'desc' : (data.microrregion_sort || 'asc'); }
                        personalizeModal.querySelectorAll('.tm-export-title-align .tm-export-align-btn').forEach(function (b) {
                            b.classList.toggle('is-active', (b.getAttribute('data-title-align') || '') === (draftCfg.title_align || 'center'));
                        });
                        var dOrient = draftCfg.orientation || 'portrait';
                        personalizeModal.querySelectorAll('.tm-export-orient-btn').forEach(function (b) {
                            b.classList.toggle('is-active', (b.getAttribute('data-orientation') || '') === dOrient);
                        });
                        var previewPage = document.getElementById('tmExportPreviewPage');
                        if (previewPage) { previewPage.classList.toggle('is-landscape', dOrient === 'landscape'); }
                        if (includeCountTableEl) {
                            includeCountTableEl.checked = !!draftCfg.include_count_table;
                            if (countByWrapEl) { countByWrapEl.hidden = !includeCountTableEl.checked; }
                        }
                        if (countByFieldsEl && Array.isArray(draftCfg.count_by_fields)) {
                            countByFieldsEl.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                                var ck = cb.getAttribute('data-count-key') || cb.value;
                                cb.checked = draftCfg.count_by_fields.indexOf(ck) !== -1;
                            });
                        }
                        if (personalizeModal) {
                            personalizeModal._exportGroups = Array.isArray(draftCfg.groups) ? draftCfg.groups.slice() : [];
                        }
                        renderExportGroups();
                        var cwEl = document.getElementById('tmExportCountTableCellWidth');
                        if (cwEl && draftCfg.count_table_cell_width != null) {
                            var cwn = parseInt(draftCfg.count_table_cell_width, 10);
                            if (!Number.isNaN(cwn)) { cwEl.value = String(Math.max(6, Math.min(40, cwn))); }
                        }
                        if (cellFontEl && draftCfg.cell_font_size_px != null) {
                            var cfn = parseInt(draftCfg.cell_font_size_px, 10);
                            if (!Number.isNaN(cfn)) { cellFontEl.value = String(Math.max(9, Math.min(24, cfn))); }
                        }
                        if (titleFontEl && draftCfg.title_font_size_px != null) {
                            var tfn = parseInt(draftCfg.title_font_size_px, 10);
                            if (!Number.isNaN(tfn)) { titleFontEl.value = String(Math.max(10, Math.min(36, tfn))); }
                        }
                        var orderedMerged = [];
                        draftCfg.columns.forEach(function (sc) {
                            var b = columns.find(function (c) { return c.key === sc.key; });
                            if (b) {
                                orderedMerged.push(Object.assign({}, b, {
                                    label: (sc.label != null && String(sc.label).trim() !== '') ? String(sc.label) : (b.label || b.key),
                                    max_width_chars: sc.max_width_chars != null ? sc.max_width_chars : b.max_width_chars,
                                    image_height: sc.image_height != null ? sc.image_height : b.image_height,
                                    image_width: sc.image_width != null ? sc.image_width : b.image_width
                                }));
                            }
                        });
                        if (orderedMerged.length) {
                            buildPersonalizeColumnsList(orderedMerged, columnsEl);
                            applyColumnDraftVisuals(columnsEl, draftCfg.columns);
                        } else {
                            if (titleEl) { titleEl.value = data.title || ''; }
                            buildPersonalizeColumnsList(columns, columnsEl);
                        }
                    } else {
                        if (titleEl) { titleEl.value = data.title || ''; }
                        if (titleUppercaseEl) { titleUppercaseEl.checked = false; }
                        if (headersUppercaseEl) { headersUppercaseEl.checked = false; }
                        var mrSortDefaultEl = document.getElementById('tmExportMicrorregionSort');
                        if (mrSortDefaultEl) { mrSortDefaultEl.value = data.microrregion_sort || 'asc'; }
                        buildPersonalizeColumnsList(columns, columnsEl);
                    }
                    buildCountTableColorList(countTableColorListEl, countByFieldsEl, personalizeModal._previewEntries, draftCfg ? draftCfg.count_table_colors : null);
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
                            } else if (e.target.closest('.tm-export-count-pct-check') || e.target.closest('.tm-export-count-sr-check')) {
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
                    if (titleUppercaseEl) {
                        titleUppercaseEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    if (headersUppercaseEl) {
                        headersUppercaseEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    if (cellFontEl) {
                        cellFontEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                        cellFontEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    if (titleFontEl) {
                        titleFontEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                        titleFontEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    var microrregionSortEl = document.getElementById('tmExportMicrorregionSort');
                    if (microrregionSortEl) {
                        microrregionSortEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }

                    if (restoreBtn && restoreWrap) {
                        restoreBtn.onclick = function () {
                            buildPersonalizeColumnsList(columns, columnsEl);
                            buildPersonalizePreview(columns, previewEl);
                            restoreWrap.hidden = true;
                            attachPersonalizeColumnListeners(columnsEl, columns, previewEl, restoreWrap);
                        };
                    }

                    function collectPersonalizeCfgObject() {
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
                        return {
                            title: state.title || '',
                            title_align: state.titleAlign || 'center',
                            title_uppercase: !!state.titleUppercase,
                            headers_uppercase: !!state.headersUppercase,
                            cell_font_size_px: state.cellFontPx || 12,
                            title_font_size_px: state.titleFontPx || 18,
                            orientation: orientation,
                            table_align: 'left',
                            include_count_table: includeCountTable,
                            count_by_fields: countByFields,
                            count_table_colors: state.countTableColors || {},
                            count_table_cell_width: state.countTableCellWidth || 12,
                            microrregion_sort: state.microrregionSort || 'asc',
                            groups: state.groups || [],
                            columns: orderedCols.map(function (col) {
                                const colState = state.columns.find(function (c) { return c.key === col.key; }) || {};
                                return {
                                    key: col.key,
                                    label: (col.label != null && String(col.label).trim() !== '') ? String(col.label) : col.key,
                                    color: colState.color || 'var(--clr-primary)',
                                    image_width: colState.imageWidth || null,
                                    image_height: colState.imageHeight || null,
                                    max_width_chars: col.max_width_chars || null,
                                    group: col.group || ''
                                };
                            })
                        };
                    }

                    function applyExport(format, mode) {
                        const cfg = collectPersonalizeCfgObject();
                        const fmt = format || 'excel';
                        const exportMode = (fmt === 'excel') ? (mode || 'single') : 'single';
                        closePersonalizeModal();
                        submitTemporaryModuleExportPost(exportUrl, fmt, exportMode, cfg);
                    }

                    var saveCfgBtn = document.getElementById('tmExportSaveConfig');
                    if (saveCfgBtn && exportUrl) {
                        saveCfgBtn.onclick = function () {
                            var cfg = collectPersonalizeCfgObject();
                            var swalChoice = personalizeModal._swalChoice || 'single';
                            try {
                                localStorage.setItem(tmExportDraftStorageKey(exportUrl), JSON.stringify({ v: 1, swal_choice: swalChoice, cfg: cfg, savedAt: Date.now() }));
                            } catch (eSave) {}
                            closePersonalizeModal();
                            var ref = personalizeModal._exportButtonRef;
                            if (ref) { openTemporaryModuleExportTypeDialog(ref); }
                        };
                    }

                    if (applyExcelSingleBtn && exportUrl) {
                        applyExcelSingleBtn.onclick = function () { applyExport('excel', 'single'); };
                    }
                    if (applyExcelMrBtn && exportUrl) {
                        applyExcelMrBtn.onclick = function () { applyExport('excel', 'mr'); };
                    }
                    if (applyWordTableBtn && exportUrl) {
                        applyWordTableBtn.onclick = function () { applyExport('word', 'single'); };
                    }
                    if (applyPdfTableBtn && exportUrl) {
                        applyPdfTableBtn.onclick = function () { applyExport('pdf', 'single'); };
                    }

                    // Listeners para Grupos
                    var addGroupBtn = document.getElementById('tmExportAddGroupBtn');
                    if (addGroupBtn) {
                        addGroupBtn.onclick = function() {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    title: 'Nuevo Grupo',
                                    input: 'text',
                                    inputLabel: 'Nombre del grupo de columnas',
                                    showCancelButton: true,
                                    confirmButtonText: 'Crear',
                                    cancelButtonText: 'Cancelar'
                                }).then(function(res) {
                                    if (res.isConfirmed && res.value) {
                                        var name = res.value.trim();
                                        if (!personalizeModal._exportGroups) {
                                            personalizeModal._exportGroups = [];
                                        }
                                        if (name && personalizeModal._exportGroups.indexOf(name) === -1) {
                                            personalizeModal._exportGroups.push(name);
                                            renderExportGroups();
                                            buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                                        }
                                    }
                                });
                            } else {
                                var name = prompt('Nombre del grupo:');
                                if (name && (name = name.trim()) !== '') {
                                    if (!personalizeModal._exportGroups) {
                                        personalizeModal._exportGroups = [];
                                    }
                                    if (personalizeModal._exportGroups.indexOf(name) === -1) {
                                        personalizeModal._exportGroups.push(name);
                                        renderExportGroups();
                                        buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                                    }
                                }
                            }
                        };
                    }
                    if (countTableColorListEl) {
                        // Usar event delegation para los remove de grupos
                        var groupsListEl = document.getElementById('tmExportGroupsList');
                        if (groupsListEl) {
                            groupsListEl.onclick = function(e) {
                                var removeBtn = e.target.closest('.tm-export-group-remove');
                                if (removeBtn) {
                                    var gName = removeBtn.getAttribute('data-group');
                                    if (!Array.isArray(personalizeModal._exportGroups)) {
                                        personalizeModal._exportGroups = [];
                                    }
                                    personalizeModal._exportGroups = personalizeModal._exportGroups.filter(function(g) { return g !== gName; });
                                    // Limpiar grupo de las columnas
                                    columnsEl.querySelectorAll('.tm-export-col-group-select').forEach(function(sel) {
                                        if (sel.value === gName) sel.value = '';
                                    });
                                    renderExportGroups();
                                    buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                                }
                            };
                        }
                    }

                })
                .catch(function (err) {
                    console.error(err);
                    if (loadingEl) { loadingEl.textContent = 'No se pudo cargar la estructura.'; }
                })
                .finally(function () {
                    if (loadingEl) { loadingEl.hidden = true; }
                    if (contentEl) { contentEl.hidden = false; }
                });
        }

        function openTemporaryModuleExportTypeDialog(exportBtnRef) {
            const exportUrl = exportBtnRef.getAttribute('data-export-url');
            if (!exportUrl || !templateSwal) { return; }
            var savedChoice = null;
            var choiceAllow = { single: 1, analysis_word: 1 };
            try {
                var dr = localStorage.getItem(tmExportDraftStorageKey(exportUrl));
                if (dr) {
                    var po = JSON.parse(dr);
                    if (po && po.swal_choice && choiceAllow[po.swal_choice]) { savedChoice = po.swal_choice; }
                }
            } catch (e) {}
            if (savedChoice === 'mr') {
                savedChoice = 'single';
            }
            templateSwal.fire({
                title: 'Exportación',
                html: '<div class="tm-swal-export-options tm-swal-export-options--stacked">'
                    + '<label class="tm-swal-export-card">'
                    + '<input type="radio" name="tm-export-choice" value="single" checked class="tm-swal-export-card__input">'
                    + '<span class="tm-swal-export-card__body">'
                    + '<span class="tm-swal-export-card__icon" aria-hidden="true"><i class="fa-solid fa-table"></i></span>'
                    + '<span class="tm-swal-export-card__text">'
                    + '<strong class="tm-swal-export-card__title">Excel, PDF, Word</strong>'
                    + '<small class="tm-swal-export-card__sub">Todos los registros · diferentes formatos.</small>'
                    + '</span></span></label>'
                    + '<label class="tm-swal-export-card">'
                    + '<input type="radio" name="tm-export-choice" value="analysis_word" class="tm-swal-export-card__input">'
                    + '<span class="tm-swal-export-card__body">'
                    + '<span class="tm-swal-export-card__icon" aria-hidden="true"><i class="fa-solid fa-file-word"></i></span>'
                    + '<span class="tm-swal-export-card__text">'
                    + '<strong class="tm-swal-export-card__title">Informe de análisis (Word)</strong>'
                    + '<small class="tm-swal-export-card__sub">Resumen y tablas de análisis (.docx).</small>'
                    + '</span></span></label>'
                    + '<p class="tm-swal-export-advanced"><button type="button" class="tm-btn tm-btn-outline tm-swal-personalize-btn">Personalizar columnas y diseño</button></p>'
                    + '</div>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Exportar',
                cancelButtonText: 'Cancelar',
                didOpen: function () {
                    if (savedChoice) {
                        var inp = document.querySelector('input[name="tm-export-choice"][value="' + savedChoice + '"]');
                        if (inp) { inp.checked = true; }
                    }
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
                            if (personalizeModal) {
                                personalizeModal._exportButtonRef = exportBtnRef;
                                personalizeModal._swalChoice = val;
                            }
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
                const separator = exportUrl.indexOf('?') === -1 ? '?' : '&';
                submitTemporaryModuleExportPost(exportUrl, 'excel', 'single', { microrregion_sort: 'asc' });
            });
        }

        exportButtons.forEach(function (exportButton) {
            exportButton.addEventListener('click', function () {
                const exportUrl = exportButton.getAttribute('data-export-url');
                if (!exportUrl) { return; }
                if (!templateSwal) {
                    submitTemporaryModuleExportPost(exportUrl, 'excel', 'single', { microrregion_sort: 'asc' });
                    return;
                }
                openTemporaryModuleExportTypeDialog(exportButton);
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
