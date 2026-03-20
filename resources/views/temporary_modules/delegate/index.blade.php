@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
@endpush

@php
    $hidePageHeader = true;
@endphp

@section('content')
<section class="tm-page tm-shell app-density-compact">
    <div class="tm-shell-main">
        @php
            $activeSection = $activeSection ?? 'upload';
            $isUploadSection = $activeSection !== 'records';
            $microsAsignadas = ($microrregionesAsignadas ?? collect())->values();
            $mostrarSelectorMicrorregion = $microsAsignadas->count() > 1;
        @endphp
        <header class="tm-shell-head tm-shell-head--with-tabs">
            <div class="tm-shell-head-text">
                <h1 class="tm-shell-title">Eventos temporales</h1>
                <p class="tm-shell-desc">Sube información a los módulos asignados y consulta tus registros y evidencias.</p>
            </div>
            <div class="tm-section-switch" role="tablist" aria-label="Cambiar vista de captura temporal">
                <button type="button" class="tm-section-tab {{ $isUploadSection ? 'is-active' : '' }}" data-section-tab data-section-target="tmUploadView" role="tab" aria-selected="{{ $isUploadSection ? 'true' : 'false' }}">Subir informacion</button>
                <button type="button" class="tm-section-tab {{ !$isUploadSection ? 'is-active' : '' }}" data-section-tab data-section-target="tmRecordsView" role="tab" aria-selected="{{ !$isUploadSection ? 'true' : 'false' }}">Ver mis registros</button>
            </div>
        </header>

        @if (session('status'))
            <div class="inline-alert inline-alert-success" role="alert">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="inline-alert inline-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

    <section class="tm-section-panel {{ $isUploadSection ? 'is-active' : '' }}" id="tmUploadView" role="tabpanel" aria-hidden="{{ $isUploadSection ? 'false' : 'true' }}">
        @include('temporary_modules.delegate.partials.upload_modules', ['modules' => $modules, 'fragmentUploadUrl' => $fragmentUploadUrl ?? '#'])
    </section>

    @foreach ($modules as $module)
        @php
            $tmImportable = $module->fields->filter(fn ($f) => in_array($f->type, \App\Services\TemporaryModules\TemporaryModuleExcelImportService::IMPORTABLE_TYPES, true));
        @endphp
        <div class="tm-modal" id="delegate-preview-{{ $module->id }}" aria-hidden="true" role="dialog" aria-modal="true">
            <div class="tm-modal-backdrop" data-close-module-preview></div>
            <div class="tm-modal-dialog tm-modal-dialog-entry">
                <div class="tm-modal-head">
                    <div class="tm-modal-head-stack">
                        <h3>Registro de modulo temporal</h3>
                        <p class="tm-modal-subtitle">{{ $module->name }}</p>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        @if ($tmImportable->isNotEmpty())
                            <a href="{{ route('temporary-modules.download-template', $module->id) }}"
                               class="tm-btn tm-btn-outline"
                               aria-label="Descargar plantilla Excel">
                                <i class="fa-solid fa-download" aria-hidden="true"></i> Plantilla
                            </a>
                            <button type="button"
                                    class="tm-btn tm-btn-outline"
                                    data-open-excel-import="tmImportarExcelModal-{{ $module->id }}"
                                    aria-label="Importar Excel">
                                <i class="fa-regular fa-file-excel" aria-hidden="true"></i> Importar Excel
                            </button>
                        @endif
                        <button type="button" class="tm-modal-close" data-close-module-preview aria-label="Cerrar">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="tm-modal-body">
                    <p class="tm-entry-subtitle">{{ $module->description ?: 'Completa los datos requeridos para este modulo.' }}</p>

                    @php
                        $savedData = [];
                        $orderedFields = $module->fields
                            ->sortBy(function ($field) {
                                return in_array($field->type, ['image', 'file'], true) ? 1 : 0;
                            })
                            ->values();
                        $mediaDividerPrinted = false;
                    @endphp

                    <form action="{{ route('temporary-modules.submit', $module->id) }}" method="POST" enctype="multipart/form-data" class="tm-form tm-entry-form">
                        @csrf
                        @if ($microsAsignadas->isNotEmpty() && !$mostrarSelectorMicrorregion)
                            <input type="hidden" name="selected_microrregion_id" value="{{ $microsAsignadas->first()->id }}">
                        @endif
                        <input type="hidden" name="entry_id" value="">

                        <div class="tm-grid tm-grid-2 tm-entry-grid">
                            @if ($mostrarSelectorMicrorregion)
                                <label class="tm-entry-field">
                                    Microrregion de captura *
                                    <select name="selected_microrregion_id" class="tm-mr-selector" required>
                                        @foreach ($microsAsignadas as $micro)
                                            <option value="{{ $micro->id }}" @selected($loop->first)>
                                                MR {{ $micro->microrregion }} - {{ $micro->cabecera }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                            @foreach ($orderedFields as $field)
                                @php
                                    $name = 'values['.$field->key.']';
                                    $id = 'field_'.$module->id.'_'.$field->key;
                                    $value = old('values.'.$field->key, $savedData[$field->key] ?? null);
                                    $isMediaField = in_array($field->type, ['image', 'file'], true);
                                @endphp
                                @if ($field->type === 'seccion')
                                    @php
                                        $secOpts = is_array($field->options) ? $field->options : [];
                                        $secTitle = $secOpts['title'] ?? $field->label;
                                        $secSubs = $secOpts['subsections'] ?? [];
                                    @endphp
                                    <div class="tm-entry-section-header tm-col-full" role="group" aria-label="{{ $secTitle }}">
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
                                @if ($isMediaField && !$mediaDividerPrinted)
                                    <div class="tm-form-divider tm-col-full">
                                        <span>Evidencias</span>
                                    </div>
                                    @php $mediaDividerPrinted = true; @endphp
                                @endif

                                <label class="tm-entry-field {{ $isMediaField ? 'is-media' : '' }}">
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
                                    @elseif ($field->type === 'municipio')
                                        <select id="{{ $id }}" name="{{ $name }}" class="tm-municipio-select" {{ $field->is_required ? 'required' : '' }}>
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
                                    @elseif (in_array($field->type, ['image', 'file'], true))
                                        <div class="tm-upload-evidence">
                                            <div class="tm-upload-evidence-toolbar">
                                                <button type="button" class="tm-btn tm-btn-outline" data-upload-trigger data-target-input="{{ $id }}" aria-label="Cargar imagen">
                                                    <i class="fa-solid fa-upload" aria-hidden="true"></i> Cargar
                                                </button>
                                                <button type="button" class="tm-btn tm-btn-outline" data-paste-image-button data-target-input="{{ $id }}" aria-label="Pegar imagen" title="Pegar imagen">
                                                    <i class="fa-solid fa-paste" aria-hidden="true"></i> Pegar
                                                </button>
                                            </div>
                                            <small class="tm-upload-evidence-hint">Arrastra aquí o usa los botones.</small>
                                            <div class="tm-upload-evidence-dropzone" data-paste-upload-wrap>
                                                <input id="{{ $id }}" type="file" accept="image/*" name="{{ $name }}" class="d-none" {{ $field->is_required ? 'required' : '' }}>
                                                <div class="tm-upload-evidence-placeholder">
                                                    <i class="fa-solid fa-images" aria-hidden="true"></i>
                                                    <p>Suelta la imagen aquí</p>
                                                </div>
                                                <div class="tm-image-preview" data-image-preview hidden>
                                                    <img src="" alt="Vista previa" data-image-preview-img>
                                                    <button type="button" class="tm-image-clear" data-image-remove aria-label="Quitar imagen">&times;</button>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <input id="{{ $id }}" type="text" name="{{ $name }}" value="{{ $value }}" {{ $field->is_required ? 'required' : '' }}>
                                    @endif
                                </label>
                            @endforeach

                        </div>

                        <div class="tm-actions">
                            <button type="submit" class="tm-btn tm-btn-primary">Guardar registro</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if ($tmImportable->isNotEmpty())
            <div class="tm-modal tm-excel-import-modal" id="tmImportarExcelModal-{{ $module->id }}" aria-hidden="true" role="dialog" aria-modal="true"
                 aria-labelledby="tmImportarExcelModalLabel-{{ $module->id }}"
                 data-excel-preview-url="{{ route('temporary-modules.import-excel-preview', $module->id) }}"
                 data-excel-import-url="{{ route('temporary-modules.import-excel', $module->id) }}">
                <div class="tm-modal-backdrop" data-close-module-preview></div>
                <div class="tm-modal-dialog tm-modal-dialog-entry tm-excel-modal-dialog">
                    <div class="tm-modal-head">
                        <h3 id="tmImportarExcelModalLabel-{{ $module->id }}">Importar desde Excel</h3>
                        <button type="button" class="tm-modal-close" data-close-module-preview aria-label="Cerrar">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="tm-modal-body tm-excel-modal-body">
                        <p class="tm-field-help" style="margin-bottom:12px;">
                            Si el archivo trae totales o títulos arriba, al leer columnas se <strong>detecta</strong> la fila de la tabla (MUNICIPIO, MICROREGION, ACCION…).
                            Luego asocia cada campo del módulo con una columna.
                        </p>

                        <div class="tm-excel-step1">
                            <div class="tm-excel-grid">
                                <label>Fila encabezados
                                    <input type="number" class="tm-excel-header-row" value="1" min="1" max="500">
                                </label>
                                <label>Primera fila datos
                                    <input type="number" class="tm-excel-data-start-row" value="2" min="2" max="50000">
                                </label>
                                @if ($microsAsignadas->count() > 1)
                                    <label>Microregión
                                        <select class="tm-excel-mr-input tm-input" name="selected_microrregion_id">
                                            @foreach ($microsAsignadas as $micro)
                                                <option value="{{ $micro->id }}" @selected($loop->first)>
                                                    MR {{ $micro->microrregion }} — {{ $micro->cabecera }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>
                                @else
                                    @if ($microsAsignadas->count() === 1)
                                        <input type="hidden" class="tm-excel-mr-input" name="selected_microrregion_id" value="{{ $microsAsignadas->first()->id }}">
                                    @endif
                                @endif
                            </div>

                            <label class="tm-col-full" style="margin-top:10px;">Archivo
                                <input type="file" class="tm-excel-file-input" accept=".xlsx,.xls">
                            </label>

                            <div class="inline-alert inline-alert-error tm-hidden tm-excel-preview-err" role="alert"></div>
                            <div class="inline-alert inline-alert-success tm-hidden tm-excel-detect-note" style="margin-top:8px;" role="status"></div>

                            <div class="tm-actions" style="margin-top:10px;">
                                <button type="button" class="tm-btn tm-btn-primary tm-excel-read-columns">
                                    Leer columnas
                                </button>
                            </div>
                        </div>

                        <div class="tm-excel-step2 tm-hidden">
                            <div class="tm-table-wrap tm-excel-map-table-wrap">
                                <table class="tm-table tm-table-sm">
                                    <thead>
                                        <tr>
                                            <th>Encabezado (Excel)</th>
                                            <th>Asignar a campo del módulo</th>
                                        </tr>
                                    </thead>
                                    <tbody class="tm-excel-map-body"></tbody>
                                </table>
                            </div>

                            <div class="inline-alert inline-alert-error tm-hidden tm-excel-import-err" role="alert"></div>
                            <div class="inline-alert inline-alert-success tm-hidden tm-excel-import-ok" role="alert"></div>

                            <div class="tm-actions" style="margin-top:10px;">
                                <button type="button" class="tm-btn tm-excel-back">Volver</button>
                                <button type="button" class="tm-btn tm-btn-primary tm-excel-importar" style="margin-left:8px;">
                                    Importar filas
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @foreach ($module->getRelation('myEntries') as $entry)
            @php
                $entryMicrorregion = ($microrregionesAsignadas ?? collect())->firstWhere('id', $entry->microrregion_id);
                $entryMunicipios = $entryMicrorregion && isset($entryMicrorregion->municipios) ? array_values($entryMicrorregion->municipios) : ($municipios ?? []);
            @endphp
            <div class="tm-modal" id="delegate-edit-{{ $entry->id }}" aria-hidden="true" role="dialog" aria-modal="true">
                <div class="tm-modal-backdrop" data-close-module-preview></div>
                <div class="tm-modal-dialog tm-modal-dialog-entry">
                    <div class="tm-modal-head">
                        <div class="tm-modal-head-stack">
                            <h3>Editar registro</h3>
                            <p class="tm-modal-subtitle">{{ $module->name }}</p>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            @if ($tmImportable->isNotEmpty())
                                <a href="{{ route('temporary-modules.download-template', $module->id) }}"
                                   class="tm-btn tm-btn-outline"
                                   aria-label="Descargar plantilla Excel">
                                    <i class="fa-solid fa-download" aria-hidden="true"></i> Plantilla
                                </a>
                                <button type="button"
                                        class="tm-btn tm-btn-outline"
                                        data-open-excel-import="tmImportarExcelModal-{{ $module->id }}"
                                        aria-label="Importar Excel">
                                    <i class="fa-regular fa-file-excel" aria-hidden="true"></i> Importar Excel
                                </button>
                            @endif
                            <button type="button" class="tm-modal-close" data-close-module-preview aria-label="Cerrar">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="tm-modal-body">
                        @php
                            $orderedFields = $module->fields
                                ->sortBy(function ($field) {
                                    return in_array($field->type, ['image', 'file'], true) ? 1 : 0;
                                })
                                ->values();
                            $mediaDividerPrinted = false;
                        @endphp

                        <form action="{{ route('temporary-modules.submit', $module->id) }}" method="POST" enctype="multipart/form-data" class="tm-form tm-entry-form">
                            @csrf
                            @if ($microsAsignadas->isNotEmpty() && !$mostrarSelectorMicrorregion)
                                <input type="hidden" name="selected_microrregion_id" value="{{ $entry->microrregion_id ?? $microsAsignadas->first()->id }}">
                            @endif
                            <input type="hidden" name="entry_id" value="{{ $entry->id }}">

                            <div class="tm-grid tm-grid-2 tm-entry-grid">
                                @if ($mostrarSelectorMicrorregion)
                                    <label class="tm-entry-field">
                                        Microrregion de captura *
                                        <select name="selected_microrregion_id" class="tm-mr-selector" required>
                                            @foreach ($microsAsignadas as $micro)
                                                <option value="{{ $micro->id }}" @selected((int) ($entry->microrregion_id ?? 0) === (int) $micro->id)>
                                                    MR {{ $micro->microrregion }} - {{ $micro->cabecera }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endif
                                @foreach ($orderedFields as $field)
                                    @php
                                        $name = 'values['.$field->key.']';
                                        $id = 'edit_'.$entry->id.'_'.$field->key;
                                        $value = old('values.'.$field->key, $entry->data[$field->key] ?? null);
                                        $isMediaField = in_array($field->type, ['image', 'file'], true);
                                        $hasExistingImage = is_string($entry->data[$field->key] ?? null) && trim((string) ($entry->data[$field->key] ?? '')) !== '';
                                    @endphp
                                    @if ($field->type === 'seccion')
                                        @php
                                            $secOpts = is_array($field->options) ? $field->options : [];
                                            $secTitle = $secOpts['title'] ?? $field->label;
                                            $secSubs = $secOpts['subsections'] ?? [];
                                        @endphp
                                        <div class="tm-entry-section-header tm-col-full" role="group" aria-label="{{ $secTitle }}">
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
                                    @if ($isMediaField && !$mediaDividerPrinted)
                                        <div class="tm-form-divider tm-col-full">
                                            <span>Evidencias</span>
                                        </div>
                                        @php $mediaDividerPrinted = true; @endphp
                                    @endif

                                    <label class="tm-entry-field {{ $isMediaField ? 'is-media' : '' }}">
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
                                        @elseif ($field->type === 'municipio')
                                            <select id="{{ $id }}" name="{{ $name }}" class="tm-municipio-select" {{ $field->is_required ? 'required' : '' }}>
                                                <option value="">Selecciona un municipio</option>
                                                @foreach ($entryMunicipios as $municipio)
                                                    <option value="{{ $municipio }}" @selected($value === $municipio)>{{ $municipio }}</option>
                                                @endforeach
                                            </select>
                                        @elseif ($field->type === 'boolean')
                                            <select id="{{ $id }}" name="{{ $name }}" {{ $field->is_required ? 'required' : '' }}>
                                                <option value="">Selecciona</option>
                                                <option value="1" @selected((string) $value === '1')>Si</option>
                                                <option value="0" @selected((string) $value === '0')>No</option>
                                            </select>
                                        @elseif (in_array($field->type, ['image', 'file'], true))
                                            <div class="tm-upload-evidence">
                                                <div class="tm-upload-evidence-toolbar">
                                                    <button type="button" class="tm-btn tm-btn-outline" data-upload-trigger data-target-input="{{ $id }}" aria-label="Cargar imagen">
                                                        <i class="fa-solid fa-upload" aria-hidden="true"></i> Cargar
                                                    </button>
                                                    <button type="button" class="tm-btn tm-btn-outline" data-paste-image-button data-target-input="{{ $id }}" aria-label="Pegar imagen" title="Pegar imagen">
                                                        <i class="fa-solid fa-paste" aria-hidden="true"></i> Pegar
                                                    </button>
                                                </div>
                                                <input type="hidden" name="remove_images[{{ $field->key }}]" value="0" data-remove-flag>
                                                <small class="tm-upload-evidence-hint">Arrastra aquí o usa los botones.</small>
                                                <div class="tm-upload-evidence-dropzone" data-paste-upload-wrap>
                                                    <input id="{{ $id }}" type="file" accept="image/*" name="{{ $name }}" class="d-none" {{ ($field->is_required && !$hasExistingImage) ? 'required' : '' }}>
                                                    <div class="tm-upload-evidence-placeholder">
                                                        <i class="fa-solid fa-images" aria-hidden="true"></i>
                                                        <p>Suelta la imagen aquí</p>
                                                    </div>
                                                    <div class="tm-image-preview" data-image-preview {{ is_string($value) && $value !== '' ? '' : 'hidden' }}>
                                                        <img
                                                            src="{{ is_string($value) && $value !== '' ? route('temporary-modules.entry-file.preview', ['module' => $module->id, 'entry' => $entry->id, 'fieldKey' => $field->key]) : '' }}"
                                                            alt="{{ $field->label }}"
                                                            data-image-preview-img
                                                        >
                                                        <button type="button" class="tm-image-clear" data-image-remove aria-label="Quitar imagen">&times;</button>
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            <input id="{{ $id }}" type="text" name="{{ $name }}" value="{{ $value }}" {{ $field->is_required ? 'required' : '' }}>
                                        @endif
                                    </label>
                                @endforeach

                            </div>

                            <div class="tm-actions">
                                <button type="submit" class="tm-btn tm-btn-primary">Guardar cambios</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endforeach

    <section class="tm-section-panel {{ !$isUploadSection ? 'is-active' : '' }}" id="tmRecordsView" role="tabpanel" aria-hidden="{{ !$isUploadSection ? 'false' : 'true' }}" data-records-url="{{ route('temporary-modules.records') }}" data-fragment-records-url="{{ $fragmentRecordsUrl ?? '' }}">
    @if ($modules->isNotEmpty())
        <article class="content-card tm-card tm-card-in-shell tm-records-container">
            <div class="tm-module-filters-row" data-tm-module-chips-row>
                <button type="button" class="tm-module-filters-nav tm-module-filters-nav--prev" data-tm-module-chips-prev aria-label="Módulos anteriores" disabled>
                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                </button>
                <div class="tm-module-filters-track" data-tm-module-chips-track>
                    <div class="tm-module-filters" role="tablist" aria-label="Filtrar por modulo temporal">
                        @foreach ($modules as $module)
                            @php $isModuleActive = (int) ($activeModuleId ?? 0) === (int) $module->id || ((int) ($activeModuleId ?? 0) === 0 && $loop->first); @endphp
                            <button
                                type="button"
                                class="tm-module-chip {{ $isModuleActive ? 'is-active' : '' }}"
                                data-module-filter
                                data-module-target="module-records-{{ $module->id }}"
                                role="tab"
                                aria-selected="{{ $isModuleActive ? 'true' : 'false' }}"
                            >
                                {{ $module->name }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <button type="button" class="tm-module-filters-nav tm-module-filters-nav--next" data-tm-module-chips-next aria-label="Módulos siguientes">
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </button>
            </div>

            <div class="tm-module-records-panels">
                @foreach ($modules as $module)
                    @php
                        $municipioField = $module->fields->firstWhere('type', 'municipio');
                        $isModuleActive = (int) ($activeModuleId ?? 0) === (int) $module->id || ((int) ($activeModuleId ?? 0) === 0 && $loop->first);
                        $entries = $isModuleActive && isset($myEntries) ? $myEntries : $module->getRelation('myEntries');
                    @endphp
                    <section
                        class="tm-module-records-panel {{ $isModuleActive ? 'is-active' : '' }}"
                        id="module-records-{{ $module->id }}"
                        role="tabpanel"
                        aria-hidden="{{ $isModuleActive ? 'false' : 'true' }}"
                    >
                        <div class="tm-records-filters" data-tm-records-filters>
                            <div class="tm-records-filters-row">
                                <label class="tm-records-filter-field tm-records-filter-field--search">
                                    <span>Buscar</span>
                                    <span class="tm-records-search-wrap">
                                        <input
                                            type="search"
                                            class="tm-records-filter-input"
                                            data-tm-filter-buscar
                                            value="{{ $isModuleActive ? e(request('buscar', '')) : '' }}"
                                            placeholder="Texto en los datos del registro…"
                                            autocomplete="off"
                                        >
                                        <button type="button" class="tm-records-search-clear" data-tm-filter-buscar-clear aria-label="Quitar texto" @if (! $isModuleActive || trim((string) request('buscar', '')) === '') hidden @endif>
                                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                        </button>
                                    </span>
                                </label>
                                <label class="tm-records-filter-field">
                                    <span>Microregión</span>
                                    <select class="tm-records-filter-select" data-tm-filter-microrregion>
                                        <option value="">Todos</option>
                                        @foreach ($microrregionesAsignadas ?? [] as $micro)
                                            <option value="{{ $micro->id }}" @selected($isModuleActive && (int) request('microrregion_id') === (int) $micro->id)>
                                                MR {{ $micro->microrregion }} — {{ $micro->cabecera }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                        </div>
                        @php
                            $recordsLoaded = $isModuleActive && isset($myEntries);
                        @endphp
                        <div
                            class="tm-records-fragment-host"
                            data-fragment-url="{{ $fragmentRecordsUrl ?? '' }}"
                            data-module-id="{{ $module->id }}"
                            @unless ($recordsLoaded) hidden @endunless
                        >
                            @if ($recordsLoaded)
                                @include('temporary_modules.delegate.partials.records_entries', [
                                    'module' => $module,
                                    'entries' => $myEntries,
                                    'municipioField' => $municipioField,
                                ])
                            @endif
                        </div>
                        <div class="tm-records-panel-placeholder" @if ($recordsLoaded) hidden @endif>
                            <p class="tm-module-subtitle">{{ $module->name }}</p>
                            @unless ($recordsLoaded)
                                <p class="tm-muted" style="font-size:0.85rem;">Selecciona este módulo arriba para cargar el listado.</p>
                            @endunless
                        </div>
                    </section>
                @endforeach
            </div>
        </article>
    @else
        <article class="content-card tm-card tm-card-in-shell">
            <p>No hay modulos temporales para mostrar registros.</p>
        </article>
    @endif
    </section>
    </div>

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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (!form || !form.matches || !form.matches('form[data-confirm-delete]')) {
                return;
            }
            e.preventDefault();
            const title = form.getAttribute('data-record-title') || 'este registro';
            if (typeof Swal === 'undefined') {
                if (confirm('¿Eliminar el registro "' + title + '" de manera permanente?')) {
                    form.submit();
                }
                return;
            }
            Swal.fire({
                title: '¿Eliminar registro?',
                text: 'Se eliminará "' + title + '" de manera permanente.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                buttonsStyling: false,
                customClass: {
                    popup: 'tm-swal-popup',
                    title: 'tm-swal-title',
                    htmlContainer: 'tm-swal-text',
                    confirmButton: 'tm-swal-confirm',
                    cancelButton: 'tm-swal-cancel'
                }
            }).then(function (result) {
                if (result.isConfirmed) form.submit();
            });
        }, true);
        const microrregionesMunicipios = @json(($microrregionesAsignadas ?? collect())->mapWithKeys(function ($micro) {
            return [(string) $micro->id => array_values($micro->municipios ?? [])];
        })->all());
        const openButtons = Array.from(document.querySelectorAll('[data-open-module-preview]'));
        const sectionTabs = Array.from(document.querySelectorAll('[data-section-tab]'));
        const sectionPanels = Array.from(document.querySelectorAll('.tm-section-panel'));
        const moduleFilterButtons = Array.from(document.querySelectorAll('[data-module-filter]'));
        const modulePanels = Array.from(document.querySelectorAll('.tm-module-records-panel'));
        const imagePreviewButtons = Array.from(document.querySelectorAll('[data-open-image-preview]'));
        const textToggleButtons = Array.from(document.querySelectorAll('[data-text-toggle]'));
        const pasteButtons = Array.from(document.querySelectorAll('[data-paste-image-button]'));
        const pasteUploadAreas = Array.from(document.querySelectorAll('[data-paste-upload-wrap]'));
        const imageModal = document.getElementById('tmImagePreviewModal');
        const imageModalImg = document.getElementById('tmImagePreviewImg');
        const imageModalTitle = document.getElementById('tmImagePreviewTitle');
        const imageInputSelector = 'input[type="file"][accept="image/*"]';
        const recordsViewPanel = document.getElementById('tmRecordsView');
        const recordsUrl = recordsViewPanel ? String(recordsViewPanel.getAttribute('data-records-url') || '') : '';
        const fragmentRecordsBase = recordsViewPanel
            ? String(recordsViewPanel.getAttribute('data-fragment-records-url') || '')
            : '';
        let lastFocusedImageInput = null;
        const modalOpeners = new Map();
        const notify = function (title, message, type) {
            if (typeof Swal !== 'undefined') {
                Swal.fire(title, message, type);
            } else if (typeof window.swal === 'function') {
                try { window.swal(title, message, type); } catch (e) { window.swal.fire(title, message, type); }
            } else {
                alert(title + '\n' + (message || ''));
            }
        };

        const activateModulePanel = function (targetId) {
            if (!targetId) {
                return;
            }

            moduleFilterButtons.forEach(function (button) {
                const isActive = button.getAttribute('data-module-target') === targetId;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            modulePanels.forEach(function (panel) {
                const isActive = panel.id === targetId;
                panel.classList.toggle('is-active', isActive);
                panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            });
        };

        const activateSectionPanel = function (targetId) {
            if (!targetId) {
                return;
            }

            sectionTabs.forEach(function (button) {
                const isActive = button.getAttribute('data-section-target') === targetId;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            sectionPanels.forEach(function (panel) {
                const isActive = panel.id === targetId;
                panel.classList.toggle('is-active', isActive);
                panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            });
        };

        const openModal = function (modal, opener) {
            if (!modal) {
                return;
            }

            if (opener) {
                modalOpeners.set(modal.id, opener);
            }

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        };

        const setMunicipiosForForm = function (form, microrregionId) {
            if (!form || !microrregionId) {
                return;
            }

            const municipios = Array.isArray(microrregionesMunicipios[String(microrregionId)])
                ? microrregionesMunicipios[String(microrregionId)]
                : [];

            Array.from(form.querySelectorAll('.tm-municipio-select')).forEach(function (select) {
                const currentValue = String(select.value || '');
                select.innerHTML = '';
                select.appendChild(new Option('Selecciona un municipio', ''));

                municipios.forEach(function (municipio) {
                    const option = new Option(municipio, municipio, false, currentValue === municipio);
                    select.appendChild(option);
                });
            });
        };

        const initializeImagePreview = function (input) {
            const wrapper = input.closest('label');
            if (!wrapper) {
                return;
            }

            input.addEventListener('focus', function () {
                lastFocusedImageInput = input;
            });

            input.addEventListener('click', function () {
                lastFocusedImageInput = input;
            });

            const preview = wrapper.querySelector('[data-image-preview]');
            const previewImg = wrapper.querySelector('[data-image-preview-img]');
            const removeButton = wrapper.querySelector('[data-image-remove]');
            const removeFlag = wrapper.querySelector('[data-remove-flag]');

            if (!preview || !previewImg) {
                return;
            }

            const hidePreview = function () {
                preview.hidden = true;
                previewImg.removeAttribute('src');
            };

            const showPreview = function (src) {
                if (!src) {
                    hidePreview();
                    return;
                }

                preview.hidden = false;
                previewImg.src = src;
            };

            if (!previewImg.getAttribute('src')) {
                hidePreview();
            }

            input.addEventListener('change', function () {
                const file = input.files && input.files[0] ? input.files[0] : null;
                if (!file) {
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (event) {
                    showPreview(String(event.target?.result || ''));
                };
                reader.readAsDataURL(file);

                if (removeFlag) {
                    removeFlag.value = '0';
                }
            });

            if (removeButton) {
                removeButton.addEventListener('click', function () {
                    input.value = '';
                    if (removeFlag) {
                        removeFlag.value = '1';
                    }
                    hidePreview();
                });
            }
        };

        Array.from(document.querySelectorAll(imageInputSelector)).forEach(function (input) {
            initializeImagePreview(input);
        });

        pasteButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const targetId = button.getAttribute('data-target-input') || '';
                const input = targetId ? document.getElementById(targetId) : null;
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }

                pasteImageFromClipboardApi(input);
            });
        });

        pasteUploadAreas.forEach(function (area) {
            const input = area.querySelector(imageInputSelector);
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            area.addEventListener('focusin', function () {
                lastFocusedImageInput = input;
            });

            area.addEventListener('click', function (event) {
                if (event.target.closest('[data-image-remove]') || event.target.closest('.tm-image-preview img')) {
                    return;
                }
                input.click();
            });

            area.addEventListener('dragenter', function (event) {
                event.preventDefault();
                area.classList.add('is-dragover');
            });

            area.addEventListener('dragover', function (event) {
                event.preventDefault();
                area.classList.add('is-dragover');
            });

            area.addEventListener('dragleave', function () {
                area.classList.remove('is-dragover');
            });

            area.addEventListener('drop', function (event) {
                event.preventDefault();
                area.classList.remove('is-dragover');

                const imageFile = getImageFileFromFileList(event.dataTransfer ? event.dataTransfer.files : []);
                if (!imageFile) {
                    notify('Aviso', 'Solo se permiten imagenes al arrastrar.', 'warning');
                    return;
                }

                const wasAssigned = setSelectedFileOnInput(input, imageFile);
                if (!wasAssigned) {
                    notify('Aviso', 'No se pudo adjuntar la imagen arrastrada.', 'warning');
                }
            });
        });

        Array.from(document.querySelectorAll('[data-upload-trigger]')).forEach(function (button) {
            button.addEventListener('click', function () {
                const targetId = button.getAttribute('data-target-input') || '';
                const input = targetId ? document.getElementById(targetId) : null;
                if (input instanceof HTMLInputElement) {
                    input.click();
                }
            });
        });

        const getPasteTargetInput = function (event) {
            const eventTarget = event.target instanceof HTMLElement ? event.target : null;
            if (eventTarget) {
                const directInput = eventTarget.matches(imageInputSelector)
                    ? eventTarget
                    : eventTarget.closest('label')?.querySelector(imageInputSelector);

                if (directInput instanceof HTMLInputElement && !directInput.disabled) {
                    return directInput;
                }
            }

            if (lastFocusedImageInput instanceof HTMLInputElement && document.body.contains(lastFocusedImageInput) && !lastFocusedImageInput.disabled) {
                return lastFocusedImageInput;
            }

            const activeElement = document.activeElement;
            if (activeElement instanceof HTMLInputElement && activeElement.matches(imageInputSelector) && !activeElement.disabled) {
                return activeElement;
            }

            const openedModal = document.querySelector('.tm-modal.is-open');
            if (openedModal instanceof HTMLElement) {
                const modalInput = openedModal.querySelector(imageInputSelector);
                if (modalInput instanceof HTMLInputElement && !modalInput.disabled) {
                    return modalInput;
                }
            }

            return null;
        };

        const getImageFromClipboard = function (event) {
            const clipboardData = event.clipboardData;
            if (!clipboardData) {
                return null;
            }

            const items = Array.from(clipboardData.items || []);
            for (let index = 0; index < items.length; index += 1) {
                const item = items[index];
                if (!item || item.kind !== 'file' || !String(item.type || '').startsWith('image/')) {
                    continue;
                }

                const file = item.getAsFile();
                if (file) {
                    return file;
                }
            }

            const files = Array.from(clipboardData.files || []);
            for (let index = 0; index < files.length; index += 1) {
                const file = files[index];
                if (file && String(file.type || '').startsWith('image/')) {
                    return file;
                }
            }

            return null;
        };

        const extensionFromMime = function (mimeType) {
            if (mimeType === 'image/jpeg') {
                return 'jpg';
            }

            if (mimeType === 'image/png') {
                return 'png';
            }

            if (mimeType === 'image/webp') {
                return 'webp';
            }

            if (mimeType === 'image/gif') {
                return 'gif';
            }

            return 'png';
        };

        const getImageFileFromFileList = function (files) {
            const list = Array.from(files || []);
            for (let index = 0; index < list.length; index += 1) {
                const file = list[index];
                if (file && String(file.type || '').indexOf('image/') === 0) {
                    return file;
                }
            }

            return null;
        };

        const setSelectedFileOnInput = function (input, file) {
            if (!(input instanceof HTMLInputElement) || !file || typeof DataTransfer === 'undefined') {
                return false;
            }

            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            input.focus();
            lastFocusedImageInput = input;
            return true;
        };

        const setImageFileOnInput = function (input, blob, mimeType) {
            if (!(input instanceof HTMLInputElement) || !blob || typeof DataTransfer === 'undefined') {
                return false;
            }

            const type = mimeType || blob.type || 'image/png';
            const fileName = 'pegada_' + Date.now() + '.' + extensionFromMime(type);
            const file = new File([blob], fileName, {
                type: type,
                lastModified: Date.now(),
            });

            return setSelectedFileOnInput(input, file);
        };

        const pasteImageFromClipboardApi = async function (targetInput) {
            if (!(targetInput instanceof HTMLInputElement)) {
                return;
            }

            if (!window.isSecureContext || !navigator.clipboard || typeof navigator.clipboard.read !== 'function') {
                notify('Aviso', 'Tu navegador no permite leer portapapeles directo. Usa Ctrl+V.', 'warning');
                return;
            }

            try {
                const clipboardItems = await navigator.clipboard.read();
                for (const clipboardItem of clipboardItems) {
                    const imageType = clipboardItem.types.find(function (type) {
                        return String(type || '').indexOf('image/') === 0;
                    });

                    if (!imageType) {
                        continue;
                    }

                    const blob = await clipboardItem.getType(imageType);
                    const assigned = setImageFileOnInput(targetInput, blob, imageType);
                    if (assigned) {
                        return;
                    }
                }

                notify('Aviso', 'No se detecto imagen en el portapapeles.', 'warning');
            } catch (error) {
                notify('Aviso', 'No se pudo leer el portapapeles.', 'warning');
            }
        };

        const closeModal = function (modal) {
            if (!modal) {
                return;
            }

            const activeElement = document.activeElement;
            if (activeElement instanceof HTMLElement && modal.contains(activeElement)) {
                activeElement.blur();
            }

            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';

            const opener = modalOpeners.get(modal.id);
            if (opener instanceof HTMLElement) {
                opener.focus();
            }
        };

        document.addEventListener('click', function (event) {
            const btn = event.target.closest('[data-open-module-preview]');
            if (!btn) {
                return;
            }
            const modalId = btn.getAttribute('data-open-module-preview');
            const modal = modalId ? document.getElementById(modalId) : null;
            if (!modal) {
                return;
            }
            event.preventDefault();
            openModal(modal, btn);
        });

        Array.from(document.querySelectorAll('.tm-form.tm-entry-form')).forEach(function (form) {
            const entryIdInput = form.querySelector('input[name="entry_id"]');
            const isCreateForm = !entryIdInput || !String(entryIdInput.value || '').trim();

            const microrregionSelector = form.querySelector('.tm-mr-selector');
            if (microrregionSelector) {
                setMunicipiosForForm(form, microrregionSelector.value);
                microrregionSelector.addEventListener('change', function () {
                    setMunicipiosForForm(form, microrregionSelector.value);
                });
            }

            if (!isCreateForm) {
                return;
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();

                const submitButton = form.querySelector('button[type="submit"]');
                const originalText = submitButton ? submitButton.textContent : '';
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Guardando...';
                }

                const formData = new FormData(form);

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .then(function (response) {
                    var ct = (response.headers.get('Content-Type') || '').toLowerCase();
                    if (!ct.includes('application/json')) {
                        return response.text().then(function () {
                            return { status: response.status, data: { success: false, message: 'La respuesta del servidor no es válida. Intenta de nuevo.' } };
                        });
                        }
                    return response.json().then(function (data) {
                        return { status: response.status, data: data };
                    }).catch(function () {
                        return { status: response.status, data: { success: false, message: 'Error al procesar la respuesta.' } };
                    });
                })
                .then(function (result) {
                    if (result.status >= 400 || !result.data.success) {
                        throw result.data;
                    }

                    notify('Exito', result.data.message || 'Registro guardado correctamente.', 'success');

                    const selectedMr = microrregionSelector ? String(microrregionSelector.value || '') : '';
                    form.reset();

                    if (microrregionSelector && selectedMr) {
                        microrregionSelector.value = selectedMr;
                        setMunicipiosForForm(form, selectedMr);
                    }

                    Array.from(form.querySelectorAll('[data-image-preview]')).forEach(function (preview) {
                        preview.hidden = true;
                    });

                    Array.from(form.querySelectorAll('[data-image-preview-img]')).forEach(function (img) {
                        img.removeAttribute('src');
                    });

                    Array.from(form.querySelectorAll('[data-remove-flag]')).forEach(function (flag) {
                        flag.value = '0';
                    });

                    const ownerModal = form.closest('.tm-modal');
                    const ownerModalId = ownerModal ? String(ownerModal.id || '') : '';
                    const moduleId = ownerModalId.startsWith('delegate-preview-')
                        ? ownerModalId.replace('delegate-preview-', '')
                        : '';

                    if (recordsUrl) {
                        const url = new URL(recordsUrl, window.location.origin);
                        if (/^\d+$/.test(moduleId)) {
                            url.searchParams.set('module', moduleId);
                        }

                        window.location.href = url.toString();
                        return;
                    }
                })
                .catch(function (errorData) {
                    const backendErrors = errorData && errorData.errors ? Object.values(errorData.errors).flat() : [];
                    const message = backendErrors[0] || errorData.message || 'No fue posible guardar el registro.';
                    notify('Error', message, 'error');
                })
                .finally(function () {
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalText;
                    }
                });
            });
        });

        const buildRecordsQueryFromPanel = function (panel, entriesPage) {
            if (!panel) {
                return '';
            }
            const host = panel.querySelector('.tm-records-fragment-host');
            const moduleId = String(host && host.getAttribute('data-module-id') || '').replace(/[^\d]/g, '');
            if (!moduleId) {
                return '';
            }
            const filters = panel.querySelector('[data-tm-records-filters]');
            let qs = 'module=' + encodeURIComponent(moduleId) + '&entries_page=' + encodeURIComponent(String(entriesPage || '1'));
            if (filters) {
                const buscarEl = filters.querySelector('[data-tm-filter-buscar]');
                const mrEl = filters.querySelector('[data-tm-filter-microrregion]');
                const buscar = (buscarEl && buscarEl.value ? String(buscarEl.value) : '').trim();
                const mr = (mrEl && mrEl.value ? String(mrEl.value) : '').trim();
                if (buscar) {
                    qs += '&buscar=' + encodeURIComponent(buscar);
                }
                if (mr) {
                    qs += '&microrregion_id=' + encodeURIComponent(mr);
                }
            }
            return qs;
        };

        const loadRecordsFragment = function (host, moduleId, queryString) {
            if (!host || !fragmentRecordsBase || !moduleId) {
                return Promise.resolve();
            }
            const qs = queryString || ('module=' + encodeURIComponent(moduleId) + '&entries_page=1');
            host.innerHTML = '<p class="tm-muted tm-records-loading">Cargando…</p>';
            host.hidden = false;
            const sep = fragmentRecordsBase.indexOf('?') >= 0 ? '&' : '?';
            return fetch(fragmentRecordsBase + sep + qs.replace(/^\?/, ''), {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
            }).then(function (res) {
                if (!res.ok) {
                    throw new Error('Error ' + res.status);
                }
                return res.text();
            }).then(function (html) {
                host.innerHTML = html;
            }).catch(function () {
                host.innerHTML = '<p class="inline-alert inline-alert-error">No se pudo cargar el listado. <a href="' + (recordsUrl ? recordsUrl + '?module=' + moduleId : '#') + '">Recargar página</a></p>';
            });
        };

        const syncTmBuscarClearVisibility = function (input) {
            if (!input) {
                return;
            }
            const wrap = input.closest('.tm-records-search-wrap');
            const clearBtn = wrap && wrap.querySelector('[data-tm-filter-buscar-clear]');
            if (clearBtn) {
                clearBtn.hidden = !String(input.value || '').trim();
            }
        };

        const reloadRecordsPanelFromFilters = function (panel, opts) {
            opts = opts || {};
            if (!panel || !fragmentRecordsBase) {
                return;
            }
            if (opts.requireActive !== false && !panel.classList.contains('is-active')) {
                return;
            }
            const host = panel.querySelector('.tm-records-fragment-host');
            const moduleId = String(host && host.getAttribute('data-module-id') || '').replace(/[^\d]/g, '');
            if (!host || !moduleId) {
                return;
            }
            const placeholder = panel.querySelector('.tm-records-panel-placeholder');
            if (placeholder) {
                placeholder.hidden = true;
            }
            loadRecordsFragment(host, moduleId, buildRecordsQueryFromPanel(panel, '1'));
        };

        moduleFilterButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const targetId = button.getAttribute('data-module-target') || '';
                activateModulePanel(targetId);
                const panel = targetId ? document.getElementById(targetId) : null;
                if (!panel || !fragmentRecordsBase) {
                    return;
                }
                const buscarInput = panel.querySelector('[data-tm-filter-buscar]');
                if (buscarInput) {
                    syncTmBuscarClearVisibility(buscarInput);
                }
                const host = panel.querySelector('.tm-records-fragment-host');
                const placeholder = panel.querySelector('.tm-records-panel-placeholder');
                const moduleId = String(host && host.getAttribute('data-module-id') || '').replace(/[^\d]/g, '');
                if (!host || !moduleId) {
                    return;
                }
                const inner = host.querySelector('.tm-records-fragment-inner');
                if (inner) {
                    if (placeholder) {
                        placeholder.hidden = true;
                    }
                    host.hidden = false;
                    return;
                }
                if (placeholder) {
                    placeholder.hidden = true;
                }
                loadRecordsFragment(host, moduleId, buildRecordsQueryFromPanel(panel, '1')).then(function () {
                    if (placeholder) {
                        placeholder.hidden = true;
                    }
                });
            });
        });

        if (recordsViewPanel && fragmentRecordsBase) {
            recordsViewPanel.querySelectorAll('[data-tm-filter-buscar]').forEach(function (el) {
                syncTmBuscarClearVisibility(el);
            });

            recordsViewPanel.addEventListener('input', function (event) {
                const input = event.target.closest('[data-tm-filter-buscar]');
                if (!input || !recordsViewPanel.contains(input)) {
                    return;
                }
                syncTmBuscarClearVisibility(input);
                const panel = input.closest('.tm-module-records-panel');
                if (!panel) {
                    return;
                }
                if (input._tmBuscarTimer) {
                    clearTimeout(input._tmBuscarTimer);
                }
                input._tmBuscarTimer = setTimeout(function () {
                    input._tmBuscarTimer = null;
                    reloadRecordsPanelFromFilters(panel, { requireActive: true });
                }, 380);
            });

            recordsViewPanel.addEventListener('change', function (event) {
                if (!event.target.matches('[data-tm-filter-microrregion]')) {
                    return;
                }
                const panel = event.target.closest('.tm-module-records-panel');
                reloadRecordsPanelFromFilters(panel, { requireActive: true });
            });

            recordsViewPanel.addEventListener('click', function (event) {
                const buscarClear = event.target.closest('[data-tm-filter-buscar-clear]');
                if (buscarClear && recordsViewPanel.contains(buscarClear)) {
                    event.preventDefault();
                    const wrap = buscarClear.closest('.tm-records-search-wrap');
                    const input = wrap && wrap.querySelector('[data-tm-filter-buscar]');
                    if (input) {
                        input.value = '';
                        syncTmBuscarClearVisibility(input);
                        const panel = input.closest('.tm-module-records-panel');
                        reloadRecordsPanelFromFilters(panel, { requireActive: false });
                    }
                    return;
                }

                const anchor = event.target.closest('a.tm-paginator-btn[href]');
                if (!anchor || !anchor.getAttribute('href')) {
                    return;
                }
                const host = anchor.closest('.tm-records-fragment-host');
                if (!host || !recordsViewPanel.contains(host)) {
                    return;
                }
                event.preventDefault();
                const url = new URL(anchor.href, window.location.origin);
                const moduleId = host.getAttribute('data-module-id') || url.searchParams.get('module');
                const entriesPage = url.searchParams.get('entries_page') || '1';
                const panel = host.closest('.tm-module-records-panel');
                const qs = panel
                    ? buildRecordsQueryFromPanel(panel, entriesPage)
                    : ('module=' + encodeURIComponent(moduleId) + '&entries_page=' + encodeURIComponent(entriesPage));
                loadRecordsFragment(host, moduleId, qs);
            });
        }

        let syncTmModuleChipsNav = function () {};

        (function initTmModuleChipsNav() {
            const row = document.querySelector('[data-tm-module-chips-row]');
            if (!row) {
                return;
            }
            const track = row.querySelector('[data-tm-module-chips-track]');
            const prev = row.querySelector('[data-tm-module-chips-prev]');
            const next = row.querySelector('[data-tm-module-chips-next]');
            if (!track || !prev || !next) {
                return;
            }
            const step = function () {
                const w = track.clientWidth;
                return Math.max(120, w > 8 ? Math.floor(w * 0.72) : 160);
            };
            const syncNav = function () {
                const cw = track.clientWidth;
                const sw = track.scrollWidth;
                if (cw < 8) {
                    prev.disabled = true;
                    next.disabled = true;
                    return;
                }
                const maxScroll = sw - cw;
                if (maxScroll <= 2) {
                    prev.disabled = true;
                    next.disabled = true;
                    return;
                }
                prev.disabled = track.scrollLeft <= 2;
                next.disabled = track.scrollLeft >= maxScroll - 2;
            };
            syncTmModuleChipsNav = syncNav;
            prev.addEventListener('click', function () {
                track.scrollBy({ left: -step(), behavior: 'smooth' });
            });
            next.addEventListener('click', function () {
                track.scrollBy({ left: step(), behavior: 'smooth' });
            });
            track.addEventListener('scroll', syncNav, { passive: true });
            window.addEventListener('resize', syncNav);
            if (typeof ResizeObserver !== 'undefined') {
                new ResizeObserver(syncNav).observe(track);
            }
            const recordsSection = document.getElementById('tmRecordsView');
            if (recordsSection && typeof MutationObserver !== 'undefined') {
                new MutationObserver(function () {
                    if (!recordsSection.classList.contains('is-active')) {
                        return;
                    }
                    requestAnimationFrame(function () {
                        requestAnimationFrame(syncNav);
                    });
                }).observe(recordsSection, { attributes: true, attributeFilter: ['class'] });
            }
            syncNav();
        })();

        document.addEventListener('click', function (event) {
            const imgBtn = event.target.closest('[data-open-image-preview]');
            if (imgBtn && recordsViewPanel && recordsViewPanel.contains(imgBtn)) {
                if (!imageModal || !imageModalImg) {
                    return;
                }
                const src = imgBtn.getAttribute('data-image-src') || '';
                const title = imgBtn.getAttribute('data-image-title') || 'Vista previa';
                if (src === '') {
                    return;
                }
                event.preventDefault();
                imageModalImg.src = src;
                imageModalImg.alt = title;
                if (imageModalTitle) {
                    imageModalTitle.textContent = title;
                }
                openModal(imageModal, imgBtn);
                return;
            }
            const textBtn = event.target.closest('[data-text-toggle]');
            if (textBtn && recordsViewPanel && recordsViewPanel.contains(textBtn)) {
                const wrap = textBtn.closest('[data-text-wrap]');
                const content = wrap ? wrap.querySelector('[data-text-content]') : null;
                if (content instanceof HTMLElement) {
                    const isCollapsed = content.classList.contains('is-collapsed');
                    content.classList.toggle('is-collapsed', !isCollapsed);
                    textBtn.textContent = isCollapsed ? 'Ver menos' : 'Ver mas';
                }
            }
        });

        sectionTabs.forEach(function (button) {
            button.addEventListener('click', function () {
                const targetId = button.getAttribute('data-section-target') || '';
                activateSectionPanel(targetId);
                if (targetId === 'tmRecordsView') {
                    requestAnimationFrame(function () {
                        requestAnimationFrame(syncTmModuleChipsNav);
                    });
                }
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

                imageModalImg.src = src;
                imageModalImg.alt = title;
                if (imageModalTitle) {
                    imageModalTitle.textContent = title;
                }

                openModal(imageModal, button);
            });
        });

        Array.from(document.querySelectorAll('.tm-modal')).forEach(function (modal) {
            Array.from(modal.querySelectorAll('[data-close-module-preview], [data-close-image-preview]')).forEach(function (button) {
                button.addEventListener('click', function () {
                    closeModal(modal);
                    if (modal.id === 'tmImagePreviewModal' && imageModalImg) {
                        imageModalImg.removeAttribute('src');
                    }
                });
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            Array.from(document.querySelectorAll('.tm-modal.is-open')).forEach(function (modal) {
                closeModal(modal);
            });
        });

        /* Importar Excel (eventos temporales) */
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @json(csrf_token());

        const stripAccents = function (value) {
            try {
                return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            } catch (e) {
                return String(value || '');
            }
        };

        const escapeHtml = function (value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };

        const findMunicipioHeaderIndex = function (headers) {
            if (!Array.isArray(headers) || !headers.length) return null;
            const exact = headers.find(function (h) {
                const label = stripAccents(h && h.label ? h.label : '').toUpperCase();
                return /MUNICIPIO/.test(label);
            });
            if (exact && typeof exact.index !== 'undefined') return exact.index;

            const munLike = headers.find(function (h) {
                const label = stripAccents(h && h.label ? h.label : '').toUpperCase();
                // Fallback: MUNICIP, MUN, MUNICIPOS, etc.
                return /\bMUN\b/.test(label) || /MUNICIP/.test(label);
            });
            if (munLike && typeof munLike.index !== 'undefined') return munLike.index;

            return null;
        };

        const excelModals = Array.from(document.querySelectorAll('.tm-excel-import-modal'));
        excelModals.forEach(function (modal) {
            const previewUrl = String(modal.getAttribute('data-excel-preview-url') || '');
            const importUrl = String(modal.getAttribute('data-excel-import-url') || '');

            const step1 = modal.querySelector('.tm-excel-step1');
            const step2 = modal.querySelector('.tm-excel-step2');
            const fileInput = modal.querySelector('.tm-excel-file-input');
            const headerRowInput = modal.querySelector('.tm-excel-header-row');
            const dataStartRowInput = modal.querySelector('.tm-excel-data-start-row');
            const mrInput = modal.querySelector('.tm-excel-mr-input');
            const mapBody = modal.querySelector('.tm-excel-map-body');

            const errPreviewEl = modal.querySelector('.tm-excel-preview-err');
            const detectNoteEl = modal.querySelector('.tm-excel-detect-note');
            const errImportEl = modal.querySelector('.tm-excel-import-err');
            const okImportEl = modal.querySelector('.tm-excel-import-ok');

            const resetModal = function () {
                excelModals.forEach(function (m) {
                    if (m === modal) return;
                });

                if (step1) step1.classList.remove('tm-hidden');
                if (step2) step2.classList.add('tm-hidden');

                if (mapBody) mapBody.innerHTML = '';
                modal.__excelHeaderToFieldState = null;

                ['.tm-excel-preview-err', '.tm-excel-detect-note', '.tm-excel-import-err', '.tm-excel-import-ok'].forEach(function (sel) {
                    const el = modal.querySelector(sel);
                    if (!el) return;
                    el.textContent = '';
                    el.classList.add('tm-hidden');
                });
            };

            modal.__excelReset = resetModal;

            const readColumnsBtn = modal.querySelector('.tm-excel-read-columns');
            readColumnsBtn?.addEventListener('click', function () {
                const file = fileInput && fileInput.files && fileInput.files[0];
                if (!file) {
                    if (errPreviewEl) {
                        errPreviewEl.textContent = 'Selecciona un archivo Excel.';
                        errPreviewEl.classList.remove('tm-hidden');
                    }
                    return;
                }

                const fd = new FormData();
                fd.append('archivo_excel', file);
                fd.append('header_row', (headerRowInput ? headerRowInput.value : '1') || '1');
                fd.append('auto_detect', '1');
                fd.append('_token', csrfToken);

                if (errPreviewEl) errPreviewEl.classList.add('tm-hidden');
                if (detectNoteEl) detectNoteEl.classList.add('tm-hidden');
                if (errImportEl) errImportEl.classList.add('tm-hidden');
                if (okImportEl) okImportEl.classList.add('tm-hidden');

                fetch(previewUrl, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (_ref) {
                    if (!_ref.ok || !_ref.j.success) {
                        if (errPreviewEl) {
                            errPreviewEl.textContent = _ref.j.message || 'Error al leer el archivo.';
                            errPreviewEl.classList.remove('tm-hidden');
                        }
                        return;
                    }

                    if (typeof _ref.j.header_row === 'number' && headerRowInput) headerRowInput.value = String(_ref.j.header_row);
                    if (typeof _ref.j.data_start_row === 'number' && dataStartRowInput) dataStartRowInput.value = String(_ref.j.data_start_row);

                    if (_ref.j.detection_note && detectNoteEl) {
                        detectNoteEl.textContent = _ref.j.detection_note;
                        detectNoteEl.classList.remove('tm-hidden');
                    }

                    const excelHeaders = _ref.j.headers || [];
                    const excelFields = _ref.j.fields || [];
                    const excelSuggested = _ref.j.suggested_map || {};

                    const municipioHeaderIndex = findMunicipioHeaderIndex(excelHeaders);

                    if (mapBody) {
                        mapBody.innerHTML = '';

                        // Estado 1-1: un encabezado -> un campo (y un campo no se repite).
                        // fieldToHeader: fieldKey => headerIndex
                        // headerToField: headerIndex => fieldKey
                        const state = {
                            fieldToHeader: {},
                            headerToField: {}
                        };

                        const municipioField = (Array.isArray(excelFields) ? excelFields : []).find(function (f) {
                            return String(f && f.type || '') === 'municipio';
                        });

                        // Preasignar MUNICIPIO si existe encabezado MUNICIPIO
                        if (municipioField && municipioHeaderIndex !== null) {
                            state.fieldToHeader[String(municipioField.key)] = municipioHeaderIndex;
                            state.headerToField[String(municipioHeaderIndex)] = String(municipioField.key);
                        }

                        // Usar sugerencias del backend para preasignar
                        Object.keys(excelSuggested || {}).forEach(function (fieldKey) {
                            const idx = excelSuggested[fieldKey];
                            if (idx === null || typeof idx === 'undefined') return;
                            if (typeof idx !== 'number') return;
                            if (!Number.isFinite(idx)) return;

                            // No sobreescribir si ya está asignado (por MUNICIPIO)
                            if (Object.prototype.hasOwnProperty.call(state.fieldToHeader, fieldKey)) return;
                            if (Object.prototype.hasOwnProperty.call(state.headerToField, String(idx))) return;

                            state.fieldToHeader[String(fieldKey)] = idx;
                            state.headerToField[String(idx)] = String(fieldKey);
                        });

                        // Opciones de campos
                        const fieldOptionsHtml = (Array.isArray(excelFields) ? excelFields : []).map(function (f) {
                            const k = String(f.key || '').replace(/"/g, '');
                            const label = String(f.label || k || '');
                            const req = f.is_required ? ' *' : '';
                            const type = String(f.type || '');
                            const optLabel = label !== '' ? label + req : k + req;
                            const typeSuffix = type ? ' (' + type + ')' : '';
                            return '<option value="' + escapeHtml(k) + '">' + escapeHtml(optLabel) + escapeHtml(typeSuffix) + '</option>';
                        }).join('');

                        excelHeaders.forEach(function (h) {
                            const headerIdx = h && typeof h.index !== 'undefined' ? h.index : null;
                            if (headerIdx === null) return;

                            const assignedFieldKey = state.headerToField[String(headerIdx)] || '';

                            const lab = (h.letter + ': ' + (h.label || '(vacío)')).replace(/</g, '');
                            const tr = document.createElement('tr');

                            // select de campo asignado a este encabezado
                            tr.innerHTML =
                                '<td>' + escapeHtml(lab) + '</td>' +
                                '<td>' +
                                    '<select class="tm-excel-header-map-select" data-header-index="' + escapeHtml(headerIdx) + '">' +
                                        '<option value="">— No importar —</option>' +
                                        fieldOptionsHtml +
                                    '</select>' +
                                '</td>';

                            mapBody.appendChild(tr);

                            const sel = tr.querySelector('select.tm-excel-header-map-select');
                            if (sel) {
                                sel.value = assignedFieldKey;
                            }
                        });

                        // listeners para mantener 1-1 (un campo asignado solo a un encabezado)
                        const headerSelects = Array.from(mapBody.querySelectorAll('select.tm-excel-header-map-select'));
                        headerSelects.forEach(function (sel) {
                            sel.addEventListener('change', function () {
                                const headerIdx = String(sel.getAttribute('data-header-index') || '');
                                const newFieldKey = String(sel.value || '');

                                // quitar asignación previa de este encabezado
                                const prevFieldKey = state.headerToField[headerIdx] || '';
                                if (prevFieldKey) {
                                    delete state.fieldToHeader[prevFieldKey];
                                }
                                delete state.headerToField[headerIdx];

                                // si no se seleccionó campo, ya terminamos
                                if (!newFieldKey) {
                                    return;
                                }

                                // si el campo ya estaba asignado a otro encabezado, limpiar ese otro
                                const oldHeader = state.fieldToHeader[newFieldKey];
                                if (typeof oldHeader !== 'undefined') {
                                    const oldHeaderKey = String(oldHeader);
                                    state.headerToField[oldHeaderKey] = '';
                                    delete state.fieldToHeader[newFieldKey];

                                    // limpiar UI en el select del encabezado anterior
                                    const oldSel = mapBody.querySelector('select.tm-excel-header-map-select[data-header-index="' + oldHeaderKey + '"]');
                                    if (oldSel instanceof HTMLSelectElement) {
                                        oldSel.value = '';
                                    }
                                }

                                // asignar
                                state.fieldToHeader[newFieldKey] = parseInt(headerIdx, 10);
                                state.headerToField[headerIdx] = newFieldKey;
                            });
                        });

                        // guardar el state dentro del modal para usarlo en el import
                        modal.__excelHeaderToFieldState = state;
                    }

                    if (step1) step1.classList.add('tm-hidden');
                    if (step2) step2.classList.remove('tm-hidden');
                })
                .catch(function () {
                    if (errPreviewEl) {
                        errPreviewEl.textContent = 'Error de red al subir el archivo.';
                        errPreviewEl.classList.remove('tm-hidden');
                    }
                });
            });

            const backBtn = modal.querySelector('.tm-excel-back');
            backBtn?.addEventListener('click', function () {
                if (step2) step2.classList.add('tm-hidden');
                if (step1) step1.classList.remove('tm-hidden');
            });

            const importBtn = modal.querySelector('.tm-excel-importar');
            importBtn?.addEventListener('click', function () {
                const file = fileInput && fileInput.files && fileInput.files[0];
                if (!file) {
                    if (errImportEl) {
                        errImportEl.textContent = 'Vuelve al paso 1 y selecciona el archivo.';
                        errImportEl.classList.remove('tm-hidden');
                    }
                    return;
                }

                const mapping = {};
                const state = modal.__excelHeaderToFieldState;
                if (state && state.fieldToHeader && typeof state.fieldToHeader === 'object') {
                    Object.keys(state.fieldToHeader).forEach(function (fieldKey) {
                        const idx = state.fieldToHeader[fieldKey];
                        if (typeof idx === 'number' && Number.isFinite(idx)) {
                            mapping[fieldKey] = idx;
                        }
                    });
                } else {
                    // fallback (si el estado no se inicializó bien)
                    Array.from(modal.querySelectorAll('select.tm-excel-header-map-select')).forEach(function (sel) {
                        const headerIdx = sel.getAttribute('data-header-index');
                        const fieldKey = sel.value;
                        if (!headerIdx || !fieldKey) return;
                        const idx = parseInt(String(headerIdx), 10);
                        if (Number.isFinite(idx)) mapping[String(fieldKey)] = idx;
                    });
                }

                const fd = new FormData();
                fd.append('archivo_excel', file);
                fd.append('header_row', (headerRowInput ? headerRowInput.value : '1') || '1');
                fd.append('data_start_row', (dataStartRowInput ? dataStartRowInput.value : '2') || '2');
                fd.append('mapping', JSON.stringify(mapping));
                fd.append('selected_microrregion_id', mrInput ? String(mrInput.value || '') : '');
                fd.append('_token', csrfToken);

                if (errImportEl) errImportEl.classList.add('tm-hidden');
                if (okImportEl) okImportEl.classList.add('tm-hidden');

                fetch(importUrl, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (_ref) {
                    if (!_ref.ok || !_ref.j.success) {
                        if (errImportEl) {
                            errImportEl.textContent = _ref.j.message || 'Error al importar.';
                            errImportEl.classList.remove('tm-hidden');
                        }
                        return;
                    }

                    let msg = _ref.j.message || 'Listo.';
                    if (_ref.j.row_errors && _ref.j.row_errors.length) {
                        msg += ' Avisos: ' + _ref.j.row_errors.slice(0, 5).map(function (e) { return 'fila ' + e.row; }).join(', ');
                    }
                    if (okImportEl) {
                        okImportEl.textContent = msg;
                        okImportEl.classList.remove('tm-hidden');
                    }

                    if (_ref.j.imported > 0) {
                        setTimeout(function () { window.location.reload(); }, 1200);
                    }
                })
                .catch(function () {
                    if (errImportEl) {
                        errImportEl.textContent = 'Error de red.';
                        errImportEl.classList.remove('tm-hidden');
                    }
                });
            });
        });

        document.addEventListener('click', function (event) {
            const btn = event.target.closest('[data-open-excel-import]');
            if (!btn) return;

            const modalId = btn.getAttribute('data-open-excel-import');
            const modal = modalId ? document.getElementById(modalId) : null;
            if (!modal) return;

            event.preventDefault();
            if (typeof modal.__excelReset === 'function') modal.__excelReset();
            openModal(modal, btn);
        });

        document.addEventListener('paste', function (event) {
            const imageFile = getImageFromClipboard(event);
            if (!imageFile) {
                return;
            }

            const targetInput = getPasteTargetInput(event);
            if (!targetInput) {
                return;
            }

            if (typeof DataTransfer === 'undefined') {
                notify('Aviso', 'Tu navegador no permite pegar imagenes automaticamente en este campo.', 'warning');
                return;
            }

            event.preventDefault();
            setSelectedFileOnInput(targetInput, imageFile);
        });
    });
</script>
@endpush
