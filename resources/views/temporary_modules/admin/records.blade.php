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

        exportButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const exportUrl = button.getAttribute('data-export-url');
                if (!exportUrl) {
                    return;
                }

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
                        + '</div>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Exportar',
                    cancelButtonText: 'Cancelar',
                    preConfirm: function () {
                        const checkedMode = document.querySelector('input[name="tm-export-mode"]:checked');
                        const includeAnalysis = document.querySelector('input[name="tm-include-analysis"]')?.checked || false;
                        return {
                            mode: checkedMode ? checkedMode.value : 'single',
                            analysis: includeAnalysis ? 1 : 0
                        };
                    }
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    const mode = result.value && result.value.mode === 'mr' ? 'mr' : 'single';
                    const analysis = result.value && result.value.analysis ? 1 : 0;
                    const separator = exportUrl.indexOf('?') === -1 ? '?' : '&';
                    window.location.href = exportUrl + separator + 'mode=' + mode + '&analysis=' + String(analysis);
                });
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

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
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
