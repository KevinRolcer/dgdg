@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
@endpush

@section('content')
@php
    $tmImportable = $temporaryModule->fields->filter(function ($f) {
        return in_array($f->type, \App\Services\TemporaryModules\TemporaryModuleExcelImportService::IMPORTABLE_TYPES, true);
    });
@endphp
<section class="tm-page">
    <article class="content-card tm-card">
        @if (session('status'))
            @php
                $tmShowStatus = session('status');
                $tmShowStatusStr = is_string($tmShowStatus) ? $tmShowStatus : (is_array($tmShowStatus) ? implode(' ', array_filter(array_map(function ($v) { return is_scalar($v) ? (string) $v : ''; }, $tmShowStatus))) : '');
            @endphp
            @if ($tmShowStatusStr !== '')
                <div class="inline-alert inline-alert-success" role="alert">{{ $tmShowStatusStr }}</div>
            @endif
        @endif

        @if ($errors->any())
            <div class="inline-alert inline-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="tm-head tm-head-stack">
            <div>
                <h2>{{ $temporaryModule->name }}</h2>
                <p>{{ $temporaryModule->description ?: 'Completa los datos requeridos para este modulo.' }}</p>
            </div>
            <div class="tm-inline-actions">
                @if ($tmImportable->isNotEmpty())
                    <button type="button" class="tm-btn tm-btn-primary" id="tmBtnImportarExcel" aria-haspopup="dialog">
                        Cargar Excel
                    </button>
                    <button type="button" class="tm-btn tm-btn-outline tm-btn-session-errors tm-hidden"
                            id="tmBtnSessionErrors-{{ $temporaryModule->id }}"
                            data-session-errors-module="{{ $temporaryModule->id }}"
                            title="Errores pendientes de importación">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span class="tm-error-count">0</span>
                    </button>
                @endif
                <a href="{{ route('temporary-modules.index') }}" class="tm-btn">Volver</a>
            </div>
        </div>

        <form action="{{ route('temporary-modules.submit', $temporaryModule->id) }}" method="POST" enctype="multipart/form-data" class="tm-form tm-entry-form">
            @csrf
            @if (!empty($editingEntry))
                <input type="hidden" name="entry_id" value="{{ $editingEntry->id }}">
                <div class="inline-alert inline-alert-success tm-col-full" role="status">
                    Completando registro #{{ $editingEntry->id }} (precargado). Puedes agregar o cambiar datos y guardar.
                    <a href="{{ route('temporary-modules.show', $temporaryModule->id) }}" class="tm-btn" style="margin-left:8px;">Cancelar / Nuevo registro</a>
                </div>
            @endif

            @php
                $microsAsignadas = ($microrregionesAsignadas ?? collect())->values();
                $mostrarSelectorMicrorregion = $microsAsignadas->count() > 1;
                $bloquearMicro = !empty($editingEntry);
            @endphp

            @if ($mostrarSelectorMicrorregion)
                <label class="tm-col-full">
                    Microrregion de captura *
                    <select id="tmMicrorregionSelector" name="selected_microrregion_id" required @disabled($bloquearMicro)>
                        @foreach ($microsAsignadas as $micro)
                            <option value="{{ $micro->id }}" @selected((int) $microrregionId === (int) $micro->id)>
                                MR {{ $micro->microrregion }} - {{ $micro->cabecera }}
                            </option>
                        @endforeach
                    </select>
                    @if ($bloquearMicro)
                        <input type="hidden" name="selected_microrregion_id" value="{{ $microrregionId }}">
                    @endif
                </label>
            @else
                <input type="hidden" name="selected_microrregion_id" value="{{ $microrregionId }}">
            @endif

            <div class="tm-grid tm-grid-2">
                @foreach ($fields as $field)
                    @php
                        $name = 'values['.$field->key.']';
                        $id = 'field_'.$field->key;
                        $value = old('values.'.$field->key, $editingEntry->data[$field->key] ?? null);
                        $existingImagePaths = is_array($value)
                            ? array_values(array_filter($value, fn ($path) => is_string($path) && trim($path) !== ''))
                            : ((is_string($value) && trim($value) !== '') ? [trim($value)] : []);
                        $hasExistingImages = count($existingImagePaths) > 0;
                        if ($field->type === 'boolean' && $value !== null && $value !== '') {
                            $value = $value === true || $value === 1 || $value === '1' ? '1' : '0';
                        }
                    @endphp
                    @if ($field->type === 'seccion')
                        @php
                            $secOpts = is_array($field->options) ? $field->options : [];
                            $secTitle = $secOpts['title'] ?? $field->label;
                            $secSubs = $secOpts['subsections'] ?? [];
                        @endphp
                        <div class="tm-entry-section-header tm-col-full" style="grid-column: 1 / -1;" role="group" aria-label="{{ $secTitle }}">
                            <h4 class="tm-section-title">{{ $secTitle }}</h4>
                            @if (count($secSubs) > 0)
                                <div class="tm-section-subsections">
                                    @foreach ($secSubs as $sub)
                                        <span class="tm-section-sub">{{ $sub }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        @continue
                    @endif
                    <label>
                        {{ $field->label }} {{ $field->is_required ? '*' : '' }}
                        @if (!empty($field->comment))
                            <small class="tm-field-help">{{ $field->comment }}</small>
                        @endif

                        @if ($field->type === 'categoria')
                            @php
                                $catOpts = is_array($field->options) ? $field->options : [];
                            @endphp
                            <select id="{{ $id }}" name="{{ $name }}" {{ $field->is_required ? 'required' : '' }} data-categoria-select>
                                <option value="">Selecciona categoría</option>
                                @foreach ($catOpts as $cat)
                                    @php
                                        $catName = $cat['name'] ?? '';
                                        $subs = $cat['sub'] ?? [];
                                    @endphp
                                    @if ($catName !== '')
                                        <option value="{{ $catName }}" @selected($value === $catName)>{{ $catName }}</option>
                                        @foreach ($subs as $sub)
                                            @php $subVal = $catName.' > '.$sub; @endphp
                                            <option value="{{ $subVal }}" @selected($value === $subVal)>{{ $catName }} &rarr; {{ $sub }}</option>
                                        @endforeach
                                    @endif
                                @endforeach
                            </select>
                        @elseif ($field->type === 'textarea')
                            <textarea id="{{ $id }}" name="{{ $name }}" rows="3" {{ $field->is_required ? 'required' : '' }}>{{ $value }}</textarea>
                        @elseif ($field->type === 'number')
                            <input id="{{ $id }}" type="number" step="any" name="{{ $name }}" value="{{ $value }}" {{ $field->is_required ? 'required' : '' }}>
                        @elseif ($field->type === 'date')
                            <input id="{{ $id }}" type="date" name="{{ $name }}" value="{{ $value }}" {{ $field->is_required ? 'required' : '' }}>
                        @elseif ($field->type === 'datetime')
                            <input id="{{ $id }}" type="datetime-local" name="{{ $name }}" value="{{ $value }}" {{ $field->is_required ? 'required' : '' }}>
                        @elseif ($field->type === 'select')
                            <select id="{{ $id }}" name="{{ $name }}" {{ $field->is_required ? 'required' : '' }}>
                                <option value="">Selecciona una opcion</option>
                                @foreach (($field->options ?? []) as $option)
                                    <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        @elseif ($field->type === 'delegado')
                            <select id="{{ $id }}" name="{{ $name }}" {{ $field->is_required ? 'required' : '' }}>
                                <option value="">Selecciona delegado</option>
                                @foreach ($delegados as $del)
                                    <option value="{{ $del->name }}" @selected($value === $del->name)>
                                        {{ $del->name }}
                                        @if(!empty($del->microrregion)) (MR {{ str_pad((string) $del->microrregion, 2, '0', STR_PAD_LEFT) }}) @endif
                                    </option>
                                @endforeach
                            </select>
                        @elseif ($field->type === 'municipio')
                            <select
                                id="{{ $id }}"
                                name="{{ $name }}"
                                class="tm-municipio-select"
                                data-field-key="{{ $field->key }}"
                                {{ $field->is_required ? 'required' : '' }}
                            >
                                <option value="">Selecciona un municipio</option>
                                @foreach ($municipios as $municipio)
                                    <option value="{{ $municipio }}" @selected($value === $municipio)>{{ $municipio }}</option>
                                @endforeach
                            </select>
                        @elseif ($field->type === 'boolean')
                            <select id="{{ $id }}" name="{{ $name }}" {{ $field->is_required ? 'required' : '' }}>
                                <option value="">Selecciona</option>
                                <option value="1" @selected($value === '1')>Si</option>
                                <option value="0" @selected($value === '0')>No</option>
                            </select>
                        @elseif ($field->type === 'semaforo')
                            <select id="{{ $id }}" name="{{ $name }}" class="tm-semaforo-select" {{ $field->is_required ? 'required' : '' }}>
                                <option value="">Selecciona nivel</option>
                                @foreach (\App\Services\TemporaryModules\TemporaryModuleFieldService::semaforoLabels() as $semVal => $semLabel)
                                    <option value="{{ $semVal }}" @selected($value === $semVal)>{{ $semLabel }}</option>
                                @endforeach
                            </select>
                        @elseif (in_array($field->type, ['image', 'file', 'document'], true))
                            <div class="tm-upload-evidence">
                                <div class="tm-upload-evidence-toolbar">
                                    <button type="button" class="tm-btn tm-btn-outline" data-upload-trigger data-target-input="{{ $id }}" aria-label="Cargar imagen">
                                        <i class="fa-solid fa-upload" aria-hidden="true"></i> Cargar
                                    </button>
                                    <button type="button" class="tm-btn tm-btn-outline" data-paste-image-button data-target-input="{{ $id }}" aria-label="Pegar imagen" title="Pegar imagen">
                                        <i class="fa-solid fa-paste" aria-hidden="true"></i> Pegar
                                    </button>
                                </div>
                                <small class="tm-upload-evidence-hint">Arrastra aquí o usa los botones.</smal                                 <div class="tm-upload-evidence-dropzone" data-paste-upload-wrap>
                                    <input id="{{ $id }}" type="file" accept="image/*" name="{{ $name }}[]" class="d-none" {{ ($field->is_required && !$hasExistingImages) ? 'required' : '' }} multiple data-max-files="2">
                                    <div class="tm-upload-evidence-placeholder">
                                        <i class="fa-solid fa-images" aria-hidden="true"></i>
                                        <p>Suelta las imágenes aquí (Máx. 2)</p>
                                    </div>
                                    <div class="tm-inline-image-preview-container" data-inline-image-preview-container style="display:flex; flex-wrap:wrap; gap:8px; width:100%; justify-content:center;">
                                        {{-- El JS insertara las previsualizaciones aqui --}}
                                    </div>
                                    @if ($hasExistingImages)
                                        <div class="tm-existing-images-note" style="margin-top:8px; font-size:12px; color:var(--clr-text-light); text-align:center;">
                                            <p><i class="fa-solid fa-circle-info"></i> Ya existen imágenes. Puedes agregar nuevas hasta un máximo de 2 en total.</p>
                                            <label style="display:inline-flex; align-items:center; gap:6px; color:var(--clr-primary); cursor:pointer;">
                                                <input type="checkbox" name="remove_images[{{ $field->key }}]" value="1">
                                                <span>Eliminar imágenes actuales</span>
                                            </label>
                                        </div>
                                    @endif
                                </div>
iv>
                                <small class="tm-paste-status" id="paste_status_{{ $id }}" aria-live="polite"></small>
                            </div>
                        @else
                            <input id="{{ $id }}" type="text" name="{{ $name }}" value="{{ $value }}" {{ $field->is_required ? 'required' : '' }}>
                        @endif
                    </label>
                @endforeach
            </div>

            <div class="tm-actions">
                <button type="submit" class="tm-btn tm-btn-primary">{{ !empty($editingEntry) ? 'Actualizar registro' : 'Guardar registro' }}</button>
            </div>
        </form>
    </article>

    @if ($tmImportable->isNotEmpty())
    <div class="tm-modal" id="tmImportarExcelModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="tmImportarExcelModalLabel">
        <div class="tm-modal-backdrop" data-tm-excel-close></div>
        <div class="tm-modal-dialog tm-excel-modal-dialog">
            <div class="tm-modal-head">
                <h3 id="tmImportarExcelModalLabel">Importar desde Excel / PDF</h3>
                <button type="button" class="tm-modal-close" data-tm-excel-close aria-label="Cerrar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="tm-modal-body tm-excel-modal-body">
                <div class="tm-excel-preview-container">
                    <!-- Lado Derecho (Desktop) / Superior (Mobile): Vista Previa -->
                    <div class="tm-excel-preview-side">
                        <div class="tm-excel-selection-info" id="tmExcelSelectionInfo">
                            <div><i class="fa-solid fa-info-circle"></i> <span id="tmExcelStepNote">Haz clic en una fila para marcar <strong>Encabezado</strong> y <strong>Datos</strong>.</span></div>
                            <div id="tmExcelSelectionStatus" class="tm-excel-selection-status">
                                <span class="tm-excel-badge badge-header" id="badgeHeaderRow" style="display:none;">Fila 1 (H)</span>
                                <span class="tm-excel-badge badge-data" id="badgeDataRow" style="display:none;">Desde Fila 2 (D)</span>
                            </div>
                        </div>
                        <div class="tm-excel-zoom-bar" id="tmExcelZoomBar" style="display:none;">
                            <button type="button" class="tm-excel-zoom-btn" id="tmExcelZoomOut" title="Alejar (Ctrl + Rueda abajo)"><i class="fa-solid fa-minus"></i></button>
                            <span class="tm-excel-zoom-info" id="tmExcelZoomVal">100%</span>
                            <button type="button" class="tm-excel-zoom-btn" id="tmExcelZoomIn" title="Acercar (Ctrl + Rueda arriba)"><i class="fa-solid fa-plus"></i></button>
                            <button type="button" class="tm-excel-zoom-btn" id="tmExcelZoomReset" title="Restablecer"><i class="fa-solid fa-rotate-left"></i></button>
                        </div>
                        <div class="tm-excel-sheet-tabs-wrap" id="tmExcelSheetTabsWrap" style="display:none;">
                            <button type="button" class="tm-excel-sheet-arrow tm-excel-sheet-arrow--left" id="tmSheetArrowLeft" title="Anterior"><i class="fa-solid fa-chevron-left"></i></button>
                            <div class="tm-excel-sheet-tabs" id="tmExcelSheetTabs"></div>
                            <button type="button" class="tm-excel-sheet-arrow tm-excel-sheet-arrow--right" id="tmSheetArrowRight" title="Siguiente"><i class="fa-solid fa-chevron-right"></i></button>
                        </div>
                        <div class="tm-excel-sheet-wrapper" id="tmExcelPreviewTableWrap">
                            <div class="tm-excel-sheet-inner" id="tmExcelSheetInner">
                                <div style="padding:60px; text-align:center; color:var(--clr-text-light);">
                                    <i class="fa-solid fa-file-excel" style="font-size:4rem; margin-bottom:16px; opacity:0.2;"></i>
                                    <p style="font-weight:600;">Vista previa del documento</p>
                                    <p style="font-size:0.85rem; opacity:0.7;">Carga un archivo Excel para comenzar a marcar las columnas.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lado Izquierdo (Desktop) / Inferior (Mobile): Controles -->
                    <div class="tm-excel-controls-side">
                        <!-- PASO 1: Selección de filas -->
                        <div id="tmExcelStep1" class="tm-form">
                            <div class="tm-excel-dropzone" id="tmExcelDropzone">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <p>Arrastra tu archivo aquí o haz clic para buscar</p>
                                <small>Formatos aceptados: .xlsx, .xls, .csv, .pdf</small>
                                <input type="file" id="tmExcelFile" accept=".xlsx,.xls,.csv,.pdf" hidden>
                            </div>
                            <div id="tmExcelFileName" class="tm-excel-badge badge-header tm-hidden" style="margin-bottom:10px; padding:6px 12px; border-radius:8px;"></div>

                            <div class="tm-excel-grid">
                                <label>Encabezados (Fila)
                                    <input type="number" id="tmExcelHeaderRow" value="1" min="1" class="tm-input">
                                </label>
                                <label>Datos (Desde fila)
                                    <input type="number" id="tmExcelDataStartRow" value="2" min="2" class="tm-input">
                                </label>
                            </div>

                            <label class="tm-excel-search-all-wrap" id="tmExcelSearchAllWrap">
                                <input type="checkbox" id="tmExcelSearchAll">
                                <span>Buscar en todos los municipios de mis microrregiones</span>
                            </label>

                            <label id="tmExcelMicrorregionLabel" style="margin-top:8px;">
                                Microregión de destino
                                <select id="tmExcelMicrorregionId" class="tm-input">
                                    @foreach (($microrregionesAsignadas ?? collect()) as $micro)
                                        <option value="{{ $micro->id }}" @selected((int) $microrregionId === (int) $micro->id)>MR {{ $micro->microrregion }} — {{ $micro->cabecera }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label id="tmExcelMunicipioLabel" style="margin-top:8px;">
                                Municipio de destino (cuando no se mapea columna Municipio)
                                <select id="tmExcelSelectedMunicipio" class="tm-input">
                                    <option value="">Selecciona un municipio</option>
                                </select>
                            </label>

                            <div id="tmExcelPreviewErr" class="inline-alert inline-alert-error tm-hidden" role="alert" style="margin-top:10px;"></div>
                            <div id="tmExcelDetectNote" class="inline-alert inline-alert-success tm-hidden" style="margin-top:10px;" role="status"></div>

                            <div class="tm-actions" style="margin-top:20px; flex-direction:column; align-items:stretch; gap:10px;">
                                <button type="button" class="tm-btn tm-btn-primary" id="tmExcelLeerColumnas">
                                    <i class="fa-solid fa-table-columns"></i> Leer secciones y mapear
                                </button>
                                <button type="button" class="tm-btn tm-btn-outline" id="tmExcelAutoDetect" style="display:none;">
                                    <i class="fa-solid fa-magic"></i> Marcar todo el documento
                                </button>
                            </div>
                        </div>

                        <!-- PASO 2: Mapeo de columnas (Integrado) -->
                        <div id="tmExcelStep2" class="tm-hidden" style="display:flex; flex-direction:column; padding-top:20px; border-top: 1px dashed var(--clr-border); margin-top:20px;">
                            <h4 class="tm-excel-step-title">Relacionar columnas</h4>
                                <div class="tm-excel-mode-tabs" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:10px;">
                                    <button type="button" class="tm-btn tm-btn-primary tm-excel-mode-tab is-active" data-excel-mode="import">Importar</button>
                                    <button type="button" class="tm-btn tm-btn-outline tm-excel-mode-tab" data-excel-mode="update">Actualizar</button>
                                </div>

                                <div class="tm-excel-mode-panel tm-excel-mode-panel-import" data-excel-mode-panel="import">
                                    <div class="inline-alert inline-alert-success" style="margin-bottom:10px;">Solo registra filas nuevas; si detecta duplicados no las sube y muestra sugerencias.</div>
                                    <label class="tm-excel-search-all-wrap" id="tmExcelAutoMunicipioWrap" style="margin-bottom:10px;">
                                        <input type="checkbox" id="tmExcelAutoIdentifyMunicipio" checked>
                                        <span>Identificar municipio automáticamente (por columna Municipio)</span>
                                    </label>
                                    <div class="tm-table-wrap" style="margin-bottom:12px; border:1px solid var(--clr-border); border-radius:8px; background: rgba(0,0,0,0.02);">
                                        <table class="tm-table tm-table-sm tm-excel-map-table" style="font-size:12px; width:100%;">
                                            <thead><tr><th style="padding:10px 8px;">Campo del módulo</th><th style="padding:10px 8px;">Columna del Excel</th></tr></thead>
                                            <tbody id="tmExcelMapBodyImport"></tbody>
                                        </table>
                                    </div>
                                    <div class="tm-actions" style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                        <button type="button" class="tm-btn" id="tmExcelVolver">Restablecer</button>
                                        <button type="button" class="tm-btn tm-btn-primary" id="tmExcelImportar">Importar filas</button>
                                    </div>
                                </div>

                                <div class="tm-excel-mode-panel tm-excel-mode-panel-update tm-hidden" data-excel-mode-panel="update">
                                    <div class="inline-alert inline-alert-success" style="margin-bottom:10px;">Define columnas base para buscar coincidencias y columnas a actualizar para aplicar cambios.</div>
                                    <label class="tm-excel-search-all-wrap" style="margin-bottom:10px;">
                                        <input type="checkbox" id="tmExcelAutoIdentifyMunicipioUpdate" checked>
                                        <span>Identificar municipio automáticamente (por columna Municipio)</span>
                                    </label>
                                    <div class="tm-table-wrap" style="margin-bottom:12px; border:1px solid var(--clr-border); border-radius:8px; background: rgba(0,0,0,0.02);">
                                        <table class="tm-table tm-table-sm tm-excel-map-table" style="font-size:12px; width:100%;">
                                            <thead><tr><th style="padding:10px 8px;">Campo del módulo</th><th style="padding:10px 8px;">Configuración de actualización</th></tr></thead>
                                            <tbody id="tmExcelMapBodyUpdate"></tbody>
                                        </table>
                                    </div>
                                    <div class="tm-actions" style="display:grid; grid-template-columns:1fr; gap:10px; margin-top:8px;">
                                        <button type="button" class="tm-btn tm-btn-outline" id="tmExcelActualizarExistentes" title="Busca por columnas base y actualiza solo columnas marcadas">
                                            <i class="fa-solid fa-arrows-rotate"></i> Actualizar registros existentes
                                        </button>
                                    </div>
                                </div>
                            <div id="tmExcelImportErr" class="inline-alert inline-alert-error tm-hidden" role="alert"></div>
                            <div id="tmExcelImportOk" class="inline-alert inline-alert-success tm-hidden" role="alert"></div>

                            <!-- SECCIÓN DE ERRORES (Logs) -->
                            <div id="tmExcelErrorsSection" class="tm-hidden" style="margin-top:20px; padding-top:20px; border-top:1px solid var(--clr-border);">
                                <h4 class="tm-excel-step-title" style="color:var(--clr-primary); border-bottom-color:var(--clr-primary);">Errores de Importación</h4>
                                <p style="font-size:0.85rem; color:var(--clr-text-light); margin-bottom:15px;">Las siguientes filas no se pudieron importar. Puedes corregirlas seleccionando una sugerencia:</p>
                                <div id="tmExcelErrorsList" style="display:grid; gap:12px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <article class="content-card tm-card" id="tmRecentRecords">
        <h2>Mis registros recientes</h2>
        <div class="tm-table-wrap">
            <table class="tm-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        @foreach ($fields as $field)
                            <th>{{ $field->label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td>
                                {{ optional($entry->submitted_at)->format('d/m/Y H:i') }}
                                <br>
                                <a href="{{ route('temporary-modules.show', ['module' => $temporaryModule->id, 'entry' => $entry->id]) }}" class="tm-btn tm-btn-outline" style="margin-top:6px;font-size:12px;padding:4px 8px;">Completar</a>
                            </td>
                            @foreach ($fields as $field)
                                @php
                                    $cell = $entry->data[$field->key] ?? null;
                                                          <td>
                                    @php
                                        $isImageField = in_array($field->type, ['file', 'image', 'document'], true);
                                        $images = is_array($cell) ? $cell : ($cell ? [(string)$cell] : []);
                                    @endphp

                                    @if ($isImageField && count($images) > 0)
                                        <div style="display:flex; flex-direction:column; gap:4px;">
                                            @foreach ($images as $index => $img)
                                                <button
                                                    type="button"
                                                    class="tm-thumb-link"
                                                    data-open-image-preview
                                                    data-image-src="{{ route('temporary-modules.entry-file.preview', ['module' => $temporaryModule->id, 'entry' => $entry->id, 'fieldKey' => $field->key, 'i' => $index]) }}"
                                                    data-image-title="{{ $field->label }} ({{ $index + 1 }})"
                                                    title="Ver imagen {{ $index + 1 }}"
                                                >
                                                    <i class="fa fa-image" aria-hidden="true"></i> Imagen {{ $index + 1 }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @elseif (is_bool($cell))
                                        {{ $cell ? 'Si' : 'No' }}
                                    @elseif ($field->type === 'semaforo' && is_string($cell) && $cell !== '')
                                        @php $semLab = \App\Services\TemporaryModules\TemporaryModuleFieldService::labelForSemaforo($cell); @endphp
                                        <span class="tm-semaforo-badge tm-semaforo-badge--{{ $cell }}" title="{{ $semLab }}">{{ $semLab }}</span>
                                    @else
                                        {{ $cell ?? '-' }}
                                    @endif
                                </td>
         </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $fields->count() + 1 }}">Aun no tienes registros en este modulo.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>

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
</section>
@endsection

@push('scripts')
@php
    $tmShowMunicipiosPorMrForJs = ($microrregionesAsignadas ?? collect())->mapWithKeys(function ($micro) {
        return [(string) $micro->id => array_values($micro->municipios ?? [])];
    })->all();
    $tmExcelPreviewUrlForJs = route('temporary-modules.import-excel-preview', $temporaryModule->id);
    $tmExcelImportUrlForJs = route('temporary-modules.import-excel', $temporaryModule->id);
    $tmExcelUpdateUrlForJs = route('temporary-modules.update-from-excel', $temporaryModule->id);
    $tmExcelImportSingleUrlForJs = route('temporary-modules.import-single-row', $temporaryModule->id);
    $tmCsrfTokenForJs = csrf_token();
    $tmCsrfRefreshUrlForJs = route('csrf.refresh');
    $tmLoginUrlForJs = route('login');
@endphp
<script>
window.TM_DELEGATE_SHOW_BOOT = @json([
    'moduleId' => (string) $temporaryModule->id,
    'municipiosPorMicrorregion' => $tmShowMunicipiosPorMrForJs,
    'excelPreviewUrl' => $tmExcelPreviewUrlForJs,
    'excelImportUrl' => $tmExcelImportUrlForJs,
    'excelUpdateUrl' => $tmExcelUpdateUrlForJs,
    'excelImportSingleUrl' => $tmExcelImportSingleUrlForJs,
    'csrfToken' => $tmCsrfTokenForJs,
    'csrfRefreshUrl' => $tmCsrfRefreshUrlForJs,
    'loginUrl' => $tmLoginUrlForJs,
]);
</script>
<script src="{{ asset('assets/js/modules/temporary-modules-delegate-show.js') }}?v={{ @filemtime(public_path('assets/js/modules/temporary-modules-delegate-show.js')) ?: time() }}"></script>
<div id="tmGlobalExcelDropOverlay" class="tm-global-drop-overlay">
    <div class="tm-global-drop-content">
        <i class="fa-solid fa-file-arrow-up"></i>
        <h3>¡Suelta tu archivo aquí!</h3>
        <p>Arrastra tu archivo Excel o PDF a cualquier área dentro de la página para comenzar la importación.</p>
    </div>
</div>
@endpush
