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
            <a href="{{ route('temporary-modules.admin.index') }}" class="tm-btn">Volver</a>
        </div>

        <form action="{{ route('temporary-modules.admin.store') }}" method="POST" class="tm-form" id="tmCreateForm">
            @csrf

            <div class="tm-grid tm-grid-2">
                <label>
                    Nombre del módulo
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </label>

                <label>
                    Visible hasta
                    <div class="tm-date-with-toggle" id="tmDateWithToggle">
                        <input type="datetime-local" id="tmExpiresAt" name="expires_at" value="{{ old('expires_at') }}">
                        <input type="hidden" id="tmIsIndefinite" name="is_indefinite" value="{{ old('is_indefinite') ? '1' : '0' }}">
                        <button type="button" class="tm-btn" id="tmIndefiniteBtn" aria-pressed="{{ old('is_indefinite') ? 'true' : 'false' }}">Indefinido</button>
                    </div>
                </label>
            </div>
            <input type="hidden" name="is_active" value="1">

            <label>
                Descripción (opcional)
                <textarea name="description" rows="2">{{ old('description') }}</textarea>
            </label>

            <section class="tm-target-box">
                <h3>Alcance del módulo</h3>
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
                                    <input type="checkbox" name="delegate_ids[]" value="{{ $delegate->id }}" data-role-type="{{ $delegateType }}" @checked(in_array($delegate->id, old('delegate_ids', []), true))>
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


            <div class="tm-fields-head">
                <h3>Campos requeridos</h3>
            </div>
            <div id="tmFieldsContainer" class="tm-fields-list"></div>
            <button type="button" class="tm-btn tm-btn-primary" id="tmAddFieldBtn">Agregar campo</button>

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
            Subsecciones (una por línea; serán columnas agrupadas)
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

        addRow();
        addRow();
    });
</script>
@endpush
