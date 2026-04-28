// Agenda Module Functionality

function agendaUrlStore() {
    var m = document.querySelector('meta[name="agenda-url-store"]');
    if (m && m.getAttribute('content')) return m.getAttribute('content');
    return agendaUrlBase();
}

function agendaUrlBase() {
    var m = document.querySelector('meta[name="agenda-url-base"]');
    if (m && m.getAttribute('content')) return m.getAttribute('content').replace(/\/$/, '');
    return window.location.origin + '/agenda';
}

/** Líneas "Aforo: N" o "Aforo: N personas" (Gira). */
var AGENDA_AFORO_LINE = /^\s*Aforo:\s*(\d+)(\s+personas)?\s*$/gim;

function agendaStripAforoLines(text) {
    if (!text || typeof text !== 'string') return '';
    return text.replace(AGENDA_AFORO_LINE, '').replace(/\n{3,}/g, '\n\n').trim();
}

function agendaExtractAforoFromDescription(text) {
    if (!text || typeof text !== 'string') return { base: '', aforo: '' };
    var m;
    var last = '';
    var re = /^\s*Aforo:\s*(\d+)(\s+personas)?\s*$/gim;
    while ((m = re.exec(text)) !== null) last = m[1];
    return { base: agendaStripAforoLines(text), aforo: last };
}

function agendaMergeAforoIntoDescripcion(baseText, aforoInput) {
    var base = agendaStripAforoLines(baseText || '');
    var n = parseInt(String(aforoInput || '').trim(), 10);
    if (!n || n < 1) return base;
    var line = 'Aforo: ' + n + ' personas';
    return base ? base + '\n\n' + line : line;
}

function updateAgendaFichaBgPreview() {
    var select = document.getElementById('modalFichaFondo');
    var preview = document.getElementById('agendaFichaBgPreview');
    if (!select || !preview) return;
    preview.classList.remove('is-tlaloc_a_beige', 'is-tlaloc_a_rojo', 'is-tlaloc_a_verde', 'is-beige', 'is-blanco', 'is-rojo', 'is-verde');
    var allowed = ['tlaloc_a_beige', 'tlaloc_a_rojo', 'tlaloc_a_verde', 'beige', 'blanco', 'rojo', 'verde'];
    var bg = allowed.indexOf(select.value) !== -1 ? select.value : 'beige';
    preview.classList.add('is-' + bg);
}

