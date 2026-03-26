@php
    $module = $module;
    $entries = $entries;
    $municipioField = $municipioField ?? null;
@endphp
<div class="tm-records-fragment-inner" data-module-id="{{ $module->id }}">
    <p class="tm-module-subtitle">{{ $module->name }}</p>
    <div class="tm-records-mobile tm-scroll-panel">
        @forelse ($entries as $entry)
            @php
                $municipioValue = $municipioField ? ($entry->data[$municipioField->key] ?? null) : null;
                $cardTitle = (is_string($municipioValue) && trim($municipioValue) !== '') ? $municipioValue : 'Registro '.($loop->iteration + ($entries->firstItem() ?? 1) - 1);
            @endphp
            <details class="tm-record-card" data-entry-id="{{ $entry->id }}">
                <summary>
                    <span class="tm-record-bulk-check tm-hidden" style="margin-right:10px;">
                        <input type="checkbox" class="tm-record-checkbox" data-tm-bulk-checkbox value="{{ $entry->id }}">
                    </span>
                    <span class="tm-record-card-title">{{ $cardTitle }}</span>
                    <small>MR {{ $entry->microrregion->microrregion ?? '?' }}</small>
                </summary>
                <div class="tm-record-card-body">
                    @foreach ($module->fields as $field)
                        @php $cell = $entry->data[$field->key] ?? null; @endphp
                        <div class="tm-record-item">
                            <strong>{{ $field->label }}</strong>
                            <div>
                                @if (in_array($field->type, ['file', 'image'], true) && is_string($cell) && $cell !== '')
                                    <button type="button" class="tm-thumb-link" data-open-image-preview
                                        data-image-src="{{ route('temporary-modules.entry-file.preview', ['module' => $module->id, 'entry' => $entry->id, 'fieldKey' => $field->key]) }}"
                                        data-image-title="{{ $field->label }}"><i class="fa fa-image"></i> Ver imagen</button>
                                @elseif (is_bool($cell))
                                    {{ $cell ? 'Si' : 'No' }}
                                @elseif ($field->type === 'semaforo' && is_string($cell) && $cell !== '')
                                    @php $semLab = \App\Services\TemporaryModules\TemporaryModuleFieldService::labelForSemaforo($cell); @endphp
                                    <span class="tm-semaforo-badge tm-semaforo-badge--{{ $cell }}" title="{{ $semLab }}">{{ $semLab }}</span>
                                @else
                                    @php
                                        $displayText = is_array($cell) ? implode(', ', array_map(fn ($i) => is_scalar($i) ? (string) $i : json_encode($i, JSON_UNESCAPED_UNICODE), $cell)) : (is_scalar($cell) ? (string) $cell : '-');
                                        $displayText = trim($displayText) !== '' ? $displayText : '-';
                                        $isLongText = mb_strlen($displayText) > 120;
                                    @endphp
                                    @if ($isLongText)
                                        <span class="tm-cell-text-wrap" data-text-wrap>
                                            <span class="tm-cell-text is-collapsed" data-text-content>{{ $displayText }}</span>
                                            <button type="button" class="tm-cell-text-toggle" data-text-toggle>Ver mas</button>
                                        </span>
                                    @else
                                        {{ $displayText }}
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                    <div class="tm-record-card-actions">
                        <button type="button" class="tm-btn" data-open-module-preview="delegate-edit-{{ $entry->id }}">Editar</button>
                        <form action="{{ route('temporary-modules.entry.destroy', ['module' => $module->id, 'entry' => $entry->id]) }}" method="POST" class="tm-inline-form" data-confirm-delete data-record-title="{{ $cardTitle }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="tm-btn tm-btn-danger"><i class="fa-solid fa-trash" aria-hidden="true"></i> Eliminar</button>
                        </form>
                    </div>
                </div>
            </details>
        @empty
            <div class="tm-record-empty">Sin registros capturados.</div>
        @endforelse
    </div>
    <div class="tm-table-wrap tm-table-wrap-scroll tm-records-desktop">
        <table class="tm-table">
            <thead>
                <tr>
                    <th class="tm-bulk-col tm-hidden" style="width:40px; text-align:center;">
                        <input type="checkbox" data-tm-bulk-select-all>
                    </th>
                    <th>Microregión</th>
                    @foreach ($module->fields as $field)
                        <th>{{ $field->label }}</th>
                    @endforeach
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr data-entry-id="{{ $entry->id }}">
                        <td class="tm-bulk-col tm-hidden" style="text-align:center;">
                            <input type="checkbox" class="tm-record-checkbox" data-tm-bulk-checkbox value="{{ $entry->id }}">
                        </td>
                        <td>MR {{ $entry->microrregion->microrregion ?? '-' }}</td>
                        @foreach ($module->fields as $field)
                            @php $cell = $entry->data[$field->key] ?? null; @endphp
                            <td>
                                @if (in_array($field->type, ['file', 'image'], true) && is_string($cell) && $cell !== '')
                                    <button type="button" class="tm-thumb-link" data-open-image-preview
                                        data-image-src="{{ route('temporary-modules.entry-file.preview', ['module' => $module->id, 'entry' => $entry->id, 'fieldKey' => $field->key]) }}"
                                        data-image-title="{{ $field->label }}"><i class="fa fa-image"></i> Ver imagen</button>
                                @elseif (is_bool($cell))
                                    {{ $cell ? 'Si' : 'No' }}
                                @elseif ($field->type === 'semaforo' && is_string($cell) && $cell !== '')
                                    @php $semLab = \App\Services\TemporaryModules\TemporaryModuleFieldService::labelForSemaforo($cell); @endphp
                                    <span class="tm-semaforo-badge tm-semaforo-badge--{{ $cell }}" title="{{ $semLab }}">{{ $semLab }}</span>
                                @else
                                    @php
                                        $displayText = is_array($cell) ? implode(', ', array_map(fn ($i) => is_scalar($i) ? (string) $i : json_encode($i, JSON_UNESCAPED_UNICODE), $cell)) : (is_scalar($cell) ? (string) $cell : '-');
                                        $displayText = trim($displayText) !== '' ? $displayText : '-';
                                        $isLongText = mb_strlen($displayText) > 120;
                                    @endphp
                                    @if ($isLongText)
                                        <span class="tm-cell-text-wrap" data-text-wrap>
                                            <span class="tm-cell-text is-collapsed" data-text-content>{{ $displayText }}</span>
                                            <button type="button" class="tm-cell-text-toggle" data-text-toggle>Ver mas</button>
                                        </span>
                                    @else
                                        {{ $displayText }}
                                    @endif
                                @endif
                            </td>
                        @endforeach
                        <td class="tm-actions-cell">
                            @php
                                $rowMrValue = $municipioField ? ($entry->data[$municipioField->key] ?? null) : null;
                                $rowTitle = (is_string($rowMrValue) && trim($rowMrValue) !== '') ? $rowMrValue : 'Registro '.($loop->iteration + ($entries->firstItem() ?? 1) - 1);
                            @endphp
                            <button type="button" class="tm-btn" data-open-module-preview="delegate-edit-{{ $entry->id }}">Editar</button>
                            <form action="{{ route('temporary-modules.entry.destroy', ['module' => $module->id, 'entry' => $entry->id]) }}" method="POST" class="tm-inline-form" data-confirm-delete data-record-title="{{ $rowTitle }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="tm-btn tm-btn-danger" title="Eliminar"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $module->fields->count() + 2 }}">Sin registros capturados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($entries->hasPages())
        <div class="tm-pagination tm-pagination--footer">
            {{ $entries->links('vendor.pagination.tm') }}
        </div>
    @endif

    @foreach ($entries as $entry)
        @php
            $microsAsignadas = ($microrregionesAsignadas ?? collect())->values();
            $mostrarSelectorMicrorregion = $microsAsignadas->count() > 1;
            $entryMicrorregion = $microsAsignadas->firstWhere('id', $entry->microrregion_id);
            $entryMunicipios = $entryMicrorregion && isset($entryMicrorregion->municipios) ? array_values($entryMicrorregion->municipios) : ($municipios ?? []);
            
            $orderedFields = $module->fields
                ->sortBy(fn ($field) => in_array($field->type, ['image', 'file'], true) ? 1 : 0)
                ->values();
            $mediaDividerPrinted = false;
        @endphp

        <div class="tm-modal" id="delegate-edit-{{ $entry->id }}" aria-hidden="true" role="dialog" aria-modal="true">
            <div class="tm-modal-backdrop" data-close-module-preview></div>
            <div class="tm-modal-dialog tm-modal-dialog-entry">
                <div class="tm-modal-head">
                    <div class="tm-modal-head-stack">
                        <h3>Editar registro</h3>
                        <p class="tm-modal-subtitle">{{ $module->name }}</p>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        @if (isset($tmImportable) && $tmImportable->isNotEmpty())
                            <a href="{{ route('temporary-modules.download-template', $module->id) }}"
                               class="tm-btn tm-btn-outline"
                               aria-label="Descargar plantilla Excel">
                                <i class="fa-solid fa-download" aria-hidden="true"></i> Plantilla
                            </a>
                        @endif
                        <button type="button" class="tm-modal-close" data-close-module-preview aria-label="Cerrar">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="tm-modal-body">
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
                                                    <span class="tm-section-sub">{{ is_scalar($sub) ? $sub : '' }}</span>
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
                                        @php $catOpts = is_array($field->options) ? $field->options : []; @endphp
                                        <select id="{{ $id }}" name="{{ $name }}" {{ $field->is_required ? 'required' : '' }}>
                                            <option value="">Selecciona categoría</option>
                                            @foreach ($catOpts as $cat)
                                                @php $catName = $cat['name'] ?? ''; $subs = $cat['sub'] ?? []; @endphp
                                                @if ($catName !== '')
                                                    <option value="{{ $catName }}" @selected($value === $catName)>{{ $catName }}</option>
                                                    @foreach ($subs as $sub)
                                                        @php $subVal = $catName.' > '.(is_scalar($sub) ? $sub : ''); @endphp
                                                        <option value="{{ $subVal }}" @selected($value === $subVal)>{{ $catName }} &rarr; {{ is_scalar($sub) ? $sub : '' }}</option>
                                                    @endforeach
                                                @endif
                                            @endforeach
                                        </select>
                                    @elseif ($field->type === 'multiselect')
                                        @php $msOpts = is_array($field->options) ? $field->options : []; $msSelected = is_array($value) ? $value : []; @endphp
                                        <div class="tm-multiselect-wrap">
                                            @foreach ($msOpts as $msOpt)
                                                @if (is_scalar($msOpt))
                                                    <label class="tm-multiselect-option">
                                                        <input type="checkbox" name="{{ $name }}[]" value="{{ $msOpt }}" @checked(in_array($msOpt, $msSelected, true))>
                                                        <span>{{ $msOpt }}</span>
                                                    </label>
                                                @endif
                                            @endforeach
                                        </div>
                                    @elseif ($field->type === 'linked')
                                        @php
                                            $linkedOpts = is_array($field->options) ? $field->options : [];
                                            $primaryType = $linkedOpts['primary_type'] ?? 'text';
                                            $primaryLabel = $linkedOpts['primary_label'] ?? $field->label.' (principal)';
                                            $primaryOptions = $linkedOpts['primary_options'] ?? [];
                                            $secondaryType = $linkedOpts['secondary_type'] ?? 'text';
                                            $secondaryLabel = $linkedOpts['secondary_label'] ?? $field->label.' (dependiente)';
                                            $secondaryOptions = $linkedOpts['secondary_options'] ?? [];
                                            $existingLinked = is_array($value) ? $value : [];
                                            $primaryValue = $existingLinked['primary'] ?? null;
                                            $secondaryValue = $existingLinked['secondary'] ?? null;
                                        @endphp
                                        <div class="tm-linked-field-wrap" data-linked-field-group>
                                            <label class="tm-linked-primary-label">{{ $primaryLabel }} *</label>
                                            @if ($primaryType === 'select')
                                                <select name="{{ $name.'__primary' }}" data-linked-primary {{ $field->is_required ? 'required' : '' }}>
                                                    <option value="">Selecciona una opción</option>
                                                    @foreach ($primaryOptions as $opt)
                                                        <option value="{{ $opt }}" @selected($primaryValue === $opt)>{{ $opt }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input type="text" name="{{ $name.'__primary' }}" value="{{ $primaryValue }}" data-linked-primary {{ $field->is_required ? 'required' : '' }}>
                                            @endif
                                            <div class="tm-linked-secondary-wrap" data-linked-secondary-wrap {{ $primaryValue ? '' : 'hidden' }}>
                                                <label class="tm-linked-secondary-label">{{ $secondaryLabel }} *</label>
                                                @if ($secondaryType === 'select')
                                                    <select name="{{ $name.'__secondary' }}" data-linked-secondary {{ $primaryValue ? 'required' : 'disabled' }}>
                                                        <option value="">Selecciona una opción</option>
                                                        @foreach ($secondaryOptions as $opt)
                                                            <option value="{{ $opt }}" @selected($secondaryValue === $opt)>{{ $opt }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input type="text" name="{{ $name.'__secondary' }}" value="{{ $secondaryValue }}" data-linked-secondary {{ $primaryValue ? 'required' : 'disabled' }}>
                                                @endif
                                            </div>
                                        </div>
                                    @elseif ($field->type === 'textarea')
                                        <textarea id="{{ $id }}" name="{{ $name }}" rows="3" {{ $field->is_required ? 'required' : '' }}>{{ $value }}</textarea>
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
                                            @foreach ($entryMunicipios as $muni)
                                                <option value="{{ $muni }}" @selected($value === $muni)>{{ $muni }}</option>
                                            @endforeach
                                        </select>
                                    @elseif (in_array($field->type, ['image', 'file'], true))
                                        <div class="tm-upload-evidence">
                                            <input type="hidden" name="remove_images[{{ $field->key }}]" value="0" data-remove-flag>
                                            <div class="tm-upload-evidence-dropzone" data-paste-upload-wrap>
                                                <input id="{{ $id }}" type="file" accept="image/*" name="{{ $name }}" class="d-none" {{ ($field->is_required && !$hasExistingImage) ? 'required' : '' }}>
                                                <div class="tm-image-preview" data-image-preview {{ $hasExistingImage ? '' : 'hidden' }}>
                                                    <img src="{{ $hasExistingImage ? route('temporary-modules.entry-file.preview', ['module' => $module->id, 'entry' => $entry->id, 'fieldKey' => $field->key]) : '' }}" data-image-preview-img>
                                                    <button type="button" class="tm-image-clear" data-image-remove>&times;</button>
                                                </div>
                                                @unless ($hasExistingImage)
                                                    <div class="tm-upload-evidence-placeholder" data-upload-trigger data-target-input="{{ $id }}">
                                                        <i class="fa-solid fa-images"></i>
                                                        <p>Subir evidencia</p>
                                                    </div>
                                                @endunless
                                            </div>
                                        </div>
                                    @else
                                        <input id="{{ $id }}" type="text" name="{{ $name }}" value="{{ is_scalar($value) ? $value : '' }}" {{ $field->is_required ? 'required' : '' }}>
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
</div>
