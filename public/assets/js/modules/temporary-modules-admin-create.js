    document.addEventListener('DOMContentLoaded', function () {
        const container = document.getElementById('tmFieldsContainer');
        const addButton = document.getElementById('tmAddFieldBtn');
        const rowTemplate = document.getElementById('tmFieldRowTemplate');
        const delegateList = document.getElementById('tmDelegateList');
        const delegateFilters = document.getElementById('tmDelegateFilters');
        const expiresAtInput = document.getElementById('tmExpiresAt');
        const isIndefiniteInput = document.getElementById('tmIsIndefinite');
        const indefiniteButton = document.getElementById('tmIndefiniteBtn');

        // Indefinido: ejecutar siempre para que funcione aunque falle algo del listado de campos
        const actualizarModoIndefinido = function () {
            if (!expiresAtInput || !isIndefiniteInput || !indefiniteButton) return;
            const indefinidoActivo = isIndefiniteInput.value === '1';
            expiresAtInput.disabled = indefinidoActivo;
            expiresAtInput.required = !indefinidoActivo;
            if (indefinidoActivo) expiresAtInput.value = '';
            indefiniteButton.classList.toggle('is-active', indefinidoActivo);
            indefiniteButton.setAttribute('aria-pressed', indefinidoActivo ? 'true' : 'false');
        };
        if (indefiniteButton && isIndefiniteInput) {
            indefiniteButton.addEventListener('click', function () {
                isIndefiniteInput.value = isIndefiniteInput.value === '1' ? '0' : '1';
                actualizarModoIndefinido();
            });
            actualizarModoIndefinido();
        }

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
            const multiselectWrap = row.querySelector('[data-multiselect-wrap]');
            const linkedWrap = row.querySelector('[data-linked-wrap]');
            const requiredWrap = row.querySelector('[data-required-wrap]');
            const removeButton = row.querySelector('[data-remove-field]');
            const commentWrap = row.querySelector('[data-comment-wrap]');
            const toggleCommentButton = row.querySelector('[data-toggle-comment]');
            const moveUpButton = row.querySelector('[data-move-up]');
            const moveDownButton = row.querySelector('[data-move-down]');

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

                    // Toggle linked sub-field option textareas based on their type selects
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
                        // Disable linked internals when not linked type
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

            if (moveUpButton) {
                moveUpButton.addEventListener('click', function () {
                    const prev = row.previousElementSibling;
                    if (!prev) return;
                    container.insertBefore(row, prev);
                    refreshIndices();
                });
            }

            if (moveDownButton) {
                moveDownButton.addEventListener('click', function () {
                    const next = row.nextElementSibling;
                    if (!next) return;
                    container.insertBefore(next, row);
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

        // Copiar campos de otro módulo
        const copyFromSelect = document.getElementById('tmCopyFromModule');
        const copyFieldsBtn = document.getElementById('tmCopyFieldsBtn');
        const copyFieldsUrl = (window.TM_ADMIN_CREATE_BOOT && window.TM_ADMIN_CREATE_BOOT.copyFieldsUrl) ? window.TM_ADMIN_CREATE_BOOT.copyFieldsUrl : '';

        const addRowWithData = function (data) {
            const fragment = rowTemplate.content.cloneNode(true);
            const row = fragment.querySelector('[data-field-row]');
            if (!row) return;
            syncRowNames(row, index);
            attachRowEvents(row);
            container.appendChild(row);

            const set = function (name, value) {
                const el = row.querySelector('[data-name="' + name + '"]');
                if (el && value !== undefined && value !== null) el.value = value;
            };
            const setCheck = function (name, checked) {
                const el = row.querySelector('[data-name="' + name + '"]');
                if (el) el.checked = !!checked;
            };

            set('label', data.label);
            set('key', data.key || '');
            set('comment', data.comment || '');
            const commentWrap = row.querySelector('[data-comment-wrap]');
            const toggleCommentBtn = row.querySelector('[data-toggle-comment]');
            if (commentWrap && toggleCommentBtn && (data.comment || '').trim() !== '') {
                commentWrap.hidden = false;
                toggleCommentBtn.textContent = 'Ocultar observación';
            }
            const typeSelect = row.querySelector('[data-field-type]');
            if (typeSelect) typeSelect.value = data.type || 'text';
            setCheck('required', data.required);

            if (data.type === 'select') {
                var optEl = row.querySelector('[data-options-wrap] [data-name="options"]');
                if (optEl) optEl.value = data.options || '';
            }
            if (data.type === 'categoria') {
                var catEl = row.querySelector('[data-categoria-wrap] [data-name="options"]');
                if (catEl) catEl.value = data.options || '';
            }
            if (data.type === 'seccion') {
                set('options_title', data.options_title || '');
                set('options_subsections', data.options_subsections || '');
            }

            if (typeSelect) typeSelect.dispatchEvent(new Event('change', { bubbles: true }));

            index++;
        };

        if (copyFieldsBtn && copyFromSelect) {
            copyFieldsBtn.addEventListener('click', function () {
                const moduleId = copyFromSelect.value;
                if (!moduleId) return;
                const url = copyFieldsUrl.replace('__ID__', moduleId);
                copyFieldsBtn.disabled = true;
                copyFieldsBtn.textContent = 'Cargando...';
                fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (res) {
                        if (!res.ok) throw new Error('No se pudieron cargar los campos');
                        return res.json();
                    })
                    .then(function (json) {
                        const fields = json.fields || [];
                        fields.forEach(function (f) { addRowWithData(f); });
                        refreshIndices();
                    })
                    .catch(function () {
                        alert('No se pudieron cargar los campos del módulo. Intenta de nuevo.');
                    })
                    .finally(function () {
                        copyFieldsBtn.disabled = false;
                        copyFieldsBtn.textContent = 'Copiar campos aquí';
                    });
            });
        }

        addRow();
        addRow();
    });