function openAgendaModal(id = null, tipo = 'asunto') {
    const modal = document.getElementById('agendaModal');
    const form = document.getElementById('agendaForm');
    const title = document.getElementById('modalTitle');
    const container = document.getElementById('extraAddressesContainer');
    const tipoInput = document.getElementById('modalTipo');
    const subTipoInput = document.getElementById('modalSubtipo');
    const assignModal = document.getElementById('agendaAssignModal');
    const fichaTituloInput = document.getElementById('modalFichaTitulo');
    const fichaFondoInput = document.getElementById('modalFichaFondo');
    const lugarPersonalizadoInput = document.getElementById('modalLugarPersonalizado');
    const lugarGiraInput = document.getElementById('modalLugar');

    // Elements to toggle
    const fieldsGira = document.getElementById('fieldsGira');
    const fieldsFichaPersonalizada = document.getElementById('fieldsFichaPersonalizada');
    const rowDescripcion = document.getElementById('rowDescripcion');

    // Reset form
    form.reset();
    document.querySelectorAll('.user-item-mini input').forEach(cb => cb.checked = false);
    // Número telefónico desactivado de momento: limpieza de filas tel
    if (container) {
        container.querySelectorAll('.agenda-phone-row').forEach(el => el.remove());
        if (!container.querySelector('.agenda-phones-hint')) {
            const hint = document.createElement('span');
            hint.className = 'agenda-phones-hint';
            hint.textContent = 'Números telefónicos adicionales (opcional)';
            container.prepend(hint);
        }
    }

    // Ensure containers are closed by default for new
    setUnfoldState('unfoldAsignar', false);
    document.getElementById('modalHora').style.display = 'none';

    tipoInput.value = tipo;
    if (subTipoInput) {
        subTipoInput.value = 'gira';
    }

    if (id) {
        const btn = document.querySelector(`button[data-id="${id}"]`);
        if (btn) {
            const itemTipo = btn.dataset.tipo || 'asunto';
            tipoInput.value = itemTipo;
            if (subTipoInput) {
                subTipoInput.value = btn.dataset.subtipo || 'gira';
                document.querySelectorAll('.agenda-type-pill').forEach(function (pill) {
                    pill.classList.toggle('is-active', (pill.dataset.subtipo || 'gira') === subTipoInput.value);
                });
            }
            title.innerText = itemTipo === 'gira' ? 'Editar Gira/Pre-Gira' : (itemTipo === 'personalizado' ? 'Editar Ficha personalizada' : 'Editar Asunto');
            form.action = agendaUrlBase() + '/' + id;
            document.getElementById('formMethod').value = 'PUT';

            document.getElementById('modalAsunto').value = btn.dataset.asunto || '';
            var rawDesc = btn.dataset.descripcion || '';

            const btnOpenDescRef = document.getElementById('btnOpenDescModal');

            if (itemTipo === 'gira') {
                var parsed = agendaExtractAforoFromDescription(rawDesc);
                document.getElementById('modalDescripcion').value = parsed.base;
                var aforoEl = document.getElementById('modalAforo');
                if (aforoEl) aforoEl.value = parsed.aforo || '';

                if (btnOpenDescRef) {
                    if (parsed.base.trim().length > 0) {
                        btnOpenDescRef.innerHTML = '<i class="fa-solid fa-align-left" style="color: var(--clr-accent, #c79b66);"></i> Ver o Editar Descripción';
                        btnOpenDescRef.classList.add('is-active');
                    } else {
                        btnOpenDescRef.innerHTML = '<i class="fa-solid fa-align-left" style="color: var(--clr-accent, #c79b66);"></i> Añadir Descripción';
                        btnOpenDescRef.classList.remove('is-active');
                    }
                }
            } else {
                document.getElementById('modalDescripcion').value = rawDesc;
                var aforoEl2 = document.getElementById('modalAforo');
                if (aforoEl2) aforoEl2.value = '';
            }
            if (fichaTituloInput) fichaTituloInput.value = btn.dataset.fichaTitulo || '';
            if (fichaFondoInput) fichaFondoInput.value = btn.dataset.fichaFondo || 'beige';
            if (lugarPersonalizadoInput) lugarPersonalizadoInput.value = btn.dataset.lugar || '';
            updateAgendaFichaBgPreview();
            if (itemTipo === 'personalizado' && btnOpenDescRef) {
                if (rawDesc.trim().length > 0) {
                    btnOpenDescRef.innerHTML = '<i class="fa-solid fa-align-left" style="color: var(--clr-accent, #c79b66);"></i> Ver o Editar Descripción';
                    btnOpenDescRef.classList.add('is-active');
                } else {
                    btnOpenDescRef.innerHTML = '<i class="fa-solid fa-align-left" style="color: var(--clr-accent, #c79b66);"></i> Añadir Descripción';
                    btnOpenDescRef.classList.remove('is-active');
                }
            }
            document.getElementById('modalFecha').value = btn.dataset.fecha || '';
            document.getElementById('modalRecordatorio').value = btn.dataset.recordatorio || '30';

            // Gira specific
            if (itemTipo === 'gira') {
                const microSelect = document.getElementById('modalMicrorregion');
                if (microSelect) {
                    microSelect.value = btn.dataset.microrregion || '';
                    microSelect.dispatchEvent(new Event('change'));
                }
                document.getElementById('modalMunicipio').value = btn.dataset.municipio || '';
                document.getElementById('modalLugar').value = btn.dataset.lugar || '';
            }
            const semaforoEl = document.getElementById('modalSemaforo');
            if (semaforoEl) semaforoEl.value = btn.dataset.semaforo || '';

            if (btn.dataset.hora && btn.dataset.hora !== 'null' && btn.dataset.hora !== '') {
                document.getElementById('modalHabilitarHora').checked = true;
                const timeValue = btn.dataset.hora.includes(':') ? btn.dataset.hora.substring(0, 5) : '';
                document.getElementById('modalHora').value = timeValue;
                document.getElementById('modalHora').style.display = 'block';
            }

            const usersIds = JSON.parse(btn.dataset.users || '[]');
            if (usersIds.length > 0) {
                setUnfoldState('unfoldAsignar', true);
                usersIds.forEach(uid => {
                    // Los checkboxes están dentro del modal de asignación (fuera del <form>),
                    // por eso no deben buscarse con form.querySelector().
                    const cb = assignModal ? assignModal.querySelector(`input[name="usuarios_asignados[]"][value="${uid}"]`) : null;
                    if (cb) cb.checked = true;
                });
            }

            // Número telefónico desactivado de momento
            // const addresses = JSON.parse(btn.dataset.addresses || '[]');
            // addresses.forEach(addr => addAddressRow(addr));
        }
    } else {
        title.innerText = tipo === 'gira' ? 'Nueva Gira/Pre-Gira' : (tipo === 'personalizado' ? 'Nueva Ficha personalizada' : 'Nuevo Asunto');
        form.action = agendaUrlStore();
        document.getElementById('formMethod').value = 'POST';
        if (fichaTituloInput) fichaTituloInput.value = '';
        if (fichaFondoInput) fichaFondoInput.value = 'beige';
        if (lugarPersonalizadoInput) lugarPersonalizadoInput.value = '';
        updateAgendaFichaBgPreview();
    }

    // Toggle fields based on type
    const isGira = tipoInput.value === 'gira';
    const isPersonalizado = tipoInput.value === 'personalizado';
    if(fieldsGira) fieldsGira.style.display = isGira ? 'block' : 'none';
    if(fieldsFichaPersonalizada) fieldsFichaPersonalizada.style.display = isPersonalizado ? 'block' : 'none';
    if (fichaTituloInput) {
        fichaTituloInput.required = isPersonalizado;
        fichaTituloInput.disabled = !isPersonalizado;
    }
    if (fichaFondoInput) fichaFondoInput.disabled = !isPersonalizado;
    if (lugarPersonalizadoInput) lugarPersonalizadoInput.disabled = !isPersonalizado;
    if (lugarGiraInput) lugarGiraInput.disabled = !isGira;
    /* Gira y ficha personalizada usan botón + modal para la descripción. */
    if (rowDescripcion) {
        rowDescripcion.style.display = (isGira || isPersonalizado) ? 'none' : 'block';
        var lbl = rowDescripcion.querySelector('.form-label-agenda');
        if (lbl) lbl.textContent = 'Descripción';
    }
    const tipoSelector = document.getElementById('agendaTipoSelector');
    if (tipoSelector) {
        tipoSelector.style.display = (isGira || isPersonalizado) ? 'flex' : 'none';
        const tipoSelectorMain = tipoSelector.querySelector('.agenda-type-switch-main');
        if (tipoSelectorMain) tipoSelectorMain.style.display = isGira ? 'block' : 'none';
    }
    const delegadoWrap = document.getElementById('agendaDelegadoLabelWrap');
    if (delegadoWrap) {
        delegadoWrap.style.display = isGira ? 'block' : 'none';
    }
    const aforoWrap = document.getElementById('agendaAforoWrap');
    if (aforoWrap) {
        aforoWrap.style.display = isGira ? 'flex' : 'none';
        aforoWrap.setAttribute('aria-hidden', isGira ? 'false' : 'true');
    }
    if (!id && (isGira || isPersonalizado)) {
        var aforoNew = document.getElementById('modalAforo');
        if (aforoNew) aforoNew.value = '';

        // Reset description button on physical new form
        const btnOpenDescRef = document.getElementById('btnOpenDescModal');
        if (btnOpenDescRef) {
            btnOpenDescRef.innerHTML = '<i class="fa-solid fa-align-left" style="color: var(--clr-accent, #c79b66);"></i> Añadir Descripción';
            btnOpenDescRef.classList.remove('is-active');
        }
    }
    // En edición ya hicimos dispatchEvent('change') al seleccionar la microrregión,
    // y ese handler puede limpiar el `municipio`. Por eso solo lo hacemos al crear.
    if (!id && isGira) {
        const microSel = document.getElementById('modalMicrorregion');
        if (microSel && typeof microSel.dispatchEvent === 'function') {
            microSel.dispatchEvent(new Event('change'));
        }
    }

    modal.style.display = 'flex';
}

