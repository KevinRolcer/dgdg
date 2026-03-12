@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endpush

@section('content')
<section class="tm-page">
    @if (session('status'))
        <div class="inline-alert inline-alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <article class="content-card tm-card">
        <div class="tm-head">
            <div>
                <h2>Registros de módulos temporales</h2>
                <p>Visualiza módulos con registros capturados, vista previa y exportación a Excel.</p>
            </div>
            <div class="tm-inline-actions">
                <a href="{{ route('temporary-modules.admin.index') }}" class="tm-btn">Gestión de módulos</a>
                <a href="{{ route('temporary-modules.admin.create') }}" class="tm-btn tm-btn-primary">Nuevo módulo</a>
            </div>
        </div>

        <div class="tm-table-wrap tm-table-wrap-scroll">
            <table class="tm-table">
                <thead>
                    <tr>
                        <th>Módulo</th>
                        <th>Vigencia</th>
                        <th>Registros</th>
                        <th>Campos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($modules as $module)
                        <tr>
                            <td>
                                <strong>{{ $module->name }}</strong>
                                @php
                                    $moduleDescription = (string) ($module->description ?: 'Sin descripcion adicional.');
                                    $isLongModuleDescription = mb_strlen($moduleDescription) > 120;
                                @endphp
                                @if ($isLongModuleDescription)
                                    <small class="tm-cell-text-wrap" data-text-wrap>
                                        <span class="tm-cell-text is-collapsed" data-text-content>{{ $moduleDescription }}</span>
                                        <button type="button" class="tm-cell-text-toggle" data-text-toggle>Ver mas</button>
                                    </small>
                                @else
                                    <small>{{ $moduleDescription }}</small>
                                @endif
                            </td>
                            <td>{{ optional($module->expires_at)->format('d/m/Y H:i') ?? 'Sin límite' }}</td>
                            <td>{{ $module->entries_count }}</td>
                            <td>{{ $module->fields_count }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="tm-btn tm-btn-success"
                                    data-open-export-options
                                    data-export-url="{{ route('temporary-modules.admin.export', $module->id) }}"
                                    data-structure-url="{{ route('temporary-modules.admin.export-preview-structure', $module->id) }}"
                                >
                                    Exportar Excel
                                </button>
                                <button
                                    type="button"
                                    class="tm-btn"
                                    data-open-module-preview="admin-preview-{{ $module->id }}"
                                >
                                    Vista previa
                                </button>
                                <form method="POST" action="{{ route('temporary-modules.admin.clear-entries', $module->id) }}" class="tm-inline-form" id="tmClearEntriesForm-{{ $module->id }}">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="button"
                                        class="tm-btn tm-btn-danger"
                                        data-clear-module-entries
                                        data-form-id="tmClearEntriesForm-{{ $module->id }}"
                                        data-module-name="{{ $module->name }}"
                                    >
                                        Vaciar registros
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No hay módulos con registros capturados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (method_exists($modules, 'links'))
            <div class="tm-pagination-wrap">
                {{ $modules->links() }}
            </div>
        @endif
    </article>

    @foreach ($modules as $module)
        <div class="tm-modal" id="admin-preview-{{ $module->id }}" aria-hidden="true" role="dialog" aria-modal="true">
            <div class="tm-modal-backdrop" data-close-module-preview></div>
            <div class="tm-modal-dialog">
                <div class="tm-modal-head">
                    <h3>{{ $module->name }}</h3>
                    <button type="button" class="tm-modal-close" data-close-module-preview>&times;</button>
                </div>

                <div class="tm-modal-body">
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

                    <h4>Registros de delegados</h4>
                    @can('Enlace')
                    <div class="tm-enlace-eliminar-registro mb-3">
                        <button type="button" class="tm-btn tm-btn-danger" id="btnEliminarRegistroEnlace">Eliminar registro de Municipios contestados hoy</button>
                    </div>
                    @endcan
                    <div class="tm-table-wrap tm-table-wrap-admin-preview">
                        <table class="tm-table tm-admin-preview-table">
                            <thead>
                                <tr>
                                    <th>Delegado</th>
                                    <th>Fecha</th>
                                    @foreach ($module->fields as $field)
                                        <th>
                                            <span class="tm-admin-col-title" title="{{ $field->label }}">{{ $field->label }}</span>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($module->entries as $entry)
                                    <tr>
                                        <td>{{ $entry->user->name ?? 'Sin usuario' }}</td>
                                        <td>{{ optional($entry->submitted_at)->format('d/m/Y H:i') }}</td>
                                        @foreach ($module->fields as $field)
                                            @php
                                                $cell = $entry->data[$field->key] ?? null;
                                                $columnIndex = $loop->index + 3;
                                            @endphp
                                            <td data-admin-col="{{ $columnIndex }}">
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
                                                    {{ $cell ? 'Sí' : 'No' }}
                                                @else
                                                    @php
                                                        if (is_array($cell)) {
                                                            $displayText = implode(', ', array_map(function ($item) {
                                                                return is_scalar($item)
                                                                    ? (string) $item
                                                                    : json_encode($item, JSON_UNESCAPED_UNICODE);
                                                            }, $cell));
                                                        } elseif (is_scalar($cell)) {
                                                            $displayText = (string) $cell;
                                                        } else {
                                                            $displayText = '-';
                                                        }
                                                        $displayText = trim($displayText) !== '' ? $displayText : '-';
                                                        $isLongText = mb_strlen($displayText) > 120;
                                                    @endphp
                                                    <span class="tm-cell-text-wrap tm-cell-text-wrap-admin" data-text-wrap>
                                                        <span class="tm-cell-text is-collapsed" data-text-content>{{ $displayText }}</span>
                                                        <span class="tm-cell-actions">
                                                            @if ($isLongText)
                                                                <button type="button" class="tm-cell-text-toggle" data-text-toggle>Ver mas</button>
                                                            @endif
                                                            <button
                                                                type="button"
                                                                class="tm-cell-expand-toggle"
                                                                data-cell-expand
                                                                data-col-index="{{ $columnIndex }}"
                                                                title="Expandir celda"
                                                                aria-label="Expandir celda"
                                                            >
                                                                <i class="fa-solid fa-left-right" aria-hidden="true"></i>
                                                            </button>
                                                        </span>
                                                    </span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $module->fields->count() + 2 }}">Sin registros capturados todavía.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

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

    <div class="tm-modal tm-export-personalize-modal" id="tmExportPersonalizeModal" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="tm-modal-backdrop" data-close-export-personalize></div>
        <div class="tm-modal-dialog tm-export-personalize-dialog">
            <div class="tm-modal-head">
                <h3>Personalizar vista previa del Excel</h3>
                <button type="button" class="tm-modal-close" data-close-export-personalize>&times;</button>
            </div>
            <div class="tm-modal-body">
                <div class="tm-export-personalize-loading" id="tmExportPersonalizeLoading">Cargando estructura…</div>
                <div class="tm-export-personalize-content" id="tmExportPersonalizeContent" hidden>
                    <div class="tm-export-personalize-field">
                        <label for="tmExportPersonalizeTitle">Título del documento</label>
                        <input type="text" id="tmExportPersonalizeTitle" class="tm-input" placeholder="Nombre del módulo">
                    </div>
                    <div class="tm-export-personalize-field tm-export-title-align">
                        <span class="tm-export-label-inline">Alineación del título</span>
                        <div class="tm-export-align-btns" role="group" aria-label="Alineación del título">
                            <button type="button" class="tm-export-align-btn is-active" data-title-align="left">Izq</button>
                            <button type="button" class="tm-export-align-btn" data-title-align="center">Centro</button>
                            <button type="button" class="tm-export-align-btn" data-title-align="right">Der</button>
                        </div>
                    </div>
                    <p class="tm-export-personalize-hint">Arrastra las columnas para cambiar el orden. Usa &times; para omitir una columna del reporte.</p>
                    <div class="tm-export-personalize-columns" id="tmExportPersonalizeColumns" role="list"></div>
                    <p class="tm-export-restore-wrap" id="tmExportRestoreWrap" hidden>
                        <button type="button" class="tm-export-restore-btn" id="tmExportRestoreBtn">Restaurar todas las columnas</button>
                    </p>
                    <div class="tm-export-personalize-preview-wrap tm-export-preview-a4">
                        <div class="tm-export-personalize-preview" id="tmExportPersonalizePreview"></div>
                    </div>
                </div>
            </div>
            <div class="tm-modal-foot">
                <button type="button" class="tm-btn tm-btn-outline" data-close-export-personalize>Cancelar</button>
                <button type="button" class="tm-btn tm-btn-success" id="tmExportPersonalizeApply">Exportar con esta configuración</button>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
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

        const openButtons = Array.from(document.querySelectorAll('[data-open-module-preview]'));
        const clearButtons = Array.from(document.querySelectorAll('[data-clear-module-entries]'));
        const imagePreviewButtons = Array.from(document.querySelectorAll('[data-open-image-preview]'));
        const textToggleButtons = Array.from(document.querySelectorAll('[data-text-toggle]'));
        const cellExpandButtons = Array.from(document.querySelectorAll('[data-cell-expand]'));
        const exportButtons = Array.from(document.querySelectorAll('[data-open-export-options]'));
        const personalizeModal = document.getElementById('tmExportPersonalizeModal');
        const imageModal = document.getElementById('tmImagePreviewModal');
        const imageModalImg = document.getElementById('tmImagePreviewImg');
        const imageModalTitle = document.getElementById('tmImagePreviewTitle');
        let lastImageOpener = null;
        const templateSwal = createTemplateSwal();

        const closeModal = function (modal) {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };

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
            if (imageModalImg) {
                imageModalImg.removeAttribute('src');
            }

            const hasAnyModuleModalOpen = Array.from(document.querySelectorAll('.tm-modal.is-open'))
                .some(function (modal) { return modal !== imageModal; });
            if (!hasAnyModuleModalOpen) {
                document.body.style.overflow = '';
            }

            if (lastImageOpener instanceof HTMLElement) {
                lastImageOpener.focus();
            }
        };

        openButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const modalId = button.getAttribute('data-open-module-preview');
                const modal = modalId ? document.getElementById(modalId) : null;
                if (!modal) {
                    return;
                }

                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            });
        });

        textToggleButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const wrap = button.closest('[data-text-wrap]');
                const content = wrap ? wrap.querySelector('[data-text-content]') : null;
                if (!(content instanceof HTMLElement)) {
                    return;
                }

                const isCollapsed = content.classList.contains('is-collapsed');
                content.classList.toggle('is-collapsed', !isCollapsed);
                button.textContent = isCollapsed ? 'Ver menos' : 'Ver mas';
            });
        });

        cellExpandButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const colIndex = parseInt(button.getAttribute('data-col-index') || '', 10);
                const row = button.closest('tr');
                if (!row || Number.isNaN(colIndex)) {
                    return;
                }

                const targetCell = row.querySelector('td[data-admin-col="' + String(colIndex) + '"]');
                if (!(targetCell instanceof HTMLTableCellElement)) {
                    return;
                }

                const wasExpanded = targetCell.classList.contains('is-expanded-cell');
                const cells = Array.from(row.querySelectorAll('td[data-admin-col]'));

                cells.forEach(function (cell) {
                    cell.classList.remove('is-expanded-cell', 'is-condensed-cell');
                });

                if (!wasExpanded) {
                    targetCell.classList.add('is-expanded-cell');
                    cells.forEach(function (cell) {
                        if (cell !== targetCell) {
                            cell.classList.add('is-condensed-cell');
                        }
                    });
                }
            });
        });

        clearButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const formId = button.getAttribute('data-form-id');
                const moduleName = button.getAttribute('data-module-name') || 'este módulo';
                const form = formId ? document.getElementById(formId) : null;
                if (!form) {
                    return;
                }

                const submitAction = function () {
                    form.submit();
                };

                if (!templateSwal) {
                    submitAction();
                    return;
                }

                templateSwal.fire({
                    title: '¿Vaciar registros de ' + moduleName + '?',
                    text: 'Se eliminarán todos los registros capturados de "' + moduleName + '". Esta acción no se puede deshacer.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, vaciar',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true
                }).then(function (result) {
                    if (result.isConfirmed) {
                        submitAction();
                    }
                });
            });
        });

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

        Array.from(document.querySelectorAll('.tm-modal')).forEach(function (modal) {
            Array.from(modal.querySelectorAll('[data-close-module-preview]')).forEach(function (button) {
                button.addEventListener('click', function () {
                    closeModal(modal);
                });
            });
        });

        Array.from(document.querySelectorAll('[data-close-image-preview]')).forEach(function (button) {
            button.addEventListener('click', closeImageModal);
        });

        const TEMPLATE_COLORS = [
            { name: 'Encabezado', value: '#861e34' },
            { name: 'Blanco', value: '#ffffff' },
            { name: 'Gris claro', value: '#f5f5f5' },
            { name: 'Gris', value: '#e8e8e8' }
        ];

        const closePersonalizeModal = function () {
            if (!personalizeModal) { return; }
            personalizeModal.classList.remove('is-open');
            personalizeModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };

        Array.from(document.querySelectorAll('[data-close-export-personalize]')).forEach(function (el) {
            el.addEventListener('click', closePersonalizeModal);
        });

        if (personalizeModal) {
            personalizeModal.addEventListener('click', function (e) {
                var alignBtn = e.target.closest('.tm-export-align-btn');
                if (!alignBtn) { return; }
                var cols = personalizeModal._personalizeColumns;
                if (!cols) { return; }
                personalizeModal.querySelectorAll('.tm-export-align-btn').forEach(function (b) { b.classList.remove('is-active'); });
                alignBtn.classList.add('is-active');
                var columnsEl = document.getElementById('tmExportPersonalizeColumns');
                var previewEl = document.getElementById('tmExportPersonalizePreview');
                if (columnsEl && previewEl) { buildPersonalizePreview(reorderColumnsList(columnsEl, cols), previewEl); }
            });
        }

        function buildPersonalizeColumnsList(columns, container) {
            container.innerHTML = '';
            columns.forEach(function (col, index) {
                const item = document.createElement('div');
                item.className = 'tm-export-personalize-col' + (col.is_image ? ' is-image' : '');
                item.setAttribute('role', 'listitem');
                item.dataset.key = col.key;
                item.dataset.index = String(index);
                item.draggable = true;
                item.innerHTML =
                    '<span class="tm-export-drag-handle" aria-hidden="true">&#9776;</span>' +
                    '<span class="tm-export-col-label">' + escapeHtml(col.label) + '</span>' +
                    (col.is_image
                        ? '<div class="tm-export-col-image-opts">' +
                            '<label>Ancho <input type="number" min="40" max="400" value="120" class="tm-export-image-width" data-key="' + escapeHtml(col.key) + '"></label>' +
                            '<label>Alto <input type="number" min="30" max="300" value="' + String(col.image_height || 80) + '" class="tm-export-image-height" data-key="' + escapeHtml(col.key) + '"></label>' +
                            '</div>'
                        : '<div class="tm-export-col-width-preview" style="min-width:' + Math.min(col.max_width_chars || 10, 40) + 'ch" title="Ancho aprox. ' + (col.max_width_chars || 10) + ' caracteres">' +
                            '<span class="tm-export-col-width-num">' + (col.max_width_chars || 10) + '</span> ch' +
                            '</div>') +
                    '<div class="tm-export-col-color">' +
                    TEMPLATE_COLORS.map(function (c, i) {
                        return '<button type="button" class="tm-export-color-swatch' + (i === 0 ? ' is-active' : '') + '" data-color="' + escapeHtml(c.value) + '" title="' + escapeHtml(c.name) + '" style="background-color:' + escapeHtml(c.value) + '"></button>';
                    }).join('') +
                    '</div>' +
                    '<button type="button" class="tm-export-omit-btn" title="Omitir en el reporte" aria-label="Omitir en el reporte">&times;</button>';
                container.appendChild(item);
            });
        }

        function escapeHtml(s) {
            const div = document.createElement('div');
            div.textContent = s;
            return div.innerHTML;
        }

        function getPersonalizeState() {
            const container = document.getElementById('tmExportPersonalizeColumns');
            if (!container) { return { title: '', titleAlign: 'center', columns: [], sampleRow: {} }; }
            const titleEl = document.getElementById('tmExportPersonalizeTitle');
            const alignBtn = document.querySelector('.tm-export-align-btn.is-active');
            const titleAlign = (alignBtn && alignBtn.getAttribute('data-title-align')) || 'center';
            const items = Array.from(container.querySelectorAll('.tm-export-personalize-col'));
            const columns = items.map(function (item) {
                const key = item.dataset.key || '';
                const colorBtn = item.querySelector('.tm-export-color-swatch.is-active');
                const color = colorBtn ? (colorBtn.getAttribute('data-color') || '#861e34') : '#861e34';
                let imageWidth = 120, imageHeight = 80;
                const w = item.querySelector('.tm-export-image-width');
                const h = item.querySelector('.tm-export-image-height');
                if (w && h) {
                    imageWidth = parseInt(w.value, 10) || 120;
                    imageHeight = parseInt(h.value, 10) || 80;
                }
                return { key, color, imageWidth, imageHeight };
            });
            return { title: titleEl ? titleEl.value : '', titleAlign: titleAlign, columns };
        }

        function readSampleRowFromPreview(previewEl) {
            const sample = {};
            if (!previewEl) { return sample; }
            const cells = previewEl.querySelectorAll('.tm-export-preview-data-cell[data-key]');
            cells.forEach(function (cell) {
                const key = cell.getAttribute('data-key');
                if (key) { sample[key] = (cell.textContent || '').trim(); }
            });
            return sample;
        }

        function buildPersonalizePreview(columns, previewEl, sampleRow) {
            if (!previewEl) { return; }
            const savedRow = sampleRow || readSampleRowFromPreview(previewEl);
            const state = getPersonalizeState();
            const colorMap = {};
            state.columns.forEach(function (c) { colorMap[c.key] = c.color; });
            const titleAlign = state.titleAlign || 'center';
            const titleStyle = 'text-align:' + (titleAlign === 'left' ? 'left' : titleAlign === 'right' ? 'right' : 'center');
            let html = '<div class="tm-export-preview-table"><div class="tm-export-preview-row tm-export-preview-title"><div class="tm-export-preview-cell tm-export-preview-title-cell" style="' + titleStyle + '" colspan="' + columns.length + '">' + escapeHtml(state.title || 'Título') + '</div></div><div class="tm-export-preview-row tm-export-preview-header">';
            columns.forEach(function (col) {
                const color = colorMap[col.key] || '#861e34';
                if (col.is_image) {
                    const c = state.columns.find(function (x) { return x.key === col.key; }) || {};
                    const w = (c.imageWidth || 120) + 'px';
                    const h = (c.imageHeight || 80) + 'px';
                    html += '<div class="tm-export-preview-cell tm-export-preview-header-cell tm-export-preview-image-cell" style="background-color:' + escapeHtml(color) + ';width:' + w + ';height:' + h + ';min-width:' + w + ';min-height:' + h + '"><span class="tm-export-preview-image-placeholder">Imagen</span></div>';
                } else {
                    const ch = Math.min(col.max_width_chars || 10, 40);
                    html += '<div class="tm-export-preview-cell tm-export-preview-header-cell" style="background-color:' + escapeHtml(color) + ';min-width:' + ch + 'ch">' + escapeHtml(col.label) + '</div>';
                }
            });
            html += '</div><div class="tm-export-preview-row tm-export-preview-data">';
            columns.forEach(function (col) {
                const color = colorMap[col.key] || '#861e34';
                const cellColor = color === '#861e34' ? '#f5f5f5' : (color === '#ffffff' ? '#fff' : color);
                if (col.is_image) {
                    const c = state.columns.find(function (x) { return x.key === col.key; }) || {};
                    const w = (c.imageWidth || 120) + 'px';
                    const h = (c.imageHeight || 80) + 'px';
                    html += '<div class="tm-export-preview-cell tm-export-preview-data-cell tm-export-preview-image-cell" data-key="' + escapeHtml(col.key) + '" style="width:' + w + ';height:' + h + ';min-width:' + w + ';min-height:' + h + ';background:#f0f0f0"><span class="tm-export-preview-image-placeholder">—</span></div>';
                } else {
                    const ch = Math.min(col.max_width_chars || 10, 40);
                    const val = savedRow[col.key] !== undefined ? escapeHtml(savedRow[col.key]) : '';
                    html += '<div class="tm-export-preview-cell tm-export-preview-data-cell" data-key="' + escapeHtml(col.key) + '" contenteditable="true" style="min-width:' + ch + 'ch;background:' + escapeHtml(cellColor) + '" data-placeholder="Ejemplo">' + val + '</div>';
                }
            });
            html += '</div></div>';
            previewEl.innerHTML = html;
        }

        function reorderColumnsList(container, columns) {
            const order = Array.from(container.querySelectorAll('.tm-export-personalize-col')).map(function (item) {
                return columns.find(function (c) { return c.key === item.dataset.key; });
            }).filter(Boolean);
            return order.length ? order : columns.slice();
        }

        function updateRestoreVisibility(columnsEl, originalColumns, restoreWrap) {
            if (!restoreWrap) { return; }
            const current = columnsEl.querySelectorAll('.tm-export-personalize-col').length;
            restoreWrap.hidden = current >= originalColumns.length;
        }

        function attachPersonalizeColumnListeners(columnsEl, columns, previewEl, restoreWrap) {
            columnsEl.querySelectorAll('.tm-export-color-swatch').forEach(function (swatch) {
                swatch.addEventListener('click', function () {
                    const parent = swatch.closest('.tm-export-personalize-col');
                    if (parent) {
                        parent.querySelectorAll('.tm-export-color-swatch').forEach(function (s) { s.classList.remove('is-active'); });
                        swatch.classList.add('is-active');
                    }
                    buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                });
            });

            let draggedItem = null;
            columnsEl.querySelectorAll('.tm-export-personalize-col').forEach(function (item) {
                item.addEventListener('dragstart', function (e) {
                    draggedItem = item;
                    e.dataTransfer.setData('text/plain', item.dataset.key);
                    item.classList.add('is-dragging');
                });
                item.addEventListener('dragend', function () {
                    item.classList.remove('is-dragging');
                    draggedItem = null;
                });
                item.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    if (draggedItem && draggedItem !== item) {
                        item.classList.add('is-drag-over');
                    }
                });
                item.addEventListener('dragleave', function () { item.classList.remove('is-drag-over'); });
                item.addEventListener('drop', function (e) {
                    e.preventDefault();
                    item.classList.remove('is-drag-over');
                    if (!draggedItem || draggedItem === item) { return; }
                    const all = Array.from(columnsEl.querySelectorAll('.tm-export-personalize-col'));
                    const idx = all.indexOf(item);
                    const dragIdx = all.indexOf(draggedItem);
                    if (idx === -1 || dragIdx === -1) { return; }
                    if (idx < dragIdx) {
                        columnsEl.insertBefore(draggedItem, item);
                    } else {
                        columnsEl.insertBefore(draggedItem, item.nextSibling);
                    }
                    buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                });
            });
            updateRestoreVisibility(columnsEl, columns, restoreWrap);
        }

        function openExportPersonalizeModal(structureUrl, exportUrl) {
            if (!structureUrl || !exportUrl || !personalizeModal) { return; }
            const loadingEl = document.getElementById('tmExportPersonalizeLoading');
            const contentEl = document.getElementById('tmExportPersonalizeContent');
            const columnsEl = document.getElementById('tmExportPersonalizeColumns');
            const previewEl = document.getElementById('tmExportPersonalizePreview');
            const titleEl = document.getElementById('tmExportPersonalizeTitle');
            const applyBtn = document.getElementById('tmExportPersonalizeApply');
            const restoreWrap = document.getElementById('tmExportRestoreWrap');
            const restoreBtn = document.getElementById('tmExportRestoreBtn');

            if (loadingEl) { loadingEl.hidden = false; }
            if (contentEl) { contentEl.hidden = true; }
            personalizeModal.classList.add('is-open');
            personalizeModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            fetch(structureUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(new Error('Error al cargar')); })
                .then(function (data) {
                    let columns = Array.isArray(data.columns) ? data.columns : [];
                    if (personalizeModal) { personalizeModal._personalizeColumns = columns; }
                    if (titleEl) { titleEl.value = data.title || ''; }
                    buildPersonalizeColumnsList(columns, columnsEl);
                    buildPersonalizePreview(columns, previewEl);
                    if (restoreWrap) { restoreWrap.hidden = true; }

                    columnsEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    columnsEl.addEventListener('change', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    columnsEl.addEventListener('click', function (e) {
                        const omitBtn = e.target.closest('.tm-export-omit-btn');
                        if (omitBtn) {
                            const row = omitBtn.closest('.tm-export-personalize-col');
                            if (row) {
                                row.remove();
                                buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl);
                                updateRestoreVisibility(columnsEl, columns, restoreWrap);
                            }
                        }
                    });
                    attachPersonalizeColumnListeners(columnsEl, columns, previewEl, restoreWrap);

                    if (titleEl) {
                        titleEl.addEventListener('input', function () { buildPersonalizePreview(reorderColumnsList(columnsEl, columns), previewEl); });
                    }

                    if (restoreBtn && restoreWrap) {
                        restoreBtn.onclick = function () {
                            buildPersonalizeColumnsList(columns, columnsEl);
                            buildPersonalizePreview(columns, previewEl);
                            restoreWrap.hidden = true;
                            attachPersonalizeColumnListeners(columnsEl, columns, previewEl, restoreWrap);
                        };
                    }

                    if (applyBtn && exportUrl) {
                        applyBtn.onclick = function () {
                            closePersonalizeModal();
                            const separator = exportUrl.indexOf('?') === -1 ? '?' : '&';
                            window.location.href = exportUrl + separator + 'mode=single&analysis=0';
                        };
                    }
                })
                .catch(function () {
                    if (loadingEl) { loadingEl.textContent = 'No se pudo cargar la estructura.'; }
                })
                .finally(function () {
                    if (loadingEl) { loadingEl.hidden = true; }
                    if (contentEl) { contentEl.hidden = false; }
                });
        }

        exportButtons.forEach(function (exportButton) {
            exportButton.addEventListener('click', function () {
                const exportUrl = exportButton.getAttribute('data-export-url');
                if (!exportUrl) { return; }
                var exportBtnRef = exportButton;
                if (!templateSwal) {
                    const separator = exportUrl.indexOf('?') === -1 ? '?' : '&';
                    window.location.href = exportUrl + separator + 'mode=single&analysis=0';
                    return;
                }
                templateSwal.fire({
                    title: 'Tipo de exportacion',
                    html: '<div style="text-align:left">'
                        + '<label style="display:flex;gap:.5rem;align-items:center;margin-bottom:.65rem;">'
                        + '<input type="radio" name="tm-export-mode" value="single" checked> '
                        + '<span>Una sola hoja</span>'
                        + '</label>'
                        + '<label style="display:flex;gap:.5rem;align-items:center;">'
                        + '<input type="radio" name="tm-export-mode" value="mr"> '
                        + '<span>1 pagina por Microrregion</span>'
                        + '</label>'
                        + '<hr style="margin:.7rem 0;">'
                        + '<label style="display:flex;gap:.5rem;align-items:center;">'
                        + '<input type="checkbox" name="tm-include-analysis" value="1">'
                        + '<span>Incluir hoja de análisis</span>'
                        + '</label>'
                        + '<hr style="margin:.7rem 0;">'
                        + '<p style="margin:0;"><button type="button" class="tm-btn tm-btn-outline tm-swal-personalize-btn">Personalizar columnas y diseño</button></p>'
                        + '</div>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Exportar',
                    cancelButtonText: 'Cancelar',
                    didOpen: function () {
                        var personalizeBtn = document.querySelector('.swal2-html-container .tm-swal-personalize-btn');
                        if (personalizeBtn) {
                            personalizeBtn.addEventListener('click', function () {
                                Swal.close();
                                var structureUrl = exportBtnRef.getAttribute('data-structure-url');
                                var expUrl = exportBtnRef.getAttribute('data-export-url');
                                if (structureUrl && expUrl) { openExportPersonalizeModal(structureUrl, expUrl); }
                            });
                        }
                    },
                    preConfirm: function () {
                        const checkedMode = document.querySelector('input[name="tm-export-mode"]:checked');
                        const includeAnalysis = document.querySelector('input[name="tm-include-analysis"]')?.checked || false;
                        return {
                            mode: checkedMode ? checkedMode.value : 'single',
                            analysis: includeAnalysis ? 1 : 0
                        };
                    }
                }).then(function (result) {
                    if (!result.isConfirmed) { return; }
                    const mode = result.value && result.value.mode === 'mr' ? 'mr' : 'single';
                    const analysis = result.value && result.value.analysis ? 1 : 0;
                    const separator = exportUrl.indexOf('?') === -1 ? '?' : '&';
                    window.location.href = exportUrl + separator + 'mode=' + mode + '&analysis=' + String(analysis);
                });
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            if (personalizeModal && personalizeModal.classList.contains('is-open')) {
                closePersonalizeModal();
                return;
            }

            if (imageModal && imageModal.classList.contains('is-open')) {
                closeImageModal();
                return;
            }

            Array.from(document.querySelectorAll('.tm-modal.is-open')).forEach(function (modal) {
                closeModal(modal);
            });
        });
    });
</script>
@endpush
