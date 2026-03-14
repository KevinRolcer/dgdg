@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
@endpush

@section('content')
<section class="tm-page">
    @if (session('status'))
        <div class="inline-alert inline-alert-success" role="alert">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="inline-alert inline-alert-error" role="alert">{{ $errors->first() }}</div>
    @endif

    @php
        $activeSection = $activeSection ?? 'upload';
        $isUploadSection = $activeSection !== 'records';
        $microsAsignadas = ($microrregionesAsignadas ?? collect())->values();
        $mostrarSelectorMicrorregion = $microsAsignadas->count() > 1;
    @endphp

    <div class="tm-section-switch" role="tablist" aria-label="Cambiar vista de captura temporal">
        <button type="button" class="tm-section-tab {{ $isUploadSection ? 'is-active' : '' }}" data-section-tab data-section-target="tmUploadView" role="tab" aria-selected="{{ $isUploadSection ? 'true' : 'false' }}">Subir informacion</button>
        <button type="button" class="tm-section-tab {{ !$isUploadSection ? 'is-active' : '' }}" data-section-tab data-section-target="tmRecordsView" role="tab" aria-selected="{{ !$isUploadSection ? 'true' : 'false' }}">Ver mis registros</button>
    </div>

    <section class="tm-section-panel {{ $isUploadSection ? 'is-active' : '' }}" id="tmUploadView" role="tabpanel" aria-hidden="{{ $isUploadSection ? 'false' : 'true' }}">
        @include('temporary_modules.delegate.partials.upload_modules', ['modules' => $modules, 'fragmentUploadUrl' => $fragmentUploadUrl ?? '#'])
    </section>

    @foreach ($modules as $module)
        <div class="tm-modal" id="delegate-preview-{{ $module->id }}" aria-hidden="true" role="dialog" aria-modal="true">
            <div class="tm-modal-backdrop" data-close-module-preview></div>
            <div class="tm-modal-dialog tm-modal-dialog-entry">
                <div class="tm-modal-head">
                    <div class="tm-modal-head-stack">
                        <h3>Registro de modulo temporal</h3>
                        <p class="tm-modal-subtitle">{{ $module->name }}</p>
                    </div>
                    <button type="button" class="tm-modal-close" data-close-module-preview aria-label="Cerrar">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
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

        @foreach ($module->getRelation('myEntries') as $entry)
            <div class="tm-modal" id="delegate-edit-{{ $entry->id }}" aria-hidden="true" role="dialog" aria-modal="true">
                <div class="tm-modal-backdrop" data-close-module-preview></div>
                <div class="tm-modal-dialog tm-modal-dialog-entry">
                    <div class="tm-modal-head">
                        <div class="tm-modal-head-stack">
                            <h3>Editar registro</h3>
                            <p class="tm-modal-subtitle">{{ $module->name }}</p>
                        </div>
                        <button type="button" class="tm-modal-close" data-close-module-preview aria-label="Cerrar">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
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
                                                @foreach ($municipios as $municipio)
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
        <article class="content-card tm-card tm-records-container">
            <h2>Mis registros</h2>

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
        <article class="content-card tm-card">
            <p>No hay modulos temporales para mostrar registros.</p>
        </article>
    @endif
    </section>

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
<script>
    document.addEventListener('DOMContentLoaded', function () {
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
            if (typeof window.swal === 'function') {
                window.swal(title, message, type);
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

        moduleFilterButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const targetId = button.getAttribute('data-module-target') || '';
                activateModulePanel(targetId);
                const panel = targetId ? document.getElementById(targetId) : null;
                if (!panel || !fragmentRecordsBase) {
                    return;
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
                loadRecordsFragment(host, moduleId, 'module=' + encodeURIComponent(moduleId) + '&entries_page=1').then(function () {
                    if (placeholder) {
                        placeholder.hidden = true;
                    }
                });
            });
        });

        if (recordsViewPanel && fragmentRecordsBase) {
            recordsViewPanel.addEventListener('click', function (event) {
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
                const qs = 'module=' + encodeURIComponent(moduleId) + '&entries_page=' + encodeURIComponent(entriesPage);
                loadRecordsFragment(host, moduleId, qs);
            });
        }

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
