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

            <div class="tm-grid tm-grid-2">
                <label>
                    Nombre del módulo
                    <input type="text" name="name" value="{{ old('name', $temporaryModule->name) }}" required>
                </label>

                <label>
                    Visible hasta
                    <div class="tm-date-with-toggle" id="tmDateWithToggle">
                        <input type="date" id="tmExpiresAt" name="expires_at" value="{{ old('expires_at', optional($temporaryModule->expires_at)->format('Y-m-d')) }}">
                        <input type="hidden" id="tmIsIndefinite" name="is_indefinite" value="{{ old('is_indefinite', is_null($temporaryModule->expires_at) ? '1' : '0') ? '1' : '0' }}">
                        <button type="button" class="tm-btn" id="tmIndefiniteBtn" aria-pressed="{{ old('is_indefinite', is_null($temporaryModule->expires_at) ? '1' : '0') ? 'true' : 'false' }}">Indefinido</button>
                    </div>
                </label>
            </div>
            <input type="hidden" name="is_active" value="{{ old('is_active', $temporaryModule->is_active ? '1' : '0') ? '1' : '0' }}">

            <label>
                Descripción (opcional)
                <textarea name="description" rows="2">{{ old('description', $temporaryModule->description) }}</textarea>
            </label>

            <section class="tm-target-box">
                <h3>Alcance del módulo</h3>
                @php
                    $oldDelegates = old('delegate_ids', $temporaryModule->applies_to_all ? collect($delegates)->pluck('id')->all() : $selectedDelegates);
                @endphp
                <input type="hidden" name="applies_to" value="selected">
                <div class="tm-target-layout">
                    <div class="tm-target-users tm-target-users-full">
                        <div class="tm-target-users-title">Filtros de selección</div>
                        <div class="tm-target-user-filters" id="tmDelegateFilters">
                            <button type="button" class="tm-btn" data-delegate-filter="enlace">Enlaces</button>
                            <button type="button" class="tm-btn" data-delegate-filter="delegado">Delegados</button>
                            <button type="button" class="tm-btn" data-delegate-filter="all">Todos</button>
                            <button type="button" class="tm-btn tm-btn-clear" data-delegate-filter="clear" aria-label="Desmarcar todos" title="Desmarcar todos">
                                <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="tm-delegate-list" id="tmDelegateList">
                            @foreach ($delegates as $delegate)
                                @php
                                    $scopeText = mb_strtolower((string) ($delegate->scope ?? ''));
                                    $delegateType = str_contains($scopeText, 'enlace') ? 'enlace' : 'delegado';
                                @endphp
                                <label class="tm-delegate-item" data-delegate-item data-original-order="{{ $loop->index }}">
                                    <input type="checkbox" name="delegate_ids[]" value="{{ $delegate->id }}" data-role-type="{{ $delegateType }}" @checked(in_array($delegate->id, $oldDelegates, true))>
                                    <span>
                                        @if($delegateType === 'enlace')
                                            {{ trim((string) ($delegate->first_name ?? '')) !== '' ? $delegate->first_name.' · ' : '' }}
                                        @endif
                                        {{ $delegate->name }}
                                        <small>
                                            {{ $delegate->scope ?? 'Usuario' }} ·
                                            @if(!empty($delegate->microrregion))
                                                MR {{ str_pad((string) $delegate->microrregion, 2, '0', STR_PAD_LEFT) }} - {{ $delegate->cabecera }}
                                            @else
                                                {{ $delegate->cabecera }}
                                            @endif
                                            · {{ $delegate->email }}
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

                            @php
                                $existingOptionsValue = '';
                                if ($field->type === 'categoria' && is_array($field->options)) {
                                    $lines = [];
                                    foreach ($field->options as $cat) {
                                        $nm = $cat['name'] ?? '';
                                        $subs = $cat['sub'] ?? [];
                                        $lines[] = $nm . (count($subs) ? ': ' . implode(', ', $subs) : '');
                                    }
                                    $existingOptionsValue = implode("\n", $lines);
                                } elseif ($field->type === 'select' && is_array($field->options)) {
                                    $existingOptionsValue = implode(", ", $field->options);
                                } else {
                                    $existingOptionsValue = (string) ($oldRow['options'] ?? (is_array($field->options) ? implode(', ', $field->options) : ($field->options ?? '')));
                                }
                                $existingSeccionTitle = (string) ($oldRow['options_title'] ?? (is_array($field->options) && isset($field->options['title']) ? $field->options['title'] : ''));
                                $existingSeccionSubs = (string) ($oldRow['options_subsections'] ?? (is_array($field->options) && !empty($field->options['subsections']) ? implode("\n", $field->options['subsections']) : ''));
                            @endphp

                            <label class="tm-inline-check" data-existing-required-wrap>
                                <input type="checkbox" value="1" name="existing_fields[{{ $index }}][required]" @checked((bool) ($oldRow['required'] ?? $field->is_required)) {{ $oldDelete ? 'disabled' : '' }}>
                                <span>Obligatorio</span>
                            </label>

                            <label class="tm-options-field" data-existing-options-wrap {{ $rowTypeForInput === 'select' ? '' : 'hidden' }}>
                                Opciones (separadas por coma o salto de línea)
                                <textarea name="existing_fields[{{ $index }}][options]" rows="2" {{ $rowTypeForInput === 'select' ? '' : 'disabled' }} {{ $oldDelete ? 'disabled' : '' }}>{{ $rowTypeForInput === 'select' ? ($oldRow['options'] ?? (is_array($field->options) ? implode(', ', $field->options) : '')) : '' }}</textarea>
                            </label>

                            <label class="tm-options-field" data-existing-categoria-wrap {{ $rowTypeForInput === 'categoria' ? '' : 'hidden' }}>
                                Categorías (una por línea: <code>Categoría: sub1, sub2</code>)
                                <textarea name="existing_fields[{{ $index }}][options]" rows="3" {{ $rowTypeForInput === 'categoria' ? '' : 'disabled' }} {{ $oldDelete ? 'disabled' : '' }}>{{ $existingOptionsValue }}</textarea>
                            </label>

                            <label class="tm-options-field tm-seccion-options" data-existing-seccion-wrap {{ $rowTypeForInput === 'seccion' ? '' : 'hidden' }}>
                                Título de la sección
                                <input type="text" name="existing_fields[{{ $index }}][options_title]" value="{{ $existingSeccionTitle }}" {{ $oldDelete ? 'disabled' : '' }}>
                            </label>
                            <label class="tm-options-field tm-seccion-subsections" data-existing-seccion-wrap {{ $rowTypeForInput === 'seccion' ? '' : 'hidden' }}>
                                Subsecciones (una por línea)
                                <textarea name="existing_fields[{{ $index }}][options_subsections]" rows="2" {{ $oldDelete ? 'disabled' : '' }}>{{ $existingSeccionSubs }}</textarea>
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
            </div>

            <div id="tmFieldsContainer" class="tm-fields-list"></div>

            <div class="tm-fields-foot">
                <button type="button" class="tm-btn tm-btn-primary" id="tmAddFieldBtn">Agregar campo</button>
            </div>

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

        <label class="tm-inline-check" data-required-wrap>
            <input type="checkbox" value="1" data-name="required">
            <span>Obligatorio</span>
        </label>

        <label class="tm-options-field" data-options-wrap hidden>
            Opciones (separadas por coma o salto de línea)
            <textarea data-name="options" rows="2" placeholder="Alta, Media, Baja"></textarea>
        </label>

        <label class="tm-options-field" data-categoria-wrap hidden>
            Categorías (una por línea: <code>Categoría: sub1, sub2</code>)
            <textarea data-name="options" rows="3" placeholder="Ejemplo:&#10;Ventas: Norte, Sur&#10;Soporte: Interno"></textarea>
        </label>

        <label class="tm-options-field tm-seccion-options" data-seccion-wrap hidden>
            Título de la sección
            <input type="text" data-name="options_title" placeholder="Ej.: Datos generales">
        </label>
        <label class="tm-options-field tm-seccion-subsections" data-seccion-wrap hidden>
            Subsecciones (una por línea)
            <textarea data-name="options_subsections" rows="2" placeholder="Subsección 1&#10;Subsección 2"></textarea>
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
        const delegateList = document.getElementById('tmDelegateList');
        const delegateFilters = document.getElementById('tmDelegateFilters');
        const expiresAtInput = document.getElementById('tmExpiresAt');
        const isIndefiniteInput = document.getElementById('tmIsIndefinite');
        const indefiniteButton = document.getElementById('tmIndefiniteBtn');
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
            const categoriaWrap = row.querySelector('[data-categoria-wrap]');
            const seccionWraps = row.querySelectorAll('[data-seccion-wrap]');
            const requiredWrap = row.querySelector('[data-required-wrap]');
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
                const toggleByType = function () {
                    const t = typeSelect.value;
                    const isSelect = t === 'select';
                    const isCategoria = t === 'categoria';
                    const isSeccion = t === 'seccion';

                    optionsWrap.hidden = !isSelect;
                    if (categoriaWrap) categoriaWrap.hidden = !isCategoria;
                    seccionWraps.forEach(function (w) { w.hidden = !isSeccion; });
                    if (requiredWrap) requiredWrap.hidden = isSeccion;

                    const optionsInput = optionsWrap.querySelector('[data-name="options"]');
                    if (optionsInput) {
                        optionsInput.required = isSelect;
                        optionsInput.disabled = !isSelect;
                        if (!isSelect) optionsInput.value = '';
                    }
                    const categoriaInput = categoriaWrap ? categoriaWrap.querySelector('[data-name="options"]') : null;
                    if (categoriaInput) {
                        categoriaInput.required = isCategoria;
                        categoriaInput.disabled = !isCategoria;
                        if (!isCategoria) categoriaInput.value = '';
                    }
                    const optionsTitle = row.querySelector('[data-name="options_title"]');
                    const optionsSubsections = row.querySelector('[data-name="options_subsections"]');
                    if (optionsTitle) { optionsTitle.disabled = !isSeccion; if (!isSeccion) optionsTitle.value = ''; }
                    if (optionsSubsections) { optionsSubsections.disabled = !isSeccion; if (!isSeccion) optionsSubsections.value = ''; }
                };

                typeSelect.addEventListener('change', toggleByType);
                toggleByType();
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
            const categoriaWrap = row.querySelector('[data-existing-categoria-wrap]');
            const seccionWraps = row.querySelectorAll('[data-existing-seccion-wrap]');
            const requiredWrap = row.querySelector('[data-existing-required-wrap]');
            if (!typeSelect) {
                return;
            }

            const isDeleted = row.classList.contains('is-marked-remove');
            const t = typeSelect.value;
            const isSelect = t === 'select';
            const isCategoria = t === 'categoria';
            const isSeccion = t === 'seccion';

            if (optionsWrap) {
                optionsWrap.hidden = !isSelect;
                const optionsInput = optionsWrap.querySelector('textarea');
                if (optionsInput) {
                    optionsInput.disabled = isDeleted || !isSelect;
                    optionsInput.required = !isDeleted && isSelect;
                    if (!isSelect && !isDeleted) optionsInput.value = '';
                }
            }
            if (categoriaWrap) {
                categoriaWrap.hidden = !isCategoria;
                const catInput = categoriaWrap.querySelector('textarea');
                if (catInput) {
                    catInput.disabled = isDeleted || !isCategoria;
                    catInput.required = !isDeleted && isCategoria;
                    if (!isCategoria && !isDeleted) catInput.value = '';
                }
            }
            seccionWraps.forEach(function (w) {
                w.hidden = !isSeccion;
                w.querySelectorAll('input, textarea').forEach(function (el) {
                    el.disabled = isDeleted || !isSeccion;
                    if (!isSeccion && !isDeleted) el.value = '';
                });
            });
            if (requiredWrap) {
                requiredWrap.hidden = isSeccion;
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

        const reordenarDelegados = function () {
            if (!delegateList) {
                return;
            }

            const items = Array.from(delegateList.querySelectorAll('[data-delegate-item]'));
            items.sort(function (itemA, itemB) {
                const checkA = itemA.querySelector('input[type="checkbox"][name="delegate_ids[]"]');
                const checkB = itemB.querySelector('input[type="checkbox"][name="delegate_ids[]"]');
                const selectedA = checkA && checkA.checked ? 1 : 0;
                const selectedB = checkB && checkB.checked ? 1 : 0;

                if (selectedA !== selectedB) {
                    return selectedB - selectedA;
                }

                const orderA = Number(itemA.getAttribute('data-original-order') || 0);
                const orderB = Number(itemB.getAttribute('data-original-order') || 0);
                return orderA - orderB;
            });

            items.forEach(function (item) {
                delegateList.appendChild(item);
            });
        };

        const marcarFiltroActivo = function (activeFilter) {
            if (!delegateFilters) {
                return;
            }

            delegateFilters.querySelectorAll('[data-delegate-filter]').forEach(function (button) {
                const filter = String(button.getAttribute('data-delegate-filter') || '').toLowerCase();
                if (filter === 'clear') {
                    button.classList.remove('is-active');
                    return;
                }

                button.classList.toggle('is-active', filter === activeFilter);
            });
        };

        const marcarDelegadosPorFiltro = function (filter) {
            if (!delegateList) {
                return;
            }

            const checkboxes = Array.from(delegateList.querySelectorAll('input[type="checkbox"][name="delegate_ids[]"]'));
            checkboxes.forEach(function (input) {
                if (input.disabled) {
                    return;
                }

                if (filter === 'clear') {
                    input.checked = false;
                    return;
                }

                if (filter === 'all') {
                    input.checked = true;
                    return;
                }

                const roleType = String(input.getAttribute('data-role-type') || '').toLowerCase();
                input.checked = roleType === filter;
            });

            reordenarDelegados();
        };

        if (delegateFilters) {
            delegateFilters.addEventListener('click', function (event) {
                const button = event.target.closest('[data-delegate-filter]');
                if (!button || button.disabled) {
                    return;
                }

                const filter = String(button.getAttribute('data-delegate-filter') || '').toLowerCase();
                if (!filter) {
                    return;
                }

                marcarDelegadosPorFiltro(filter);
                marcarFiltroActivo(filter);
            });
        }

        if (delegateList) {
            delegateList.addEventListener('change', function (event) {
                const target = event.target;
                if (!target || target.name !== 'delegate_ids[]') {
                    return;
                }

                reordenarDelegados();
            });
        }

        reordenarDelegados();

        const actualizarModoIndefinido = function () {
            if (!expiresAtInput || !isIndefiniteInput || !indefiniteButton) {
                return;
            }

            const indefinidoActivo = isIndefiniteInput.value === '1';
            expiresAtInput.disabled = indefinidoActivo;
            expiresAtInput.required = !indefinidoActivo;
            if (indefinidoActivo) {
                expiresAtInput.value = '';
            }

            indefiniteButton.classList.toggle('is-active', indefinidoActivo);
            indefiniteButton.setAttribute('aria-pressed', indefinidoActivo ? 'true' : 'false');
        };

        if (indefiniteButton && isIndefiniteInput) {
            indefiniteButton.addEventListener('click', function () {
                isIndefiniteInput.value = isIndefiniteInput.value === '1' ? '0' : '1';
                actualizarModoIndefinido();
            });
        }

        actualizarModoIndefinido();

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
                    submitWithAction('clear_module');
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
