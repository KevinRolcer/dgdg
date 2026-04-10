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
                                                                            @php
                                                                                $mediaPaths = in_array($field->type, ['file', 'image'], true)
                                                                                    ? (is_array($cell)
                                                                                        ? array_values(array_filter($cell, fn ($path) => is_string($path) && trim($path) !== ''))
                                                                                        : ((is_string($cell) && trim($cell) !== '') ? [trim($cell)] : []))
                                                                                    : [];
                                                                            @endphp
                                                                            @if (count($mediaPaths) > 0)
                                                                                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                                                                    @foreach ($mediaPaths as $imageIndex => $imagePath)
                                                                                        <button
                                                                                            type="button"
                                                                                            class="tm-thumb-link"
                                                                                            data-open-image-preview
                                                                                            data-image-src="{{ route('temporary-modules.entry-file.preview', ['module' => $module->id, 'entry' => $entry->id, 'fieldKey' => $field->key, 'i' => $imageIndex]) }}"
                                                                                            data-image-title="{{ $field->label }}{{ count($mediaPaths) > 1 ? ' ('.($imageIndex + 1).')' : '' }}"
                                                                                            title="{{ count($mediaPaths) > 1 ? 'Ver imagen '.($imageIndex + 1) : 'Ver imagen' }}"
                                                                                        >
                                                                                            <i class="fa fa-image" aria-hidden="true"></i> {{ count($mediaPaths) > 1 ? 'Imagen '.($imageIndex + 1) : 'Ver imagen' }}
                                                                                        </button>
                                                                                    @endforeach
                                                                                </div>
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
                            <button type="button" role="tab" class="tm-export-side-tab" data-tm-export-side-tab="tm-export-sec-layout">Tabla</button>
                            <button type="button" role="tab" class="tm-export-side-tab" data-tm-export-side-tab="tm-export-sec-columns">Columnas</button>
                            <button type="button" role="tab" class="tm-export-side-tab" data-tm-export-side-tab="tm-export-sec-export">Exportar</button>
                        </nav>
                        <div class="tm-export-side-scroll">
                        <section id="tm-export-sec-title" class="tm-export-side-section">
                            <h4 class="tm-export-side-section__title"><span class="tm-export-side-section__icon" aria-hidden="true"><i class="fa-solid fa-heading"></i></span> Título y tipografía</h4>
                            <p class="tm-export-side-section__lead">Encabezado del documento y tamaños de fuente.</p>
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
                            <label for="tmExportCellFontSize" title="Tamaño de letra en celdas de la tabla de registros">Registros: celdas (px)</label>
                            <input type="number" id="tmExportCellFontSize" class="tm-input tm-input--num-compact" min="9" max="24" value="12">
                        </div>
                        <div class="tm-export-personalize-field">
                            <label for="tmExportHeaderFontSize" title="Tamaño de letra de encabezados de la tabla de registros">Registros: encabezados (px)</label>
                            <input type="number" id="tmExportHeaderFontSize" class="tm-input tm-input--num-compact" min="9" max="28" value="12">
                        </div>
                    </div>
                    <div class="tm-export-field-row tm-export-field-row--fonts">
                        <div class="tm-export-personalize-field">
                            <label for="tmExportSumCellFontSize" title="Tamaño de letra en celdas de la tabla de sumatoria">Sumatoria: celdas (px)</label>
                            <input type="number" id="tmExportSumCellFontSize" class="tm-input tm-input--num-compact" min="9" max="24" value="12">
                        </div>
                        <div class="tm-export-personalize-field">
                            <label for="tmExportSumHeaderFontSize" title="Tamaño de letra de encabezados de la tabla de sumatoria">Sumatoria: encabezados (px)</label>
                            <input type="number" id="tmExportSumHeaderFontSize" class="tm-input tm-input--num-compact" min="9" max="28" value="12">
                        </div>
                    </div>
                    <div class="tm-export-field-row tm-export-field-row--fonts">
                        <div class="tm-export-personalize-field">
                            <label for="tmExportTotalsCellFontSize" title="Tamaño de letra en celdas de la tabla independiente de totales">Totales: celdas (px)</label>
                            <input type="number" id="tmExportTotalsCellFontSize" class="tm-input tm-input--num-compact" min="9" max="24" value="12">
                        </div>
                        <div class="tm-export-personalize-field">
                            <label for="tmExportTotalsHeaderFontSize" title="Tamaño de letra de encabezados de la tabla independiente de totales">Totales: encabezados (px)</label>
                            <input type="number" id="tmExportTotalsHeaderFontSize" class="tm-input tm-input--num-compact" min="9" max="48" value="12">
                        </div>
                    </div>
                    <div class="tm-export-field-row tm-export-field-row--fonts">
                        <div class="tm-export-personalize-field">
                            <label for="tmExportRecordsGroupHeaderFontSize" title="Tamaño de letra de títulos de grupo en la tabla de registros">Registros: grupos (px)</label>
                            <input type="number" id="tmExportRecordsGroupHeaderFontSize" class="tm-input tm-input--num-compact" min="9" max="48" value="12">
                        </div>
                        <div class="tm-export-personalize-field">
                            <label for="tmExportSumGroupHeaderFontSize" title="Tamaño de letra de títulos de grupo en la tabla de sumatoria">Sumatoria: grupos (px)</label>
                            <input type="number" id="tmExportSumGroupHeaderFontSize" class="tm-input tm-input--num-compact" min="9" max="48" value="12">
                        </div>
                        <div class="tm-export-personalize-field">
                            <label for="tmExportTotalsGroupHeaderFontSize" title="Tamaño de letra de títulos de grupo en la tabla de totales">Totales: grupos (px)</label>
                            <input type="number" id="tmExportTotalsGroupHeaderFontSize" class="tm-input tm-input--num-compact" min="9" max="48" value="12">
                        </div>
                    </div>
                    <div class="tm-export-personalize-field-row tm-export-title-row-group" style="margin-top:8px;">
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
                    <div class="tm-export-personalize-field">
                        <label for="tmExportTotalsTableTitle">Título de tabla de totales</label>
                        <input type="text" id="tmExportTotalsTableTitle" class="tm-input" placeholder="Totales">
                    </div>
                    <div class="tm-export-personalize-field">
                        <label for="tmExportSectionLabel">Título de tabla de desglose</label>
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
                    <div class="tm-export-case-toggle-row-sub">
                        <label class="tm-export-case-toggle tm-export-case-toggle--sub" title="Convertir encabezados y títulos de tabla a MAYÚSCULAS">
                            <input type="checkbox" id="tmExportHeadersUppercase" value="1">
                            <span class="tm-export-case-icon">Az</span>
                            <small>Convertir encabezados a MAYÚSCULAS</small>
                        </label>
                    </div>
                    <p class="tm-analysis-hint tm-export-font-hint">Todos los tamaños de letra del documento se configuran aquí.</p>
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
                    <div class="tm-export-personalize-field tm-export-count-table-section">
                        <label class="tm-export-count-table-toggle">
                            <input type="checkbox" id="tmExportIncludeCountTable" value="1">
                            Incluir tabla de conteo general arriba de la tabla de registros
                        </label>
                        <div class="tm-export-count-by-wrap" id="tmExportCountByWrap" hidden>
                            <div class="tm-export-count-table-sizes">
                                <span class="tm-export-label-inline">Tamaños de las celdas:</span>
                                <label class="tm-export-count-cell-width-label">
                                    Ancho (ch):
                                    <input type="number" id="tmExportCountTableCellWidth" class="tm-export-count-cell-width-input tm-input tm-input--large" style="width: 80px;" min="6" max="40" value="12" aria-label="Ancho de columnas de la tabla de conteo en caracteres">
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

                    <div class="tm-export-personalize-field tm-export-sum-table-section">
                        <label class="tm-export-count-table-toggle">
                            <input type="checkbox" id="tmExportIncludeSumTable" value="1">
                            Incluir tabla de sumatoria (agrupada)
                        </label>
                        <div class="tm-export-sum-wrap" id="tmExportSumWrap" hidden>
                            <div class="tm-export-sum-block">
                                <div class="tm-export-groups-wrap__head">
                                    <span class="tm-export-label-inline">Personalización de sumatoria</span>
                                </div>
                                <div class="tm-export-personalize-field tm-export-count-table-align-group">
                                    <span class="tm-export-label-inline">Alineación del título de sumatoria</span>
                                    <div class="tm-export-align-btns" id="tmExportSumTitleAlignGroup" role="group" aria-label="Alineación del título de sumatoria">
                                        <button type="button" class="tm-export-align-btn" data-sum-title-align="left">Izquierda</button>
                                        <button type="button" class="tm-export-align-btn is-active" data-sum-title-align="center">Centro</button>
                                        <button type="button" class="tm-export-align-btn" data-sum-title-align="right">Derecha</button>
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
                            <div class="tm-export-sum-row tm-export-sum-row--sum-config">
                                <label class="tm-export-count-table-toggle">
                                    <input type="checkbox" id="tmExportIncludeTotalsTable" value="1">
                                    Anexar fila de totales como tabla independiente
                                </label>
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

        function openSeedDiscardLog(moduleName, jsonId, registerUrl, searchUrl) {
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
                        searchUrl: searchUrl || '',
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
                    btn.getAttribute('data-register-url') || '',
                    btn.getAttribute('data-search-url') || ''
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

        const closePersonalizeModalImmediate = function () {
            if (!personalizeModal) { return; }
            personalizeModal.classList.remove('is-open');
            personalizeModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };

        const closePersonalizeModal = function (options) {
            if (!personalizeModal) { return; }
            var opts = options || {};
            if (opts.force === true) {
                closePersonalizeModalImmediate();
                return;
            }

            var isOpen = personalizeModal.classList.contains('is-open');
            if (!isOpen) {
                return;
            }

            var collectCfg = personalizeModal._collectPersonalizeCfgObject;
            if (typeof collectCfg !== 'function') {
                closePersonalizeModalImmediate();
                return;
            }

            var currentCfg = null;
            try {
                currentCfg = collectCfg();
            } catch (eCollect) {
                closePersonalizeModalImmediate();
                return;
            }

            var exportUrl = personalizeModal._exportUrl || '';
            var swalChoiceNow = personalizeModal._swalChoice || 'single';
            var savedDraft = null;
            if (exportUrl) {
                try {
                    var savedRaw = localStorage.getItem(tmExportDraftStorageKey(exportUrl));
                    if (savedRaw) {
                        var parsedSaved = JSON.parse(savedRaw);
                        if (parsedSaved && parsedSaved.v === 1 && parsedSaved.cfg && typeof parsedSaved.cfg === 'object') {
                            savedDraft = parsedSaved;
                        }
                    }
                } catch (eSaved) {}
            }

            var hasPendingChanges = false;
            try {
                var currentJson = JSON.stringify(currentCfg || {});
                if (savedDraft) {
                    hasPendingChanges = (currentJson !== JSON.stringify(savedDraft.cfg || {})) || ((savedDraft.swal_choice || 'single') !== swalChoiceNow);
                } else {
                    var openSnapshot = personalizeModal._openCfgSnapshot || '';
                    var openChoice = personalizeModal._openSwalChoice || 'single';
                    hasPendingChanges = (openSnapshot !== '' && currentJson !== openSnapshot) || (openChoice !== swalChoiceNow);
                }
            } catch (eCmp) {
                hasPendingChanges = false;
            }

            if (!hasPendingChanges) {
                closePersonalizeModalImmediate();
                return;
            }

            var persistFn = personalizeModal._persistExportDraft;
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: '¿Salir sin guardar configuración?',
                    text: 'Puedes salir sin guardar, guardar y salir, o cancelar.',
                    showCancelButton: true,
                    showDenyButton: true,
                    confirmButtonText: 'Salir sin guardar',
                    denyButtonText: 'Guardar y salir',
                    cancelButtonText: 'Cancelar'
                }).then(function (res) {
                    if (!res) { return; }
                    if (res.isConfirmed) {
                        closePersonalizeModalImmediate();
                        return;
                    }
                    if (res.isDenied) {
                        var saveOk = false;
                        if (typeof persistFn === 'function') {
                            var saveRes = persistFn(currentCfg);
                            saveOk = !!(saveRes && saveRes.ok);
                        }
                        if (saveOk) {
                            closePersonalizeModalImmediate();
                            Swal.fire({
                                icon: 'success',
                                title: 'Configuración guardada',
                                text: 'Se guardó la configuración y se cerró el editor.',
                                toast: true,
                                position: 'top-end',
                                timer: 2200,
                                timerProgressBar: true,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'No se pudo guardar',
                                text: 'No fue posible guardar la configuración en este navegador.',
                                toast: true,
                                position: 'top-end',
                                timer: 2400,
                                timerProgressBar: true,
                                showConfirmButton: false
                            });
                        }
                    }
                });
                return;
            }

            if (confirm('Hay cambios sin guardar. ¿Salir sin guardar?')) {
                closePersonalizeModalImmediate();
            }
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
                            closePersonalizeModal({ force: true });
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
                    var alignGroup = alignBtn.closest('.tm-export-align-btns');
                    if (!alignGroup) { return; }
                    alignGroup.querySelectorAll('.tm-export-align-btn').forEach(function (b) { b.classList.remove('is-active'); });
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
                    var paperSizeToolbarEl = document.getElementById('tmExportPaperSize');
                    var paperSizeToolbar = (paperSizeToolbarEl && paperSizeToolbarEl.value) ? paperSizeToolbarEl.value : 'letter';
                    applyExportPreviewPageLayout(orientation, paperSizeToolbar);
                }
            });
        }

        function renderExportGroups() {
            var listEl = document.getElementById('tmExportGroupsList');
            var hintEl = document.getElementById('tmExportNoGroupsHint');
            if (!listEl) return;
            var groups = normalizeExportGroups((personalizeModal && personalizeModal._exportGroups) || []);
            if (personalizeModal) {
                personalizeModal._exportGroups = groups;
            }
            listEl.innerHTML = '';
            if (groups.length === 0) {
                if (hintEl) hintEl.hidden = false;
                return;
            }
            if (hintEl) hintEl.hidden = true;
            var groupColorOptions = TEMPLATE_COLORS.map(function (c) {
                return '<option value="' + escapeHtml(c.value) + '">' + escapeHtml(c.name) + '</option>';
            }).join('');
            groups.forEach(function (g, idx) {
                var tag = document.createElement('div');
                tag.className = 'tm-export-group-tag';
                tag.style.cssText = 'background: #f8fafc; border: 1px solid #cbd5e1; padding: 6px 10px; border-radius: 16px; display: flex; align-items: center; gap: 8px; font-size: 0.8rem;';
                tag.innerHTML =
                    '<span class="tm-export-group-color-dot" style="width:12px;height:12px;border-radius:999px;border:1px solid #64748b;background:' + escapeHtml(g.color) + ';"></span>' +
                    '<input type="text" class="tm-input tm-export-group-name-input" data-group-index="' + idx + '" value="' + escapeHtml(g.name) + '" placeholder="Nombre del grupo" style="min-width: 160px; max-width: 240px; font-size: 0.8rem; padding: 4px 8px;">' +
                    '<select class="tm-input tm-export-group-color-select" data-group-index="' + idx + '" style="min-width: 150px; font-size: 0.78rem; padding: 4px 8px;">' + groupColorOptions + '</select>' +
                    '<button type="button" class="tm-export-group-remove" data-group-index="' + idx + '" data-group="' + escapeHtml(g.name) + '" style="border:none; background:none; cursor:pointer; color:#ef4444; font-weight:bold; padding:0 4px;">&times;</button>';
                listEl.appendChild(tag);
                var colorSel = tag.querySelector('.tm-export-group-color-select');
                if (colorSel) {
                    colorSel.value = g.color || TEMPLATE_COLORS[0].value;
                }
            });
            // Re-render columns list to update selects
            var columnsEl = document.getElementById('tmExportPersonalizeColumns');
            if (columnsEl && personalizeModal && personalizeModal._personalizeColumns) {
                buildPersonalizeColumnsList(reorderColumnsList(columnsEl, personalizeModal._personalizeColumns), columnsEl);
            }
            if (personalizeModal && Array.isArray(personalizeModal._countableColumns)) {
                tmExportRenderSumConfigurator(personalizeModal._countableColumns);
                tmExportRenderCalculatedColumnsConfigurator(personalizeModal._countableColumns);
            }
        }

        function buildPersonalizeColumnsList(columns, container) {
            container.innerHTML = '';
            var groups = normalizeExportGroups((personalizeModal && personalizeModal._exportGroups) || []);
            columns.forEach(function (col, index) {
                var item = document.createElement('div');
                item.className = 'tm-export-personalize-col' + (col.is_image ? ' is-image' : '');
                item.setAttribute('role', 'listitem');
                item.dataset.key = col.key;
                item.dataset.index = String(index);
                item.dataset.fillEmptyMode = String(col.fill_empty_mode || col.fillEmptyMode || 'none');
                item.dataset.fillEmptyValue = String(col.fill_empty_value != null ? col.fill_empty_value : (col.fillEmptyValue || ''));
                item.dataset.contentBold = (col.content_bold || col.contentBold) ? '1' : '0';
                item.dataset.headerColor = String(col.color || TEMPLATE_COLORS[0].value);
                item.draggable = false;

                var groupSelect = '<select class="tm-input tm-export-col-group-select" data-key="' + escapeHtml(col.key) + '">' +
                    '<option value="">Sin grupo</option>' +
                    groups.map(function(g) {
                        var gName = String((g && g.name) || '').trim();
                        return '<option value="' + escapeHtml(gName) + '"' + (col.group === gName ? ' selected' : '') + '>' + escapeHtml(gName) + '</option>';
                    }).join('') +
                    '</select>';

                var secondaryRow = '';
                var toolbarHtml =
                    '<button type="button" class="tm-export-omit-btn tm-export-omit-btn--top" title="Omitir columna" aria-label="Omitir columna">&times;</button>';
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
                        '<div class="tm-export-col-inline-tools">' +
                        '<label class="tm-export-col-field tm-export-col-field--wch tm-export-col-field--wch-compact">' +
                        '<span class="tm-export-col-field__lab">Ancho (ch)</span>' +
                        '<input type="number" class="tm-input tm-input--num-compact tm-export-col-width-input" min="2" max="60" value="' + String(approx) + '" data-key="' + escapeHtml(col.key) + '" data-width-hint="' + escapeHtml(String(approx)) + '">' +
                        '</label>' +
                        '</div></div>';
                }
                var currentLabel = (col.label != null && String(col.label).trim() !== '') ? String(col.label) : String(col.key || '');
                item.innerHTML =
                    '<span class="tm-export-col-move-inline">'
                    + '<button type="button" class="tm-btn tm-btn-outline tm-export-col-move-btn" data-move-col-up title="Subir columna">&#8593;</button>'
                    + '<button type="button" class="tm-btn tm-btn-outline tm-export-col-move-btn" data-move-col-down title="Bajar columna">&#8595;</button>'
                    + '</span>' +
                    '<div class="tm-export-col-main">' +
                        '<div class="tm-export-col-row tm-export-col-row--primary">' +
                        '<label class="tm-export-col-field tm-export-col-field--grow">' +
                        '<span class="tm-export-col-field__lab">Columna</span>' +
                        '<textarea class="tm-input tm-export-col-label-input" data-key="' + escapeHtml(col.key) + '" rows="1" placeholder="Nombre en el export">' + escapeHtml(currentLabel) + '</textarea>' +
                        '</label>' +
                        toolbarHtml +
                        '</div>' +
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
                if (sc.color) { item.dataset.headerColor = String(sc.color); }
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
                item.dataset.fillEmptyMode = String(sc.fill_empty_mode || 'none');
                item.dataset.fillEmptyValue = String(sc.fill_empty_value != null ? sc.fill_empty_value : '');
                item.dataset.contentBold = sc.content_bold ? '1' : '0';
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
                    row2Values: {},
                    row2Widths: {}
                };
                var srCheck = row.querySelector('.tm-export-count-sr-check');
                if (srCheck) { savedColors[k].showSR = !!srCheck.checked; }
                row.querySelectorAll('.tm-export-count-table-value-color').forEach(function (vrow) {
                    var v = vrow.getAttribute('data-value');
                    var vt = vrow.querySelector('.tm-export-color-trigger');
                    if (v && vt) { savedColors[k].row2Values[v] = vt.getAttribute('data-color') || 'var(--clr-secondary)'; }
                    var vw = vrow.querySelector('.tm-export-count-width-input');
                    if (v && vw) {
                        var wn = parseInt(vw.value, 10);
                        if (!Number.isNaN(wn)) { savedColors[k].row2Widths[v] = Math.max(6, Math.min(40, wn)); }
                    }
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
                var row2Widths = (colors && colors.row2Widths) ? colors.row2Widths : {};
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
                        var vw = row2Widths[vlabel] || 12;
                        block += '<div class="tm-export-count-table-value-color" data-value="' + escapeHtml(vlabel) + '">' +
                            '<span class="tm-export-value-label">' + escapeHtml(vlabel) + '</span>' +
                            '<label class="tm-export-count-width-label" title="Ancho de celda para este valor">' +
                            '<span>Ancho</span>' +
                            '<input type="number" class="tm-export-count-width-input tm-input" min="6" max="40" value="' + escapeHtml(String(vw)) + '" aria-label="Ancho de celda para ' + escapeHtml(vlabel) + '">' +
                            '</label>' +
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

        function tmExportNormalizeText(value) {
            return String(value == null ? '' : value)
                .trim()
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
        }

        function tmExportParseNumber(value) {
            if (value == null) { return null; }
            if (typeof value === 'number') { return Number.isFinite(value) ? value : null; }
            var raw = String(value).trim();
            if (raw === '') { return null; }
            raw = raw.replace(/\s+/g, '');
            var commaCount = (raw.match(/,/g) || []).length;
            var dotCount = (raw.match(/\./g) || []).length;
            if (commaCount > 0 && dotCount > 0) {
                if (raw.lastIndexOf(',') > raw.lastIndexOf('.')) {
                    raw = raw.replace(/\./g, '').replace(',', '.');
                } else {
                    raw = raw.replace(/,/g, '');
                }
            } else if (commaCount > 0 && dotCount === 0) {
                raw = raw.replace(',', '.');
            }
            var num = Number(raw);
            return Number.isFinite(num) ? num : null;
        }

        function tmExportGetMunicipioFromEntry(entry, columns) {
            if (!entry || !entry.data) { return ''; }
            var direct = entry.data._municipio_reporte;
            if (direct != null && String(direct).trim() !== '') { return String(direct).trim(); }
            var munCol = (columns || []).find(function (c) {
                var k = String(c && c.key || '').toLowerCase();
                var l = String(c && c.label || '').toLowerCase();
                return k.indexOf('municipio') !== -1 || l.indexOf('municipio') !== -1;
            });
            if (munCol && entry.data[munCol.key] != null) {
                var val = String(entry.data[munCol.key]).trim();
                if (val !== '') { return val; }
            }
            return '';
        }

        function tmExportEnsureCalculatedColumnsState(countableColumns, draftCfg) {
            if (!personalizeModal) { return; }
            if (!Array.isArray(personalizeModal._calculatedColumns)) {
                personalizeModal._calculatedColumns = [];
            }

            var fromDraft = [];
            if (draftCfg && Array.isArray(draftCfg.calculated_columns)) {
                fromDraft = draftCfg.calculated_columns.map(function (c, idx) {
                    var id = String(c.id || ('calc_' + idx + '_' + Date.now()));
                    var opFields = Array.isArray(c.operation_fields)
                        ? c.operation_fields.map(function (k) { return String(k || ''); }).filter(Boolean)
                        : (Array.isArray(c.fields) ? c.fields.map(function (k) { return String(k || ''); }).filter(Boolean) : []);
                    var op = String(c.operation || '').toLowerCase();
                    if (['add', 'subtract', 'multiply', 'percent'].indexOf(op) === -1) { op = 'add'; }
                    return {
                        id: id,
                        label: String(c.label || ('Calculada ' + (idx + 1))),
                        group: String(c.group || ''),
                        operation: op,
                        baseField: String(c.base_field || c.baseField || c.reference_field || c.referenceField || ''),
                        afterKey: String(c.position_after_key || c.after_key || c.afterKey || ''),
                        operationFields: opFields,
                        cellColor: String(c.cell_color || c.color || 'var(--clr-secondary)'),
                        cellSizeCh: Math.max(8, Math.min(40, parseInt(String(c.cell_size_ch != null ? c.cell_size_ch : c.cellSizeCh), 10) || 18)),
                        cellBold: !!(c.cell_bold || c.cellBold)
                    };
                });
            } else if (draftCfg && draftCfg.include_operations_column) {
                fromDraft = [{
                    id: 'calc_legacy_1',
                    label: String(draftCfg.operations_label || 'Operaciones'),
                    group: String(draftCfg.operations_group || ''),
                    operation: (!Object.prototype.hasOwnProperty.call(draftCfg, 'operations_include_percent') || !!draftCfg.operations_include_percent) ? 'percent' : 'add',
                    baseField: String(draftCfg.operations_reference_field || ''),
                    afterKey: String(draftCfg.operations_after_key || ''),
                    operationFields: Array.isArray(draftCfg.operations_fields) ? draftCfg.operations_fields.map(function (k) { return String(k || ''); }).filter(Boolean) : [],
                    cellColor: 'var(--clr-secondary)',
                    cellSizeCh: 18,
                    cellBold: !!draftCfg.operations_cell_bold
                }];
            }

            personalizeModal._calculatedColumns = fromDraft;

            if (personalizeModal._calculatedColumns.length === 0 && Array.isArray(countableColumns) && countableColumns.length > 0) {
                personalizeModal._calculatedColumns.push({
                    id: 'calc_1',
                    label: 'Operaciones',
                    group: '',
                    operation: 'percent',
                    baseField: String(countableColumns[0].key || ''),
                    afterKey: '',
                    operationFields: [String(countableColumns[0].key || '')],
                    cellColor: 'var(--clr-secondary)',
                    cellSizeCh: 18,
                    cellBold: false
                });
            }
        }

        function tmExportRenderCalculatedColumnsConfigurator(countableColumns) {
            if (!personalizeModal) { return; }
            var wrap = document.getElementById('tmExportCalculatedColumnsList');
            if (!wrap) { return; }
            var list = Array.isArray(personalizeModal._calculatedColumns) ? personalizeModal._calculatedColumns : [];
            wrap.innerHTML = '';

            var optionsHtml = '<option value="">Sin referencia</option>' + (countableColumns || []).map(function (c) {
                return '<option value="' + escapeHtml(String(c.key || '')) + '">' + escapeHtml(String(c.label || c.key || '')) + '</option>';
            }).join('');
            var opMultiOptionsHtml = (countableColumns || []).map(function (c) {
                return '<option value="' + escapeHtml(String(c.key || '')) + '">' + escapeHtml(String(c.label || c.key || '')) + '</option>';
            }).join('');

            list.forEach(function (calc, idx) {
                var row = document.createElement('div');
                row.className = 'tm-export-sum-row tm-export-calc-row';
                row.setAttribute('data-calc-col-id', String(calc.id || ''));
                var operation = String(calc.operation || 'add').toLowerCase();
                if (['add', 'subtract', 'multiply', 'percent'].indexOf(operation) === -1) { operation = 'add'; }
                var baseField = String(calc.baseField || '');
                var group = String(calc.group || '');
                var groupOptions = tmExportBuildGroupOptionsHtml(group);
                var opFields = Array.isArray(calc.operationFields) ? calc.operationFields : [];
                var cellColor = String(calc.cellColor || 'var(--clr-secondary)');
                var cellSizeCh = Math.max(8, Math.min(40, parseInt(String(calc.cellSizeCh || 18), 10) || 18));
                var cellBold = !!calc.cellBold;
                var calcBoldClass = cellBold ? 'tm-export-col-bold-btn is-active' : 'tm-export-col-bold-btn';

                row.innerHTML = ''
                    + '<div class="tm-export-sum-name-wrap">'
                    + '  <span class="tm-export-sum-move-inline">'
                    + '    <button type="button" class="tm-btn tm-btn-outline tm-export-sum-move-btn" data-move-calc-up title="Subir">&#8593;</button>'
                    + '    <button type="button" class="tm-btn tm-btn-outline tm-export-sum-move-btn" data-move-calc-down title="Bajar">&#8595;</button>'
                    + '  </span>'
                    + '  <textarea class="tm-input tm-export-col-label-input" rows="2" data-calc-label placeholder="Nombre de columna">' + escapeHtml(String(calc.label || ('Calculada ' + (idx + 1)))) + '</textarea>'
                    + '</div>'
                    + '<select class="tm-input" data-calc-operation>'
                    + '  <option value="add">Suma (+)</option>'
                    + '  <option value="subtract">Resta (-)</option>'
                    + '  <option value="multiply">Multiplicación (×)</option>'
                    + '  <option value="percent">Porcentaje (%)</option>'
                    + '</select>'
                    + '<select class="tm-input" data-calc-group>' + groupOptions + '</select>'
                    + '<select class="tm-input" data-calc-base>' + optionsHtml + '</select>'
                    + '<select class="tm-input" data-calc-op-fields multiple size="4">' + opMultiOptionsHtml + '</select>'
                    + '<div class="tm-export-col-color tm-export-calc-color-wrap">'
                    + '  <button type="button" class="tm-export-color-trigger" data-calc-color-trigger data-color="' + escapeHtml(cellColor) + '" aria-haspopup="listbox" aria-expanded="false" title="Color de celda">'
                    + '    <span class="tm-export-color-swatch" style="background-color:' + escapeHtml(cellColor) + '"></span>'
                    + '  </button>'
                    + '  <div class="tm-export-color-menu" role="listbox" hidden>' + TEMPLATE_COLORS.map(function (c, i) {
                        return '<button type="button" class="tm-export-color-option' + (i === 0 ? ' is-active' : '') + '" data-color="' + escapeHtml(c.value) + '">' +
                            '<span class="tm-export-color-swatch" style="background-color:' + escapeHtml(c.value) + '"></span>' +
                            '<span class="tm-export-color-name">' + escapeHtml(c.name) + '</span></button>';
                    }).join('') + '</div>'
                    + '</div>'
                    + '<button type="button" class="tm-btn tm-btn-outline ' + calcBoldClass + '" data-calc-bold title="Negritas en celdas de contenido" aria-label="Negritas en celdas de contenido"><strong>B</strong></button>'
                    + '<label class="tm-export-col-field tm-export-col-field--wch tm-export-col-field--wch-compact">'
                    + '  <span class="tm-export-col-field__lab">Tamaño celda (ch)</span>'
                    + '  <input type="number" class="tm-input tm-input--num-compact" data-calc-size min="8" max="40" value="' + escapeHtml(String(cellSizeCh)) + '">'
                    + '</label>'
                    + '<button type="button" class="tm-btn tm-btn-danger" data-remove-calc-col>&times;</button>';

                wrap.appendChild(row);
                var opSel = row.querySelector('[data-calc-operation]');
                if (opSel) { opSel.value = operation; }
                var groupSel = row.querySelector('[data-calc-group]');
                if (groupSel) { groupSel.value = group; }
                var baseSel = row.querySelector('[data-calc-base]');
                if (baseSel) { baseSel.value = baseField; }
                var opFieldsSel = row.querySelector('[data-calc-op-fields]');
                if (opFieldsSel) {
                    Array.from(opFieldsSel.options).forEach(function (opt) {
                        opt.selected = opFields.indexOf(String(opt.value || '')) !== -1;
                    });
                }
                var colorTrigger = row.querySelector('[data-calc-color-trigger]');
                if (colorTrigger) {
                    row.querySelectorAll('.tm-export-color-option').forEach(function (opt) {
                        opt.classList.toggle('is-active', (opt.getAttribute('data-color') || '') === cellColor);
                    });
                }
            });
        }

        function tmExportMoveCalculatedColumn(id, direction) {
            if (!personalizeModal || !Array.isArray(personalizeModal._calculatedColumns)) { return false; }
            var calcList = personalizeModal._calculatedColumns.slice();
            var calcById = {};
            calcList.forEach(function (c) { calcById[String(c && c.id || '')] = c; });
            var baseColumns = Array.isArray(personalizeModal._personalizeColumns) ? personalizeModal._personalizeColumns : [];
            var baseKeys = baseColumns
                .map(function (c) { return String(c && c.key || ''); })
                .filter(function (k) { return k !== '' && k.indexOf('__calc_') !== 0; });

            var seq = [];
            baseKeys.forEach(function (bk) {
                seq.push({ type: 'base', key: bk });
                calcList.forEach(function (calc) {
                    if (String(calc && calc.afterKey || '') === bk) {
                        seq.push({ type: 'calc', id: String(calc.id || '') });
                    }
                });
            });
            calcList.forEach(function (calc) {
                var ak = String(calc && calc.afterKey || '');
                if (!ak || baseKeys.indexOf(ak) === -1) {
                    seq.push({ type: 'calc', id: String(calc.id || '') });
                }
            });

            var from = seq.findIndex(function (x) {
                return x && x.type === 'calc' && String(x.id || '') === String(id || '');
            });
            if (from < 0) { return false; }
            var to = direction === 'up' ? from - 1 : from + 1;
            if (to < 0 || to >= seq.length) { return false; }

            var tmp = seq[from];
            seq[from] = seq[to];
            seq[to] = tmp;

            var ordered = [];
            var lastBaseKey = '';
            seq.forEach(function (node) {
                if (!node) { return; }
                if (node.type === 'base') {
                    lastBaseKey = String(node.key || '');
                    return;
                }
                if (node.type !== 'calc') { return; }
                var calc = calcById[String(node.id || '')];
                if (!calc) { return; }
                calc.afterKey = lastBaseKey;
                ordered.push(calc);
            });

            personalizeModal._calculatedColumns = ordered;
            return true;
        }

        function tmExportEnsureSumState(countableColumns, draftCfg) {
            if (!personalizeModal) { return; }
            if (!Array.isArray(personalizeModal._sumMetrics)) {
                personalizeModal._sumMetrics = [];
            }
            if (!Array.isArray(personalizeModal._sumFormulas)) {
                personalizeModal._sumFormulas = [];
            }
            if (draftCfg && Array.isArray(draftCfg.sum_metrics)) {
                personalizeModal._sumMetrics = draftCfg.sum_metrics.map(function (m, idx) {
                    return {
                        id: String(m.id || ('m' + idx + '_' + Date.now())),
                        label: String(m.label || ('Métrica ' + (idx + 1))),
                        group: String(m.group || ''),
                        field_key: String(m.field_key || ''),
                        agg: ['sum', 'count_non_empty', 'count_unique', 'count_equals'].indexOf(String(m.agg || 'sum')) !== -1 ? String(m.agg) : 'sum',
                        match_value: String(m.match_value || ''),
                        text_color: String(m.text_color || 'var(--clr-primary)'),
                        include_total: !Object.prototype.hasOwnProperty.call(m || {}, 'include_total') ? true : !!m.include_total,
                        font_size: Math.max(9, Math.min(28, parseInt(String(m.font_size || '12'), 10) || 12)),
                        sort_order: Math.max(1, parseInt(String(m.sort_order || (idx + 1)), 10) || (idx + 1))
                    };
                });
            }
            if (draftCfg && Array.isArray(draftCfg.sum_formulas)) {
                personalizeModal._sumFormulas = draftCfg.sum_formulas.map(function (f, idx) {
                    var ids = Array.isArray(f.metric_ids) ? f.metric_ids.map(function (x) { return String(x); }) : [];
                    return {
                        id: String(f.id || ('f' + idx + '_' + Date.now())),
                        label: String(f.label || ('Cálculo ' + (idx + 1))),
                        group: String(f.group || ''),
                        op: ['add', 'subtract', 'multiply', 'divide', 'percent'].indexOf(String(f.op || 'add')) !== -1 ? String(f.op) : 'add',
                        metric_ids: ids,
                        base_metric_id: String(f.base_metric_id || ''),
                        text_color: String(f.text_color || 'var(--clr-primary)'),
                        include_total: !Object.prototype.hasOwnProperty.call(f || {}, 'include_total') ? true : !!f.include_total,
                        font_size: Math.max(9, Math.min(28, parseInt(String(f.font_size || '12'), 10) || 12)),
                        sort_order: Math.max(1, parseInt(String(f.sort_order || (idx + 1)), 10) || (idx + 1))
                    };
                });
            }
            if (personalizeModal._sumMetrics.length === 0 && Array.isArray(countableColumns) && countableColumns.length > 0) {
                personalizeModal._sumMetrics = [{
                    id: 'm1',
                    label: 'Total capturado',
                    group: '',
                    field_key: String(countableColumns[0].key || ''),
                    agg: 'count_non_empty',
                    match_value: '',
                    text_color: 'var(--clr-primary)',
                    include_total: true,
                    font_size: 12,
                    sort_order: 1
                }];
            }
            tmExportReindexSumOrders();
        }

        function tmExportBuildGroupOptionsHtml(selectedGroup) {
            var selected = String(selectedGroup || '');
            var groups = normalizeExportGroups((personalizeModal && Array.isArray(personalizeModal._exportGroups)) ? personalizeModal._exportGroups : []);
            var html = '<option value="">Sin grupo</option>';
            groups.forEach(function (g) {
                var name = String((g && g.name) || '').trim();
                if (!name) { return; }
                html += '<option value="' + escapeHtml(name) + '"' + (selected === name ? ' selected' : '') + '>' + escapeHtml(name) + '</option>';
            });
            return html;
        }

        function normalizeExportGroups(groupsRaw) {
            var source = Array.isArray(groupsRaw) ? groupsRaw : [];
            var used = {};
            var fallbackColor = (TEMPLATE_COLORS && TEMPLATE_COLORS[0] && TEMPLATE_COLORS[0].value) ? TEMPLATE_COLORS[0].value : 'var(--clr-primary)';
            var out = [];

            source.forEach(function (g) {
                var name = '';
                var color = fallbackColor;
                if (typeof g === 'string') {
                    name = g.trim();
                } else if (g && typeof g === 'object') {
                    name = String(g.name || '').trim();
                    if (g.color) {
                        color = String(g.color);
                    }
                }
                if (!name) { return; }
                var key = name.toLocaleLowerCase();
                if (used[key]) { return; }
                used[key] = true;
                out.push({ name: name, color: color || fallbackColor });
            });

            return out;
        }

        function buildGroupColorMap(groupsRaw) {
            var map = {};
            normalizeExportGroups(groupsRaw).forEach(function (g) {
                map[g.name] = g.color || 'var(--clr-primary)';
            });
            return map;
        }

        function tmExportRenderSumConfigurator(countableColumns) {
            if (!personalizeModal) { return; }
            var metricsWrap = document.getElementById('tmExportSumMetricsList');
            var formulasWrap = document.getElementById('tmExportSumFormulasList');
            if (!metricsWrap || !formulasWrap) { return; }
            var colorMenuHtml = TEMPLATE_COLORS.map(function (c, i) {
                return '<button type="button" class="tm-export-color-option' + (i === 0 ? ' is-active' : '') + '" data-color="' + escapeHtml(c.value) + '">' +
                    '<span class="tm-export-color-swatch" style="background-color:' + escapeHtml(c.value) + '"></span>' +
                    '<span class="tm-export-color-name">' + escapeHtml(c.name) + '</span></button>';
            }).join('');
            var metricOptions = (countableColumns || []).map(function (c) {
                return '<option value="' + escapeHtml(String(c.key || '')) + '">' + escapeHtml(String(c.label || c.key || '')) + '</option>';
            }).join('');

            metricsWrap.innerHTML = '';
            (personalizeModal._sumMetrics || []).forEach(function (m, idx) {
                var metricGroupOptions = tmExportBuildGroupOptionsHtml(m.group || '');
                var row = document.createElement('div');
                row.className = 'tm-export-sum-row';
                row.setAttribute('data-sum-metric-id', m.id);
                row.innerHTML = ''
                    + '<div class="tm-export-sum-name-wrap">'
                    + '  <span class="tm-export-sum-move-inline">'
                    + '    <button type="button" class="tm-btn tm-btn-outline tm-export-sum-move-btn" data-move-sum-metric-up title="Subir">&#8593;</button>'
                    + '    <button type="button" class="tm-btn tm-btn-outline tm-export-sum-move-btn" data-move-sum-metric-down title="Bajar">&#8595;</button>'
                    + '  </span>'
                    + '  <input type="text" class="tm-input" data-sum-metric-label placeholder="Etiqueta" value="' + escapeHtml(m.label || ('Métrica ' + (idx + 1))) + '">'
                    + '</div>'
                    + '<select class="tm-input" data-sum-metric-group>' + metricGroupOptions + '</select>'
                    + '<select class="tm-input" data-sum-metric-field>' + metricOptions + '</select>'
                    + '<select class="tm-input" data-sum-metric-agg>'
                    + '  <option value="sum">Suma numérica</option>'
                    + '  <option value="count_non_empty">Conteo con dato</option>'
                    + '  <option value="count_unique">Conteo por dato único</option>'
                    + '</select>'
                    + '<div class="tm-export-col-color">'
                    + '<button type="button" class="tm-export-color-trigger" data-sum-metric-color-trigger data-color="' + escapeHtml(String(m.text_color || 'var(--clr-primary)')) + '" aria-haspopup="listbox" aria-expanded="false" title="Color del encabezado">'
                    + '<span class="tm-export-color-swatch" style="background-color:' + escapeHtml(String(m.text_color || 'var(--clr-primary)')) + '"></span></button>'
                    + '<div class="tm-export-color-menu" role="listbox" hidden>' + colorMenuHtml + '</div></div>'
                    + '<label class="tm-export-count-table-toggle" style="white-space:nowrap;"><input type="checkbox" data-sum-metric-include-total ' + (m.include_total === false ? '' : 'checked') + '> Total</label>'
                    + '<input type="number" class="tm-input" data-sum-metric-size min="9" max="28" value="' + escapeHtml(String(m.font_size || 12)) + '" title="Tamaño texto (px)">'
                    + '<input type="text" class="tm-input" data-sum-metric-match placeholder="Valor a contar (si aplica)" value="' + escapeHtml(m.match_value || '') + '">'
                    + '<input type="hidden" data-sum-metric-order value="' + escapeHtml(String(m.sort_order || (idx + 1))) + '">'
                    + '<button type="button" class="tm-btn tm-btn-danger" data-remove-sum-metric>&times;</button>';
                metricsWrap.appendChild(row);
                var fSel = row.querySelector('[data-sum-metric-field]');
                var aSel = row.querySelector('[data-sum-metric-agg]');
                var matchEl = row.querySelector('[data-sum-metric-match]');
                if (fSel) { fSel.value = m.field_key || ''; }
                if (aSel) { aSel.value = m.agg || 'sum'; }
                if (matchEl && aSel) { matchEl.hidden = aSel.value !== 'count_equals'; }
                var metricTrigger = row.querySelector('[data-sum-metric-color-trigger]');
                var metricColor = (metricTrigger && metricTrigger.getAttribute('data-color')) ? metricTrigger.getAttribute('data-color') : 'var(--clr-primary)';
                row.querySelectorAll('.tm-export-color-option').forEach(function (opt) {
                    opt.classList.toggle('is-active', (opt.getAttribute('data-color') || '') === metricColor);
                });
            });

            formulasWrap.innerHTML = '';
            var metricChoices = (personalizeModal._sumMetrics || []).map(function (m) {
                var label = String(m.label || m.id || 'Métrica');
                return '<option value="' + escapeHtml(String(m.id)) + '">' + escapeHtml(label) + '</option>';
            }).join('');
            var metricSingleChoices = '<option value="">Base 100%...</option>' + metricChoices;

            (personalizeModal._sumFormulas || []).forEach(function (f, idx) {
                var formulaGroupOptions = tmExportBuildGroupOptionsHtml(f.group || '');
                var row = document.createElement('div');
                row.className = 'tm-export-sum-row tm-export-sum-row--formula';
                row.setAttribute('data-sum-formula-id', f.id);
                row.innerHTML = ''
                    + '<div class="tm-export-sum-name-wrap">'
                    + '  <span class="tm-export-sum-move-inline">'
                    + '    <button type="button" class="tm-btn tm-btn-outline tm-export-sum-move-btn" data-move-sum-formula-up title="Subir">&#8593;</button>'
                    + '    <button type="button" class="tm-btn tm-btn-outline tm-export-sum-move-btn" data-move-sum-formula-down title="Bajar">&#8595;</button>'
                    + '  </span>'
                    + '  <input type="text" class="tm-input" data-sum-formula-label placeholder="Etiqueta cálculo" value="' + escapeHtml(f.label || ('Cálculo ' + (idx + 1))) + '">'
                    + '</div>'
                    + '<select class="tm-input" data-sum-formula-group>' + formulaGroupOptions + '</select>'
                    + '<select class="tm-input" data-sum-formula-op>'
                    + '  <option value="add">Suma (+)</option>'
                    + '  <option value="subtract">Resta (-)</option>'
                    + '  <option value="multiply">Multiplicación (×)</option>'
                    + '  <option value="divide">División (÷)</option>'
                    + '  <option value="percent">Porcentaje (%)</option>'
                    + '</select>'
                    + '<select class="tm-input" data-sum-formula-metrics multiple size="3">' + metricChoices + '</select>'
                    + '<select class="tm-input" data-sum-formula-base>' + metricSingleChoices + '</select>'
                    + '<div class="tm-export-col-color">'
                    + '<button type="button" class="tm-export-color-trigger" data-sum-formula-color-trigger data-color="' + escapeHtml(String(f.text_color || 'var(--clr-primary)')) + '" aria-haspopup="listbox" aria-expanded="false" title="Color del encabezado">'
                    + '<span class="tm-export-color-swatch" style="background-color:' + escapeHtml(String(f.text_color || 'var(--clr-primary)')) + '"></span></button>'
                    + '<div class="tm-export-color-menu" role="listbox" hidden>' + colorMenuHtml + '</div></div>'
                    + '<label class="tm-export-count-table-toggle" style="white-space:nowrap;"><input type="checkbox" data-sum-formula-include-total ' + (f.include_total === false ? '' : 'checked') + '> Total</label>'
                    + '<input type="number" class="tm-input" data-sum-formula-size min="9" max="28" value="' + escapeHtml(String(f.font_size || 12)) + '" title="Tamaño texto (px)">'
                    + '<input type="hidden" data-sum-formula-order value="' + escapeHtml(String(f.sort_order || (idx + 1))) + '">'
                    + '<button type="button" class="tm-btn tm-btn-danger" data-remove-sum-formula>&times;</button>';
                formulasWrap.appendChild(row);
                var op = row.querySelector('[data-sum-formula-op]');
                if (op) { op.value = f.op || 'add'; }
                var ms = row.querySelector('[data-sum-formula-metrics]');
                if (ms) {
                    Array.from(ms.options).forEach(function (opt) {
                        opt.selected = (f.metric_ids || []).indexOf(opt.value) !== -1;
                    });
                }
                var baseSel = row.querySelector('[data-sum-formula-base]');
                if (baseSel) {
                    baseSel.value = String(f.base_metric_id || '');
                    baseSel.hidden = (f.op || 'add') !== 'percent';
                }
                var formulaTrigger = row.querySelector('[data-sum-formula-color-trigger]');
                var formulaColor = (formulaTrigger && formulaTrigger.getAttribute('data-color')) ? formulaTrigger.getAttribute('data-color') : 'var(--clr-primary)';
                row.querySelectorAll('.tm-export-color-option').forEach(function (opt) {
                    opt.classList.toggle('is-active', (opt.getAttribute('data-color') || '') === formulaColor);
                });
            });
        }

        function tmExportReadSumConfigurator() {
            if (!personalizeModal) { return { metrics: [], formulas: [] }; }
            var metrics = [];
            var formulas = [];
            var metricsWrap = document.getElementById('tmExportSumMetricsList');
            var formulasWrap = document.getElementById('tmExportSumFormulasList');
            if (metricsWrap) {
                metricsWrap.querySelectorAll('[data-sum-metric-id]').forEach(function (row) {
                    var id = row.getAttribute('data-sum-metric-id') || '';
                    var label = (row.querySelector('[data-sum-metric-label]') || {}).value || '';
                    var group = (row.querySelector('[data-sum-metric-group]') || {}).value || '';
                    var fieldKey = (row.querySelector('[data-sum-metric-field]') || {}).value || '';
                    var agg = (row.querySelector('[data-sum-metric-agg]') || {}).value || 'sum';
                    var match = (row.querySelector('[data-sum-metric-match]') || {}).value || '';
                    var metricColorTrigger = row.querySelector('[data-sum-metric-color-trigger]');
                    var textColor = (metricColorTrigger && metricColorTrigger.getAttribute('data-color')) ? metricColorTrigger.getAttribute('data-color') : 'var(--clr-primary)';
                    var fontSize = parseInt((row.querySelector('[data-sum-metric-size]') || {}).value || '12', 10);
                    var includeTotal = !!((row.querySelector('[data-sum-metric-include-total]') || {}).checked);
                    var order = parseInt((row.querySelector('[data-sum-metric-order]') || {}).value || '0', 10);
                    if (!id || !fieldKey) { return; }
                    metrics.push({
                        id: id,
                        label: String(label || id).trim(),
                        group: String(group || '').trim(),
                        field_key: fieldKey,
                        agg: agg,
                        match_value: String(match || '').trim(),
                        text_color: String(textColor || 'var(--clr-primary)'),
                        include_total: includeTotal,
                        font_size: Number.isNaN(fontSize) ? 12 : Math.max(9, Math.min(28, fontSize)),
                        sort_order: Number.isNaN(order) ? 0 : order
                    });
                });
            }
            if (formulasWrap) {
                formulasWrap.querySelectorAll('[data-sum-formula-id]').forEach(function (row) {
                    var id = row.getAttribute('data-sum-formula-id') || '';
                    var label = (row.querySelector('[data-sum-formula-label]') || {}).value || '';
                    var group = (row.querySelector('[data-sum-formula-group]') || {}).value || '';
                    var op = (row.querySelector('[data-sum-formula-op]') || {}).value || 'add';
                    var metricIds = [];
                    var sel = row.querySelector('[data-sum-formula-metrics]');
                    if (sel) {
                        metricIds = Array.from(sel.selectedOptions || []).map(function (o) { return o.value; }).filter(Boolean);
                    }
                    var baseMetricId = ((row.querySelector('[data-sum-formula-base]') || {}).value || '').trim();
                    var formulaColorTrigger = row.querySelector('[data-sum-formula-color-trigger]');
                    var textColor = (formulaColorTrigger && formulaColorTrigger.getAttribute('data-color')) ? formulaColorTrigger.getAttribute('data-color') : 'var(--clr-primary)';
                    var fontSize = parseInt((row.querySelector('[data-sum-formula-size]') || {}).value || '12', 10);
                    var includeTotal = !!((row.querySelector('[data-sum-formula-include-total]') || {}).checked);
                    var order = parseInt((row.querySelector('[data-sum-formula-order]') || {}).value || '0', 10);
                    if (!id || metricIds.length === 0) { return; }
                    formulas.push({
                        id: id,
                        label: String(label || id).trim(),
                        group: String(group || '').trim(),
                        op: op,
                        metric_ids: metricIds,
                        base_metric_id: baseMetricId,
                        text_color: String(textColor || 'var(--clr-primary)'),
                        include_total: includeTotal,
                        font_size: Number.isNaN(fontSize) ? 12 : Math.max(9, Math.min(28, fontSize)),
                        sort_order: Number.isNaN(order) ? 0 : order
                    });
                });
            }
            return { metrics: metrics, formulas: formulas };
        }

        function tmExportGetSumCombinedSorted() {
            var metrics = Array.isArray(personalizeModal._sumMetrics) ? personalizeModal._sumMetrics : [];
            var formulas = Array.isArray(personalizeModal._sumFormulas) ? personalizeModal._sumFormulas : [];
            var combined = [];
            metrics.forEach(function (m, idx) { combined.push({ type: 'metric', id: m.id, ref: m, fallback: idx }); });
            formulas.forEach(function (f, idx) { combined.push({ type: 'formula', id: f.id, ref: f, fallback: metrics.length + idx }); });
            combined.forEach(function (x, idx) {
                var raw = parseInt(String((x.ref && x.ref.sort_order) || ''), 10);
                x.order = Number.isNaN(raw) ? (idx + 1) : raw;
            });
            combined.sort(function (a, b) {
                if (a.order !== b.order) { return a.order - b.order; }
                return a.fallback - b.fallback;
            });
            return combined;
        }

        function tmExportReindexSumOrders() {
            var combined = tmExportGetSumCombinedSorted();
            combined.forEach(function (x, idx) {
                if (x.ref) { x.ref.sort_order = idx + 1; }
            });
        }

        function tmExportMoveSumColumn(kind, id, direction) {
            if (!personalizeModal || !id) { return false; }
            tmExportReindexSumOrders();
            var combined = tmExportGetSumCombinedSorted();
            var idx = combined.findIndex(function (x) { return x.type === kind && x.id === id; });
            if (idx === -1) { return false; }
            var target = direction === 'up' ? idx - 1 : idx + 1;
            if (target < 0 || target >= combined.length) { return false; }
            var a = combined[idx];
            var b = combined[target];
            var tmp = a.ref.sort_order;
            a.ref.sort_order = b.ref.sort_order;
            b.ref.sort_order = tmp;
            tmExportReindexSumOrders();
            return true;
        }

        function tmExportBuildSumPreviewData(entries, microrregionMeta, columns, state) {
            if (!state || !state.includeSumTable) { return null; }
            var metrics = Array.isArray(state.sumMetrics) ? state.sumMetrics : [];
            if (metrics.length === 0 || !Array.isArray(entries) || entries.length === 0) { return null; }
            var formulas = Array.isArray(state.sumFormulas) ? state.sumFormulas : [];
            var by = state.sumGroupBy === 'municipio' ? 'municipio' : 'microrregion';
            var groups = [];
            var indexMap = {};

            entries.forEach(function (entry) {
                var groupLabel = 'Sin grupo';
                var groupMrNumber = '';
                var groupMrCabecera = '';
                if (by === 'microrregion') {
                    var mr = microrregionMeta && microrregionMeta[entry.microrregion_id];
                    groupLabel = (mr && mr.label) ? String(mr.label) : 'Sin microrregión';
                    groupMrNumber = (mr && mr.number) ? String(mr.number) : '';
                    groupMrCabecera = (mr && mr.cabecera) ? String(mr.cabecera) : '';
                } else {
                    groupLabel = tmExportGetMunicipioFromEntry(entry, columns) || 'Sin municipio';
                    var mrMpio = microrregionMeta && microrregionMeta[entry.microrregion_id];
                    groupMrNumber = (mrMpio && mrMpio.number) ? String(mrMpio.number) : '';
                    groupMrCabecera = (mrMpio && mrMpio.cabecera) ? String(mrMpio.cabecera) : '';
                }
                var groupKey = by + ':' + groupLabel;
                if (indexMap[groupKey] == null) {
                    indexMap[groupKey] = groups.length;
                    var metricVals = {};
                    metrics.forEach(function (m) { metricVals[m.id] = 0; });
                    groups.push({ key: groupKey, label: groupLabel, mrNumber: groupMrNumber, mrCabecera: groupMrCabecera, metrics: metricVals, formulas: {}, _uniqueSets: {} });
                }
                var g = groups[indexMap[groupKey]];
                if (!g.mrNumber && groupMrNumber) { g.mrNumber = groupMrNumber; }
                if (!g.mrCabecera && groupMrCabecera) { g.mrCabecera = groupMrCabecera; }
                metrics.forEach(function (m) {
                    var raw = entry && entry.data ? entry.data[m.field_key] : null;
                    if (m.agg === 'sum') {
                        var acc = 0;
                        if (Array.isArray(raw)) {
                            raw.forEach(function (part) {
                                var n = tmExportParseNumber(part);
                                if (n != null) { acc += n; }
                            });
                        } else {
                            var n1 = tmExportParseNumber(raw);
                            if (n1 != null) { acc += n1; }
                        }
                        g.metrics[m.id] = (g.metrics[m.id] || 0) + acc;
                    } else if (m.agg === 'count_non_empty') {
                        var hasValue = false;
                        if (Array.isArray(raw)) {
                            hasValue = raw.some(function (x) { return String(x == null ? '' : x).trim() !== ''; });
                        } else if (raw && typeof raw === 'object' && Object.prototype.hasOwnProperty.call(raw, 'primary')) {
                            hasValue = String(raw.primary == null ? '' : raw.primary).trim() !== '';
                        } else {
                            hasValue = String(raw == null ? '' : raw).trim() !== '';
                        }
                        if (hasValue) { g.metrics[m.id] = (g.metrics[m.id] || 0) + 1; }
                    } else if (m.agg === 'count_unique' || m.agg === 'count_equals') {
                        var target = tmExportNormalizeText(m.match_value || '');
                        if (m.agg === 'count_equals' && target !== '') {
                            var matched = false;
                            if (Array.isArray(raw)) {
                                matched = raw.some(function (x) { return tmExportNormalizeText(x) === target; });
                            } else {
                                matched = tmExportNormalizeText(raw) === target;
                            }
                            if (matched) { g.metrics[m.id] = (g.metrics[m.id] || 0) + 1; }
                        } else {
                            if (!g._uniqueSets[m.id]) { g._uniqueSets[m.id] = {}; }
                            var pushUnique = function (val) {
                                var key = tmExportNormalizeText(val);
                                if (key !== '') { g._uniqueSets[m.id][key] = true; }
                            };
                            if (Array.isArray(raw)) {
                                raw.forEach(function (x) { pushUnique(x); });
                            } else if (raw && typeof raw === 'object' && Object.prototype.hasOwnProperty.call(raw, 'primary')) {
                                pushUnique(raw.primary);
                            } else {
                                pushUnique(raw);
                            }
                        }
                    }
                });
            });

            if (by === 'municipio') {
                groups.sort(function (a, b) { return a.label.localeCompare(b.label, undefined, { sensitivity: 'base' }); });
            }

            groups.forEach(function (g) {
                metrics.forEach(function (m) {
                    var target = tmExportNormalizeText(m.match_value || '');
                    if (m.agg === 'count_unique' || (m.agg === 'count_equals' && target === '')) {
                        var set = g._uniqueSets && g._uniqueSets[m.id] ? g._uniqueSets[m.id] : {};
                        g.metrics[m.id] = Object.keys(set).length;
                    }
                });
                delete g._uniqueSets;
            });

            groups.forEach(function (g) {
                formulas.forEach(function (f) {
                    var vals = (f.metric_ids || []).map(function (id) { return Number(g.metrics[id] || 0); });
                    if (vals.length === 0) { g.formulas[f.id] = 0; return; }
                    if (f.op === 'subtract') {
                        g.formulas[f.id] = vals.slice(1).reduce(function (acc, n) { return acc - n; }, vals[0]);
                    } else if (f.op === 'multiply') {
                        g.formulas[f.id] = vals.reduce(function (acc, n) { return acc * n; }, 1);
                    } else if (f.op === 'divide') {
                        g.formulas[f.id] = vals.slice(1).reduce(function (acc, n) {
                            return n === 0 ? 0 : (acc / n);
                        }, vals[0]);
                    } else if (f.op === 'percent') {
                        var numerator = Number(vals[0] || 0);
                        var base = Number(g.metrics[f.base_metric_id] || 0);
                        if (!Number.isFinite(base) || base === 0) {
                            g.formulas[f.id] = 0;
                        } else {
                            g.formulas[f.id] = (numerator / base) * 100;
                        }
                    } else {
                        g.formulas[f.id] = vals.reduce(function (acc, n) { return acc + n; }, 0);
                    }
                });
            });

            return {
                groupBy: by,
                groupLabel: by === 'microrregion' ? 'Microrregión' : 'Municipio',
                metrics: metrics,
                formulas: formulas,
                groups: groups
            };
        }

        function tmExportBuildOrderedSumColumns(sumData) {
            var columns = [];
            (sumData.metrics || []).forEach(function (m, idx) {
                columns.push({ type: 'metric', id: m.id, label: m.label || m.id, group: String(m.group || ''), order: Number.isFinite(Number(m.sort_order)) ? Number(m.sort_order) : (idx + 1), source: m, fallback: idx });
            });
            (sumData.formulas || []).forEach(function (f, idx) {
                columns.push({ type: 'formula', id: f.id, label: f.label || f.id, group: String(f.group || ''), order: Number.isFinite(Number(f.sort_order)) ? Number(f.sort_order) : (idx + 1), source: f, fallback: (sumData.metrics || []).length + idx });
            });
            columns.sort(function (a, b) {
                if (a.order !== b.order) { return a.order - b.order; }
                return (a.fallback || 0) - (b.fallback || 0);
            });
            return columns;
        }

        function tmExportRenderTotalsStandalonePreviewTable(sumData, headersUppercase, totalsTableAlign, totalsTableTitle, totalsTableTitleAlign, totalsTableTitleFontSize, totalsHeaderFontPx, totalsGroupHeaderFontPx, totalsCellFontPx, sumTotalsBold, sumTotalsTextColor, groups) {
            if (!sumData || !Array.isArray(sumData.groups) || sumData.groups.length === 0) { return ''; }
            var sumColumns = tmExportBuildOrderedSumColumns(sumData);
            if (!sumColumns.length) { return ''; }
            var align = (totalsTableAlign === 'center' || totalsTableAlign === 'right') ? totalsTableAlign : 'left';
            var tableMargin = align === 'center' ? 'margin:0 auto 1rem auto;' : (align === 'right' ? 'margin:0 0 1rem auto;' : 'margin:0 1rem 1rem 0;');
            var titleRaw = String(totalsTableTitle || '').trim() !== '' ? String(totalsTableTitle) : 'Totales';
            var title = normalizeExportHeadingText(titleRaw, !!headersUppercase);
            var titleAlign = (totalsTableTitleAlign === 'left' || totalsTableTitleAlign === 'right') ? totalsTableTitleAlign : 'left';
            var titleFont = parseInt(String(totalsTableTitleFontSize || '14'), 10);
            titleFont = Number.isNaN(titleFont) ? 14 : Math.max(10, Math.min(36, titleFont));
            var headerFont = parseInt(String(totalsHeaderFontPx || '12'), 10);
            headerFont = Number.isNaN(headerFont) ? 12 : Math.max(9, Math.min(48, headerFont));
            var groupHeaderFont = parseInt(String(totalsGroupHeaderFontPx || headerFont || '12'), 10);
            groupHeaderFont = Number.isNaN(groupHeaderFont) ? headerFont : Math.max(9, Math.min(48, groupHeaderFont));
            var cellFont = parseInt(String(totalsCellFontPx || '12'), 10);
            cellFont = Number.isNaN(cellFont) ? 12 : Math.max(9, Math.min(24, cellFont));
            var hasGroups = sumColumns.some(function (c) { return String(c.group || '').trim() !== ''; });
            var groupColorMap = buildGroupColorMap(Array.isArray(groups) ? groups : []);
            var spans = [];
            if (hasGroups) {
                sumColumns.forEach(function (c) {
                    var g = String(c.group || '');
                    if (spans.length > 0 && spans[spans.length - 1].label === g) {
                        spans[spans.length - 1].span++;
                    } else {
                        spans.push({ label: g, span: 1 });
                    }
                });
            }

            var totalsStyle = (sumTotalsBold ? 'font-weight:700;' : '') + 'color:' + escapeHtml(String(sumTotalsTextColor || 'var(--clr-primary)')) + ';';
            var html = '<p class="tm-export-preview-desglose-label" style="font-weight:600;margin:10px 0 4px 0;text-align:' + titleAlign + ';font-size:' + titleFont + 'px;">' + escapeHtml(title) + '</p>';
            html += '<table class="tm-export-preview-table tm-export-preview-sum-table" style="table-layout:auto;border-collapse:collapse;width:auto;' + tableMargin + '">';
            if (hasGroups) {
                html += '<tr class="tm-export-preview-row tm-export-preview-group-header">';
                html += '<th class="tm-export-preview-cell" style="background:#475569;color:#fff;border:1px solid #334155;"></th>';
                spans.forEach(function (s) {
                    if (String(s.label || '').trim() === '') {
                        html += '<th class="tm-export-preview-cell" colspan="' + s.span + '" style="background:#f8fafc;border:1px solid #e2e8f0;font-size:' + groupHeaderFont + 'px;"></th>';
                    } else {
                        var gColor = groupColorMap[s.label] || '#64748b';
                        html += '<th class="tm-export-preview-cell" colspan="' + s.span + '" style="background:' + escapeHtml(gColor) + ';color:#fff;border:1px solid #1e293b;font-size:' + groupHeaderFont + 'px;">' + escapeHtml(normalizeExportHeadingText(s.label, !!headersUppercase)) + '</th>';
                    }
                });
                html += '</tr>';
            }
            html += '<tr class="tm-export-preview-row tm-export-preview-header">';
            html += '<th class="tm-export-preview-cell tm-export-preview-header-cell" style="background-color:#475569;color:#fff;font-size:' + headerFont + 'px;">' + escapeHtml(normalizeExportHeadingText('Total', !!headersUppercase)) + '</th>';
            sumColumns.forEach(function (c) {
                var cGroup = String(c.group || '').trim();
                var cColor = cGroup !== '' ? (groupColorMap[cGroup] || '#64748b') : '#475569';
                html += '<th class="tm-export-preview-cell tm-export-preview-header-cell" style="background-color:' + escapeHtml(cColor) + ';color:#fff;font-size:' + headerFont + 'px;">' + escapeHtml(normalizeExportHeadingText(c.label || c.id, !!headersUppercase)) + '</th>';
            });
            html += '</tr>';
            html += '<tr class="tm-export-preview-row tm-export-preview-data">';
            html += '<td class="tm-export-preview-cell tm-export-preview-data-cell" style="font-size:' + cellFont + 'px;' + totalsStyle + '">' + escapeHtml(normalizeExportHeadingText('Total', !!headersUppercase)) + '</td>';
            sumColumns.forEach(function (c) {
                var val = 0;
                if (c.type === 'formula' && String((c.source && c.source.op) || '') === 'percent') {
                    var metricIds = Array.isArray(c.source && c.source.metric_ids) ? c.source.metric_ids : [];
                    var numeratorMetricId = String(metricIds.length ? (metricIds[0] || '') : '');
                    var baseMetricId = String((c.source && c.source.base_metric_id) || '');
                    var numeratorTotal = 0;
                    var baseTotal = 0;
                    if (numeratorMetricId !== '' && baseMetricId !== '') {
                        sumData.groups.forEach(function (g) {
                            numeratorTotal += Number(g.metrics && g.metrics[numeratorMetricId] || 0);
                            baseTotal += Number(g.metrics && g.metrics[baseMetricId] || 0);
                        });
                    }
                    val = baseTotal !== 0 ? ((numeratorTotal / baseTotal) * 100) : 0;
                } else if (c.type === 'metric') {
                    sumData.groups.forEach(function (g) { val += Number(g.metrics && g.metrics[c.id] || 0); });
                } else {
                    sumData.groups.forEach(function (g) { val += Number(g.formulas && g.formulas[c.id] || 0); });
                }
                var isPercent = c.type === 'formula' && String((c.source && c.source.op) || '') === 'percent';
                var txt = String(Math.round((val + Number.EPSILON) * 100) / 100) + (isPercent ? '%' : '');
                html += '<td class="tm-export-preview-cell tm-export-preview-data-cell" style="font-size:' + cellFont + 'px;' + totalsStyle + '">' + escapeHtml(txt) + '</td>';
            });
            html += '</tr>';
            html += '</table>';
            return html;
        }

        function tmExportRenderSumPreviewTable(sumData, headersUppercase, sumTableAlign, sumTitle, sumTitleCase, sumTitleAlign, sumTitleFontSize, sumHeaderFontPx, sumGroupHeaderFontPx, sumCellFontPx, sumGroupColor, sumIncludeTotalsRow, sumTotalsBold, sumTotalsTextColor, groups, sumLeadConfig) {
            if (!sumData || !Array.isArray(sumData.groups) || sumData.groups.length === 0) { return ''; }
            var rawTitle = String(sumTitle || '').trim() !== '' ? String(sumTitle) : 'Sumatoria';
            var title = normalizeExportHeadingText(rawTitle, !!headersUppercase);
            if (sumTitleCase === 'upper') {
                title = title.toLocaleUpperCase();
            } else if (sumTitleCase === 'lower') {
                var lowered = title.toLocaleLowerCase();
                title = lowered.charAt(0).toLocaleUpperCase() + lowered.slice(1);
            }
            var align = (sumTableAlign === 'center' || sumTableAlign === 'right') ? sumTableAlign : 'left';
            var sumTableMargin = align === 'center' ? 'margin:0 auto 1rem auto;' : (align === 'right' ? 'margin:0 0 1rem auto;' : 'margin:0 1rem 1rem 0;');
            var sumColumns = tmExportBuildOrderedSumColumns(sumData);
            var hasGroups = sumColumns.some(function (c) { return String(c.group || '').trim() !== ''; });
            var groupColorMap = buildGroupColorMap(Array.isArray(groups) ? groups : []);
            var spans = [];
            if (hasGroups) {
                sumColumns.forEach(function (c) {
                    var g = String(c.group || '');
                    if (spans.length > 0 && spans[spans.length - 1].label === g) {
                        spans[spans.length - 1].span++;
                    } else {
                        spans.push({ label: g, span: 1 });
                    }
                });
            }

            var titleAlign = (sumTitleAlign === 'left' || sumTitleAlign === 'right') ? sumTitleAlign : 'center';
            var titleFont = parseInt(String(sumTitleFontSize || '14'), 10);
            titleFont = Number.isNaN(titleFont) ? 14 : Math.max(10, Math.min(36, titleFont));
            var sumHeaderFont = parseInt(String(sumHeaderFontPx || '12'), 10);
            sumHeaderFont = Number.isNaN(sumHeaderFont) ? 12 : Math.max(9, Math.min(28, sumHeaderFont));
            var sumGroupHeaderFont = parseInt(String(sumGroupHeaderFontPx || sumHeaderFont || '12'), 10);
            sumGroupHeaderFont = Number.isNaN(sumGroupHeaderFont) ? sumHeaderFont : Math.max(9, Math.min(48, sumGroupHeaderFont));
            var sumCellFont = parseInt(String(sumCellFontPx || '12'), 10);
            sumCellFont = Number.isNaN(sumCellFont) ? 12 : Math.max(9, Math.min(24, sumCellFont));
            var firstColColor = String(sumGroupColor || 'var(--clr-primary)');
            var cfg = (sumLeadConfig && typeof sumLeadConfig === 'object') ? sumLeadConfig : {};
            var showItem = !Object.prototype.hasOwnProperty.call(cfg, 'showItem') ? true : !!cfg.showItem;
            var showDeleg = !Object.prototype.hasOwnProperty.call(cfg, 'showDelegation') ? true : !!cfg.showDelegation;
            var showCabecera = !Object.prototype.hasOwnProperty.call(cfg, 'showCabecera') ? true : !!cfg.showCabecera;
            var itemLabel = String(cfg.itemLabel || '#').trim() || '#';
            var delegLabel = String(cfg.delegationLabel || 'Delegación').trim() || 'Delegación';
            var cabeceraLabel = String(cfg.cabeceraLabel || 'Cabecera').trim() || 'Cabecera';
            var groupBy = String(sumData.groupBy || 'microrregion');
            var leadColumns = [];
            if (showItem) { leadColumns.push({ key: 'item', label: itemLabel }); }
            if (groupBy === 'microrregion') {
                if (showDeleg) { leadColumns.push({ key: 'delegacion_numero', label: delegLabel }); }
                if (showCabecera) { leadColumns.push({ key: 'cabecera_microrregion', label: cabeceraLabel }); }
            } else {
                leadColumns.push({ key: 'group', label: sumData.groupLabel || 'Municipio' });
                if (showDeleg) { leadColumns.push({ key: 'delegacion_numero', label: delegLabel }); }
                if (showCabecera) { leadColumns.push({ key: 'cabecera_microrregion', label: cabeceraLabel }); }
            }
            if (leadColumns.length === 0) {
                leadColumns.push({ key: 'group', label: sumData.groupLabel || 'Grupo' });
            }
            var html = '<p class="tm-export-preview-desglose-label" style="font-weight:600;margin:10px 0 4px 0;text-align:' + titleAlign + ';font-size:' + titleFont + 'px;">' + escapeHtml(title) + '</p>';
            html += '<table class="tm-export-preview-table tm-export-preview-sum-table" style="table-layout:auto;border-collapse:collapse;width:auto;' + sumTableMargin + '">';
            if (hasGroups) {
                html += '<tr class="tm-export-preview-row tm-export-preview-group-header">';
                leadColumns.forEach(function () {
                    html += '<th class="tm-export-preview-cell" style="background:' + escapeHtml(firstColColor) + ';color:#fff;border:1px solid #334155;"></th>';
                });
                spans.forEach(function (s) {
                    if (s.label.trim() === '') {
                        html += '<th class="tm-export-preview-cell" colspan="' + s.span + '" style="background:#f8fafc;border:1px solid #e2e8f0;font-size:' + sumGroupHeaderFont + 'px;"></th>';
                    } else {
                        var sumGroupHeaderColor = groupColorMap[s.label] || '#64748b';
                        html += '<th class="tm-export-preview-cell" colspan="' + s.span + '" style="background:' + escapeHtml(sumGroupHeaderColor) + ';color:#fff;border:1px solid #1e293b;font-size:' + sumGroupHeaderFont + 'px;">' + escapeHtml(normalizeExportHeadingText(s.label, headersUppercase)) + '</th>';
                    }
                });
                html += '</tr>';
            }
            html += '<tr class="tm-export-preview-row tm-export-preview-header">';
            leadColumns.forEach(function (col) {
                html += '<th class="tm-export-preview-cell tm-export-preview-header-cell" style="background-color:' + escapeHtml(firstColColor) + ';color:#fff;font-size:' + sumHeaderFont + 'px;">' + escapeHtml(normalizeExportHeadingText(col.label, !!headersUppercase)) + '</th>';
            });
            sumColumns.forEach(function (c) {
                var cGroup = String(c.group || '').trim();
                var cColor = cGroup !== ''
                    ? (groupColorMap[cGroup] || '#64748b')
                    : ((c.source && c.source.text_color) ? String(c.source.text_color) : '#0f172a');
                html += '<th class="tm-export-preview-cell tm-export-preview-header-cell" style="background-color:' + escapeHtml(cColor) + ';color:#fff;font-size:' + sumHeaderFont + 'px;">' + escapeHtml(normalizeExportHeadingText(c.label || c.id, !!headersUppercase)) + '</th>';
            });
            html += '</tr>';
            sumData.groups.forEach(function (g, idx) {
                html += '<tr class="tm-export-preview-row tm-export-preview-data">';
                leadColumns.forEach(function (col) {
                    var leadValue = '';
                    if (col.key === 'item') {
                        leadValue = String(idx + 1);
                    } else if (col.key === 'delegacion_numero') {
                        leadValue = String(g.mrNumber || '');
                    } else if (col.key === 'cabecera_microrregion') {
                        leadValue = String(g.mrCabecera || '');
                    } else {
                        leadValue = String(g.label || '');
                    }
                    html += '<td class="tm-export-preview-cell tm-export-preview-data-cell" style="font-size:' + sumCellFont + 'px;">' + escapeHtml(leadValue) + '</td>';
                });
                sumColumns.forEach(function (c) {
                    var val = c.type === 'metric' ? Number(g.metrics[c.id] || 0) : Number(g.formulas[c.id] || 0);
                    var isPercent = c.type === 'formula' && String((c.source && c.source.op) || '') === 'percent';
                    html += '<td class="tm-export-preview-cell tm-export-preview-data-cell" style="font-size:' + sumCellFont + 'px;">' + escapeHtml(String(Math.round((val + Number.EPSILON) * 100) / 100)) + (isPercent ? '%' : '') + '</td>';
                });
                html += '</tr>';
            });

            if (sumIncludeTotalsRow) {
                var totalsStyle = '';
                if (sumTotalsBold) {
                    totalsStyle += 'font-weight:700;';
                }
                totalsStyle += 'color:' + escapeHtml(String(sumTotalsTextColor || 'var(--clr-primary)')) + ';';

                html += '<tr class="tm-export-preview-row tm-export-preview-data">';
                leadColumns.forEach(function (col, colIdx) {
                    var label = colIdx === 0 ? normalizeExportHeadingText('Total', !!headersUppercase) : '';
                    html += '<td class="tm-export-preview-cell tm-export-preview-data-cell" style="font-size:' + sumCellFont + 'px;' + totalsStyle + '">' + escapeHtml(label) + '</td>';
                });
                sumColumns.forEach(function (c) {
                    var includeTotal = !(c.source && Object.prototype.hasOwnProperty.call(c.source, 'include_total')) || !!c.source.include_total;
                    var totalVal = 0;
                    if (includeTotal) {
                        var isPercentFormula = c.type === 'formula' && String((c.source && c.source.op) || '') === 'percent';
                        if (isPercentFormula) {
                            var metricIds = Array.isArray(c.source && c.source.metric_ids) ? c.source.metric_ids : [];
                            var numeratorMetricId = String(metricIds.length ? (metricIds[0] || '') : '');
                            var baseMetricId = String((c.source && c.source.base_metric_id) || '');
                            var numeratorTotal = 0;
                            var baseTotal = 0;
                            if (numeratorMetricId !== '' && baseMetricId !== '') {
                                sumData.groups.forEach(function (g) {
                                    var nNum = Number(g.metrics && g.metrics[numeratorMetricId] || 0);
                                    var nBase = Number(g.metrics && g.metrics[baseMetricId] || 0);
                                    if (Number.isFinite(nNum)) { numeratorTotal += nNum; }
                                    if (Number.isFinite(nBase)) { baseTotal += nBase; }
                                });
                                totalVal = baseTotal !== 0 ? ((numeratorTotal / baseTotal) * 100) : 0;
                            }
                        } else {
                            sumData.groups.forEach(function (g) {
                                var n = c.type === 'metric' ? Number(g.metrics[c.id] || 0) : Number(g.formulas[c.id] || 0);
                                if (Number.isFinite(n)) { totalVal += n; }
                            });
                        }
                    }
                    var isPercent = c.type === 'formula' && String((c.source && c.source.op) || '') === 'percent';
                    var txt = includeTotal ? String(Math.round((totalVal + Number.EPSILON) * 100) / 100) + (isPercent ? '%' : '') : '';
                    html += '<td class="tm-export-preview-cell tm-export-preview-data-cell" style="font-size:' + sumCellFont + 'px;' + totalsStyle + '">' + escapeHtml(txt) + '</td>';
                });
                html += '</tr>';
            }

            html += '</table>';
            return html;
        }

        function applyExportPreviewPageLayout(orientation, paperSize) {
            var previewPage = document.getElementById('tmExportPreviewPage');
            if (!previewPage) { return; }
            var orient = orientation === 'landscape' ? 'landscape' : 'portrait';
            var paper = paperSize === 'legal' ? 'legal' : 'letter';
            previewPage.classList.toggle('is-landscape', orient === 'landscape');
            previewPage.classList.toggle('is-legal', paper === 'legal');
            previewPage.classList.toggle('is-letter', paper !== 'legal');
        }

        function getPersonalizeState() {
            var modal = document.getElementById('tmExportPersonalizeModal');
            var container = modal ? modal.querySelector('#tmExportPersonalizeColumns') : document.getElementById('tmExportPersonalizeColumns');
            if (!container) {
                return { title: '', titleAlign: 'center', countTableAlign: 'left', dataTableAlign: 'left', sectionLabel: 'Desglose', sectionLabelAlign: 'left', sumTableAlign: 'left', sumTitle: 'Sumatoria', sumTitleCase: 'normal', sumTitleAlign: 'center', sumTitleFontPx: 14, sumShowItem: true, sumItemLabel: '#', sumShowDelegation: true, sumDelegationLabel: 'Delegación', sumShowCabecera: true, sumCabeceraLabel: 'Cabecera', sumGroupColor: 'var(--clr-primary)', sumIncludeTotalsRow: false, includeTotalsTable: false, totalsTableTitle: 'Totales', totalsTableAlign: 'left', sumTotalsBold: true, sumTotalsTextColor: 'var(--clr-primary)', titleUppercase: false, headersUppercase: false, columns: [], sampleRow: {}, countTableColors: {}, countTableCellWidth: 12, recordsCellFontPx: 12, recordsHeaderFontPx: 12, recordsGroupHeaderFontPx: 12, sumCellFontPx: 12, sumHeaderFontPx: 12, sumGroupHeaderFontPx: 12, totalsCellFontPx: 12, totalsHeaderFontPx: 12, totalsGroupHeaderFontPx: 12, cellFontPx: 12, headerFontPx: 12, titleFontPx: 18, docMarginPreset: 'compact', paperSize: 'letter', groups: [], microrregionSort: 'asc', includeSumTable: false, sumGroupBy: 'microrregion', includeCalculatedColumns: false, calculatedColumns: [], includeOperationsColumn: false, operationsLabel: 'Operaciones', operationsReferenceField: '', operationsIncludePercent: true, operationsFields: [], sumMetrics: [], sumFormulas: [] };
            }
            const titleEl = modal ? modal.querySelector('#tmExportPersonalizeTitle') : document.getElementById('tmExportPersonalizeTitle');
            const titleUppercaseEl = modal ? modal.querySelector('#tmExportTitleUppercase') : document.getElementById('tmExportTitleUppercase');
            const headersUppercaseEl = modal ? modal.querySelector('#tmExportHeadersUppercase') : document.getElementById('tmExportHeadersUppercase');
            const cellFontEl = modal ? modal.querySelector('#tmExportCellFontSize') : document.getElementById('tmExportCellFontSize');
            const titleFontEl = modal ? modal.querySelector('#tmExportTitleFontSize') : document.getElementById('tmExportTitleFontSize');
            const headerFontEl = modal ? modal.querySelector('#tmExportHeaderFontSize') : document.getElementById('tmExportHeaderFontSize');
            const microrregionSortEl = modal ? modal.querySelector('#tmExportMicrorregionSort') : document.getElementById('tmExportMicrorregionSort');
            const includeCalculatedColumnsEl = modal ? modal.querySelector('#tmExportIncludeCalculatedColumns') : document.getElementById('tmExportIncludeCalculatedColumns');
            const calculatedColumnsListEl = modal ? modal.querySelector('#tmExportCalculatedColumnsList') : document.getElementById('tmExportCalculatedColumnsList');
            const docMarginPresetEl = modal ? modal.querySelector('#tmExportDocMarginPreset') : document.getElementById('tmExportDocMarginPreset');
            const paperSizeEl = modal ? modal.querySelector('#tmExportPaperSize') : document.getElementById('tmExportPaperSize');
            const alignBtn = modal ? modal.querySelector('#tmExportTitleAlignGroup .tm-export-align-btn.is-active') : null;
            const titleAlign = (alignBtn && alignBtn.getAttribute('data-title-align')) || 'center';
            const countTableAlignBtn = modal ? modal.querySelector('#tmExportCountAlignGroup .tm-export-align-btn.is-active') : null;
            const countTableAlign = (countTableAlignBtn && countTableAlignBtn.getAttribute('data-count-table-align')) || 'left';
            const dataTableAlignBtn = modal ? modal.querySelector('#tmExportDataAlignGroup .tm-export-align-btn.is-active') : null;
            const dataTableAlign = (dataTableAlignBtn && dataTableAlignBtn.getAttribute('data-data-table-align')) || 'left';
            const sumTableAlignBtn = modal ? modal.querySelector('#tmExportSumAlignGroup .tm-export-align-btn.is-active') : null;
            const sumTableAlign = (sumTableAlignBtn && sumTableAlignBtn.getAttribute('data-sum-table-align')) || 'left';
            const sumTitleEl = modal ? modal.querySelector('#tmExportSumTitle') : document.getElementById('tmExportSumTitle');
            const sumTitleUppercaseEl = modal ? modal.querySelector('#tmExportSumTitleUppercase') : document.getElementById('tmExportSumTitleUppercase');
            const sumTitleFontEl = modal ? modal.querySelector('#tmExportSumTitleFontSize') : document.getElementById('tmExportSumTitleFontSize');
            const sumShowItemColEl = modal ? modal.querySelector('#tmExportSumShowItemCol') : document.getElementById('tmExportSumShowItemCol');
            const sumItemLabelEl = modal ? modal.querySelector('#tmExportSumItemLabel') : document.getElementById('tmExportSumItemLabel');
            const sumShowDelegacionColEl = modal ? modal.querySelector('#tmExportSumShowDelegacionCol') : document.getElementById('tmExportSumShowDelegacionCol');
            const sumDelegacionLabelEl = modal ? modal.querySelector('#tmExportSumDelegacionLabel') : document.getElementById('tmExportSumDelegacionLabel');
            const sumShowCabeceraColEl = modal ? modal.querySelector('#tmExportSumShowCabeceraCol') : document.getElementById('tmExportSumShowCabeceraCol');
            const sumCabeceraLabelEl = modal ? modal.querySelector('#tmExportSumCabeceraLabel') : document.getElementById('tmExportSumCabeceraLabel');
            const sectionLabelEl = modal ? modal.querySelector('#tmExportSectionLabel') : document.getElementById('tmExportSectionLabel');
            const sumGroupColorTrigger = modal ? modal.querySelector('#tmExportSumGroupColorTrigger') : document.getElementById('tmExportSumGroupColorTrigger');
            const sumIncludeTotalsRowEl = modal ? modal.querySelector('#tmExportSumIncludeTotalsRow') : document.getElementById('tmExportSumIncludeTotalsRow');
            const sumCellFontEl = modal ? modal.querySelector('#tmExportSumCellFontSize') : document.getElementById('tmExportSumCellFontSize');
            const sumHeaderFontEl = modal ? modal.querySelector('#tmExportSumHeaderFontSize') : document.getElementById('tmExportSumHeaderFontSize');
            const recordsGroupHeaderFontEl = modal ? modal.querySelector('#tmExportRecordsGroupHeaderFontSize') : document.getElementById('tmExportRecordsGroupHeaderFontSize');
            const sumGroupHeaderFontEl = modal ? modal.querySelector('#tmExportSumGroupHeaderFontSize') : document.getElementById('tmExportSumGroupHeaderFontSize');
            const includeTotalsTableEl = modal ? modal.querySelector('#tmExportIncludeTotalsTable') : document.getElementById('tmExportIncludeTotalsTable');
            const totalsTableTitleEl = modal ? modal.querySelector('#tmExportTotalsTableTitle') : document.getElementById('tmExportTotalsTableTitle');
            const totalsCellFontEl = modal ? modal.querySelector('#tmExportTotalsCellFontSize') : document.getElementById('tmExportTotalsCellFontSize');
            const totalsHeaderFontEl = modal ? modal.querySelector('#tmExportTotalsHeaderFontSize') : document.getElementById('tmExportTotalsHeaderFontSize');
            const totalsGroupHeaderFontEl = modal ? modal.querySelector('#tmExportTotalsGroupHeaderFontSize') : document.getElementById('tmExportTotalsGroupHeaderFontSize');
            const sumTotalsBoldEl = modal ? modal.querySelector('#tmExportSumTotalsBold') : document.getElementById('tmExportSumTotalsBold');
            const sumTotalsColorTrigger = modal ? modal.querySelector('#tmExportSumTotalsColorTrigger') : document.getElementById('tmExportSumTotalsColorTrigger');
            const sumTitleAlignBtn = modal ? modal.querySelector('#tmExportSumTitleAlignGroup .tm-export-align-btn.is-active') : null;
            const sectionLabelAlignBtn = modal ? modal.querySelector('#tmExportSectionLabelAlignGroup .tm-export-align-btn.is-active') : null;
            const totalsTableAlignBtn = modal ? modal.querySelector('#tmExportTotalsTableAlignGroup .tm-export-align-btn.is-active') : null;
            const sumTitleAlign = (sumTitleAlignBtn && sumTitleAlignBtn.getAttribute('data-sum-title-align')) || 'center';
            const sectionLabelAlign = (sectionLabelAlignBtn && sectionLabelAlignBtn.getAttribute('data-section-label-align')) || 'left';
            const totalsTableAlign = (totalsTableAlignBtn && totalsTableAlignBtn.getAttribute('data-totals-table-align')) || 'left';
            const sumTitleFontPx = sumTitleFontEl && sumTitleFontEl.value ? Math.max(10, Math.min(36, parseInt(sumTitleFontEl.value, 10) || 14)) : 14;
            const sumGroupColor = (sumGroupColorTrigger && sumGroupColorTrigger.getAttribute('data-color')) ? sumGroupColorTrigger.getAttribute('data-color') : 'var(--clr-primary)';
            const sumTotalsTextColor = (sumTotalsColorTrigger && sumTotalsColorTrigger.getAttribute('data-color')) ? sumTotalsColorTrigger.getAttribute('data-color') : 'var(--clr-primary)';
            const recordsCellFontPx = cellFontEl && cellFontEl.value ? Math.max(9, Math.min(24, parseInt(cellFontEl.value, 10) || 12)) : 12;
            const titleFontPx = titleFontEl && titleFontEl.value ? Math.max(10, Math.min(36, parseInt(titleFontEl.value, 10) || 18)) : 18;
            const recordsHeaderFontPx = headerFontEl && headerFontEl.value ? Math.max(9, Math.min(28, parseInt(headerFontEl.value, 10) || 12)) : 12;
            const recordsGroupHeaderFontPx = recordsGroupHeaderFontEl && recordsGroupHeaderFontEl.value ? Math.max(9, Math.min(48, parseInt(recordsGroupHeaderFontEl.value, 10) || 12)) : recordsHeaderFontPx;
            const sumCellFontPx = sumCellFontEl && sumCellFontEl.value ? Math.max(9, Math.min(24, parseInt(sumCellFontEl.value, 10) || 12)) : 12;
            const sumHeaderFontPx = sumHeaderFontEl && sumHeaderFontEl.value ? Math.max(9, Math.min(28, parseInt(sumHeaderFontEl.value, 10) || 12)) : 12;
            const sumGroupHeaderFontPx = sumGroupHeaderFontEl && sumGroupHeaderFontEl.value ? Math.max(9, Math.min(48, parseInt(sumGroupHeaderFontEl.value, 10) || 12)) : sumHeaderFontPx;
            const totalsCellFontPx = totalsCellFontEl && totalsCellFontEl.value ? Math.max(9, Math.min(24, parseInt(totalsCellFontEl.value, 10) || 12)) : 12;
            const totalsHeaderFontPx = totalsHeaderFontEl && totalsHeaderFontEl.value ? Math.max(9, Math.min(48, parseInt(totalsHeaderFontEl.value, 10) || 12)) : 12;
            const totalsGroupHeaderFontPx = totalsGroupHeaderFontEl && totalsGroupHeaderFontEl.value ? Math.max(9, Math.min(48, parseInt(totalsGroupHeaderFontEl.value, 10) || 12)) : totalsHeaderFontPx;
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
                    var row2Widths = {};
                    row.querySelectorAll('.tm-export-count-table-value-color').forEach(function (vrow) {
                        var v = vrow.getAttribute('data-value');
                        var vt = vrow.querySelector('.tm-export-color-trigger');
                        if (v && vt) { row2Values[v] = vt.getAttribute('data-color') || 'var(--clr-secondary)'; }
                        var vw = vrow.querySelector('.tm-export-count-width-input');
                        if (v && vw) {
                            var wn = parseInt(vw.value, 10);
                            if (!Number.isNaN(wn)) { row2Widths[v] = Math.max(6, Math.min(40, wn)); }
                        }
                    });
                    if (Object.keys(row2Values).length) { obj.row2Values = row2Values; }
                    if (Object.keys(row2Widths).length) { obj.row2Widths = row2Widths; }
                    var pctCheck = row.querySelector('.tm-export-count-pct-check');
                    obj.showPct = !!(pctCheck && pctCheck.checked);
                    var srCheck = row.querySelector('.tm-export-count-sr-check');
                    if (srCheck) { obj.showSR = !!srCheck.checked; }
                    countTableColors[k] = obj;
                });
            }
            var countTableCellWidthEl = modal ? modal.querySelector('#tmExportCountTableCellWidth') : document.getElementById('tmExportCountTableCellWidth');
            var countTableCellWidth = (countTableCellWidthEl && countTableCellWidthEl.value) ? (parseInt(countTableCellWidthEl.value, 10) || 12) : 12;
            var groups = normalizeExportGroups((personalizeModal && personalizeModal._exportGroups) || []);
            var includeSumTableEl = modal ? modal.querySelector('#tmExportIncludeSumTable') : document.getElementById('tmExportIncludeSumTable');
            var sumGroupByEl = modal ? modal.querySelector('#tmExportSumGroupBy') : document.getElementById('tmExportSumGroupBy');
            var sumCfg = tmExportReadSumConfigurator();
            var calculatedColumns = [];
            if (calculatedColumnsListEl) {
                calculatedColumnsListEl.querySelectorAll('[data-calc-col-id]').forEach(function (row) {
                    var id = String(row.getAttribute('data-calc-col-id') || '');
                    if (!id) { return; }
                    var labelEl = row.querySelector('[data-calc-label]');
                    var opEl = row.querySelector('[data-calc-operation]');
                    var groupEl = row.querySelector('[data-calc-group]');
                    var baseEl = row.querySelector('[data-calc-base]');
                    var opFieldsEl = row.querySelector('[data-calc-op-fields]');
                    var colorTrigger = row.querySelector('[data-calc-color-trigger]');
                    var sizeEl = row.querySelector('[data-calc-size]');
                    var boldBtn = row.querySelector('[data-calc-bold]');
                    var existingCalc = Array.isArray(personalizeModal._calculatedColumns)
                        ? personalizeModal._calculatedColumns.find(function (x) { return String(x && x.id || '') === id; })
                        : null;
                    var opFields = [];
                    if (opFieldsEl) {
                        opFields = Array.from(opFieldsEl.selectedOptions || []).map(function (o) { return String(o.value || ''); }).filter(Boolean);
                    }
                    var op = opEl ? String(opEl.value || 'add') : 'add';
                    if (['add', 'subtract', 'multiply', 'percent'].indexOf(op) === -1) { op = 'add'; }
                    var size = parseInt(String(sizeEl ? sizeEl.value : '18'), 10);
                    if (Number.isNaN(size)) { size = 18; }
                    calculatedColumns.push({
                        id: id,
                        label: labelEl && String(labelEl.value || '').trim() !== '' ? String(labelEl.value).trim() : ('Calculada ' + (calculatedColumns.length + 1)),
                        group: groupEl ? String(groupEl.value || '').trim() : '',
                        operation: op,
                        baseField: baseEl ? String(baseEl.value || '') : '',
                        afterKey: existingCalc ? String(existingCalc.afterKey || '') : '',
                        operationFields: opFields,
                        cellColor: (colorTrigger && colorTrigger.getAttribute('data-color')) ? String(colorTrigger.getAttribute('data-color')) : 'var(--clr-secondary)',
                        cellSizeCh: Math.max(8, Math.min(40, size)),
                        cellBold: !!(boldBtn && boldBtn.classList.contains('is-active'))
                    });
                });
            }
            var firstCalculated = calculatedColumns.length ? calculatedColumns[0] : null;
            return {
                title: titleEl ? titleEl.value : '',
                titleAlign: titleAlign,
                countTableAlign: countTableAlign,
                dataTableAlign: dataTableAlign,
                sumTableAlign: sumTableAlign,
                sumTitle: sumTitleEl ? String(sumTitleEl.value || '').trim() : 'Sumatoria',
                sumTitleCase: (sumTitleUppercaseEl && sumTitleUppercaseEl.checked) ? 'upper' : 'lower',
                sumShowItem: !(sumShowItemColEl && !sumShowItemColEl.checked),
                sumItemLabel: sumItemLabelEl && String(sumItemLabelEl.value || '').trim() !== '' ? String(sumItemLabelEl.value).trim() : '#',
                sumShowDelegation: !(sumShowDelegacionColEl && !sumShowDelegacionColEl.checked),
                sumDelegationLabel: sumDelegacionLabelEl && String(sumDelegacionLabelEl.value || '').trim() !== '' ? String(sumDelegacionLabelEl.value).trim() : 'Delegación',
                sumShowCabecera: !(sumShowCabeceraColEl && !sumShowCabeceraColEl.checked),
                sumCabeceraLabel: sumCabeceraLabelEl && String(sumCabeceraLabelEl.value || '').trim() !== '' ? String(sumCabeceraLabelEl.value).trim() : 'Cabecera',
                sectionLabel: sectionLabelEl && String(sectionLabelEl.value || '').trim() !== '' ? String(sectionLabelEl.value).trim() : 'Desglose',
                sectionLabelAlign: ['left', 'center', 'right'].indexOf(sectionLabelAlign) !== -1 ? sectionLabelAlign : 'left',
                sumTitleAlign: sumTitleAlign,
                sumTitleFontPx: sumTitleFontPx,
                sumGroupColor: sumGroupColor,
                sumIncludeTotalsRow: !!(sumIncludeTotalsRowEl && sumIncludeTotalsRowEl.checked),
                includeTotalsTable: !!(includeTotalsTableEl && includeTotalsTableEl.checked),
                totalsTableTitle: totalsTableTitleEl && String(totalsTableTitleEl.value || '').trim() !== '' ? String(totalsTableTitleEl.value).trim() : 'Totales',
                totalsTableAlign: ['left', 'center', 'right'].indexOf(totalsTableAlign) !== -1 ? totalsTableAlign : 'left',
                sumTotalsBold: !(sumTotalsBoldEl && !sumTotalsBoldEl.checked),
                sumTotalsTextColor: sumTotalsTextColor,
                titleUppercase: !!(titleUppercaseEl && titleUppercaseEl.checked),
                headersUppercase: !!(headersUppercaseEl && headersUppercaseEl.checked),
                columns: columns,
                countTableColors: countTableColors,
                countTableCellWidth: countTableCellWidth,
                recordsCellFontPx: recordsCellFontPx,
                recordsHeaderFontPx: recordsHeaderFontPx,
                recordsGroupHeaderFontPx: recordsGroupHeaderFontPx,
                sumCellFontPx: sumCellFontPx,
                sumHeaderFontPx: sumHeaderFontPx,
                sumGroupHeaderFontPx: sumGroupHeaderFontPx,
                totalsCellFontPx: totalsCellFontPx,
                totalsHeaderFontPx: totalsHeaderFontPx,
                totalsGroupHeaderFontPx: totalsGroupHeaderFontPx,
                cellFontPx: recordsCellFontPx,
                titleFontPx: titleFontPx,
                headerFontPx: recordsHeaderFontPx,
                docMarginPreset: (docMarginPresetEl && ['normal', 'compact', 'none'].indexOf(docMarginPresetEl.value) !== -1) ? docMarginPresetEl.value : 'compact',
                paperSize: (paperSizeEl && ['letter', 'legal'].indexOf(String(paperSizeEl.value || '').toLowerCase()) !== -1) ? String(paperSizeEl.value).toLowerCase() : 'letter',
                groups: groups,
                microrregionSort: (microrregionSortEl && microrregionSortEl.value === 'desc') ? 'desc' : 'asc',
                includeSumTable: !!(includeSumTableEl && includeSumTableEl.checked),
                sumGroupBy: (sumGroupByEl && sumGroupByEl.value === 'municipio') ? 'municipio' : 'microrregion',
                includeCalculatedColumns: !!(includeCalculatedColumnsEl && includeCalculatedColumnsEl.checked),
                calculatedColumns: calculatedColumns,
                includeOperationsColumn: !!(includeCalculatedColumnsEl && includeCalculatedColumnsEl.checked) && !!firstCalculated,
                operationsLabel: firstCalculated ? String(firstCalculated.label || 'Operaciones') : 'Operaciones',
                operationsReferenceField: firstCalculated ? String(firstCalculated.baseField || '') : '',
                operationsIncludePercent: firstCalculated ? (String(firstCalculated.operation || 'add') === 'percent') : true,
                operationsFields: firstCalculated && Array.isArray(firstCalculated.operationFields) ? firstCalculated.operationFields.slice() : [],
                sumMetrics: sumCfg.metrics,
                sumFormulas: sumCfg.formulas
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

        function tmExportIsEmptyCellValue(val) {
            if (val === null || val === undefined) { return true; }
            if (Array.isArray(val)) {
                if (!val.length) { return true; }
                return val.every(function (v) { return tmExportIsEmptyCellValue(v); });
            }
            if (typeof val === 'object') {
                if (Object.prototype.hasOwnProperty.call(val, 'primary')) {
                    return tmExportIsEmptyCellValue(val.primary);
                }
                var keys = Object.keys(val);
                if (!keys.length) { return true; }
                return keys.every(function (k) { return tmExportIsEmptyCellValue(val[k]); });
            }
            return String(val).trim() === '';
        }

        function tmExportLooksNumericColumn(col, originalValue) {
            if (typeof originalValue === 'number') { return true; }
            var hints = [
                String(col && col.type || ''),
                String(col && col.field_type || ''),
                String(col && col.data_type || ''),
                String(col && col.key || ''),
                String(col && col.label || '')
            ].join(' ').toLowerCase();
            return /(number|numeric|int|integer|decimal|float|double|cantidad|total|monto|importe|num)/.test(hints);
        }

        function tmExportApplyEmptyFillForColumn(col, rawValue) {
            if (!col || col.is_image || ['item', 'microrregion', 'delegacion_numero', 'cabecera_microrregion'].indexOf(String(col.key || '')) !== -1) {
                return rawValue;
            }
            if (!tmExportIsEmptyCellValue(rawValue)) {
                return rawValue;
            }
            var mode = String(col.fill_empty_mode || col.fillEmptyMode || 'none');
            if (mode !== 'auto' && mode !== 'custom') {
                return rawValue;
            }
            if (mode === 'custom') {
                return String(col.fill_empty_value != null ? col.fill_empty_value : (col.fillEmptyValue || ''));
            }
            return tmExportLooksNumericColumn(col, rawValue) ? 0 : 'S/R';
        }

        function escapeHtmlWithBreaks(val) {
            return escapeHtml(String(val == null ? '' : val)).replace(/\r?\n/g, '<br>');
        }

        function normalizeExportHeadingText(text, uppercase) {
            var value = text == null ? '' : String(text);
            return uppercase ? value.toLocaleUpperCase() : value;
        }

        function tmExportOperationIsEmpty(val) {
            if (val === null || val === undefined) { return true; }
            if (Array.isArray(val)) {
                return val.every(function (item) { return tmExportOperationIsEmpty(item); });
            }
            if (typeof val === 'object') {
                if (Object.prototype.hasOwnProperty.call(val, 'primary')) {
                    return tmExportOperationIsEmpty(val.primary);
                }
                var keys = Object.keys(val);
                if (!keys.length) { return true; }
                return keys.every(function (k) { return tmExportOperationIsEmpty(val[k]); });
            }
            return String(val).trim() === '';
        }

        function tmExportBuildCalculatedTextForEntry(entry, calculatedColumn, effectiveColumns, headersUppercase) {
            var data = (entry && entry.data && typeof entry.data === 'object') ? entry.data : {};
            var cfg = calculatedColumn && typeof calculatedColumn === 'object' ? calculatedColumn : {};
            var op = String(cfg.operation || 'add').toLowerCase();
            if (['add', 'subtract', 'multiply', 'percent'].indexOf(op) === -1) { op = 'add'; }
            var baseKey = String(cfg.baseField || '');
            var opKeys = Array.isArray(cfg.operationFields) ? cfg.operationFields.slice() : [];

            var toNumber = function (raw) {
                if (Array.isArray(raw)) {
                    var sum = 0;
                    var hasAny = false;
                    raw.forEach(function (part) {
                        var n = tmExportParseNumber(part);
                        if (n != null) { hasAny = true; sum += n; }
                    });
                    return hasAny ? sum : null;
                }
                if (raw && typeof raw === 'object' && Object.prototype.hasOwnProperty.call(raw, 'primary')) {
                    return tmExportParseNumber(raw.primary);
                }
                return tmExportParseNumber(raw);
            };

            var baseValRaw = baseKey ? toNumber(data[baseKey]) : null;
            var baseVal = baseValRaw != null ? baseValRaw : 0;
            var opVals = [];
            opKeys.forEach(function (k) {
                if (!k) { return; }
                var n = toNumber(data[k]);
                if (n != null) { opVals.push(n); }
            });

            var result = null;
            if (op === 'add') {
                var sumOps = opVals.reduce(function (acc, n) { return acc + n; }, 0);
                result = baseVal + sumOps;
            } else if (op === 'subtract') {
                var subOps = opVals.reduce(function (acc, n) { return acc + n; }, 0);
                result = baseVal - subOps;
            } else if (op === 'multiply') {
                var product = opVals.length ? opVals.reduce(function (acc, n) { return acc * n; }, 1) : 1;
                result = baseVal * product;
            } else if (op === 'percent') {
                var numerator = opVals.reduce(function (acc, n) { return acc + n; }, 0);
                result = baseVal !== 0 ? ((numerator / baseVal) * 100) : 0;
            }

            if (result == null || !Number.isFinite(result)) {
                return '';
            }

            var rounded = Math.round((result + Number.EPSILON) * 100) / 100;
            return op === 'percent' ? (String(rounded) + '%') : String(rounded);
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
            const recordsCellFontPx = state.recordsCellFontPx || state.cellFontPx || 12;
            const recordsHeaderFontPx = state.recordsHeaderFontPx || state.headerFontPx || 12;
            const recordsGroupHeaderFontPx = state.recordsGroupHeaderFontPx || recordsHeaderFontPx;
            const sumCellFontPx = state.sumCellFontPx || recordsCellFontPx;
            const sumHeaderFontPx = state.sumHeaderFontPx || recordsHeaderFontPx;
            const sumGroupHeaderFontPx = state.sumGroupHeaderFontPx || sumHeaderFontPx;
            const totalsCellFontPx = state.totalsCellFontPx || sumCellFontPx;
            const totalsHeaderFontPx = state.totalsHeaderFontPx || sumHeaderFontPx;
            const totalsGroupHeaderFontPx = state.totalsGroupHeaderFontPx || totalsHeaderFontPx;
            const titleFontPx = state.titleFontPx || 18;
            const titleAlign = state.titleAlign || 'center';
            const countTableAlign = state.countTableAlign || 'left';
            const dataTableAlign = state.dataTableAlign || 'left';
            const titleUppercase = !!state.titleUppercase;
            const headersUppercase = !!state.headersUppercase;
            const docMarginPreset = state.docMarginPreset || 'compact';
            var includeCalculatedColumns = !!state.includeCalculatedColumns;
            var calculatedColumns = Array.isArray(state.calculatedColumns) ? state.calculatedColumns.slice() : [];
            var effectiveColumns = columns.slice();
            if (includeCalculatedColumns) {
                var insertCalculatedColumn = function (calc, index) {
                    var cid = String(calc && calc.id ? calc.id : ('calc_' + index));
                    var clabel = String(calc && calc.label ? calc.label : ('Calculada ' + (index + 1))).trim();
                    if (clabel === '') { clabel = 'Calculada ' + (index + 1); }
                    var calcColor = String(calc && calc.cellColor ? calc.cellColor : 'var(--clr-secondary)');
                    var calcSizeCh = Math.max(8, Math.min(40, parseInt(String(calc && calc.cellSizeCh != null ? calc.cellSizeCh : 18), 10) || 18));
                    var calcCol = {
                        key: '__calc_' + cid,
                        label: clabel,
                        color: calcColor,
                        group: String(calc && calc.group ? calc.group : ''),
                        max_width_chars: calcSizeCh,
                        content_bold: !!(calc && (calc.cellBold || calc.cell_bold)),
                        _calc_cfg: calc || null,
                    };
                    var afterKey = String(calc && calc.afterKey ? calc.afterKey : '');
                    if (afterKey === '') {
                        effectiveColumns.push(calcCol);
                        return;
                    }
                    var insertAt = -1;
                    for (var i = 0; i < effectiveColumns.length; i++) {
                        if (String(effectiveColumns[i] && effectiveColumns[i].key || '') === afterKey) {
                            insertAt = i + 1;
                            break;
                        }
                    }
                    if (insertAt === -1) {
                        effectiveColumns.push(calcCol);
                    } else {
                        effectiveColumns.splice(insertAt, 0, calcCol);
                    }
                };
                calculatedColumns.forEach(function (calc, index) {
                    insertCalculatedColumn(calc, index);
                });
            }
            const titleStyle = 'text-align:' + (titleAlign === 'left' ? 'left' : titleAlign === 'right' ? 'right' : 'center');
            var countTableMargin = countTableAlign === 'center' ? 'margin: 1.5rem auto;' : countTableAlign === 'right' ? 'margin: 1.5rem 0 1.5rem auto;' : 'margin: 1.5rem auto 1.5rem 0;';
            var estimateColumnUnits = function (col) {
                if (!col) { return 8; }
                if (String(col.key || '').indexOf('__calc_') === 0) {
                    var calcW = parseInt(String(col.max_width_chars != null ? col.max_width_chars : 18), 10);
                    return Math.max(8, Math.min(40, Number.isNaN(calcW) ? 18 : calcW));
                }
                if (col.is_image) {
                    var imageCfg = state.columns.find(function (x) { return x.key === col.key; }) || {};
                    var imageW = imageCfg.imageWidth || 120;
                    return Math.max(8, Math.round(imageW / 10));
                }
                var raw = col.max_width_chars;
                if (raw != null && !Number.isNaN(parseInt(raw, 10))) {
                    var custom = parseInt(raw, 10);
                    return Math.max(2, Math.min(custom, 60));
                }
                var key = col.key || '';
                if (key === 'item') { return 4; }
                if (key === 'microrregion') { return 18; }
                if (key === 'delegacion_numero') { return 10; }
                if (key === 'cabecera_microrregion') { return 18; }
                if (key === 'municipio') { return 20; }
                if (key === 'estatus') { return 12; }
                return 24;
            };
            var totalUnits = 0;
            var colUnitsByKey = {};
            effectiveColumns.forEach(function (col) {
                var units = estimateColumnUnits(col);
                colUnitsByKey[col.key] = units;
                totalUnits += units;
            });
            if (totalUnits <= 0) { totalUnits = 1; }
            var colPercentByKey = {};
            Object.keys(colUnitsByKey).forEach(function (k) {
                colPercentByKey[k] = (colUnitsByKey[k] * 100) / totalUnits;
            });

            // Si hay muchas columnas o ancho total alto, usar todo el ancho de la hoja para evitar recortes.
            var forceFullWidthDataTable = effectiveColumns.length >= 6 || totalUnits > 110;
            var effectiveDataTableAlign = forceFullWidthDataTable ? 'left' : dataTableAlign;
            var dataTableMargin = effectiveDataTableAlign === 'center' ? 'margin: 1.5rem auto;' : effectiveDataTableAlign === 'right' ? 'margin: 1.5rem 0 1.5rem auto;' : 'margin: 1.5rem auto 1.5rem 0;';
            var dataTableStyle = (forceFullWidthDataTable || effectiveDataTableAlign === 'left') ? 'width:100%;' : 'width:auto;display:table;';
            var previewPadding = docMarginPreset === 'none'
                ? '0mm'
                : (docMarginPreset === 'normal' ? '15mm 15mm 15mm 15mm' : '12mm 12mm 10mm 12mm');
            previewEl.style.padding = previewPadding;

            var getDataColWidthStyle = function (col) {
                if (forceFullWidthDataTable) {
                    var units = colUnitsByKey[col.key] || 1;
                    var pct = (units * 100) / totalUnits;
                    return 'width:' + pct.toFixed(2) + '%;';
                }
                if (col.is_image) {
                    var imageCfg = state.columns.find(function (x) { return x.key === col.key; }) || {};
                    var imageW = (imageCfg.imageWidth || 120) + 'px';
                    return 'width:' + imageW + ';min-width:' + imageW + ';';
                }
                var ch = Math.min(col.max_width_chars || 24, 60);
                return 'width:' + ch + 'ch;';
            };
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
                    var getCountValueWidth = function (groupIndex, valueLabel) {
                        var key = countTableKeys[groupIndex];
                        var fallback = cellW;
                        if (!key) { return fallback; }
                        var c = countTableColors[key];
                        if (!c || !c.row2Widths) { return fallback; }
                        var direct = c.row2Widths[valueLabel];
                        if (direct == null && valueLabel != null) { direct = c.row2Widths[String(valueLabel).toLowerCase()]; }
                        var parsed = parseInt(direct, 10);
                        if (Number.isNaN(parsed)) { return fallback; }
                        return Math.max(6, Math.min(40, parsed));
                    };

                    const cellW = state.countTableCellWidth || 12;

                    countTableHtml = '<table class="tm-export-preview-count-table" style="table-layout:fixed;width:auto;' + countTableMargin + '">';
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
                            var valueW = getCountValueWidth(gi, subLabel);
                            var valuePctW = Math.max(6, Math.floor(valueW * 0.7));
                            if (showPct && isRedundant) {
                                countTableHtml += '<th class="tm-export-preview-count-value-header" style="background-color:' + escapeHtml(bg) + ';width:' + valueW + 'ch;white-space:normal;overflow-wrap:anywhere;word-break:break-word;line-height:1.1;">' + escapeHtml(normalizeExportHeadingText('Cantidad', headersUppercase)) + '</th>';
                                countTableHtml += '<th class="tm-export-preview-count-value-header" style="background-color:' + escapeHtml(bg) + ';width:' + valuePctW + 'ch;font-size:0.75rem;white-space:normal;">%</th>';
                            } else {
                                countTableHtml += '<th class="tm-export-preview-count-value-header" style="background-color:' + escapeHtml(bg) + ';width:' + valueW + 'ch;min-width:' + valueW + 'ch;max-width:' + valueW + 'ch;white-space:normal;overflow-wrap:anywhere;word-break:break-word;line-height:1.1;">' + escapeHtml(normalizeExportHeadingText(subLabel, headersUppercase)) + '</th>';
                                if (showPct) {
                                    countTableHtml += '<th class="tm-export-preview-count-value-header" style="background-color:' + escapeHtml(bg) + ';width:' + valuePctW + 'ch;font-size:0.75rem;white-space:normal;">%</th>';
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
                            var subLabel = v.label !== '' ? v.label : g.label;
                            var valueW = getCountValueWidth(gi, subLabel);
                            var valuePctW = Math.max(6, Math.floor(valueW * 0.7));
                            countTableHtml += '<td class="tm-export-preview-count-value" style="width:' + valueW + 'ch;min-width:' + valueW + 'ch;max-width:' + valueW + 'ch;white-space:normal;overflow-wrap:anywhere;word-break:break-word;">' + escapeHtml(String(v.count)) + '</td>';
                            if (showPct) {
                                var pct = gTotal > 0 ? Math.round((v.count / gTotal) * 100) : 0;
                                countTableHtml += '<td class="tm-export-preview-count-value" style="width:' + valuePctW + 'ch;font-size:0.75rem;color:#666;white-space:normal;">' + pct + '%</td>';
                            }
                        });
                    });
                    countTableHtml += '</tr></tbody></table>';
                }
            }
            const totalColSpan = effectiveColumns.length;
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

            var sumPreviewData = tmExportBuildSumPreviewData(entries, meta, columns, state);

            // Tabla independiente de totales (si está habilitada)
            if (state.includeTotalsTable) {
                html += tmExportRenderTotalsStandalonePreviewTable(
                    sumPreviewData,
                    headersUppercase,
                    state.totalsTableAlign || 'left',
                    state.totalsTableTitle || 'Totales',
                    state.totalsTableAlign || 'left',
                    state.sumTitleFontPx || 14,
                    totalsHeaderFontPx,
                    totalsGroupHeaderFontPx,
                    totalsCellFontPx,
                    !(state.sumTotalsBold === false),
                    state.sumTotalsTextColor || 'var(--clr-primary)',
                    state.groups || []
                );
            }

            // Tabla de Conteo (Resumen)
            html += countTableHtml;

            // Tabla de Sumatoria (agregados y cálculos)
            html += tmExportRenderSumPreviewTable(
                sumPreviewData,
                headersUppercase,
                state.sumTableAlign || 'left',
                state.sumTitle || 'Sumatoria',
                state.sumTitleCase || 'normal',
                state.sumTitleAlign || 'center',
                state.sumTitleFontPx || 14,
                sumHeaderFontPx,
                sumGroupHeaderFontPx,
                sumCellFontPx,
                state.sumGroupColor || 'var(--clr-primary)',
                !!state.sumIncludeTotalsRow,
                !(state.sumTotalsBold === false),
                state.sumTotalsTextColor || 'var(--clr-primary)',
                state.groups || [],
                {
                    showItem: state.sumShowItem !== false,
                    itemLabel: state.sumItemLabel || '#',
                    showDelegation: state.sumShowDelegation !== false,
                    delegationLabel: state.sumDelegationLabel || 'Delegación',
                    showCabecera: state.sumShowCabecera !== false,
                    cabeceraLabel: state.sumCabeceraLabel || 'Cabecera'
                }
            );

            // Tabla de Datos (Desglose)
            var sectionLabelText = normalizeExportHeadingText((state.sectionLabel && String(state.sectionLabel).trim() !== '') ? String(state.sectionLabel) : 'Desglose', headersUppercase);
            var sectionLabelAlign = (state.sectionLabelAlign === 'center' || state.sectionLabelAlign === 'right') ? state.sectionLabelAlign : 'left';
            html += '<p class="tm-export-preview-desglose-label" style="font-weight:600;margin:10px 0 4px 0;text-align:' + sectionLabelAlign + ';">' + escapeHtml(sectionLabelText) + '</p>';
            html += '<table class="tm-export-preview-table" style="table-layout:fixed;border-collapse:collapse;' + dataTableStyle + dataTableMargin + '">';

            // Encabezados (con Grupos si aplica)
            var groupSpans = [];
            effectiveColumns.forEach(function (col) {
                var g = col.group || '';
                if (groupSpans.length > 0 && groupSpans[groupSpans.length - 1].label === g) {
                    groupSpans[groupSpans.length - 1].span++;
                } else {
                    groupSpans.push({ label: g, span: 1 });
                }
            });

            var hasAnyGroup = groupSpans.some(function (gs) { return gs.label !== ''; });
            if (hasAnyGroup) {
                var groupColorMap = buildGroupColorMap(state.groups || []);
                html += '<tr class="tm-export-preview-row tm-export-preview-group-header">';
                var gColIdx = 0;
                groupSpans.forEach(function (gs) {
                    var groupColor = gs.label ? (groupColorMap[gs.label] || '#64748b') : 'transparent';
                    var style = gs.label ? ('background-color:' + escapeHtml(groupColor) + '; color:white; border:1px solid #475569; font-weight:bold; border-bottom:none; font-size:' + recordsGroupHeaderFontPx + 'px;') : 'border:none;';
                    if (forceFullWidthDataTable) {
                        var gPct = 0;
                        for (var gi = 0; gi < gs.span; gi++) {
                            var gcol = effectiveColumns[gColIdx + gi];
                            if (gcol && gcol.key) { gPct += (colPercentByKey[gcol.key] || 0); }
                        }
                        style += 'width:' + gPct.toFixed(2) + '%;';
                    }
                    gColIdx += gs.span;
                    html += '<th class="tm-export-preview-cell" colspan="' + gs.span + '" style="' + style + '">' + (gs.label ? escapeHtmlWithBreaks(normalizeExportHeadingText(gs.label, headersUppercase)) : '') + '</th>';
                });
                html += '</tr>';
            }

            html += '<tr class="tm-export-preview-row tm-export-preview-header">';
            effectiveColumns.forEach(function (col) {
                const color = colorMap[col.key] || '#861e34';
                var widthStyle = getDataColWidthStyle(col);
                if (col.is_image) {
                    const c = state.columns.find(function (x) { return x.key === col.key; }) || {};
                    const h = (c.imageHeight || 80) + 'px';
                    html += '<th class="tm-export-preview-cell tm-export-preview-header-cell tm-export-preview-image-cell" style="background-color:' + escapeHtml(color) + ';' + widthStyle + 'height:' + h + ';min-height:' + h + ';font-size:' + recordsHeaderFontPx + 'px;"><span class="tm-export-preview-image-placeholder">' + escapeHtmlWithBreaks(normalizeExportHeadingText('Imagen', headersUppercase)) + '</span></th>';
                } else {
                    html += '<th class="tm-export-preview-cell tm-export-preview-header-cell" style="background-color:' + escapeHtml(color) + ';' + widthStyle + 'font-size:' + recordsHeaderFontPx + 'px;">' + escapeHtmlWithBreaks(normalizeExportHeadingText(col.label, headersUppercase)) + '</th>';
                }
            });
            html += '</tr>';

            if (Array.isArray(entries) && entries.length > 0) {
                var itemNum = 1;
                entries.forEach(function (entry) {
                    var mrLabel = (meta[entry.microrregion_id] && meta[entry.microrregion_id].label) ? meta[entry.microrregion_id].label : 'Sin microrregión';
                    html += '<tr class="tm-export-preview-row tm-export-preview-data">';
                    effectiveColumns.forEach(function (col) {
                        const cellColor = '#f5f5f5';
                        var widthStyle = getDataColWidthStyle(col);
                        if (col.is_image) {
                            const c = state.columns.find(function (x) { return x.key === col.key; }) || {};
                            const h = (c.imageHeight || 80) + 'px';
                            var imageBold = col.content_bold ? 'font-weight:700;' : '';
                            html += '<td class="tm-export-preview-cell tm-export-preview-data-cell tm-export-preview-image-cell" style="' + widthStyle + 'height:' + h + ';min-height:' + h + ';background:#f0f0f0;' + imageBold + '"><span class="tm-export-preview-image-placeholder">-</span></td>';
                        } else {
                            var val = '';
                            var valueHtml = '';
                            if (col.key === 'item') {
                                val = String(itemNum++);
                                valueHtml = escapeHtml(val);
                            } else if (col.key === 'microrregion') {
                                val = mrLabel;
                                valueHtml = escapeHtml(val);
                            } else if (col.key === 'delegacion_numero') {
                                val = (meta[entry.microrregion_id] && meta[entry.microrregion_id].number) ? String(meta[entry.microrregion_id].number) : '';
                                valueHtml = escapeHtml(val);
                            } else if (col.key === 'cabecera_microrregion') {
                                val = (meta[entry.microrregion_id] && meta[entry.microrregion_id].cabecera) ? String(meta[entry.microrregion_id].cabecera) : '';
                                valueHtml = escapeHtml(val);
                            } else if (String(col.key || '').indexOf('__calc_') === 0) {
                                val = tmExportBuildCalculatedTextForEntry(entry, col._calc_cfg || null, effectiveColumns, headersUppercase);
                                valueHtml = escapeHtmlWithBreaks(val);
                            } else {
                                var rawVal = entry.data && entry.data[col.key];
                                rawVal = tmExportApplyEmptyFillForColumn(col, rawVal);
                                val = formatPreviewCellValue(rawVal);
                                valueHtml = escapeHtml(val);
                            }
                            var weightStyle = col.content_bold ? 'font-weight:700;' : '';
                            html += '<td class="tm-export-preview-cell tm-export-preview-data-cell" style="' + widthStyle + 'background:' + escapeHtml(cellColor) + ';font-size:' + recordsCellFontPx + 'px;' + weightStyle + '">' + valueHtml + '</td>';
                        }
                    });
                    html += '</tr>';
                });
            } else {
                html += '<tr class="tm-export-preview-row tm-export-preview-data">';
                effectiveColumns.forEach(function (col) {
                    const cellColor = '#f5f5f5';
                    var widthStyle = getDataColWidthStyle(col);
                    if (col.is_image) {
                        const c = state.columns.find(function (x) { return x.key === col.key; }) || {};
                        const h = (c.imageHeight || 80) + 'px';
                        var emptyImageBold = col.content_bold ? 'font-weight:700;' : '';
                        html += '<td class="tm-export-preview-cell tm-export-preview-data-cell tm-export-preview-image-cell" data-key="' + escapeHtml(col.key) + '" style="' + widthStyle + 'height:' + h + ';min-height:' + h + ';background:#f0f0f0;' + emptyImageBold + '"><span class="tm-export-preview-image-placeholder">-</span></td>';
                    } else if (String(col.key || '').indexOf('__calc_') === 0) {
                        var emptyCalcBold = col.content_bold ? 'font-weight:700;' : '';
                        html += '<td class="tm-export-preview-cell tm-export-preview-data-cell" style="' + widthStyle + 'background:' + escapeHtml(cellColor) + ';font-size:' + recordsCellFontPx + 'px;' + emptyCalcBold + '">OK</td>';
                    } else {
                        const val = (savedRow && savedRow[col.key] !== undefined) ? escapeHtml(savedRow[col.key]) : '';
                        var emptyTextBold = col.content_bold ? 'font-weight:700;' : '';
                        html += '<td class="tm-export-preview-cell tm-export-preview-data-cell" data-key="' + escapeHtml(col.key) + '" contenteditable="true" style="' + widthStyle + 'background:' + escapeHtml(cellColor) + ';font-size:' + recordsCellFontPx + 'px;' + emptyTextBold + '" data-placeholder="Ejemplo">' + val + '</td>';
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
                col.fill_empty_mode = String(item.dataset.fillEmptyMode || 'none');
                col.fill_empty_value = String(item.dataset.fillEmptyValue || '');
                col.content_bold = String(item.dataset.contentBold || '0') === '1';

                if (!col.is_image) {
                    const widthInput = item.querySelector('.tm-export-col-width-input');
                    if (widthInput) {
                        var raw = parseInt(widthInput.value, 10);
                        if (!Number.isNaN(raw)) {
                            col.max_width_chars = Math.max(2, Math.min(raw, 60));
                        }
                    }
                }
                col.color = String(item.dataset.headerColor || 'var(--clr-primary)');

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
                const headerFontEl = document.getElementById('tmExportHeaderFontSize');
            const titleFontEl = document.getElementById('tmExportTitleFontSize');
                    const docMarginPresetEl = document.getElementById('tmExportDocMarginPreset');
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
            const includeCalculatedColumnsEl = document.getElementById('tmExportIncludeCalculatedColumns');
            const calculatedColumnsWrapEl = document.getElementById('tmExportCalculatedColumnsWrap');
            const addCalculatedColumnBtn = document.getElementById('tmExportAddCalculatedColumnBtn');
            const calculatedColumnsListEl = document.getElementById('tmExportCalculatedColumnsList');
            const includeSumTableEl = document.getElementById('tmExportIncludeSumTable');
            const sumWrapEl = document.getElementById('tmExportSumWrap');
            const sumGroupByEl = document.getElementById('tmExportSumGroupBy');
            const sumTitleEl = document.getElementById('tmExportSumTitle');
            const sumTitleUppercaseEl = document.getElementById('tmExportSumTitleUppercase');
            const sumTitleFontEl = document.getElementById('tmExportSumTitleFontSize');
            const sumShowItemColEl = document.getElementById('tmExportSumShowItemCol');
            const sumItemLabelEl = document.getElementById('tmExportSumItemLabel');
            const sumShowDelegacionColEl = document.getElementById('tmExportSumShowDelegacionCol');
            const sumDelegacionLabelEl = document.getElementById('tmExportSumDelegacionLabel');
            const sumShowCabeceraColEl = document.getElementById('tmExportSumShowCabeceraCol');
            const sumCabeceraLabelEl = document.getElementById('tmExportSumCabeceraLabel');
            const sectionLabelEl = document.getElementById('tmExportSectionLabel');
            const sumGroupColorWrapEl = document.getElementById('tmExportSumGroupColorWrap');
            const sumGroupColorTriggerEl = document.getElementById('tmExportSumGroupColorTrigger');
            const sumGroupColorMenuEl = document.getElementById('tmExportSumGroupColorMenu');
            const sumIncludeTotalsRowEl = document.getElementById('tmExportSumIncludeTotalsRow');
            const sumCellFontEl = document.getElementById('tmExportSumCellFontSize');
            const sumHeaderFontEl = document.getElementById('tmExportSumHeaderFontSize');
            const recordsGroupHeaderFontEl = document.getElementById('tmExportRecordsGroupHeaderFontSize');
            const sumGroupHeaderFontEl = document.getElementById('tmExportSumGroupHeaderFontSize');
            const includeTotalsTableEl = document.getElementById('tmExportIncludeTotalsTable');
            const totalsTableWrapEl = document.getElementById('tmExportTotalsTableWrap');
            const totalsTableTitleEl = document.getElementById('tmExportTotalsTableTitle');
            const totalsCellFontEl = document.getElementById('tmExportTotalsCellFontSize');
            const totalsHeaderFontEl = document.getElementById('tmExportTotalsHeaderFontSize');
            const totalsGroupHeaderFontEl = document.getElementById('tmExportTotalsGroupHeaderFontSize');
            const sumTotalsBoldEl = document.getElementById('tmExportSumTotalsBold');
            const sumTotalsColorWrapEl = document.getElementById('tmExportSumTotalsColorWrap');
            const sumTotalsColorTriggerEl = document.getElementById('tmExportSumTotalsColorTrigger');
            const sumTotalsColorMenuEl = document.getElementById('tmExportSumTotalsColorMenu');
            const addSumMetricBtn = document.getElementById('tmExportAddSumMetricBtn');
            const addSumFormulaBtn = document.getElementById('tmExportAddSumFormulaBtn');

            if (sumGroupColorMenuEl) {
                sumGroupColorMenuEl.innerHTML = TEMPLATE_COLORS.map(function (c, i) {
                    return '<button type="button" class="tm-export-color-option' + (i === 0 ? ' is-active' : '') + '" data-color="' + escapeHtml(c.value) + '">' +
                        '<span class="tm-export-color-swatch" style="background-color:' + escapeHtml(c.value) + '"></span>' +
                        '<span class="tm-export-color-name">' + escapeHtml(c.name) + '</span></button>';
                }).join('');
            }
            if (sumTotalsColorMenuEl) {
                sumTotalsColorMenuEl.innerHTML = TEMPLATE_COLORS.map(function (c, i) {
                    return '<button type="button" class="tm-export-color-option' + (i === 0 ? ' is-active' : '') + '" data-color="' + escapeHtml(c.value) + '">' +
                        '<span class="tm-export-color-swatch" style="background-color:' + escapeHtml(c.value) + '"></span>' +
                        '<span class="tm-export-color-name">' + escapeHtml(c.name) + '</span></button>';
                }).join('');
            }
            var setSumGroupColor = function (color) {
                var c = String(color || 'var(--clr-primary)');
                if (sumGroupColorTriggerEl) {
                    sumGroupColorTriggerEl.setAttribute('data-color', c);
                    var sw = sumGroupColorTriggerEl.querySelector('.tm-export-color-swatch');
                    if (sw) { sw.style.backgroundColor = c; }
                }
                if (sumGroupColorMenuEl) {
                    sumGroupColorMenuEl.querySelectorAll('.tm-export-color-option').forEach(function (opt) {
                        opt.classList.toggle('is-active', (opt.getAttribute('data-color') || '') === c);
                    });
                }
            };
            var setSumTotalsColor = function (color) {
                var c = String(color || 'var(--clr-primary)');
                if (sumTotalsColorTriggerEl) {
                    sumTotalsColorTriggerEl.setAttribute('data-color', c);
                    var sw = sumTotalsColorTriggerEl.querySelector('.tm-export-color-swatch');
                    if (sw) { sw.style.backgroundColor = c; }
                }
                if (sumTotalsColorMenuEl) {
                    sumTotalsColorMenuEl.querySelectorAll('.tm-export-color-option').forEach(function (opt) {
                        opt.classList.toggle('is-active', (opt.getAttribute('data-color') || '') === c);
                    });
                }
            };
            if (sumGroupColorWrapEl && !sumGroupColorWrapEl.dataset.bound) {
                sumGroupColorWrapEl.dataset.bound = '1';
                sumGroupColorWrapEl.addEventListener('click', function (e) {
                    var trigger = e.target.closest('#tmExportSumGroupColorTrigger');
                    if (trigger) {
                        var menu = document.getElementById('tmExportSumGroupColorMenu');
                        if (menu instanceof HTMLElement) {
                            var isOpen = !menu.hidden;
                            Array.from(personalizeModal.querySelectorAll('.tm-export-color-menu')).forEach(function (m) { m.hidden = true; });
                            trigger.setAttribute('aria-expanded', String(!isOpen));
                            menu.hidden = isOpen;
                        }
                        return;
                    }
                    var option = e.target.closest('#tmExportSumGroupColorMenu .tm-export-color-option');
                    if (option) {
                        setSumGroupColor(option.getAttribute('data-color') || 'var(--clr-primary)');
                        if (sumGroupColorMenuEl) { sumGroupColorMenuEl.hidden = true; }
                        buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl, undefined, personalizeModal._previewEntries, personalizeModal._previewMicrorregionMeta);
                    }
                });
            }
            if (sumTotalsColorWrapEl && !sumTotalsColorWrapEl.dataset.bound) {
                sumTotalsColorWrapEl.dataset.bound = '1';
                sumTotalsColorWrapEl.addEventListener('click', function (e) {
                    var trigger = e.target.closest('#tmExportSumTotalsColorTrigger');
                    if (trigger) {
                        var menu = document.getElementById('tmExportSumTotalsColorMenu');
                        if (menu instanceof HTMLElement) {
                            var isOpen = !menu.hidden;
                            Array.from(personalizeModal.querySelectorAll('.tm-export-color-menu')).forEach(function (m) { m.hidden = true; });
                            trigger.setAttribute('aria-expanded', String(!isOpen));
                            menu.hidden = isOpen;
                        }
                        return;
                    }
                    var option = e.target.closest('#tmExportSumTotalsColorMenu .tm-export-color-option');
                    if (option) {
                        setSumTotalsColor(option.getAttribute('data-color') || 'var(--clr-primary)');
                        if (sumTotalsColorMenuEl) { sumTotalsColorMenuEl.hidden = true; }
                        buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl, undefined, personalizeModal._previewEntries, personalizeModal._previewMicrorregionMeta);
                    }
                });
            }

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
                        return ['item', 'microrregion', 'delegacion_numero', 'cabecera_microrregion'].indexOf(k) === -1 && !c.is_image;
                    });
                    if (personalizeModal) { personalizeModal._countableColumns = countableColumns; }
                    if (personalizeModal) {
                        personalizeModal._sumMetrics = [];
                        personalizeModal._sumFormulas = [];
                    }
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

                    var refreshSumPreview = function () {
                        buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl, undefined, personalizeModal._previewEntries, personalizeModal._previewMicrorregionMeta);
                    };

                    tmExportEnsureCalculatedColumnsState(countableColumns, null);
                    tmExportRenderCalculatedColumnsConfigurator(countableColumns);
                    if (includeCalculatedColumnsEl && calculatedColumnsWrapEl) {
                        calculatedColumnsWrapEl.hidden = !includeCalculatedColumnsEl.checked;
                        includeCalculatedColumnsEl.onchange = function () {
                            calculatedColumnsWrapEl.hidden = !includeCalculatedColumnsEl.checked;
                            refreshSumPreview();
                        };
                    }
                    if (addCalculatedColumnBtn) {
                        addCalculatedColumnBtn.onclick = function () {
                            if (!Array.isArray(personalizeModal._calculatedColumns)) {
                                personalizeModal._calculatedColumns = [];
                            }
                            personalizeModal._calculatedColumns.push({
                                id: 'calc_' + Date.now() + '_' + Math.floor(Math.random() * 1000),
                                label: 'Calculada ' + (personalizeModal._calculatedColumns.length + 1),
                                group: '',
                                operation: 'add',
                                baseField: countableColumns.length ? String(countableColumns[0].key || '') : '',
                                afterKey: '',
                                operationFields: countableColumns.length ? [String(countableColumns[0].key || '')] : [],
                                cellColor: 'var(--clr-secondary)',
                                cellSizeCh: 18,
                                cellBold: false
                            });
                            tmExportRenderCalculatedColumnsConfigurator(countableColumns);
                            refreshSumPreview();
                        };
                    }
                    if (calculatedColumnsListEl) {
                        var syncCalculatedListToState = function (triggerEvent) {
                            var next = [];
                            calculatedColumnsListEl.querySelectorAll('[data-calc-col-id]').forEach(function (row) {
                                var id = String(row.getAttribute('data-calc-col-id') || '');
                                if (!id) { return; }
                                var labelEl = row.querySelector('[data-calc-label]');
                                var opEl = row.querySelector('[data-calc-operation]');
                                var groupEl = row.querySelector('[data-calc-group]');
                                var baseEl = row.querySelector('[data-calc-base]');
                                var opFieldsEl = row.querySelector('[data-calc-op-fields]');
                                var colorTrigger = row.querySelector('[data-calc-color-trigger]');
                                var sizeEl = row.querySelector('[data-calc-size]');
                                var boldBtn = row.querySelector('[data-calc-bold]');
                                var existingCalc = Array.isArray(personalizeModal._calculatedColumns)
                                    ? personalizeModal._calculatedColumns.find(function (x) { return String(x && x.id || '') === id; })
                                    : null;
                                var opFields = [];
                                if (opFieldsEl) {
                                    opFields = Array.from(opFieldsEl.selectedOptions || []).map(function (o) { return String(o.value || ''); }).filter(Boolean);
                                }
                                var op = opEl ? String(opEl.value || 'add') : 'add';
                                if (['add', 'subtract', 'multiply', 'percent'].indexOf(op) === -1) { op = 'add'; }
                                var size = parseInt(String(sizeEl ? sizeEl.value : '18'), 10);
                                if (Number.isNaN(size)) { size = 18; }
                                next.push({
                                    id: id,
                                    label: labelEl && String(labelEl.value || '').trim() !== '' ? String(labelEl.value).trim() : 'Calculada',
                                    group: groupEl ? String(groupEl.value || '').trim() : '',
                                    operation: op,
                                    baseField: baseEl ? String(baseEl.value || '') : '',
                                    afterKey: existingCalc ? String(existingCalc.afterKey || '') : '',
                                    operationFields: opFields,
                                    cellColor: (colorTrigger && colorTrigger.getAttribute('data-color')) ? String(colorTrigger.getAttribute('data-color')) : 'var(--clr-secondary)',
                                    cellSizeCh: Math.max(8, Math.min(40, size)),
                                    cellBold: !!(boldBtn && boldBtn.classList.contains('is-active'))
                                });
                            });
                            personalizeModal._calculatedColumns = next;
                            if (triggerEvent) { refreshSumPreview(); }
                        };
                        calculatedColumnsListEl.oninput = function () { syncCalculatedListToState(true); };
                        calculatedColumnsListEl.onchange = function () { syncCalculatedListToState(true); };
                        calculatedColumnsListEl.onclick = function (e) {
                            var upBtn = e.target.closest('[data-move-calc-up]');
                            if (upBtn) {
                                var upRow = upBtn.closest('[data-calc-col-id]');
                                if (!upRow) { return; }
                                var upId = String(upRow.getAttribute('data-calc-col-id') || '');
                                if (tmExportMoveCalculatedColumn(upId, 'up')) {
                                    tmExportRenderCalculatedColumnsConfigurator(countableColumns);
                                    refreshSumPreview();
                                }
                                return;
                            }
                            var downBtn = e.target.closest('[data-move-calc-down]');
                            if (downBtn) {
                                var downRow = downBtn.closest('[data-calc-col-id]');
                                if (!downRow) { return; }
                                var downId = String(downRow.getAttribute('data-calc-col-id') || '');
                                if (tmExportMoveCalculatedColumn(downId, 'down')) {
                                    tmExportRenderCalculatedColumnsConfigurator(countableColumns);
                                    refreshSumPreview();
                                }
                                return;
                            }
                            var colorTrigger = e.target.closest('[data-calc-color-trigger]');
                            if (colorTrigger) {
                                var menu = colorTrigger.nextElementSibling;
                                if (menu instanceof HTMLElement) {
                                    var isOpen = !menu.hidden;
                                    calculatedColumnsListEl.querySelectorAll('.tm-export-color-menu').forEach(function (m) { m.hidden = true; });
                                    colorTrigger.setAttribute('aria-expanded', String(!isOpen));
                                    menu.hidden = isOpen;
                                }
                                return;
                            }
                            var colorOption = e.target.closest('.tm-export-color-option');
                            if (colorOption) {
                                var color = colorOption.getAttribute('data-color') || 'var(--clr-secondary)';
                                var menu = colorOption.closest('.tm-export-color-menu');
                                var wrapColor = menu ? menu.closest('.tm-export-col-color') : null;
                                var tr = wrapColor ? wrapColor.querySelector('[data-calc-color-trigger]') : null;
                                if (wrapColor && tr) {
                                    wrapColor.querySelectorAll('.tm-export-color-option').forEach(function (opt) { opt.classList.remove('is-active'); });
                                    colorOption.classList.add('is-active');
                                    tr.setAttribute('data-color', color);
                                    var swatch = tr.querySelector('.tm-export-color-swatch');
                                    if (swatch instanceof HTMLElement) { swatch.style.backgroundColor = color; }
                                }
                                if (menu) { menu.hidden = true; }
                                syncCalculatedListToState(true);
                                return;
                            }
                            var boldBtn = e.target.closest('[data-calc-bold]');
                            if (boldBtn) {
                                boldBtn.classList.toggle('is-active');
                                syncCalculatedListToState(true);
                                return;
                            }
                            var removeBtn = e.target.closest('[data-remove-calc-col]');
                            if (!removeBtn) { return; }
                            var row = removeBtn.closest('[data-calc-col-id]');
                            if (!row) { return; }
                            var id = String(row.getAttribute('data-calc-col-id') || '');
                            personalizeModal._calculatedColumns = (personalizeModal._calculatedColumns || []).filter(function (x) { return String(x.id || '') !== id; });
                            tmExportRenderCalculatedColumnsConfigurator(countableColumns);
                            refreshSumPreview();
                        };
                    }
                    tmExportEnsureSumState(countableColumns, null);
                    tmExportRenderSumConfigurator(countableColumns);

                    if (includeSumTableEl && sumWrapEl) {
                        sumWrapEl.hidden = !includeSumTableEl.checked;
                        if (totalsTableWrapEl && includeTotalsTableEl) {
                            totalsTableWrapEl.hidden = !includeSumTableEl.checked || !includeTotalsTableEl.checked;
                        }
                        includeSumTableEl.onchange = function () {
                            sumWrapEl.hidden = !includeSumTableEl.checked;
                            if (!includeSumTableEl.checked && includeTotalsTableEl) {
                                includeTotalsTableEl.checked = false;
                            }
                            if (totalsTableWrapEl && includeTotalsTableEl) {
                                totalsTableWrapEl.hidden = !includeSumTableEl.checked || !includeTotalsTableEl.checked;
                            }
                            refreshSumPreview();
                        };
                    }
                    if (sumGroupByEl) {
                        sumGroupByEl.onchange = refreshSumPreview;
                    }
                    personalizeModal.querySelectorAll('#tmExportSumAlignGroup .tm-export-align-btn').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            personalizeModal.querySelectorAll('#tmExportSumAlignGroup .tm-export-align-btn').forEach(function (b) { b.classList.remove('is-active'); });
                            btn.classList.add('is-active');
                            refreshSumPreview();
                        });
                    });
                    if (addSumMetricBtn) {
                        addSumMetricBtn.onclick = function () {
                            if (!Array.isArray(personalizeModal._sumMetrics)) { personalizeModal._sumMetrics = []; }
                            var firstField = (countableColumns[0] && countableColumns[0].key) ? String(countableColumns[0].key) : '';
                            personalizeModal._sumMetrics.push({
                                id: 'm' + Date.now() + '_' + Math.floor(Math.random() * 1000),
                                label: 'Métrica ' + (personalizeModal._sumMetrics.length + 1),
                                group: '',
                                field_key: firstField,
                                agg: 'sum',
                                match_value: '',
                                text_color: 'var(--clr-primary)',
                                include_total: true,
                                font_size: 12,
                                sort_order: (tmExportGetSumCombinedSorted().length + 1)
                            });
                            tmExportRenderSumConfigurator(countableColumns);
                            refreshSumPreview();
                        };
                    }
                    if (addSumFormulaBtn) {
                        addSumFormulaBtn.onclick = function () {
                            if (!Array.isArray(personalizeModal._sumFormulas)) { personalizeModal._sumFormulas = []; }
                            var defaultMetricId = (personalizeModal._sumMetrics && personalizeModal._sumMetrics[0]) ? String(personalizeModal._sumMetrics[0].id) : '';
                            personalizeModal._sumFormulas.push({
                                id: 'f' + Date.now() + '_' + Math.floor(Math.random() * 1000),
                                label: 'Cálculo ' + (personalizeModal._sumFormulas.length + 1),
                                group: '',
                                op: 'add',
                                metric_ids: defaultMetricId ? [defaultMetricId] : [],
                                base_metric_id: defaultMetricId,
                                text_color: 'var(--clr-primary)',
                                include_total: true,
                                font_size: 12,
                                sort_order: (tmExportGetSumCombinedSorted().length + 1)
                            });
                            tmExportRenderSumConfigurator(countableColumns);
                            refreshSumPreview();
                        };
                    }
                    var sumMetricsList = document.getElementById('tmExportSumMetricsList');
                    var sumFormulasList = document.getElementById('tmExportSumFormulasList');
                    if (sumMetricsList) {
                        var handleMetricEdit = function (e) {
                            var row = e.target.closest('[data-sum-metric-id]');
                            if (!row) { return; }
                            var id = row.getAttribute('data-sum-metric-id');
                            var m = (personalizeModal._sumMetrics || []).find(function (x) { return x.id === id; });
                            if (!m) { return; }
                            m.label = (row.querySelector('[data-sum-metric-label]') || {}).value || m.label;
                            m.group = (row.querySelector('[data-sum-metric-group]') || {}).value || m.group || '';
                            m.field_key = (row.querySelector('[data-sum-metric-field]') || {}).value || m.field_key;
                            m.agg = (row.querySelector('[data-sum-metric-agg]') || {}).value || m.agg;
                            m.match_value = (row.querySelector('[data-sum-metric-match]') || {}).value || '';
                            var mColorTrigger = row.querySelector('[data-sum-metric-color-trigger]');
                            m.text_color = (mColorTrigger && mColorTrigger.getAttribute('data-color')) ? mColorTrigger.getAttribute('data-color') : (m.text_color || 'var(--clr-primary)');
                            m.include_total = !!((row.querySelector('[data-sum-metric-include-total]') || {}).checked);
                            var mFont = parseInt((row.querySelector('[data-sum-metric-size]') || {}).value || String(m.font_size || '12'), 10);
                            m.font_size = Number.isNaN(mFont) ? 12 : Math.max(9, Math.min(28, mFont));
                            var mOrder = parseInt((row.querySelector('[data-sum-metric-order]') || {}).value || String(m.sort_order || '0'), 10);
                            m.sort_order = Number.isNaN(mOrder) ? (m.sort_order || 0) : mOrder;
                            var matchEl = row.querySelector('[data-sum-metric-match]');
                            if (matchEl) { matchEl.hidden = m.agg !== 'count_equals'; }
                            if (e.target && (e.target.matches('[data-sum-metric-field]') || e.target.matches('[data-sum-metric-agg]'))) {
                                tmExportRenderSumConfigurator(countableColumns);
                            }
                            refreshSumPreview();
                        };
                        sumMetricsList.oninput = handleMetricEdit;
                        sumMetricsList.onchange = handleMetricEdit;
                        sumMetricsList.onclick = function (e) {
                            var colorTrigger = e.target.closest('[data-sum-metric-color-trigger]');
                            if (colorTrigger) {
                                var menu = colorTrigger.nextElementSibling;
                                if (menu instanceof HTMLElement) {
                                    var isOpen = !menu.hidden;
                                    sumMetricsList.querySelectorAll('.tm-export-color-menu').forEach(function (m) { m.hidden = true; });
                                    colorTrigger.setAttribute('aria-expanded', String(!isOpen));
                                    menu.hidden = isOpen;
                                }
                                return;
                            }
                            var colorOption = e.target.closest('.tm-export-color-option');
                            if (colorOption) {
                                var color = colorOption.getAttribute('data-color') || 'var(--clr-primary)';
                                var menu = colorOption.closest('.tm-export-color-menu');
                                var cell = menu ? menu.closest('.tm-export-col-color') : null;
                                var trigger = cell ? cell.querySelector('[data-sum-metric-color-trigger]') : null;
                                if (trigger) {
                                    cell.querySelectorAll('.tm-export-color-option').forEach(function (opt) { opt.classList.remove('is-active'); });
                                    colorOption.classList.add('is-active');
                                    trigger.setAttribute('data-color', color);
                                    var swatch = trigger.querySelector('.tm-export-color-swatch');
                                    if (swatch) { swatch.style.backgroundColor = color; }
                                }
                                if (menu) { menu.hidden = true; }
                                handleMetricEdit(e);
                                return;
                            }
                            var upBtn = e.target.closest('[data-move-sum-metric-up]');
                            if (upBtn) {
                                var upRow = upBtn.closest('[data-sum-metric-id]');
                                if (!upRow) { return; }
                                var upId = upRow.getAttribute('data-sum-metric-id');
                                if (tmExportMoveSumColumn('metric', upId, 'up')) {
                                    tmExportRenderSumConfigurator(countableColumns);
                                    refreshSumPreview();
                                }
                                return;
                            }
                            var downBtn = e.target.closest('[data-move-sum-metric-down]');
                            if (downBtn) {
                                var downRow = downBtn.closest('[data-sum-metric-id]');
                                if (!downRow) { return; }
                                var downId = downRow.getAttribute('data-sum-metric-id');
                                if (tmExportMoveSumColumn('metric', downId, 'down')) {
                                    tmExportRenderSumConfigurator(countableColumns);
                                    refreshSumPreview();
                                }
                                return;
                            }
                            var btn = e.target.closest('[data-remove-sum-metric]');
                            if (!btn) { return; }
                            var row = btn.closest('[data-sum-metric-id]');
                            if (!row) { return; }
                            var id = row.getAttribute('data-sum-metric-id');
                            personalizeModal._sumMetrics = (personalizeModal._sumMetrics || []).filter(function (x) { return x.id !== id; });
                            personalizeModal._sumFormulas = (personalizeModal._sumFormulas || []).map(function (f) {
                                f.metric_ids = (f.metric_ids || []).filter(function (mId) { return mId !== id; });
                                if ((f.base_metric_id || '') === id) {
                                    f.base_metric_id = '';
                                }
                                return f;
                            });
                            tmExportRenderSumConfigurator(countableColumns);
                            refreshSumPreview();
                        };
                    }
                    if (sumFormulasList) {
                        var handleFormulaEdit = function (e) {
                            var row = e.target.closest('[data-sum-formula-id]');
                            if (!row) { return; }
                            var id = row.getAttribute('data-sum-formula-id');
                            var f = (personalizeModal._sumFormulas || []).find(function (x) { return x.id === id; });
                            if (!f) { return; }
                            f.label = (row.querySelector('[data-sum-formula-label]') || {}).value || f.label;
                            f.group = (row.querySelector('[data-sum-formula-group]') || {}).value || f.group || '';
                            f.op = (row.querySelector('[data-sum-formula-op]') || {}).value || f.op;
                            var mSel = row.querySelector('[data-sum-formula-metrics]');
                            if (mSel) {
                                f.metric_ids = Array.from(mSel.selectedOptions || []).map(function (o) { return o.value; }).filter(Boolean);
                            }
                            var baseSel = row.querySelector('[data-sum-formula-base]');
                            if (baseSel) {
                                baseSel.hidden = f.op !== 'percent';
                                f.base_metric_id = baseSel.value || f.base_metric_id || '';
                            }
                            var fColorTrigger = row.querySelector('[data-sum-formula-color-trigger]');
                            f.text_color = (fColorTrigger && fColorTrigger.getAttribute('data-color')) ? fColorTrigger.getAttribute('data-color') : (f.text_color || 'var(--clr-primary)');
                            f.include_total = !!((row.querySelector('[data-sum-formula-include-total]') || {}).checked);
                            var fFont = parseInt((row.querySelector('[data-sum-formula-size]') || {}).value || String(f.font_size || '12'), 10);
                            f.font_size = Number.isNaN(fFont) ? 12 : Math.max(9, Math.min(28, fFont));
                            var fOrder = parseInt((row.querySelector('[data-sum-formula-order]') || {}).value || String(f.sort_order || '0'), 10);
                            f.sort_order = Number.isNaN(fOrder) ? (f.sort_order || 0) : fOrder;
                            refreshSumPreview();
                        };
                        sumFormulasList.oninput = handleFormulaEdit;
                        sumFormulasList.onchange = handleFormulaEdit;
                        sumFormulasList.onclick = function (e) {
                            var colorTrigger = e.target.closest('[data-sum-formula-color-trigger]');
                            if (colorTrigger) {
                                var menu = colorTrigger.nextElementSibling;
                                if (menu instanceof HTMLElement) {
                                    var isOpen = !menu.hidden;
                                    sumFormulasList.querySelectorAll('.tm-export-color-menu').forEach(function (m) { m.hidden = true; });
                                    colorTrigger.setAttribute('aria-expanded', String(!isOpen));
                                    menu.hidden = isOpen;
                                }
                                return;
                            }
                            var colorOption = e.target.closest('.tm-export-color-option');
                            if (colorOption) {
                                var color = colorOption.getAttribute('data-color') || 'var(--clr-primary)';
                                var menu = colorOption.closest('.tm-export-color-menu');
                                var cell = menu ? menu.closest('.tm-export-col-color') : null;
                                var trigger = cell ? cell.querySelector('[data-sum-formula-color-trigger]') : null;
                                if (trigger) {
                                    cell.querySelectorAll('.tm-export-color-option').forEach(function (opt) { opt.classList.remove('is-active'); });
                                    colorOption.classList.add('is-active');
                                    trigger.setAttribute('data-color', color);
                                    var swatch = trigger.querySelector('.tm-export-color-swatch');
                                    if (swatch) { swatch.style.backgroundColor = color; }
                                }
                                if (menu) { menu.hidden = true; }
                                handleFormulaEdit(e);
                                return;
                            }
                            var upBtn = e.target.closest('[data-move-sum-formula-up]');
                            if (upBtn) {
                                var upRow = upBtn.closest('[data-sum-formula-id]');
                                if (!upRow) { return; }
                                var upId = upRow.getAttribute('data-sum-formula-id');
                                if (tmExportMoveSumColumn('formula', upId, 'up')) {
                                    tmExportRenderSumConfigurator(countableColumns);
                                    refreshSumPreview();
                                }
                                return;
                            }
                            var downBtn = e.target.closest('[data-move-sum-formula-down]');
                            if (downBtn) {
                                var downRow = downBtn.closest('[data-sum-formula-id]');
                                if (!downRow) { return; }
                                var downId = downRow.getAttribute('data-sum-formula-id');
                                if (tmExportMoveSumColumn('formula', downId, 'down')) {
                                    tmExportRenderSumConfigurator(countableColumns);
                                    refreshSumPreview();
                                }
                                return;
                            }
                            var btn = e.target.closest('[data-remove-sum-formula]');
                            if (!btn) { return; }
                            var row = btn.closest('[data-sum-formula-id]');
                            if (!row) { return; }
                            var id = row.getAttribute('data-sum-formula-id');
                            personalizeModal._sumFormulas = (personalizeModal._sumFormulas || []).filter(function (x) { return x.id !== id; });
                            tmExportRenderSumConfigurator(countableColumns);
                            refreshSumPreview();
                        };
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
                            if (parsedDraft && parsedDraft.v === 1 && parsedDraft.cfg && typeof parsedDraft.cfg === 'object') {
                                draftCfg = parsedDraft.cfg;
                            }
                        }
                    } catch (eDraft) {}

                    if (personalizeModal) {
                        personalizeModal._exportGroups = normalizeExportGroups([]);
                    }

                    if (draftCfg) {
                        tmExportEnsureCalculatedColumnsState(countableColumns, draftCfg);
                        tmExportRenderCalculatedColumnsConfigurator(countableColumns);
                        tmExportEnsureSumState(countableColumns, draftCfg);
                        tmExportRenderSumConfigurator(countableColumns);
                        if (titleEl) { titleEl.value = draftCfg.title != null ? String(draftCfg.title) : (data.title || ''); }
                        if (titleUppercaseEl) { titleUppercaseEl.checked = !!draftCfg.title_uppercase; }
                        if (headersUppercaseEl) { headersUppercaseEl.checked = !!draftCfg.headers_uppercase; }
                        var mrSortEl = document.getElementById('tmExportMicrorregionSort');
                        if (mrSortEl) { mrSortEl.value = draftCfg.microrregion_sort === 'desc' ? 'desc' : (data.microrregion_sort || 'asc'); }
                        personalizeModal.querySelectorAll('.tm-export-title-align .tm-export-align-btn').forEach(function (b) {
                            b.classList.toggle('is-active', (b.getAttribute('data-title-align') || '') === (draftCfg.title_align || 'center'));
                        });
                        personalizeModal.querySelectorAll('#tmExportCountAlignGroup .tm-export-align-btn').forEach(function (b) {
                            b.classList.toggle('is-active', (b.getAttribute('data-count-table-align') || '') === (draftCfg.count_table_align || 'left'));
                        });
                        personalizeModal.querySelectorAll('#tmExportDataAlignGroup .tm-export-align-btn').forEach(function (b) {
                            b.classList.toggle('is-active', (b.getAttribute('data-data-table-align') || '') === (draftCfg.table_align || 'left'));
                        });
                        personalizeModal.querySelectorAll('#tmExportSumAlignGroup .tm-export-align-btn').forEach(function (b) {
                            b.classList.toggle('is-active', (b.getAttribute('data-sum-table-align') || '') === (draftCfg.sum_table_align || 'left'));
                        });
                        personalizeModal.querySelectorAll('#tmExportSumTitleAlignGroup .tm-export-align-btn').forEach(function (b) {
                            b.classList.toggle('is-active', (b.getAttribute('data-sum-title-align') || '') === (draftCfg.sum_title_align || 'center'));
                        });
                        personalizeModal.querySelectorAll('#tmExportSectionLabelAlignGroup .tm-export-align-btn').forEach(function (b) {
                            b.classList.toggle('is-active', (b.getAttribute('data-section-label-align') || '') === (draftCfg.section_label_align || 'left'));
                        });
                        var dOrient = draftCfg.orientation || 'portrait';
                        personalizeModal.querySelectorAll('.tm-export-orient-btn').forEach(function (b) {
                            b.classList.toggle('is-active', (b.getAttribute('data-orientation') || '') === dOrient);
                        });
                        var paperSizeElDraft = document.getElementById('tmExportPaperSize');
                        var draftPaper = String(draftCfg.paper_size || 'letter').toLowerCase();
                        if (paperSizeElDraft) {
                            paperSizeElDraft.value = draftPaper === 'legal' ? 'legal' : 'letter';
                        }
                        applyExportPreviewPageLayout(dOrient, draftPaper);
                        if (includeCountTableEl) {
                            includeCountTableEl.checked = !!draftCfg.include_count_table;
                            if (countByWrapEl) { countByWrapEl.hidden = !includeCountTableEl.checked; }
                        }
                        if (includeCalculatedColumnsEl) {
                            var hasCalcDraft = !!draftCfg.include_calculated_columns || !!draftCfg.include_operations_column;
                            includeCalculatedColumnsEl.checked = hasCalcDraft;
                            if (calculatedColumnsWrapEl) { calculatedColumnsWrapEl.hidden = !hasCalcDraft; }
                        }
                        if (includeSumTableEl) {
                            includeSumTableEl.checked = !!draftCfg.include_sum_table;
                            if (sumWrapEl) { sumWrapEl.hidden = !includeSumTableEl.checked; }
                        }
                        if (sumGroupByEl) {
                            sumGroupByEl.value = draftCfg.sum_group_by === 'municipio' ? 'municipio' : 'microrregion';
                        }
                        if (sumTitleEl) {
                            sumTitleEl.value = (draftCfg.sum_title != null && String(draftCfg.sum_title).trim() !== '') ? String(draftCfg.sum_title) : 'Sumatoria';
                        }
                        if (sumTitleUppercaseEl) { sumTitleUppercaseEl.checked = String(draftCfg.sum_title_case || '').toLowerCase() === 'upper'; }
                        if (sumTitleFontEl) {
                            var stf = parseInt(draftCfg.sum_title_font_size_px, 10);
                            sumTitleFontEl.value = String(Number.isNaN(stf) ? 14 : Math.max(10, Math.min(36, stf)));
                        }
                        if (sumShowItemColEl) { sumShowItemColEl.checked = !Object.prototype.hasOwnProperty.call(draftCfg, 'sum_show_item') ? true : !!draftCfg.sum_show_item; }
                        if (sumItemLabelEl) { sumItemLabelEl.value = (draftCfg.sum_item_label != null && String(draftCfg.sum_item_label).trim() !== '') ? String(draftCfg.sum_item_label) : '#'; }
                        if (sumShowDelegacionColEl) { sumShowDelegacionColEl.checked = !Object.prototype.hasOwnProperty.call(draftCfg, 'sum_show_delegacion') ? true : !!draftCfg.sum_show_delegacion; }
                        if (sumDelegacionLabelEl) { sumDelegacionLabelEl.value = (draftCfg.sum_delegacion_label != null && String(draftCfg.sum_delegacion_label).trim() !== '') ? String(draftCfg.sum_delegacion_label) : 'Delegación'; }
                        if (sumShowCabeceraColEl) { sumShowCabeceraColEl.checked = !Object.prototype.hasOwnProperty.call(draftCfg, 'sum_show_cabecera') ? true : !!draftCfg.sum_show_cabecera; }
                        if (sumCabeceraLabelEl) { sumCabeceraLabelEl.value = (draftCfg.sum_cabecera_label != null && String(draftCfg.sum_cabecera_label).trim() !== '') ? String(draftCfg.sum_cabecera_label) : 'Cabecera'; }
                        setSumGroupColor(draftCfg.sum_group_color || 'var(--clr-primary)');
                        if (sumIncludeTotalsRowEl) { sumIncludeTotalsRowEl.checked = !!draftCfg.include_sum_totals_row; }
                        if (sumCellFontEl && draftCfg.sum_table_cell_font_size_px != null) {
                            var ssf = parseInt(draftCfg.sum_table_cell_font_size_px, 10);
                            if (!Number.isNaN(ssf)) { sumCellFontEl.value = String(Math.max(9, Math.min(24, ssf))); }
                        }
                        if (sumHeaderFontEl && draftCfg.sum_table_header_font_size_px != null) {
                            var shf = parseInt(draftCfg.sum_table_header_font_size_px, 10);
                            if (!Number.isNaN(shf)) { sumHeaderFontEl.value = String(Math.max(9, Math.min(28, shf))); }
                        }
                        if (recordsGroupHeaderFontEl) {
                            var rghf = parseInt((draftCfg.records_group_header_font_size_px != null ? draftCfg.records_group_header_font_size_px : draftCfg.records_header_font_size_px), 10);
                            if (!Number.isNaN(rghf)) { recordsGroupHeaderFontEl.value = String(Math.max(9, Math.min(48, rghf))); }
                        }
                        if (sumGroupHeaderFontEl) {
                            var sghf = parseInt((draftCfg.sum_group_header_font_size_px != null ? draftCfg.sum_group_header_font_size_px : draftCfg.sum_table_header_font_size_px), 10);
                            if (!Number.isNaN(sghf)) { sumGroupHeaderFontEl.value = String(Math.max(9, Math.min(48, sghf))); }
                        }
                        if (includeTotalsTableEl) { includeTotalsTableEl.checked = !!draftCfg.include_totals_table; }
                        if (totalsTableWrapEl) { totalsTableWrapEl.hidden = !(includeTotalsTableEl && includeTotalsTableEl.checked); }
                        if (totalsTableTitleEl) {
                            totalsTableTitleEl.value = (draftCfg.totals_table_title != null && String(draftCfg.totals_table_title).trim() !== '') ? String(draftCfg.totals_table_title) : 'Totales';
                        }
                        if (sectionLabelEl) {
                            sectionLabelEl.value = (draftCfg.section_label != null && String(draftCfg.section_label).trim() !== '') ? String(draftCfg.section_label) : 'Desglose';
                        }
                        if (totalsCellFontEl && draftCfg.totals_table_cell_font_size_px != null) {
                            var tcf = parseInt(draftCfg.totals_table_cell_font_size_px, 10);
                            if (!Number.isNaN(tcf)) { totalsCellFontEl.value = String(Math.max(9, Math.min(24, tcf))); }
                        }
                        if (totalsHeaderFontEl && draftCfg.totals_table_header_font_size_px != null) {
                            var thf = parseInt(draftCfg.totals_table_header_font_size_px, 10);
                            if (!Number.isNaN(thf)) { totalsHeaderFontEl.value = String(Math.max(9, Math.min(48, thf))); }
                        }
                        if (totalsGroupHeaderFontEl) {
                            var tghf = parseInt((draftCfg.totals_group_header_font_size_px != null ? draftCfg.totals_group_header_font_size_px : draftCfg.totals_table_header_font_size_px), 10);
                            if (!Number.isNaN(tghf)) { totalsGroupHeaderFontEl.value = String(Math.max(9, Math.min(48, tghf))); }
                        }
                        personalizeModal.querySelectorAll('#tmExportTotalsTableAlignGroup .tm-export-align-btn').forEach(function (b) {
                            var current = String(draftCfg.totals_table_align || 'left');
                            b.classList.toggle('is-active', (b.getAttribute('data-totals-table-align') || '') === current);
                        });
                        if (sumTotalsBoldEl) { sumTotalsBoldEl.checked = !Object.prototype.hasOwnProperty.call(draftCfg, 'sum_totals_bold') ? true : !!draftCfg.sum_totals_bold; }
                        setSumTotalsColor(draftCfg.sum_totals_text_color || 'var(--clr-primary)');
                        if (countByFieldsEl && Array.isArray(draftCfg.count_by_fields)) {
                            countByFieldsEl.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                                var ck = cb.getAttribute('data-count-key') || cb.value;
                                cb.checked = draftCfg.count_by_fields.indexOf(ck) !== -1;
                            });
                        }
                        if (personalizeModal) {
                            personalizeModal._exportGroups = normalizeExportGroups(Array.isArray(draftCfg.groups) ? draftCfg.groups : []);
                        }
                        renderExportGroups();
                        var cwEl = document.getElementById('tmExportCountTableCellWidth');
                        if (cwEl && draftCfg.count_table_cell_width != null) {
                            var cwn = parseInt(draftCfg.count_table_cell_width, 10);
                            if (!Number.isNaN(cwn)) { cwEl.value = String(Math.max(6, Math.min(40, cwn))); }
                        }
                        if (cellFontEl && (draftCfg.records_cell_font_size_px != null || draftCfg.cell_font_size_px != null)) {
                            var cfn = parseInt((draftCfg.records_cell_font_size_px != null ? draftCfg.records_cell_font_size_px : draftCfg.cell_font_size_px), 10);
                            if (!Number.isNaN(cfn)) { cellFontEl.value = String(Math.max(9, Math.min(24, cfn))); }
                        }
                        if (headerFontEl && (draftCfg.records_header_font_size_px != null || draftCfg.header_font_size_px != null)) {
                            var hfn = parseInt((draftCfg.records_header_font_size_px != null ? draftCfg.records_header_font_size_px : draftCfg.header_font_size_px), 10);
                            if (!Number.isNaN(hfn)) { headerFontEl.value = String(Math.max(9, Math.min(28, hfn))); }
                        }
                        if (titleFontEl && draftCfg.title_font_size_px != null) {
                            var tfn = parseInt(draftCfg.title_font_size_px, 10);
                            if (!Number.isNaN(tfn)) { titleFontEl.value = String(Math.max(10, Math.min(36, tfn))); }
                        }
                        if (docMarginPresetEl) {
                            var dm = ['normal', 'compact', 'none'].indexOf(draftCfg.doc_margin_preset) !== -1 ? draftCfg.doc_margin_preset : 'compact';
                            docMarginPresetEl.value = dm;
                        }
                        var orderedMerged = [];
                        var draftColumns = Array.isArray(draftCfg.columns) ? draftCfg.columns : [];
                        draftColumns.forEach(function (sc) {
                            var b = columns.find(function (c) { return c.key === sc.key; });
                            if (b) {
                                orderedMerged.push(Object.assign({}, b, {
                                    label: (sc.label != null && String(sc.label).trim() !== '') ? String(sc.label) : (b.label || b.key),
                                    max_width_chars: sc.max_width_chars != null ? sc.max_width_chars : b.max_width_chars,
                                    image_height: sc.image_height != null ? sc.image_height : b.image_height,
                                    image_width: sc.image_width != null ? sc.image_width : b.image_width,
                                    fill_empty_mode: sc.fill_empty_mode || 'none',
                                    fill_empty_value: sc.fill_empty_value != null ? sc.fill_empty_value : '',
                                    content_bold: !!sc.content_bold
                                }));
                            }
                        });
                        if (orderedMerged.length) {
                            buildPersonalizeColumnsList(orderedMerged, columnsEl);
                            applyColumnDraftVisuals(columnsEl, draftColumns);
                        } else {
                            buildPersonalizeColumnsList(columns, columnsEl);
                        }
                    } else {
                        tmExportEnsureCalculatedColumnsState(countableColumns, null);
                        tmExportRenderCalculatedColumnsConfigurator(countableColumns);
                        tmExportEnsureSumState(countableColumns, null);
                        tmExportRenderSumConfigurator(countableColumns);
                        if (titleEl) { titleEl.value = data.title || ''; }
                        if (titleUppercaseEl) { titleUppercaseEl.checked = false; }
                        if (headersUppercaseEl) { headersUppercaseEl.checked = false; }
                        if (cellFontEl) { cellFontEl.value = '12'; }
                        if (headerFontEl) { headerFontEl.value = '12'; }
                        if (docMarginPresetEl) { docMarginPresetEl.value = 'compact'; }
                        if (includeCalculatedColumnsEl) { includeCalculatedColumnsEl.checked = false; }
                        if (calculatedColumnsWrapEl) { calculatedColumnsWrapEl.hidden = true; }
                        var paperSizeElDefault = document.getElementById('tmExportPaperSize');
                        if (paperSizeElDefault) { paperSizeElDefault.value = 'letter'; }
                        personalizeModal.querySelectorAll('#tmExportSumAlignGroup .tm-export-align-btn').forEach(function (b) {
                            b.classList.toggle('is-active', (b.getAttribute('data-sum-table-align') || '') === 'left');
                        });
                        personalizeModal.querySelectorAll('#tmExportSumTitleAlignGroup .tm-export-align-btn').forEach(function (b) {
                            b.classList.toggle('is-active', (b.getAttribute('data-sum-title-align') || '') === 'center');
                        });
                        if (includeSumTableEl) { includeSumTableEl.checked = false; }
                        if (sumWrapEl) { sumWrapEl.hidden = true; }
                        if (sumGroupByEl) { sumGroupByEl.value = 'microrregion'; }
                        if (sumTitleEl) { sumTitleEl.value = 'Sumatoria'; }
                        if (sumTitleUppercaseEl) { sumTitleUppercaseEl.checked = false; }
                        if (sumTitleFontEl) { sumTitleFontEl.value = '14'; }
                        if (sumShowItemColEl) { sumShowItemColEl.checked = true; }
                        if (sumItemLabelEl) { sumItemLabelEl.value = '#'; }
                        if (sumShowDelegacionColEl) { sumShowDelegacionColEl.checked = true; }
                        if (sumDelegacionLabelEl) { sumDelegacionLabelEl.value = 'Delegación'; }
                        if (sumShowCabeceraColEl) { sumShowCabeceraColEl.checked = true; }
                        if (sumCabeceraLabelEl) { sumCabeceraLabelEl.value = 'Cabecera'; }
                        if (sectionLabelEl) { sectionLabelEl.value = 'Desglose'; }
                        if (sumCellFontEl) { sumCellFontEl.value = '12'; }
                        if (sumHeaderFontEl) { sumHeaderFontEl.value = '12'; }
                        if (recordsGroupHeaderFontEl) { recordsGroupHeaderFontEl.value = '12'; }
                        if (sumGroupHeaderFontEl) { sumGroupHeaderFontEl.value = '12'; }
                        setSumGroupColor('var(--clr-primary)');
                        if (sumIncludeTotalsRowEl) { sumIncludeTotalsRowEl.checked = false; }
                        if (includeTotalsTableEl) { includeTotalsTableEl.checked = false; }
                        if (totalsTableWrapEl) { totalsTableWrapEl.hidden = true; }
                        if (totalsTableTitleEl) { totalsTableTitleEl.value = 'Totales'; }
                        if (totalsCellFontEl) { totalsCellFontEl.value = '12'; }
                        if (totalsHeaderFontEl) { totalsHeaderFontEl.value = '12'; }
                        if (totalsGroupHeaderFontEl) { totalsGroupHeaderFontEl.value = '12'; }
                        personalizeModal.querySelectorAll('#tmExportTotalsTableAlignGroup .tm-export-align-btn').forEach(function (b) {
                            b.classList.toggle('is-active', (b.getAttribute('data-totals-table-align') || '') === 'left');
                        });
                        personalizeModal.querySelectorAll('#tmExportSectionLabelAlignGroup .tm-export-align-btn').forEach(function (b) {
                            b.classList.toggle('is-active', (b.getAttribute('data-section-label-align') || '') === 'left');
                        });
                        if (sumTotalsBoldEl) { sumTotalsBoldEl.checked = true; }
                        setSumTotalsColor('var(--clr-primary)');
                        applyExportPreviewPageLayout('portrait', 'letter');
                        var mrSortDefaultEl = document.getElementById('tmExportMicrorregionSort');
                        if (mrSortDefaultEl) { mrSortDefaultEl.value = data.microrregion_sort || 'asc'; }
                        buildPersonalizeColumnsList(columns, columnsEl);
                    }
                    buildCountTableColorList(countTableColorListEl, countByFieldsEl, personalizeModal._previewEntries, draftCfg ? draftCfg.count_table_colors : null);
                    if (personalizeModal && !personalizeModal._personalizeGeneralListenersBound) {
                        personalizeModal._personalizeGeneralListenersBound = true;
                        personalizeModal.addEventListener('click', function (e) {
                            var alignBtn = e.target.closest('.tm-export-align-btn');
                            if (alignBtn) {
                                // Limpiar solo el grupo inmediato al que pertenece el botón
                                var parentGroup = alignBtn.parentElement;
                                if (parentGroup) {
                                    Array.from(parentGroup.querySelectorAll('.tm-export-align-btn')).forEach(function (b) { b.classList.remove('is-active'); });
                                    alignBtn.classList.add('is-active');
                                    buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl, undefined, personalizeModal._previewEntries, personalizeModal._previewMicrorregionMeta);
                                }
                            }

                            var orientBtn = e.target.closest('.tm-export-orient-btn');
                            if (orientBtn) {
                                var group = orientBtn.closest('.tm-export-orient-btns');
                                if (group) {
                                    group.querySelectorAll('.tm-export-orient-btn').forEach(function (b) { b.classList.remove('is-active'); });
                                    orientBtn.classList.add('is-active');
                                    var orient = orientBtn.getAttribute('data-orientation') || 'portrait';
                                    var paperSizeCurrentEl = document.getElementById('tmExportPaperSize');
                                    var paperCurrent = (paperSizeCurrentEl && paperSizeCurrentEl.value) ? paperSizeCurrentEl.value : 'letter';
                                    applyExportPreviewPageLayout(orient, paperCurrent);
                                    buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl, undefined, personalizeModal._previewEntries, personalizeModal._previewMicrorregionMeta);
                                }
                            }

                            var colorList = document.getElementById('tmExportCountTableColorList');
                            if (colorList && colorList.contains(e.target)) {
                                var trigger = e.target.closest('.tm-export-color-trigger');
                                var option = e.target.closest('.tm-export-color-option');
                                if (trigger) {
                                    var menu = trigger.nextElementSibling;
                                    if (menu instanceof HTMLElement) {
                                        var isOpen = !menu.hidden;
                                        Array.from(personalizeModal.querySelectorAll('.tm-export-color-menu')).forEach(function (m) { m.hidden = true; });
                                        trigger.setAttribute('aria-expanded', String(!isOpen));
                                        menu.hidden = isOpen;
                                        if (!isOpen && menu.scrollIntoView) {
                                            setTimeout(function () { menu.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }, 0);
                                        }
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
                            }
                        });
                        personalizeModal.addEventListener('input', function (e) {
                            if (e.target && (e.target.closest('.tm-export-count-width-input') || e.target.id === 'tmExportCountTableCellWidth')) {
                                buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl, undefined, personalizeModal._previewEntries, personalizeModal._previewMicrorregionMeta);
                            }
                        });
                        personalizeModal.addEventListener('change', function (e) {
                            if (e.target && (e.target.closest('.tm-export-count-width-input') || e.target.id === 'tmExportCountTableCellWidth')) {
                                buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl, undefined, personalizeModal._previewEntries, personalizeModal._previewMicrorregionMeta);
                            }
                        });
                    }
                    buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl, undefined, personalizeModal._previewEntries, personalizeModal._previewMicrorregionMeta);
                    if (restoreWrap) { restoreWrap.hidden = true; }
                    if (personalizeModal) { personalizeModal._previewZoom = 100; }
                    setupPreviewZoom();

                    var tmExportColCtxMenuEl = null;

                    var tmExportHideColumnCtxMenu = function () {
                        if (tmExportColCtxMenuEl && tmExportColCtxMenuEl.parentNode) {
                            tmExportColCtxMenuEl.parentNode.removeChild(tmExportColCtxMenuEl);
                        }
                        tmExportColCtxMenuEl = null;
                    };

                    var tmExportOpenReplaceDialog = function (row) {
                        if (!row) { return; }
                        var currentMode = String(row.dataset.fillEmptyMode || 'none');
                        var currentValue = String(row.dataset.fillEmptyValue || '');

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Reemplazar vacios',
                                html: ''
                                    + '<div style="display:grid;gap:8px;text-align:left;">'
                                    + '  <label style="display:grid;gap:4px;">'
                                    + '    <span>Modo</span>'
                                    + '    <select id="tmFillMode" class="swal2-input" style="margin:0;">'
                                    + '      <option value="none">Sin reemplazo</option>'
                                    + '      <option value="auto">Automatico (numerico=0, texto=S/R)</option>'
                                    + '      <option value="custom">Personalizado</option>'
                                    + '    </select>'
                                    + '  </label>'
                                    + '  <label style="display:grid;gap:4px;">'
                                    + '    <span>Valor personalizado</span>'
                                    + '    <input id="tmFillValue" class="swal2-input" style="margin:0;" placeholder="Ej: S/R, 0, N/A">'
                                    + '  </label>'
                                    + '</div>',
                                showCancelButton: true,
                                confirmButtonText: 'Guardar',
                                cancelButtonText: 'Cancelar',
                                didOpen: function () {
                                    var modeEl = document.getElementById('tmFillMode');
                                    var valEl = document.getElementById('tmFillValue');
                                    if (modeEl) { modeEl.value = currentMode; }
                                    if (valEl) { valEl.value = currentValue; }
                                },
                                preConfirm: function () {
                                    var modeEl = document.getElementById('tmFillMode');
                                    var valEl = document.getElementById('tmFillValue');
                                    return {
                                        mode: modeEl ? String(modeEl.value || 'none') : 'none',
                                        value: valEl ? String(valEl.value || '') : ''
                                    };
                                }
                            }).then(function (res) {
                                if (!res || !res.isConfirmed) { return; }
                                row.dataset.fillEmptyMode = String(res.value && res.value.mode || 'none');
                                row.dataset.fillEmptyValue = String(res.value && res.value.value || '');
                                buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                            });
                            return;
                        }

                        var modeRaw = prompt('Modo de vacio: none | auto | custom', currentMode);
                        if (modeRaw == null) { return; }
                        var mode = String(modeRaw || '').trim().toLowerCase();
                        if (['none', 'auto', 'custom'].indexOf(mode) === -1) { mode = 'none'; }
                        var value = currentValue;
                        if (mode === 'custom') {
                            var v = prompt('Valor personalizado', currentValue);
                            if (v == null) { return; }
                            value = String(v);
                        }
                        row.dataset.fillEmptyMode = mode;
                        row.dataset.fillEmptyValue = value;
                        buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                    };

                    var tmExportOpenColorDialog = function (row) {
                        if (!row) { return; }
                        var currentColor = String(row.dataset.headerColor || 'var(--clr-primary)');

                        if (typeof Swal !== 'undefined') {
                            var colorOptionsHtml = TEMPLATE_COLORS.map(function (c) {
                                return '<option value="' + escapeHtml(c.value) + '">' + escapeHtml(c.name) + '</option>';
                            }).join('');
                            Swal.fire({
                                title: 'Color de columna',
                                html: '<select id="tmColCtxColor" class="swal2-input" style="margin:0;">' + colorOptionsHtml + '</select>',
                                showCancelButton: true,
                                confirmButtonText: 'Guardar',
                                cancelButtonText: 'Cancelar',
                                didOpen: function () {
                                    var colorEl = document.getElementById('tmColCtxColor');
                                    if (colorEl) { colorEl.value = currentColor; }
                                },
                                preConfirm: function () {
                                    var colorEl = document.getElementById('tmColCtxColor');
                                    return colorEl ? String(colorEl.value || 'var(--clr-primary)') : 'var(--clr-primary)';
                                }
                            }).then(function (res) {
                                if (!res || !res.isConfirmed) { return; }
                                row.dataset.headerColor = String(res.value || 'var(--clr-primary)');
                                buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                            });
                            return;
                        }

                        var labels = TEMPLATE_COLORS.map(function (c, idx) { return (idx + 1) + '. ' + c.name; }).join('\n');
                        var idxRaw = prompt('Selecciona color:\n' + labels, '1');
                        if (idxRaw == null) { return; }
                        var idx = parseInt(String(idxRaw), 10) - 1;
                        if (Number.isNaN(idx) || idx < 0 || idx >= TEMPLATE_COLORS.length) { return; }
                        row.dataset.headerColor = String(TEMPLATE_COLORS[idx].value || 'var(--clr-primary)');
                        buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                    };

                    var tmExportShowColumnCtxMenu = function (clientX, clientY, row) {
                        tmExportHideColumnCtxMenu();
                        if (!row) { return; }

                        var menu = document.createElement('div');
                        menu.className = 'tm-export-col-ctx-menu';
                        menu.setAttribute('role', 'menu');

                        var addItem = function (label, onActivate) {
                            var b = document.createElement('button');
                            b.type = 'button';
                            b.className = 'tm-export-col-ctx-menu-item';
                            b.textContent = label;
                            b.setAttribute('role', 'menuitem');
                            b.addEventListener('click', function (ev) {
                                ev.stopPropagation();
                                tmExportHideColumnCtxMenu();
                                onActivate();
                            });
                            menu.appendChild(b);
                        };

                        var isBold = String(row.dataset.contentBold || '0') === '1';
                        addItem(isBold ? 'Quitar negritas' : 'Aplicar negritas', function () {
                            row.dataset.contentBold = isBold ? '0' : '1';
                            buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                        });
                        addItem('Reemplazar vacios...', function () {
                            tmExportOpenReplaceDialog(row);
                        });
                        addItem('Color de columna...', function () {
                            tmExportOpenColorDialog(row);
                        });

                        document.body.appendChild(menu);
                        menu.style.position = 'fixed';
                        menu.style.left = clientX + 'px';
                        menu.style.top = clientY + 'px';
                        menu.style.zIndex = '10020';
                        tmExportColCtxMenuEl = menu;

                        requestAnimationFrame(function () {
                            var r = menu.getBoundingClientRect();
                            var x = clientX;
                            var y = clientY;
                            if (r.right > window.innerWidth - 8) { x = Math.max(8, clientX - r.width); }
                            if (r.bottom > window.innerHeight - 8) { y = Math.max(8, clientY - r.height); }
                            menu.style.left = x + 'px';
                            menu.style.top = y + 'px';
                        });
                    };

                    if (personalizeModal && !personalizeModal.dataset.colCtxMenuBound) {
                        personalizeModal.dataset.colCtxMenuBound = '1';
                        document.addEventListener('click', function (e) {
                            if (tmExportColCtxMenuEl && !e.target.closest('.tm-export-col-ctx-menu')) {
                                tmExportHideColumnCtxMenu();
                            }
                        });
                        document.addEventListener('keydown', function (e) {
                            if (e.key === 'Escape') { tmExportHideColumnCtxMenu(); }
                        });
                        document.addEventListener('scroll', function () { tmExportHideColumnCtxMenu(); }, true);
                    }

                    columnsEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    columnsEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    columnsEl.addEventListener('contextmenu', function (e) {
                        var row = e.target.closest('.tm-export-personalize-col');
                        if (!row || !columnsEl.contains(row)) { return; }
                        e.preventDefault();
                        tmExportShowColumnCtxMenu(e.clientX, e.clientY, row);
                    });
                    columnsEl.addEventListener('click', function (e) {
                        const upBtn = e.target.closest('[data-move-col-up]');
                        if (upBtn) {
                            const row = upBtn.closest('.tm-export-personalize-col');
                            if (!row) { return; }
                            var prev = row.previousElementSibling;
                            while (prev && (!prev.classList || !prev.classList.contains('tm-export-personalize-col'))) {
                                prev = prev.previousElementSibling;
                            }
                            if (prev) {
                                columnsEl.insertBefore(row, prev);
                                buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                            }
                            return;
                        }

                        const downBtn = e.target.closest('[data-move-col-down]');
                        if (downBtn) {
                            const row = downBtn.closest('.tm-export-personalize-col');
                            if (!row) { return; }
                            var next = row.nextElementSibling;
                            while (next && (!next.classList || !next.classList.contains('tm-export-personalize-col'))) {
                                next = next.nextElementSibling;
                            }
                            if (next) {
                                columnsEl.insertBefore(next, row);
                                buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                            }
                            return;
                        }

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
                    if (headerFontEl) {
                        headerFontEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                        headerFontEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    if (recordsGroupHeaderFontEl) {
                        recordsGroupHeaderFontEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                        recordsGroupHeaderFontEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    if (titleFontEl) {
                        titleFontEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                        titleFontEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    if (sumTitleEl) {
                        sumTitleEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    if (sectionLabelEl) {
                        sectionLabelEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                        sectionLabelEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    if (sumTitleUppercaseEl) {
                        sumTitleUppercaseEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    if (sumTitleFontEl) {
                        sumTitleFontEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                        sumTitleFontEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    [sumShowItemColEl, sumItemLabelEl, sumShowDelegacionColEl, sumDelegacionLabelEl, sumShowCabeceraColEl, sumCabeceraLabelEl].forEach(function (el) {
                        if (!el) { return; }
                        el.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                        el.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    });
                    if (sumIncludeTotalsRowEl) {
                        sumIncludeTotalsRowEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    if (sumCellFontEl) {
                        sumCellFontEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                        sumCellFontEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    if (sumHeaderFontEl) {
                        sumHeaderFontEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                        sumHeaderFontEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    if (sumGroupHeaderFontEl) {
                        sumGroupHeaderFontEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                        sumGroupHeaderFontEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    if (includeTotalsTableEl) {
                        includeTotalsTableEl.addEventListener('change', function () {
                            if (totalsTableWrapEl) { totalsTableWrapEl.hidden = !includeTotalsTableEl.checked; }
                            buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                        });
                    }
                    if (totalsTableTitleEl) {
                        totalsTableTitleEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                        totalsTableTitleEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    if (totalsCellFontEl) {
                        totalsCellFontEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                        totalsCellFontEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    if (totalsHeaderFontEl) {
                        totalsHeaderFontEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                        totalsHeaderFontEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    if (totalsGroupHeaderFontEl) {
                        totalsGroupHeaderFontEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                        totalsGroupHeaderFontEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    if (sumTotalsBoldEl) {
                        sumTotalsBoldEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    var microrregionSortEl = document.getElementById('tmExportMicrorregionSort');
                    if (microrregionSortEl) {
                        microrregionSortEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    if (docMarginPresetEl) {
                        docMarginPresetEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }
                    var paperSizeEl = document.getElementById('tmExportPaperSize');
                    if (paperSizeEl) {
                        paperSizeEl.addEventListener('change', function () {
                            var orientBtn = personalizeModal.querySelector('.tm-export-orient-btn.is-active');
                            var orient = orientBtn ? (orientBtn.getAttribute('data-orientation') || 'portrait') : 'portrait';
                            applyExportPreviewPageLayout(orient, paperSizeEl.value || 'letter');
                            buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                        });
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
                        var calcColumns = Array.isArray(state.calculatedColumns) ? state.calculatedColumns : [];
                        var firstCalc = calcColumns.length ? calcColumns[0] : null;
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
                            records_cell_font_size_px: state.recordsCellFontPx || state.cellFontPx || 12,
                            records_header_font_size_px: state.recordsHeaderFontPx || state.headerFontPx || 12,
                            records_group_header_font_size_px: state.recordsGroupHeaderFontPx || state.recordsHeaderFontPx || state.headerFontPx || 12,
                            cell_font_size_px: state.recordsCellFontPx || state.cellFontPx || 12,
                            cellFontPx: state.recordsCellFontPx || state.cellFontPx || 12,
                            header_font_size_px: state.recordsHeaderFontPx || state.headerFontPx || 12,
                            headerFontPx: state.recordsHeaderFontPx || state.headerFontPx || 12,
                            title_font_size_px: state.titleFontPx || 18,
                            doc_margin_preset: state.docMarginPreset || 'compact',
                            orientation: orientation,
                            paper_size: state.paperSize || 'letter',
                            count_table_align: state.countTableAlign || 'left',
                            table_align: state.dataTableAlign || 'left',
                            sum_table_align: state.sumTableAlign || 'left',
                            sum_title: state.sumTitle || 'Sumatoria',
                            sum_title_case: state.sumTitleCase || 'normal',
                            sum_show_item: state.sumShowItem !== false,
                            sum_item_label: state.sumItemLabel || '#',
                            sum_show_delegacion: state.sumShowDelegation !== false,
                            sum_delegacion_label: state.sumDelegationLabel || 'Delegación',
                            sum_show_cabecera: state.sumShowCabecera !== false,
                            sum_cabecera_label: state.sumCabeceraLabel || 'Cabecera',
                            sum_title_align: state.sumTitleAlign || 'center',
                            sum_title_font_size_px: state.sumTitleFontPx || 14,
                            sum_table_cell_font_size_px: state.sumCellFontPx || 12,
                            sum_table_header_font_size_px: state.sumHeaderFontPx || 12,
                            sum_group_header_font_size_px: state.sumGroupHeaderFontPx || state.sumHeaderFontPx || 12,
                            sum_group_color: state.sumGroupColor || 'var(--clr-primary)',
                            include_sum_totals_row: !!state.sumIncludeTotalsRow,
                            include_totals_table: !!state.includeTotalsTable,
                            totals_table_title: state.totalsTableTitle || 'Totales',
                            section_label: state.sectionLabel || 'Desglose',
                            section_label_align: state.sectionLabelAlign || 'left',
                            totals_table_align: state.totalsTableAlign || 'left',
                            totals_table_cell_font_size_px: state.totalsCellFontPx || 12,
                            totals_table_header_font_size_px: state.totalsHeaderFontPx || 12,
                            totals_group_header_font_size_px: state.totalsGroupHeaderFontPx || state.totalsHeaderFontPx || 12,
                            sum_totals_bold: !(state.sumTotalsBold === false),
                            sum_totals_text_color: state.sumTotalsTextColor || 'var(--clr-primary)',
                            include_count_table: includeCountTable,
                            count_by_fields: countByFields,
                            count_table_colors: state.countTableColors || {},
                            count_table_cell_width: state.countTableCellWidth || 12,
                            include_sum_table: !!state.includeSumTable,
                            sum_group_by: state.sumGroupBy || 'microrregion',
                            include_calculated_columns: !!state.includeCalculatedColumns,
                            calculated_columns: calcColumns.map(function (c) {
                                var op = String(c.operation || 'add').toLowerCase();
                                if (['add', 'subtract', 'multiply', 'percent'].indexOf(op) === -1) { op = 'add'; }
                                var baseField = String(c.baseField || c.referenceField || '');
                                var afterKey = String(c.afterKey || c.position_after_key || c.after_key || '');
                                var group = String(c.group || '');
                                var opFields = Array.isArray(c.operationFields)
                                    ? c.operationFields.slice()
                                    : (Array.isArray(c.fields) ? c.fields.slice() : []);
                                var cellColor = String(c.cellColor || c.color || 'var(--clr-secondary)');
                                var cellSizeCh = Math.max(8, Math.min(40, parseInt(String(c.cellSizeCh != null ? c.cellSizeCh : c.cell_size_ch), 10) || 18));
                                return {
                                    id: String(c.id || ''),
                                    label: String(c.label || ''),
                                    group: group,
                                    operation: op,
                                    base_field: baseField,
                                    position_after_key: afterKey,
                                    after_key: afterKey,
                                    operation_fields: opFields,
                                    cell_color: cellColor,
                                    cell_size_ch: cellSizeCh,
                                    cell_bold: !!(c.cellBold || c.cell_bold),
                                    reference_field: baseField,
                                    include_percent: op === 'percent',
                                    fields: opFields,
                                    weights: {}
                                };
                            }),
                            include_operations_column: !!state.includeCalculatedColumns && !!firstCalc,
                            operations_label: firstCalc ? String(firstCalc.label || 'Operaciones') : 'Operaciones',
                            operations_group: firstCalc ? String(firstCalc.group || '') : '',
                            operations_reference_field: firstCalc ? String(firstCalc.baseField || firstCalc.referenceField || '') : '',
                            operations_include_percent: firstCalc ? (String(firstCalc.operation || 'add') === 'percent') : true,
                            operations_after_key: firstCalc ? String(firstCalc.afterKey || '') : '',
                            operations_cell_bold: firstCalc ? !!(firstCalc.cellBold || firstCalc.cell_bold) : false,
                            operations_fields: firstCalc
                                ? (Array.isArray(firstCalc.operationFields)
                                    ? firstCalc.operationFields.slice()
                                    : (Array.isArray(firstCalc.fields) ? firstCalc.fields.slice() : []))
                                : [],
                            sum_metrics: Array.isArray(state.sumMetrics) ? state.sumMetrics : [],
                            sum_formulas: Array.isArray(state.sumFormulas) ? state.sumFormulas : [],
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
                                    group: col.group || '',
                                    fill_empty_mode: col.fill_empty_mode || 'none',
                                    fill_empty_value: col.fill_empty_value != null ? String(col.fill_empty_value) : '',
                                    content_bold: !!col.content_bold
                                };
                            })
                        };
                    }

                    function readSavedExportDraft() {
                        if (!exportUrl) { return null; }
                        try {
                            var raw = localStorage.getItem(tmExportDraftStorageKey(exportUrl));
                            if (!raw) { return null; }
                            var parsed = JSON.parse(raw);
                            if (!parsed || parsed.v !== 1 || !parsed.cfg || typeof parsed.cfg !== 'object') {
                                return null;
                            }

                            return parsed;
                        } catch (eRead) {
                            return null;
                        }
                    }

                    function isSameExportCfg(a, b) {
                        if (!a || !b) { return false; }
                        try {
                            return JSON.stringify(a) === JSON.stringify(b);
                        } catch (eCmp) {
                            return false;
                        }
                    }

                    function showSaveConfigFeedback(hasChanges, savedOk) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: savedOk ? (hasChanges ? 'success' : 'info') : 'error',
                                title: savedOk
                                    ? (hasChanges ? 'Configuración guardada' : 'Sin cambios por guardar')
                                    : 'No se pudo guardar',
                                text: savedOk
                                    ? (hasChanges ? 'Se guardó la configuración actual.' : 'La configuración actual ya estaba guardada.')
                                    : 'No fue posible guardar la configuración en este navegador.',
                                toast: true,
                                position: 'top-end',
                                timer: 2200,
                                timerProgressBar: true,
                                showConfirmButton: false
                            });
                            return;
                        }
                        if (!savedOk) {
                            alert('No fue posible guardar la configuración en este navegador.');
                            return;
                        }
                        alert(hasChanges ? 'Se guardó la configuración actual.' : 'La configuración actual ya estaba guardada.');
                    }

                    function persistExportDraft(cfg) {
                        if (!exportUrl) { return false; }
                        var payloadCfg = cfg || collectPersonalizeCfgObject();
                        var swalChoice = personalizeModal._swalChoice || 'single';
                        var previous = readSavedExportDraft();
                        var hadChanges = !previous || !isSameExportCfg(previous.cfg, payloadCfg) || previous.swal_choice !== swalChoice;
                        try {
                            localStorage.setItem(tmExportDraftStorageKey(exportUrl), JSON.stringify({ v: 1, swal_choice: swalChoice, cfg: payloadCfg, savedAt: Date.now() }));
                            return { ok: true, changed: hadChanges };
                        } catch (eSave) {
                            return { ok: false, changed: hadChanges };
                        }
                    }

                    function applyExport(format, mode) {
                        const cfg = collectPersonalizeCfgObject();
                        persistExportDraft(cfg);
                        const fmt = format || 'excel';
                        const exportMode = (fmt === 'excel') ? (mode || 'single') : 'single';
                        closePersonalizeModal();
                        submitTemporaryModuleExportPost(exportUrl, fmt, exportMode, cfg);
                    }

                    if (personalizeModal) {
                        personalizeModal._collectPersonalizeCfgObject = collectPersonalizeCfgObject;
                        personalizeModal._persistExportDraft = persistExportDraft;
                        personalizeModal._exportUrl = exportUrl;
                        personalizeModal._structureUrl = structureUrl;
                        personalizeModal._openSwalChoice = personalizeModal._swalChoice || 'single';
                        try {
                            personalizeModal._openCfgSnapshot = JSON.stringify(collectPersonalizeCfgObject());
                        } catch (eSnapshot) {
                            personalizeModal._openCfgSnapshot = '';
                        }
                    }

                    var saveCfgBtn = document.getElementById('tmExportSaveConfig');
                    var saveCfgTopBtn = document.getElementById('tmExportSaveConfigTop');
                    var clearCfgBtn = document.getElementById('tmExportClearConfig');
                    var clearCfgTopBtn = document.getElementById('tmExportClearConfigTop');
                    [saveCfgTopBtn, saveCfgBtn].forEach(function (btn) {
                        if (!btn || !exportUrl) { return; }
                        btn.onclick = function () {
                            var result = persistExportDraft();
                            showSaveConfigFeedback(!!(result && result.changed), !!(result && result.ok));
                        };
                    });
                    [clearCfgTopBtn, clearCfgBtn].forEach(function (btn) {
                        if (!btn || !exportUrl) { return; }
                        btn.onclick = function () {
                            var hadDraft = !!readSavedExportDraft();

                            var doClear = function () {
                                try {
                                    localStorage.removeItem(tmExportDraftStorageKey(exportUrl));
                                } catch (eRm) {}
                                openExportPersonalizeModal(structureUrl, exportUrl);
                                if (typeof Swal !== 'undefined') {
                                    Swal.fire({
                                        icon: hadDraft ? 'success' : 'info',
                                        title: hadDraft ? 'Configuración limpiada' : 'Cambios limpiados',
                                        text: hadDraft
                                            ? 'Se eliminó la configuración guardada y se restauró la vista por defecto.'
                                            : 'No había configuración guardada; se limpiaron los cambios actuales del modal.',
                                        toast: true,
                                        position: 'top-end',
                                        timer: 2400,
                                        timerProgressBar: true,
                                        showConfirmButton: false
                                    });
                                }
                            };

                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'warning',
                                    title: '¿Quitar cambios de configuración?',
                                    text: 'Se tendrá que configurar el reporte nuevamente.',
                                    showCancelButton: true,
                                    confirmButtonText: 'Sí, limpiar',
                                    cancelButtonText: 'Cancelar'
                                }).then(function (res) {
                                    if (res && res.isConfirmed) {
                                        doClear();
                                    }
                                });
                                return;
                            }

                            if (confirm('¿Quitar cambios de configuración? Se tendrá que configurar el reporte nuevamente.')) {
                                doClear();
                            }
                        };
                    });

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
                                        personalizeModal._exportGroups = normalizeExportGroups(personalizeModal._exportGroups);
                                        var exists = personalizeModal._exportGroups.some(function (g) { return String(g.name || '').toLocaleLowerCase() === name.toLocaleLowerCase(); });
                                        if (name && !exists) {
                                            personalizeModal._exportGroups.push({ name: name, color: TEMPLATE_COLORS[0].value });
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
                                    personalizeModal._exportGroups = normalizeExportGroups(personalizeModal._exportGroups);
                                    var exists = personalizeModal._exportGroups.some(function (g) { return String(g.name || '').toLocaleLowerCase() === name.toLocaleLowerCase(); });
                                    if (!exists) {
                                        personalizeModal._exportGroups.push({ name: name, color: TEMPLATE_COLORS[0].value });
                                        renderExportGroups();
                                        buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                                    }
                                }
                            }
                        };
                    }
                    // Usar event delegation para edición y remove de grupos
                    var groupsListEl = document.getElementById('tmExportGroupsList');
                    if (groupsListEl) {
                        groupsListEl.onclick = function(e) {
                            var removeBtn = e.target.closest('.tm-export-group-remove');
                            if (removeBtn) {
                                var gIndex = parseInt(removeBtn.getAttribute('data-group-index') || '-1', 10);
                                if (!Array.isArray(personalizeModal._exportGroups)) {
                                    personalizeModal._exportGroups = [];
                                }
                                personalizeModal._exportGroups = normalizeExportGroups(personalizeModal._exportGroups);
                                var removed = (gIndex >= 0 && gIndex < personalizeModal._exportGroups.length)
                                    ? personalizeModal._exportGroups[gIndex]
                                    : null;
                                personalizeModal._exportGroups = personalizeModal._exportGroups.filter(function (_, idx) { return idx !== gIndex; });

                                var removedName = removed ? String(removed.name || '') : '';
                                if (removedName !== '') {
                                    // Limpiar grupo de las columnas
                                    columnsEl.querySelectorAll('.tm-export-col-group-select').forEach(function(sel) {
                                        if (sel.value === removedName) sel.value = '';
                                    });
                                    if (Array.isArray(personalizeModal._calculatedColumns)) {
                                        personalizeModal._calculatedColumns = personalizeModal._calculatedColumns.map(function (c) {
                                            if ((c.group || '') === removedName) {
                                                c.group = '';
                                            }
                                            return c;
                                        });
                                    }
                                    if (Array.isArray(personalizeModal._sumMetrics)) {
                                        personalizeModal._sumMetrics = personalizeModal._sumMetrics.map(function (m) {
                                            if ((m.group || '') === removedName) {
                                                m.group = '';
                                            }
                                            return m;
                                        });
                                    }
                                    if (Array.isArray(personalizeModal._sumFormulas)) {
                                        personalizeModal._sumFormulas = personalizeModal._sumFormulas.map(function (f) {
                                            if ((f.group || '') === removedName) {
                                                f.group = '';
                                            }
                                            return f;
                                        });
                                    }
                                }
                                renderExportGroups();
                                buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                            }
                        };
                        groupsListEl.onchange = function (e) {
                            var colorSel = e.target.closest('.tm-export-group-color-select');
                            if (colorSel) {
                                var idx = parseInt(colorSel.getAttribute('data-group-index') || '-1', 10);
                                if (!Array.isArray(personalizeModal._exportGroups)) {
                                    personalizeModal._exportGroups = [];
                                }
                                personalizeModal._exportGroups = normalizeExportGroups(personalizeModal._exportGroups);
                                if (idx < 0 || idx >= personalizeModal._exportGroups.length) { return; }
                                personalizeModal._exportGroups[idx].color = colorSel.value || TEMPLATE_COLORS[0].value;
                                renderExportGroups();
                                buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                                return;
                            }

                            var nameInput = e.target.closest('.tm-export-group-name-input');
                            if (!nameInput) { return; }
                            var idx = parseInt(nameInput.getAttribute('data-group-index') || '-1', 10);
                            if (!Array.isArray(personalizeModal._exportGroups)) {
                                personalizeModal._exportGroups = [];
                            }
                            personalizeModal._exportGroups = normalizeExportGroups(personalizeModal._exportGroups);
                            if (idx < 0 || idx >= personalizeModal._exportGroups.length) { return; }
                            var oldName = String(personalizeModal._exportGroups[idx].name || '');
                            var newName = String(nameInput.value || '').trim();
                            if (!newName || oldName === newName) { return; }
                            var dup = personalizeModal._exportGroups.some(function (g, gIdx) {
                                if (gIdx === idx) { return false; }
                                return String(g.name || '').toLocaleLowerCase() === newName.toLocaleLowerCase();
                            });
                            if (dup) { return; }
                            personalizeModal._exportGroups[idx].name = newName;

                            // Propagar renombre a columnas/sumatorias
                            columnsEl.querySelectorAll('.tm-export-col-group-select').forEach(function(sel) {
                                if (sel.value === oldName) { sel.value = newName; }
                            });
                            if (Array.isArray(personalizeModal._calculatedColumns)) {
                                personalizeModal._calculatedColumns = personalizeModal._calculatedColumns.map(function (c) {
                                    if ((c.group || '') === oldName) {
                                        c.group = newName;
                                    }
                                    return c;
                                });
                            }
                            if (Array.isArray(personalizeModal._sumMetrics)) {
                                personalizeModal._sumMetrics = personalizeModal._sumMetrics.map(function (m) {
                                    if ((m.group || '') === oldName) {
                                        m.group = newName;
                                    }
                                    return m;
                                });
                            }
                            if (Array.isArray(personalizeModal._sumFormulas)) {
                                personalizeModal._sumFormulas = personalizeModal._sumFormulas.map(function (f) {
                                    if ((f.group || '') === oldName) {
                                        f.group = newName;
                                    }
                                    return f;
                                });
                            }

                            renderExportGroups();
                            buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                        };
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
