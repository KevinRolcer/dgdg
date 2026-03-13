// Agenda Module Functionality

function openAgendaModal(id = null, tipo = 'asunto') {
    const modal = document.getElementById('agendaModal');
    const form = document.getElementById('agendaForm');
    const title = document.getElementById('modalTitle');
    const container = document.getElementById('extraAddressesContainer');
    const tipoInput = document.getElementById('modalTipo');
    const subTipoInput = document.getElementById('modalSubtipo');
    
    // Elements to toggle
    const fieldsGira = document.getElementById('fieldsGira');
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
            title.innerText = itemTipo === 'gira' ? 'Editar Gira/Pre-Gira' : 'Editar Asunto';
            form.action = `/agenda/${id}`;
            document.getElementById('formMethod').value = 'PUT';
            
            document.getElementById('modalAsunto').value = btn.dataset.asunto || '';
            document.getElementById('modalDescripcion').value = btn.dataset.descripcion || '';
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
                    const cb = form.querySelector(`input[name="usuarios_asignados[]"][value="${uid}"]`);
                    if (cb) cb.checked = true;
                });
            }

            // Número telefónico desactivado de momento
            // const addresses = JSON.parse(btn.dataset.addresses || '[]');
            // addresses.forEach(addr => addAddressRow(addr));
        }
    } else {
        title.innerText = tipo === 'gira' ? 'Nueva Gira/Pre-Gira' : 'Nuevo Asunto';
        form.action = '/agenda';
        document.getElementById('formMethod').value = 'POST';
    }
    
    // Toggle fields based on type
    const isGira = tipoInput.value === 'gira';
    if(fieldsGira) fieldsGira.style.display = isGira ? 'block' : 'none';
    if(rowDescripcion) rowDescripcion.style.display = isGira ? 'none' : 'block';
    const tipoSelector = document.getElementById('agendaTipoSelector');
    if (tipoSelector) {
        tipoSelector.style.display = isGira ? 'block' : 'none';
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

function addAddressRow(value = '') {
    const container = document.getElementById('extraAddressesContainer');
    // Quitar el hint si es el primer número (para que no se duplique)
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
    // Backdrop click close
    const modal = document.getElementById('agendaModal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeAgendaModal();
        });
    }

    // Toggle Time logic
    const toggleHora = document.getElementById('modalHabilitarHora');
    if (toggleHora) {
        toggleHora.addEventListener('change', function() {
            document.getElementById('modalHora').style.display = this.checked ? 'block' : 'none';
        });
    }

    // Toggle Unfold Asignar
    const btnAsignar = document.getElementById('btnToggleAsignar');
    if (btnAsignar) {
        btnAsignar.addEventListener('click', () => toggleUnfold('unfoldAsignar'));
    }

    // Número telefónico desactivado de momento
    // const btnAddAddr = document.getElementById('btnAddAddress');
    // if (btnAddAddr) {
    //     btnAddAddr.addEventListener('click', () => addAddressRow());
    // }

    // Modal User Search
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

    // Filter Municipios by Microrregion
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

            // Si queremos conservar el municipio (caso: se eligió primero municipio),
            // re-asignamos el valor solo si sigue visible.
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

        // Al seleccionar un municipio, marcar la microrregión que le corresponde
        municipioSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const microCodigo = opt ? (opt.dataset.microCodigo || '') : '';
            if (microCodigo && microrregionSelect) {
                microrregionSelect.value = microCodigo;
                updateFromSelectedMicrorregion(microrregionSelect, true);
            }
        });

        // Inicializar al cargar modal si ya hay microrregión seleccionada (editar)
        updateFromSelectedMicrorregion(microrregionSelect);
    }

    // Selector de tipo (Gira / Pre-Gira)
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

    // Abrir/cerrar modal de asignación
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

    // Global Confirm Delete
    window.confirmDelete = function(id) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "No podrás revertir esto",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
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
});

function agendaCsrfToken() {
    const m = document.querySelector('meta[name="csrf-token"]');
    if (m && m.getAttribute('content')) {
        return m.getAttribute('content');
    }
    const input = document.querySelector('input[name="_token"]');
    return input ? input.value : '';
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
    fetch('/agenda/modulo/enlaces', {
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
                    const url = action === 'asignar' ? '/agenda/modulo/asignar' : '/agenda/modulo/quitar';
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
                                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'success', title: res.j.message || 'Listo', timer: 1800, showConfirmButton: false });
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
