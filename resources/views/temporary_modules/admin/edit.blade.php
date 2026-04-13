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
            <div class="tm-inline-actions">
                @if (!is_null($temporaryModule->seed_discard_log))
                    <script type="application/json" id="tm-seed-discard-edit">{!! json_encode($temporaryModule->seed_discard_log ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
                    <button type="button" class="tm-btn tm-btn-secondary" id="tmEditSeedLogBtn" data-module-name="{{ e($temporaryModule->name) }}">Log (filas omitidas)</button>
                @endif
                <a href="{{ route('temporary-modules.admin.index') }}" class="tm-btn">Volver</a>
            </div>
        </div>

        @if (!is_null($temporaryModule->seed_discard_log))
        <div
            class="tm-modal"
            id="tmSeedDiscardLogModalEdit"
            aria-hidden="true"
            role="dialog"
            aria-modal="true"
            data-register-url="{{ route('temporary-modules.admin.seed-discard-register', $temporaryModule->id) }}"
            data-search-url="{{ route('temporary-modules.admin.seed-discard-search-municipios', $temporaryModule->id) }}"
            data-csrf-token="{{ csrf_token() }}"
        >
            <div class="tm-modal-backdrop" data-tm-seed-log-close-edit></div>
            <div class="tm-modal-dialog tm-seed-log-dialog">
                <div class="tm-modal-head">
                    <h3>Log — filas no cargadas</h3>
                    <button type="button" class="tm-modal-close" data-tm-seed-log-close-edit aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="tm-modal-body tm-seed-log-body">
                    <p class="tm-seed-log-module" id="tmSeedDiscardLogModuleEdit"></p>
                    <p class="tm-muted" id="tmSeedDiscardLogEmptyEdit" hidden>Sin filas descartadas.</p>
                    <div class="tm-table-wrap tm-seed-log-table-wrap" id="tmSeedDiscardLogTableWrapEdit" hidden>
                        <table class="tm-table tm-table-sm">
                            <thead><tr><th>Fila</th><th>Motivo</th><th>MR</th><th>Municipio</th><th>Acción</th><th>Enlazar municipio</th></tr></thead>
                            <tbody id="tmSeedDiscardLogTbodyEdit"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

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
                                        $lines[] = $nm . (count($subs) ? ': ' . implode(', ', array_filter($subs, 'is_string')) : '');
                                    }
                                    $existingOptionsValue = implode("\n", $lines);
                                } elseif ($field->type === 'select' && is_array($field->options)) {
                                    $existingOptionsValue = implode(", ", array_filter($field->options, 'is_string'));
                                } else {
                                    $existingOptionsValue = (string) ($oldRow['options'] ?? (is_array($field->options) ? implode(', ', array_filter($field->options, 'is_string')) : ($field->options ?? '')));
                                }
                                $existingSeccionTitle = (string) ($oldRow['options_title'] ?? (is_array($field->options) && isset($field->options['title']) ? $field->options['title'] : ''));
                                $existingSeccionSubs = (string) ($oldRow['options_subsections'] ?? (is_array($field->options) && !empty($field->options['subsections']) ? implode("\n", array_filter($field->options['subsections'], 'is_string')) : ''));
                            @endphp

                            <label class="tm-inline-check" data-existing-required-wrap>
                                <input type="checkbox" value="1" name="existing_fields[{{ $index }}][required]" @checked((bool) ($oldRow['required'] ?? $field->is_required)) {{ $oldDelete ? 'disabled' : '' }}>
                                <span>Obligatorio</span>
                            </label>

                            <label class="tm-options-field" data-existing-options-wrap {{ $rowTypeForInput === 'select' ? '' : 'hidden' }}>
                                Opciones (separadas por coma o salto de línea)
                                <textarea name="existing_fields[{{ $index }}][options]" rows="2" {{ $rowTypeForInput === 'select' ? '' : 'disabled' }} {{ $oldDelete ? 'disabled' : '' }}>{{ $rowTypeForInput === 'select' ? ($oldRow['options'] ?? (is_array($field->options) ? implode(', ', array_filter($field->options, 'is_string')) : '')) : '' }}</textarea>
                            </label>

                            <label class="tm-options-field" data-existing-multiselect-wrap {{ $rowTypeForInput === 'multiselect' ? '' : 'hidden' }}>
                                Opciones seleccionables (separadas por coma o salto de línea)
                                <textarea name="existing_fields[{{ $index }}][options]" rows="2" {{ $rowTypeForInput === 'multiselect' ? '' : 'disabled' }} {{ $oldDelete ? 'disabled' : '' }}>{{ $rowTypeForInput === 'multiselect' ? ($oldRow['options'] ?? (is_array($field->options) ? implode(', ', array_filter($field->options, 'is_string')) : '')) : '' }}</textarea>
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
<script src="{{ asset('assets/js/modules/temporary-modules-seed-discard-log.js') }}?v={{ @filemtime(public_path('assets/js/modules/temporary-modules-seed-discard-log.js')) ?: time() }}"></script>
<script>
window.TM_ADMIN_EDIT_BOOT = {
    hasSeedDiscardLog: @json(!is_null($temporaryModule->seed_discard_log)),
    showSeedLog: @json((bool) session('show_seed_log')),
};
</script>
<script src="{{ asset('assets/js/modules/temporary-modules-admin-edit.js') }}?v={{ @filemtime(public_path('assets/js/modules/temporary-modules-admin-edit.js')) ?: time() }}"></script>
@endpush
