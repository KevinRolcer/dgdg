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
        <button type="button" class="tm-section-tab {{ $isUploadSection ? 'is-active' : '' }}" data-section-tab data-section-target="tmUploadView" role="tab" aria-selected="{{ $isUploadSection ? 'true' : 'false' }}">Subir información</button>
        <button type="button" class="tm-section-tab {{ !$isUploadSection ? 'is-active' : '' }}" data-section-tab data-section-target="tmRecordsView" role="tab" aria-selected="{{ !$isUploadSection ? 'true' : 'false' }}">Ver mis registros</button>
    </div>

    <section class="tm-section-panel {{ $isUploadSection ? 'is-active' : '' }}" id="tmUploadView" role="tabpanel" aria-hidden="{{ $isUploadSection ? 'false' : 'true' }}">
        <div class="tm-module-grid">
            @forelse ($modules as $module)
                <article class="content-card tm-card tm-module-card tm-upload-card">
                    <div class="tm-upload-card-head">
                        <h2>{{ $module->name }}</h2>
                        <p>{{ $module->description ?: 'Sin descripción adicional.' }}</p>
                    </div>

                    <div class="tm-upload-meta-row">
                        <span class="tm-upload-meta-pill"><strong>Vence:</strong> {{ optional($module->expires_at)->format('d/m/Y H:i') ?? 'Sin límite' }}</span>
                        <span class="tm-upload-meta-pill"><strong>Mis registros:</strong> {{ $module->my_entries_count }}</span>
                    </div>

                    <div class="tm-upload-card-foot">
                        <button
                            type="button"
                            class="tm-btn tm-btn-primary"
                            data-open-module-preview="delegate-preview-{{ $module->id }}"
                        >
                            Registrar información
                        </button>
                    </div>
                </article>
            @empty
                <article class="content-card tm-card">
                    <p>No hay módulos temporales activos en este momento.</p>
                </article>
            @endforelse
        </div>
    </section>

    @foreach ($modules as $module)
        <div class="tm-modal" id="delegate-preview-{{ $module->id }}" aria-hidden="true" role="dialog" aria-modal="true">
            <div class="tm-modal-backdrop" data-close-module-preview></div>
            <div class="tm-modal-dialog tm-modal-dialog-entry">
                <div class="tm-modal-head">
                    <div class="tm-modal-head-stack">
                        <h3>Registro de módulo temporal</h3>
                        <p class="tm-modal-subtitle">{{ $module->name }}</p>
                    </div>
                    <button type="button" class="tm-modal-close" data-close-module-preview>&times;</button>
                </div>

                <div class="tm-modal-body">
                    <p class="tm-entry-subtitle">{{ $module->description ?: 'Completa los datos requeridos para este módulo.' }}</p>

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
                        @if ($mostrarSelectorMicrorregion)
                            <label class="tm-col-full tm-entry-field">
                                Microrregión de captura *
                                <select name="selected_microrregion_id" class="tm-mr-selector" required>
                                    @foreach ($microsAsignadas as $micro)
                                        <option value="{{ $micro->id }}" @selected($loop->first)>
                                            MR {{ $micro->microrregion }} — {{ $micro->cabecera }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @elseif ($microsAsignadas->isNotEmpty())
                            <input type="hidden" name="selected_microrregion_id" value="{{ $microsAsignadas->first()->id }}">
                        @endif

                        <div class="tm-grid tm-grid-2 tm-entry-grid">
                            <input type="hidden" name="entry_id" value="">
                            @foreach ($orderedFields as $field)
                                @php
                                    $name = 'values['.$field->key.']';
                                    $id = 'field_'.$module->id.'_'.$field->key;
                                    $value = old('values.'.$field->key, $savedData[$field->key] ?? null);
                                    $isMediaField = in_array($field->type, ['image', 'file'], true);
                                    $isLong = $field->type === 'textarea';
                                @endphp
                                @if ($isMediaField && !$mediaDividerPrinted)
                                    <div class="tm-form-divider tm-col-full">
                                        <span>Evidencias</span>
                                    </div>
                                    @php $mediaDividerPrinted = true; @endphp
                                @endif

                                <label class="{{ $isLong ? 'tm-col-full' : '' }} tm-entry-field {{ $isMediaField ? 'is-media' : '' }}">
                                    {{ $field->label }} {{ $field->is_required ? '*' : '' }}
                                    @if (!empty($field->comment))
                                        <small class="tm-field-help">{{ $field->comment }}</small>
                                    @endif

                                    @if ($field->type === 'textarea')
                                        <textarea id="{{ $id }}" name="{{ $name }}" rows="3" {{ $field->is_required ? 'required' : '' }}>{{ $value }}</textarea>
                                    @elseif ($field->type === 'number')
                                        <input id="{{ $id }}" type="number" step="any" name="{{ $name }}" value="{{ $value }}" {{ $field->is_required ? 'required' : '' }}>
                                    @elseif ($field->type === 'date')
                                        <input id="{{ $id }}" type="date" name="{{ $name }}" value="{{ $value }}" {{ $field->is_required ? 'required' : '' }}>
                                    @elseif ($field->type === 'datetime')
                                        <input id="{{ $id }}" type="datetime-local" name="{{ $name }}" value="{{ $value }}" {{ $field->is_required ? 'required' : '' }}>
                                    @elseif ($field->type === 'select')
                                        <select id="{{ $id }}" name="{{ $name }}" {{ $field->is_required ? 'required' : '' }}>
                                            <option value="">Selecciona una opción</option>
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
                                            <option value="1" @selected($value === '1')>Sí</option>
                                            <option value="0" @selected($value === '0')>No</option>
                                        </select>
                                    @elseif (in_array($field->type, ['image', 'file'], true))
                                        <input id="{{ $id }}" type="file" accept="image/*" name="{{ $name }}" {{ $field->is_required ? 'required' : '' }}>
                                        <small class="tm-field-help">También puedes pegar una imagen con Ctrl+V.</small>
                                        <div class="tm-image-preview" data-image-preview hidden>
                                            <img src="" alt="Vista previa" data-image-preview-img>
                                            <button type="button" class="tm-image-clear" data-image-remove aria-label="Quitar imagen">&times;</button>
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
                        <button type="button" class="tm-modal-close" data-close-module-preview>&times;</button>
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
                            <div class="tm-grid tm-grid-2 tm-entry-grid">
                                <input type="hidden" name="entry_id" value="{{ $entry->id }}">
                                @foreach ($orderedFields as $field)
                                    @php
                                        $name = 'values['.$field->key.']';
                                        $id = 'edit_'.$entry->id.'_'.$field->key;
                                        $value = old('values.'.$field->key, $entry->data[$field->key] ?? null);
                                        $isMediaField = in_array($field->type, ['image', 'file'], true);
                                        $isLong = $field->type === 'textarea';
                                        $hasExistingImage = is_string($entry->data[$field->key] ?? null) && trim((string) ($entry->data[$field->key] ?? '')) !== '';
                                    @endphp
                                    @if ($isMediaField && !$mediaDividerPrinted)
                                        <div class="tm-form-divider tm-col-full">
                                            <span>Evidencias</span>
                                        </div>
                                        @php $mediaDividerPrinted = true; @endphp
                                    @endif

                                    <label class="{{ $isLong ? 'tm-col-full' : '' }} tm-entry-field {{ $isMediaField ? 'is-media' : '' }}">
                                        {{ $field->label }} {{ $field->is_required ? '*' : '' }}
                                        @if (!empty($field->comment))
                                            <small class="tm-field-help">{{ $field->comment }}</small>
                                        @endif

                                        @if ($field->type === 'textarea')
                                            <textarea id="{{ $id }}" name="{{ $name }}" rows="3" {{ $field->is_required ? 'required' : '' }}>{{ $value }}</textarea>
                                        @elseif ($field->type === 'number')
                                            <input id="{{ $id }}" type="number" step="any" name="{{ $name }}" value="{{ $value }}" {{ $field->is_required ? 'required' : '' }}>
                                        @elseif ($field->type === 'date')
                                            <input id="{{ $id }}" type="date" name="{{ $name }}" value="{{ $value }}" {{ $field->is_required ? 'required' : '' }}>
                                        @elseif ($field->type === 'datetime')
                                            <input id="{{ $id }}" type="datetime-local" name="{{ $name }}" value="{{ $value }}" {{ $field->is_required ? 'required' : '' }}>
                                        @elseif ($field->type === 'select')
                                            <select id="{{ $id }}" name="{{ $name }}" {{ $field->is_required ? 'required' : '' }}>
                                                <option value="">Selecciona una opción</option>
                                                @foreach (($field->options ?? []) as $option)
                                                    <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        @elseif ($field->type === 'municipio')
                                            <select id="{{ $id }}" name="{{ $name }}" {{ $field->is_required ? 'required' : '' }}>
                                                <option value="">Selecciona un municipio</option>
                                                @foreach ($municipios as $municipio)
                                                    <option value="{{ $municipio }}" @selected($value === $municipio)>{{ $municipio }}</option>
                                                @endforeach
                                            </select>
                                        @elseif ($field->type === 'boolean')
                                            <select id="{{ $id }}" name="{{ $name }}" {{ $field->is_required ? 'required' : '' }}>
                                                <option value="">Selecciona</option>
                                                <option value="1" @selected((string) $value === '1')>Sí</option>
                                                <option value="0" @selected((string) $value === '0')>No</option>
                                            </select>
                                        @elseif (in_array($field->type, ['image', 'file'], true))
                                            <input id="{{ $id }}" type="file" accept="image/*" name="{{ $name }}" {{ ($field->is_required && !$hasExistingImage) ? 'required' : '' }}>
                                            <small class="tm-field-help">También puedes pegar una imagen con Ctrl+V.</small>
                                            <input type="hidden" name="remove_images[{{ $field->key }}]" value="0" data-remove-flag>
                                            <div class="tm-image-preview" data-image-preview {{ is_string($value) && $value !== '' ? '' : 'hidden' }}>
                                                <img
                                                    src="{{ is_string($value) && $value !== '' ? route('temporary-modules.entry-file.preview', ['entry' => $entry->id, 'fieldKey' => $field->key]) : '' }}"
                                                    alt="{{ $field->label }}"
                                                    data-image-preview-img
                                                >
                                                <button type="button" class="tm-image-clear" data-image-remove aria-label="Quitar imagen">&times;</button>
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

    <section class="tm-section-panel {{ !$isUploadSection ? 'is-active' : '' }}" id="tmRecordsView" role="tabpanel" aria-hidden="{{ !$isUploadSection ? 'false' : 'true' }}">
    @if ($modules->isNotEmpty())
        <article class="content-card tm-card tm-records-container">
            <h2>Mis registros</h2>

            <div class="tm-module-filters" role="tablist" aria-label="Filtrar por módulo temporal">
                @foreach ($modules as $module)
                    <button
                        type="button"
                        class="tm-module-chip {{ $loop->first ? 'is-active' : '' }}"
                        data-module-filter
                        data-module-target="module-records-{{ $module->id }}"
                        role="tab"
                        aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                    >
                        {{ $module->name }}
                    </button>
                @endforeach
            </div>

            <div class="tm-module-records-panels">
                @foreach ($modules as $module)
                    @php
                        $municipioField = $module->fields->firstWhere('type', 'municipio');
                    @endphp
                    <section
                        class="tm-module-records-panel {{ $loop->first ? 'is-active' : '' }}"
                        id="module-records-{{ $module->id }}"
                        role="tabpanel"
                        aria-hidden="{{ $loop->first ? 'false' : 'true' }}"
                    >
                        <p class="tm-module-subtitle">{{ $module->name }}</p>

                        <div class="tm-records-mobile">
                            @forelse ($module->getRelation('myEntries') as $entry)
                                @php
                                    $municipioValue = $municipioField ? ($entry->data[$municipioField->key] ?? null) : null;
                                    $cardTitle = (is_string($municipioValue) && trim($municipioValue) !== '')
                                        ? $municipioValue
                                        : 'Registro '.($loop->iteration);
                                @endphp
                                <details class="tm-record-card">
                                    <summary>
                                        <span class="tm-record-card-title">{{ $cardTitle }}</span>
                                        <small>{{ optional($entry->submitted_at)->format('d/m/Y H:i') }}</small>
                                    </summary>

                                    <div class="tm-record-card-body">
                                        @foreach ($module->fields as $field)
                                            @php
                                                $cell = $entry->data[$field->key] ?? null;
                                            @endphp
                                            <div class="tm-record-item">
                                                <strong>{{ $field->label }}</strong>
                                                <div>
                                                    @if (in_array($field->type, ['file', 'image'], true) && is_string($cell) && $cell !== '')
                                                        <button
                                                            type="button"
                                                            class="tm-thumb-link"
                                                            data-open-image-preview
                                                            data-image-src="{{ route('temporary-modules.entry-file.preview', ['entry' => $entry->id, 'fieldKey' => $field->key]) }}"
                                                            data-image-title="{{ $field->label }}"
                                                        >
                                                            <img class="tm-thumb" src="{{ route('temporary-modules.entry-file.preview', ['entry' => $entry->id, 'fieldKey' => $field->key]) }}" alt="{{ $field->label }}">
                                                        </button>
                                                    @elseif (is_bool($cell))
                                                        {{ $cell ? 'Sí' : 'No' }}
                                                    @else
                                                        {{ $cell ?? '—' }}
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach

                                        <div class="tm-record-card-actions">
                                            <button type="button" class="tm-btn" data-open-module-preview="delegate-edit-{{ $entry->id }}">Editar</button>
                                        </div>
                                    </div>
                                </details>
                            @empty
                                <div class="tm-record-empty">Sin registros capturados.</div>
                            @endforelse
                        </div>

                        <div class="tm-table-wrap tm-records-desktop">
                            <table class="tm-table">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        @foreach ($module->fields as $field)
                                            <th>{{ $field->label }}</th>
                                        @endforeach
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($module->getRelation('myEntries') as $entry)
                                        <tr>
                                            <td>{{ optional($entry->submitted_at)->format('d/m/Y H:i') }}</td>
                                            @foreach ($module->fields as $field)
                                                @php
                                                    $cell = $entry->data[$field->key] ?? null;
                                                @endphp
                                                <td>
                                                    @if (in_array($field->type, ['file', 'image'], true) && is_string($cell) && $cell !== '')
                                                        <button
                                                            type="button"
                                                            class="tm-thumb-link"
                                                            data-open-image-preview
                                                            data-image-src="{{ route('temporary-modules.entry-file.preview', ['entry' => $entry->id, 'fieldKey' => $field->key]) }}"
                                                            data-image-title="{{ $field->label }}"
                                                        >
                                                            <img class="tm-thumb" src="{{ route('temporary-modules.entry-file.preview', ['entry' => $entry->id, 'fieldKey' => $field->key]) }}" alt="{{ $field->label }}">
                                                        </button>
                                                    @elseif (is_bool($cell))
                                                        {{ $cell ? 'Sí' : 'No' }}
                                                    @else
                                                        {{ $cell ?? '—' }}
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td>
                                                <button type="button" class="tm-btn" data-open-module-preview="delegate-edit-{{ $entry->id }}">Editar</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ $module->fields->count() + 2 }}">Sin registros capturados.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach
            </div>
        </article>
    @else
        <article class="content-card tm-card">
            <p>No hay módulos temporales para mostrar registros.</p>
        </article>
    @endif
    </section>

    <div class="tm-modal" id="tmImagePreviewModal" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="tm-modal-backdrop" data-close-image-preview></div>
        <div class="tm-modal-dialog tm-image-modal-dialog">
            <div class="tm-modal-head">
                <h3 id="tmImagePreviewTitle">Vista previa</h3>
                <button type="button" class="tm-modal-close" data-close-image-preview>&times;</button>
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
        const imageModal = document.getElementById('tmImagePreviewModal');
        const imageModalImg = document.getElementById('tmImagePreviewImg');
        const imageModalTitle = document.getElementById('tmImagePreviewTitle');
        const imageInputSelector = 'input[type="file"][accept="image/*"]';
        let lastFocusedImageInput = null;
        const modalOpeners = new Map();
        const notify = function (title, message, type) {
            if (typeof window.swal === 'function') {
                window.swal(title, message, type);
                return;
            }

            window.alert((title ? title + ': ' : '') + (message || ''));
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

        openButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const modalId = button.getAttribute('data-open-module-preview');
                const modal = modalId ? document.getElementById(modalId) : null;
                if (!modal) {
                    return;
                }

                openModal(modal, button);
            });
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
                    return response.json().then(function (data) {
                        return { status: response.status, data: data };
                    });
                })
                .then(function (result) {
                    if (result.status >= 400 || !result.data.success) {
                        throw result.data;
                    }

                    notify('Éxito', result.data.message || 'Registro guardado correctamente.', 'success');

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

        moduleFilterButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const targetId = button.getAttribute('data-module-target') || '';
                activateModulePanel(targetId);
            });
        });

        sectionTabs.forEach(function (button) {
            button.addEventListener('click', function () {
                const targetId = button.getAttribute('data-section-target') || '';
                activateSectionPanel(targetId);
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
                notify('Aviso', 'Tu navegador no permite pegar imágenes automáticamente en este campo.', 'warning');
                return;
            }

            event.preventDefault();
            const transfer = new DataTransfer();
            transfer.items.add(imageFile);
            targetInput.files = transfer.files;
            targetInput.dispatchEvent(new Event('change', { bubbles: true }));
            targetInput.focus();
            lastFocusedImageInput = targetInput;
        });
    });
</script>
@endpush