function closeAgendaModal() {
    document.getElementById('agendaModal').style.display = 'none';
}

function setUnfoldState(id, show) {
    const container = document.getElementById(id);
    const btn = document.getElementById('btnToggle' + id.replace('unfold', ''));
    if (!container) {
        return;
    }
    if (show) {
        container.style.display = 'block';
        if (btn) btn.classList.add('is-active');
    } else {
        container.style.display = 'none';
        if (btn) btn.classList.remove('is-active');
    }
}

function toggleUnfold(id) {
    const container = document.getElementById(id);
    const isShowing = container.style.display !== 'none';
    setUnfoldState(id, !isShowing);
}

function openDescModal() {
    const modal = document.getElementById('agendaDescModal');
    const miniDesc = document.getElementById('modalMiniDescripcion');
    const mainDesc = document.getElementById('modalDescripcion');

    if (modal && miniDesc && mainDesc) {
        miniDesc.value = agendaStripAforoLines(mainDesc.value);
        modal.style.display = 'flex';
        setTimeout(() => miniDesc.focus(), 100);
    }
}

function closeDescModal() {
    const modal = document.getElementById('agendaDescModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

window.closeDescModal = closeDescModal;

function addAddressRow(value = '') {
    const container = document.getElementById('extraAddressesContainer');
    const hint = container.querySelector('.agenda-phones-hint');
    if (hint && container.querySelectorAll('.agenda-phone-row').length === 0) {
        hint.remove();
    }
    const row = document.createElement('div');
    row.className = 'agenda-phone-row';
    const safeValue = (typeof value === 'string' ? value : '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    row.innerHTML = `
        <span class="agenda-phone-input-wrap">
            <i class="fa-solid fa-phone agenda-phone-icon" aria-hidden="true"></i>
            <input type="tel" name="direcciones_adicionales[]" class="form-control-agenda agenda-phone-input" value="${safeValue}" placeholder="Ej. 222 123 4567" inputmode="numeric" pattern="[0-9\s\-+()]{10,20}" maxlength="20" autocomplete="tel">
        </span>
        <button type="button" class="btn-remove-mini" onclick="this.parentElement.remove()" title="Quitar número">
            <i class="fa-solid fa-trash"></i>
        </button>
    `;
    container.appendChild(row);
}

document.addEventListener('DOMContentLoaded', function () {
    var agendaForm = document.getElementById('agendaForm');
    if (agendaForm) {
        agendaForm.addEventListener('submit', function () {
            var tipo = document.getElementById('modalTipo');
            var desc = document.getElementById('modalDescripcion');
            var delegadoLabel = document.getElementById('agendaDelegadoLabel');
            var delegadoInput = document.getElementById('modalDelegadoEncargado');
            if (!desc) return;
            if (tipo && tipo.value === 'gira') {
                var aforo = document.getElementById('modalAforo');
                desc.value = agendaMergeAforoIntoDescripcion(desc.value, aforo ? aforo.value : '');
                if (delegadoLabel && delegadoInput) {
                    var txt = delegadoLabel.textContent || '';
                    var match = txt.match(/Delegad@ encargado: (.+)/);
                    delegadoInput.value = match ? match[1].trim() : '';
                }
            } else if (delegadoInput) {
                delegadoInput.value = '';
            }
        });
    }
    const modal = document.getElementById('agendaModal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeAgendaModal();
        });
    }

    const toggleHora = document.getElementById('modalHabilitarHora');
    if (toggleHora) {
        toggleHora.addEventListener('change', function() {
            document.getElementById('modalHora').style.display = this.checked ? 'block' : 'none';
        });
    }

    const btnAsignar = document.getElementById('btnToggleAsignar');
    if (btnAsignar) {
        btnAsignar.addEventListener('click', () => toggleUnfold('unfoldAsignar'));
    }


    const userSearch = document.getElementById('modalUserSearch');
    if (userSearch) {
        userSearch.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            document.querySelectorAll('.user-item-mini').forEach(item => {
                const name = item.dataset.name || '';
                const email = item.dataset.email || '';
                item.style.display = (name.includes(query) || email.includes(query)) ? 'flex' : 'none';
            });
        });
    }

    const btnOpenDesc = document.getElementById('btnOpenDescModal');
    if (btnOpenDesc) {
        btnOpenDesc.addEventListener('click', openDescModal);
    }

    const btnSaveDesc = document.getElementById('btnSaveDescModal');
    if (btnSaveDesc) {
        btnSaveDesc.addEventListener('click', function() {
            const miniDesc = document.getElementById('modalMiniDescripcion');
            const mainDesc = document.getElementById('modalDescripcion');
            const btnOpenDescRef = document.getElementById('btnOpenDescModal');

            if (miniDesc && mainDesc) {
                mainDesc.value = miniDesc.value;

                if (btnOpenDescRef) {
                    if (miniDesc.value.trim().length > 0) {
                        btnOpenDescRef.innerHTML = '<i class="fa-solid fa-align-left" style="color: var(--clr-accent, #c79b66);"></i> Ver o Editar Descripción';
                        btnOpenDescRef.classList.add('is-active');
                    } else {
                        btnOpenDescRef.innerHTML = '<i class="fa-solid fa-align-left" style="color: var(--clr-accent, #c79b66);"></i> Añadir Descripción';
                        btnOpenDescRef.classList.remove('is-active');
                    }
                }
                closeDescModal();
            }
        });
    }

    const fichaFondo = document.getElementById('modalFichaFondo');
    if (fichaFondo) {
        fichaFondo.addEventListener('change', updateAgendaFichaBgPreview);
        updateAgendaFichaBgPreview();
    }

    const microrregionSelect = document.getElementById('modalMicrorregion');
    const municipioSelect = document.getElementById('modalMunicipio');
    if (microrregionSelect && municipioSelect) {
        const updateFromSelectedMicrorregion = function(selectEl, keepMunicipioValue = false) {
            const selected = selectEl.options[selectEl.selectedIndex];
            const microId = selected ? (selected.dataset.id || '') : '';
            const delegadoName = selected ? (selected.dataset.delegado || '') : '';
            const municipioOptions = municipioSelect.querySelectorAll('option');
            const delegadoLabel = document.getElementById('agendaDelegadoLabel');

            const currentMunicipio = municipioSelect.value;
            if (!keepMunicipioValue) {
                municipioSelect.value = '';
            }

            municipioOptions.forEach(opt => {
                if (opt.value === '') {
                    opt.style.display = 'block';
                } else {
                    const optMicroId = opt.dataset.micro || '';
                    opt.style.display = (microId === '' || optMicroId === microId) ? 'block' : 'none';
                }
            });

            if (keepMunicipioValue && currentMunicipio) {
                const candidate = Array.from(municipioOptions).find(
                    opt => opt.value === currentMunicipio && opt.style.display !== 'none'
                );
                if (candidate) {
                    municipioSelect.value = currentMunicipio;
                }
            }

            if (delegadoLabel) {
                delegadoLabel.textContent = delegadoName
                    ? 'Delegad@ encargado: ' + delegadoName
                    : 'Delegad@ encargado: —';
            }
        };

        microrregionSelect.addEventListener('change', function() {
            updateFromSelectedMicrorregion(this);
        });

        municipioSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const microCodigo = opt ? (opt.dataset.microCodigo || '') : '';
            if (microCodigo && microrregionSelect) {
                microrregionSelect.value = microCodigo;
                updateFromSelectedMicrorregion(microrregionSelect, true);
            }
        });

        updateFromSelectedMicrorregion(microrregionSelect);
    }

    const tipoSelector = document.getElementById('agendaTipoSelector');
    if (tipoSelector) {
        tipoSelector.addEventListener('click', function (e) {
            const pill = e.target.closest('.agenda-type-pill');
            if (!pill) return;
            const subtipo = pill.dataset.subtipo || 'gira';
            const subInput = document.getElementById('modalSubtipo');
            if (subInput) {
                subInput.value = subtipo;
            }
            tipoSelector.querySelectorAll('.agenda-type-pill').forEach(btn => btn.classList.remove('is-active'));
            pill.classList.add('is-active');
        });
    }

    const btnOpenAssign = document.getElementById('btnOpenAssignModal');
    const assignModal = document.getElementById('agendaAssignModal');
    if (btnOpenAssign && assignModal) {
        btnOpenAssign.addEventListener('click', function () {
            assignModal.style.display = 'flex';
        });
        assignModal.addEventListener('click', function (e) {
            if (e.target === assignModal) {
                assignModal.style.display = 'none';
            }
        });
        window.closeAssignModal = function () {
            assignModal.style.display = 'none';
        };
    }

    window.confirmDelete = function(id) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "No podrás revertir esto",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete-form-' + id).submit();
            }
        });
    };

    const moduloModal = document.getElementById('agendaModuloModal');
    if (moduloModal) {
        moduloModal.addEventListener('click', function (e) {
            if (e.target === moduloModal) closeAgendaModuloModal();
        });
    }

    var verDescModal = document.getElementById('agendaVerDescripcionModal');
    if (verDescModal) {
        verDescModal.addEventListener('click', function (e) {
            if (e.target === verDescModal) window.agendaCerrarVerDescripcion();
        });
    }
});

function agendaCsrfToken() {
    const m = document.querySelector('meta[name="csrf-token"]');
    if (m && m.getAttribute('content')) {
        return m.getAttribute('content');
    }
    const input = document.querySelector('input[name="_token"]');
    return input ? input.value : '';
}

/** Evita 404 cuando la app vive en subcarpeta (APP_URL con path); route() rellena las meta en index. */
function agendaModuloApiUrl(kind) {
    const meta = document.querySelector('meta[name="agenda-modulo-' + kind + '"]');
    const c = meta && meta.getAttribute('content');
    if (c) return c;
    const fallback = { enlaces: '/agenda/modulo/enlaces', asignar: '/agenda/modulo/asignar', quitar: '/agenda/modulo/quitar' };
    return fallback[kind] || fallback.enlaces;
}

function openAgendaModuloModal() {
    const el = document.getElementById('agendaModuloModal');
    if (!el) return;
    el.style.display = 'flex';
    el.setAttribute('aria-hidden', 'false');
    const list = document.getElementById('agendaModuloList');
    const loading = document.getElementById('agendaModuloLoading');
    list.innerHTML = '';
    loading.style.display = 'block';
    fetch(agendaModuloApiUrl('enlaces'), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': agendaCsrfToken() },
    })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            loading.style.display = 'none';
            const enlaces = data.enlaces || [];
            if (enlaces.length === 0) {
                list.innerHTML = '<li class="agenda-table-empty" style="border:none;background:transparent;">No hay usuarios con rol Enlace activos.</li>';
                return;
            }
            enlaces.forEach(function (u) {
                const li = document.createElement('li');
                const asignado = u.tiene_agenda;
                li.innerHTML =
                    '<div class="agenda-modulo-user"><strong>' + escapeHtml(u.name) + '</strong><small>' + escapeHtml(u.email) + '</small></div>' +
                    '<div>' +
                    (asignado
                        ? '<span class="agenda-badge is-active" style="margin-right:6px;">Con acceso</span><button type="button" class="agenda-btn agenda-btn-danger" style="min-height:32px;padding:4px 10px;font-size:0.75rem;" data-user-id="' + u.id + '" data-action="quitar">Quitar</button>'
                        : '<button type="button" class="agenda-btn agenda-btn-primary" style="min-height:32px;padding:4px 10px;font-size:0.75rem;" data-user-id="' + u.id + '" data-action="asignar">Asignar Agenda</button>') +
                    '</div>';
                list.appendChild(li);
            });
            list.querySelectorAll('button[data-action]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const uid = btn.getAttribute('data-user-id');
                    const action = btn.getAttribute('data-action');
                    const url = action === 'asignar' ? agendaModuloApiUrl('asignar') : agendaModuloApiUrl('quitar');
                    btn.disabled = true;
                    const token = agendaCsrfToken();
                    const body = new URLSearchParams();
                    body.set('_token', token);
                    body.set('user_id', String(uid));
                    fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: body.toString(),
                    })
                        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                        .then(function (res) {
                            if (res.ok && res.j.ok) {
                                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'success', title: res.j.message || 'Listo', timer: 1300, showConfirmButton: false });
                                openAgendaModuloModal();
                            } else {
                                btn.disabled = false;
                                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: res.j.message || 'Error' });
                            }
                        })
                        .catch(function () {
                            btn.disabled = false;
                            if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Error de red' });
                        });
                });
            });
        })
        .catch(function () {
            loading.style.display = 'none';
            list.innerHTML = '<li class="agenda-table-empty" style="border:none;">No se pudo cargar la lista.</li>';
        });
}

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function closeAgendaModuloModal() {
    const el = document.getElementById('agendaModuloModal');
    if (el) {
        el.style.display = 'none';
        el.setAttribute('aria-hidden', 'true');
    }
}

window.openAgendaModuloModal = openAgendaModuloModal;
window.closeAgendaModuloModal = closeAgendaModuloModal;

/** Abre el modal de solo lectura con la descripción (botón "Ver" en la tabla). */
window.agendaVerDescripcion = function(btn) {
    var text = btn && btn.getAttribute ? btn.getAttribute('data-descripcion') : '';
    var body = document.getElementById('agendaVerDescripcionBody');
    var modal = document.getElementById('agendaVerDescripcionModal');
    if (body) body.textContent = text || '';
    if (modal) {
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
    }
};

/** Cierra el modal de ver descripción. */
window.agendaCerrarVerDescripcion = function() {
    var modal = document.getElementById('agendaVerDescripcionModal');
    if (modal) {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
    }
};

/** Abre el modal de solo lectura con usuarios asignados (botón "Ver" en la tabla). */
window.agendaVerUsuariosAsignados = function(btn) {
    var usersRaw = btn && btn.getAttribute ? (btn.getAttribute('data-users') || '[]') : '[]';
    var users = [];
    try {
        users = JSON.parse(usersRaw);
    } catch (e) {
        users = [];
    }
    var body = document.getElementById('agendaVerUsuariosBody');
    if (body) {
        if (!Array.isArray(users) || users.length === 0) {
            body.textContent = 'Sin asignar';
        } else {
            body.innerHTML = users.map(function (u) {
                var name = (u && u.name) ? String(u.name) : '';
                var email = (u && u.email) ? String(u.email) : '';
                if (!name && !email) return '';
                return '<div style="padding:8px 10px;border:1px solid var(--clr-border);border-radius:10px;margin-bottom:8px;background:var(--clr-card)">' +
                    '<div style="font-weight:700;color:var(--clr-text-main)">' + escapeHtml(name || 'Usuario') + '</div>' +
                    '<div style="font-size:.85rem;color:var(--clr-text-light)">' + escapeHtml(email) + '</div>' +
                '</div>';
            }).filter(Boolean).join('');
        }
    }

    var modal = document.getElementById('agendaVerUsuariosModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
    }
};

window.agendaCerrarVerUsuariosAsignados = function() {
    var modal = document.getElementById('agendaVerUsuariosModal');
    if (modal) {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
    }
};
