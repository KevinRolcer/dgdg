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
            <details class="tm-record-card">
                <summary>
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
                    <th>Microrregión</th>
                    @foreach ($module->fields as $field)
                        <th>{{ $field->label }}</th>
                    @endforeach
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
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
                        <td><button type="button" class="tm-btn" data-open-module-preview="delegate-edit-{{ $entry->id }}">Editar</button></td>
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
</div>
