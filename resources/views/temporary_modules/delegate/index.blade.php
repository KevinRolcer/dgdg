@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules-delegate-index.css') }}?v={{ filemtime(public_path('assets/css/modules/temporary-modules-delegate-index.css')) ?: time() }}">
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
                <button
                    type="button"
                    class="tm-section-tab tm-section-tab-errors tm-btn-session-errors tm-hidden"
                    id="tmBtnHeaderSessionErrors"
                    data-session-errors-open
                    title="Errores pendientes de importación"
                >
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    Errores
                    <span class="tm-error-count">0</span>
                </button>
            </div>
        </header>

        @if (session('status'))
            @php
                $tmStatus = session('status');
                $tmStatusStr = is_string($tmStatus) ? $tmStatus : (is_array($tmStatus) ? implode(' ', array_filter(array_map(function ($v) { return is_scalar($v) ? (string) $v : ''; }, $tmStatus))) : '');
            @endphp
            @if ($tmStatusStr !== '')
                <div class="inline-alert inline-alert-success" role="alert">{{ $tmStatusStr }}</div>
            @endif
        @endif

        @if ($errors->any())
            <div class="inline-alert inline-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

    <section class="tm-section-panel {{ $isUploadSection ? 'is-active' : '' }}" id="tmUploadView" role="tabpanel" aria-hidden="{{ $isUploadSection ? 'false' : 'true' }}" data-upload-url="{{ route('temporary-modules.fragment.upload') }}">
        <div class="tm-search-row">
            <div class="tm-filter-bar">
                <div class="tm-search-input-wrap">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input
                        type="text"
                        id="tmModuleSearchInput"
                        placeholder="Buscar..."
                        class="tm-search-input-small"
                        aria-label="Buscar módulo">
                    <button type="button" class="tm-btn-clear-inline tm-hidden" id="tmClearSearch" title="Limpiar búsqueda">
                        <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="tm-filter-group">
                    <span class="tm-filter-label">Ordenar:</span>
                    <div class="tm-pill-group">
                        <button type="button" class="tm-pill is-active" data-sort="az" title="A-Z">A-Z</button>
                        <button type="button" class="tm-pill" data-sort="za" title="Z-A">Z-A</button>
                    </div>
                </div>

                <div class="tm-filter-group">
                    <span class="tm-filter-label">Vigencia:</span>
                    <div class="tm-pill-group">
                        <button type="button" class="tm-pill is-active" data-filter-expiry="all">Todos</button>
                        <button type="button" class="tm-pill" data-filter-expiry="none">Indefinida</button>
                        <button type="button" class="tm-pill" data-filter-expiry="has">Con fecha</button>
                    </div>
                </div>

                <div class="tm-filter-group tm-hidden" id="tmDateFilterGroup">
                    <div class="tm-date-filter-wrap">
                        <input type="date" id="tmDateLimit" class="tm-date-input" title="Vence hasta...">
                    </div>
                </div>
            </div>
        </div>
        @include('temporary_modules.delegate.partials.upload_modules', ['modules' => $modules, 'fragmentUploadUrl' => $fragmentUploadUrl ?? '#'])
    </section>

    @foreach (($allModules ?? $modules) as $module)
        @php
            $tmImportable = $module->fields->filter(function ($f) {
                return in_array($f->type, \App\Services\TemporaryModules\TemporaryModuleExcelImportService::IMPORTABLE_TYPES, true);
            });
            $templateModalId = 'tmTemplateDownloadModal-'.$module->id;
            $hasTemplateData = (int) ($module->my_entries_count ?? 0) > 0;
            $moduleRegScope = in_array((string) ($module->registration_scope ?? ''), ['microrregion', 'free', 'municipios'], true)
                ? (string) $module->registration_scope
                : 'microrregion';
            $moduleUsesMicrorregion = $moduleRegScope === 'microrregion' && (bool) ($module->show_microregion ?? true);
            $moduleMostrarSelectorMicrorregion = $moduleUsesMicrorregion && $microsAsignadas->count() > 1;
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
                            @if ($hasTemplateData)
                                <button type="button"
                                   class="tm-btn tm-btn-outline"
                                   data-open-template-download="{{ $templateModalId }}"
                                   aria-label="Descargar plantilla Excel">
                                    <i class="fa-solid fa-download" aria-hidden="true"></i> Plantilla
                                </button>
                            @else
                                <a href="{{ route('temporary-modules.download-template', ['module' => $module->id, 'scope' => 'blank']) }}"
                                   class="tm-btn tm-btn-outline"
                                   aria-label="Descargar plantilla Excel">
                                    <i class="fa-solid fa-download" aria-hidden="true"></i> Plantilla
                                </a>
                            @endif
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
                                return in_array($field->type, ['image', 'file', 'document'], true) ? 1 : 0;
                            })
                            ->values();
                        $mediaDividerPrinted = false;
                    @endphp

                    <form action="{{ route('temporary-modules.submit', $module->id) }}" method="POST" enctype="multipart/form-data" class="tm-form tm-entry-form">
                        @csrf
                        @if ($moduleUsesMicrorregion && $microsAsignadas->isNotEmpty() && !$moduleMostrarSelectorMicrorregion)
                            <input type="hidden" name="selected_microrregion_id" value="{{ $microsAsignadas->first()->id }}">
                        @endif
                        <input type="hidden" name="entry_id" value="">

                        <div class="tm-grid tm-grid-2 tm-entry-grid">
                            @if ($moduleMostrarSelectorMicrorregion)
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
                                    $isMediaField = in_array($field->type, ['image', 'file', 'document'], true);
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
                                                    <span class="tm-section-sub">{{ is_scalar($sub) ? $sub : '' }}</span>
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
                                                        @php $subVal = $catName.' > '.(is_scalar($sub) ? $sub : ''); @endphp
                                                        <option value="{{ $subVal }}" @selected($value === $subVal)>{{ $catName }} &rarr; {{ is_scalar($sub) ? $sub : '' }}</option>
                                                    @endforeach
                                                @endif
                                            @endforeach
                                        </select>
                                    @elseif ($field->type === 'multiselect')
                                        @php
                                            $msOpts = is_array($field->options) ? $field->options : [];
                                            $msSelected = is_array($value) ? $value : [];
                                        @endphp
                                        <div class="tm-multiselect-wrap" role="group" aria-label="{{ $field->label }}">
                                            @foreach ($msOpts as $msOpt)
                                                @if (! is_scalar($msOpt))
                                                    @continue
                                                @endif
                                                <label class="tm-multiselect-option">
                                                    <input type="checkbox"
                                                           name="{{ $name }}[]"
                                                           value="{{ $msOpt }}"
                                                           @checked(in_array($msOpt, $msSelected, true))>
                                                    <span>{{ $msOpt }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @elseif ($field->type === 'linked')
                                        @php
                                            $linkedOpts = is_array($field->options) ? $field->options : [];
                                            $primaryType     = $linkedOpts['primary_type'] ?? 'text';
                                            $primaryLabel    = $linkedOpts['primary_label'] ?? $field->label.' (principal)';
                                            $primaryOptions  = $linkedOpts['primary_options'] ?? [];
                                            $secondaryType   = $linkedOpts['secondary_type'] ?? 'text';
                                            $secondaryLabel  = $linkedOpts['secondary_label'] ?? $field->label.' (dependiente)';
                                            $secondaryOptions = $linkedOpts['secondary_options'] ?? [];
                                            $secondaryRequired = $linkedOpts['secondary_required'] ?? true;
                                            $existingLinked  = is_array($value) ? $value : [];
                                            $primaryValue    = $existingLinked['primary'] ?? null;
                                            $secondaryValue  = $existingLinked['secondary'] ?? null;
                                            $primaryId       = $id.'__primary';
                                            $secondaryId     = $id.'__secondary';
                                            $primaryName     = 'values['.$field->key.'__primary]';
                                            $secondaryName   = 'values['.$field->key.'__secondary]';
                                        @endphp
                                        {{-- Linked compound field --}}
                                        <div class="tm-linked-field-wrap" data-linked-field-group>
                                            {{-- Primary sub-field --}}
                                            <label class="tm-linked-primary-label">{{ $primaryLabel }} {{ $field->is_required ? '*' : '' }}</label>
                                            @if ($primaryType === 'select')
                                                <select id="{{ $primaryId }}" name="{{ $primaryName }}"
                                                        data-linked-primary
                                                        {{ $field->is_required ? 'required' : '' }}>
                                                    <option value="">Selecciona una opción</option>
                                                    @foreach ($primaryOptions as $opt)
                                                        @if (! is_scalar($opt))
                                                            @continue
                                                        @endif
                                                        <option value="{{ $opt }}" @selected($primaryValue === $opt)>{{ $opt }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif ($primaryType === 'textarea')
                                                <textarea id="{{ $primaryId }}" name="{{ $primaryName }}"
                                                          rows="2" data-linked-primary
                                                          {{ $field->is_required ? 'required' : '' }}>{{ is_scalar($primaryValue) ? $primaryValue : '' }}</textarea>
                                            @elseif ($primaryType === 'semaforo')
                                                <select id="{{ $primaryId }}" name="{{ $primaryName }}"
                                                        data-linked-primary
                                                        {{ $field->is_required ? 'required' : '' }}>
                                                    <option value="">Selecciona nivel</option>
                                                    @foreach (\App\Services\TemporaryModules\TemporaryModuleFieldService::semaforoLabels() as $semVal => $semLabel)
                                                        <option value="{{ $semVal }}" @selected($primaryValue === $semVal)>{{ $semLabel }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input id="{{ $primaryId }}"
                                                       type="{{ $primaryType === 'number' ? 'number' : ($primaryType === 'date' ? 'date' : 'text') }}"
                                                       name="{{ $primaryName }}"
                                                       value="{{ is_scalar($primaryValue) ? $primaryValue : '' }}"
                                                       data-linked-primary
                                                       {{ $field->is_required ? 'required' : '' }}>
                                            @endif

                                            {{-- Secondary sub-field (dependent) --}}
                                            <div class="tm-linked-secondary-wrap" data-linked-secondary-wrap
                                                 {{ $primaryValue ? '' : 'hidden' }}>
                                                <label class="tm-linked-secondary-label">{{ $secondaryLabel }} *</label>
                                                @if ($secondaryType === 'select')
                                                    <select id="{{ $secondaryId }}" name="{{ $secondaryName }}"
                                                            data-linked-secondary
                                                            {{ $primaryValue ? 'required' : 'disabled' }}>
                                                        <option value="">Selecciona una opción</option>
                                                        @foreach ($secondaryOptions as $opt)
                                                            @if (! is_scalar($opt))
                                                                @continue
                                                            @endif
                                                            <option value="{{ $opt }}" @selected($secondaryValue === $opt)>{{ $opt }}</option>
                                                        @endforeach
                                                    </select>
                                                @elseif ($secondaryType === 'textarea')
                                                    <textarea id="{{ $secondaryId }}" name="{{ $secondaryName }}"
                                                              rows="2" data-linked-secondary
                                                              {{ $primaryValue ? 'required' : 'disabled' }}>{{ is_scalar($secondaryValue) ? $secondaryValue : '' }}</textarea>
                                                @elseif ($secondaryType === 'semaforo')
                                                    <select id="{{ $secondaryId }}" name="{{ $secondaryName }}"
                                                            data-linked-secondary
                                                            {{ $primaryValue ? 'required' : 'disabled' }}>
                                                        <option value="">Selecciona nivel</option>
                                                        @foreach (\App\Services\TemporaryModules\TemporaryModuleFieldService::semaforoLabels() as $semVal => $semLabel)
                                                            <option value="{{ $semVal }}" @selected($secondaryValue === $semVal)>{{ $semLabel }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input id="{{ $secondaryId }}"
                                                           type="{{ $secondaryType === 'number' ? 'number' : ($secondaryType === 'date' ? 'date' : 'text') }}"
                                                           name="{{ $secondaryName }}"
                                                           value="{{ is_scalar($secondaryValue) ? $secondaryValue : '' }}"
                                                           data-linked-secondary
                                                           {{ $primaryValue ? 'required' : 'disabled' }}>
                                                @endif
                                            </div>
                                        </div>
                                    @elseif ($field->type === 'textarea')
                                        <textarea id="{{ $id }}" name="{{ $name }}" rows="3" {{ $field->is_required ? 'required' : '' }}>{{ is_scalar($value) ? $value : '' }}</textarea>
                                    @elseif ($field->type === 'number')
                                        <input id="{{ $id }}" type="number" step="any" name="{{ $name }}" value="{{ is_scalar($value) ? $value : '' }}" {{ $field->is_required ? 'required' : '' }}>
                                    @elseif ($field->type === 'date')
                                        <input id="{{ $id }}" type="date" name="{{ $name }}" value="{{ is_scalar($value) ? $value : '' }}" {{ $field->is_required ? 'required' : '' }}>
                                    @elseif ($field->type === 'datetime')
                                        <input id="{{ $id }}" type="datetime-local" name="{{ $name }}" value="{{ is_scalar($value) ? $value : '' }}" {{ $field->is_required ? 'required' : '' }}>
                                    @elseif ($field->type === 'delegado')
                                        <select id="{{ $id }}" name="{{ $name }}" {{ $field->is_required ? 'required' : '' }}>
                                            <option value="">Selecciona delegado</option>
                                            @foreach (($delegados ?? collect()) as $del)
                                                <option value="{{ $del->name }}" @selected($value === $del->name)>
                                                    {{ $del->name }}
                                                    @if(!empty($del->microrregion)) (MR {{ str_pad((string) $del->microrregion, 2, '0', STR_PAD_LEFT) }}) @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    @elseif ($field->type === 'select')
                                        <select id="{{ $id }}" name="{{ $name }}" {{ $field->is_required ? 'required' : '' }}>
                                            <option value="">Selecciona una opcion</option>
                                            @foreach (($field->options ?? []) as $option)
                                                @if (! is_scalar($option))
                                                    @continue
                                                @endif
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
                                    @elseif ($field->type === 'semaforo')
                                        <select id="{{ $id }}" name="{{ $name }}" class="tm-semaforo-select" {{ $field->is_required ? 'required' : '' }}>
                                            <option value="">Selecciona nivel</option>
                                            @foreach (\App\Services\TemporaryModules\TemporaryModuleFieldService::semaforoLabels() as $semVal => $semLabel)
                                                <option value="{{ $semVal }}" @selected($value === $semVal)>{{ $semLabel }}</option>
                                            @endforeach
                                        </select>
                                    @elseif (in_array($field->type, ['image', 'file', 'document'], true))
                                        @php
                                            $isSingleFileField = in_array($field->type, ['file', 'document'], true);
                                            $isDocumentField = $field->type === 'document';
                                            $uploadAccept = $isDocumentField ? '.pdf,.docx' : 'image/*';
                                            $dropIcon = $isDocumentField ? 'fa-file-arrow-up' : 'fa-images';
                                            $maxFilesAllowed = $isSingleFileField ? 1 : 2;
                                            $dropText = $isDocumentField
                                                ? 'Suelta tu documento aquí (PDF/DOCX, Máx. 1)'
                                                : 'Suelta las imágenes aquí (Máx. 2)';
                                        @endphp
                                        <div class="tm-upload-evidence">
                                            <div class="tm-upload-evidence-toolbar">
                                                <button type="button" class="tm-btn tm-btn-outline" data-upload-trigger data-target-input="{{ $id }}" aria-label="Cargar archivo">
                                                    <i class="fa-solid fa-upload" aria-hidden="true"></i> Cargar
                                                </button>
                                                @unless ($isDocumentField)
                                                    <button type="button" class="tm-btn tm-btn-outline" data-paste-image-button data-target-input="{{ $id }}" aria-label="Pegar imagen" title="Pegar imagen">
                                                        <i class="fa-solid fa-paste" aria-hidden="true"></i> Pegar
                                                    </button>
                                                @endunless
                                            </div>
                                            <small class="tm-upload-evidence-hint">
                                                {{ $isDocumentField ? 'Arrastra aquí tu PDF/DOCX o usa el botón cargar.' : 'Arrastra aquí o usa los botones.' }}
                                            </small>
                                            <div class="tm-upload-evidence-dropzone" data-paste-upload-wrap>
                                                <input id="{{ $id }}" type="file" accept="{{ $uploadAccept }}" name="{{ $name }}[]" class="d-none" {{ $field->is_required ? 'required' : '' }} {{ $isSingleFileField ? '' : 'multiple' }} data-max-files="{{ $maxFilesAllowed }}" data-upload-kind="{{ $isDocumentField ? 'document' : 'image' }}">
                                                <div class="tm-upload-evidence-placeholder">
                                                    <i class="fa-solid {{ $dropIcon }}" aria-hidden="true"></i>
                                                    <p>{{ $dropText }}</p>
                                                </div>
                                                <div class="tm-inline-image-preview-container" data-inline-image-preview-container style="display:flex; flex-wrap:wrap; gap:8px; width:100%; justify-content:center;">
                                                    {{-- El JS insertara las previsualizaciones aqui --}}
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <input id="{{ $id }}" type="text" name="{{ $name }}" value="{{ is_scalar($value) ? $value : '' }}" {{ $field->is_required ? 'required' : '' }}>
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

        @if ($tmImportable->isNotEmpty() && $hasTemplateData)
            @php
                $allTemplateUrls = $microsAsignadas->map(function ($micro) use ($module) {
                    return route('temporary-modules.download-template', [
                        'module' => $module->id,
                        'scope' => 'microrregion',
                        'selected_microrregion_id' => $micro->id,
                        'with_data' => 1,
                    ]);
                })->values()->all();
            @endphp
            <div class="tm-modal tm-template-download-modal" id="{{ $templateModalId }}" aria-hidden="true" role="dialog" aria-modal="true">
                <div class="tm-modal-backdrop" data-close-module-preview></div>
                <div class="tm-modal-dialog tm-modal-dialog-entry tm-template-download-dialog">
                    <div class="tm-modal-head">
                        <div class="tm-modal-head-stack">
                            <h3>Descargar plantilla</h3>
                            <p class="tm-modal-subtitle">{{ $module->name }}</p>
                        </div>
                        <button type="button" class="tm-modal-close" data-close-module-preview aria-label="Cerrar">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="tm-modal-body" style="position:relative;">
                        <div class="tm-template-dropdown"
                             data-all-template-options
                             hidden
                             style="position:absolute;z-index:120;top:8px;left:18px;right:18px;background:#fff;border:1px solid #ddd;border-radius:8px;box-shadow:0 10px 28px rgba(0,0,0,0.14);padding:18px 18px 12px 18px;">
                            <div style="font-weight:600;margin-bottom:8px;">¿Qué deseas descargar?</div>
                            <div class="tm-modal-actions" style="display:flex;flex-direction:column;gap:8px;">
                                <a href="{{ route('temporary-modules.download-template', ['module' => $module->id, 'scope' => 'all', 'mode' => 'single']) }}" class="tm-btn tm-btn-outline tm-btn-block">
                                    Solo registros existentes
                                </a>
                                <a href="{{ route('temporary-modules.download-template', ['module' => $module->id, 'scope' => 'all', 'mode' => 'municipios']) }}" class="tm-btn tm-btn-outline tm-btn-block">
                                    Listado de todos mis municipios asignados
                                </a>
                            </div>
                        </div>
                        <div class="tm-template-download-grid">
                            <a class="tm-template-download-card tm-template-download-card--blank"
                               href="{{ route('temporary-modules.download-template', ['module' => $module->id, 'scope' => 'blank']) }}">
                                <span class="tm-template-download-icon" aria-hidden="true"><i class="fa-solid fa-file-arrow-down"></i></span>
                                <span class="tm-template-download-copy">
                                    <span class="tm-template-download-title">Plantilla sin datos</span>
                                    <span class="tm-template-download-desc">Con ejemplo de llenado</span>
                                </span>
                                <span class="tm-template-download-action">Descargar</span>
                            </a>

                            @foreach ($microsAsignadas as $micro)
                                <a class="tm-template-download-card"
                                   href="{{ route('temporary-modules.download-template', ['module' => $module->id, 'scope' => 'microrregion', 'selected_microrregion_id' => $micro->id, 'with_data' => 1]) }}">
                                    <span class="tm-template-download-icon" aria-hidden="true"><i class="fa-solid fa-file-arrow-down"></i></span>
                                    <span class="tm-template-download-copy">
                                        <span class="tm-template-download-title">MR {{ $micro->microrregion }} - {{ $micro->cabecera }}</span>
                                        <span class="tm-template-download-desc">Plantilla con datos.</span>
                                    </span>
                                    <span class="tm-template-download-action">Descargar</span>
                                </a>
                            @endforeach

                            <button type="button"
                               class="tm-template-download-card tm-template-download-card--all"
                               data-download-all-templates
                               data-template-urls='@json($allTemplateUrls)'>
                                <span class="tm-template-download-icon" aria-hidden="true"><i class="fa-solid fa-file-zipper"></i></span>
                                <span class="tm-template-download-copy">
                                    <span class="tm-template-download-title">Todas las microregiones</span>
                                    <span class="tm-template-download-desc">1 archivo por MR</span>
                                </span>
                                <span class="tm-template-download-action">Descargar</span>
                            </button>
                            <div class="tm-template-download-card tm-template-download-card--all tm-template-download-card--dropdown">
                                <a href="#" data-all-template-options-toggle style="display:block;width:100%;text-decoration:none;">
                                    <span class="tm-template-download-icon" aria-hidden="true"><i class="fa-solid fa-file-excel"></i></span>
                                    <span class="tm-template-download-copy">
                                        <span class="tm-template-download-title">Todas las microregiones</span>
                                        <span class="tm-template-download-desc">1 archivo para todas</span>
                                    </span>
                                    <span class="tm-template-download-action">Descargar</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($tmImportable->isNotEmpty())
            <div class="tm-modal tm-excel-import-modal" id="tmImportarExcelModal-{{ $module->id }}" aria-hidden="true" role="dialog" aria-modal="true"
                 aria-labelledby="tmImportarExcelModalLabel-{{ $module->id }}"
                 data-excel-preview-url="{{ route('temporary-modules.import-excel-preview', $module->id) }}"
                 data-excel-import-url="{{ route('temporary-modules.import-excel', $module->id) }}"
                 data-excel-update-url="{{ route('temporary-modules.update-from-excel', $module->id) }}"
                 data-excel-import-single-url="{{ route('temporary-modules.import-single-row', $module->id) }}">
                <div class="tm-modal-backdrop" data-close-module-preview></div>
                <div class="tm-modal-dialog tm-modal-dialog-entry tm-excel-modal-dialog">
                    <div class="tm-modal-head">
                        <h3 id="tmImportarExcelModalLabel-{{ $module->id }}">Importar desde Excel / PDF</h3>
                        <button type="button" class="tm-modal-close" data-close-module-preview aria-label="Cerrar">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="tm-modal-body tm-excel-modal-body">
                        <div class="tm-excel-preview-container">
                            <!-- Lado Derecho: Vista Previa -->
                            <div class="tm-excel-preview-side">
                                <div class="tm-excel-selection-info">
                                    <div><i class="fa-solid fa-info-circle"></i> <span class="tm-excel-step-note">Haz clic en una fila para marcar <strong>Encabezado</strong> y <strong>Datos</strong>.</span></div>
                                    <div class="tm-excel-selection-status">
                                        <span class="tm-excel-badge badge-header tm-excel-badge-header" style="display:none;">Fila 1 (H)</span>
                                        <span class="tm-excel-badge badge-data tm-excel-badge-data" style="display:none;">Desde Fila 2 (D)</span>
                                    </div>
                                </div>
                                <div class="tm-excel-zoom-bar tm-excel-zoom-bar-el" style="display:none;">
                                    <button type="button" class="tm-excel-zoom-btn tm-excel-zoom-out" title="Alejar (Ctrl + Rueda abajo)"><i class="fa-solid fa-minus"></i></button>
                                    <span class="tm-excel-zoom-info tm-excel-zoom-val">100%</span>
                                    <button type="button" class="tm-excel-zoom-btn tm-excel-zoom-in" title="Acercar (Ctrl + Rueda arriba)"><i class="fa-solid fa-plus"></i></button>
                                    <button type="button" class="tm-excel-zoom-btn tm-excel-zoom-reset" title="Restablecer"><i class="fa-solid fa-rotate-left"></i></button>
                                </div>
                                <div class="tm-excel-sheet-tabs-wrap tm-excel-sheet-tabs-wrap-el" style="display:none;">
                                    <button type="button" class="tm-excel-sheet-arrow tm-excel-sheet-arrow--left tm-sheet-arrow-left-el" title="Anterior"><i class="fa-solid fa-chevron-left"></i></button>
                                    <div class="tm-excel-sheet-tabs tm-excel-sheet-tabs-el"></div>
                                    <button type="button" class="tm-excel-sheet-arrow tm-excel-sheet-arrow--right tm-sheet-arrow-right-el" title="Siguiente"><i class="fa-solid fa-chevron-right"></i></button>
                                </div>
                                <div class="tm-excel-sheet-wrapper tm-excel-preview-table-wrap">
                                    <div class="tm-excel-sheet-inner tm-excel-sheet-inner-el">
                                        <div style="padding:60px; text-align:center; color:var(--clr-text-light);">
                                            <i class="fa-solid fa-file-import" style="font-size:4rem; margin-bottom:16px; opacity:0.2;"></i>
                                            <p style="font-weight:600;">Vista previa del documento</p>
                                            <p style="font-size:0.85rem; opacity:0.7;">Carga un archivo Excel o PDF para comenzar a marcar las columnas.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Lado Izquierdo: Controles -->
                            <div class="tm-excel-controls-side">
                                <!-- PASO 1 -->
                                <div class="tm-excel-step1-inner">
                                    <div class="tm-form">
                                        <div class="tm-excel-dropzone tm-excel-dropzone-el">
                                            <i class="fa-solid fa-cloud-arrow-up"></i>
                                            <p>Arrastra tu archivo aquí o haz clic para buscar</p>
                                            <small>Formatos aceptados: .xlsx, .xls, .csv, .pdf</small>
                                            <input type="file" class="tm-excel-file-input tm-input" accept=".xlsx,.xls,.csv,.pdf" hidden>
                                        </div>
                                        <div class="tm-excel-badge badge-header tm-excel-file-name tm-hidden" style="margin-bottom:10px; padding:6px 12px; border-radius:8px;"></div>

                                        <div class="tm-excel-grid">
                                            <label>Encabezados (Fila)
                                                <input type="number" class="tm-excel-header-row" value="1" min="1" max="500" class="tm-input">
                                            </label>
                                            <label>Datos (Desde fila)
                                                <input type="number" class="tm-excel-data-start-row" value="2" min="2" max="50000" class="tm-input">
                                            </label>
                                        </div>

                                        @if ($moduleUsesMicrorregion && $microsAsignadas->count() > 0)
                                            <label class="tm-excel-search-all-wrap tm-excel-search-all-wrap-el">
                                                <input type="checkbox" class="tm-excel-search-all">
                                                <span>Buscar en todos los municipios de mis microrregiones</span>
                                            </label>
                                        @endif

                                        @if ($moduleUsesMicrorregion && $microsAsignadas->count() > 1)
                                            <div class="tm-excel-mr-select-container-el">
                                                <label style="margin-top:8px;">Microregión
                                                    <select class="tm-excel-mr-input tm-input" name="selected_microrregion_id">
                                                        @foreach ($microsAsignadas as $micro)
                                                            <option value="{{ $micro->id }}" @selected($loop->first)>
                                                                MR {{ $micro->microrregion }} — {{ $micro->cabecera }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                            </div>
                                        @else
                                            @if ($moduleUsesMicrorregion && $microsAsignadas->count() === 1)
                                                <input type="hidden" class="tm-excel-mr-input" name="selected_microrregion_id" value="{{ $microsAsignadas->first()->id }}">
                                            @endif
                                        @endif

                                        @if ($moduleUsesMicrorregion && $microsAsignadas->count() > 0)
                                            <label class="tm-excel-municipio-container-el" style="margin-top:8px;">
                                                Municipio de destino (si no se mapea Municipio)
                                                <select class="tm-excel-municipio-input tm-input">
                                                    <option value="">Selecciona un municipio</option>
                                                </select>
                                            </label>
                                        @endif

                                        <div class="inline-alert inline-alert-error tm-hidden tm-excel-preview-err" role="alert" style="margin-top:10px;"></div>
                                        <div class="inline-alert inline-alert-success tm-hidden tm-excel-detect-note" style="margin-top:10px;" role="status"></div>

                                        <div class="tm-actions" style="margin-top:20px; flex-direction:column; align-items:stretch; gap:10px;">
                                            <div style="display:flex; gap:8px;">
                                                <button type="button" class="tm-btn tm-btn-primary tm-excel-read-columns" style="flex:1;">
                                                    <i class="fa-solid fa-table-columns"></i> Leer secciones y mapear
                                                </button>
                                                <button type="button" class="tm-btn tm-btn-outline tm-excel-reset-trigger" title="Limpiar todo y empezar de nuevo">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </div>
                                            <button type="button" class="tm-btn tm-btn-outline tm-excel-auto-detect" style="display:none;">
                                                <i class="fa-solid fa-magic"></i> Marcar todo el documento
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- PASO 2 -->
                                <div class="tm-excel-step2-inner tm-hidden" style="display:flex; flex-direction:column; padding-top:20px; border-top: 1px dashed var(--clr-border); margin-top:20px;">
                                    <h4 class="tm-excel-step-title">Relacionar columnas</h4>
                                    <div class="tm-excel-mode-tabs" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:10px;">
                                        <button type="button" class="tm-btn tm-btn-primary tm-excel-mode-tab is-active" data-excel-mode="import">Importar</button>
                                        <button type="button" class="tm-btn tm-btn-outline tm-excel-mode-tab" data-excel-mode="update">Actualizar</button>
                                    </div>

                                    <div class="tm-excel-mode-panel tm-excel-mode-panel-import" data-excel-mode-panel="import">
                                        <div class="inline-alert inline-alert-success" style="margin-bottom:10px;">Solo registra filas nuevas; si detecta duplicados no las sube y muestra sugerencias.</div>
                                        <label class="tm-excel-search-all-wrap tm-excel-auto-municipio-wrap-el" style="margin-bottom:10px;">
                                            <input type="checkbox" class="tm-excel-auto-municipio-check" checked>
                                            <span>Identificar municipio automáticamente (por columna Municipio)</span>
                                        </label>
                                        <div class="tm-table-wrap tm-excel-map-table-wrap" style="margin-bottom:12px; border:1px solid var(--clr-border); border-radius:8px; background: rgba(0,0,0,0.02);">
                                            <table class="tm-table tm-table-sm tm-excel-map-table" style="font-size:12px; width:100%;">
                                                <thead>
                                                    <tr>
                                                        <th style="padding:10px 8px;">Campo del módulo</th>
                                                        <th style="padding:10px 8px;">Columna del Excel</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="tm-excel-map-body-import"></tbody>
                                            </table>
                                        </div>
                                        <div class="tm-actions" style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                            <button type="button" class="tm-btn tm-excel-back">Restablecer</button>
                                            <button type="button" class="tm-btn tm-btn-primary tm-excel-importar">Importar filas</button>
                                        </div>
                                    </div>

                                    <div class="tm-excel-mode-panel tm-excel-mode-panel-update tm-hidden" data-excel-mode-panel="update">
                                        <div class="inline-alert inline-alert-success" style="margin-bottom:10px;">Define columnas base para buscar coincidencias y columnas a actualizar para aplicar cambios.</div>
                                        <label class="tm-excel-search-all-wrap tm-excel-auto-municipio-wrap-el" style="margin-bottom:10px;">
                                            <input type="checkbox" class="tm-excel-auto-municipio-check" checked>
                                            <span>Identificar municipio automáticamente (por columna Municipio)</span>
                                        </label>
                                        <div class="tm-table-wrap tm-excel-map-table-wrap" style="margin-bottom:12px; border:1px solid var(--clr-border); border-radius:8px; background: rgba(0,0,0,0.02);">
                                            <table class="tm-table tm-table-sm tm-excel-map-table" style="font-size:12px; width:100%;">
                                                <thead>
                                                    <tr>
                                                        <th style="padding:10px 8px;">Campo del módulo</th>
                                                        <th style="padding:10px 8px;">Configuración de actualización</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="tm-excel-map-body-update"></tbody>
                                            </table>
                                        </div>
                                        <div class="tm-actions" style="display:grid; grid-template-columns:1fr; gap:10px; margin-top:8px;">
                                            <button type="button" class="tm-btn tm-btn-outline tm-excel-actualizar-existentes" title="Busca por columnas base y actualiza solo columnas marcadas">
                                                <i class="fa-solid fa-arrows-rotate"></i> Actualizar registros existentes
                                            </button>
                                        </div>
                                    </div>

                                    <div class="inline-alert inline-alert-error tm-hidden tm-excel-import-err" role="alert"></div>
                                    <div class="inline-alert inline-alert-success tm-hidden tm-excel-import-ok" role="alert"></div>

                                    <div class="tm-excel-errors-section tm-hidden" style="margin-top:20px; padding-top:20px; border-top:1px solid var(--clr-border);">
                                        <h4 class="tm-excel-step-title" style="color:var(--clr-error-text); border-bottom-color:var(--clr-error-text);">Errores de Importación</h4>
                                        <p style="font-size:0.85rem; color:var(--clr-text-light); margin-bottom:15px;">Las siguientes filas no se pudieron importar. Puedes corregirlas seleccionando una sugerencia:</p>
                                        <div class="tm-excel-errors-list" style="display:grid; gap:12px;"></div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @php
            $bulkInsertFields = $module->fields
                ->filter(fn ($f) => !in_array($f->type, ['seccion'], true))
                ->values();
            $bulkInsertHasMunicipio = $bulkInsertFields->contains(fn ($f) => $f->type === 'municipio');
            $bulkInsertMunicipiosAll = $microsAsignadas
                ->flatMap(function ($micro) {
                    return collect($micro->municipios ?? [])->map(function ($item) {
                        if (is_scalar($item)) {
                            return trim((string) $item);
                        }
                        if (is_array($item)) {
                            return trim((string) ($item['municipio'] ?? ''));
                        }
                        if (is_object($item)) {
                            return trim((string) ($item->municipio ?? ''));
                        }
                        return '';
                    });
                })
                ->filter(fn ($mun) => is_string($mun) && $mun !== '')
                ->unique()
                ->values()
                ->all();
            $bulkInsertFieldsJson = $bulkInsertFields->map(function ($f) {
                return [
                    'key' => (string) $f->key,
                    'label' => (string) $f->label,
                    'type' => (string) $f->type,
                    'is_required' => (bool) $f->is_required,
                    'options' => is_array($f->options) ? $f->options : [],
                ];
            })->values();
        @endphp
        @if ($bulkInsertFields->isNotEmpty())
            <div
                class="tm-modal tm-bulk-insert-modal"
                id="tmBulkInsertModal-{{ $module->id }}"
                aria-hidden="true"
                role="dialog"
                aria-modal="true"
                aria-labelledby="tmBulkInsertTitle-{{ $module->id }}"
                data-module-id="{{ $module->id }}"
                data-import-single-url="{{ route('temporary-modules.import-single-row', $module->id) }}"
                data-submit-url="{{ route('temporary-modules.submit', $module->id) }}"
                data-show-mr-select="{{ ($moduleMostrarSelectorMicrorregion && !$bulkInsertHasMunicipio) ? '1' : '0' }}"
                data-has-municipio="{{ $bulkInsertHasMunicipio ? '1' : '0' }}"
            >
                <div class="tm-modal-backdrop" data-tm-bulk-insert-dismiss></div>
                <div class="tm-modal-dialog tm-modal-dialog-bulk-edit">
                    <div class="tm-modal-head">
                        <div class="tm-modal-head-stack">
                            <h3 id="tmBulkInsertTitle-{{ $module->id }}">Registrar en hoja de calculo</h3>
                            <p class="tm-modal-subtitle">{{ $module->name }}</p>
                        </div>
                        <div class="tm-modal-head-actions">
                            <button type="button" class="tm-modal-close" data-tm-bulk-insert-dismiss aria-label="Cerrar">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="tm-modal-body tm-bulk-insert-body">
                        <script type="application/json" data-tm-bulk-insert-fields-json>@json($bulkInsertFieldsJson)</script>
                        <script type="application/json" data-tm-bulk-insert-municipios-json>@json($bulkInsertMunicipiosAll)</script>

                        <div class="tm-bulk-insert-toolbar">
                            <button type="button" class="tm-btn tm-btn-outline tm-btn-sm" data-tm-bulk-insert-add-row>
                                <i class="fa-solid fa-plus" aria-hidden="true"></i> Agregar fila
                            </button>
                            <details class="tm-bulk-insert-fields-picker" data-tm-bulk-insert-fields-picker>
                                <summary class="tm-btn tm-btn-outline tm-btn-sm">
                                    <i class="fa-solid fa-columns" aria-hidden="true"></i> Campos
                                </summary>
                                <div class="tm-bulk-insert-fields-popover" data-tm-bulk-insert-fields-popover></div>
                            </details>
                            @if ($bulkInsertHasMunicipio)
                                <button type="button" class="tm-btn tm-btn-outline tm-btn-sm" data-tm-bulk-insert-open-municipios>
                                    <i class="fa-solid fa-list-check" aria-hidden="true"></i> Registro para todos los municipios
                                </button>
                            @endif
                            <div class="tm-bulk-insert-toolbar-spacer"></div>
                            <button type="button" class="tm-btn tm-btn-primary tm-btn-sm" data-tm-bulk-insert-submit>
                                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Registrar filas
                            </button>
                        </div>

                        <div class="tm-excel-sheet-container tm-bulk-insert-sheet-wrap">
                            <div class="tm-excel-sheet-inner tm-bulk-insert-sheet" data-tm-bulk-insert-sheet></div>
                        </div>

                        <div class="tm-bulk-insert-status tm-hidden" data-tm-bulk-insert-status></div>
                    </div>
                </div>
            </div>

            @if ($bulkInsertHasMunicipio)
                <div
                    class="tm-modal tm-bulk-insert-municipios-modal"
                    id="tmBulkInsertMunicipiosModal-{{ $module->id }}"
                    aria-hidden="true"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="tmBulkInsertMunicipiosTitle-{{ $module->id }}"
                    data-module-id="{{ $module->id }}"
                >
                    <div class="tm-modal-backdrop" data-tm-bulk-insert-municipios-dismiss></div>
                    <div class="tm-modal-dialog tm-modal-dialog-entry">
                        <div class="tm-modal-head">
                            <div class="tm-modal-head-stack">
                                <h3 id="tmBulkInsertMunicipiosTitle-{{ $module->id }}">Mis municipios</h3>
                                <p class="tm-modal-subtitle">Se creara una fila por municipio seleccionado.</p>
                            </div>
                            <div class="tm-modal-head-actions" style="display:flex; gap:8px; align-items:center;">
                                <button type="button" class="tm-btn tm-btn-outline tm-btn-sm" data-tm-bulk-insert-mr-toggle-all>
                                    <i class="fa-solid fa-check-double" aria-hidden="true"></i> Marcar/Desmarcar todos
                                </button>
                                <button type="button" class="tm-modal-close" data-tm-bulk-insert-municipios-dismiss aria-label="Cerrar">
                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="tm-modal-body">
                            <div class="tm-bulk-insert-mr-tabs-wrap" data-tm-bulk-insert-mr-tabs-wrap>
                                <button type="button" class="tm-bulk-insert-mr-arrow" data-tm-bulk-insert-mr-prev title="Anterior" aria-label="Microrregion anterior">
                                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                                </button>
                                <div class="tm-bulk-insert-mr-tabs" role="tablist" aria-label="Microrregiones" data-tm-bulk-insert-mr-tabs>
                                    @foreach ($microsAsignadas as $micro)
                                        <button type="button"
                                                class="tm-bulk-insert-mr-tab {{ $loop->first ? 'is-active' : '' }}"
                                                data-tm-bulk-insert-mr-tab
                                                data-mr-id="{{ (int) $micro->id }}"
                                                role="tab"
                                                aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                            MR {{ $micro->microrregion }} - {{ $micro->cabecera }}
                                        </button>
                                    @endforeach
                                </div>
                                <button type="button" class="tm-bulk-insert-mr-arrow" data-tm-bulk-insert-mr-next title="Siguiente" aria-label="Microrregion siguiente">
                                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="tm-bulk-insert-mr-pages" data-tm-bulk-insert-mr-pages>
                                @foreach ($microsAsignadas as $micro)
                                    @php
                                        $mrId = (int) $micro->id;
                                        $munList = collect($micro->municipios ?? [])
                                            ->map(function ($item) {
                                                if (is_scalar($item)) {
                                                    return trim((string) $item);
                                                }
                                                if (is_array($item)) {
                                                    return trim((string) ($item['municipio'] ?? ''));
                                                }
                                                if (is_object($item)) {
                                                    return trim((string) ($item->municipio ?? ''));
                                                }
                                                return '';
                                            })
                                            ->filter(fn ($s) => is_string($s) && $s !== '')
                                            ->values()
                                            ->all();
                                    @endphp
                                    <div class="tm-bulk-insert-mr-page {{ $loop->first ? '' : 'tm-hidden' }}"
                                         data-tm-bulk-insert-mr-page
                                         data-mr-id="{{ $mrId }}"
                                         role="tabpanel"
                                         aria-hidden="{{ $loop->first ? 'false' : 'true' }}">
                                        <div class="tm-bulk-insert-municipios-list" data-tm-bulk-insert-municipios-list>
                                            @foreach ($munList as $mun)
                                                @php $mun = is_scalar($mun) ? (string) $mun : ''; @endphp
                                                @if ($mun === '')
                                                    @continue
                                                @endif
                                                <label class="tm-bulk-insert-mun-opt">
                                                    <input type="checkbox" value="{{ $mun }}" data-mr-id="{{ $mrId }}" checked>
                                                    <span>{{ $mun }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="tm-actions tm-bulk-insert-mun-footer">
                                <button type="button" class="tm-btn tm-btn-outline" data-tm-bulk-insert-municipios-dismiss>Cancelar</button>
                                <button type="button" class="tm-btn tm-btn-primary" data-tm-bulk-insert-municipios-apply>Cargar en hoja</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif

    @endforeach

    <section class="tm-section-panel {{ !$isUploadSection ? 'is-active' : '' }}" id="tmRecordsView" role="tabpanel" aria-hidden="{{ !$isUploadSection ? 'false' : 'true' }}" data-records-url="{{ route('temporary-modules.records') }}" data-fragment-records-url="{{ $fragmentRecordsUrl ?? '' }}">
    @if (($allModules ?? $modules)->isNotEmpty())
        <article class="content-card tm-card tm-card-in-shell tm-records-container">
            <div class="tm-module-filters-row" data-tm-module-chips-row>
                <button type="button" class="tm-module-filters-nav tm-module-filters-nav--prev" data-tm-module-chips-prev aria-label="Módulos anteriores" disabled>
                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                </button>
                <div class="tm-module-filters-track" data-tm-module-chips-track>
                    <div class="tm-module-filters" role="tablist" aria-label="Filtrar por modulo temporal">
                        @foreach (($allModules ?? $modules) as $module)
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

            @php
                $tmQueryBuscar = request()->query('buscar', '');
                $tmBuscarFilterStr = is_array($tmQueryBuscar) ? '' : (string) $tmQueryBuscar;
                $tmQueryMr = request()->query('microrregion_id');
                $tmMicrorregionFilterId = is_array($tmQueryMr) ? 0 : (int) $tmQueryMr;
            @endphp
            <div class="tm-module-records-panels">
                @foreach (($allModules ?? $modules) as $module)
                    @php
                        $municipioField = $module->fields->firstWhere('type', 'municipio');
                        $isModuleActive = (int) ($activeModuleId ?? 0) === (int) $module->id || ((int) ($activeModuleId ?? 0) === 0 && $loop->first);
                    @endphp
                    <section
                        class="tm-module-records-panel {{ $isModuleActive ? 'is-active' : '' }}"
                        id="module-records-{{ $module->id }}"
                        data-bulk-delete-url="{{ route('temporary-modules.entries.bulk-destroy', ['module' => $module->id]) }}"
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
                                            value="{{ $isModuleActive ? e($tmBuscarFilterStr) : '' }}"
                                            placeholder="Texto en los datos del registro…"
                                            autocomplete="off"
                                        >
                                        <button type="button" class="tm-records-search-clear" data-tm-filter-buscar-clear aria-label="Quitar texto" @if (! $isModuleActive || trim($tmBuscarFilterStr) === '') hidden @endif>
                                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                        </button>
                                    </span>
                                </label>
                                <label class="tm-records-filter-field">
                                    <span>Microregión</span>
                                    <select class="tm-records-filter-select" data-tm-filter-microrregion>
                                        <option value="">Todos</option>
                                        @foreach ($microrregionesAsignadas ?? [] as $micro)
                                            <option value="{{ $micro->id }}" @selected($isModuleActive && $tmMicrorregionFilterId === (int) $micro->id)>
                                                MR {{ $micro->microrregion }} — {{ $micro->cabecera }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="tm-records-filter-field">
                                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                        <button type="button" class="tm-btn tm-btn-primary tm-btn-sm" data-tm-bulk-toggle title="Selección masiva">Eliminar varios</button>
                                        <button type="button" class="tm-btn tm-btn-outline tm-btn-sm" data-tm-bulk-edit-open data-module-id="{{ $module->id }}" title="Editar varios registros">
                                            <i class="fa-solid fa-table-cells" aria-hidden="true"></i> Editar en hoja de cálculo
                                        </button>
                                        <button type="button" class="tm-btn tm-btn-sm tm-btn-danger tm-hidden" data-tm-bulk-delete-trigger>
                                            <i class="fa-solid fa-trash-can"></i> <span data-tm-bulk-count>0</span>
                                        </button>
                                    </div>
                                </label>
                            </div>
                        </div>
                        @php
                            $recordsLoaded = false;
                        @endphp
                        <div
                            class="tm-records-fragment-host"
                            data-fragment-url="{{ $fragmentRecordsUrl ?? '' }}"
                            data-module-id="{{ $module->id }}"
                            @unless ($recordsLoaded) hidden @endunless
                        >
                            {{-- Registros cargados vía AJAX fragment --}}
                        </div>
                        <div class="tm-records-panel-placeholder" @if ($recordsLoaded) hidden @endif>
                            <p class="tm-module-subtitle">{{ $module->name }}</p>
                            @unless ($recordsLoaded)
                                <p class="tm-muted" style="font-size:0.85rem;">Selecciona este módulo arriba para cargar el listado.</p>
                            @endunless
                        </div>

                        <div
                            class="tm-modal tm-bulk-edit-modal"
                            id="tmBulkEditModal-{{ $module->id }}"
                            aria-hidden="true"
                            role="dialog"
                            aria-modal="true"
                            aria-labelledby="tmBulkEditTitle-{{ $module->id }}"
                            data-bulk-data-url="{{ route('temporary-modules.fragment.bulk-edit-data') }}"
                            data-submit-url="{{ route('temporary-modules.submit', $module->id) }}"
                            data-module-id="{{ $module->id }}"
                            data-show-mr-select="{{ ($microrregionesAsignadas ?? collect())->count() > 1 ? '1' : '0' }}"
                            data-preview-url-template="{{ url('/modulos-temporales/'.$module->id.'/registros/__EID__/archivo/__FKEY__') }}"
                        >
                            <div class="tm-modal-backdrop" data-tm-bulk-edit-dismiss></div>
                            <div class="tm-modal-dialog tm-modal-dialog-bulk-edit">
                                <div class="tm-modal-head">
                                    <div class="tm-modal-head-stack">
                                        <div class="tm-bulk-head-title-row">
                                            <h3 id="tmBulkEditTitle-{{ $module->id }}">Editar en hoja de cálculo</h3>
                                        </div>
                                        <p class="tm-modal-subtitle tm-bulk-edit-module-name">{{ $module->name }}</p>
                                    </div>
                                    <div class="tm-modal-head-actions">
                                        <button type="button" class="tm-btn tm-btn-outline tm-btn-sm" data-tm-bulk-sheet-toggle>
                                            <i class="fa-solid fa-table-cells" aria-hidden="true"></i> Editar en hojas de cálculo
                                        </button>
                                        <button type="button" class="tm-modal-close" data-tm-bulk-edit-dismiss aria-label="Cerrar">
                                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="tm-modal-body tm-bulk-edit-body">
                                    <div class="tm-bulk-edit-loading" data-tm-bulk-loading>
                                        <p class="tm-muted">Cargando registros…</p>
                                    </div>
                                    <div class="tm-bulk-edit-main tm-hidden" data-tm-bulk-main>
                                        <aside class="tm-bulk-edit-left">
                                            <label class="tm-bulk-toolbar-label">
                                                <span class="tm-bulk-toolbar-label-text">Buscar en esta lista</span>
                                                <input type="search" class="tm-input tm-input-sm" data-tm-bulk-list-search placeholder="Buscar en cualquier campo del registro…" autocomplete="off">
                                            </label>
                                            <details class="tm-bulk-empty-filters-details" open>
                                                <summary class="tm-bulk-empty-filters-summary">Buscar datos sin responder</summary>
                                                <div class="tm-bulk-empty-fields-scroll" data-tm-bulk-empty-fields></div>
                                                <button type="button" class="tm-btn tm-btn-ghost tm-btn-sm tm-bulk-empty-reset" data-tm-bulk-empty-filter-reset>Limpiar columnas marcadas</button>
                                            </details>
                                            <p class="tm-bulk-list-summary tm-muted" data-tm-bulk-list-summary aria-live="polite"></p>
                                            <div class="tm-bulk-edit-list" data-tm-bulk-list></div>
                                        </aside>
                                        <div class="tm-bulk-edit-right">
                                            <div class="tm-bulk-edit-empty" data-tm-bulk-form-empty>
                                                <p class="tm-bulk-edit-empty-title">Selecciona un registro</p>
                                                <p class="tm-muted">Haz clic en un elemento de la lista de la izquierda para cargar sus campos aquí.</p>
                                                <p class="tm-muted tm-bulk-form-empty-extra tm-hidden" data-tm-bulk-form-empty-extra></p>
                                            </div>
                                            <form class="tm-form tm-bulk-edit-form tm-hidden" data-tm-bulk-form novalidate>
                                                <div class="tm-bulk-field-filter-wrap" data-tm-bulk-field-filter-wrap>
                                                    <label class="tm-bulk-toolbar-label">
                                                        <span class="tm-bulk-toolbar-label-text">Buscar campo por nombre</span>
                                                        <input type="search" class="tm-input tm-input-sm" data-tm-bulk-field-filter placeholder="Ej. teléfono, fecha…" autocomplete="off">
                                                    </label>
                                                </div>
                                                <div class="tm-bulk-edit-mr-wrap tm-hidden" data-tm-bulk-mr-wrap>
                                                    <label class="tm-entry-field">
                                                        Microrregión de captura
                                                        <select name="bulk_selected_microrregion_id" class="tm-input tm-bulk-mr-select" data-tm-bulk-mr-select></select>
                                                    </label>
                                                </div>
                                                <div class="tm-grid tm-grid-2 tm-entry-grid tm-bulk-edit-fields" data-tm-bulk-fields></div>
                                                <div class="tm-actions tm-bulk-edit-single-actions">
                                                    <button type="button" class="tm-btn tm-btn-primary tm-btn-sm" data-tm-bulk-save-one type="button">
                                                        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar este registro
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="tm-bulk-sheet-view tm-hidden" data-tm-bulk-sheet>
                                        <div class="tm-bulk-sheet-toolbar">
                                            <label class="tm-bulk-toolbar-label tm-bulk-sheet-search">
                                                <span class="tm-bulk-toolbar-label-text">Buscar en la hoja</span>
                                                <input type="search" class="tm-input tm-input-sm" data-tm-bulk-sheet-search placeholder="Buscar en cualquier celda…" autocomplete="off">
                                            </label>
                                            <div class="tm-bulk-sheet-zoom tm-hidden" data-tm-bulk-sheet-zoom aria-label="Zoom de hoja">
                                                <button type="button" class="tm-btn tm-btn-ghost tm-btn-sm" data-tm-bulk-sheet-zoom-out title="Alejar">-</button>
                                                <span class="tm-bulk-sheet-zoom-val" data-tm-bulk-sheet-zoom-val>100%</span>
                                                <button type="button" class="tm-btn tm-btn-ghost tm-btn-sm" data-tm-bulk-sheet-zoom-in title="Acercar">+</button>
                                                <button type="button" class="tm-btn tm-btn-ghost tm-btn-sm" data-tm-bulk-sheet-zoom-reset title="Restablecer">Reset</button>
                                            </div>
                                            <button type="button" class="tm-btn tm-btn-ghost tm-btn-sm" data-tm-bulk-sheet-clear-filters>Limpiar filtros</button>
                                            <button type="button" class="tm-btn tm-btn-outline tm-btn-sm" data-tm-bulk-sheet-exit>Volver al editor</button>
                                        </div>
                                        <div class="tm-excel-sheet-container tm-bulk-sheet-container">
                                            <div class="tm-excel-sheet-inner tm-bulk-sheet-inner" data-tm-bulk-sheet-inner></div>
                                        </div>
                                    </div>
                                    <div class="tm-bulk-edit-error tm-hidden" data-tm-bulk-error></div>
                                </div>
                                <div class="tm-bulk-edit-footer">
                                    <span class="tm-bulk-edit-counter" data-tm-bulk-counter>0 campos editados en 0 registros</span>
                                    <div class="tm-bulk-edit-footer-actions">
                                        <button type="button" class="tm-btn tm-btn-outline tm-btn-sm" data-tm-bulk-dismiss>Salir</button>
                                        <button type="button" class="tm-btn tm-btn-secondary tm-btn-sm tm-hidden" data-tm-bulk-save-exit>
                                            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Salir y guardar cambios
                                        </button>
                                        <button type="button" class="tm-btn tm-btn-primary tm-btn-sm" data-tm-bulk-save-all>
                                            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar cambios
                                        </button>
                                    </div>
                                </div>
                            </div>
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

    <div class="tm-modal" id="tmFilePreviewModal" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="tm-modal-backdrop" data-close-file-preview></div>
        <div class="tm-modal-dialog tm-image-modal-dialog">
            <div class="tm-modal-head">
                <h3 id="tmFilePreviewTitle">Vista previa de documento</h3>
                <button type="button" class="tm-modal-close" data-close-file-preview aria-label="Cerrar">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="tm-modal-body">
                <iframe id="tmFilePreviewFrame" title="Vista previa de documento" style="width:100%; min-height:70vh; border:0; border-radius:10px;"></iframe>
            </div>
        </div>
    </div>

    <div class="tm-modal" id="tmImportErrorsModal" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="tm-modal-backdrop" data-close-module-preview></div>
        <div class="tm-modal-dialog tm-modal-dialog-entry">
            <div class="tm-modal-head">
                <div class="tm-modal-head-stack">
                    <h3>Registros no importados</h3>
                    <p class="tm-modal-subtitle">Lista de filas fallidas, motivo y sugerencias de municipio.</p>
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <button type="button" class="tm-btn tm-btn-sm tm-btn-danger" id="tmBtnClearAllErrors" style="display:none;" aria-label="Vaciar todos los errores" title="Vaciar todos los errores">
                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                        <span>Vaciar todo</span>
                    </button>
                    <button type="button" class="tm-modal-close" data-close-module-preview aria-label="Cerrar">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="tm-modal-body" style="max-height:70vh; overflow:auto;">
                <div data-errors-log-empty class="tm-hidden" style="padding:10px; border:1px dashed var(--clr-border); border-radius:10px; color:var(--clr-text-light);">
                    No hay errores pendientes en sesión.
                </div>
                <div data-errors-log-list style="display:grid; gap:10px;"></div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
window.TM_DELEGATE_BOOT = @json($delegateScriptPayload ?? []);
</script>
<script src="{{ asset('assets/js/modules/temporary-modules-delegate-index.js') }}?v={{ @filemtime(public_path('assets/js/modules/temporary-modules-delegate-index.js')) ?: time() }}"></script>

<div id="tmGlobalExcelDropOverlay" class="tm-global-drop-overlay">
    <div class="tm-global-drop-content">
        <i class="fa-solid fa-file-arrow-up"></i>
        <h3>¡Suelta tu archivo aquí!</h3>
        <p>Arrastra tu archivo Excel o PDF a cualquier área dentro de la página para comenzar la importación.</p>
    </div>
</div>

@endpush
