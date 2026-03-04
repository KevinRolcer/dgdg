@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endpush

@section('content')
<section class="tm-page">
    <article class="content-card tm-card">
        @if ($errors->any())
            <div class="inline-alert inline-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="tm-head tm-head-stack">
            <div>
                <h2>Editar módulo temporal</h2>
                <p>Actualiza vigencia, alcance y agrega datos extra requeridos.</p>
            </div>
            <a href="{{ route('temporary-modules.admin.index') }}" class="tm-btn">Volver</a>
        </div>

        <form action="{{ route('temporary-modules.admin.update', $temporaryModule->id) }}" method="POST" class="tm-form tm-edit-compact" id="tmEditForm">
            @csrf
            @method('PUT')

            <div class="tm-grid tm-grid-3">
                <label>
                    Nombre del módulo
                    <input type="text" name="name" value="{{ old('name', $temporaryModule->name) }}" required>
                </label>

                <label>
                    Visible hasta
                    <input type="date" name="expires_at" value="{{ old('expires_at', optional($temporaryModule->expires_at)->format('Y-m-d')) }}" required>
                </label>

                <label class="tm-inline-check">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $temporaryModule->is_active) ? 'checked' : '' }}>
                    <span>Activo</span>
                </label>
            </div>

            <label>
                Descripción (opcional)
                <textarea name="description" rows="2">{{ old('description', $temporaryModule->description) }}</textarea>
            </label>

            <section class="tm-target-box">
                <h3>Alcance del módulo</h3>
                @php
                    $oldDelegates = old('delegate_ids', $selectedDelegates);
                @endphp
                <div class="tm-target-layout">
                    <div class="tm-target-selector">
                        <label class="tm-inline-check tm-target-choice">
                            <input type="radio" name="applies_to" value="all" {{ old('applies_to', $temporaryModule->applies_to_all ? 'all' : 'selected') === 'all' ? 'checked' : '' }}>
                            <span>Todos</span>
                        </label>
                        <label class="tm-inline-check tm-target-choice">
                            <input type="radio" name="applies_to" value="selected" {{ old('applies_to', $temporaryModule->applies_to_all ? 'all' : 'selected') === 'selected' ? 'checked' : '' }}>
                            <span>Selección Específica.</span>
                        </label>
                    </div>

                    <div class="tm-target-users">
                        <div class="tm-target-users-title">Delegados / usuarios</div>
                        <div class="tm-delegate-list {{ old('applies_to', $temporaryModule->applies_to_all ? 'all' : 'selected') === 'selected' ? '' : 'is-disabled' }}" id="tmDelegateList">
                            @foreach ($delegates as $delegate)
                                <label class="tm-delegate-item">
                                    <input type="checkbox" name="delegate_ids[]" value="{{ $delegate->id }}" @checked(in_array($delegate->id, $oldDelegates, true))>
                                    <span>
                                        {{ $delegate->name }}
                                        <small>
                                            MR {{ str_pad((string) $delegate->microrregion, 2, '0', STR_PAD_LEFT) }} - {{ $delegate->cabecera }} · {{ $delegate->email }}
                                        </small>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            <section class="tm-target-box">
                <h3>Campos actuales (editar / eliminar)</h3>
                <input type="hidden" name="conflict_action" id="tmConflictAction" value="none">

                <div class="tm-fields-list" id="tmExistingFieldsContainer">
                    @foreach ($temporaryModule->fields as $index => $field)
                        @php
                            $oldRow = old('existing_fields.'.$index, []);
                            $oldDelete = (bool) ($oldRow['delete'] ?? false);
                            $fieldHasData = ((int) ($fieldUsage[$field->key] ?? 0)) > 0;
                            $rowType = (string) ($oldRow['type'] ?? $field->type);
                            $normalizedOldType = $field->type === 'file' ? 'image' : $field->type;
                            $rowTypeForInput = array_key_exists($rowType, $fieldTypes) ? $rowType : ($rowType === 'file' ? 'image' : 'text');
                            $existingComment = (string) ($oldRow['comment'] ?? ($field->comment ?? ''));
                            $hasExistingComment = trim($existingComment) !== '';
                        @endphp
                        <div
                            class="tm-field-row tm-existing-field-row {{ $oldDelete ? 'is-marked-remove' : '' }}"
                            data-existing-field-row
                            data-old-key="{{ $field->key }}"
                            data-old-type="{{ $normalizedOldType }}"
                            data-has-data="{{ $fieldHasData ? '1' : '0' }}"
                        >
                            <input type="hidden" name="existing_fields[{{ $index }}][id]" value="{{ $field->id }}">

                            <label>
                                Etiqueta
                                <input type="text" name="existing_fields[{{ $index }}][label]" value="{{ $oldRow['label'] ?? $field->label }}" {{ $oldDelete ? 'disabled' : '' }} required>
                            </label>

                            <label>
                                Clave (opcional)
                                <input type="text" name="existing_fields[{{ $index }}][key]" value="{{ $oldRow['key'] ?? $field->key }}" {{ $oldDelete ? 'disabled' : '' }}>
                            </label>

                            <button type="button" class="tm-btn" data-toggle-comment>
                                {{ $hasExistingComment ? 'Editar observación' : 'Agregar observación' }}
                            </button>

                            <label class="tm-comment-field" data-comment-wrap hidden>
                                Comentario de ayuda (opcional)
                                <input type="text" name="existing_fields[{{ $index }}][comment]" value="{{ $existingComment }}" {{ $oldDelete ? 'disabled' : '' }}>
                            </label>

                            <label>
                                Tipo
                                <select name="existing_fields[{{ $index }}][type]" data-existing-field-type {{ $oldDelete ? 'disabled' : '' }}>
                                    @foreach ($fieldTypes as $value => $label)
                                        <option value="{{ $value }}" @selected($rowTypeForInput === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="tm-inline-check">
                                <input type="checkbox" value="1" name="existing_fields[{{ $index }}][required]" @checked((bool) ($oldRow['required'] ?? $field->is_required)) {{ $oldDelete ? 'disabled' : '' }}>
                                <span>Obligatorio</span>
                            </label>

                            <label class="tm-options-field" data-existing-options-wrap {{ $rowTypeForInput === 'select' ? '' : 'hidden' }}>
                                Opciones (separadas por coma o salto de línea)
                                <textarea name="existing_fields[{{ $index }}][options]" rows="2" {{ $rowTypeForInput === 'select' ? '' : 'disabled' }} {{ $oldDelete ? 'disabled' : '' }}>{{ $oldRow['options'] ?? (is_array($field->options) ? implode(', ', $field->options) : '') }}</textarea>
                            </label>

                            <input type="hidden" name="existing_fields[{{ $index }}][delete]" value="{{ $oldDelete ? '1' : '0' }}" data-existing-delete-flag>

                            <button type="button" class="tm-btn tm-btn-danger" data-toggle-existing-delete>
                                {{ $oldDelete ? 'Restaurar' : 'Eliminar campo' }}
                            </button>
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="tm-fields-head">
                <h3>Agregar nuevos campos</h3>
                <button type="button" class="tm-btn tm-btn-primary" id="tmAddFieldBtn">Agregar campo</button>
            </div>

            <div id="tmFieldsContainer" class="tm-fields-list"></div>

            <div class="tm-actions">
                <button type="submit" class="tm-btn tm-btn-primary">Guardar cambios</button>
            </div>
        </form>
    </article>
</section>

<template id="tmFieldRowTemplate">
    <div class="tm-field-row" data-field-row>
        <label>
            Etiqueta
            <input type="text" data-name="label">
        </label>

        <label>
            Clave (opcional)
            <input type="text" data-name="key" placeholder="ejemplo: direccion">
        </label>

        <button type="button" class="tm-btn" data-toggle-comment>Agregar observación</button>

        <label class="tm-comment-field" data-comment-wrap hidden>
            Comentario de ayuda (opcional)
            <input type="text" data-name="comment" placeholder="Ejemplo: registra calle y número exterior">
        </label>

        <label>
            Tipo
            <select data-name="type" data-field-type>
                @foreach ($fieldTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="tm-inline-check">
            <input type="checkbox" value="1" data-name="required">
            <span>Obligatorio</span>
        </label>

        <label class="tm-options-field" data-options-wrap hidden>
            Opciones (separadas por coma o salto de línea)
            <textarea data-name="options" rows="2" placeholder="Alta, Media, Baja"></textarea>
        </label>

        <button type="button" class="tm-btn tm-btn-danger" data-remove-field>Quitar</button>
    </div>
</template>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.getElementById('tmFieldsContainer');
        const addButton = document.getElementById('tmAddFieldBtn');
        const rowTemplate = document.getElementById('tmFieldRowTemplate');
        const appliesToInputs = Array.from(document.querySelectorAll('input[name="applies_to"]'));
        const delegateList = document.getElementById('tmDelegateList');
        const existingRows = Array.from(document.querySelectorAll('[data-existing-field-row]'));
        const conflictActionInput = document.getElementById('tmConflictAction');
        const editForm = document.getElementById('tmEditForm');

        if (!container || !addButton || !rowTemplate) {
            return;
        }

        let index = 0;

        const syncRowNames = function (row, rowIndex) {
            row.querySelectorAll('[data-name]').forEach(function (input) {
                const key = input.getAttribute('data-name');
                input.setAttribute('name', `extra_fields[${rowIndex}][${key}]`);
            });
        };

        const refreshIndices = function () {
            const rows = Array.from(container.querySelectorAll('[data-field-row]'));
            rows.forEach(function (row, rowIndex) {
                syncRowNames(row, rowIndex);
            });
            index = rows.length;
        };

        const slugify = function (value) {
            return value
                .toString()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');
        };

        const attachRowEvents = function (row) {
            const labelInput = row.querySelector('[data-name="label"]');
            const keyInput = row.querySelector('[data-name="key"]');
            const typeSelect = row.querySelector('[data-field-type]');
            const optionsWrap = row.querySelector('[data-options-wrap]');
            const removeButton = row.querySelector('[data-remove-field]');
            const commentWrap = row.querySelector('[data-comment-wrap]');
            const toggleCommentButton = row.querySelector('[data-toggle-comment]');

            if (labelInput && keyInput) {
                labelInput.addEventListener('input', function () {
                    if (keyInput.value.trim() !== '') {
                        return;
                    }
                    keyInput.value = slugify(labelInput.value);
                });
            }

            if (typeSelect && optionsWrap) {
                const toggleOptions = function () {
                    const isSelect = typeSelect.value === 'select';
                    optionsWrap.hidden = !isSelect;
                    const optionsInput = optionsWrap.querySelector('[data-name="options"]');
                    if (optionsInput) {
                        optionsInput.required = isSelect;
                        optionsInput.disabled = !isSelect;
                        if (!isSelect) {
                            optionsInput.value = '';
                        }
                    }
                };

                typeSelect.addEventListener('change', toggleOptions);
                toggleOptions();
            }

            if (removeButton) {
                removeButton.addEventListener('click', function () {
                    row.remove();
                    refreshIndices();
                });
            }

            if (toggleCommentButton && commentWrap) {
                const commentInput = commentWrap.querySelector('[data-name="comment"]');
                const syncCommentState = function () {
                    const isVisible = !commentWrap.hidden;
                    toggleCommentButton.textContent = isVisible ? 'Ocultar observación' : 'Agregar observación';
                    if (!isVisible && commentInput && String(commentInput.value || '').trim() === '') {
                        commentInput.value = '';
                    }
                };

                toggleCommentButton.addEventListener('click', function () {
                    commentWrap.hidden = !commentWrap.hidden;
                    syncCommentState();
                });

                syncCommentState();
            }
        };

        const addRow = function () {
            const fragment = rowTemplate.content.cloneNode(true);
            const row = fragment.querySelector('[data-field-row]');
            if (!row) {
                return;
            }

            syncRowNames(row, index);
            attachRowEvents(row);
            container.appendChild(row);
            index++;
        };

        addButton.addEventListener('click', addRow);

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

        const templateSwal = createTemplateSwal();

        const syncExistingOptions = function (row) {
            const typeSelect = row.querySelector('[data-existing-field-type]');
            const optionsWrap = row.querySelector('[data-existing-options-wrap]');
            if (!typeSelect || !optionsWrap) {
                return;
            }

            const optionsInput = optionsWrap.querySelector('textarea');
            const isDeleted = row.classList.contains('is-marked-remove');
            const isSelect = typeSelect.value === 'select';
            optionsWrap.hidden = !isSelect;

            if (optionsInput) {
                optionsInput.disabled = isDeleted || !isSelect;
                optionsInput.required = !isDeleted && isSelect;
                if (!isSelect && !isDeleted) {
                    optionsInput.value = '';
                }
            }
        };

        const setExistingRowDeleteState = function (row, shouldDelete) {
            const deleteFlag = row.querySelector('[data-existing-delete-flag]');
            const toggleButton = row.querySelector('[data-toggle-existing-delete]');

            row.classList.toggle('is-marked-remove', shouldDelete);
            if (deleteFlag) {
                deleteFlag.value = shouldDelete ? '1' : '0';
            }

            row.querySelectorAll('input, select, textarea').forEach(function (field) {
                if (field === deleteFlag || /\[id\]$/.test(field.name || '')) {
                    return;
                }

                field.disabled = shouldDelete;
            });

            if (toggleButton) {
                toggleButton.textContent = shouldDelete ? 'Restaurar' : 'Eliminar campo';
            }

            syncExistingOptions(row);
        };

        existingRows.forEach(function (row) {
            const typeSelect = row.querySelector('[data-existing-field-type]');
            const toggleButton = row.querySelector('[data-toggle-existing-delete]');
            const commentWrap = row.querySelector('[data-comment-wrap]');
            const toggleCommentButton = row.querySelector('[data-toggle-comment]');

            if (typeSelect) {
                typeSelect.addEventListener('change', function () {
                    syncExistingOptions(row);
                });
            }

            if (toggleButton) {
                toggleButton.addEventListener('click', function () {
                    const isDeleting = !row.classList.contains('is-marked-remove');
                    setExistingRowDeleteState(row, isDeleting);
                });
            }

            if (toggleCommentButton && commentWrap) {
                const commentInput = commentWrap.querySelector('input[name$="[comment]"]');
                const hasValue = commentInput && String(commentInput.value || '').trim() !== '';
                commentWrap.hidden = true;
                toggleCommentButton.textContent = hasValue ? 'Editar observación' : 'Agregar observación';

                toggleCommentButton.addEventListener('click', function () {
                    commentWrap.hidden = !commentWrap.hidden;
                    toggleCommentButton.textContent = commentWrap.hidden
                        ? (commentInput && String(commentInput.value || '').trim() !== '' ? 'Editar observación' : 'Agregar observación')
                        : 'Ocultar observación';
                });
            }

            syncExistingOptions(row);
        });

        const toggleDelegates = function () {
            if (!delegateList) {
                return;
            }

            const selectedInput = appliesToInputs.find(function (input) {
                return input.checked;
            });
            const isSelectedMode = selectedInput && selectedInput.value === 'selected';

            delegateList.classList.toggle('is-disabled', !isSelectedMode);
            delegateList.setAttribute('aria-disabled', isSelectedMode ? 'false' : 'true');
            delegateList.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
                input.disabled = !isSelectedMode;
            });
        };

        appliesToInputs.forEach(function (input) {
            input.addEventListener('change', toggleDelegates);
        });
        toggleDelegates();

        let forceSubmit = false;
        if (editForm) {
            editForm.addEventListener('submit', function (event) {
                if (forceSubmit || !conflictActionInput) {
                    return;
                }

                conflictActionInput.value = 'none';

                const normalizeFieldType = function (value) {
                    return value === 'file' ? 'image' : value;
                };

                const hasConflict = existingRows.some(function (row) {
                    const hasData = row.getAttribute('data-has-data') === '1';
                    if (!hasData) {
                        return false;
                    }

                    const oldKey = row.getAttribute('data-old-key') || '';
                    const oldType = row.getAttribute('data-old-type') || '';
                    const isDeleted = row.classList.contains('is-marked-remove');
                    const keyInput = row.querySelector('input[name$="[key]"]');
                    const typeSelect = row.querySelector('[data-existing-field-type]');
                    const newKey = keyInput ? keyInput.value.trim() : oldKey;
                    const newType = normalizeFieldType(typeSelect ? typeSelect.value : oldType);

                    return isDeleted || newKey !== oldKey || newType !== oldType;
                });

                if (!hasConflict) {
                    return;
                }

                event.preventDefault();

                const submitWithAction = function (action) {
                    conflictActionInput.value = action;
                    forceSubmit = true;
                    editForm.submit();
                };

                if (!templateSwal) {
                    if (window.confirm('Hay datos en campos que vas a modificar o eliminar. ¿Deseas vaciar todos los registros del módulo?')) {
                        submitWithAction('clear_module');
                    }
                    return;
                }

                templateSwal.fire({
                    title: 'Conflicto con datos existentes',
                    text: 'Detectamos registros en campos que quieres modificar o eliminar. Elige cómo continuar.',
                    icon: 'warning',
                    showCancelButton: true,
                    showDenyButton: true,
                    confirmButtonText: 'Vaciar toda la tabla',
                    denyButtonText: 'Borrar datos de esos campos',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true
                }).then(function (result) {
                    if (result.isConfirmed) {
                        submitWithAction('clear_module');
                        return;
                    }

                    if (result.isDenied) {
                        submitWithAction('clear_field_data');
                    }
                });
            });
        }
    });
</script>
@endpush
