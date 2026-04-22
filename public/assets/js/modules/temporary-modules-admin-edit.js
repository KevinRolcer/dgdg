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
            const multiselectWrap = row.querySelector('[data-multiselect-wrap]');
            const linkedWrap = row.querySelector('[data-linked-wrap]');
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
                    const isMultiselect = t === 'multiselect';
                    const isLinked = t === 'linked';

                    optionsWrap.hidden = !isSelect;
                    if (categoriaWrap) categoriaWrap.hidden = !isCategoria;
                    seccionWraps.forEach(function (w) { w.hidden = !isSeccion; });
                    if (multiselectWrap) multiselectWrap.hidden = !isMultiselect;
                    if (linkedWrap) linkedWrap.hidden = !isLinked;
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
                    const multiselectInput = multiselectWrap ? multiselectWrap.querySelector('[data-name="options"]') : null;
                    if (multiselectInput) {
                        multiselectInput.required = isMultiselect;
                        multiselectInput.disabled = !isMultiselect;
                        if (!isMultiselect) multiselectInput.value = '';
                    }

                    if (linkedWrap) {
                        const toggleLinkedOpts = function (typeEl, optsWrapEl) {
                            if (!typeEl || !optsWrapEl) return;
                            const needsOpts = ['select', 'multiselect', 'categoria'].includes(typeEl.value);
                            optsWrapEl.hidden = !needsOpts;
                            const ta = optsWrapEl.querySelector('textarea');
                            if (ta) { ta.disabled = !needsOpts || !isLinked; }
                        };
                        const primaryTypeEl = linkedWrap.querySelector('[data-linked-primary-type]');
                        const primaryOptsWrap = linkedWrap.querySelector('[data-linked-primary-options-wrap]');
                        const secondaryTypeEl = linkedWrap.querySelector('[data-linked-secondary-type]');
                        const secondaryOptsWrap = linkedWrap.querySelector('[data-linked-secondary-options-wrap]');
                        if (primaryTypeEl) {
                            primaryTypeEl.onchange = function () { toggleLinkedOpts(primaryTypeEl, primaryOptsWrap); };
                            toggleLinkedOpts(primaryTypeEl, primaryOptsWrap);
                        }
                        if (secondaryTypeEl) {
                            secondaryTypeEl.onchange = function () { toggleLinkedOpts(secondaryTypeEl, secondaryOptsWrap); };
                            toggleLinkedOpts(secondaryTypeEl, secondaryOptsWrap);
                        }
                        linkedWrap.querySelectorAll('input, select, textarea').forEach(function (el) {
                            if (!isLinked) el.disabled = true;
                            else if (!el.closest('[hidden]')) el.disabled = false;
                        });
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
            const multiselectWrap = row.querySelector('[data-existing-multiselect-wrap]');
            const categoriaWrap = row.querySelector('[data-existing-categoria-wrap]');
            const seccionWraps = row.querySelectorAll('[data-existing-seccion-wrap]');
            const requiredWrap = row.querySelector('[data-existing-required-wrap]');
            if (!typeSelect) {
                return;
            }

            const isDeleted = row.classList.contains('is-marked-remove');
            const t = typeSelect.value;
            const isSelect = t === 'select';
            const isMultiselect = t === 'multiselect';
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
            if (multiselectWrap) {
                multiselectWrap.hidden = !isMultiselect;
                const multiselectInput = multiselectWrap.querySelector('textarea');
                if (multiselectInput) {
                    multiselectInput.disabled = isDeleted || !isMultiselect;
                    multiselectInput.required = !isDeleted && isMultiselect;
                    if (!isMultiselect && !isDeleted) multiselectInput.value = '';
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

                const submitWithAction = function (action) {
                    conflictActionInput.value = action;
                    forceSubmit = true;
                    editForm.submit();
                };

                const normalizeFieldType = function (value) {
                    return value === 'file' ? 'image' : value;
                };

                const normalizeSiNoToken = function (value) {
                    return String(value || '')
                        .toLowerCase()
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .replace(/[^a-z0-9]+/g, '');
                };

                const parseSelectOptions = function (raw) {
                    return String(raw || '')
                        .split(/\r?\n|,/)
                        .map(function (item) { return item.trim(); })
                        .filter(function (item) { return item !== ''; });
                };

                const hasSiNoInOptions = function (options) {
                    let hasSi = false;
                    let hasNo = false;
                    options.forEach(function (opt) {
                        const token = normalizeSiNoToken(opt);
                        if (token === 'si') {
                            hasSi = true;
                        } else if (token === 'no') {
                            hasNo = true;
                        }
                    });

                    return hasSi && hasNo;
                };

                let hasConflict = false;
                let hasMunicipioConflict = false;
                let hasSelectToMultiselect = false;
                let hasBooleanToSelect = false;
                let hasTextToSelect = false;
                let hasInvalidBooleanToSelectOptions = false;

                existingRows.forEach(function (row) {
                    const hasData = row.getAttribute('data-has-data') === '1';
                    if (!hasData) {
                        return;
                    }

                    const oldKey = row.getAttribute('data-old-key') || '';
                    const oldType = row.getAttribute('data-old-type') || '';
                    const isDeleted = row.classList.contains('is-marked-remove');
                    const keyInput = row.querySelector('input[name$="[key]"]');
                    const typeSelect = row.querySelector('[data-existing-field-type]');
                    const newKey = keyInput ? keyInput.value.trim() : oldKey;
                    const newType = normalizeFieldType(typeSelect ? typeSelect.value : oldType);

                    // select → multiselect with same key: safe migration
                    if (!isDeleted && newKey === oldKey && oldType === 'select' && newType === 'multiselect') {
                        hasSelectToMultiselect = true;
                        return; // not a destructive conflict
                    }

                    // text → texto largo: mismo string en JSON; sin migración de datos
                    if (!isDeleted && newKey === oldKey && oldType === 'text' && newType === 'textarea') {
                        return;
                    }

                    // boolean → select with same key: safe migration if select options include Si/Sí and No
                    if (!isDeleted && newKey === oldKey && oldType === 'boolean' && newType === 'select') {
                        const selectOptionsInput = row.querySelector('[data-existing-options-wrap] textarea[name$="[options]"]');
                        const options = parseSelectOptions(selectOptionsInput ? selectOptionsInput.value : '');
                        if (hasSiNoInOptions(options)) {
                            hasBooleanToSelect = true;
                            return; // not a destructive conflict
                        }

                        hasInvalidBooleanToSelectOptions = true;
                    }

                    if (!isDeleted && newKey === oldKey && oldType === 'text' && newType === 'select') {
                        hasTextToSelect = true;
                        return;
                    }

                    const rowHasConflict = isDeleted || newKey !== oldKey || newType !== oldType;
                    if (rowHasConflict) {
                        hasConflict = true;
                        if (oldKey === 'municipio' && normalizeFieldType(oldType) !== 'municipio' && newType === 'municipio') {
                            hasMunicipioConflict = true;
                        }
                    }
                });

                // Only pure migration (no other conflicts)
                if (hasSelectToMultiselect && !hasConflict) {
                    event.preventDefault();
                    if (!templateSwal) {
                        submitWithAction('migrate_to_multiselect');
                        return;
                    }
                    templateSwal.fire({
                        title: 'Convertir lista a selección múltiple',
                        html: 'Los registros existentes que tenían <b>un valor</b> seleccionado se conservarán como <b>una opción seleccionada</b>.<br><br>¿Continuar con la migración?',
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: 'Migrar y guardar',
                        cancelButtonText: 'Cancelar',
                        reverseButtons: true
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            submitWithAction('migrate_to_multiselect');
                        }
                    });
                    return;
                }

                if (hasInvalidBooleanToSelectOptions) {
                    event.preventDefault();
                    if (!templateSwal) {
                        return;
                    }
                    templateSwal.fire({
                        title: 'Opciones incompletas para normalizar',
                        text: 'Para convertir un campo de Sí/No a Lista de opciones y conservar respuestas, debes incluir claramente Si (o Sí) y No en las opciones.',
                        icon: 'error',
                        confirmButtonText: 'Entendido'
                    });
                    return;
                }

                if (hasTextToSelect && !hasConflict) {
                    event.preventDefault();
                    if (!templateSwal) {
                        submitWithAction('normalize_text_select');
                        return;
                    }
                    templateSwal.fire({
                        title: 'Normalizar texto a lista de opciones',
                        html: 'Se intentará normalizar automáticamente las respuestas existentes que coincidan con las nuevas opciones.<br><br>Las respuestas que no coincidan quedarán registradas en un <b>log</b> para que puedas reasignarlas manualmente a una de las opciones configuradas.',
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: 'Normalizar y guardar',
                        cancelButtonText: 'Cancelar',
                        reverseButtons: true
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            submitWithAction('normalize_text_select');
                        }
                    });
                    return;
                }

                // Only pure migration (no other conflicts)
                if (hasBooleanToSelect && !hasConflict) {
                    event.preventDefault();
                    if (!templateSwal) {
                        submitWithAction('normalize_boolean_select');
                        return;
                    }
                    templateSwal.fire({
                        title: 'Normalizar datos existentes',
                        html: 'Se conservarán tus respuestas ya capturadas del campo <b>Sí/No</b> y se convertirán a las etiquetas de la nueva <b>Lista de opciones</b>.<br><br>¿Deseas continuar?',
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: 'Normalizar y guardar',
                        cancelButtonText: 'Cancelar',
                        reverseButtons: true
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            submitWithAction('normalize_boolean_select');
                        }
                    });
                    return;
                }

                if (!hasConflict) {
                    return;
                }

                event.preventDefault();

                if (!templateSwal) {
                    submitWithAction(hasMunicipioConflict ? 'normalize_municipio' : 'clear_module');
                    return;
                }

                if (hasMunicipioConflict) {
                    templateSwal.fire({
                        title: 'Conflicto con datos existentes',
                        text: 'Detectamos registros en el campo municipio. ¿Quieres normalizar esos valores contra el catálogo oficial o borrar los datos?',
                        icon: 'warning',
                        showCancelButton: true,
                        showDenyButton: true,
                        confirmButtonText: 'Normalizar municipios',
                        denyButtonText: 'Borrar datos de esos campos',
                        cancelButtonText: 'Cancelar',
                        reverseButtons: true
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            submitWithAction('normalize_municipio');
                            return;
                        }

                        if (result.isDenied) {
                            submitWithAction('clear_field_data');
                        }
                    });
                } else {
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
                }
            });
        }

        if (window.TM_ADMIN_EDIT_BOOT && window.TM_ADMIN_EDIT_BOOT.hasSeedDiscardLog) {
        (function () {
            var btn = document.getElementById('tmEditSeedLogBtn');
            var modal = document.getElementById('tmSeedDiscardLogModalEdit');
            var jsonEl = document.getElementById('tm-seed-discard-edit');
            if (!btn || !modal || !jsonEl || !window.tmSeedDiscardLog) return;
            var currentList = [];
            function openLog() {
                try { currentList = JSON.parse(jsonEl.textContent || '[]'); } catch (e) { currentList = []; }
                if (!Array.isArray(currentList)) currentList = [];
                document.getElementById('tmSeedDiscardLogModuleEdit').textContent = btn.getAttribute('data-module-name') || '';
                var tbody = document.getElementById('tmSeedDiscardLogTbodyEdit');
                var empty = document.getElementById('tmSeedDiscardLogEmptyEdit');
                var wrap = document.getElementById('tmSeedDiscardLogTableWrapEdit');
                if (currentList.length === 0) {
                    empty.hidden = false;
                    wrap.hidden = true;
                    tbody.innerHTML = '';
                } else {
                    empty.hidden = true;
                    wrap.hidden = false;
                    window.tmSeedDiscardLog.renderRows(tbody, currentList, {
                        registerUrl: modal.getAttribute('data-register-url') || '',
                        searchUrl: modal.getAttribute('data-search-url') || '',
                        csrfToken: modal.getAttribute('data-csrf-token') || '',
                        jsonScriptEl: jsonEl,
                        onUpdateList: function (newLog) {
                            currentList = newLog;
                        },
                        onEmpty: function () {
                            empty.hidden = false;
                            wrap.hidden = true;
                        },
                    });
                }
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }
            function closeLog() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }
            btn.addEventListener('click', openLog);
            modal.querySelectorAll('[data-tm-seed-log-close-edit]').forEach(function (el) { el.addEventListener('click', closeLog); });
            if (window.TM_ADMIN_EDIT_BOOT && window.TM_ADMIN_EDIT_BOOT.showSeedLog) {
                openLog();
            }
        })();
        }

        if (window.TM_ADMIN_EDIT_BOOT && window.TM_ADMIN_EDIT_BOOT.hasOptionNormalizationLog) {
        (function () {
            var btn = document.getElementById('tmEditOptionNormalizationLogBtn');
            var modal = document.getElementById('tmOptionNormalizationLogModalEdit');
            var jsonEl = document.getElementById('tm-option-normalization-log-edit');
            if (!btn || !modal || !jsonEl) return;

            var currentList = [];

            function closeLog() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            function setRows(tbody, rows) {
                tbody.innerHTML = '';
                rows.forEach(function (row) {
                    var tr = document.createElement('tr');
                    tr.dataset.logUid = row.log_uid || '';

                    var tdEntry = document.createElement('td');
                    tdEntry.textContent = row.entry_id ? ('#' + row.entry_id) : '—';
                    tr.appendChild(tdEntry);

                    var tdField = document.createElement('td');
                    tdField.textContent = row.field_label || row.field_key || '—';
                    tr.appendChild(tdField);

                    var tdOriginal = document.createElement('td');
                    tdOriginal.textContent = row.original_value || '—';
                    tr.appendChild(tdOriginal);

                    var tdReason = document.createElement('td');
                    tdReason.textContent = row.reason || 'Sin detalle';
                    tr.appendChild(tdReason);

                    var tdAction = document.createElement('td');
                    var select = document.createElement('select');
                    select.className = 'tm-seed-log-muni-select';
                    var placeholder = document.createElement('option');
                    placeholder.value = '';
                    placeholder.textContent = 'Selecciona una opción';
                    select.appendChild(placeholder);
                    (Array.isArray(row.options) ? row.options : []).forEach(function (optionValue) {
                        var opt = document.createElement('option');
                        opt.value = String(optionValue || '');
                        opt.textContent = String(optionValue || '');
                        select.appendChild(opt);
                    });

                    var saveBtn = document.createElement('button');
                    saveBtn.type = 'button';
                    saveBtn.className = 'tm-btn tm-btn-sm tm-btn-primary';
                    saveBtn.textContent = 'Guardar';
                    saveBtn.addEventListener('click', function () {
                        var selectedOption = String(select.value || '').trim();
                        if (!selectedOption) {
                            if (typeof window.Swal !== 'undefined') {
                                window.Swal.fire({ icon: 'warning', title: 'Selecciona una opción', text: 'Debes elegir una opción válida antes de guardar.' });
                            }
                            return;
                        }

                        select.disabled = true;
                        saveBtn.disabled = true;
                        fetch(modal.getAttribute('data-resolve-url') || '', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': modal.getAttribute('data-csrf-token') || '',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                log_uid: row.log_uid,
                                selected_option: selectedOption,
                            }),
                        })
                            .then(function (response) {
                                return response.json().then(function (payload) {
                                    return { ok: response.ok, payload: payload };
                                });
                            })
                            .then(function (result) {
                                if (!result.ok) {
                                    throw new Error((result.payload && result.payload.message) || 'No se pudo actualizar la respuesta.');
                                }

                                currentList = Array.isArray(result.payload.seed_discard_log)
                                    ? result.payload.seed_discard_log.filter(function (item) {
                                        return item && item.log_type === 'field_option_normalization';
                                    })
                                    : [];
                                jsonEl.textContent = JSON.stringify(currentList);
                                openLog();
                            })
                            .catch(function (error) {
                                if (typeof window.Swal !== 'undefined') {
                                    window.Swal.fire({ icon: 'error', title: 'Error', text: error.message || 'No se pudo actualizar la respuesta.' });
                                }
                                select.disabled = false;
                                saveBtn.disabled = false;
                            });
                    });

                    tdAction.appendChild(select);
                    tdAction.appendChild(document.createTextNode(' '));
                    tdAction.appendChild(saveBtn);
                    tr.appendChild(tdAction);
                    tbody.appendChild(tr);
                });
            }

            function openLog() {
                try { currentList = JSON.parse(jsonEl.textContent || '[]'); } catch (e) { currentList = []; }
                if (!Array.isArray(currentList)) currentList = [];
                document.getElementById('tmOptionNormalizationLogModuleEdit').textContent = btn.getAttribute('data-module-name') || '';
                var tbody = document.getElementById('tmOptionNormalizationLogTbodyEdit');
                var empty = document.getElementById('tmOptionNormalizationLogEmptyEdit');
                var wrap = document.getElementById('tmOptionNormalizationLogTableWrapEdit');
                if (currentList.length === 0) {
                    empty.hidden = false;
                    wrap.hidden = true;
                    tbody.innerHTML = '';
                } else {
                    empty.hidden = true;
                    wrap.hidden = false;
                    setRows(tbody, currentList);
                }
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            btn.addEventListener('click', openLog);
            modal.querySelectorAll('[data-tm-option-log-close-edit]').forEach(function (el) { el.addEventListener('click', closeLog); });
            if (window.TM_ADMIN_EDIT_BOOT && window.TM_ADMIN_EDIT_BOOT.showOptionNormalizationLog) {
                openLog();
            }
        })();
        }
    });
