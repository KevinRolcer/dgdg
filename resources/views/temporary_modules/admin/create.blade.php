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
                        <input type="date" id="tmExpiresAt" name="expires_at" value="{{ old('expires_at') }}">
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
                @if (isset($modulesForCopy) && $modulesForCopy->isNotEmpty())
                    <div class="tm-copy-from-wrap">
                        <label for="tmCopyFromModule" class="tm-copy-label">Copiar campos de:</label>
                        <select id="tmCopyFromModule" class="tm-copy-select">
                            <option value="">— Seleccionar módulo —</option>
                            @foreach ($modulesForCopy as $m)
                                <option value="{{ $m->id }}">{{ $m->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="tm-btn" id="tmCopyFieldsBtn">Copiar campos aquí</button>
                    </div>
                @endif
            </div>

            <div id="tmFieldsContainer" class="tm-fields-list"></div>

            <div class="tm-fields-foot">
                <button type="button" class="tm-btn tm-btn-primary" id="tmAddFieldBtn">Agregar campo</button>
            </div>

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

        <div class="tm-row-sort-actions" data-sort-actions>
            <button type="button" class="tm-btn" data-move-up title="Subir campo">↑</button>
            <button type="button" class="tm-btn" data-move-down title="Bajar campo">↓</button>
        </div>

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

        {{-- multiselect: same options list as select --}}
        <label class="tm-options-field" data-multiselect-wrap hidden>
            Opciones seleccionables (separadas por coma o salto de línea)
            <textarea data-name="options" rows="2" placeholder="Rojo, Verde, Amarillo"></textarea>
        </label>

        {{-- linked: two sub-field definitions --}}
        <div class="tm-linked-container" data-linked-wrap hidden>
            <div class="tm-linked-wrap tm-grid tm-grid-2" style="border:1px solid var(--clr-divider,#ddd);border-radius:6px;padding:12px 16px;gap:24px;margin-top:8px;background:var(--tm-muted-bg, rgba(0,0,0,0.02));">

                {{-- Principal --}}
                <div style="display:flex;flex-direction:column;gap:8px;border-right:1px solid var(--clr-divider,#ddd);padding-right:16px;">
                    <h6 style="margin:0;font-size:.85rem;font-weight:700;color:var(--clr-primary,#861e34);">1. Campo principal</h6>
                    <div class="tm-grid tm-grid-2" style="gap:8px;">
                        <label>
                            Etiqueta
                            <input type="text" data-name="linked_primary_label" placeholder="ej. Problemática">
                        </label>
                        <label>
                            Tipo
                            <select data-name="linked_primary_type" data-linked-primary-type>
                                @foreach ($fieldTypes as $value => $label)
                                    @if (!in_array($value, ['linked', 'seccion']))
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <label data-linked-primary-options-wrap hidden>
                        Opciones (coma o salto de línea)
                        <textarea data-name="linked_primary_options" rows="1" placeholder="Opción 1, Opción 2"></textarea>
                    </label>
                </div>

                {{-- Dependiente --}}
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <h6 style="margin:0;font-size:.85rem;font-weight:700;color:var(--clr-secondary,#246257);">2. Campo dependiente</h6>
                    <div class="tm-grid tm-grid-2" style="gap:8px;">
                        <label>
                            Etiqueta
                            <input type="text" data-name="linked_secondary_label" placeholder="ej. Semáforo">
                        </label>
                        <label>
                            Tipo
                            <select data-name="linked_secondary_type" data-linked-secondary-type>
                                @foreach ($fieldTypes as $value => $label)
                                    @if (!in_array($value, ['linked', 'seccion']))
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <label data-linked-secondary-options-wrap hidden>
                        Opciones (coma o salto de línea)
                        <textarea data-name="linked_secondary_options" rows="1" placeholder="Rojo, Amarillo, Verde"></textarea>
                    </label>
                </div>

            </div>
        </div>

        <button type="button" class="tm-btn tm-btn-danger" data-remove-field>Quitar</button>
    </div>
</template>
@endsection

@push('scripts')
<script>
window.TM_ADMIN_CREATE_BOOT = { copyFieldsUrl: @json(route('temporary-modules.admin.fields-json', ['module' => '__ID__'])) };
</script>
<script src="{{ asset('assets/js/modules/temporary-modules-admin-create.js') }}?v={{ @filemtime(public_path('assets/js/modules/temporary-modules-admin-create.js')) ?: time() }}"></script>
@endpush
