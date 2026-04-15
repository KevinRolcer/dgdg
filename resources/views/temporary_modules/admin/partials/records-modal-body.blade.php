                    @php
                        $moduleDescription = (string) ($module->description ?: 'Sin descripcion adicional.');
                        $isLongModuleDescription = mb_strlen($moduleDescription) > 180;
                    @endphp
                    @if ($isLongModuleDescription)
                        <p class="tm-cell-text-wrap" data-text-wrap>
                            <span class="tm-cell-text is-collapsed" data-text-content>{{ $moduleDescription }}</span>
                            <button type="button" class="tm-cell-text-toggle" data-text-toggle>Ver mas</button>
                        </p>
                    @else
                        <p>{{ $moduleDescription }}</p>
                    @endif

                    @can('Enlace')
                    <div class="tm-enlace-eliminar-registro mb-3">
                        <button type="button" class="tm-btn tm-btn-danger" id="btnEliminarRegistroEnlace">Eliminar registro de Municipios contestados hoy</button>
                    </div>
                    @endcan
                    @php
                        $municipiosMap = \App\Models\Municipio::with('microrregion')->get()->mapWithKeys(function ($m) {
                            $norm = \Illuminate\Support\Str::slug($m->nombre, '');
                            $mrName = $m->microrregion ? 'Microrregión ' . $m->microrregion->microrregion . ' - ' . $m->microrregion->cabecera : 'Sin microrregión asignada';
                            return [$norm => $mrName];
                        })->toArray();

                        $moduleFields = $module->fields;

                        $getMunicipioName = function ($entry) use ($moduleFields) {
                            $data = is_array($entry->data) ? $entry->data : (array)$entry->data;

                            // Iterate through all fields looking for "municipio"
                            foreach ($moduleFields as $field) {
                                $label = $field->label ?? $field['label'] ?? '';
                                $key = $field->key ?? $field['key'] ?? '';

                                if (stripos($label, 'municipio') !== false || stripos((string)$key, 'municipio') !== false) {
                                    $val = $data[$key] ?? null;
                                    if (is_string($val) && trim($val) !== '') {
                                        return trim($val);
                                    }
                                }
                            }
                            return 'Sin municipio especificado';
                        };

                        $getMicrorregionName = function ($entry) use ($getMunicipioName, $municipiosMap) {
                            // Option 1: It has an explicit microrregion relation already saved
                            if ($entry->microrregion && $entry->microrregion->microrregion) {
                                return 'Microrregión ' . $entry->microrregion->microrregion . ' - ' . $entry->microrregion->cabecera;
                            }
                            // Option 2: Fallback to mapping the text municipio from $entry->data
                            $mpioName = $getMunicipioName($entry);
                            if ($mpioName !== 'Sin municipio especificado') {
                                $norm = \Illuminate\Support\Str::slug($mpioName, '');
                                if (isset($municipiosMap[$norm])) {
                                    return $municipiosMap[$norm];
                                }
                            }
                            return 'Sin microrregión asignada';
                        };

                        // First group by Microrregión (Using explicit ID or mapped from string)
                        $groupedByMr = collect($module->entries)->groupBy($getMicrorregionName);

                        // Calculate unique municipalities
                        $uniqueMpiosCount = collect($module->entries)->map($getMunicipioName)->filter(function($mp) {
                            return $mp !== 'Sin municipio especificado';
                        })->unique()->count();

                    @endphp

                    <div class="tm-apr-bar" data-admin-preview-meta-row>
                        <div class="tm-apr-stats">
                            <span class="tm-apr-stat">
                                <i class="fa-solid fa-list-ol" aria-hidden="true"></i>
                                <strong>{{ $module->entries->count() }}</strong> registro(s)
                            </span>
                            <span class="tm-apr-stat">
                                <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
                                <strong>{{ $uniqueMpiosCount }}</strong> municipio(s)
                            </span>
                        </div>

                        <div class="tm-apr-filters" data-admin-preview-filters>
                            <div class="tm-apr-search-wrap">
                                <input
                                    type="search"
                                    class="tm-apr-search-input"
                                    placeholder="Buscar en registros…"
                                    autocomplete="off"
                                    data-admin-preview-filter-text>
                            </div>

                            <small class="tm-apr-count" data-admin-preview-filter-count></small>

                            <button
                                type="button"
                                class="tm-btn tm-btn-ghost tm-btn-sm tm-apr-clear-btn"
                                data-admin-preview-filter-clear
                                title="Limpiar filtros">
                                <i class="fa-solid fa-times" aria-hidden="true"></i> Limpiar
                            </button>
                            <div class="tm-apr-view-btns" role="group" aria-label="Modo de vista">
                                <button type="button" class="tm-apr-view-btn is-active" data-admin-preview-view="cards" title="Tarjetas">
                                    <i class="fa-solid fa-grip" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="tm-apr-view-btn" data-admin-preview-view="list" title="Listado">
                                    <i class="fa-solid fa-list" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="tm-apr-view-btn" data-admin-preview-view="table" title="Tabla">
                                    <i class="fa-solid fa-table" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    @if ($module->entries->isEmpty())
                        <div class="tm-table-wrap tm-table-wrap-admin-preview">
                            <p style="text-align: center; padding: 20px;">Sin registros capturados todavía.</p>
                        </div>
                    @else
                        @php
                            $sortedEntries = collect($module->entries)->sortByDesc('submitted_at')->values();
                            $orderedFields = $module->fields
                                ->sortBy(function ($field) {
                                    return in_array($field->type, ['image', 'file', 'document'], true) ? 1 : 0;
                                })
                                ->values();
                            $totalNonMediaCount = $orderedFields->filter(fn ($f) => !in_array($f->type, ['image', 'file', 'document'], true))->count();
                            $maxPreviewFields = $totalNonMediaCount <= 5 ? $totalNonMediaCount : 5;
                            $nonMediaFieldsAll = $orderedFields->filter(fn ($f) => !in_array($f->type, ['image', 'file', 'document'], true))->values();
                            $mediaFieldsAll = $orderedFields->filter(fn ($f) => in_array($f->type, ['image', 'file', 'document'], true))->values();
                        @endphp

                        <div data-apr-views-wrap>
                        {{-- VISTA: LISTADO --}}
                        <div data-admin-records-view="list" hidden>
                        <div class="tm-admin-preview-accordion" data-admin-preview-accordion-legacy>
                            @foreach ($groupedByMr as $mrName => $mrEntries)
                                @php
                                    $mrLatestEntry = $mrEntries->sortByDesc('submitted_at')->first();
                                    $mrUsers = $mrEntries->pluck('user.name')->filter()->unique()->implode(', ');

                                    // Second group by Municipio
                                    $groupedByMpio = $mrEntries->groupBy($getMunicipioName);
                                @endphp
                                <details class="tm-admin-preview-card" data-admin-mr-group data-mr-name="{{ $mrName }}">
                                    <summary class="tm-admin-preview-summary">
                                        <div class="tm-preview-summary-header">
                                            <strong class="tm-preview-mr-name">{{ $mrName }}</strong>
                                            <span class="tm-badge tm-badge-primary" style="margin-left:8px;">{{ $mrEntries->count() }} registro(s)</span>
                                        </div>
                                        <div class="tm-preview-summary-meta">
                                            <span title="Usuario(s)"><i class="fa fa-user"></i> {{ $mrUsers ?: 'Sin usuario' }}</span>
                                            <span title="Última captura"><i class="fa fa-calendar"></i> {{ optional($mrLatestEntry->submitted_at)->format('d/m/Y H:i') }}</span>
                                        </div>
                                        <div class="tm-preview-summary-icon">
                                            <i class="fa-solid fa-chevron-down"></i>
                                        </div>
                                    </summary>

                                    <div class="tm-admin-preview-detail tm-admin-preview-mpio-container">
                                        @foreach ($groupedByMpio as $mpioName => $mpioEntries)
                                            <details class="tm-mpio-group-details" data-admin-mpio-group data-mr-name="{{ $mrName }}" data-mpio-name="{{ $mpioName }}">
                                                <summary class="tm-mpio-summary">
                                                    <div class="tm-mpio-summary-header">
                                                        <i class="fa-solid fa-map-location-dot"></i> <strong>{{ $mpioName }}</strong>
                                                        <span class="tm-badge tm-badge-secondary" style="margin-left: 8px;">{{ $mpioEntries->count() }} registro(s)</span>
                                                    </div>
                                                    <i class="fa-solid fa-chevron-down tm-mpio-chevron"></i>
                                                </summary>
                                                <div class="tm-mpio-detail-content">
                                                    <div class="tm-records-grid">
                                                        @foreach ($mpioEntries as $entry)
                                                            <div class="tm-record-item" data-admin-record-legacy data-mr-name="{{ $mrName }}" data-mpio-name="{{ $mpioName }}" data-user-name="{{ $entry->user->name ?? 'Sin usuario' }}">
                                                            <div class="tm-record-item-header">
                                                                <span class="tm-record-user"><i class="fa-solid fa-user-circle"></i> {{ $entry->user->name ?? 'Sin usuario' }}</span>
                                                                <span class="tm-record-date"><i class="fa-regular fa-clock"></i> {{ optional($entry->submitted_at)->format('d/m/Y H:i') }}</span>
                                                            </div>
                                                            <div class="tm-record-item-body">
                                                                @foreach ($module->fields as $field)
                                                                    @php
                                                                        $cell = $entry->data[$field->key] ?? null;
                                                                    @endphp
                                                                    <div class="tm-record-field">
                                                                        <div class="tm-record-label">{{ $field->label }}</div>
                                                                        <div class="tm-record-value">
                                                                            @php
                                                                                $mediaPaths = in_array($field->type, ['file', 'image', 'document'], true)
                                                                                    ? (is_array($cell)
                                                                                        ? array_values(array_filter($cell, fn ($path) => is_string($path) && trim($path) !== ''))
                                                                                        : ((is_string($cell) && trim($cell) !== '') ? [trim($cell)] : []))
                                                                                    : [];
                                                                            @endphp
                                                                            @if (count($mediaPaths) > 0)
                                                                                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                                                                    @foreach ($mediaPaths as $imageIndex => $imagePath)
                                                                                        <button
                                                                                            type="button"
                                                                                            class="tm-thumb-link"
                                                                                            data-open-image-preview
                                                                                            data-image-src="{{ route('temporary-modules.entry-file.preview', ['module' => $module->id, 'entry' => $entry->id, 'fieldKey' => $field->key, 'i' => $imageIndex]) }}"
                                                                                            data-image-title="{{ $field->label }}{{ count($mediaPaths) > 1 ? ' ('.($imageIndex + 1).')' : '' }}"
                                                                                            title="{{ count($mediaPaths) > 1 ? 'Ver imagen '.($imageIndex + 1) : 'Ver imagen' }}"
                                                                                        >
                                                                                            <i class="fa fa-image" aria-hidden="true"></i> {{ count($mediaPaths) > 1 ? 'Imagen '.($imageIndex + 1) : 'Ver imagen' }}
                                                                                        </button>
                                                                                    @endforeach
                                                                                </div>
                                                                            @elseif (is_bool($cell))
                                                                                {{ $cell ? 'Sí' : 'No' }}
                                                                            @elseif ($field->type === 'semaforo' && is_string($cell) && $cell !== '')
                                                                                @php $semLab = \App\Services\TemporaryModules\TemporaryModuleFieldService::labelForSemaforo($cell); @endphp
                                                                                <span class="tm-semaforo-badge tm-semaforo-badge--{{ $cell }}" title="{{ $semLab }}">{{ $semLab }}</span>
                                                                            @else
                                                                                @php
                                                                                    if (is_array($cell)) {
                                                                                        $displayText = implode(', ', array_map(function ($item) {
                                                                                            return is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE);
                                                                                        }, $cell));
                                                                                    } elseif (is_scalar($cell)) {
                                                                                        $displayText = (string) $cell;
                                                                                    } else {
                                                                                        $displayText = '-';
                                                                                    }
                                                                                    $displayText = trim($displayText) !== '' ? $displayText : '-';
                                                                                @endphp
                                                                                <span class="tm-cell-text-wrap" data-text-wrap>
                                                                                    <span class="tm-cell-text is-collapsed" data-text-content>{{ $displayText }}</span>
                                                                                    @if (mb_strlen($displayText) > 120)
                                                                                        <span class="tm-cell-actions">
                                                                                            <button type="button" class="tm-cell-text-toggle" data-text-toggle>Ver mas</button>
                                                                                        </span>
                                                                                    @endif
                                                                                </span>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                            </details>
                                        @endforeach
                                    </div>
                                </details>
                            @endforeach
                        </div>{{-- /tm-admin-preview-accordion --}}
                        </div>{{-- /view:list --}}

                        {{-- VISTA: TARJETAS --}}
                        <div data-admin-records-view="cards">
                        <div class="tm-admin-records-grid" data-admin-records-grid>
                            @foreach ($sortedEntries as $entry)
                                @php
                                    $mrName = $getMicrorregionName($entry);
                                    $mpioName = $getMunicipioName($entry);
                                    $userName = $entry->user->name ?? 'Sin usuario';
                                    $submittedAt = optional($entry->submitted_at)->format('d/m/Y H:i') ?? '';

                                    $nonMediaFields = $orderedFields->filter(fn ($f) => !in_array($f->type, ['image', 'file', 'document'], true))->values();
                                    $mediaFields = $orderedFields->filter(fn ($f) => in_array($f->type, ['image', 'file', 'document'], true))->values();
                                    $previewFields = $nonMediaFields->take($maxPreviewFields);
                                    $extraFields = $nonMediaFields->slice($maxPreviewFields)->values();
                                    $extraCount = $extraFields->count();
                                    $hasAnyMedia = $mediaFields->contains(function ($f) use ($entry) {
                                        $cell = $entry->data[$f->key] ?? null;
                                        $paths = is_array($cell)
                                            ? array_values(array_filter($cell, fn ($p) => is_string($p) && trim($p) !== ''))
                                            : ((is_string($cell) && trim($cell) !== '') ? [trim($cell)] : []);
                                        return count($paths) > 0;
                                    });
                                    $hasMore = $extraCount > 0 || $hasAnyMedia;
                                @endphp
                                <article class="tm-admin-record-card" data-admin-record data-mr-name="{{ $mrName }}" data-mpio-name="{{ $mpioName }}" data-user-name="{{ $userName }}">
                                    <header class="tm-admin-record-card-head">
                                        <div class="tm-admin-record-card-top">
                                            <div class="tm-admin-record-card-title">
                                                <span class="tm-badge tm-badge-secondary">MR</span>
                                                <strong title="{{ $mrName }}">{{ $mrName }}</strong>
                                            </div>
                                            @if ($mpioName !== 'Sin municipio especificado')
                                                <span class="tm-badge tm-badge-primary" title="Municipio">{{ $mpioName }}</span>
                                            @endif
                                        </div>
                                        <div class="tm-admin-record-card-meta">
                                            <span title="Usuario"><i class="fa-solid fa-user-circle" aria-hidden="true"></i> {{ $userName }}</span>
                                            @if ($submittedAt !== '')
                                                <span title="Fecha"><i class="fa-regular fa-clock" aria-hidden="true"></i> {{ $submittedAt }}</span>
                                            @endif
                                        </div>
                                    </header>

                                    <div class="tm-admin-record-card-body">
                                        <dl class="tm-admin-record-fields">
                                            @foreach ($previewFields as $field)
                                                @php $cell = $entry->data[$field->key] ?? null; @endphp
                                                <div class="tm-admin-record-field">
                                                    <dt>{{ $field->label }}</dt>
                                                    <dd>
                                                        @if (is_bool($cell))
                                                            {{ $cell ? 'Sí' : 'No' }}
                                                        @elseif ($field->type === 'semaforo' && is_string($cell) && $cell !== '')
                                                            @php $semLab = \App\Services\TemporaryModules\TemporaryModuleFieldService::labelForSemaforo($cell); @endphp
                                                            <span class="tm-semaforo-badge tm-semaforo-badge--{{ $cell }}" title="{{ $semLab }}">{{ $semLab }}</span>
                                                        @else
                                                            @php
                                                                if (is_array($cell)) {
                                                                    $displayText = implode(', ', array_map(function ($item) {
                                                                        return is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE);
                                                                    }, $cell));
                                                                } elseif (is_scalar($cell)) {
                                                                    $displayText = (string) $cell;
                                                                } else {
                                                                    $displayText = '-';
                                                                }
                                                                $displayText = trim($displayText) !== '' ? $displayText : '-';
                                                            @endphp
                                                            {{ $displayText }}
                                                        @endif
                                                    </dd>
                                                </div>
                                            @endforeach
                                        </dl>

                                        @if ($hasMore)
                                            <div hidden data-admin-record-more>
                                                @if ($extraFields->isNotEmpty())
                                                    <dl class="tm-admin-record-fields" style="margin-top:10px;">
                                                        @foreach ($extraFields as $field)
                                                            @php $cell = $entry->data[$field->key] ?? null; @endphp
                                                            <div class="tm-admin-record-field">
                                                                <dt>{{ $field->label }}</dt>
                                                                <dd>
                                                                    @if (is_bool($cell))
                                                                        {{ $cell ? 'Sí' : 'No' }}
                                                                    @elseif ($field->type === 'semaforo' && is_string($cell) && $cell !== '')
                                                                        @php $semLab = \App\Services\TemporaryModules\TemporaryModuleFieldService::labelForSemaforo($cell); @endphp
                                                                        <span class="tm-semaforo-badge tm-semaforo-badge--{{ $cell }}" title="{{ $semLab }}">{{ $semLab }}</span>
                                                                    @else
                                                                        @php
                                                                            if (is_array($cell)) {
                                                                                $displayText = implode(', ', array_map(function ($item) {
                                                                                    return is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE);
                                                                                }, $cell));
                                                                            } elseif (is_scalar($cell)) {
                                                                                $displayText = (string) $cell;
                                                                            } else {
                                                                                $displayText = '-';
                                                                            }
                                                                            $displayText = trim($displayText) !== '' ? $displayText : '-';
                                                                        @endphp
                                                                        {{ $displayText }}
                                                                    @endif
                                                                </dd>
                                                            </div>
                                                        @endforeach
                                                    </dl>
                                                @endif

                                                @foreach ($mediaFields as $field)
                                                    @php
                                                        $cell = $entry->data[$field->key] ?? null;
                                                        $mediaPaths = (is_array($cell)
                                                            ? array_values(array_filter($cell, fn ($p) => is_string($p) && trim($p) !== ''))
                                                            : ((is_string($cell) && trim($cell) !== '') ? [trim($cell)] : []));
                                                    @endphp
                                                    @if (count($mediaPaths) > 0)
                                                        <div style="display:flex; flex-direction:column; gap:6px; margin-top:10px;">
                                                            <div style="font-size:0.78rem; font-weight:700; color: var(--clr-text-muted);">{{ $field->label }}</div>
                                                            <div class="tm-admin-record-media">
                                                                @foreach ($mediaPaths as $imageIndex => $imagePath)
                                                                    @if ($field->type === 'image')
                                                                        <button
                                                                            type="button"
                                                                            class="tm-thumb-link"
                                                                            data-open-image-preview
                                                                            data-image-src="{{ route('temporary-modules.entry-file.preview', ['module' => $module->id, 'entry' => $entry->id, 'fieldKey' => $field->key, 'i' => $imageIndex]) }}"
                                                                            data-image-title="{{ $field->label }}{{ count($mediaPaths) > 1 ? ' ('.($imageIndex + 1).')' : '' }}"
                                                                            title="{{ count($mediaPaths) > 1 ? 'Ver imagen '.($imageIndex + 1) : 'Ver imagen' }}"
                                                                        >
                                                                            <img src="{{ route('temporary-modules.entry-file.preview', ['module' => $module->id, 'entry' => $entry->id, 'fieldKey' => $field->key, 'i' => $imageIndex]) }}" alt="Imagen {{ $imageIndex + 1 }}">
                                                                        </button>
                                                                    @else
                                                                        <button type="button" class="tm-thumb-link" data-open-file-preview="1"
                                                                            data-file-src="{{ route('temporary-modules.entry-file.preview', ['module' => $module->id, 'entry' => $entry->id, 'fieldKey' => $field->key, 'i' => $imageIndex]) }}"
                                                                            data-file-title="{{ $field->label }}{{ count($mediaPaths) > 1 ? ' ('.($imageIndex + 1).')' : '' }}"
                                                                            title="Ver documento">
                                                                            <i class="fa-solid fa-file-lines" aria-hidden="true"></i> Ver documento
                                                                        </button>
                                                                    @endif
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    <footer class="tm-admin-record-card-foot">
                                        <span class="tm-admin-record-more">
                                            @if ($extraCount > 0)
                                                +{{ $extraCount }} campo(s)
                                            @else
                                                &nbsp;
                                            @endif
                                        </span>
                                        @if ($hasMore)
                                            <button type="button" class="tm-btn tm-btn-outline tm-btn-sm tm-admin-record-toggle" data-admin-record-toggle>
                                                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                                                <span data-admin-record-toggle-text>Ver todo</span>
                                            </button>
                                        @endif
                                    </footer>
                                </article>
                            @endforeach
                        </div>{{-- /tm-admin-records-grid --}}
                        </div>{{-- /view:cards --}}

                        {{-- VISTA: TABLA --}}
                        <div data-admin-records-view="table" hidden>
                            @php $groupedByMpioTable = $sortedEntries->groupBy($getMunicipioName); @endphp
                            @forelse ($groupedByMpioTable as $tMpioName => $tMpioEntries)
                                <div class="tm-admin-table-mpio-section" data-admin-table-mpio-section>
                                    <h4 class="tm-admin-table-mpio-title">
                                        <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
                                        {{ $tMpioName }}
                                        <span class="tm-badge tm-badge-secondary">{{ $tMpioEntries->count() }}</span>
                                    </h4>
                                    <div class="tm-table-wrap" style="overflow-x:auto;">
                                        <table class="tm-table tm-admin-records-table">
                                            <thead>
                                                <tr>
                                                    <th>Microrregión</th>
                                                    <th>Usuario</th>
                                                    <th>Fecha</th>
                                                    @foreach ($nonMediaFieldsAll as $tHeader)
                                                        <th>{{ $tHeader->label }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($tMpioEntries as $tEntry)
                                                    @php
                                                        $tMrName = $getMicrorregionName($tEntry);
                                                        $tUserName = $tEntry->user->name ?? 'Sin usuario';
                                                        $tDate = optional($tEntry->submitted_at)->format('d/m/Y H:i') ?? '';
                                                    @endphp
                                                    <tr data-admin-records-table-row data-mr-name="{{ $tMrName }}" data-mpio-name="{{ $tMpioName }}" data-user-name="{{ $tUserName }}">
                                                        <td>{{ $tMrName }}</td>
                                                        <td>{{ $tUserName }}</td>
                                                        <td>{{ $tDate }}</td>
                                                        @foreach ($nonMediaFieldsAll as $tField)
                                                            @php
                                                                $tCell = $tEntry->data[$tField->key] ?? null;
                                                                if (is_array($tCell)) {
                                                                    $tText = implode(', ', array_map(fn ($ti) => is_scalar($ti) ? (string) $ti : json_encode($ti, JSON_UNESCAPED_UNICODE), $tCell));
                                                                } elseif (is_scalar($tCell)) {
                                                                    $tText = (string) $tCell;
                                                                } else {
                                                                    $tText = '-';
                                                                }
                                                                $tText = trim($tText) !== '' ? $tText : '-';
                                                            @endphp
                                                            <td>
                                                                @if (is_bool($tCell))
                                                                    {{ $tCell ? 'Sí' : 'No' }}
                                                                @elseif ($tField->type === 'semaforo' && is_string($tCell) && $tCell !== '')
                                                                    @php $tSemLab = \App\Services\TemporaryModules\TemporaryModuleFieldService::labelForSemaforo($tCell); @endphp
                                                                    <span class="tm-semaforo-badge tm-semaforo-badge--{{ $tCell }}">{{ $tSemLab }}</span>
                                                                @else
                                                                    {{ $tText }}
                                                                @endif
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @empty
                                <p style="text-align:center;padding:20px;">Sin registros para mostrar.</p>
                            @endforelse
                        </div>{{-- /view:table --}}

                        </div>{{-- /data-apr-views-wrap --}}
                    @endif
