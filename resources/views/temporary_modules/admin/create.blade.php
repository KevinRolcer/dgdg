@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
@endpush

@section('content')
<section class="tm-page">
    <article class="content-card tm-card">
        @if ($errors->any())
            <div class="inline-alert inline-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="tm-head tm-head-stack">
            <div>
                <h2>Crear módulo temporal</h2>
                <p>Define nombre, vigencia y campos requeridos para delegados.</p>
            </div>
            <a href="{{ route('temporary-modules.admin.index') }}" class="tm-btn">Volver</a>
        </div>

        <form action="{{ route('temporary-modules.admin.store') }}" method="POST" class="tm-form" id="tmCreateForm">
            @csrf

            <div class="tm-grid tm-grid-3">
                <label>
                    Nombre del módulo
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </label>

                <label>
                    Visible hasta
                    <input type="date" name="expires_at" value="{{ old('expires_at') }}" required>
                </label>

                <label class="tm-inline-check">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                    <span>Activo</span>
                </label>
            </div>

            <label>
                Descripción (opcional)
                <textarea name="description" rows="2">{{ old('description') }}</textarea>
            </label>

            <section class="tm-target-box">
                <h3>Alcance del módulo</h3>
                <div class="tm-target-layout">
                    <div class="tm-target-selector">
                        <label class="tm-inline-check tm-target-choice">
                            <input type="radio" name="applies_to" value="all" {{ old('applies_to', 'all') === 'all' ? 'checked' : '' }}>
                            <span>Todos</span>
                        </label>
                        <label class="tm-inline-check tm-target-choice">
                            <input type="radio" name="applies_to" value="selected" {{ old('applies_to') === 'selected' ? 'checked' : '' }}>
                            <span>Selección Específica.</span>
                        </label>
                    </div>

                    <div class="tm-target-users">
                        <div class="tm-target-users-title">Delegados / usuarios</div>
                        <div class="tm-delegate-list {{ old('applies_to') === 'selected' ? '' : 'is-disabled' }}" id="tmDelegateList">
                            @foreach ($delegates as $delegate)
                                <label class="tm-delegate-item">
                                    <input type="checkbox" name="delegate_ids[]" value="{{ $delegate->id }}" @checked(in_array($delegate->id, old('delegate_ids', []), true))>
                                    <span>
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

            <div class="tm-fields-head">
                <h3>Campos requeridos</h3>
                <button type="button" class="tm-btn tm-btn-primary" id="tmAddFieldBtn">Agregar campo</button>
            </div>

            <div id="tmFieldsContainer" class="tm-fields-list"></div>

            <div class="tm-actions">
                <button type="submit" class="tm-btn tm-btn-primary">Guardar módulo</button>
            </div>
        </form>
    </article>
</section>

<template id="tmFieldRowTemplate">
    <div class="tm-field-row" data-field-row>
        <label>
            Etiqueta
            <input type="text" data-name="label" required>
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

        if (!container || !addButton || !rowTemplate) {
            return;
        }

        let index = 0;

        const syncRowNames = function (row, rowIndex) {
            row.querySelectorAll('[data-name]').forEach(function (input) {
                const key = input.getAttribute('data-name');
                input.setAttribute('name', `fields[${rowIndex}][${key}]`);
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

        addRow();
        addRow();
    });
</script>
@endpush
