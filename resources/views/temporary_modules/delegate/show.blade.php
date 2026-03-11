@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
@endpush

@section('content')
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
            <a href="{{ route('temporary-modules.index') }}" class="tm-btn">Volver</a>
        </div>

        <form action="{{ route('temporary-modules.submit', $temporaryModule->id) }}" method="POST" enctype="multipart/form-data" class="tm-form tm-entry-form">
            @csrf

            @php
                $microsAsignadas = ($microrregionesAsignadas ?? collect())->values();
                $mostrarSelectorMicrorregion = $microsAsignadas->count() > 1;
            @endphp

            @if ($mostrarSelectorMicrorregion)
                <label class="tm-col-full">
                    Microrregion de captura *
                    <select id="tmMicrorregionSelector" name="selected_microrregion_id" required>
                        @foreach ($microsAsignadas as $micro)
                            <option value="{{ $micro->id }}" @selected((int) $microrregionId === (int) $micro->id)>
                                MR {{ $micro->microrregion }} - {{ $micro->cabecera }}
                            </option>
                        @endforeach
                    </select>
                </label>
            @else
                <input type="hidden" name="selected_microrregion_id" value="{{ $microrregionId }}">
            @endif

            <div class="tm-grid tm-grid-2">
                @foreach ($fields as $field)
                    @php
                        $name = 'values['.$field->key.']';
                        $id = 'field_'.$field->key;
                        $value = old('values.'.$field->key);
                    @endphp
                    <label>
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
                            <div class="tm-file-paste-wrap" data-paste-upload-wrap>
                                <input
                                    id="{{ $id }}"
                                    type="file"
                                    accept="image/*"
                                    name="{{ $name }}"
                                    class="tm-hidden-file-input"
                                    data-paste-upload-input
                                    {{ $field->is_required ? 'required' : '' }}
                                >
                                <div class="tm-file-upload-layout">
                                    <div class="tm-file-upload-controls">
                                        <div class="tm-file-paste-actions">
                                            <button
                                                type="button"
                                                class="tm-btn tm-btn-icon"
                                                data-paste-image-button
                                                data-target-input="{{ $id }}"
                                                aria-label="Pegar imagen"
                                                title="Pegar imagen"
                                            >
                                                <i class="fa-regular fa-paste" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                        <div class="tm-paste-zone" contenteditable="true" data-paste-zone data-target-input="{{ $id }}" role="textbox" aria-label="Area para pegar imagen">Pega o arrastra imagen</div>
                                    </div>
                                    <div class="tm-inline-image-preview" data-inline-image-preview hidden>
                                        <img src="" alt="Vista previa" data-inline-image-preview-img>
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
                <button type="submit" class="tm-btn tm-btn-primary">Guardar registro</button>
            </div>
        </form>
    </article>

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
                            <td>{{ optional($entry->submitted_at)->format('d/m/Y H:i') }}</td>
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
                                            data-image-src="{{ route('temporary-modules.entry-file.preview', ['entry' => $entry->id, 'fieldKey' => $field->key]) }}"
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
        const pasteButtons = Array.from(document.querySelectorAll('[data-paste-image-button]'));
        const pasteInputs = Array.from(document.querySelectorAll('[data-paste-upload-input]'));
        const pasteZones = Array.from(document.querySelectorAll('[data-paste-zone]'));
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

        pasteZones.forEach(function (zone) {
            const targetInputId = zone.getAttribute('data-target-input') || '';
            const input = targetInputId ? document.getElementById(targetInputId) : null;
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            zone.addEventListener('mousedown', function () {
                input.click();
            });

            zone.addEventListener('focus', function () {
                activePasteInput = input;
            });

            zone.addEventListener('click', function () {
                activePasteInput = input;
            });

            zone.addEventListener('input', function () {
                zone.textContent = 'Pega o arrastra imagen';
            });

            zone.addEventListener('dragenter', function (event) {
                event.preventDefault();
                zone.classList.add('is-dragover');
            });

            zone.addEventListener('dragover', function (event) {
                event.preventDefault();
                zone.classList.add('is-dragover');
            });

            zone.addEventListener('dragleave', function () {
                zone.classList.remove('is-dragover');
            });

            zone.addEventListener('drop', function (event) {
                event.preventDefault();
                zone.classList.remove('is-dragover');

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
    });
</script>
@endpush
