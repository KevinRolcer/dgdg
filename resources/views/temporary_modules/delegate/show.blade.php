@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
@endpush

@section('content')
@php
    $tmImportable = $temporaryModule->fields->filter(fn ($f) => in_array($f->type, \App\Services\TemporaryModules\TemporaryModuleExcelImportService::IMPORTABLE_TYPES, true));
@endphp
<section class="tm-page">
    <article class="content-card tm-card">
        @if (session('status'))
            <div class="inline-alert inline-alert-success" role="alert">{{ session('status') }}</div>
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
                                    <div class="tm-inline-image-preview tm-image-preview" data-inline-image-preview data-image-preview hidden>
                                        <img src="" alt="Vista previa" data-inline-image-preview-img data-image-preview-img>
                                        <button type="button" class="tm-image-clear" data-inline-image-remove aria-label="Quitar imagen">&times;</button>
                                    </div>
                                </div>
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
        <div class="tm-modal-dialog tm-excel-modal-dialog" style="max-width:720px;">
            <div class="tm-modal-head">
                <h3 id="tmImportarExcelModalLabel">Importar desde Excel</h3>
                <button type="button" class="tm-modal-close" data-tm-excel-close aria-label="Cerrar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="tm-modal-body tm-excel-modal-body">
                <p class="tm-field-help" style="margin-bottom:12px;">Si el archivo trae totales o títulos arriba, al leer columnas se <strong>detecta</strong> la fila de la tabla (MUNICIPIO, MICROREGION, ACCION…) y la primera fila de ítems. Luego asocia cada campo del módulo con una columna.</p>
                <div id="tmExcelStep1">
                    <div class="tm-excel-grid">
                        <label>Fila encabezados <input type="number" id="tmExcelHeaderRow" value="1" min="1" max="500"></label>
                        <label>Primera fila datos <input type="number" id="tmExcelDataStartRow" value="2" min="2" max="50000"></label>
                        <label>Microregión
                            <select id="tmExcelMicrorregionId">
                                @foreach (($microrregionesAsignadas ?? collect()) as $micro)
                                    <option value="{{ $micro->id }}" @selected((int) $microrregionId === (int) $micro->id)>MR {{ $micro->microrregion }} — {{ $micro->cabecera }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <label class="tm-col-full" style="margin-top:10px;">Archivo <input type="file" id="tmExcelFile" accept=".xlsx,.xls"></label>
                    <div id="tmExcelPreviewErr" class="inline-alert inline-alert-error tm-hidden" role="alert"></div>
                    <div id="tmExcelDetectNote" class="inline-alert inline-alert-success tm-hidden" style="margin-top:8px;" role="status"></div>
                    <div class="tm-actions" style="margin-top:10px;">
                        <button type="button" class="tm-btn tm-btn-primary" id="tmExcelLeerColumnas">Leer columnas</button>
                    </div>
                </div>
                <div id="tmExcelStep2" class="tm-hidden">
                    <div class="tm-table-wrap" style="max-height:240px; overflow:auto;">
                        <table class="tm-table tm-table-sm">
                            <thead><tr><th>Campo del módulo</th><th>Tipo</th><th>Columna Excel</th></tr></thead>
                            <tbody id="tmExcelMapBody"></tbody>
                        </table>
                    </div>
                    <div id="tmExcelImportErr" class="inline-alert inline-alert-error tm-hidden" role="alert"></div>
                    <div id="tmExcelImportOk" class="inline-alert inline-alert-success tm-hidden" role="alert"></div>
                    <div class="tm-actions" style="margin-top:10px;">
                        <button type="button" class="tm-btn" id="tmExcelVolver">Volver</button>
                        <button type="button" class="tm-btn tm-btn-primary" id="tmExcelImportar">Importar filas</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <article class="content-card tm-card">
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
                                @endphp
                                <td>
                                    @if (in_array($field->type, ['file', 'image'], true) && is_string($cell) && $cell !== '')
                                        <button
                                            type="button"
                                            class="tm-thumb-link"
                                            data-open-image-preview
                                            data-image-src="{{ route('temporary-modules.entry-file.preview', ['module' => $temporaryModule->id, 'entry' => $entry->id, 'fieldKey' => $field->key]) }}"
                                            data-image-title="{{ $field->label }}"
                                            title="Ver imagen"
                                        >
                                            <i class="fa fa-image" aria-hidden="true"></i> Ver imagen
                                        </button>
                                    @elseif (is_bool($cell))
                                        {{ $cell ? 'Si' : 'No' }}
                                    @else
                                        {{ $cell ?? '-' }}
                                    @endif
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
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const pasteButtons = Array.from(document.querySelectorAll('[data-paste-image-button]'));
        const pasteInputs = Array.from(document.querySelectorAll('[data-paste-upload-input]'));
        const pasteUploadAreas = Array.from(document.querySelectorAll('[data-paste-upload-wrap]'));
        let activePasteInput = null;

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

        const getStatusElement = function (input) {
            if (!input || !input.id) {
                return null;
            }

            return document.getElementById('paste_status_' + input.id);
        };

        const setStatus = function (statusElement, message, hasError) {
            if (!statusElement) {
                return;
            }

            statusElement.textContent = message;
            statusElement.classList.toggle('is-error', Boolean(hasError));
            statusElement.classList.toggle('is-success', !hasError && message !== '');
        };

        const setFileInInput = function (input, file) {
            if (!input || !file || typeof DataTransfer === 'undefined') {
                return false;
            }

            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        };

        const setPreview = function (input, file) {
            const wrap = input.closest('[data-paste-upload-wrap]');
            if (!wrap) {
                return;
            }

            const preview = wrap.querySelector('[data-inline-image-preview]');
            const previewImg = wrap.querySelector('[data-inline-image-preview-img]');
            if (!(preview instanceof HTMLElement) || !(previewImg instanceof HTMLImageElement)) {
                return;
            }

            if (!file) {
                preview.hidden = true;
                previewImg.removeAttribute('src');
                return;
            }

            const reader = new FileReader();
            reader.onload = function (event) {
                previewImg.src = String(event.target && event.target.result ? event.target.result : '');
                preview.hidden = false;
            };
            reader.readAsDataURL(file);
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

        const assignClipboardFile = function (input, blob) {
            if (!input || !blob) {
                return false;
            }

            const mimeType = blob.type || 'image/png';
            const extension = extensionFromMime(mimeType);
            const fileName = 'pegada_' + Date.now() + '.' + extension;
            const file = new File([blob], fileName, {
                type: mimeType,
                lastModified: Date.now(),
            });

            return setFileInInput(input, file);
        };

        const handlePasteEvent = function (event, input) {
            const statusElement = getStatusElement(input);
            const items = event.clipboardData ? Array.from(event.clipboardData.items || []) : [];

            const imageItem = items.find(function (item) {
                return item.kind === 'file' && String(item.type || '').indexOf('image/') === 0;
            });

            if (!imageItem) {
                setStatus(statusElement, 'No se detecto una imagen en el portapapeles.', true);
                return;
            }

            const imageFile = imageItem.getAsFile();
            const wasAssigned = assignClipboardFile(input, imageFile);
            if (!wasAssigned) {
                setStatus(statusElement, 'No se pudo cargar la imagen pegada. Usa seleccionar archivo.', true);
                return;
            }

            setStatus(statusElement, 'Imagen pegada correctamente.', false);
            event.preventDefault();
        };

        const handlePasteFromButton = async function (input) {
            const statusElement = getStatusElement(input);
            activePasteInput = input;

            if (!window.isSecureContext || !navigator.clipboard || typeof navigator.clipboard.read !== 'function') {
                setStatus(statusElement, 'Portapapeles bloqueado.', true);
                return;
            }

            try {
                const clipboardItems = await navigator.clipboard.read();
                let assigned = false;

                for (const clipboardItem of clipboardItems) {
                    const imageType = clipboardItem.types.find(function (type) {
                        return String(type).indexOf('image/') === 0;
                    });

                    if (!imageType) {
                        continue;
                    }

                    const blob = await clipboardItem.getType(imageType);
                    assigned = assignClipboardFile(input, blob);
                    if (assigned) {
                        break;
                    }
                }

                if (!assigned) {
                    setStatus(statusElement, 'No se detecto una imagen en el portapapeles.', true);
                    return;
                }

                setStatus(statusElement, 'Imagen pegada correctamente.', false);
            } catch (error) {
                setStatus(statusElement, 'No se pudo leer el portapapeles.', true);
            }
        };

        pasteInputs.forEach(function (input) {
            input.addEventListener('focus', function () {
                activePasteInput = input;
            });

            input.addEventListener('click', function () {
                activePasteInput = input;
            });

            input.addEventListener('change', function () {
                const file = input.files && input.files[0] ? input.files[0] : null;
                setPreview(input, file);
            });

            const wrap = input.closest('[data-paste-upload-wrap]');
            if (!wrap) {
                return;
            }

            const removeButton = wrap.querySelector('[data-inline-image-remove]');
            if (removeButton instanceof HTMLButtonElement) {
                removeButton.addEventListener('click', function () {
                    input.value = '';
                    setPreview(input, null);
                });
            }
        });

        pasteButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const targetInputId = button.getAttribute('data-target-input') || '';
                const input = targetInputId ? document.getElementById(targetInputId) : null;
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }

                handlePasteFromButton(input);
            });
        });

        pasteUploadAreas.forEach(function (area) {
            const input = area.querySelector('input[type="file"][accept="image/*"]');
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            area.addEventListener('click', function (event) {
                if (event.target.closest('[data-inline-image-remove]') || event.target.closest('.tm-inline-image-preview img')) {
                    return;
                }
                activePasteInput = input;
                input.click();
            });

            area.addEventListener('focusin', function () {
                activePasteInput = input;
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
                    setStatus(getStatusElement(input), 'Solo imagenes.', true);
                    return;
                }

                const wasAssigned = setFileInInput(input, imageFile);
                setStatus(getStatusElement(input), wasAssigned ? 'Imagen cargada.' : 'No se pudo adjuntar.', !wasAssigned);
                if (wasAssigned) {
                    setPreview(input, imageFile);
                }
            });
        });

        Array.from(document.querySelectorAll('[data-upload-trigger]')).forEach(function (button) {
            button.addEventListener('click', function () {
                const targetId = button.getAttribute('data-target-input') || '';
                const input = targetId ? document.getElementById(targetId) : null;
                if (input instanceof HTMLInputElement) {
                    activePasteInput = input;
                    input.click();
                }
            });
        });

        document.addEventListener('paste', function (event) {
            if (!(activePasteInput instanceof HTMLInputElement)) {
                return;
            }

            handlePasteEvent(event, activePasteInput);
        });

        const microrregionSelector = document.getElementById('tmMicrorregionSelector');
        const municipioSelects = Array.from(document.querySelectorAll('.tm-municipio-select'));
        const municipiosPorMicrorregion = @json(($microrregionesAsignadas ?? collect())->mapWithKeys(function ($micro) {
            return [(string) $micro->id => array_values($micro->municipios ?? [])];
        })->all());

        const renderMunicipios = function (microrregionId) {
            if (!microrregionId || municipioSelects.length === 0) {
                return;
            }

            const municipios = Array.isArray(municipiosPorMicrorregion[microrregionId])
                ? municipiosPorMicrorregion[microrregionId]
                : [];

            municipioSelects.forEach(function (select) {
                const selectedPrevio = select.value;
                select.innerHTML = '';
                select.appendChild(new Option('Selecciona un municipio', ''));

                municipios.forEach(function (municipio) {
                    const option = new Option(municipio, municipio, false, selectedPrevio === municipio);
                    select.appendChild(option);
                });
            });
        };

        if (microrregionSelector) {
            renderMunicipios(String(microrregionSelector.value || ''));
            microrregionSelector.addEventListener('change', function () {
                renderMunicipios(String(microrregionSelector.value || ''));
            });
        }

        const imagePreviewButtons = Array.from(document.querySelectorAll('[data-open-image-preview]'));
        const imageModal = document.getElementById('tmImagePreviewModal');
        const imageModalImg = document.getElementById('tmImagePreviewImg');
        const imageModalTitle = document.getElementById('tmImagePreviewTitle');
        let lastImageOpener = null;

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
            document.body.style.overflow = '';
            if (imageModalImg) {
                imageModalImg.removeAttribute('src');
            }

            if (lastImageOpener instanceof HTMLElement) {
                lastImageOpener.focus();
            }
        };

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

        Array.from(document.querySelectorAll('[data-close-image-preview]')).forEach(function (button) {
            button.addEventListener('click', closeImageModal);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && imageModal && imageModal.classList.contains('is-open')) {
                closeImageModal();
            }
        });

        /* Importar Excel */
        const excelModal = document.getElementById('tmImportarExcelModal');
        const excelPreviewUrl = @json(route('temporary-modules.import-excel-preview', $temporaryModule->id));
        const excelImportUrl = @json(route('temporary-modules.import-excel', $temporaryModule->id));
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @json(csrf_token());
        let excelHeaders = [];
        let excelFields = [];
        let excelSuggested = {};

        const openExcelModal = function () {
            if (!excelModal) return;
            excelModal.classList.add('is-open');
            excelModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            document.getElementById('tmExcelStep1')?.classList.remove('tm-hidden');
            document.getElementById('tmExcelStep2')?.classList.add('tm-hidden');
            ['tmExcelPreviewErr', 'tmExcelImportErr', 'tmExcelImportOk'].forEach(function (id) {
                const el = document.getElementById(id);
                if (el) { el.textContent = ''; el.classList.add('tm-hidden'); }
            });
        };
        const closeExcelModal = function () {
            if (!excelModal) return;
            excelModal.classList.remove('is-open');
            excelModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };
        document.getElementById('tmBtnImportarExcel')?.addEventListener('click', openExcelModal);
        excelModal?.querySelectorAll('[data-tm-excel-close]').forEach(function (el) {
            el.addEventListener('click', closeExcelModal);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && excelModal && excelModal.classList.contains('is-open')) closeExcelModal();
        });

        document.getElementById('tmExcelLeerColumnas')?.addEventListener('click', function () {
            const fileInput = document.getElementById('tmExcelFile');
            const file = fileInput && fileInput.files && fileInput.files[0];
            const err = document.getElementById('tmExcelPreviewErr');
            if (!file) {
                if (err) { err.textContent = 'Selecciona un archivo Excel.'; err.classList.remove('tm-hidden'); }
                return;
            }
            const fd = new FormData();
            fd.append('archivo_excel', file);
            fd.append('header_row', document.getElementById('tmExcelHeaderRow')?.value || '1');
            fd.append('auto_detect', '1');
            fd.append('_token', csrfToken);
            if (err) err.classList.add('tm-hidden');
            const noteEl = document.getElementById('tmExcelDetectNote');
            if (noteEl) { noteEl.classList.add('tm-hidden'); }
            fetch(excelPreviewUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (_ref) {
                    if (!_ref.ok || !_ref.j.success) {
                        if (err) { err.textContent = _ref.j.message || 'Error al leer el archivo.'; err.classList.remove('tm-hidden'); }
                        return;
                    }
                    const hr = document.getElementById('tmExcelHeaderRow');
                    const dataStart = document.getElementById('tmExcelDataStartRow');
                    if (typeof _ref.j.header_row === 'number' && hr) hr.value = String(_ref.j.header_row);
                    if (typeof _ref.j.data_start_row === 'number' && dataStart) dataStart.value = String(_ref.j.data_start_row);
                    if (_ref.j.detection_note && noteEl) {
                        noteEl.textContent = _ref.j.detection_note;
                        noteEl.classList.remove('tm-hidden');
                    }
                    excelHeaders = _ref.j.headers || [];
                    excelFields = _ref.j.fields || [];
                    excelSuggested = _ref.j.suggested_map || {};
                    const tbody = document.getElementById('tmExcelMapBody');
                    if (tbody) {
                        tbody.innerHTML = '';
                        excelFields.forEach(function (f) {
                            const tr = document.createElement('tr');
                            const sug = excelSuggested[f.key];
                            let opts = '<option value="">— No importar —</option>';
                            excelHeaders.forEach(function (h) {
                                const sel = (sug === h.index) ? ' selected' : '';
                                const lab = (h.letter + ': ' + (h.label || '(vacío)')).replace(/</g, '');
                                opts += '<option value="' + h.index + '"' + sel + '>' + lab + '</option>';
                            });
                            tr.innerHTML = '<td>' + String(f.label).replace(/</g, '') + (f.is_required ? ' *' : '') + '</td><td>' + String(f.type).replace(/</g, '') + '</td><td><select class="tm-excel-map-select" data-field-key="' + String(f.key).replace(/"/g, '') + '">' + opts + '</select></td>';
                            tbody.appendChild(tr);
                        });
                    }
                    document.getElementById('tmExcelStep1')?.classList.add('tm-hidden');
                    document.getElementById('tmExcelStep2')?.classList.remove('tm-hidden');
                })
                .catch(function () {
                    if (err) { err.textContent = 'Error de red al subir el archivo.'; err.classList.remove('tm-hidden'); }
                });
        });

        document.getElementById('tmExcelVolver')?.addEventListener('click', function () {
            document.getElementById('tmExcelStep2')?.classList.add('tm-hidden');
            document.getElementById('tmExcelStep1')?.classList.remove('tm-hidden');
        });

        document.getElementById('tmExcelImportar')?.addEventListener('click', function () {
            const fileInput = document.getElementById('tmExcelFile');
            const file = fileInput && fileInput.files && fileInput.files[0];
            const errEl = document.getElementById('tmExcelImportErr');
            const okEl = document.getElementById('tmExcelImportOk');
            if (!file) {
                if (errEl) { errEl.textContent = 'Vuelve al paso 1 y selecciona el archivo.'; errEl.classList.remove('tm-hidden'); }
                return;
            }
            const mapping = {};
            document.querySelectorAll('.tm-excel-map-select').forEach(function (sel) {
                const key = sel.getAttribute('data-field-key');
                if (!key) return;
                const v = sel.value;
                mapping[key] = v === '' ? null : parseInt(v, 10);
            });
            const fd = new FormData();
            fd.append('archivo_excel', file);
            fd.append('header_row', document.getElementById('tmExcelHeaderRow')?.value || '1');
            fd.append('data_start_row', document.getElementById('tmExcelDataStartRow')?.value || '2');
            fd.append('mapping', JSON.stringify(mapping));
            fd.append('selected_microrregion_id', document.getElementById('tmExcelMicrorregionId')?.value || '');
            fd.append('_token', csrfToken);
            if (errEl) errEl.classList.add('tm-hidden');
            if (okEl) okEl.classList.add('tm-hidden');
            fetch(excelImportUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (_ref) {
                    if (!_ref.ok || !_ref.j.success) {
                        if (errEl) { errEl.textContent = _ref.j.message || 'Error al importar.'; errEl.classList.remove('tm-hidden'); }
                        return;
                    }
                    let msg = _ref.j.message || 'Listo.';
                    if (_ref.j.row_errors && _ref.j.row_errors.length) {
                        msg += ' Avisos: ' + _ref.j.row_errors.slice(0, 5).map(function (e) { return 'fila ' + e.row; }).join(', ');
                    }
                    if (okEl) { okEl.textContent = msg; okEl.classList.remove('tm-hidden'); }
                    if (_ref.j.imported > 0) setTimeout(function () { window.location.reload(); }, 1200);
                })
                .catch(function () {
                    if (errEl) { errEl.textContent = 'Error de red.'; errEl.classList.remove('tm-hidden'); }
                });
        });
    });
</script>
@endpush
