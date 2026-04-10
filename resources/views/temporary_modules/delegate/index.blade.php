@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
<style>
    :root {
        --clr-error-text: var(--clr-primary);
    }
    html.theme-dark {
        --clr-error-text: #c79b66;
    }
    .tm-row-glow-purple > td {
        box-shadow: inset 0 2px 0 #8e44ad, inset 0 -2px 0 #8e44ad !important;
        background-color: rgba(142, 68, 173, 0.1) !important;
    }
    .tm-row-glow-purple > td:first-child { box-shadow: inset 2px 2px 0 #8e44ad, inset 0 -2px 0 #8e44ad !important; }
    .tm-row-glow-purple > td:last-child { box-shadow: inset -2px 2px 0 #8e44ad, inset 0 -2px 0 #8e44ad, inset 0 2px 0 #8e44ad !important; }

    .tm-row-glow-orange > td {
        box-shadow: inset 0 2px 0 #e67e22, inset 0 -2px 0 #e67e22 !important;
        background-color: rgba(230, 126, 34, 0.1) !important;
    }
    .tm-row-glow-orange > td:first-child { box-shadow: inset 2px 2px 0 #e67e22, inset 0 -2px 0 #e67e22 !important; }
    .tm-row-glow-orange > td:last-child { box-shadow: inset -2px 2px 0 #e67e22, inset 0 -2px 0 #e67e22, inset 0 2px 0 #e67e22 !important; }

    .tm-error-log-card {
        cursor: pointer;
        border: 2px solid transparent !important;
    }
    .tm-error-log-card:hover {
        border-color: var(--clr-error-text) !important;
        background: rgba(0,0,0,0.02) !important;
    }
    .tm-error-log-card.is-active-row {
        border-color: #246257 !important;
        box-shadow: 0 0 8px rgba(142,68,173,0.3) !important;
    }

    .tm-records-hscroll {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        align-items: center;
        gap: 8px;
    }

    .tm-records-hscroll-btn {
        width: 34px;
        height: 34px;
        border: 1px solid var(--clr-border);
        border-radius: 999px;
        background: var(--clr-bg-card, #fff);
        color: var(--clr-text-main);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.2s ease, color 0.2s ease, opacity 0.2s ease;
    }

    .tm-records-hscroll-btn:hover:not([disabled]) {
        background: rgba(0, 0, 0, 0.05);
    }

    .tm-records-hscroll-btn[disabled] {
        opacity: 0.35;
        cursor: not-allowed;
    }

    @media (max-width: 900px) {
        .tm-records-hscroll {
            grid-template-columns: 1fr;
            gap: 6px;
        }

        .tm-records-hscroll-btn {
            display: none;
        }
    }
</style>
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
                    <div class="tm-modal-body">
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

                                        @if ($microsAsignadas->count() > 0)
                                            <label class="tm-excel-search-all-wrap tm-excel-search-all-wrap-el">
                                                <input type="checkbox" class="tm-excel-search-all">
                                                <span>Buscar en todos los municipios de mis microrregiones</span>
                                            </label>
                                        @endif

                                        @if ($microsAsignadas->count() > 1)
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
                                            @if ($microsAsignadas->count() === 1)
                                                <input type="hidden" class="tm-excel-mr-input" name="selected_microrregion_id" value="{{ $microsAsignadas->first()->id }}">
                                            @endif
                                        @endif

                                        @if ($microsAsignadas->count() > 0)
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
                                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edición múltiple
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
                                        <h3 id="tmBulkEditTitle-{{ $module->id }}">Edición múltiple</h3>
                                        <p class="tm-modal-subtitle tm-bulk-edit-module-name">{{ $module->name }}</p>
                                    </div>
                                    <button type="button" class="tm-modal-close" data-tm-bulk-edit-dismiss aria-label="Cerrar">
                                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div class="tm-modal-body tm-bulk-edit-body">
                                    <div class="tm-bulk-edit-loading" data-tm-bulk-loading>
                                        <p class="tm-muted">Cargando registros…</p>
                                    </div>
                                    <div class="tm-bulk-edit-main tm-hidden" data-tm-bulk-main>
                                        <aside class="tm-bulk-edit-left">
                                            <p class="tm-bulk-edit-hint">Registros (mismos filtros que el listado)</p>
                                            <div class="tm-bulk-edit-list" data-tm-bulk-list></div>
                                        </aside>
                                        <div class="tm-bulk-edit-right">
                                            <div class="tm-bulk-edit-empty" data-tm-bulk-form-empty>
                                                <p class="tm-muted">Selecciona un registro para editar.</p>
                                            </div>
                                            <form class="tm-form tm-bulk-edit-form tm-hidden" data-tm-bulk-form novalidate>
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
    const DELEGATE_BOOT = @json($delegateScriptPayload ?? []);
    let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || DELEGATE_BOOT.csrfToken;

    async function refreshCsrfToken() {
        try {
            const r = await fetch(DELEGATE_BOOT.csrfRefreshUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (r.redirected || r.status === 401) {
                window.location.href = DELEGATE_BOOT.loginUrl;
                return false;
            }
            if (!r.ok) throw new Error('refresh failed');
            const j = await r.json();
            if (j.token) {
                csrfToken = j.token;
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.setAttribute('content', j.token);
            }
            return true;
        } catch (_) { return false; }
    }

    async function csrfFetch(url, opts = {}) {
        const res = await fetch(url, opts);
        if (res.status === 419) {
            const ok = await refreshCsrfToken();
            if (!ok) return res;
            if (opts.body instanceof FormData) {
                opts.body.set('_token', csrfToken);
            }
            if (opts.headers && typeof opts.headers === 'object' && !(opts.headers instanceof Headers)) {
                if ('X-CSRF-TOKEN' in opts.headers) opts.headers['X-CSRF-TOKEN'] = csrfToken;
            }
            return fetch(url, opts);
        }
        return res;
    }

    async function safeJsonParse(response) {
        if (response.status === 419) {
            throw new Error('Tu sesión ha expirado. Recarga la página para continuar.');
        }
        var ct = response.headers.get('content-type') || '';
        if (!response.ok) {
            if (ct.includes('application/json')) {
                var errJson = await response.json().catch(function() { return {}; });
                // 422/403: devolver el objeto para que importSingleRow / validación muestren j.error (no solo el mensaje genérico)
                if (response.status === 422 || response.status === 403) {
                    return errJson;
                }
                var msg = errJson.message || '';
                if (!msg && errJson.errors) {
                    msg = Object.values(errJson.errors).flat().join(' ');
                }
                throw new Error((msg && String(msg).trim()) || ('Error del servidor: HTTP ' + response.status));
            }
            var text = await response.text().catch(function() { return ''; });
            if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                throw new Error('El servidor no pudo procesar la solicitud (posible límite de memoria o tiempo). Intenta con un archivo más pequeño.');
            }
            throw new Error(text.substring(0, 200) || ('Error del servidor: HTTP ' + response.status));
        }
        if (!ct.includes('application/json')) {
            var text2 = await response.text().catch(function() { return ''; });
            throw new Error(text2.substring(0, 200) || ('Respuesta inesperada: HTTP ' + response.status));
        }
        return response.json();
    }

    /** Evita enviar "" en microrregion_id (Laravel falla regla integer). */
    function tmParseMicrorregionId(mrId) {
        if (mrId === '' || mrId === undefined || mrId === null) {
            return null;
        }
        var n = parseInt(String(mrId).trim(), 10);
        return Number.isNaN(n) ? null : n;
    }

    /**
     * Módulos sin municipio: importar-fila debe enviar la MR elegida en el modal Excel.
     * Las sugerencias de select no llevan data-mr; se completa desde la tarjeta o el .tm-excel-mr-input.
     */
    function tmResolveMicrorregionForRetry(card, moduleId, microrregionId) {
        var parsed = tmParseMicrorregionId(microrregionId);
        if (parsed !== null) {
            return parsed;
        }
        if (card && card.dataset && card.dataset.microrregionId) {
            var fromCard = tmParseMicrorregionId(card.dataset.microrregionId);
            if (fromCard !== null) {
                return fromCard;
            }
        }
        var mid = (moduleId !== undefined && moduleId !== null) ? String(moduleId).trim() : '';
        if (mid !== '') {
            var exModal = document.getElementById('tmImportarExcelModal-' + mid);
            var mrInput = exModal ? exModal.querySelector('.tm-excel-mr-input') : null;
            if (mrInput && mrInput.value !== '') {
                return tmParseMicrorregionId(mrInput.value);
            }
        }
        return null;
    }

    document.addEventListener('DOMContentLoaded', function () {
        // --- Persistent Error Log Helpers ---
        const saveImportErrors = (moduleId, errors, singleUrl) => {
            if (!errors || errors.length === 0) {
                sessionStorage.removeItem(`tm_errors_${moduleId}`);
            } else {
                sessionStorage.setItem(`tm_errors_${moduleId}`, JSON.stringify({
                    errors: errors,
                    singleUrl: singleUrl,
                    timestamp: Date.now()
                }));
            }
            updateErrorIndicator(moduleId);
            updateHeaderErrorIndicator();

            if (typeof window.__tmRefreshImportErrorsModal === 'function') {
                window.__tmRefreshImportErrorsModal();
            }
        };

        const parseStoredErrors = (moduleId) => {
            const data = sessionStorage.getItem(`tm_errors_${moduleId}`);
            if (!data) {
                return null;
            }

            try {
                return JSON.parse(data);
            } catch (e) {
                sessionStorage.removeItem(`tm_errors_${moduleId}`);
                return null;
            }
        };

        const getModulesWithSessionErrors = () => {
            const modules = [];
            document.querySelectorAll('[data-session-errors-module]').forEach((btn) => {
                const moduleId = String(btn.getAttribute('data-session-errors-module') || '').trim();
                if (!moduleId) {
                    return;
                }

                const parsed = parseStoredErrors(moduleId);
                const count = (parsed?.errors || []).length;
                if (count > 0) {
                    modules.push({
                        moduleId,
                        count,
                        timestamp: Number(parsed.timestamp || 0)
                    });
                }
            });

            return modules;
        };

        const updateHeaderErrorIndicator = () => {
            const headerBtn = document.getElementById('tmBtnHeaderSessionErrors');
            if (!headerBtn) {
                return;
            }

            const total = getModulesWithSessionErrors().reduce((sum, item) => sum + item.count, 0);
            const countEl = headerBtn.querySelector('.tm-error-count');
            if (countEl) {
                countEl.textContent = String(total);
            }

            headerBtn.classList.toggle('tm-hidden', total === 0);
        };

        const normalizeRowErrorMessage = (err) => {
            const raw = String(err?.message || '').trim();
            if (!raw) {
                return 'No se pudo importar esta fila.';
            }

            const cleaned = raw.replace(/^fila\s+\d+\s*:\s*/i, '').trim();
            return cleaned || raw;
        };
        window.normalizeRowErrorMessage = normalizeRowErrorMessage;

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        window.escapeHtml = escapeHtml;

        const getModuleNameById = (moduleId) => {
            const subtitle = document.querySelector(`#delegate-preview-${moduleId} .tm-modal-subtitle`);
            if (subtitle && subtitle.textContent) {
                return subtitle.textContent.trim();
            }

            return `Módulo ${moduleId}`;
        };
        window.getModuleNameById = getModuleNameById;

        const getFailedFields = (err) => {
            if (Array.isArray(err?.failed_fields) && err.failed_fields.length > 0) {
                return err.failed_fields;
            }

            const normalizedMessage = normalizeRowErrorMessage(err);
            const match = normalizedMessage.match(/\(([^)]+)\)/);
            if (match && match[1]) {
                return [{
                    key: '',
                    label: match[1].trim(),
                    reason: normalizedMessage,
                    received: '',
                }];
            }

            return [];
        };
        window.getFailedFields = getFailedFields;

        const getFieldLabel = (moduleId, key) => {
            const entryModal = document.getElementById('delegate-preview-' + moduleId);
            if (!entryModal) return key;
            const input = entryModal.querySelector(`[name="values[${key}]"], [name="values[${key}__primary]"], [name="values[${key}__secondary]"]`);
            if (input) {
                const labelWrap = input.closest('.tm-entry-field');
                if (labelWrap) return labelWrap.childNodes[0].textContent.trim();
            }
            return key;
        };
        window.getFieldLabel = getFieldLabel;

        const renderErrorCardHtml = (err, idx, moduleId, singleUrl, cardId, isLogModal = false) => {
            const moduleName = isLogModal ? getModuleNameById(moduleId) : '';
            const failedFields = getFailedFields(err);
            // Contar campos que tienen sugerencias (para modo multi-corrección)
            const fieldsWithSugg = failedFields.filter(f => f.suggestions && f.suggestions.length > 0).length;
            const isMultiCorrect = fieldsWithSugg >= 2;

            let failedFieldsHtml = '';
            if (failedFields.length > 0) {
                failedFieldsHtml = '<div style="margin-top:8px;">';
                failedFields.forEach(f => {
                    let fieldSugg = '';
                    // Sugerencias específicas de este campo (ej: para Listas o Listas múltiples)
                    if (f.suggestions && f.suggestions.length > 0) {
                        fieldSugg = '<div style="margin-top:8px; display:flex; flex-wrap:wrap; gap:6px;">' +
                            '<span style="width:100%; font-size:0.65rem; color:var(--clr-error-text); font-weight:700;">VALORES DISPONIBLES:</span>' +
                            f.suggestions.map(s => `
                                <button type="button" class="tm-btn tm-btn-sm tm-btn-outline ${isMultiCorrect ? 'tm-idx-stage-btn' : 'tm-idx-sugg-btn'}"
                                    data-idx="${idx}" data-val="${escapeHtml(s)}" data-key="${escapeHtml(f.key)}"
                                    data-surl="${escapeHtml(singleUrl)}" data-mid="${escapeHtml(moduleId)}"
                                    data-cid="${escapeHtml(cardId)}" data-row="${err.row || 0}"
                                    style="font-size:0.68rem; padding:3px 8px; font-weight:600; text-transform:uppercase;">
                                    ${escapeHtml(s)}
                                </button>
                            `).join('') +
                        '</div>';
                    }
                    failedFieldsHtml += `
                        <div style="margin-bottom:12px; padding:10px; background:rgba(0,0,0,0.03); border-radius:8px;">
                            <div style="font-size:0.8rem; line-height:1.4;">
                                <strong style="color:var(--clr-text-main);">${escapeHtml(f.label || f.key || 'Campo')}</strong>:
                                <span style="color:var(--clr-text-light);">${escapeHtml(f.reason || 'Dato no válido')}</span>
                            </div>
                            ${f.received ? `<div style="font-size:0.75rem; margin-top:4px; font-style:italic; opacity:0.8;">Valor en Excel: "${escapeHtml(f.received)}"</div>` : ''}
                            ${fieldSugg}
                        </div>
                    `;
                });
                failedFieldsHtml += '</div>';
            }

            // Sugerencias generales (normalmente para municipios/microrregiones)
            let generalSuggHtml = '';
            if (!failedFields.some(f => f.suggestions && f.suggestions.length > 0) && err.suggestions && err.suggestions.length > 0) {
                generalSuggHtml = '<div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:6px;">' +
                    '<span style="width:100%; font-weight:600; font-size:0.75rem; margin-bottom:4px; display:block;">¿Quisiste decir? (Municipios)</span>' +
                    err.suggestions.map(s => `
                        <button type="button" class="tm-btn tm-btn-sm tm-btn-outline tm-idx-sugg-btn"
                            data-idx="${idx}" data-val="${escapeHtml(s.municipio || '')}" data-mr="${Number(s.microrregion_id || 0)}"
                            data-surl="${escapeHtml(singleUrl)}" data-mid="${escapeHtml(moduleId)}"
                            data-cid="${escapeHtml(cardId)}" data-row="${err.row || 0}"
                            style="font-size:0.7rem; padding:4px 8px;">
                            ${escapeHtml(s.municipio)}
                        </button>
                    `).join('') +
                '</div>';
            }

            // Previsualización de registro en conflicto (si existe)
            let conflictPreviewHtml = '';
            if (err.conflict_data && Object.keys(err.conflict_data).length > 0) {
                let conflictDetails = '';
                Object.entries(err.conflict_data).forEach(([key, val]) => {
                    if (val === null || val === undefined || val === '') return;
                    const label = getFieldLabel(moduleId, key);
                    let displayVal = '';
                    if (typeof val === 'string' && val.startsWith('data:image/')) {
                        displayVal = '<img src="' + val + '" style="max-height:40px; border-radius:4px; margin-top:2px;">';
                    } else {
                        displayVal = escapeHtml(Array.isArray(val) ? val.join(', ') : val);
                    }
                    conflictDetails += `
                        <div style="margin-bottom:4px;">
                            <span style="font-weight:700; font-size:0.65rem; color:var(--clr-text-light); text-transform:uppercase; margin-right:4px;">${escapeHtml(label)}:</span>
                            <span style="font-size:0.75rem; color:var(--clr-text-main); font-weight:500;">${displayVal}</span>
                        </div>
                    `;
                });

                conflictPreviewHtml = `
                    <div style="margin:10px 0; padding:12px; background:rgba(124, 77, 255, 0.08); border:1px solid rgba(124, 77, 255, 0.2); border-radius:10px;">
                        <div style="font-size:0.7rem; font-weight:800; color:#7c4dff; margin-bottom:8px; display:flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-circle-exclamation"></i> REGISTRO ORIGINAL (CONFIRMADO)
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:4px 12px;">
                            ${conflictDetails}
                        </div>
                    </div>
                `;
            }

            return `
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                    <div>
                        <div style="color:var(--clr-error-text); font-weight:800; font-size:0.9rem;">
                            ${isLogModal ? `${escapeHtml(moduleName)} · ` : ''}Fila ${err.row}
                            ${isLogModal ? `<button type="button" class="tm-btn-delete-error" data-card-id="${cardId}" title="Eliminar este error" style="border:0; background:transparent; color:var(--clr-error-text); cursor:pointer; padding:0; font-size:0.9em; margin-left:8px;"><i class="fa-solid fa-trash-alt"></i></button>` : ''}
                        </div>
                        <div style="font-size:0.78rem; font-weight:500; color:var(--clr-text-light); margin-top:2px;">${escapeHtml(normalizeRowErrorMessage(err))}</div>
                    </div>
                    ${isLogModal ? '<span class="tm-upload-meta-pill" style="font-size:0.65rem; padding:2px 8px;">PENDIENTE</span>' : ''}
                </div>
                ${failedFieldsHtml}
                ${conflictPreviewHtml}
                <div style="margin-top:12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <button type="button" class="tm-btn tm-btn-sm tm-btn-primary"
                        onclick="openErrorModifyModal('${cardId}')"
                        style="padding:4px 10px; font-size:0.75rem;">
                        <i class="fa-solid fa-pen"></i> Corregir datos manualmente
                    </button>
                    ${isMultiCorrect ? `<button type="button" class="tm-btn tm-btn-sm tm-idx-save-staged-btn" data-cid="${escapeHtml(cardId)}" data-idx="${idx}" data-surl="${escapeHtml(singleUrl)}" data-mid="${escapeHtml(moduleId)}" data-row="${err.row || 0}" style="padding:4px 12px; font-size:0.75rem; background:var(--clr-secondary,#2e7d32); color:#fff; border:0; border-radius:6px; cursor:pointer; display:none;"><i class="fa-solid fa-check"></i> Guardar correcciones</button>` : ''}
                    ${generalSuggHtml}
                </div>
            `;
        };
        window.renderErrorCardHtml = renderErrorCardHtml;

        window.__tmSaveImportErrors = saveImportErrors;

        const renderErrorsLogModal = (filterModuleId = null) => {
            const modal = document.getElementById('tmImportErrorsModal');
            if (!modal) {
                return;
            }

            const emptyEl = modal.querySelector('[data-errors-log-empty]');
            const listEl = modal.querySelector('[data-errors-log-list]');
            if (!listEl || !emptyEl) {
                return;
            }

            const moduleFilter = filterModuleId ? String(filterModuleId) : null;
            const modulesWithErrors = getModulesWithSessionErrors().sort((a, b) => b.timestamp - a.timestamp);
            const filtered = moduleFilter
                ? modulesWithErrors.filter((m) => String(m.moduleId) === moduleFilter)
                : modulesWithErrors;

            listEl.innerHTML = '';
            if (filtered.length === 0) {
                emptyEl.classList.remove('tm-hidden');

                return;
            }

            emptyEl.classList.add('tm-hidden');

            filtered.forEach(({ moduleId }) => {
                const parsed = parseStoredErrors(moduleId);
                const errors = Array.isArray(parsed?.errors) ? parsed.errors : [];
                const singleUrl = String(parsed?.singleUrl || '');
                const moduleName = getModuleNameById(moduleId);

                errors.forEach((err, idx) => {
                    const cardId = `tmErrLogRow_${moduleId}_${idx}`;
                    const card = document.createElement('div');
                    card.className = 'tm-error-log-card';
                    card.id = cardId;
                    card.dataset.rowData = JSON.stringify(err?.data || {});
                    if (err?.data_urls) card.dataset.dataUrls = JSON.stringify(err.data_urls);
                    if (err?.conflict_data) card.dataset.conflictData = JSON.stringify(err.conflict_data);
                    card.dataset.municipioKey = String(err?.municipio_key || 'municipio');
                    if (err?.selected_microrregion_id != null && err.selected_microrregion_id !== '') {
                        card.dataset.microrregionId = String(err.selected_microrregion_id);
                    }
                    card.dataset.moduleId = moduleId;
                    card.dataset.singleUrl = singleUrl;
                    card.dataset.errorIndex = idx;
                    card.style = 'padding:15px; border:1px solid var(--clr-border); border-radius:12px; background:var(--clr-bg); font-size:0.85rem; transition: all 0.3s ease;';
                    card.innerHTML = renderErrorCardHtml(err, idx, moduleId, singleUrl, cardId, true);
                    listEl.appendChild(card);
                });
            });
        };

        const deleteErrorFromSession = (moduleId, errorIndex) => {
            const data = parseStoredErrors(moduleId);
            if (data && Array.isArray(data.errors)) {
                data.errors.splice(errorIndex, 1);
                saveImportErrors(moduleId, data.errors, data.singleUrl);
            }
        };

        const clearAllErrorsFromSession = () => {
            const modulesWithErrors = getModulesWithSessionErrors();
            modulesWithErrors.forEach(({ moduleId }) => {
                sessionStorage.removeItem(`tm_errors_${moduleId}`);
                updateErrorIndicator(moduleId);
            });
            updateHeaderErrorIndicator();

            if (typeof window.__tmRefreshImportErrorsModal === 'function') {
                window.__tmRefreshImportErrorsModal();
            }
        };

        const openErrorsLogModal = (opener, filterModuleId = null) => {
            const modal = document.getElementById('tmImportErrorsModal');
            if (!modal) {
                return;
            }

            if (filterModuleId) {
                modal.setAttribute('data-filter-module', String(filterModuleId));
            } else {
                modal.removeAttribute('data-filter-module');
            }

            renderErrorsLogModal(filterModuleId);
            updateClearButtonVisibility();
            openModal(modal, opener || null);
        };

        window.__tmRefreshImportErrorsModal = () => {
            const modal = document.getElementById('tmImportErrorsModal');
            if (!modal || modal.getAttribute('aria-hidden') !== 'false') {
                return;
            }

            const filterModuleId = modal.getAttribute('data-filter-module');
            renderErrorsLogModal(filterModuleId || null);
            updateClearButtonVisibility();
        };

        const updateClearButtonVisibility = () => {
            const modal = document.getElementById('tmImportErrorsModal');
            const clearBtn = document.getElementById('tmBtnClearAllErrors');
            if (!modal || !clearBtn) {
                return;
            }

            const modulesWithErrors = getModulesWithSessionErrors();
            const hasErrors = modulesWithErrors.length > 0;
            clearBtn.style.display = hasErrors ? '' : 'none';
        };

        const updateErrorIndicator = (moduleId) => {
            const data = parseStoredErrors(moduleId);
            const btn = document.getElementById(`tmBtnSessionErrors-${moduleId}`);
            if (!btn) return;

            if (data) {
                const count = (data.errors || []).length;
                if (count > 0) {
                    btn.querySelector('.tm-error-count').textContent = count;
                    btn.classList.remove('tm-hidden');
                } else {
                    btn.classList.add('tm-hidden');
                }
            } else {
                btn.classList.add('tm-hidden');
            }
        };

        // Initialize indicators on load
        document.querySelectorAll('[data-session-errors-module]').forEach(btn => {
            updateErrorIndicator(btn.getAttribute('data-session-errors-module'));
        });
        updateHeaderErrorIndicator();

        const renderSessionErrors = (modal, moduleId) => {
            const parsed = parseStoredErrors(moduleId);
            const errSection = modal.querySelector('.tm-excel-errors-section');
            const errList = modal.querySelector('.tm-excel-errors-list');
            if (!errSection || !errList) return;

            if (!parsed || !Array.isArray(parsed.errors) || parsed.errors.length === 0) {
                errList.innerHTML = '';
                errSection.classList.add('tm-hidden');
                return;
            }

            const errors = parsed.errors || [];
            const singleUrl = parsed.singleUrl;

            errSection.classList.remove('tm-hidden');
            errList.innerHTML = '';
            errors.forEach((err, idx) => {
                const cardId = `tmErrRow_${moduleId}_${idx}`;
                const card = document.createElement('div');
                card.className = 'tm-error-log-card';
                card.id = cardId;
                card.dataset.rowData = JSON.stringify(err?.data);
                card.dataset.municipioKey = String(err.municipio_key || 'municipio');
                if (err?.selected_microrregion_id != null && err.selected_microrregion_id !== '') {
                    card.dataset.microrregionId = String(err.selected_microrregion_id);
                }
                card.style = 'padding:15px; border:1px solid var(--clr-border); border-radius:12px; background:var(--clr-bg); font-size:0.85rem; transition: all 0.3s ease;';
                card.innerHTML = renderErrorCardHtml(err, idx, moduleId, singleUrl, cardId, false);
                errList.appendChild(card);
            });
        };

        // Global check for session error clicks
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-session-errors-module]');
            if (!btn) return;
            const moduleId = btn.getAttribute('data-session-errors-module');
            if (!moduleId) {
                return;
            }

            openErrorsLogModal(btn, moduleId);
        });

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-session-errors-open]');
            if (!btn) {
                return;
            }

            const modulesWithErrors = getModulesWithSessionErrors().sort((a, b) => b.timestamp - a.timestamp);
            if (modulesWithErrors.length === 0) {
                return;
            }

            openErrorsLogModal(btn, null);
        });

        // Delete single error
        document.addEventListener('click', (e) => {
            const deleteBtn = e.target.closest('.tm-btn-delete-error');
            if (!deleteBtn) {
                return;
            }

            const card = deleteBtn.closest('.tm-error-log-card');
            if (!card) {
                return;
            }

            const moduleId = String(card.dataset.moduleId || '').trim();
            const errorIndex = Number(card.dataset.errorIndex || 0);

            if (!moduleId) {
                return;
            }

            deleteErrorFromSession(moduleId, errorIndex);
        });

        // Clear all errors
        document.getElementById('tmBtnClearAllErrors')?.addEventListener('click', () => {
            clearAllErrorsFromSession();
        });
        // ------------------------------------

        // --- Refresh module status (entry count) ---
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-refresh-module]');
            if (!btn) return;

            const moduleId = String(btn.getAttribute('data-refresh-module') || '').trim();
            const url = String(btn.getAttribute('data-refresh-url') || '').trim();
            if (!moduleId || !url) return;

            // Spin the icon
            btn.classList.add('is-loading');

            fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (res) { return safeJsonParse(res); })
                .then(function (data) {
                    const count = typeof data.my_entries_count === 'number' ? data.my_entries_count : null;
                    if (count === null) return;

                    // Find the card that contains this refresh button
                    const card = btn.closest('article');
                    if (!card) return;

                    // Update the "Mis registros" pill
                    const pills = card.querySelectorAll('.tm-upload-meta-pill');
                    pills.forEach(function (pill) {
                        const strong = pill.querySelector('strong');
                        if (strong && strong.textContent.trim() === 'Mis registros:') {
                            pill.innerHTML = '<strong>Mis registros:</strong> ' + count;
                        }
                    });

                    // Re-evaluate the session-errors indicator for this card
                    updateErrorIndicator(moduleId);
                    updateHeaderErrorIndicator();
                })
                .catch(function () {
                    // silently ignore errors
                })
                .finally(function () {
                    btn.classList.remove('is-loading');
                });
        });
        // ------------------------------------

        // --- Linked field: show/hide secondary sub-field based on primary value ---
        const handleLinkedPrimaryChange = function (primaryEl) {
            const group = primaryEl.closest('[data-linked-field-group]');
            if (!group) return;
            const secondaryWrap = group.querySelector('[data-linked-secondary-wrap]');
            const secondaryEl = group.querySelector('[data-linked-secondary]');
            if (!secondaryWrap || !secondaryEl) return;
            const hasValue = (primaryEl.tagName === 'SELECT' ? primaryEl.value : (primaryEl.value || '').trim()) !== '';
            secondaryWrap.hidden = !hasValue;
            secondaryEl.disabled = !hasValue;
            secondaryEl.required = hasValue;
        };
        document.addEventListener('input', function (e) {
            const primaryEl = e.target.closest('[data-linked-primary]');
            if (primaryEl) handleLinkedPrimaryChange(primaryEl);
        });
        document.addEventListener('change', function (e) {
            const primaryEl = e.target.closest('[data-linked-primary]');
            if (primaryEl) handleLinkedPrimaryChange(primaryEl);
        });
        // ------------------------------------

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
        const microrregionesMunicipios = DELEGATE_BOOT.microrregionesMunicipios;
        const microrregionesMeta = DELEGATE_BOOT.microrregionesMeta;
        const tmSemaforoLabels = DELEGATE_BOOT.semaforoLabels;
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
        const fileModal = document.getElementById('tmFilePreviewModal');
        const fileModalFrame = document.getElementById('tmFilePreviewFrame');
        const fileModalTitle = document.getElementById('tmFilePreviewTitle');
        const imageInputSelector = 'input[type="file"][accept="image/*"]';
        const mediaInputSelector = 'input[type="file"][data-max-files]';
        const recordsViewPanel = document.getElementById('tmRecordsView');
        const uploadViewPanel = document.getElementById('tmUploadView');
        const recordsUrl = recordsViewPanel ? String(recordsViewPanel.getAttribute('data-records-url') || '') : '';
        const fragmentRecordsBase = recordsViewPanel
            ? String(recordsViewPanel.getAttribute('data-fragment-records-url') || '')
            : '';
        const fragmentUploadBase = uploadViewPanel
            ? String(uploadViewPanel.getAttribute('data-upload-url') || '')
            : '';

        const tmModuleSearchInput = document.getElementById('tmModuleSearchInput');
        const tmClearSearch = document.getElementById('tmClearSearch');
        const tmSortPills = Array.from(document.querySelectorAll('[data-sort]'));
        const tmExpiryPills = Array.from(document.querySelectorAll('[data-filter-expiry]'));
        const tmDateLimit = document.getElementById('tmDateLimit');
        const tmDateFilterGroup = document.getElementById('tmDateFilterGroup');
        const tmModuleGrid = document.getElementById('tmFragmentUpload');

        const applyModuleFilters = function () {
            if (!tmModuleGrid) return;
            const searchTerm = (tmModuleSearchInput?.value || '').toLowerCase().trim();
            const expiryFilter = tmExpiryPills.find(p => p.classList.contains('is-active'))?.getAttribute('data-filter-expiry') || 'all';
            const dateLimit = tmDateLimit?.value || '';
            const sortMode = tmSortPills.find(p => p.classList.contains('is-active'))?.getAttribute('data-sort') || 'az';

            const cards = Array.from(tmModuleGrid.querySelectorAll('[data-module-card]'));

            cards.forEach(card => {
                const name = card.getAttribute('data-name') || '';
                const desc = card.getAttribute('data-desc') || '';
                const expiry = card.getAttribute('data-expiry') || '';

                let mSearch = !searchTerm || name.includes(searchTerm) || desc.includes(searchTerm);
                let mExpiry = true;

                if (expiryFilter === 'none') {
                    mExpiry = expiry === 'none';
                } else if (expiryFilter === 'has') {
                    mExpiry = expiry !== 'none';
                    if (mExpiry && dateLimit) {
                        mExpiry = expiry <= dateLimit;
                    }
                }

                card.style.display = (mSearch && mExpiry) ? '' : 'none';
            });

            cards.sort((a, b) => {
                const nameA = a.getAttribute('data-name') || '';
                const nameB = b.getAttribute('data-name') || '';
                const res = nameA.localeCompare(nameB);
                return sortMode === 'az' ? res : -res;
            });

            cards.forEach(c => tmModuleGrid.appendChild(c));
            if (tmClearSearch) tmClearSearch.classList.toggle('tm-hidden', !searchTerm);
        };

        if (tmModuleSearchInput) {
            tmModuleSearchInput.addEventListener('input', applyModuleFilters);
            tmClearSearch?.addEventListener('click', () => {
                tmModuleSearchInput.value = '';
                applyModuleFilters();
            });
            tmSortPills.forEach(p => p.addEventListener('click', () => {
                tmSortPills.forEach(x => x.classList.remove('is-active'));
                p.classList.add('is-active');
                applyModuleFilters();
            }));
            tmExpiryPills.forEach(p => p.addEventListener('click', () => {
                tmExpiryPills.forEach(x => x.classList.remove('is-active'));
                p.classList.add('is-active');
                if (tmDateFilterGroup) {
                    tmDateFilterGroup.classList.toggle('tm-hidden', p.getAttribute('data-filter-expiry') !== 'has');
                }
                applyModuleFilters();
            }));
            tmDateLimit?.addEventListener('change', applyModuleFilters);
        }

        let lastFocusedImageInput = null;
        const modalOpeners = new Map();
        const notify = function (title, message, type) {
            if ((type === 'success' || type === 'status') && typeof window.segobToast === 'function') {
                window.segobToast('success', message || title);
                return;
            }
            if (typeof Swal !== 'undefined') {
                Swal.fire(title, message, type);
            } else {
                alert(title + (message ? '\n' + message : ''));
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
            modal.dispatchEvent(new CustomEvent('modal:open', { detail: { modal, opener } }));
        };
        window.openModal = openModal;

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

        const setPreview = function (input) {
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            const wrap = input.closest('[data-paste-upload-wrap]');
            if (!wrap) {
                return;
            }

            const container = wrap.querySelector('[data-inline-image-preview-container]');
            if (!container) {
                return;
            }
            const placeholder = wrap.querySelector('.tm-upload-evidence-placeholder');

            Array.from(container.querySelectorAll('[data-new-preview="1"]')).forEach(function (node) {
                node.remove();
            });

            const files = Array.from(input.files || []);
            const existingCount = container.querySelectorAll('[data-existing-preview="1"]').length;

            let renderedCount = 0;

            files.forEach(function (file, index) {
                const uploadKind = String(input.dataset.uploadKind || 'image').toLowerCase();
                renderedCount += 1;

                const previewDiv = document.createElement('div');
                previewDiv.className = 'tm-inline-image-preview tm-image-preview';
                previewDiv.setAttribute('data-new-preview', '1');
                previewDiv.style.position = 'relative';

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'tm-image-clear';
                removeBtn.innerHTML = '&times;';
                removeBtn.style.position = 'absolute';
                removeBtn.style.top = '-8px';
                removeBtn.style.right = '-8px';

                removeBtn.addEventListener('click', function (event) {
                    event.stopPropagation();
                    if (typeof DataTransfer === 'undefined') {
                        input.value = '';
                        setPreview(input);
                        return;
                    }
                    const dt = new DataTransfer();
                    Array.from(input.files || []).forEach(function (f, i) {
                        if (i !== index) {
                            dt.items.add(f);
                        }
                    });
                    input.files = dt.files;
                    setPreview(input);
                });

                if (uploadKind === 'document') {
                    const docPreview = document.createElement('button');
                    docPreview.type = 'button';
                    docPreview.className = 'tm-thumb-link';
                    docPreview.style.display = 'inline-flex';
                    docPreview.style.alignItems = 'center';
                    docPreview.style.gap = '6px';
                    docPreview.style.maxWidth = '100%';
                    docPreview.style.width = '100%';
                    docPreview.style.justifyContent = 'flex-start';
                    previewDiv.style.width = 'min(100%, 360px)';
                    previewDiv.style.maxWidth = '100%';
                    const docUrl = (typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function')
                        ? URL.createObjectURL(file)
                        : '';
                    const displayName = String(file.name || ('Documento ' + (index + 1)));
                    if (docUrl) {
                        docPreview.setAttribute('data-open-file-preview', '1');
                        docPreview.setAttribute('data-file-src', docUrl);
                        docPreview.setAttribute('data-file-title', displayName);
                    }
                    docPreview.innerHTML = '<i class="fa-solid fa-file-lines" aria-hidden="true"></i><span>'
                        + displayName + '</span>';
                    const nameSpan = docPreview.querySelector('span');
                    if (nameSpan) {
                        nameSpan.style.whiteSpace = 'normal';
                        nameSpan.style.wordBreak = 'break-word';
                        nameSpan.style.lineHeight = '1.25';
                        nameSpan.title = displayName;
                    }
                    previewDiv.appendChild(docPreview);
                    previewDiv.appendChild(removeBtn);
                    container.appendChild(previewDiv);
                } else {
                    const img = document.createElement('img');
                    img.style.maxWidth = '120px';
                    img.style.maxHeight = '120px';
                    img.style.borderRadius = '8px';
                    img.style.objectFit = 'cover';
                    const reader = new FileReader();
                    reader.onload = function (event) {
                        img.src = String(event.target && event.target.result ? event.target.result : '');
                        previewDiv.appendChild(img);
                        previewDiv.appendChild(removeBtn);
                        container.appendChild(previewDiv);
                    };
                    reader.readAsDataURL(file);
                }
            });

            if (placeholder) {
                placeholder.hidden = (renderedCount + existingCount) > 0;
            }
        };

        const getActiveExistingImageCount = function (input) {
            if (!(input instanceof HTMLInputElement)) {
                return 0;
            }

            const wrap = input.closest('[data-paste-upload-wrap]');
            if (!(wrap instanceof HTMLElement)) {
                return 0;
            }

            const container = wrap.querySelector('[data-inline-image-preview-container]');
            if (!(container instanceof HTMLElement)) {
                return 0;
            }

            return container.querySelectorAll('[data-existing-preview="1"]').length;
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
            const inlineContainer = wrapper.querySelector('[data-inline-image-preview-container]');

            if (inlineContainer) {
                input.addEventListener('change', function () {
                    setPreview(input);
                    if (removeFlag) {
                        removeFlag.value = '0';
                    }
                });
                return;
            }

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

        Array.from(document.querySelectorAll(mediaInputSelector)).forEach(function (input) {
            initializeImagePreview(input);
        });

        const bindImageUploadInteractions = function (root) {
            const scope = root instanceof HTMLElement ? root : document;

            Array.from(scope.querySelectorAll('[data-paste-image-button]')).forEach(function (button) {
                if (button.dataset.tmBoundPaste === '1') return;
                button.dataset.tmBoundPaste = '1';
                button.addEventListener('click', function (event) {
                    event.stopPropagation();
                    const targetId = button.getAttribute('data-target-input') || '';
                    const input = targetId ? document.getElementById(targetId) : null;
                    if (!(input instanceof HTMLInputElement)) {
                        return;
                    }
                    pasteImageFromClipboardApi(input);
                });
            });

            Array.from(scope.querySelectorAll('[data-paste-upload-wrap]')).forEach(function (area) {
                if (area.dataset.tmBoundDrop === '1') return;
                area.dataset.tmBoundDrop = '1';

                const input = area.querySelector(mediaInputSelector);
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

                    // If the dropzone is inside a <label> that already contains the input,
                    // the browser opens the picker natively. Calling input.click() again causes
                    // a second open after cancel in some browsers.
                    const ownerLabel = area.closest('label');
                    const labelHandlesClick = ownerLabel instanceof HTMLElement && ownerLabel.contains(input);
                    if (!labelHandlesClick) {
                        input.click();
                    }
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

                    const selectedFile = getAllowedFileFromFileList(event.dataTransfer ? event.dataTransfer.files : [], input);
                    if (!selectedFile) {
                        const kind = String(input.dataset.uploadKind || 'image').toLowerCase();
                        notify('Aviso', kind === 'document' ? 'Solo se permiten archivos PDF o DOCX.' : 'Solo se permiten imagenes al arrastrar.', 'warning');
                        return;
                    }

                    const wasAssigned = setSelectedFileOnInput(input, selectedFile);
                    if (!wasAssigned) {
                        notify('Aviso', 'No se pudo adjuntar el archivo arrastrado.', 'warning');
                    }
                });
            });

            Array.from(scope.querySelectorAll('[data-upload-trigger]')).forEach(function (button) {
                if (button.dataset.tmBoundUpload === '1') return;
                button.dataset.tmBoundUpload = '1';
                button.addEventListener('click', function (event) {
                    event.stopPropagation();
                    const targetId = button.getAttribute('data-target-input') || '';
                    const input = targetId ? document.getElementById(targetId) : null;
                    if (input instanceof HTMLInputElement) {
                        input.click();
                    }
                });
            });

            Array.from(scope.querySelectorAll('[data-remove-existing-image]')).forEach(function (button) {
                if (button.dataset.tmBoundRemoveExistingSingle === '1') return;
                button.dataset.tmBoundRemoveExistingSingle = '1';
                button.addEventListener('click', function (event) {
                    event.stopPropagation();
                    const targetId = button.getAttribute('data-target-input') || '';
                    const input = targetId ? document.getElementById(targetId) : null;
                    if (!(input instanceof HTMLInputElement)) {
                        return;
                    }

                    const existingPath = String(button.getAttribute('data-existing-path') || '').trim();
                    const removeName = String(button.getAttribute('data-remove-existing-name') || '').trim();
                    const evidence = input.closest('.tm-upload-evidence');
                    const removeContainer = evidence ? evidence.querySelector('[data-remove-existing-container]') : null;

                    if (existingPath && removeName && removeContainer instanceof HTMLElement) {
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = removeName;
                        hidden.value = existingPath;
                        removeContainer.appendChild(hidden);
                    }

                    const previewItem = button.closest('[data-existing-preview="1"]');
                    if (previewItem instanceof HTMLElement) {
                        previewItem.remove();
                    }

                    const wrap = input.closest('[data-paste-upload-wrap]');
                    if (wrap instanceof HTMLElement) {
                        const container = wrap.querySelector('[data-inline-image-preview-container]');
                        const placeholder = wrap.querySelector('.tm-upload-evidence-placeholder');
                        const existingCount = container ? container.querySelectorAll('[data-existing-preview="1"]').length : 0;
                        const newCount = Array.from(input.files || []).length;
                        if (placeholder) {
                            placeholder.hidden = (existingCount + newCount) > 0;
                        }
                    }

                    const removeFlag = evidence ? evidence.querySelector('[data-remove-flag]') : null;
                    if (removeFlag instanceof HTMLInputElement) {
                        removeFlag.value = (getActiveExistingImageCount(input) + Array.from(input.files || []).length) === 0 ? '1' : '0';
                    }
                });
            });

            Array.from(scope.querySelectorAll('[data-remove-existing-images]')).forEach(function (button) {
                if (button.dataset.tmBoundRemoveExisting === '1') return;
                button.dataset.tmBoundRemoveExisting = '1';
                button.addEventListener('click', function () {
                    const targetId = button.getAttribute('data-target-input') || '';
                    const input = targetId ? document.getElementById(targetId) : null;
                    if (!(input instanceof HTMLInputElement)) {
                        return;
                    }

                    input.value = '';
                    const wrap = input.closest('[data-paste-upload-wrap]');
                    const evidence = input.closest('.tm-upload-evidence');
                    const removeFlag = evidence ? evidence.querySelector('[data-remove-flag]') : null;
                    if (removeFlag instanceof HTMLInputElement) {
                        removeFlag.value = '1';
                    }

                    if (wrap instanceof HTMLElement) {
                        const container = wrap.querySelector('[data-inline-image-preview-container]');
                        if (container) {
                            const removeContainer = evidence ? evidence.querySelector('[data-remove-existing-container]') : null;
                            Array.from(container.querySelectorAll('[data-existing-preview="1"]')).forEach(function (existingPreview) {
                                const existingButton = existingPreview.querySelector('[data-remove-existing-image]');
                                const existingPath = String(existingButton ? (existingButton.getAttribute('data-existing-path') || '') : '').trim();
                                const removeName = String(existingButton ? (existingButton.getAttribute('data-remove-existing-name') || '') : '').trim();
                                if (existingPath && removeName && removeContainer instanceof HTMLElement) {
                                    const hidden = document.createElement('input');
                                    hidden.type = 'hidden';
                                    hidden.name = removeName;
                                    hidden.value = existingPath;
                                    removeContainer.appendChild(hidden);
                                }
                            });
                            container.innerHTML = '';
                        }
                        const placeholder = wrap.querySelector('.tm-upload-evidence-placeholder');
                        if (placeholder) {
                            placeholder.hidden = false;
                        }
                    }
                });
            });
        };

        bindImageUploadInteractions(document);
        bindHorizontalScrollControls(document);

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

        const getAllowedFileFromFileList = function (files, input) {
            const kind = String(input?.dataset?.uploadKind || 'image').toLowerCase();
            const list = Array.from(files || []);
            for (let index = 0; index < list.length; index += 1) {
                const file = list[index];
                if (!file) {
                    continue;
                }
                const fileType = String(file.type || '').toLowerCase();
                const fileName = String(file.name || '').toLowerCase();
                if (kind === 'document') {
                    if (fileType === 'application/pdf' || fileType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || fileName.endsWith('.pdf') || fileName.endsWith('.docx')) {
                        return file;
                    }
                } else if (fileType.indexOf('image/') === 0) {
                    return file;
                }
            }

            return null;
        };

        const setSelectedFileOnInput = function (input, file) {
            if (!(input instanceof HTMLInputElement) || !file || typeof DataTransfer === 'undefined') {
                return false;
            }

            const maxFiles = parseInt(input.dataset.maxFiles || (input.multiple ? '2' : '1'), 10) || 1;
            const existingFiles = Array.from(input.files || []);
            const existingServerImages = getActiveExistingImageCount(input);
            if ((existingFiles.length + existingServerImages) >= maxFiles) {
                notify('Aviso', 'Solo puedes adjuntar hasta ' + maxFiles + ' archivo(s).', 'warning');
                return false;
            }

            const transfer = new DataTransfer();
            existingFiles.forEach(function (existingFile) {
                transfer.items.add(existingFile);
            });
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

            // Si se cierra el modal de errores, actualizar los registros
            if (modal.id === 'tmImportErrorsModal') {
                const filterModuleId = modal.getAttribute('data-filter-module');
                if (filterModuleId) {
                    // Recargar la página para actualizar los registros
                    setTimeout(() => {
                        location.reload();
                    }, 300);
                }
            }

            // Al cerrar el modal de Excel, notificar y refrescar si hubo importaciones
            if (modal.classList.contains('tm-excel-import-modal') && modal.__excelImportedCount > 0) {
                var excelModuleId = String(modal.id || '').replace(/^tmImportarExcelModal-/, '');
                var totalImported = modal.__excelImportedCount;
                modal.__excelImportedCount = 0;

                // Actualizar contador "Mis registros"
                var refreshBtn = document.querySelector('[data-refresh-module="' + excelModuleId + '"]');
                if (refreshBtn) refreshBtn.click();

                // Actualizar panel de historial
                var recordsPanel = document.getElementById('module-records-' + excelModuleId);
                if (recordsPanel && typeof window.__tmReloadRecordsPanel === 'function') {
                    window.__tmReloadRecordsPanel(recordsPanel, { requireActive: false });
                }

                // Mostrar aviso emergente
                setTimeout(function () {
                    if (typeof window.segobToast === 'function') {
                        window.segobToast('success', totalImported + ' registro(s) agregado(s) desde Excel.');
                    }
                }, 200);
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
        window.closeModal = closeModal;

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

                csrfFetch(form.action, {
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

                    if (typeof window.segobToast === 'function') {
                        window.segobToast('success', result.data.message || 'Registro guardado correctamente.');
                    } else {
                        notify('Exito', result.data.message || 'Registro guardado correctamente.', 'success');
                    }

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

                    Array.from(form.querySelectorAll('[data-inline-image-preview-container]')).forEach(function (container) {
                        container.innerHTML = '';
                    });

                    Array.from(form.querySelectorAll('[data-remove-flag]')).forEach(function (flag) {
                        flag.value = '0';
                    });

                    const ownerModal = form.closest('.tm-modal');
                    const ownerModalId = ownerModal ? String(ownerModal.id || '') : '';
                    const moduleId = ownerModalId.startsWith('delegate-preview-')
                        ? ownerModalId.replace('delegate-preview-', '')
                        : '';

                    if (/^\d+$/.test(moduleId)) {
                        // Actualiza contador "Mis registros" sin cerrar modal.
                        const refreshBtn = document.querySelector('[data-refresh-module="' + moduleId + '"]');
                        if (refreshBtn) {
                            refreshBtn.click();
                        }

                        // Actualiza historial de "Ver mis registros" en segundo plano.
                        const recordsPanel = document.getElementById('module-records-' + moduleId);
                        if (recordsPanel && typeof window.__tmReloadRecordsPanel === 'function') {
                            window.__tmReloadRecordsPanel(recordsPanel, { requireActive: false });
                        }
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

        function bindHorizontalScrollControls(root) {
            const scope = root instanceof HTMLElement ? root : document;
            const containers = Array.from(scope.querySelectorAll('[data-h-scroll-container]'));

            containers.forEach(function (container) {
                const target = container.querySelector('[data-h-scroll-target]');
                const prevBtn = container.querySelector('[data-h-scroll-prev]');
                const nextBtn = container.querySelector('[data-h-scroll-next]');

                if (!(target instanceof HTMLElement) || !(prevBtn instanceof HTMLButtonElement) || !(nextBtn instanceof HTMLButtonElement)) {
                    return;
                }

                const updateState = function () {
                    const maxScroll = Math.max(0, target.scrollWidth - target.clientWidth);
                    const hasOverflow = maxScroll > 6;

                    prevBtn.hidden = !hasOverflow;
                    nextBtn.hidden = !hasOverflow;

                    if (!hasOverflow) {
                        prevBtn.disabled = true;
                        nextBtn.disabled = true;
                        return;
                    }

                    prevBtn.disabled = target.scrollLeft <= 2;
                    nextBtn.disabled = target.scrollLeft >= (maxScroll - 2);
                };

                if (container.dataset.tmHScrollBound !== '1') {
                    container.dataset.tmHScrollBound = '1';

                    prevBtn.addEventListener('click', function () {
                        const step = Math.max(280, Math.round(target.clientWidth * 0.65));
                        target.scrollBy({ left: -step, behavior: 'smooth' });
                    });

                    nextBtn.addEventListener('click', function () {
                        const step = Math.max(280, Math.round(target.clientWidth * 0.65));
                        target.scrollBy({ left: step, behavior: 'smooth' });
                    });

                    target.addEventListener('scroll', updateState, { passive: true });
                    window.addEventListener('resize', updateState);
                }

                updateState();
            });
        }

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
                if (res.redirected || (res.url && res.url.includes('/login'))) {
                    window.location.reload();
                    return null;
                }
                if (!res.ok) {
                    throw new Error('Error ' + res.status);
                }
                return res.text();
            }).then(function (html) {
                if (!html) return;
                host.innerHTML = html;
                const panel = host.closest('.tm-module-records-panel');
                const tb = panel ? panel.querySelector('[data-tm-bulk-toggle]') : null;
                if (tb && tb.classList.contains('is-active')) {
                    panel.querySelectorAll('.tm-record-bulk-check, .tm-bulk-col').forEach(el => el.classList.remove('tm-hidden'));
                }
                if (typeof updateBulkUI === 'function') updateBulkUI(panel);
                Array.from(host.querySelectorAll(mediaInputSelector)).forEach(function (inp) {
                    initializeImagePreview(inp);
                });
                bindImageUploadInteractions(host);
                bindHorizontalScrollControls(host);
            }).catch(function (err) {
                console.error('Fragment load error:', err);
                host.innerHTML = '<p class="inline-alert inline-alert-error">No se pudo cargar el listado. <a href="' + (recordsUrl ? recordsUrl + '?module=' + moduleId : '#') + '">Recargar página</a></p>';
            });
        };

        const loadUploadFragment = function (host, page) {
            if (!host || !fragmentUploadBase) {
                return;
            }
            host.style.opacity = '0.5';
            const sep = fragmentUploadBase.indexOf('?') >= 0 ? '&' : '?';
            fetch(fragmentUploadBase + sep + 'page=' + page, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
            }).then(function (res) {
                if (!res.ok) throw new Error('Error ' + res.status);
                return res.text();
            }).then(function (html) {
                host.innerHTML = html;
                host.style.opacity = '1';
                // Reseteamos el scroll de la página o del host si es necesario
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }).catch(function (err) {
                console.error(err);
                host.style.opacity = '1';
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
        window.__tmReloadRecordsPanel = reloadRecordsPanelFromFilters;

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

            /* Modal “Editar registro”: al cambiar microrregión, actualizar lista de municipios (fragmento AJAX). */
            recordsViewPanel.addEventListener('change', function (event) {
                const mrSel = event.target.closest('.tm-mr-selector');
                if (!mrSel || !recordsViewPanel.contains(mrSel)) {
                    return;
                }
                const form = mrSel.closest('form.tm-entry-form');
                if (!form) {
                    return;
                }
                setMunicipiosForForm(form, mrSel.value);
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

            // --- Lógica de Selección y Borrado Masivo Persistente ---
            const tmBulkSelections = new Map(); // moduleId -> Set of IDs
            const getBulkSet = (panel) => {
                const host = panel.querySelector('.tm-records-fragment-host');
                const moduleId = host ? host.getAttribute('data-module-id') : null;
                if (!moduleId) return null;
                if (!tmBulkSelections.has(moduleId)) tmBulkSelections.set(moduleId, new Set());
                return tmBulkSelections.get(moduleId);
            };

            window.updateBulkUI = function(panel) {
                if (!panel) return;
                const set = getBulkSet(panel);
                if (!set) return;
                const countEls = panel.querySelectorAll('[data-tm-bulk-count]');
                const deleteBtns = panel.querySelectorAll('[data-tm-bulk-delete-trigger]');
                countEls.forEach(el => el.textContent = set.size);
                deleteBtns.forEach(btn => btn.classList.toggle('tm-hidden', set.size === 0));

                // Sincronizar checkboxes actualmente visibles
                panel.querySelectorAll('[data-tm-bulk-checkbox]').forEach(cb => {
                    cb.checked = set.has(cb.value);
                });

                // Actualizar estado de "Seleccionar todo" (si todos los de la página están en el set)
                panel.querySelectorAll('[data-tm-bulk-select-all]').forEach(selectAll => {
                    const checkboxes = Array.from(panel.querySelectorAll('[data-tm-bulk-checkbox]'));
                    if (checkboxes.length > 0) {
                        selectAll.checked = checkboxes.every(cb => set.has(cb.value));
                    } else {
                        selectAll.checked = false;
                    }
                });
            };

            recordsViewPanel.addEventListener('click', function(e) {
                const toggleBtn = e.target.closest('[data-tm-bulk-toggle]');
                if (toggleBtn) {
                    const panel = toggleBtn.closest('.tm-module-records-panel');
                    if (!panel) return;
                    const isActive = toggleBtn.classList.toggle('is-active');
                    // El usuario pidió que SOLO diga "Seleccionar"
                    const els = panel.querySelectorAll('.tm-record-bulk-check, .tm-bulk-col');
                    els.forEach(el => el.classList.toggle('tm-hidden', !isActive));
                    if (!isActive) {
                        const set = getBulkSet(panel);
                        if (set) set.clear();
                    }
                    updateBulkUI(panel);
                    return;
                }

                const deleteBtn = e.target.closest('[data-tm-bulk-delete-trigger]');
                if (deleteBtn) {
                    const panel = deleteBtn.closest('.tm-module-records-panel');
                    const host = panel.querySelector('.tm-records-fragment-host');
                    const moduleId = host ? host.getAttribute('data-module-id') : null;
                    const set = getBulkSet(panel);
                    const selected = set ? Array.from(set) : [];
                    if (!selected.length || !moduleId) return;

                    Swal.fire({
                        title: '¿Eliminar ' + selected.length + ' registros?',
                        text: 'Esta acción no se puede deshacer y eliminará las evidencias asociadas de todos los elementos seleccionados (incluyendo otras páginas).',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, eliminar todos',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const originalHtml = deleteBtn.innerHTML;
                            deleteBtn.disabled = true;
                            deleteBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
                            let delUrl = "{{ route('temporary-modules.entries.bulk-destroy', ['module' => ':moduleId']) }}".replace(':moduleId', moduleId);
                            csrfFetch(delUrl, {
                                method: 'DELETE',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({ entry_ids: selected })
                            })
                            .then(res => safeJsonParse(res))
                            .then(data => {
                                if (data.success) {
                                    if (typeof window.segobToast === 'function') {
                                        window.segobToast('success', data.message);
                                    }
                                    if (set) set.clear();
                                    reloadRecordsPanelFromFilters(panel, { requireActive: false });
                                } else {
                                    Swal.fire('Error', data.message || 'Error al eliminar.', 'error');
                                }
                            })
                            .catch(() => {
                                Swal.fire('Error', 'Error de conexión o permisos.', 'error');
                            })
                            .finally(() => {
                                deleteBtn.disabled = false;
                                deleteBtn.innerHTML = originalHtml;
                            });
                        }
                    });
                }
            });

            recordsViewPanel.addEventListener('change', function(e) {
                const panel = e.target.closest('.tm-module-records-panel');
                if (!panel) return;
                const set = getBulkSet(panel);
                if (!set) return;

                if (e.target.matches('[data-tm-bulk-checkbox]')) {
                    if (e.target.checked) set.add(e.target.value);
                    else set.delete(e.target.value);
                    updateBulkUI(panel);
                }

                if (e.target.matches('[data-tm-bulk-select-all]')) {
                    const isChecked = e.target.checked;
                    panel.querySelectorAll('[data-tm-bulk-checkbox]').forEach(cb => {
                        cb.checked = isChecked;
                        if (isChecked) set.add(cb.value);
                        else set.delete(cb.value);
                    });
                    updateBulkUI(panel);
                }
            });
        }

        if (uploadViewPanel && fragmentUploadBase) {
            uploadViewPanel.addEventListener('click', function (event) {
                const anchor = event.target.closest('a.tm-paginator-btn[href]');
                if (!anchor || !anchor.getAttribute('href')) {
                    return;
                }
                event.preventDefault();
                const url = new URL(anchor.href, window.location.origin);
                const pageNum = url.searchParams.get('page') || '1';
                const grid = document.getElementById('tmFragmentUpload');
                if (grid) {
                    loadUploadFragment(grid, pageNum);
                }
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
            const scrollToActive = function () {
                const active = track.querySelector('.tm-module-chip.is-active');
                if (active) {
                    const trackWidth = track.clientWidth;
                    const activeLeft = active.offsetLeft;
                    const activeWidth = active.offsetWidth;
                    const center = activeLeft - (trackWidth / 2) + (activeWidth / 2);
                    track.scrollTo({ left: center, behavior: 'smooth' });
                }
            };

            syncTmModuleChipsNav = syncNav;

            // Scroll to active on start
            setTimeout(scrollToActive, 200);

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
                        requestAnimationFrame(scrollToActive);
                    });
                }).observe(recordsSection, { attributes: true, attributeFilter: ['class'] });
            }
            syncNav();
            scrollToActive();
        })();

        document.addEventListener('click', function (event) {
            const imgBtn = event.target.closest('[data-open-image-preview]');
            if (imgBtn) {
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
            const fileBtn = event.target.closest('[data-open-file-preview]');
            if (fileBtn) {
                if (!fileModal || !fileModalFrame) {
                    return;
                }
                const src = fileBtn.getAttribute('data-file-src') || '';
                const title = fileBtn.getAttribute('data-file-title') || 'Vista previa de documento';
                if (src === '') {
                    return;
                }
                event.preventDefault();
                fileModalFrame.src = src;
                if (fileModalTitle) {
                    fileModalTitle.textContent = title;
                }
                openModal(fileModal, fileBtn);
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

        /* Cerrar modales por backdrop/botón X: delegación global (los modales de “Editar” se inyectan por AJAX
           y no existían en el DOM en el forEach inicial). */
        document.addEventListener('click', function (event) {
            const closeEl = event.target.closest('[data-close-module-preview], [data-close-image-preview], [data-close-file-preview]');
            if (!closeEl) {
                return;
            }
            const modal = closeEl.closest('.tm-modal');
            if (!modal) {
                return;
            }
            event.preventDefault();
            closeModal(modal);
            if (modal.id === 'tmImagePreviewModal' && imageModalImg) {
                imageModalImg.removeAttribute('src');
            }
            if (modal.id === 'tmFilePreviewModal' && fileModalFrame) {
                fileModalFrame.src = 'about:blank';
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            Array.from(document.querySelectorAll('.tm-modal.is-open')).forEach(function (modal) {
                closeModal(modal);
            });
        });

        /* Importar Excel (eventos temporales) - Premium Logic */

        if (typeof XLSX === 'undefined') {
            const script = document.createElement('script');
            script.src = "https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js";
            document.head.appendChild(script);
        }

        const getColLetter = (n) => {
            let s = "";
            while (n >= 0) {
                s = String.fromCharCode((n % 26) + 65) + s;
                n = Math.floor(n / 26) - 1;
            }
            return s;
        };

        const excelModals = Array.from(document.querySelectorAll('.tm-excel-import-modal'));
        excelModals.forEach(function (modal) {
            const currentModuleId = String(modal.id || '').replace(/^tmImportarExcelModal-/, '');
            const previewUrl = String(modal.getAttribute('data-excel-preview-url') || '');
            const importUrl = String(modal.getAttribute('data-excel-import-url') || '');
            const updateUrl = String(modal.getAttribute('data-excel-update-url') || '');
            const importSingleUrl = String(modal.getAttribute('data-excel-import-single-url') || '');

            const step1 = modal.querySelector('.tm-excel-step1-inner');
            const step2 = modal.querySelector('.tm-excel-step2-inner');
            const fileInput = modal.querySelector('.tm-excel-file-input');
            const headerRowInput = modal.querySelector('.tm-excel-header-row');
            const dataStartRowInput = modal.querySelector('.tm-excel-data-start-row');
            const mrInput = modal.querySelector('.tm-excel-mr-input');
            const municipioInput = modal.querySelector('.tm-excel-municipio-input');
            const municipioContainer = modal.querySelector('.tm-excel-municipio-container-el');
            const autoMunicipioWrap = modal.querySelector('.tm-excel-auto-municipio-wrap-el');
            const autoMunicipioChecks = Array.from(modal.querySelectorAll('.tm-excel-auto-municipio-check'));
            const mapBodyImport = modal.querySelector('.tm-excel-map-body-import');
            const mapBodyUpdate = modal.querySelector('.tm-excel-map-body-update');
            const modeTabs = Array.from(modal.querySelectorAll('.tm-excel-mode-tab'));
            const modePanels = Array.from(modal.querySelectorAll('.tm-excel-mode-panel'));
            const previewTableWrap = modal.querySelector('.tm-excel-preview-table-wrap');

            const badgeH = modal.querySelector('.tm-excel-badge-header');
            const badgeD = modal.querySelector('.tm-excel-badge-data');

            const errPreviewEl = modal.querySelector('.tm-excel-preview-err');
            const detectNoteEl = modal.querySelector('.tm-excel-detect-note');
            const errImportEl = modal.querySelector('.tm-excel-import-err');
            const okImportEl = modal.querySelector('.tm-excel-import-ok');

            let workbookData = null;
            let currentWorkbook = null;
            let currentSheetIdx = 0;
            let currentSheetStartRow = 1;
            let excelFields = [];
            let activeExcelMode = 'import';

            const getActiveMapSelector = () => activeExcelMode === 'update'
                ? '.tm-excel-map-select-update'
                : '.tm-excel-map-select-import';

            const getActiveAutoMunicipioCheck = () => {
                const activePanel = modal.querySelector('.tm-excel-mode-panel[data-excel-mode-panel="' + activeExcelMode + '"]');
                return activePanel ? activePanel.querySelector('.tm-excel-auto-municipio-check') : null;
            };

            const setExcelMode = (mode) => {
                activeExcelMode = mode === 'update' ? 'update' : 'import';
                modeTabs.forEach((tab) => {
                    const isActive = tab.getAttribute('data-excel-mode') === activeExcelMode;
                    tab.classList.toggle('is-active', isActive);
                    tab.classList.toggle('tm-btn-primary', isActive);
                    tab.classList.toggle('tm-btn-outline', !isActive);
                });
                modePanels.forEach((panel) => {
                    const isActive = panel.getAttribute('data-excel-mode-panel') === activeExcelMode;
                    panel.classList.toggle('tm-hidden', !isActive);
                });
                updateMunicipioImportModeForModal();
            };

            modeTabs.forEach((tab) => {
                tab.addEventListener('click', function () {
                    setExcelMode(tab.getAttribute('data-excel-mode') || 'import');
                });
            });

            const switchToSheet = function(idx) {
                if (!currentWorkbook) return;
                const names = currentWorkbook.SheetNames;
                if (idx < 0 || idx >= names.length) return;
                currentSheetIdx = idx;
                const worksheet = currentWorkbook.Sheets[names[idx]];
                let startRow = 1;
                if (worksheet && worksheet['!ref']) {
                    try {
                        const range = XLSX.utils.decode_range(String(worksheet['!ref']));
                        if (range && range.s && Number.isFinite(range.s.r)) {
                            startRow = range.s.r + 1;
                        }
                    } catch (_) {}
                }
                currentSheetStartRow = Math.max(1, startRow);
                workbookData = XLSX.utils.sheet_to_json(worksheet, { header: 1, blankrows: true, defval: '' });
                renderExcelPreview(workbookData);
            };

            const renderSheetTabs = function(sheetNames, activeIdx) {
                const wrap = modal.querySelector('.tm-excel-sheet-tabs-wrap-el');
                const container = modal.querySelector('.tm-excel-sheet-tabs-el');
                if (!container || !wrap) return;
                if (!sheetNames || sheetNames.length <= 1) { wrap.style.display = 'none'; container.innerHTML = ''; return; }
                let html = '';
                sheetNames.forEach(function(name, idx) {
                    const cls = 'tm-excel-sheet-tab-btn' + (idx === activeIdx ? ' tm-excel-sheet-tab-btn--active' : '');
                    html += '<button type="button" class="' + cls + '" data-sheet-idx="' + idx + '">' + String(name).replace(/</g, '&lt;') + '</button>';
                });
                container.innerHTML = html;
                wrap.style.display = 'flex';
                updateSheetArrows();
            };

            const renderMunicipioOptionsForModal = function () {
                if (!mrInput || !municipioInput) return;
                const mrId = String(mrInput.value || '');
                const municipios = Array.isArray(microrregionesMunicipios[mrId]) ? microrregionesMunicipios[mrId] : [];
                const prev = municipioInput.value;
                municipioInput.innerHTML = '<option value="">Selecciona un municipio</option>';
                municipios.forEach(function (m) {
                    municipioInput.appendChild(new Option(m, m, false, prev === m));
                });
            };

            const updateMunicipioImportModeForModal = function () {
                const municipioField = Array.isArray(excelFields) ? excelFields.find(function (f) { return f && f.type === 'municipio'; }) : null;
                const fieldKey = municipioField ? String(municipioField.key || '').replace(/"/g, '') : '';
                const municipioMapSelect = fieldKey ? modal.querySelector(getActiveMapSelector() + '[data-field-key="' + fieldKey + '"]') : null;
                const hasMunicipioMapped = !!(municipioMapSelect && municipioMapSelect.value !== '');

                if (autoMunicipioWrap) autoMunicipioWrap.style.display = municipioMapSelect ? '' : 'none';
                const activeAutoCheck = getActiveAutoMunicipioCheck();
                if (activeAutoCheck) {
                    activeAutoCheck.disabled = !hasMunicipioMapped;
                    if (!hasMunicipioMapped) activeAutoCheck.checked = false;
                    if (hasMunicipioMapped && activeAutoCheck.dataset.userTouched !== '1') {
                        activeAutoCheck.checked = true;
                    }
                }
                const useManualMunicipio = !hasMunicipioMapped || !activeAutoCheck || !activeAutoCheck.checked;
                if (municipioContainer) {
                    const allMr = !!(searchAllCheck && searchAllCheck.checked);
                    municipioContainer.style.display = (!allMr && useManualMunicipio) ? 'block' : 'none';
                }
            };

            const updateSheetArrows = function() {
                const tabs = modal.querySelector('.tm-excel-sheet-tabs-el');
                const leftArr = modal.querySelector('.tm-sheet-arrow-left-el');
                const rightArr = modal.querySelector('.tm-sheet-arrow-right-el');
                if (!tabs || !leftArr || !rightArr) return;
                leftArr.disabled = tabs.scrollLeft <= 0;
                rightArr.disabled = tabs.scrollLeft + tabs.clientWidth >= tabs.scrollWidth - 1;
            };

            (function() {
                const tabsContainer = modal.querySelector('.tm-excel-sheet-tabs-el');
                const leftArr = modal.querySelector('.tm-sheet-arrow-left-el');
                const rightArr = modal.querySelector('.tm-sheet-arrow-right-el');

                if (leftArr) leftArr.addEventListener('click', function() {
                    if (tabsContainer) { tabsContainer.scrollBy({ left: -200, behavior: 'smooth' }); setTimeout(updateSheetArrows, 350); }
                });
                if (rightArr) rightArr.addEventListener('click', function() {
                    if (tabsContainer) { tabsContainer.scrollBy({ left: 200, behavior: 'smooth' }); setTimeout(updateSheetArrows, 350); }
                });
                if (tabsContainer) {
                    tabsContainer.addEventListener('scroll', updateSheetArrows);
                    tabsContainer.addEventListener('click', function(e) {
                        const btn = e.target.closest('.tm-excel-sheet-tab-btn');
                        if (!btn) return;
                        const idx = parseInt(btn.getAttribute('data-sheet-idx'));
                        if (isNaN(idx) || idx === currentSheetIdx) return;
                        switchToSheet(idx);
                        renderSheetTabs(currentWorkbook.SheetNames, idx);
                        if (headerRowInput) headerRowInput.value = '';
                        if (dataStartRowInput) dataStartRowInput.value = '';
                        if (step2) step2.classList.add('tm-hidden');
                    });
                }
            })();

            modal.__excelImportedCount = 0;

            modal.__excelReset = function() {
                modal.__excelImportedCount = 0;
                if (errPreviewEl) { errPreviewEl.textContent = ''; errPreviewEl.classList.add('tm-hidden'); }
                if (errImportEl) { errImportEl.textContent = ''; errImportEl.classList.add('tm-hidden'); }
                if (okImportEl) { okImportEl.textContent = ''; okImportEl.classList.add('tm-hidden'); }
                if (step2) step2.classList.add('tm-hidden');
                if (step1) step1.classList.remove('tm-hidden');

                const errSection = modal.querySelector('.tm-excel-errors-section');
                if (errSection) errSection.classList.add('tm-hidden');
                const errList = modal.querySelector('.tm-excel-errors-list');
                if (errList) errList.innerHTML = '';

                if (fileInput) fileInput.value = '';
                const nameEl = modal.querySelector('.tm-excel-file-name');
                if (nameEl) { nameEl.textContent = ''; nameEl.classList.add('tm-hidden'); }

                if (headerRowInput) headerRowInput.value = '';
                if (dataStartRowInput) dataStartRowInput.value = '';
                workbookData = null;
                currentWorkbook = null;
                currentSheetIdx = 0;
                currentSheetStartRow = 1;
                const sheetTabsWrap = modal.querySelector('.tm-excel-sheet-tabs-wrap-el');
                if (sheetTabsWrap) sheetTabsWrap.style.display = 'none';
                const sheetTabsEl = modal.querySelector('.tm-excel-sheet-tabs-el');
                if (sheetTabsEl) sheetTabsEl.innerHTML = '';

                const inner = modal.querySelector('.tm-excel-sheet-inner-el');
                if (inner) inner.innerHTML = '<div style="padding:60px; text-align:center; color:var(--clr-text-light);"><i class="fa-solid fa-file-excel" style="font-size:4rem; margin-bottom:16px; opacity:0.2;"></i><p style="font-weight:600;">Vista previa del documento</p><p style="font-size:0.85rem; opacity:0.7;">Carga un archivo Excel para comenzar a marcar las columnas.</p></div>';

                const zoomBar = modal.querySelector('.tm-excel-zoom-bar-el');
                if (zoomBar) zoomBar.style.display = 'none';

                const badgeH = modal.querySelector('.tm-excel-badge-header');
                const badgeD = modal.querySelector('.tm-excel-badge-data');
                if (badgeH) badgeH.style.display = 'none';
                if (badgeD) badgeD.style.display = 'none';

                const controlsSide = modal.querySelector('.tm-excel-controls-side');
                if (controlsSide) controlsSide.scrollTo({ top: 0, behavior: 'auto' });
            };

            const updateRowHighlights = function() {
                const hIdx = parseInt(headerRowInput.value);
                const dIdx = parseInt(dataStartRowInput.value);
                modal.querySelectorAll('.tm-excel-preview-table tbody tr').forEach(row => {
                    const idx = parseInt(row.getAttribute('data-row-index'));
                    row.classList.toggle('is-header-row', idx === hIdx);
                    row.classList.toggle('is-data-row', idx === dIdx);
                });
                if (badgeH) {
                    if (hIdx) { badgeH.textContent = 'Encabezado: Fila ' + hIdx; badgeH.style.display = 'inline-flex'; }
                    else badgeH.style.display = 'none';
                }
                if (badgeD) {
                    if (dIdx) { badgeD.textContent = 'Datos: Fila ' + dIdx; badgeD.style.display = 'inline-flex'; }
                    else badgeD.style.display = 'none';
                }
            };

            const applyExcelPreviewThumbnails = (thumbs) => {
                if (!thumbs || !thumbs.length) return;
                const table = modal.querySelector('.tm-excel-preview-table');
                if (!table) return;
                thumbs.forEach((t) => {
                    const r = parseInt(t.row, 10);
                    const c = parseInt(t.col, 10);
                    if (!Number.isFinite(r) || !Number.isFinite(c)) return;
                    const tr = table.querySelector('tbody tr[data-row-index="' + r + '"]');
                    if (!tr) return;
                    const td = tr.querySelector('td:nth-child(' + (c + 2) + ')');
                    if (!td || !t.data_url || String(t.data_url).indexOf('data:image/') !== 0) return;
                    const img = document.createElement('img');
                    img.src = t.data_url;
                    img.alt = '';
                    img.loading = 'lazy';
                    img.style.cssText = 'max-width:88px;max-height:88px;vertical-align:middle;border-radius:6px;object-fit:contain;display:block;';
                    td.replaceChildren(img);
                });
            };

            const fetchExcelPreviewThumbnails = (file, useAutoDetect) => {
                if (!file || !previewUrl) return;
                const fd = new FormData();
                fd.append('archivo_excel', file);
                fd.append('header_row', headerRowInput?.value || '1');
                fd.append('sheet_index', currentSheetIdx);
                fd.append('auto_detect', useAutoDetect ? '1' : '0');
                fd.append('_token', csrfToken);
                csrfFetch(previewUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken } })
                    .then((r) => safeJsonParse(r))
                    .then((j) => {
                        if (!j.success) return;
                        if (j.preview_rows && j.preview_rows.length) {
                            workbookData = j.preview_rows;
                            renderExcelPreview(workbookData);
                            if (j.header_row) { headerRowInput.value = j.header_row; }
                            if (j.data_start_row) { dataStartRowInput.value = j.data_start_row; }
                            updateRowHighlights();
                        }
                        if (j.preview_thumbnails) applyExcelPreviewThumbnails(j.preview_thumbnails);
                    })
                    .catch(() => {});
            };

            const renderExcelPreview = function(data) {
                if (!previewTableWrap) return;
                if (!data || data.length === 0) {
                    const inner = modal.querySelector('.tm-excel-sheet-inner-el');
                    if (inner) inner.innerHTML = '<div style="padding:60px; text-align:center; color:var(--clr-text-light);"><i class="fa-solid fa-table" style="font-size:2.5rem; margin-bottom:12px; opacity:0.25;"></i><p>Esta hoja no contiene datos.</p></div>';
                    return;
                }
                let maxCols = 0;
                data.slice(0, 50).forEach(row => { if (row && row.length > maxCols) maxCols = row.length; });

                let html = '<table class="tm-excel-preview-table"><thead><tr><th class="row-num"></th>';
                for (let i = 0; i < maxCols; i++) html += '<th>' + getColLetter(i) + '</th>';
                html += '</tr></thead><tbody>';

                data.slice(0, 1000).forEach((row, rowIndex) => {
                    const displayRowIndex = currentSheetStartRow + rowIndex;
                    html += '<tr data-row-index="' + displayRowIndex + '"><td class="row-num">' + displayRowIndex + '</td>';
                    for (let i = 0; i < maxCols; i++) {
                        const cell = row[i] !== undefined && row[i] !== null ? row[i] : '';
                        html += '<td>' + String(cell).substring(0, 120) + '</td>';
                    }
                    html += '</tr>';
                });
                html += '</tbody></table>';
                const inner = modal.querySelector('.tm-excel-sheet-inner-el');
                if (inner) {
                    inner.innerHTML = html;
                    const zoomBar = modal.querySelector('.tm-excel-zoom-bar-el');
                    if (zoomBar) zoomBar.style.display = 'flex';
                    currentZoom = 1;
                    updateZoomUI();
                }

                let isDraggingRow = null;
                const tbody = modal.querySelector('.tm-excel-preview-table tbody');

                const handleRowInteraction = (row, isDrag = false) => {
                    const idx = parseInt(row.getAttribute('data-row-index'));
                    const currH = parseInt(headerRowInput.value);
                    const currD = parseInt(dataStartRowInput.value);

                    if (!isDrag) {
                        if (currH === idx) {
                            headerRowInput.value = '';
                            if (currD === idx + 1) dataStartRowInput.value = '';
                        } else if (!headerRowInput.value || idx < currH) {
                            headerRowInput.value = idx;
                            dataStartRowInput.value = idx + 1;
                        } else {
                            dataStartRowInput.value = idx;
                        }
                    } else {
                        if (isDraggingRow === 'header') {
                            headerRowInput.value = idx;
                        } else if (isDraggingRow === 'data') {
                            dataStartRowInput.value = idx;
                        }
                    }
                    updateRowHighlights();
                };

                tbody?.querySelectorAll('tr').forEach(row => {
                    row.addEventListener('mousedown', function(e) {
                        const idx = parseInt(this.getAttribute('data-row-index'));
                        const hIdx = parseInt(headerRowInput.value);
                        const dIdx = parseInt(dataStartRowInput.value);

                        if (idx === hIdx) isDraggingRow = 'header';
                        else if (idx === dIdx) isDraggingRow = 'data';
                        else isDraggingRow = null;

                        if (isDraggingRow) {
                            e.preventDefault();
                            this.style.cursor = 'grabbing';
                        }
                    });

                    row.addEventListener('mouseover', function() {
                        if (isDraggingRow) {
                            handleRowInteraction(this, true);
                        }
                    });

                    row.addEventListener('click', function() {
                        if (!isDraggingRow) {
                            handleRowInteraction(this, false);
                        }
                    });

                    // Touch support
                    row.addEventListener('touchstart', function(e) {
                        const idx = parseInt(this.getAttribute('data-row-index'));
                        const hIdx = parseInt(headerRowInput.value);
                        const dIdx = parseInt(dataStartRowInput.value);

                        if (idx === hIdx) isDraggingRow = 'header';
                        else if (idx === dIdx) isDraggingRow = 'data';

                        if (isDraggingRow) {
                            e.preventDefault();
                        }
                    }, { passive: false });
                });

                const stopDragging = () => {
                    isDraggingRow = null;
                    tbody?.querySelectorAll('tr').forEach(r => r.style.cursor = 'pointer');
                };

                window.addEventListener('mouseup', stopDragging);

                const table = modal.querySelector('.tm-excel-preview-table');
                table?.addEventListener('touchmove', function(e) {
                    if (isDraggingRow) {
                        e.preventDefault();
                        const touch = e.touches[0];
                        const target = document.elementFromPoint(touch.clientX, touch.clientY);
                        const row = target?.closest('tr');
                        if (row && row.getAttribute('data-row-index')) {
                            handleRowInteraction(row, true);
                        }
                    }
                }, { passive: false });

                table?.addEventListener('touchend', stopDragging);

                updateRowHighlights();
            };

            let currentZoom = 1;
            const updateZoomUI = () => {
                const inner = modal.querySelector('.tm-excel-sheet-inner-el');
                const val = modal.querySelector('.tm-excel-zoom-val');
                if (inner) inner.style.transform = `scale(${currentZoom})`;
                if (val) val.textContent = Math.round(currentZoom * 100) + '%';
            };

            modal.querySelector('.tm-excel-zoom-in')?.addEventListener('click', () => {
                currentZoom = Math.min(currentZoom + 0.1, 3);
                updateZoomUI();
            });
            modal.querySelector('.tm-excel-zoom-out')?.addEventListener('click', () => {
                currentZoom = Math.max(currentZoom - 0.1, 0.2);
                updateZoomUI();
            });
            modal.querySelector('.tm-excel-zoom-reset')?.addEventListener('click', () => {
                currentZoom = 1;
                updateZoomUI();
            });

            (() => {
                const zoomBar = modal.querySelector('.tm-excel-zoom-bar-el');
                if (!zoomBar) return;

                let isDraggingZoom = false;
                let offsetX, offsetY;

                zoomBar.addEventListener('mousedown', (e) => {
                    if (e.target.closest('button')) return;
                    isDraggingZoom = true;

                    const rect = zoomBar.getBoundingClientRect();
                    offsetX = e.clientX - rect.left;
                    offsetY = e.clientY - rect.top;

                    zoomBar.style.bottom = 'auto';
                    zoomBar.style.transition = 'none';
                    zoomBar.style.cursor = 'grabbing';
                });

                window.addEventListener('mousemove', (e) => {
                    if (!isDraggingZoom) return;

                    const parentRect = zoomBar.parentElement.getBoundingClientRect();
                    const barRect = zoomBar.getBoundingClientRect();

                    let newLeft = e.clientX - parentRect.left - offsetX;
                    let newTop = e.clientY - parentRect.top - offsetY;

                    // Bounding
                    newLeft = Math.max(10, Math.min(newLeft, parentRect.width - barRect.width - 10));
                    newTop = Math.max(10, Math.min(newTop, parentRect.height - barRect.height - 10));

                    zoomBar.style.left = newLeft + 'px';
                    zoomBar.style.top = newTop + 'px';
                });

                window.addEventListener('mouseup', () => {
                    if (isDraggingZoom) {
                        isDraggingZoom = false;
                        zoomBar.style.transition = 'transform 0.1s ease-out';
                        zoomBar.style.cursor = 'move';
                    }
                });

                // Touch support
                zoomBar.addEventListener('touchstart', (e) => {
                    if (e.target.closest('button')) return;
                    const touch = e.touches[0];
                    isDraggingZoom = true;

                    const rect = zoomBar.getBoundingClientRect();
                    offsetX = touch.clientX - rect.left;
                    offsetY = touch.clientY - rect.top;

                    zoomBar.style.bottom = 'auto';
                    zoomBar.style.transition = 'none';
                }, { passive: false });

                window.addEventListener('touchmove', (e) => {
                    if (!isDraggingZoom) return;
                    const touch = e.touches[0];
                    const parentRect = zoomBar.parentElement.getBoundingClientRect();
                    const barRect = zoomBar.getBoundingClientRect();

                    let newLeft = touch.clientX - parentRect.left - offsetX;
                    let newTop = touch.clientY - parentRect.top - offsetY;

                    newLeft = Math.max(10, Math.min(newLeft, parentRect.width - barRect.width - 10));
                    newTop = Math.max(10, Math.min(newTop, parentRect.height - barRect.height - 10));

                    zoomBar.style.left = newLeft + 'px';
                    zoomBar.style.top = newTop + 'px';
                }, { passive: false });

                window.addEventListener('touchend', () => {
                    if (isDraggingZoom) {
                        isDraggingZoom = false;
                        zoomBar.style.transition = 'transform 0.1s ease-out';
                    }
                });
            })();

            previewTableWrap?.addEventListener('wheel', (e) => {
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    if (e.deltaY < 0) currentZoom = Math.min(currentZoom + 0.05, 3);
                    else currentZoom = Math.max(currentZoom - 0.05, 0.2);
                    updateZoomUI();
                }
            }, { passive: false });

            const handleExcelFile = (file, isFromInput = false) => {
                if (!file) return;

                const isPdf = file.name.toLowerCase().endsWith('.pdf');
                modal.classList.toggle('tm-pdf-theme', isPdf);

                if (!isFromInput && fileInput) {
                    setSelectedFileOnInput(fileInput, file);
                    return;
                }

                const nameEl = modal.querySelector('.tm-excel-file-name');
                if (nameEl) {
                    nameEl.textContent = 'Archivo: ' + file.name;
                    nameEl.classList.remove('tm-hidden');
                }

                if (isPdf) {
                    // PDF: no se puede leer con SheetJS, usar previsualización del servidor
                    currentWorkbook = null;
                    currentSheetIdx = 0;
                    const sheetTabsWrap = modal.querySelector('.tm-excel-sheet-tabs-wrap-el');
                    if (sheetTabsWrap) sheetTabsWrap.style.display = 'none';
                    const inner = modal.querySelector('.tm-excel-sheet-inner-el');
                    if (inner) inner.innerHTML = '<div style="padding:60px; text-align:center; color:var(--clr-text-light);"><i class="fa-solid fa-file-pdf" style="font-size:2.5rem; margin-bottom:12px; opacity:0.4; color:#e74c3c;"></i><p>Archivo PDF cargado. Las columnas se detectarán automáticamente del servidor.</p></div>';
                    fetchExcelPreviewThumbnails(file, true);
                    modal.querySelector('.tm-excel-auto-detect').style.display = 'inline-flex';
                    return;
                }

                const reader = new FileReader();
                reader.onload = (re) => {
                    try {
                        const data = new Uint8Array(re.target.result);
                        const workbook = XLSX.read(data, {type: 'array'});
                        currentWorkbook = workbook;
                        currentSheetIdx = 0;
                        switchToSheet(0);
                        renderSheetTabs(workbook.SheetNames, 0);
                        fetchExcelPreviewThumbnails(file, true);
                        modal.querySelector('.tm-excel-auto-detect').style.display = 'inline-flex';
                    } catch (err) {
                        if (errPreviewEl) { errPreviewEl.textContent = 'Error al procesar el Excel.'; errPreviewEl.classList.remove('tm-hidden'); }
                    }
                };
                reader.readAsArrayBuffer(file);
            };

            modal.__tmHandleFile = (f) => handleExcelFile(f, false);

            const dropzone = modal.querySelector('.tm-excel-dropzone-el');
            dropzone?.addEventListener('click', () => fileInput?.click());
            fileInput?.addEventListener('change', (e) => handleExcelFile(e.target.files[0], true));

            ['dragover', 'dragleave', 'drop'].forEach(evt => {
                dropzone?.addEventListener(evt, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (evt === 'dragover') dropzone.classList.add('is-dragover');
                    else dropzone.classList.remove('is-dragover');
                    if (evt === 'drop') handleExcelFile(e.dataTransfer.files[0], false);
                });
            });

            const searchAllCheck = modal.querySelector('.tm-excel-search-all');
            const mrSelectContainer = modal.querySelector('.tm-excel-mr-select-container-el');

            headerRowInput?.addEventListener('input', updateRowHighlights);
            dataStartRowInput?.addEventListener('input', updateRowHighlights);

            searchAllCheck?.addEventListener('change', function() {
                if (mrSelectContainer) mrSelectContainer.style.display = this.checked ? 'none' : 'block';
                renderMunicipioOptionsForModal();
                updateMunicipioImportModeForModal();
            });
            mrInput?.addEventListener('change', function () {
                renderMunicipioOptionsForModal();
                updateMunicipioImportModeForModal();
            });
            autoMunicipioChecks.forEach(function (chk) {
                chk.addEventListener('change', function () {
                    this.dataset.userTouched = '1';
                    updateMunicipioImportModeForModal();
                });
            });
            renderMunicipioOptionsForModal();
            updateMunicipioImportModeForModal();

            modal.querySelector('.tm-excel-auto-detect')?.addEventListener('click', () => {
                if (!workbookData) return;
                let found = currentSheetStartRow;
                for (let i = 0; i < workbookData.length; i++) {
                    if ((workbookData[i] || []).filter(c => c !== null && c !== '').length >= 3) {
                        found = currentSheetStartRow + i;
                        break;
                    }
                }
                headerRowInput.value = found;
                dataStartRowInput.value = found + 1;
                updateRowHighlights();
            });

            modal.querySelector('.tm-excel-reset-trigger')?.addEventListener('click', () => {
                if (typeof modal.__excelReset === 'function') modal.__excelReset();
            });

            modal.querySelector('.tm-excel-read-columns')?.addEventListener('click', function () {
                const errSection = modal.querySelector('.tm-excel-errors-section');
                if (errSection) errSection.classList.add('tm-hidden');
                const errList = modal.querySelector('.tm-excel-errors-list');
                if (errList) errList.innerHTML = '';

                const file = fileInput?.files[0];
                if (!file) {
                    if (errPreviewEl) { errPreviewEl.textContent = 'Selecciona un archivo Excel.'; errPreviewEl.classList.remove('tm-hidden'); }
                    return;
                }
                const fd = new FormData();
                fd.append('archivo_excel', file);
                fd.append('header_row', headerRowInput.value || '1');
                fd.append('sheet_index', currentSheetIdx);
                fd.append('_token', csrfToken);

                const btn = this;
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Leyendo...';

                csrfFetch(previewUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken } })
                .then(r => safeJsonParse(r))
                .then(j => {
                    if (!j.success) throw new Error(j.message);
                    excelFields = j.fields || [];
                    const warnings = Array.isArray(j.warnings) ? j.warnings.filter(Boolean) : [];
                    if (warnings.length && detectNoteEl) {
                        detectNoteEl.textContent = warnings.join(' ');
                        detectNoteEl.classList.remove('tm-hidden');
                    }

                    if (j.preview_thumbnails) applyExcelPreviewThumbnails(j.preview_thumbnails);

                    const updateMappedColumns = () => {
                        modal.querySelectorAll('.is-mapped-column').forEach(el => el.classList.remove('is-mapped-column'));
                        modal.querySelectorAll(getActiveMapSelector()).forEach(sel => {
                            if (sel.value === '') return;
                            const idx = parseInt(sel.value, 10);
                            const th = modal.querySelector(`.tm-excel-preview-table thead th:nth-child(${idx + 2})`);
                            if (th) th.classList.add('is-mapped-column');
                            modal.querySelectorAll(`.tm-excel-preview-table tbody tr`).forEach(tr => {
                                const td = tr.querySelector(`td:nth-child(${idx + 2})`);
                                if (td) td.classList.add('is-mapped-column');
                            });
                        });
                    };

                    const updateMatchingKeyPriorities = () => {
                        let rank = 1;
                        modal.querySelectorAll('.tm-excel-map-select-update').forEach(sel => {
                            const key = sel.getAttribute('data-field-key');
                            if (!key) return;
                            const checkbox = modal.querySelector('.tm-excel-match-key[data-field-key="' + String(key).replace(/"/g, '') + '"]');
                            const badge = modal.querySelector('.tm-excel-match-order[data-field-key="' + String(key).replace(/"/g, '') + '"]');
                            if (!checkbox || !badge) return;

                            if (sel.value === '') {
                                checkbox.checked = false;
                                checkbox.disabled = true;
                                badge.style.display = 'none';
                                badge.textContent = '';
                                return;
                            }

                            checkbox.disabled = false;
                            if (checkbox.checked) {
                                badge.textContent = 'Base ' + rank;
                                badge.style.display = 'inline-flex';
                                rank++;
                            } else {
                                badge.style.display = 'none';
                                badge.textContent = '';
                            }
                        });
                    };

                    const friendlyTypes = {
                        'text': 'Texto',
                        'image': 'Imagen/Foto',
                        'select': 'Lista de opciones',
                        'geopoint': 'Ubicación (GPS)',
                        'date': 'Fecha',
                        'number': 'Número',
                        'url': 'Enlace web',
                        'textarea': 'Texto largo',
                        'semaforo': 'Semáforo (Verde / Amarillo / Rojo)'
                    };

                    if (mapBodyImport && mapBodyUpdate) {
                        mapBodyImport.innerHTML = '';
                        mapBodyUpdate.innerHTML = '';
                        (excelFields || []).forEach(f => {
                            const trImport = document.createElement('tr');
                            const trUpdate = document.createElement('tr');
                            const sug = (j.suggested_map || {})[f.key];
                            const friendly = friendlyTypes[f.type] || f.type;
                            let opts = '<option value="">— No importar —</option>';
                            (j.headers || []).forEach(h => {
                                const sel = (sug === h.index) ? ' selected' : '';
                                opts += '<option value="' + h.index + '"' + sel + '>' + (h.letter + ': ' + (h.label || '(vacío)')).replace(/</g, '') + '</option>';
                            });
                            trImport.innerHTML = '<td title="Tipo: ' + friendly + '" style="cursor:help; font-weight:600;">' + String(f.label).replace(/</g, '') + (f.is_required ? ' *' : '') + '</td><td><select class="tm-excel-map-select-import" data-field-key="' + String(f.key).replace(/"/g, '') + '">' + opts + '</select></td>';
                            trUpdate.innerHTML = '<td title="Tipo: ' + friendly + '" style="cursor:help; font-weight:600; width:38%;">' + String(f.label).replace(/</g, '') + (f.is_required ? ' *' : '') + '</td><td><div style="display:flex; flex-direction:column; gap:8px;"><div style="display:flex; flex-direction:column; gap:4px;"><span style="font-size:10px; color:var(--clr-text-light);">Columna del Excel</span><select class="tm-excel-map-select-update" data-field-key="' + String(f.key).replace(/"/g, '') + '" style="width:100%; min-width:0;">' + opts + '</select></div><div style="display:flex; flex-wrap:wrap; gap:12px 18px; align-items:center;"><label style="display:flex; align-items:center; gap:6px; font-size:12px; line-height:1.2;"><input type="checkbox" class="tm-excel-match-key" data-field-key="' + String(f.key).replace(/"/g, '') + '"><span>Usar como base</span></label><span class="tm-excel-match-order" data-field-key="' + String(f.key).replace(/"/g, '') + '" style="display:none; padding:2px 8px; border-radius:999px; background:rgba(47,111,237,0.12); color:#2f6fed; font-weight:700;"></span><label style="display:flex; align-items:center; gap:6px; font-size:12px; line-height:1.2;"><input type="checkbox" class="tm-excel-update-key" data-field-key="' + String(f.key).replace(/"/g, '') + '"><span>Actualizar</span></label></div></div></td>';
                            mapBodyImport.appendChild(trImport);
                            mapBodyUpdate.appendChild(trUpdate);
                        });

                        modal.querySelectorAll('.tm-excel-map-select-import, .tm-excel-map-select-update').forEach(sel => {
                            sel.addEventListener('change', function () {
                                updateMappedColumns();
                                updateMunicipioImportModeForModal();
                                updateMatchingKeyPriorities();
                            });
                        });
                        modal.querySelectorAll('.tm-excel-match-key').forEach(chk => {
                            chk.addEventListener('change', function () {
                                updateMatchingKeyPriorities();
                            });
                        });
                        updateMappedColumns();
                        updateMunicipioImportModeForModal();
                        updateMatchingKeyPriorities();
                    }
                    setExcelMode(activeExcelMode);
                    if (step2) step2.classList.remove('tm-hidden');
                    const controlsSide = modal.querySelector('.tm-excel-controls-side');
                    if (controlsSide) {
                        setTimeout(() => {
                            controlsSide.scrollTo({ top: controlsSide.scrollHeight, behavior: 'smooth' });
                        }, 100);
                    }
                    const stepNote = modal.querySelector('.tm-excel-step-note');
                    if (stepNote) stepNote.textContent = 'Relaciona cada campo del módulo con una columna de tu Excel.';
                }).catch(e => {
                    if (errPreviewEl) { errPreviewEl.textContent = e.message; errPreviewEl.classList.remove('tm-hidden'); }
                }).finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
            });

            modal.querySelector('.tm-excel-back')?.addEventListener('click', () => {
                if (step2) step2.classList.add('tm-hidden');
                const controlsSide = modal.querySelector('.tm-excel-controls-side');
                if (controlsSide) controlsSide.scrollTo({ top: 0, behavior: 'smooth' });
                const stepNote = modal.querySelector('.tm-excel-step-note');
                if (stepNote) stepNote.innerHTML = 'Haz clic en una fila para marcar <strong>Encabezado</strong> y <strong>Datos</strong>.';
            });

            modal.querySelector('.tm-excel-importar')?.addEventListener('click', function() {
                const errSection = modal.querySelector('.tm-excel-errors-section');
                if (errSection) errSection.classList.add('tm-hidden');
                const errList = modal.querySelector('.tm-excel-errors-list');
                if (errList) errList.innerHTML = '';

                const file = fileInput?.files[0];
                if (!file) return;
                const mapping = {};
                modal.querySelectorAll('.tm-excel-map-select-import').forEach(sel => {
                    const key = sel.getAttribute('data-field-key');
                    if (key) mapping[key] = sel.value === '' ? null : parseInt(sel.value, 10);
                });
                const municipioVisible = municipioContainer && municipioContainer.style.display !== 'none';
                if (municipioVisible && municipioInput && !municipioInput.value) {
                    if (errImportEl) {
                        errImportEl.textContent = 'Selecciona un municipio de destino o mapea la columna Municipio para identificarlo automáticamente.';
                        errImportEl.classList.remove('tm-hidden');
                    }
                    return;
                }
                const fd = new FormData();
                fd.append('archivo_excel', file);
                fd.append('header_row', headerRowInput.value || '1');
                fd.append('data_start_row', dataStartRowInput.value || '2');
                fd.append('mapping', JSON.stringify(mapping));
                fd.append('all_microrregions', searchAllCheck?.checked ? '1' : '0');
                fd.append('selected_microrregion_id', mrInput ? mrInput.value : '');
                fd.append('selected_municipio', municipioInput ? municipioInput.value : '');
                fd.append('auto_identify_municipio', (getActiveAutoMunicipioCheck()?.checked ? '1' : '0'));
                fd.append('sheet_index', currentSheetIdx);
                fd.append('_token', csrfToken);

                const btn = this;
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Importando...';

                csrfFetch(importUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken } })
                .then(r => safeJsonParse(r))
                .then(j => {
                    const okEl = modal.querySelector('.tm-excel-import-ok');
                    const errEl = modal.querySelector('.tm-excel-import-err');
                    if (okEl) okEl.classList.add('tm-hidden');
                    if (errEl) errEl.classList.add('tm-hidden');

                    if (!j.success) throw new Error(j.message);

                    // Mostrar errores detallados
                    const errSection = modal.querySelector('.tm-excel-errors-section');
                    const errList = modal.querySelector('.tm-excel-errors-list');
                    if (j.row_errors && j.row_errors.length > 0) {
                        errSection?.classList.remove('tm-hidden');
                        if (errList) {
                            errList.innerHTML = '';
                            j.row_errors.forEach((err, idx) => {
                                const cardId = `tmErrRow_${currentModuleId}_${idx}`;
                                if (!searchAllCheck?.checked && mrInput && mrInput.value !== '' &&
                                    (err.selected_microrregion_id == null || err.selected_microrregion_id === '')) {
                                    err.selected_microrregion_id = parseInt(mrInput.value, 10);
                                }
                                const card = document.createElement('div');
                                card.className = 'tm-error-log-card';
                                card.id = cardId;
                                card.dataset.rowData = JSON.stringify(err.data);
                                if (err.data_urls) card.dataset.dataUrls = JSON.stringify(err.data_urls);
                                if (err.conflict_data) card.dataset.conflictData = JSON.stringify(err.conflict_data);
                                card.dataset.municipioKey = String(err.municipio_key || 'municipio');
                                if (err?.selected_microrregion_id != null && err.selected_microrregion_id !== '') {
                                    card.dataset.microrregionId = String(err.selected_microrregion_id);
                                }
                                card.style = 'padding:15px; border:1px solid var(--clr-border); border-radius:12px; background:var(--clr-bg); font-size:0.85rem; transition: all 0.3s ease;';
                                card.innerHTML = renderErrorCardHtml(err, idx, currentModuleId, importSingleUrl, cardId, false);
                                errList.appendChild(card);
                            });

                            const controlsSide = modal.querySelector('.tm-excel-controls-side');
                            if (controlsSide) {
                                setTimeout(() => {
                                    controlsSide.scrollTo({ top: errSection.offsetTop, behavior: 'smooth' });
                                }, 100);
                            }
                        }
                    }

                    // Resaltar duplicados: interactivo al hacer click en cada tarjeta
                    if (j.row_errors && errList) {
                        const table = modal.querySelector('.tm-excel-preview-table');
                        if (table) {
                            j.row_errors.forEach((err, idx) => {
                                const cardId = `tmErrRow_${currentModuleId}_${idx}`;
                                const card = document.getElementById(cardId);
                                if (!card) return;

                                // Guardar metadata del error en la tarjeta para el click
                                card.dataset.errRow = String(err.row || '');
                                card.dataset.originalRow = String(err.original_row || '');
                                card.dataset.isDuplicate = err.is_duplicate ? '1' : '0';

                                card.title = 'Clic para localizar en el Excel';
                                card.addEventListener('click', function(e) {
                                    if (e.target.closest('button') || e.target.closest('.tm-inline-edit-form') || e.target.closest('select') || e.target.closest('input')) return;

                                    // Limpiar anteriores
                                    table.querySelectorAll('.tm-row-glow-purple, .tm-row-glow-orange').forEach(r => {
                                        r.classList.remove('tm-row-glow-purple', 'tm-row-glow-orange');
                                    });
                                    errList.querySelectorAll('.is-active-row').forEach(c => c.classList.remove('is-active-row'));

                                    // Marcar esta tarjeta como activa
                                    card.classList.add('is-active-row');

                                    // Resaltar la fila del error en naranja
                                    const errRowIdx = card.dataset.errRow;
                                    const rowEl = table.querySelector('tr[data-row-index="' + errRowIdx + '"]');
                                    if (rowEl) {
                                        rowEl.classList.add('tm-row-glow-orange');
                                        rowEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    }

                                    // Si es duplicado y tiene fila original, resaltarla en morado
                                    const origRow = card.dataset.originalRow;
                                    if (card.dataset.isDuplicate === '1' && origRow && origRow !== 'db' && origRow !== '') {
                                        const origEl = table.querySelector('tr[data-row-index="' + origRow + '"]');
                                        if (origEl) origEl.classList.add('tm-row-glow-purple');
                                    }
                                });
                            });
                        }
                    }

                    const warnings = Array.isArray(j.warnings) ? j.warnings.filter(Boolean) : [];
                    const msg = warnings.length ? ((j.message || '') + ' ' + warnings.join(' ')).trim() : j.message;

                    // Persistir errores en sesión
                    if (j.row_errors && j.row_errors.length > 0) {
                        saveImportErrors(currentModuleId, j.row_errors, importSingleUrl);
                    }

                    // Acumular registros importados en esta sesión de modal
                    if (j.imported > 0) modal.__excelImportedCount = (modal.__excelImportedCount || 0) + j.imported;

                    Swal.fire({ title: '¡Completado!', text: msg, icon: 'success', confirmButtonText: 'Aceptar' })
                        .then(() => {
                            if (j.skipped === 0) {
                                saveImportErrors(currentModuleId, [], ''); // Limpiar si todo fue ok
                                var savedCount = modal.__excelImportedCount || 0;
                                if (typeof modal.__excelReset === 'function') modal.__excelReset();
                                modal.__excelImportedCount = savedCount;
                            }
                        });
                }).catch(e => {
                    const errEl = modal.querySelector('.tm-excel-import-err');
                    if (errEl) { errEl.textContent = e.message; errEl.classList.remove('tm-hidden'); }
                }).finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
            });

            // Botón "Actualizar registros existentes"
            modal.querySelector('.tm-excel-actualizar-existentes')?.addEventListener('click', function() {
                const errSection = modal.querySelector('.tm-excel-errors-section');
                if (errSection) errSection.classList.add('tm-hidden');
                const errList = modal.querySelector('.tm-excel-errors-list');
                if (errList) errList.innerHTML = '';

                const file = fileInput?.files[0];
                if (!file) return;
                if (!updateUrl) { alert('Ruta de actualización no configurada.'); return; }

                const mapping = {};
                modal.querySelectorAll('.tm-excel-map-select-update').forEach(sel => {
                    const key = sel.getAttribute('data-field-key');
                    if (key) mapping[key] = sel.value === '' ? null : parseInt(sel.value, 10);
                });
                const matchingKeys = [];
                modal.querySelectorAll('.tm-excel-match-key:checked').forEach(chk => {
                    const key = chk.getAttribute('data-field-key');
                    if (!key) return;
                    if (!Object.prototype.hasOwnProperty.call(mapping, key)) return;
                    if (mapping[key] === null) return;
                    matchingKeys.push(key);
                });
                if (matchingKeys.length === 0) {
                    if (errImportEl) {
                        errImportEl.textContent = 'Selecciona al menos una columna base mapeada para identificar coincidencias.';
                        errImportEl.classList.remove('tm-hidden');
                    }
                    return;
                }
                const updateKeys = [];
                modal.querySelectorAll('.tm-excel-update-key:checked').forEach(chk => {
                    const key = chk.getAttribute('data-field-key');
                    if (!key) return;
                    if (!Object.prototype.hasOwnProperty.call(mapping, key)) return;
                    if (mapping[key] === null) return;
                    if (matchingKeys.includes(key)) return;
                    updateKeys.push(key);
                });
                if (updateKeys.length === 0) {
                    if (errImportEl) {
                        errImportEl.textContent = 'Selecciona al menos una columna a actualizar (distinta de las columnas base).';
                        errImportEl.classList.remove('tm-hidden');
                    }
                    return;
                }
                const fd = new FormData();
                fd.append('archivo_excel', file);
                fd.append('header_row', headerRowInput.value || '1');
                fd.append('data_start_row', dataStartRowInput.value || '2');
                fd.append('mapping', JSON.stringify(mapping));
                fd.append('matching_keys', JSON.stringify(matchingKeys));
                fd.append('update_keys', JSON.stringify(updateKeys));
                fd.append('all_microrregions', searchAllCheck?.checked ? '1' : '0');
                fd.append('selected_microrregion_id', mrInput ? mrInput.value : '');
                fd.append('selected_municipio', municipioInput ? municipioInput.value : '');
                fd.append('auto_identify_municipio', (getActiveAutoMunicipioCheck()?.checked ? '1' : '0'));
                fd.append('sheet_index', currentSheetIdx);
                fd.append('_token', csrfToken);

                const btn = this;
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Actualizando...';

                csrfFetch(updateUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken } })
                .then(r => safeJsonParse(r))
                .then(j => {
                    const okEl = modal.querySelector('.tm-excel-import-ok');
                    const errEl = modal.querySelector('.tm-excel-import-err');
                    if (okEl) okEl.classList.add('tm-hidden');
                    if (errEl) errEl.classList.add('tm-hidden');

                    if (!j.success) throw new Error(j.message);

                    // Mostrar errores/info de filas no coincidentes
                    if (j.row_errors && j.row_errors.length > 0) {
                        const errSection = modal.querySelector('.tm-excel-errors-section');
                        const errList = modal.querySelector('.tm-excel-errors-list');
                        errSection?.classList.remove('tm-hidden');
                        if (errList) {
                            errList.innerHTML = '';
                            j.row_errors.forEach((err, idx) => {
                                const card = document.createElement('div');
                                card.className = 'tm-error-log-card';
                                card.style = 'padding:12px; border:1px solid var(--clr-border); border-radius:10px; background:var(--clr-bg); font-size:0.85rem;';
                                card.innerHTML = '<div style="font-weight:600; color:var(--clr-text-light); margin-bottom:4px;">Fila ' + (err.row || '?') + '</div><div>' + String(err.message || '').replace(/</g, '&lt;') + '</div>';
                                errList.appendChild(card);
                            });
                        }
                    }

                    if (j.updated > 0) modal.__excelImportedCount = (modal.__excelImportedCount || 0) + j.updated;

                    Swal.fire({ title: '¡Completado!', text: j.message, icon: j.updated > 0 ? 'success' : 'info', confirmButtonText: 'Aceptar' });
                }).catch(e => {
                    const errEl = modal.querySelector('.tm-excel-import-err');
                    if (errEl) { errEl.textContent = e.message; errEl.classList.remove('tm-hidden'); }
                }).finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
            });
        });


        document.addEventListener('click', function (event) {
            const btn = event.target.closest('[data-open-template-download]');
            if (!btn) return;

            const modalId = btn.getAttribute('data-open-template-download');
            const modal = modalId ? document.getElementById(modalId) : null;
            if (!modal) return;

            event.preventDefault();
            openModal(modal, btn);
        });

        document.addEventListener('click', function (event) {
            const btn = event.target.closest('[data-download-all-templates]');
            if (!btn) {
                return;
            }

            event.preventDefault();

            let urls = [];
            try {
                const raw = btn.getAttribute('data-template-urls') || '[]';
                const parsed = JSON.parse(raw);
                urls = Array.isArray(parsed) ? parsed.filter(function (u) { return typeof u === 'string' && u.trim() !== ''; }) : [];
            } catch (_) {
                urls = [];
            }

            if (!urls.length) {
                if (typeof window.segobToast === 'function') {
                    window.segobToast('warning', 'No hay microrregiones asignadas para descargar.');
                }
                return;
            }

            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="tm-template-download-badge">Descargando...</span><h4>Todas las microregiones</h4><p>Preparando archivos individuales...</p>';

            let iframe = document.getElementById('tmTemplateDownloadFrame');
            if (!(iframe instanceof HTMLIFrameElement)) {
                iframe = document.createElement('iframe');
                iframe.id = 'tmTemplateDownloadFrame';
                iframe.style.display = 'none';
                iframe.setAttribute('aria-hidden', 'true');
                document.body.appendChild(iframe);
            }

            urls.forEach(function (url, index) {
                window.setTimeout(function () {
                    const sep = url.indexOf('?') >= 0 ? '&' : '?';
                    iframe.src = url + sep + 'batch=' + Date.now() + '_' + index;
                }, index * 900);
            });

            window.setTimeout(function () {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }, (urls.length * 900) + 350);
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

        // Global Drag & Drop for Excel
        const globalOverlay = document.getElementById('tmGlobalExcelDropOverlay');
        let dragCounter = 0;
        const getOpenExcelModal = () => document.querySelector('.tm-excel-import-modal.is-open');

        window.addEventListener('dragenter', (e) => {
            if (!getOpenExcelModal()) return;
            if (e.dataTransfer?.types?.includes('Files')) {
                dragCounter++;
                globalOverlay?.classList.add('is-active');
            }
        });

        window.addEventListener('dragleave', (e) => {
            if (!getOpenExcelModal()) return;
            dragCounter--;
            if (dragCounter <= 0) {
                globalOverlay?.classList.remove('is-active');
                dragCounter = 0;
            }
        });

        window.addEventListener('dragover', (e) => {
            if (!getOpenExcelModal()) return;
            e.preventDefault();
        });

        window.addEventListener('drop', (e) => {
            const openModal = getOpenExcelModal();
            if (!openModal) return;
            e.preventDefault();
            dragCounter = 0;
            globalOverlay?.classList.remove('is-active');

            const file = e.dataTransfer.files[0];
            if (!file) return;

            const name = (file.name || '').toLowerCase();
            if (!name.endsWith('.xlsx') && !name.endsWith('.xls') && !name.endsWith('.csv') && !name.endsWith('.pdf')) return;

            // Encontrar modal de excel abierto
            if (openModal && typeof openModal.__tmHandleFile === 'function') {
                openModal.__tmHandleFile(file);
            }
        });

        // --- Edición múltiple (modal lista + formulario) ---
        (function initBulkEditModal() {
            function tmBulkEsc(s) {
                return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
            }
            /** Filas iniciales del textarea (luego tmBulkFitTextareaHeight ajusta al contenido real). */
            function tmBulkTextareaRows(str) {
                const s = String(str ?? '');
                if (!s.length) {
                    return 1;
                }
                const lines = s.split(/\r?\n/).length;
                const byWidth = Math.ceil(s.length / 72);
                return Math.min(22, Math.max(1, lines, byWidth));
            }
            /** Altura proporcional al texto (poco contenido = poca altura). */
            function tmBulkFitTextareaHeight(el) {
                if (!el || el.tagName !== 'TEXTAREA' || !el.classList.contains('tm-bulk-textarea')) {
                    return;
                }
                el.style.overflowY = 'hidden';
                el.style.height = 'auto';
                const natural = el.scrollHeight;
                const maxH = Math.min(window.innerHeight * 0.5, 448);
                const next = Math.min(natural, maxH);
                el.style.height = next + 'px';
                el.style.overflowY = natural > maxH ? 'auto' : 'hidden';
            }
            function deepClone(o) {
                try {
                    return JSON.parse(JSON.stringify(o));
                } catch (e) {
                    return {};
                }
            }
            function valEqual(a, b) {
                if (a === b) {
                    return true;
                }
                if (typeof a === 'object' && a !== null && typeof b === 'object' && b !== null) {
                    return JSON.stringify(a) === JSON.stringify(b);
                }
                return String(a ?? '') === String(b ?? '');
            }
            function entryHasPendingFileChanges(state, eid) {
                const entryPending = state.pendingFiles[eid] || {};
                const hasFiles = Object.keys(entryPending).some(function (key) {
                    return Array.isArray(entryPending[key]) && entryPending[key].length > 0;
                });
                if (hasFiles) {
                    return true;
                }
                const removeMap = state.removeExisting[eid] || {};
                return Object.keys(removeMap).some(function (key) {
                    return Array.isArray(removeMap[key]) && removeMap[key].length > 0;
                });
            }
            function entryIsDirty(state, eid) {
                const orig = state.originals[eid];
                const draft = state.drafts[eid];
                if (!orig || !draft) {
                    return false;
                }
                for (const f of state.fields) {
                    if (f.type === 'image' || f.type === 'file' || f.type === 'document') {
                        continue;
                    }
                    if (!valEqual(draft[f.key], orig[f.key])) {
                        return true;
                    }
                }
                if (state.showMrSelect) {
                    const mrOrig = state.entryById[eid].microrregion_id;
                    const mr = state.draftMicrorregion[eid] !== undefined ? state.draftMicrorregion[eid] : mrOrig;
                    if (mr !== mrOrig) {
                        return true;
                    }
                }
                if (entryHasPendingFileChanges(state, eid)) {
                    return true;
                }
                return false;
            }
            function countDirty(state) {
                let fieldCount = 0;
                const entryIds = new Set();
                for (const eidStr of Object.keys(state.drafts)) {
                    const eid = parseInt(eidStr, 10);
                    const orig = state.originals[eid];
                    const draft = state.drafts[eid];
                    if (!orig || !draft) {
                        continue;
                    }
                    for (const f of state.fields) {
                        if (f.type === 'image' || f.type === 'file' || f.type === 'document') {
                            continue;
                        }
                        if (!valEqual(draft[f.key], orig[f.key])) {
                            fieldCount++;
                            entryIds.add(eid);
                        }
                    }
                    if (state.showMrSelect) {
                        const mrOrig = state.entryById[eid].microrregion_id;
                        const mr = state.draftMicrorregion[eid] !== undefined ? state.draftMicrorregion[eid] : mrOrig;
                        if (mr !== mrOrig) {
                            fieldCount++;
                            entryIds.add(eid);
                        }
                    }
                    if (entryHasPendingFileChanges(state, eid)) {
                        fieldCount++;
                        entryIds.add(eid);
                    }
                }
                return { fieldCount, entryCount: entryIds.size };
            }
            function refreshBulkCounter(modal) {
                const st = modal.__bulkState;
                if (!st) {
                    return;
                }
                const c = countDirty(st);
                const el = modal.querySelector('[data-tm-bulk-counter]');
                if (el) {
                    el.textContent = c.fieldCount + ' campo(s) editado(s) en ' + c.entryCount + ' registro(s)';
                }
                const saveExit = modal.querySelector('[data-tm-bulk-save-exit]');
                if (saveExit) {
                    saveExit.classList.toggle('tm-hidden', c.fieldCount === 0);
                }
                modal.querySelectorAll('[data-tm-bulk-row-save]').forEach(function (btn) {
                    const id = parseInt(btn.getAttribute('data-entry-id'), 10);
                    const dirty = entryIsDirty(st, id);
                    btn.classList.toggle('tm-hidden', !dirty);
                });
                modal.querySelectorAll('[data-tm-bulk-dirty-badge]').forEach(function (badge) {
                    const id = parseInt(badge.getAttribute('data-entry-id'), 10);
                    badge.classList.toggle('tm-hidden', !entryIsDirty(st, id));
                });
            }
            function buildFieldHtml(field, val, entryId, previewTpl, fileMeta) {
                function isScalar(x) {
                    return x === null || typeof x === 'string' || typeof x === 'number' || typeof x === 'boolean';
                }
                const k = field.key;
                const req = field.is_required ? ' required' : '';
                const lab = tmBulkEsc(field.label);
                const comment = field.comment ? '<small class="tm-field-help">' + tmBulkEsc(field.comment) + '</small>' : '';
                const name = 'values[' + k + ']';
                let v = val;
                if (v === undefined || v === null) {
                    v = '';
                }
                if (field.type === 'textarea') {
                    const tr = tmBulkTextareaRows(v);
                    return '<label class="tm-entry-field tm-col-full">' + lab + comment +
                        '<textarea class="tm-bulk-textarea" name="' + name + '" rows="' + tr + '" data-field-key="' + tmBulkEsc(k) + '"' + req + '>' + tmBulkEsc(String(v)) + '</textarea></label>';
                }
                if (field.type === 'number') {
                    return '<label class="tm-entry-field">' + lab + comment +
                        '<input type="number" step="any" name="' + name + '" value="' + tmBulkEsc(String(v)) + '" data-field-key="' + tmBulkEsc(k) + '"' + req + '></label>';
                }
                if (field.type === 'date') {
                    return '<label class="tm-entry-field">' + lab + comment +
                        '<input type="date" name="' + name + '" value="' + tmBulkEsc(String(v)) + '" data-field-key="' + tmBulkEsc(k) + '"' + req + '></label>';
                }
                if (field.type === 'datetime') {
                    return '<label class="tm-entry-field">' + lab + comment +
                        '<input type="datetime-local" name="' + name + '" value="' + tmBulkEsc(String(v)) + '" data-field-key="' + tmBulkEsc(k) + '"' + req + '></label>';
                }
                if (field.type === 'select') {
                    const opts = Array.isArray(field.options) ? field.options : [];
                    let optsHtml = '<option value="">Selecciona</option>';
                    opts.forEach(function (opt) {
                        if (typeof opt !== 'string' && typeof opt !== 'number') {
                            return;
                        }
                        const sel = String(opt) === String(v) ? ' selected' : '';
                        optsHtml += '<option value="' + tmBulkEsc(String(opt)) + '"' + sel + '>' + tmBulkEsc(String(opt)) + '</option>';
                    });
                    return '<label class="tm-entry-field">' + lab + comment +
                        '<select name="' + name + '" data-field-key="' + tmBulkEsc(k) + '"' + req + '>' + optsHtml + '</select></label>';
                }
                if (field.type === 'multiselect') {
                    const opts = Array.isArray(field.options) ? field.options : [];
                    const arr = Array.isArray(v) ? v : (typeof v === 'string' && v ? v.split(',').map(function (s) { return s.trim(); }) : []);
                    let boxes = '<div class="tm-multiselect-wrap">';
                    opts.forEach(function (opt) {
                        if (!isScalar(opt)) {
                            return;
                        }
                        const checked = arr.indexOf(String(opt)) !== -1 ? ' checked' : '';
                        boxes += '<label class="tm-multiselect-option"><input type="checkbox" name="' + name + '[]" value="' + tmBulkEsc(String(opt)) + '" data-field-key-multi="' + tmBulkEsc(k) + '"' + checked + '><span>' + tmBulkEsc(String(opt)) + '</span></label>';
                    });
                    boxes += '</div>';
                    return '<label class="tm-entry-field tm-col-full">' + lab + comment + boxes + '</label>';
                }
                if (field.type === 'boolean') {
                    const boolYes = v === true || v === 1 || v === '1' || (typeof v === 'string' && ['sí', 'si', 'yes', 'true', 'verdadero'].indexOf(String(v).toLowerCase().trim()) !== -1);
                    const boolNo = v === false || v === 0 || v === '0' || (typeof v === 'string' && ['no', 'false', 'falso'].indexOf(String(v).toLowerCase().trim()) !== -1);
                    return '<label class="tm-entry-field">' + lab + comment +
                        '<select name="' + name + '" data-field-key="' + tmBulkEsc(k) + '"' + req + '>' +
                        '<option value="">Selecciona</option>' +
                        '<option value="1"' + (boolYes ? ' selected' : '') + '>Si</option>' +
                        '<option value="0"' + (boolNo && !boolYes ? ' selected' : '') + '>No</option></select></label>';
                }
                if (field.type === 'semaforo') {
                    let optsHtml = '<option value="">Selecciona nivel</option>';
                    Object.keys(tmSemaforoLabels || {}).forEach(function (semVal) {
                        const sel = String(v) === String(semVal) ? ' selected' : '';
                        optsHtml += '<option value="' + tmBulkEsc(semVal) + '"' + sel + '>' + tmBulkEsc(tmSemaforoLabels[semVal]) + '</option>';
                    });
                    return '<label class="tm-entry-field">' + lab + comment +
                        '<select name="' + name + '" class="tm-semaforo-select" data-field-key="' + tmBulkEsc(k) + '"' + req + '>' + optsHtml + '</select></label>';
                }
                if (field.type === 'categoria') {
                    const catOpts = Array.isArray(field.options) ? field.options : [];
                    let optsHtml = '<option value="">Selecciona categoría</option>';
                    catOpts.forEach(function (cat) {
                        const catName = cat && cat.name ? String(cat.name) : '';
                        if (!catName) {
                            return;
                        }
                        const sel = String(v) === catName ? ' selected' : '';
                        optsHtml += '<option value="' + tmBulkEsc(catName) + '"' + sel + '>' + tmBulkEsc(catName) + '</option>';
                        const subs = (cat.sub || []);
                        subs.forEach(function (sub) {
                            const subVal = catName + ' > ' + String(sub);
                            const sel2 = String(v) === subVal ? ' selected' : '';
                            optsHtml += '<option value="' + tmBulkEsc(subVal) + '"' + sel2 + '>' + tmBulkEsc(catName) + ' → ' + tmBulkEsc(String(sub)) + '</option>';
                        });
                    });
                    return '<label class="tm-entry-field">' + lab + comment +
                        '<select name="' + name + '" data-field-key="' + tmBulkEsc(k) + '"' + req + '>' + optsHtml + '</select></label>';
                }
                if (field.type === 'municipio') {
                    return '<label class="tm-entry-field">' + lab + comment +
                        '<select name="' + name + '" class="tm-municipio-select" data-field-key="' + tmBulkEsc(k) + '"' + req + '><option value="">Selecciona un municipio</option></select></label>';
                }
                if (field.type === 'linked') {
                    const opts = field.options || {};
                    const pt = opts.primary_type || 'text';
                    const plab = opts.primary_label || 'Principal';
                    const pOpts = opts.primary_options || [];
                    const st = opts.secondary_type || 'text';
                    const slab = opts.secondary_label || 'Secundario';
                    const sOpts = opts.secondary_options || [];
                    const existing = typeof v === 'object' && v !== null ? v : {};
                    const pv = existing.primary != null ? existing.primary : '';
                    const sv = existing.secondary != null ? existing.secondary : '';
                    let primaryHtml = '';
                    if (pt === 'select') {
                        primaryHtml = '<select name="values[' + k + '__primary]" data-linked-primary data-field-key-p="' + tmBulkEsc(k) + '"' + req + '><option value="">Selecciona</option>';
                        pOpts.forEach(function (opt) {
                            primaryHtml += '<option value="' + tmBulkEsc(String(opt)) + '"' + (String(pv) === String(opt) ? ' selected' : '') + '>' + tmBulkEsc(String(opt)) + '</option>';
                        });
                        primaryHtml += '</select>';
                    } else {
                        primaryHtml = '<textarea class="tm-bulk-textarea" name="values[' + k + '__primary]" rows="' + tmBulkTextareaRows(pv) + '" data-linked-primary data-field-key-p="' + tmBulkEsc(k) + '"' + req + '>' + tmBulkEsc(String(pv)) + '</textarea>';
                    }
                    let secondaryHtml = '';
                    const showSec = pv !== '' && pv !== null && pv !== undefined;
                    if (st === 'select') {
                        secondaryHtml = '<select name="values[' + k + '__secondary]" data-linked-secondary data-field-key-s="' + tmBulkEsc(k) + '"' + (showSec ? '' : ' disabled') + '><option value="">Selecciona</option>';
                        sOpts.forEach(function (opt) {
                            secondaryHtml += '<option value="' + tmBulkEsc(String(opt)) + '"' + (String(sv) === String(opt) ? ' selected' : '') + '>' + tmBulkEsc(String(opt)) + '</option>';
                        });
                        secondaryHtml += '</select>';
                    } else {
                        secondaryHtml = '<textarea class="tm-bulk-textarea" name="values[' + k + '__secondary]" rows="' + tmBulkTextareaRows(sv) + '" data-linked-secondary data-field-key-s="' + tmBulkEsc(k) + '"' + (showSec ? '' : ' disabled') + '>' + tmBulkEsc(String(sv)) + '</textarea>';
                    }
                    return '<div class="tm-entry-field tm-col-full tm-linked-field-wrap" data-linked-field-group data-linked-key="' + tmBulkEsc(k) + '">' +
                        '<label class="tm-linked-primary-label">' + tmBulkEsc(plab) + '</label>' + primaryHtml +
                        '<div class="tm-linked-secondary-wrap" data-linked-secondary-wrap' + (showSec ? '' : ' hidden') + '>' +
                        '<label class="tm-linked-secondary-label">' + tmBulkEsc(slab) + '</label>' + secondaryHtml + '</div></div>';
                }
                if (field.type === 'image' || field.type === 'file' || field.type === 'document') {
                    const removedSet = new Set((fileMeta && Array.isArray(fileMeta.removedPaths)) ? fileMeta.removedPaths : []);
                    const pendingCount = fileMeta && typeof fileMeta.pendingCount === 'number' ? fileMeta.pendingCount : 0;
                    const paths = Array.isArray(v)
                        ? v.filter(function (p) { return typeof p === 'string' && p.trim() !== ''; })
                        : (typeof v === 'string' && v.trim() !== '' ? [v.trim()] : []);
                    const maxFiles = field.type === 'image' ? 2 : 1;
                    const accept = field.type === 'image'
                        ? 'image/jpeg,image/png,image/webp'
                        : (field.type === 'document' ? '.pdf,.docx' : '.jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv');
                    const rowHtml = paths.map(function (path, idx) {
                        const base = previewTpl ? previewTpl.replace('__EID__', String(entryId)).replace('__FKEY__', String(k)) : '';
                        const sep = base.indexOf('?') >= 0 ? '&' : '?';
                        const previewUrl = base ? (base + sep + 'i=' + idx) : '';
                        if (field.type === 'image' && previewUrl) {
                            return '<div class="tm-bulk-existing-file-row">'
                                + '<button type="button" class="tm-thumb-link" data-open-image-preview data-image-src="' + tmBulkEsc(previewUrl) + '" data-image-title="' + lab + ' (' + (idx + 1) + ')" title="Ver imagen ' + (idx + 1) + '">'
                                + '<i class="fa fa-image" aria-hidden="true"></i> Imagen ' + (idx + 1) + '</button>'
                                + '<label style="margin-left:8px;"><input type="checkbox" data-bulk-remove-existing data-entry-id="' + entryId + '" data-field-key="' + tmBulkEsc(k) + '" data-existing-path="' + tmBulkEsc(path) + '"' + (removedSet.has(path) ? ' checked' : '') + '> Quitar</label>'
                                + '</div>';
                        }
                        if (previewUrl) {
                            return '<div class="tm-bulk-existing-file-row">'
                                + '<button type="button" class="tm-thumb-link" data-open-file-preview="1" data-file-src="' + tmBulkEsc(previewUrl) + '" data-file-title="' + tmBulkEsc(String(lab).replace(/<[^>]*>/g, '')) + '" style="max-width:420px; width:min(100%,420px); text-align:left;">'
                                + '<i class="fa-solid fa-file" aria-hidden="true"></i> Ver documento</button>'
                                + '<label style="margin-left:8px;"><input type="checkbox" data-bulk-remove-existing data-entry-id="' + entryId + '" data-field-key="' + tmBulkEsc(k) + '" data-existing-path="' + tmBulkEsc(path) + '"' + (removedSet.has(path) ? ' checked' : '') + '> Quitar</label>'
                                + '</div>';
                        }
                        return '<div class="tm-bulk-existing-file-row">'
                            + '<span class="tm-muted">Archivo ' + (idx + 1) + '</span>'
                            + '<label style="margin-left:8px;"><input type="checkbox" data-bulk-remove-existing data-entry-id="' + entryId + '" data-field-key="' + tmBulkEsc(k) + '" data-existing-path="' + tmBulkEsc(path) + '"' + (removedSet.has(path) ? ' checked' : '') + '> Quitar</label>'
                            + '</div>';
                    }).join('');
                    return '<div class="tm-entry-field tm-col-full">'
                        + '<strong>' + lab + '</strong>' + comment
                        + '<div class="tm-bulk-existing-files" style="display:grid; gap:6px; margin:8px 0;">'
                        + (rowHtml || '<span class="tm-muted" style="font-size:0.78rem;">Sin archivo actual.</span>')
                        + '</div>'
                        + '<label style="display:block; margin:4px 0 8px;">'
                        + '<input type="checkbox" data-bulk-remove-all data-entry-id="' + entryId + '" data-field-key="' + tmBulkEsc(k) + '"' + ((paths.length > 0 && removedSet.size >= paths.length) ? ' checked' : '') + '> Quitar todos los archivos actuales'
                        + '</label>'
                        + '<input type="file" data-bulk-file-input data-entry-id="' + entryId + '" data-field-key="' + tmBulkEsc(k) + '" accept="' + tmBulkEsc(accept) + '"' + (maxFiles > 1 ? ' multiple' : '') + ' data-max-files="' + maxFiles + '">'
                        + (pendingCount > 0 ? '<small class="tm-field-help">Hay ' + pendingCount + ' archivo(s) listo(s) para subir al guardar.</small>' : '')
                        + '<small class="tm-field-help">Puedes subir hasta ' + maxFiles + ' archivo(s) por campo.</small>'
                        + '</div>';
                }
                if (field.type === 'geopoint') {
                    const gr = tmBulkTextareaRows(v);
                    return '<label class="tm-entry-field tm-col-full">' + lab + comment +
                        '<textarea class="tm-bulk-textarea" name="' + name + '" rows="' + Math.min(8, Math.max(1, gr)) + '" data-field-key="' + tmBulkEsc(k) + '"' + req + ' spellcheck="false">' + tmBulkEsc(String(v)) + '</textarea></label>';
                }
                const tr = tmBulkTextareaRows(v);
                return '<label class="tm-entry-field tm-col-full">' + lab + comment +
                    '<textarea class="tm-bulk-textarea" name="' + name + '" rows="' + tr + '" data-field-key="' + tmBulkEsc(k) + '"' + req + '>' + tmBulkEsc(String(v)) + '</textarea></label>';
            }
            function syncDraftFromForm(modal) {
                const st = modal.__bulkState;
                const form = modal.querySelector('[data-tm-bulk-form]');
                if (!st || !form || !st.selectedId) {
                    return;
                }
                const eid = st.selectedId;
                const draft = st.drafts[eid];
                if (!draft) {
                    return;
                }
                st.fields.forEach(function (f) {
                    if (f.type === 'seccion') {
                        return;
                    }
                    if (f.type === 'image' || f.type === 'file' || f.type === 'document') {
                        const removeChecks = Array.from(form.querySelectorAll('[data-bulk-remove-existing][data-entry-id="' + eid + '"][data-field-key="' + f.key + '"]:checked'));
                        const removeAll = form.querySelector('[data-bulk-remove-all][data-entry-id="' + eid + '"][data-field-key="' + f.key + '"]');
                        if (!st.removeExisting[eid]) {
                            st.removeExisting[eid] = {};
                        }
                        st.removeExisting[eid][f.key] = removeChecks.map(function (el) {
                            return String(el.getAttribute('data-existing-path') || '').trim();
                        }).filter(function (path) {
                            return path !== '';
                        });
                        if (removeAll && removeAll.checked) {
                            const allPaths = form.querySelectorAll('[data-bulk-remove-existing][data-entry-id="' + eid + '"][data-field-key="' + f.key + '"]');
                            st.removeExisting[eid][f.key] = Array.from(allPaths).map(function (el) {
                                return String(el.getAttribute('data-existing-path') || '').trim();
                            }).filter(function (path) {
                                return path !== '';
                            });
                        }
                        return;
                    }
                    if (f.type === 'multiselect') {
                        const arr = [];
                        form.querySelectorAll('[data-field-key-multi="' + f.key + '"]').forEach(function (cb) {
                            if (cb.checked) {
                                arr.push(cb.value);
                            }
                        });
                        draft[f.key] = arr;
                        return;
                    }
                    if (f.type === 'linked') {
                        const pk = f.key + '__primary';
                        const sk = f.key + '__secondary';
                        const pv = form.querySelector('[name="values[' + pk + ']"]');
                        const sv = form.querySelector('[name="values[' + sk + ']"]');
                        draft[f.key] = {
                            primary: pv ? pv.value : null,
                            secondary: sv && !sv.disabled ? sv.value : null,
                        };
                        return;
                    }
                    const inp = form.querySelector('[data-field-key="' + f.key + '"]');
                    if (inp) {
                        if (f.type === 'boolean') {
                            draft[f.key] = inp.value === '' ? null : (inp.value === '1' || inp.value === 'true');
                        } else {
                            draft[f.key] = inp.value === '' ? null : inp.value;
                        }
                    }
                });
            }
            function wireForm(modal) {
                const form = modal.querySelector('[data-tm-bulk-form]');
                if (!form) {
                    return;
                }
                const st = modal.__bulkState;
                form.querySelectorAll('[data-field-key], [data-field-key-multi], [data-linked-primary], [data-linked-secondary]').forEach(function (el) {
                    el.addEventListener('input', function () { syncDraftFromForm(modal); refreshBulkCounter(modal); });
                    el.addEventListener('change', function () { syncDraftFromForm(modal); refreshBulkCounter(modal); });
                });
                form.querySelectorAll('input[type="checkbox"][data-field-key-multi]').forEach(function (el) {
                    el.addEventListener('change', function () { syncDraftFromForm(modal); refreshBulkCounter(modal); });
                });
                form.querySelectorAll('[data-bulk-file-input]').forEach(function (input) {
                    input.addEventListener('change', function () {
                        const entryId = parseInt(input.getAttribute('data-entry-id'), 10);
                        const key = String(input.getAttribute('data-field-key') || '');
                        if (!entryId || !key || !st) {
                            return;
                        }
                        if (!st.pendingFiles[entryId]) {
                            st.pendingFiles[entryId] = {};
                        }
                        const maxFiles = parseInt(input.getAttribute('data-max-files') || '1', 10) || 1;
                        const selected = Array.from(input.files || []);
                        st.pendingFiles[entryId][key] = selected.slice(0, maxFiles);
                        if (selected.length > maxFiles) {
                            input.value = '';
                            Swal.fire('Aviso', 'Solo puedes seleccionar hasta ' + maxFiles + ' archivo(s).', 'warning');
                            st.pendingFiles[entryId][key] = [];
                        }
                        syncDraftFromForm(modal);
                        refreshBulkCounter(modal);
                    });
                });
                form.querySelectorAll('[data-bulk-remove-existing], [data-bulk-remove-all]').forEach(function (el) {
                    el.addEventListener('change', function () {
                        const removeAll = el.matches('[data-bulk-remove-all]');
                        if (removeAll) {
                            const entryId = el.getAttribute('data-entry-id');
                            const key = el.getAttribute('data-field-key');
                            if (entryId && key) {
                                form.querySelectorAll('[data-bulk-remove-existing][data-entry-id="' + entryId + '"][data-field-key="' + key + '"]').forEach(function (chk) {
                                    chk.checked = el.checked;
                                });
                            }
                        }
                        syncDraftFromForm(modal);
                        refreshBulkCounter(modal);
                    });
                });
                form.querySelectorAll('[data-linked-primary]').forEach(function (prim) {
                    function onLinkedPrimaryToggle() {
                        const wrap = prim.closest('[data-linked-field-group]');
                        if (!wrap) {
                            return;
                        }
                        const secWrap = wrap.querySelector('[data-linked-secondary-wrap]');
                        const sec = wrap.querySelector('[data-linked-secondary]');
                        const has = prim.value && String(prim.value).trim() !== '';
                        if (secWrap) {
                            secWrap.hidden = !has;
                        }
                        if (sec) {
                            sec.disabled = !has;
                            if (!has) {
                                sec.value = '';
                            }
                        }
                        syncDraftFromForm(modal);
                        refreshBulkCounter(modal);
                    }
                    prim.addEventListener('change', onLinkedPrimaryToggle);
                    prim.addEventListener('input', onLinkedPrimaryToggle);
                });
                const mrSel = form.querySelector('[data-tm-bulk-mr-select]');
                if (mrSel && st) {
                    mrSel.addEventListener('change', function () {
                        st.draftMicrorregion[st.selectedId] = parseInt(mrSel.value, 10);
                        setMunicipiosForForm(form, String(st.draftMicrorregion[st.selectedId] || st.entryById[st.selectedId].microrregion_id));
                        refreshBulkCounter(modal);
                    });
                }
                form.querySelectorAll('textarea.tm-bulk-textarea').forEach(function (ta) {
                    ta.addEventListener('input', function () { tmBulkFitTextareaHeight(ta); });
                    tmBulkFitTextareaHeight(ta);
                });
            }
            function selectEntry(modal, entryId) {
                const st = modal.__bulkState;
                if (!st) {
                    return;
                }
                if (st.selectedId && st.selectedId !== entryId) {
                    syncDraftFromForm(modal);
                }
                st.selectedId = entryId;
                if (!st.drafts[entryId]) {
                    st.drafts[entryId] = deepClone(st.originals[entryId]);
                }
                modal.querySelectorAll('.tm-bulk-edit-row').forEach(function (row) {
                    row.classList.toggle('is-active', parseInt(row.getAttribute('data-entry-id'), 10) === entryId);
                });
                const emptyEl = modal.querySelector('[data-tm-bulk-form-empty]');
                const form = modal.querySelector('[data-tm-bulk-form]');
                const fieldsEl = modal.querySelector('[data-tm-bulk-fields]');
                const mrWrap = modal.querySelector('[data-tm-bulk-mr-wrap]');
                const mrSel = modal.querySelector('[data-tm-bulk-mr-select]');
                if (emptyEl) {
                    emptyEl.classList.add('tm-hidden');
                }
                if (form) {
                    form.classList.remove('tm-hidden');
                }
                const entry = st.entryById[entryId];
                const previewTpl = modal.getAttribute('data-preview-url-template') || '';
                let html = '';
                st.fields.forEach(function (f) {
                    if (f.type === 'seccion') {
                        return;
                    }
                    const removedPaths = (((st.removeExisting || {})[entryId] || {})[f.key] || []);
                    const pendingCount = (((st.pendingFiles || {})[entryId] || {})[f.key] || []).length;
                    html += buildFieldHtml(f, st.drafts[entryId][f.key], entryId, previewTpl, { removedPaths: removedPaths, pendingCount: pendingCount });
                });
                if (fieldsEl) {
                    fieldsEl.innerHTML = html;
                }
                if (st.showMrSelect && mrWrap && mrSel) {
                    mrWrap.classList.remove('tm-hidden');
                    mrSel.innerHTML = '';
                    microrregionesMeta.forEach(function (m) {
                        const opt = document.createElement('option');
                        opt.value = String(m.id);
                        opt.textContent = m.label;
                        const cur = st.draftMicrorregion[entryId] !== undefined ? st.draftMicrorregion[entryId] : entry.microrregion_id;
                        opt.selected = parseInt(cur, 10) === m.id;
                        mrSel.appendChild(opt);
                    });
                } else if (mrWrap) {
                    mrWrap.classList.add('tm-hidden');
                }
                const mrForMun = st.draftMicrorregion[entryId] !== undefined ? st.draftMicrorregion[entryId] : entry.microrregion_id;
                setMunicipiosForForm(form, String(mrForMun));
                const munField = st.fields.find(function (f) { return f.type === 'municipio'; });
                if (munField && form) {
                    const ms = form.querySelector('[data-field-key="' + munField.key + '"]');
                    const mv = st.drafts[entryId][munField.key];
                    if (ms && mv != null && String(mv) !== '') {
                        ms.value = String(mv);
                    }
                }
                wireForm(modal);
                refreshBulkCounter(modal);
            }
            function appendValuesToFormData(fd, state, entryId) {
                const draft = state.drafts[entryId];
                const entry = state.entryById[entryId];
                if (!draft || !entry) {
                    return;
                }
                const mr = state.draftMicrorregion[entryId] !== undefined ? state.draftMicrorregion[entryId] : entry.microrregion_id;
                fd.append('selected_microrregion_id', String(mr));
                state.fields.forEach(function (f) {
                    if (f.type === 'seccion') {
                        return;
                    }
                    const k = f.key;
                    if (f.type === 'image' || f.type === 'file' || f.type === 'document') {
                        const removePaths = (((state.removeExisting || {})[entryId] || {})[k] || []).filter(function (path) {
                            return typeof path === 'string' && path.trim() !== '';
                        });
                        removePaths.forEach(function (path) {
                            fd.append('remove_existing_images[' + k + '][]', path);
                        });
                        const files = (((state.pendingFiles || {})[entryId] || {})[k] || []).filter(function (file) {
                            return file instanceof File;
                        });
                        files.forEach(function (file) {
                            fd.append('values[' + k + '][]', file, file.name);
                        });
                        if (removePaths.length > 0 && files.length === 0) {
                            fd.append('remove_images[' + k + ']', '1');
                        }
                        return;
                    }
                    const v = draft[k];
                    if (f.type === 'multiselect') {
                        const arr = Array.isArray(v) ? v : [];
                        arr.forEach(function (item) {
                            fd.append('values[' + k + '][]', item);
                        });
                        return;
                    }
                    if (f.type === 'linked') {
                        const obj = typeof v === 'object' && v !== null ? v : {};
                        fd.append('values[' + k + '__primary]', obj.primary != null ? String(obj.primary) : '');
                        fd.append('values[' + k + '__secondary]', obj.secondary != null ? String(obj.secondary) : '');
                        return;
                    }
                    if (f.type === 'boolean') {
                        if (v === null || v === undefined) {
                            fd.append('values[' + k + ']', '');
                        } else {
                            fd.append('values[' + k + ']', v === true || v === '1' || v === 1 ? '1' : '0');
                        }
                        return;
                    }
                    if (v === null || v === undefined) {
                        fd.append('values[' + k + ']', '');
                    } else {
                        fd.append('values[' + k + ']', typeof v === 'object' ? JSON.stringify(v) : String(v));
                    }
                });
            }
            function saveEntryRequest(modal, entryId) {
                const st = modal.__bulkState;
                if (!st || !st.drafts[entryId]) {
                    return Promise.resolve(false);
                }
                syncDraftFromForm(modal);
                const fd = new FormData();
                fd.append('_token', csrfToken);
                fd.append('entry_id', String(entryId));
                appendValuesToFormData(fd, st, entryId);
                return csrfFetch(st.submitUrl, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                }).then(function (r) {
                    if (r.status === 422) {
                        return r.json().then(function (j) {
                            throw new Error(j.message || (j.errors && Object.values(j.errors).flat().join(' ')) || 'Validación');
                        });
                    }
                    if (!r.ok) {
                        throw new Error('Error al guardar');
                    }
                    return r.json();
                }).then(function (j) {
                    if (j.success) {
                        st.originals[entryId] = deepClone(st.drafts[entryId]);
                        const ent = st.entryById[entryId];
                        if (ent && st.draftMicrorregion[entryId] !== undefined) {
                            ent.microrregion_id = st.draftMicrorregion[entryId];
                        }
                        if (st.pendingFiles[entryId]) {
                            delete st.pendingFiles[entryId];
                        }
                        if (st.removeExisting[entryId]) {
                            delete st.removeExisting[entryId];
                        }
                        delete st.draftMicrorregion[entryId];
                        refreshBulkCounter(modal);
                        if (typeof window.__tmReloadRecordsPanel === 'function') {
                            const panel = document.getElementById('module-records-' + st.moduleId);
                            if (panel) {
                                window.__tmReloadRecordsPanel(panel, { requireActive: false });
                            }
                        }
                        return true;
                    }
                    throw new Error(j.message || 'Error');
                });
            }
            function tryCloseBulkModal(modal) {
                const st = modal.__bulkState;
                const c = st ? countDirty(st) : { fieldCount: 0, entryCount: 0 };
                if (c.fieldCount === 0) {
                    closeModal(modal);
                    return;
                }
                Swal.fire({
                    title: 'Cambios sin guardar',
                    text: 'Tienes ' + c.fieldCount + ' campo(s) editado(s). ¿Qué deseas hacer?',
                    icon: 'warning',
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: 'Guardar y salir',
                    denyButtonText: 'Salir sin guardar',
                    cancelButtonText: 'Seguir editando',
                }).then(function (res) {
                    if (res.isConfirmed) {
                        saveAll(modal).then(function () {
                            closeModal(modal);
                        }).catch(function () {});
                    } else if (res.isDenied) {
                        closeModal(modal);
                    }
                });
            }
            function saveAll(modal) {
                const st = modal.__bulkState;
                if (!st) {
                    return Promise.resolve();
                }
                syncDraftFromForm(modal);
                const c = countDirty(st);
                if (c.fieldCount === 0) {
                    return Promise.resolve();
                }
                const ids = [];
                st.entries.forEach(function (e) {
                    if (entryIsDirty(st, e.id)) {
                        ids.push(e.id);
                    }
                });
                let chain = Promise.resolve();
                ids.forEach(function (id) {
                    chain = chain.then(function () {
                        return saveEntryRequest(modal, id);
                    });
                });
                return chain.then(function () {
                    Swal.fire({ title: 'Listo', text: 'Registros actualizados.', icon: 'success', toast: true, position: 'top-end', timer: 2500, showConfirmButton: false });
                }).catch(function (e) {
                    Swal.fire('Error', e.message || 'No se pudo guardar', 'error');
                    return Promise.reject(e);
                });
            }
            document.addEventListener('click', function (e) {
                const openBtn = e.target.closest('[data-tm-bulk-edit-open]');
                if (openBtn) {
                    e.preventDefault();
                    const moduleId = openBtn.getAttribute('data-module-id');
                    const panel = document.getElementById('module-records-' + moduleId);
                    const modal = document.getElementById('tmBulkEditModal-' + moduleId);
                    if (!modal || !panel) {
                        return;
                    }
                    const buscarEl = panel.querySelector('[data-tm-filter-buscar]');
                    const mrEl = panel.querySelector('[data-tm-filter-microrregion]');
                    const buscar = buscarEl ? String(buscarEl.value || '').trim() : '';
                    const mr = mrEl ? String(mrEl.value || '').trim() : '';
                    const base = modal.getAttribute('data-bulk-data-url');
                    const url = new URL(base, window.location.origin);
                    url.searchParams.set('module', moduleId);
                    if (buscar) {
                        url.searchParams.set('buscar', buscar);
                    }
                    if (mr) {
                        url.searchParams.set('microrregion_id', mr);
                    }
                    modal.querySelector('[data-tm-bulk-loading]').classList.remove('tm-hidden');
                    modal.querySelector('[data-tm-bulk-main]').classList.add('tm-hidden');
                    modal.querySelector('[data-tm-bulk-error]').classList.add('tm-hidden');
                    fetch(url.toString(), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                        .then(function (r) {
                            return r.json();
                        })
                        .then(function (j) {
                            if (!j.success) {
                                throw new Error(j.message || 'Error al cargar');
                            }
                            const st = {
                                payload: j,
                                fields: j.fields || [],
                                entries: j.entries || [],
                                originals: {},
                                drafts: {},
                                draftMicrorregion: {},
                                pendingFiles: {},
                                removeExisting: {},
                                selectedId: null,
                                entryById: {},
                                showMrSelect: modal.getAttribute('data-show-mr-select') === '1',
                                submitUrl: modal.getAttribute('data-submit-url'),
                                moduleId: parseInt(modal.getAttribute('data-module-id'), 10),
                            };
                            j.entries.forEach(function (e) {
                                st.originals[e.id] = deepClone(e.data);
                                st.entryById[e.id] = e;
                            });
                            modal.__bulkState = st;
                            const listEl = modal.querySelector('[data-tm-bulk-list]');
                            const nameEl = modal.querySelector('.tm-bulk-edit-module-name');
                            if (nameEl) {
                                nameEl.textContent = j.module_name || '';
                            }
                            if (listEl) {
                                if (!st.entries.length) {
                                    listEl.innerHTML = '<p class="tm-muted">No hay registros con los filtros actuales.</p>';
                                } else {
                                    listEl.innerHTML = st.entries.map(function (e) {
                                        return '<div class="tm-bulk-edit-row" data-entry-id="' + e.id + '">' +
                                            '<button type="button" class="tm-bulk-edit-row-main" data-tm-bulk-select-entry="' + e.id + '">' +
                                            '<span class="tm-bulk-edit-row-title">' + tmBulkEsc(e.title) + '</span>' +
                                            '<span class="tm-bulk-edit-row-meta">' + tmBulkEsc(e.microrregion_label) + '</span></button>' +
                                            '<span class="tm-bulk-edit-row-badge tm-hidden" data-tm-bulk-dirty-badge data-entry-id="' + e.id + '">Pendiente</span>' +
                                            '<button type="button" class="tm-btn tm-btn-sm tm-btn-primary tm-hidden" data-tm-bulk-row-save data-entry-id="' + e.id + '">Guardar</button>' +
                                            '</div>';
                                    }).join('');
                                }
                            }
                            modal.querySelector('[data-tm-bulk-loading]').classList.add('tm-hidden');
                            modal.querySelector('[data-tm-bulk-main]').classList.remove('tm-hidden');
                            modal.querySelector('[data-tm-bulk-form]')?.classList.add('tm-hidden');
                            modal.querySelector('[data-tm-bulk-form-empty]')?.classList.remove('tm-hidden');
                            refreshBulkCounter(modal);
                            openModal(modal, openBtn);
                        })
                        .catch(function (err) {
                            modal.querySelector('[data-tm-bulk-loading]').classList.add('tm-hidden');
                            const errEl = modal.querySelector('[data-tm-bulk-error]');
                            if (errEl) {
                                errEl.textContent = err.message || 'Error';
                                errEl.classList.remove('tm-hidden');
                            }
                            openModal(modal, openBtn);
                        });
                    return;
                }
                const sel = e.target.closest('[data-tm-bulk-select-entry]');
                if (sel) {
                    const row = sel.closest('.tm-bulk-edit-row');
                    const modal = sel.closest('.tm-bulk-edit-modal');
                    if (!row || !modal || !modal.__bulkState) {
                        return;
                    }
                    selectEntry(modal, parseInt(row.getAttribute('data-entry-id'), 10));
                    return;
                }
                const rowSave = e.target.closest('[data-tm-bulk-row-save]');
                if (rowSave) {
                    e.preventDefault();
                    e.stopPropagation();
                    const modal = rowSave.closest('.tm-bulk-edit-modal');
                    const id = parseInt(rowSave.getAttribute('data-entry-id'), 10);
                    if (modal && modal.__bulkState) {
                        if (modal.__bulkState.selectedId !== id) {
                            selectEntry(modal, id);
                        }
                        syncDraftFromForm(modal);
                        saveEntryRequest(modal, id).then(function (ok) {
                            if (ok) {
                                Swal.fire({ title: 'Guardado', text: 'Registro actualizado.', icon: 'success', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
                            }
                        }).catch(function (err) {
                            Swal.fire('Error', err.message, 'error');
                        });
                    }
                    return;
                }
                const saveOne = e.target.closest('[data-tm-bulk-save-one]');
                if (saveOne) {
                    const modal = saveOne.closest('.tm-bulk-edit-modal');
                    const st = modal && modal.__bulkState;
                    if (st && st.selectedId) {
                        syncDraftFromForm(modal);
                        saveEntryRequest(modal, st.selectedId).then(function (ok) {
                            if (ok) {
                                Swal.fire({ title: 'Guardado', text: 'Registro actualizado.', icon: 'success', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
                            }
                        }).catch(function (err) {
                            Swal.fire('Error', err.message, 'error');
                        });
                    }
                    return;
                }
                const saveAllBtn = e.target.closest('[data-tm-bulk-save-all]');
                if (saveAllBtn) {
                    const modal = saveAllBtn.closest('.tm-bulk-edit-modal');
                    if (modal) {
                        saveAll(modal);
                    }
                    return;
                }
                const saveExit = e.target.closest('[data-tm-bulk-save-exit]');
                if (saveExit) {
                    const modal = saveExit.closest('.tm-bulk-edit-modal');
                    if (modal) {
                        saveAll(modal).then(function () {
                            closeModal(modal);
                        }).catch(function () {});
                    }
                    return;
                }
                const dismiss = e.target.closest('[data-tm-bulk-edit-dismiss], [data-tm-bulk-dismiss]');
                if (dismiss) {
                    const modal = dismiss.closest('.tm-bulk-edit-modal');
                    if (modal) {
                        tryCloseBulkModal(modal);
                    }
                }
            });
        })();

        // --- Trigger initial load for the active module panel if we are in records view ---
        if (recordsViewPanel && recordsViewPanel.classList.contains('is-active')) {
            const activeChip = document.querySelector('[data-tm-module-chips-track] .tm-module-chip.is-active');
            if (activeChip) {
                const targetId = activeChip.getAttribute('data-module-target');
                const panel = targetId ? document.getElementById(targetId) : null;
                const host = panel ? panel.querySelector('.tm-records-fragment-host') : null;
                if (host && !host.querySelector('.tm-records-fragment-inner')) {
                    activeChip.click();
                }
            }
        }
    });

    // Delegated click handler for suggestion buttons — single-field mode (immediate send)
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.tm-idx-sugg-btn');
        if (!btn) return;
        const ds = btn.dataset;
        const mrId = ds.mr ? Number(ds.mr) : null;
        retryImportRow(Number(ds.idx), mrId, ds.val, ds.surl || null, ds.mid, ds.cid, btn, Number(ds.row), ds.key || null);
    });

    // Delegated click handler for staging buttons — multi-field mode (stage locally)
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.tm-idx-stage-btn');
        if (!btn) return;
        const ds = btn.dataset;
        const card = document.getElementById(ds.cid);
        if (!card) return;

        // Deseleccionar otros botones del mismo campo
        card.querySelectorAll(`.tm-idx-stage-btn[data-key="${CSS.escape(ds.key)}"]`).forEach(b => {
            b.style.background = '';
            b.style.color = '';
            b.style.borderColor = '';
        });
        // Marcar este como seleccionado
        btn.style.background = 'var(--clr-secondary, #2e7d32)';
        btn.style.color = '#fff';
        btn.style.borderColor = 'var(--clr-secondary, #2e7d32)';

        // Guardar corrección en el dataset de la tarjeta
        const staged = JSON.parse(card.dataset.stagedCorrections || '{}');
        staged[ds.key] = { val: ds.val, mr: ds.mr || null };
        card.dataset.stagedCorrections = JSON.stringify(staged);

        // Mostrar botón "Guardar correcciones"
        const saveBtn = card.querySelector('.tm-idx-save-staged-btn');
        if (saveBtn) saveBtn.style.display = '';
    });

    // Delegated click handler for "Guardar correcciones" button
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.tm-idx-save-staged-btn');
        if (!btn) return;
        const ds = btn.dataset;
        const card = document.getElementById(ds.cid);
        if (!card) return;

        const staged = JSON.parse(card.dataset.stagedCorrections || '{}');
        if (Object.keys(staged).length === 0) return;

        const rowData = JSON.parse(card.dataset.rowData);
        let mrId = null;
        for (const [key, corr] of Object.entries(staged)) {
            const wasArray = Array.isArray(rowData[key]);
            rowData[key] = wasArray ? [corr.val] : corr.val;
            if (corr.mr) mrId = Number(corr.mr);
        }

        retryImportRow(Number(ds.idx), mrId, null, ds.surl || null, ds.mid, ds.cid, btn, Number(ds.row), null, rowData);
    });

    window.retryImportRow = function(errIdx, microrregionId, correctedValue, singleUrl, moduleId, cardId, buttonEl, rowNumber, specificFieldKey = null, prebuiltRowData = null) {
        const card = document.getElementById(cardId || ('tmErrRow_' + errIdx));
        if (!card) return;

        let rowData;
        if (prebuiltRowData) {
            rowData = prebuiltRowData;
        } else {
            rowData = JSON.parse(card.dataset.rowData);
            const fieldKey = specificFieldKey || String(card.dataset.municipioKey || 'municipio');
            // Si el valor original era un array (multiselect), envolver la corrección en array
            const wasArray = Array.isArray(rowData[fieldKey]);
            rowData[fieldKey] = wasArray ? [correctedValue] : correctedValue;
        }
        // Limpiar correcciones staged al enviar
        delete card.dataset.stagedCorrections;

        const btn = buttonEl || null;
        if (!btn) {
            return;
        }
        const oldText = btn.innerText;
        btn.disabled = true;
        btn.innerText = 'Cargando...';

        csrfFetch(singleUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                data: rowData,
                microrregion_id: tmResolveMicrorregionForRetry(card, moduleId, microrregionId),
            })
        })
        .then(r => safeJsonParse(r))
        .then(j => {
            if (j.success) {
                btn.innerText = '✓ Importado';
                btn.style.background = 'var(--clr-secondary)';
                btn.style.color = '#fff';

                // Animación de salida y remoción
                card.style.transition = 'all 0.4s ease';
                card.style.opacity = '0';
                card.style.transform = 'translateX(20px)';

                setTimeout(() => {
                    card.remove();
                    // Si el contenedor se queda vacío, ocultar sección o poner mensaje
                    const list = card.parentElement;
                    if (list && list.children.length === 0) {
                        const sec = list.closest('.tm-excel-errors-section');
                        if (sec) sec.classList.add('tm-hidden');
                    }
                }, 400);

                // Si se pasó moduleId y rowNumber, remover este error de la sesión usando el número de fila
                if (moduleId && rowNumber) {
                    const data = sessionStorage.getItem(`tm_errors_${moduleId}`);
                    if (data) {
                        const parsed = JSON.parse(data);
                        parsed.errors = (parsed.errors || []).filter((e) => e.row !== rowNumber);
                        if (typeof window.__tmSaveImportErrors === 'function') {
                            window.__tmSaveImportErrors(moduleId, parsed.errors, parsed.singleUrl);
                        }
                    }
                }

                if (typeof window.__tmRefreshImportErrorsModal === 'function') {
                    window.__tmRefreshImportErrorsModal();
                }

                // Acumular en el contador del modal de Excel abierto
                var openExcelModal = document.querySelector('.tm-excel-import-modal.is-open');
                if (openExcelModal) {
                    openExcelModal.__excelImportedCount = (openExcelModal.__excelImportedCount || 0) + 1;
                }
            } else if (j.error && j.error.failed_fields && j.error.failed_fields.length > 0) {
                // El servidor devolvió errores restantes con sugerencias — actualizar la tarjeta
                btn.disabled = false;
                btn.innerText = oldText;
                j.error.row = rowNumber || j.error.row || 'Manual';
                card.dataset.rowData = JSON.stringify(j.error.data || rowData);
                if (j.error.municipio_key) card.dataset.municipioKey = j.error.municipio_key;
                if (j.error.data_urls) card.dataset.dataUrls = JSON.stringify(j.error.data_urls);
                card.innerHTML = renderErrorCardHtml(j.error, errIdx, moduleId, singleUrl, cardId, false);
            } else {
                throw new Error(j.message || 'Error al reintentar');
            }
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerText = oldText;
            Swal.fire('Error', e.message, 'error');
        });
    };

    window.openErrorModifyModal = function(cardId) {
        const card = document.getElementById(cardId);
        if (!card) return;

        // Toggle: si ya existe formulario inline, quitarlo
        var existingForm = card.querySelector('.tm-inline-edit-form');
        if (existingForm) { existingForm.remove(); return; }

        const rowData = JSON.parse(card.dataset.rowData || '{}');
        const dataUrls = JSON.parse(card.dataset.dataUrls || '{}');
        // Soportar ambos formatos: tmErrRow y tmErrLogRow
        const isLogCard = cardId.startsWith('tmErrLogRow_');
        const moduleId = card.dataset.moduleId || cardId.split('_')[1];

        // URL de importación individual
        const exModal = document.getElementById('tmImportarExcelModal-' + moduleId);
        const singleUrl = card.dataset.singleUrl || (exModal ? exModal.dataset.excelImportSingleUrl : ('/modulos-temporales/' + moduleId + '/importar-fila'));

        // Microrregión: la del error de importación (misma MR que al importar) o la del modal Excel
        const mrFromErr = card.dataset.microrregionId ? String(card.dataset.microrregionId).trim() : '';
        const mrInput = document.querySelector('#tmImportarExcelModal-' + moduleId + ' .tm-excel-mr-input')
            || (exModal ? exModal.querySelector('.tm-excel-mr-input') : null);
        const mrId = mrFromErr !== '' ? mrFromErr : (mrInput && mrInput.value !== '' ? mrInput.value : '');

        // Formulario original para obtener labels y selects
        const entryModal = document.getElementById('delegate-preview-' + moduleId);
        const origForm = entryModal ? entryModal.querySelector('.tm-entry-form') : null;

        const conflictData = JSON.parse(card.dataset.conflictData || '{}');
        const hasConflict = Object.keys(conflictData).length > 0;

        // Clonar campos reales del formulario original
        var formDiv = document.createElement('div');
        formDiv.className = 'tm-inline-edit-form';
        formDiv.style.cssText = 'margin-top:12px; padding:12px; border:1px dashed var(--clr-border); border-radius:10px; background:rgba(0,0,0,0.02);';

        var headerHtml = '';
        if (hasConflict) {
            // Renderizar preview del registro original (conflictivo)
            var conflictFieldsHtml = '';
            if (origForm) {
                origForm.querySelectorAll('.tm-entry-grid .tm-entry-field').forEach(function(fieldLabel) {
                    var input = fieldLabel.querySelector('input, select, textarea');
                    if (!input) return;
                    var nameAttr = input.getAttribute('name') || '';
                    var match = nameAttr.match(/^values\[([^\]]+)\]/);
                    if (!match) return;
                    var key = match[1];
                    if (key.includes('__')) key = key.split('__')[0];

                    var val = conflictData[key];
                    if (val === undefined || val === null) return;

                    var displayVal = '';
                    var isImg = false;
                    if (typeof val === 'string' && val.startsWith('data:image/')) {
                        displayVal = '<img src="' + val + '" style="max-height:50px; border-radius:4px; border:1px solid var(--clr-border);">';
                        isImg = true;
                    } else if (input.tagName === 'SELECT') {
                        var opt = Array.from(input.options).find(o => String(o.value) === String(val));
                        displayVal = opt ? opt.textContent : val;
                    } else if (input.type === 'checkbox') {
                        displayVal = Array.isArray(val) ? val.join(', ') : val;
                    } else {
                        displayVal = val;
                    }

                    var labelText = fieldLabel.childNodes[0] ? fieldLabel.childNodes[0].textContent.trim() : key;
                    conflictFieldsHtml += '<div style="margin-bottom:6px;">' +
                        '<div style="font-size:0.65rem; font-weight:700; color:var(--clr-text-light); text-transform:uppercase; opacity:0.8;">' + escapeHtml(labelText) + '</div>' +
                        '<div style="font-size:0.75rem; color:var(--clr-text-main); font-weight:500;">' + (isImg ? displayVal : escapeHtml(displayVal)) + '</div>' +
                        '</div>';
                });
            }

            headerHtml += '<div style="margin-bottom:15px; padding:10px; background:rgba(124, 77, 255, 0.08); border:1px solid rgba(124, 77, 255, 0.2); border-radius:8px;">' +
                '<div style="font-size:0.75rem; font-weight:800; color:#7c4dff; margin-bottom:8px; display:flex; align-items:center; gap:6px;">' +
                '<i class="fa-solid fa-circle-exclamation"></i> REGISTRO ORIGINAL (YA EXISTENTE)' +
                '</div>' +
                '<div style="display:grid; grid-template-columns:1fr 1fr; gap:6px 12px;">' + conflictFieldsHtml + '</div>' +
                '</div>';
        }

        headerHtml += '<div style="font-size:0.75rem; font-weight:700; color:var(--clr-error-text); margin-bottom:10px;">' +
            '<i class="fa-solid fa-pen-to-square"></i> EDITAR DATOS PARA REIMPORTAR' +
            '</div>';

        var gridDiv = document.createElement('div');
        gridDiv.style.cssText = 'display:grid; grid-template-columns:1fr 1fr; gap:6px 12px;';

        if (origForm) {
            // Clonar la grilla de campos del formulario original
            var origGrid = origForm.querySelector('.tm-entry-grid');
            if (origGrid) {
                var clonedGrid = origGrid.cloneNode(true);
                // Quitar microrregion selector del clon (se envía aparte)
                var mrSel = clonedGrid.querySelector('.tm-mr-selector');
                if (mrSel) {
                    var mrLabel = mrSel.closest('.tm-entry-field');
                    if (mrLabel) mrLabel.remove();
                }
                // Quitar secciones header decorativas
                clonedGrid.querySelectorAll('.tm-entry-section-header, .tm-form-divider').forEach(function(el) { el.remove(); });

                // Quitar campo municipio del clon (se corrige con las sugerencias de arriba)
                var munSel = clonedGrid.querySelector('.tm-municipio-select');
                if (munSel) {
                    var munLabel = munSel.closest('.tm-entry-field');
                    if (munLabel) munLabel.remove();
                }

                // Limpiar todas las previsualizaciones de imagen clonadas para evitar requests a URLs de otros registros
                clonedGrid.querySelectorAll('[data-image-preview]').forEach(function(pv) {
                    pv.hidden = true;
                    var img = pv.querySelector('img');
                    if (img) img.removeAttribute('src');
                });

                // Aplicar valores del rowData a cada campo clonado
                clonedGrid.querySelectorAll('.tm-entry-field').forEach(function(fieldLabel) {
                    // Buscar inputs, selects, textareas dentro
                    fieldLabel.querySelectorAll('input, select, textarea').forEach(function(input) {
                        var nameAttr = input.getAttribute('name') || '';
                        // Extraer key de values[key] o values[key__primary]
                        var match = nameAttr.match(/^values\[([^\]]+)\]/);
                        if (!match) return;
                        var key = match[1];
                        var subKey = null;
                        if (key.includes('__')) {
                            var parts = key.split('__');
                            key = parts[0];
                            subKey = parts[1];
                        }

                        var val = rowData[key];
                        if (subKey && typeof val === 'object' && val !== null) {
                            val = val[subKey];
                        }

                        // Limpiar IDs del clon para evitar duplicados
                        if (input.id) input.id = input.id + '_inline_' + cardId;

                        // Quitar required del clon
                        input.removeAttribute('required');

                        if (input.type === 'file') {
                            // Imagen: mostrar preview si tenemos data
                            var imgVal = typeof rowData[key] === 'string' ? rowData[key] : '';
                            var dUrl = dataUrls[key];
                            if (dUrl || (imgVal && (imgVal.startsWith('temporary-modules/') || imgVal.startsWith('data:')))) {
                                var previewDiv = fieldLabel.querySelector('[data-image-preview]');
                                var previewImg = previewDiv ? previewDiv.querySelector('img') : null;
                                if (previewImg && previewDiv) {
                                    previewImg.src = dUrl || imgVal;
                                    previewDiv.hidden = false;
                                }
                            }
                            // Agregar hidden para mantener el valor
                            var hiddenImg = document.createElement('input');
                            hiddenImg.type = 'hidden';
                            hiddenImg.setAttribute('data-field-key', key);
                            hiddenImg.value = imgVal;
                            input.parentElement.appendChild(hiddenImg);
                            return;
                        }

                        if (input.type === 'checkbox') {
                            // Multiselect: marcar según array
                            var arrVal = Array.isArray(rowData[key]) ? rowData[key] : (typeof rowData[key] === 'string' ? rowData[key].split(',').map(function(s) { return s.trim(); }) : []);
                            input.checked = arrVal.indexOf(input.value) !== -1;
                            input.setAttribute('data-field-key-multi', key);
                            return;
                        }

                        if (input.type === 'radio') {
                            input.checked = String(input.value) === String(val || '');
                            input.setAttribute('data-field-key', key);
                            return;
                        }

                        if (input.tagName === 'SELECT') {
                            input.setAttribute('data-field-key', subKey ? (key + '__' + subKey) : key);
                            var strVal = (val !== null && val !== undefined) ? String(val) : '';
                            // Intentar seleccionar
                            var found = false;
                            for (var oi = 0; oi < input.options.length; oi++) {
                                if (input.options[oi].value === strVal) {
                                    input.selectedIndex = oi;
                                    found = true;
                                    break;
                                }
                            }
                            if (!found) input.selectedIndex = 0;
                            return;
                        }

                        // Text, number, date, textarea
                        input.setAttribute('data-field-key', subKey ? (key + '__' + subKey) : key);
                        input.value = (val !== null && val !== undefined) ? String(val) : '';
                    });
                });

                gridDiv = clonedGrid;
                gridDiv.style.cssText = 'display:grid; grid-template-columns:1fr 1fr; gap:6px 12px;';
            }
        } else {
            // Fallback: generar inputs simples si no hay formulario original
            var fieldsHtml = '';
            for (var fkey in rowData) {
                if (!rowData.hasOwnProperty(fkey)) continue;
                var val = rowData[fkey];
                var displayVal = (val !== null && val !== undefined) ? String(val) : '';
                fieldsHtml += '<div style="margin-bottom:8px;">' +
                    '<label style="font-size:0.7rem; font-weight:700; color:var(--clr-text-light); text-transform:uppercase; letter-spacing:0.5px;">' + escapeHtml(fkey) + '</label>' +
                    '<input type="text" data-field-key="' + escapeHtml(fkey) + '" value="' + escapeHtml(displayVal) + '"' +
                    ' style="width:100%; padding:5px 8px; border:1px solid var(--clr-border); border-radius:6px; background:var(--clr-bg); color:var(--clr-text-main); font-size:0.8rem; margin-top:4px;">' +
                    '</div>';
            }
            gridDiv.innerHTML = fieldsHtml;
        }

        formDiv.innerHTML = headerHtml;
        formDiv.appendChild(gridDiv);

        var btnsDiv = document.createElement('div');
        btnsDiv.style.cssText = 'margin-top:10px; display:flex; gap:8px;';
        btnsDiv.innerHTML = '<button type="button" class="tm-btn tm-btn-sm tm-btn-primary tm-inline-save-btn" style="padding:5px 14px; font-size:0.75rem;"><i class="fa-solid fa-check"></i> Guardar corregido</button>' +
            '<button type="button" class="tm-btn tm-btn-sm tm-btn-outline tm-inline-cancel-btn" style="padding:5px 14px; font-size:0.75rem;">Cancelar</button>';
        formDiv.appendChild(btnsDiv);
        card.appendChild(formDiv);

        // Prevenir que el click del formDiv haga scroll en la tabla
        formDiv.addEventListener('click', function(e) { e.stopPropagation(); });

        // Cancelar
        formDiv.querySelector('.tm-inline-cancel-btn').addEventListener('click', function() { formDiv.remove(); });

        // Guardar
        formDiv.querySelector('.tm-inline-save-btn').addEventListener('click', async function() {
            var btn = this;
            var oldT = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Guardando...';

            // Partir de rowData: el clon del formulario suele quitar municipio/microrregión;
            // si no fusionamos, el POST llega sin municipio y falla la resolución de MR.
            var data = Object.assign({}, rowData);
            // Campos simples (text, select, hidden, etc.) — sobrescriben rowData
            formDiv.querySelectorAll('[data-field-key]').forEach(function(el) {
                var k = el.dataset.fieldKey;
                if (k.includes('__')) {
                    var parts = k.split('__');
                    if (!data[parts[0]]) data[parts[0]] = {};
                    data[parts[0]][parts[1]] = el.value;
                } else {
                    data[k] = el.value;
                }
            });
            // Campos multiselect (checkboxes)
            var multiKeys = {};
            formDiv.querySelectorAll('[data-field-key-multi]').forEach(function(cb) {
                var k = cb.dataset.fieldKeyMulti;
                if (!multiKeys[k]) multiKeys[k] = [];
                if (cb.checked) multiKeys[k].push(cb.value);
            });
            for (var mk in multiKeys) {
                if (multiKeys.hasOwnProperty(mk)) data[mk] = multiKeys[mk];
            }

            try {
                var r = await csrfFetch(singleUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify({ data: data, microrregion_id: tmResolveMicrorregionForRetry(card, moduleId, mrId) })
                });
                var j = await safeJsonParse(r);
                if (j.success) {
                    Swal.fire({ title: 'Éxito', text: 'Registro corregido e importado.', icon: 'success', toast: true, position: 'top-end', timer: 3000, showConfirmButton: false });
                    // Si es tarjeta del modal de log, eliminar del sessionStorage
                    if (isLogCard && card.dataset.errorIndex !== undefined) {
                        var eidx = parseInt(card.dataset.errorIndex, 10);
                        if (typeof deleteErrorFromSession === 'function') {
                            deleteErrorFromSession(moduleId, eidx);
                        }
                        if (typeof updateErrorIndicator === 'function') updateErrorIndicator(moduleId);
                        if (typeof updateHeaderErrorIndicator === 'function') updateHeaderErrorIndicator();
                    }
                    card.style.transition = 'all 0.4s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(20px)';
                    setTimeout(function() {
                        var list = card.parentElement;
                        card.remove();
                        if (list && list.children.length === 0) {
                            var sec = list.closest('.tm-excel-errors-section');
                            if (sec) sec.classList.add('tm-hidden');
                        }
                    }, 400);
                } else if (j.error) {
                    // Si el backend devuelve un nuevo objeto de error (por ejemplo, aún falta el municipio)
                    var errDetail = j.message || '';
                    if (j.error.failed_fields && j.error.failed_fields[0]) {
                        var ff = j.error.failed_fields[0];
                        errDetail = [ff.label, ff.reason, ff.received ? '(valor: ' + ff.received + ')' : ''].filter(Boolean).join(' ');
                    }
                    Swal.fire({ title: 'Atención', text: 'Revisa el registro: ' + errDetail, icon: 'warning', toast: true, position: 'top-end', timer: 5000 });

                    // Actualizar dataset con los nuevos datos (incluyendo data_urls para imágenes)
                    card.dataset.rowData = JSON.stringify(j.error.data || data);
                    if (j.error.data_urls) card.dataset.dataUrls = JSON.stringify(j.error.data_urls);
                    if (j.error.conflict_data) card.dataset.conflictData = JSON.stringify(j.error.conflict_data);

                    // Re-renderizar el interior de la tarjeta
                    card.innerHTML = renderErrorCardHtml(j.error, card.dataset.errorIndex || 0, moduleId, singleUrl, cardId, isLogCard);

                    // AUTO-REABRIR el formulario de edición (Sequential Correction)
                    setTimeout(() => {
                        window.openErrorModifyModal(cardId);
                    }, 500);
                } else if (j.errors && typeof j.errors === 'object') {
                    var flatErr = Object.values(j.errors).flat();
                    Swal.fire({ title: 'Error de validación', text: (flatErr[0] || j.message || 'Revisa los datos.'), icon: 'error' });
                } else if (j.success === false && j.message) {
                    Swal.fire({ title: 'No se pudo guardar', text: j.message, icon: 'warning' });
                } else {
                    throw new Error(j.message || 'Error al guardar');
                }
            } catch (err) {
                Swal.fire('Error', err.message, 'error');
            } finally {
                if (btn && btn.parentNode) {
                    btn.disabled = false;
                    btn.innerHTML = oldT;
                }
            }
        });
    };
</script>

<div id="tmGlobalExcelDropOverlay" class="tm-global-drop-overlay">
    <div class="tm-global-drop-content">
        <i class="fa-solid fa-file-arrow-up"></i>
        <h3>¡Suelta tu archivo aquí!</h3>
        <p>Arrastra tu archivo Excel o PDF a cualquier área dentro de la página para comenzar la importación.</p>
    </div>
</div>

@endpush
