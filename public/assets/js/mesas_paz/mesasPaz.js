document.addEventListener('DOMContentLoaded', function () {
    const app = document.getElementById('mesasPazApp');
    if (!app) {
        return;
    }

    const modalidadGlobalSelect = document.getElementById('modalidad_global');
    const delegadoAsistioGlobalSelect = document.getElementById('delegado_asistio_global');
    const delegadoAsistenciaGroup = document.getElementById('delegadoAsistenciaGroup');
    const modalidadInfo = document.getElementById('modalidadInfo');
    const capturaPrereqInfo = document.getElementById('capturaPrereqInfo');
    const guardarMunicipioUrl = app.dataset.guardarMunicipioUrl;
    const guardarAcuerdoHoyUrl = app.dataset.guardarAcuerdoHoyUrl;
    const guardarEvidenciaHoyUrl = app.dataset.guardarEvidenciaHoyUrl;
    const eliminarEvidenciaHoyUrl = app.dataset.eliminarEvidenciaHoyUrl;
    const historialDetalleUrl = app.dataset.historialDetalleUrl;
    const importarExcelUrl = app.dataset.importarExcelUrl || '/mesas-paz/importar-excel';
    const vaciarMicrorregionUrl = app.dataset.vaciarMicrorregionUrl || '/mesas-paz/vaciar-microrregion';
    const csrfToken = app.dataset.csrfToken;
    const listaContestados = document.getElementById('listaMunicipiosContestados');
    const btnToggleContestadosDetalle = document.getElementById('btnToggleContestadosDetalle');
    const historialHoyWrapper = document.getElementById('historialHoyWrapper');
    const historialModal = document.getElementById('historialDetalleModal');
    const parteObservacionGroup = document.getElementById('parte_observacion_group');
    const parteObservacionHoy = document.getElementById('parte_observacion_hoy');
    const acuerdoObservacionHoy = document.getElementById('acuerdo_observacion_hoy');
    const acuerdoObservacionLabel = document.getElementById('acuerdo_observacion_label');
    const btnGuardarAcuerdoHoy = document.getElementById('btnGuardarAcuerdoHoy');
    const municipiosCapturaSection = document.getElementById('municipiosCapturaSection');
    const municipiosContestadosSection = document.getElementById('municipiosContestadosSection');
    const specialModeMunicipiosMsg = document.getElementById('specialModeMunicipiosMsg');
    const btnCargarEvidencia = document.getElementById('btnCargarEvidencia');
    const inputEvidenciaHoy = document.getElementById('inputEvidenciaHoy');
    const selectedMicrorregionId = Number(app.dataset.selectedMicrorregionId || 0);
    const evidenciaActualBox = document.getElementById('evidenciaActualBox');
    const evidenciaModal = document.getElementById('evidenciaPreviewDeleteModal');
    const evidenciaModalTitle = document.getElementById('evidenciaPreviewDeleteTitle');
    const evidenciaModalImg = document.getElementById('evidenciaPreviewDeleteImg');
    const evidenciaModalText = document.getElementById('evidenciaPreviewDeleteText');
    const btnConfirmarEliminarEvidencia = document.getElementById('btnConfirmarEliminarEvidencia');
    let historialLastTrigger = null;
    let mostrarDetalleContestados = false;

    function actualizarVistaDetalleContestados() {
        if (!listaContestados) {
            return;
        }

        listaContestados.querySelectorAll('.contestado-detalle-item').forEach(function (item) {
            item.classList.toggle('d-none', !mostrarDetalleContestados);
        });

        if (btnToggleContestadosDetalle) {
            const hayItems = listaContestados.querySelectorAll('.list-group-item').length > 0
                && !listaContestados.querySelector('.list-group-item.text-muted');
            btnToggleContestadosDetalle.style.display = hayItems ? '' : 'none';
            btnToggleContestadosDetalle.textContent = mostrarDetalleContestados ? 'Ver menos' : 'Ver detalle';
        }
    }
    const maxEvidenciasHoy = Number(app.dataset.maxEvidenciasHoy || 3);
    let evidenciasHoy = [];

    try {
        const initialEvidencias = JSON.parse(app.dataset.evidenciasHoy || '[]');
        if (Array.isArray(initialEvidencias)) {
            evidenciasHoy = initialEvidencias
                .map(function (item) {
                    if (!item || typeof item !== 'object') {
                        return null;
                    }

                    const path = String(item.path || '').trim();
                    const url = String(item.url || '').trim();
                    if (!path || !url) {
                        return null;
                    }

                    return { path: path, url: url };
                })
                .filter(function (item) {
                    return !!item;
                });
        }
    } catch (e) {
        evidenciasHoy = [];
    }

    function setupHistorialModalFocusManagement() {
        if (!historialModal || historialModal.dataset.focusManaged === '1') {
            return;
        }

        historialModal.dataset.focusManaged = '1';

        historialModal.addEventListener('hide.bs.modal', function () {
            const focusedEl = document.activeElement;
            if (focusedEl && historialModal.contains(focusedEl) && typeof focusedEl.blur === 'function') {
                focusedEl.blur();
            }
        });

        historialModal.addEventListener('hidden.bs.modal', function () {
            if (historialLastTrigger && document.contains(historialLastTrigger) && typeof historialLastTrigger.focus === 'function') {
                historialLastTrigger.focus();
            }
            historialLastTrigger = null;
        });
    }

    function renderizarEvidenciaActual() {
        if (!evidenciaActualBox) {
            return;
        }

        const placeholder = document.getElementById('dropzonePlaceholder');

        if (!evidenciasHoy.length) {
            evidenciaActualBox.innerHTML = '<span class="text-muted">Sin evidencia cargada hoy.</span>';
            if (placeholder) placeholder.classList.remove('d-none');
            return;
        }

        if (placeholder) placeholder.classList.add('d-none');

        evidenciaActualBox.innerHTML = '<div class="evidencias-grid">'
            + evidenciasHoy.map(function (item, index) {
                const url = escapeHtml(item.url);
                const path = escapeHtml(item.path);

                return '<div class="evidencia-card">'
                    + '<button type="button" class="btn btn-sm btn-danger btn-eliminar-evidencia" data-index="' + index + '" data-path="' + path + '" aria-label="Eliminar evidencia">×</button>'
                    + '<img src="' + url + '" alt="Evidencia ' + (index + 1) + '" class="evidencia-thumb">'
                    + '<div class="d-flex gap-1 mt-2">'
                    + '<button type="button" class="btn btn-sm btn-outline-primary flex-fill btn-preview-evidencia" data-index="' + index + '">Vista previa</button>'
                    + '<a class="btn btn-sm btn-outline-secondary flex-fill" target="_blank" rel="noopener noreferrer" href="' + url + '">Abrir</a>'
                    + '</div>'
                    + '</div>';
            }).join('')
            + '</div>';
    }

    // Convierte texto libre en acuerdos. Si detecta viñetas, toma cada bloque completo como un acuerdo.
    function normalizarAcuerdoItemsDesdeTexto(texto) {
        if (!texto) {
            return [];
        }

        const textoNormalizado = String(texto).replace(/\r\n|\r/g, '\n');
        const patronBloqueVinyeta = /(?:^|\n)\s*(?:[\-\*\u2022]|\d+[\.)])\s+([\s\S]*?)(?=(?:\n\s*(?:[\-\*\u2022]|\d+[\.)])\s+)|$)/gu;
        const bloques = [];

        let match;
        while ((match = patronBloqueVinyeta.exec(textoNormalizado)) !== null) {
            const contenido = String(match[1] || '')
                .replace(/\s*\n\s*/g, ' ')
                .trim();

            if (contenido.length > 0 && /[\p{L}\p{N}]/u.test(contenido)) {
                bloques.push(contenido);
            }
        }

        if (bloques.length > 0) {
            return bloques;
        }

        return textoNormalizado
            .split(/\n/g)
            .map(function (linea) {
                return String(linea || '').trim();
            })
            .filter(function (linea) {
                return linea.length > 0;
            })
            .map(function (linea) {
                return linea.replace(/^[\-\*\u2022]\s*/u, '').trim();
            })
            .filter(function (linea) {
                return /[\p{L}\p{N}]/u.test(linea);
            });
    }

    function formatearAcuerdoItemsComoTexto(items) {
        if (!items || !items.length) {
            return '';
        }

        return items.map(function (item) {
            return '• ' + item;
        }).join('\n');
    }

    function obtenerItemsDesdeTextarea(textarea) {
        if (!textarea) {
            return [];
        }

        return normalizarAcuerdoItemsDesdeTexto(textarea.value || '');
    }

    function obtenerAcuerdoItems() {
        return obtenerItemsDesdeTextarea(acuerdoObservacionHoy);
    }

    function obtenerParteItems() {
        return obtenerItemsDesdeTextarea(parteObservacionHoy);
    }

    function escapeHtml(texto) {
        return String(texto || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizarTextoCargo(valor) {
        const texto = String(valor || '').trim();
        const textoLower = texto.toLowerCase();

        if (textoLower === 'presidente y representante' || textoLower === 'ambos') {
            return 'Presidente y Director de Seguridad';
        }

        if (textoLower === 'presidente y director de seguridad') {
            return 'Presidente y Director de Seguridad';
        }

        if (textoLower === 'director de seguridad' || textoLower === 'director de seguridad municipal') {
            return 'Director de Seguridad Municipal';
        }

        if (textoLower === 'secretario/regidor de gobernación' || textoLower === 'secretario/regidor de gobernacion') {
            return 'Secretario/Regidor de Gobernación';
        }

        if (textoLower === 'presidente') {
            return 'Presidente Municipal';
        }

        return texto;
    }

    function insertarTextoEnCursor(el, texto) {
        const inicio = el.selectionStart;
        const fin = el.selectionEnd;
        const previo = el.value;
        el.value = previo.substring(0, inicio) + texto + previo.substring(fin);
        const nuevaPosicion = inicio + texto.length;
        el.selectionStart = nuevaPosicion;
        el.selectionEnd = nuevaPosicion;
    }

    // Regla de captura: modalidad y asistencia del delegado son requisitos previos.
    function puedeCapturarMunicipio() {
        return !!(modalidadGlobalSelect && modalidadGlobalSelect.value && delegadoAsistioGlobalSelect && delegadoAsistioGlobalSelect.value);
    }

    function obtenerReglaEspecialPorModalidad() {
        if (!modalidadGlobalSelect || !modalidadGlobalSelect.value) {
            return null;
        }

        const modalidad = String(modalidadGlobalSelect.value || '').trim();
        if (modalidad === 'Sin reporte de Delegado') {
            return {
                delegadoAsistio: 'No',
                presidente: 'No',
            };
        }

        if (modalidad === 'Sin información de enlace') {
            return {
                delegadoAsistio: 'No',
                presidente: 'No',
            };
        }

        if (modalidad === 'Suspención de mesa de Seguridad' || modalidad === 'Suspención de la Mesa de Seguridad') {
            return {
                delegadoAsistio: 'No',
                presidente: 'No',
            };
        }

        return null;
    }

    function esModoEspecialModalidad() {
        return !!obtenerReglaEspecialPorModalidad();
    }

    function esModalidadSuspension() {
        if (!modalidadGlobalSelect || !modalidadGlobalSelect.value) {
            return false;
        }

        const modalidad = String(modalidadGlobalSelect.value || '').trim();
        return modalidad === 'Suspención de mesa de Seguridad' || modalidad === 'Suspención de la Mesa de Seguridad';
    }

    function actualizarVisibilidadDelegadoAsistio() {
        if (!delegadoAsistenciaGroup || !delegadoAsistioGlobalSelect) {
            return;
        }

        const suspensionActiva = esModalidadSuspension();
        delegadoAsistenciaGroup.classList.toggle('d-none', suspensionActiva);
        delegadoAsistioGlobalSelect.disabled = suspensionActiva;

        if (suspensionActiva) {
            delegadoAsistioGlobalSelect.value = 'No';
        }
    }

    function aplicarReglaEspecialModalidad() {
        const regla = obtenerReglaEspecialPorModalidad();
        if (!regla) {
            return;
        }

        if (delegadoAsistioGlobalSelect) {
            delegadoAsistioGlobalSelect.value = regla.delegadoAsistio;
        }

        document.querySelectorAll('.presidente-select').forEach(function (select) {
            select.value = regla.presidente;
            actualizarRepresentante(select.getAttribute('data-municipio-id'));
        });
    }

    function actualizarModoEspecialUI() {
        const esEspecial = esModoEspecialModalidad();

        if (municipiosCapturaSection) {
            municipiosCapturaSection.classList.toggle('d-none', esEspecial);
        }

        if (municipiosContestadosSection) {
            municipiosContestadosSection.classList.toggle('d-none', esEspecial);
        }

        if (specialModeMunicipiosMsg) {
            specialModeMunicipiosMsg.classList.toggle('d-none', !esEspecial);
        }
    }

    function actualizarVisibilidadPartePorModalidad() {
        const suspensionActiva = esModalidadSuspension();

        if (parteObservacionGroup) {
            parteObservacionGroup.classList.toggle('d-none', suspensionActiva);
        }

        if (!parteObservacionHoy) {
            return;
        }

        parteObservacionHoy.disabled = suspensionActiva;
        if (suspensionActiva) {
            parteObservacionHoy.value = '• S/R';
        } else if (!parteObservacionHoy.value || !parteObservacionHoy.value.trim()) {
            parteObservacionHoy.value = '• ';
        }
    }

    function actualizarTextosObservacionPorModalidad() {
        const suspensionActiva = esModalidadSuspension();

        if (acuerdoObservacionLabel) {
            acuerdoObservacionLabel.textContent = suspensionActiva
                ? 'Nota/Observación'
                : 'Acuerdos/Observaciones';
        }

        if (btnGuardarAcuerdoHoy) {
            btnGuardarAcuerdoHoy.textContent = suspensionActiva
                ? 'Guardar Nota/Observación'
                : 'Guardar Parte y Acuerdos';
        }
    }

    function esMunicipioRegistrado(municipioId) {
        const card = document.getElementById('municipio_card_' + municipioId);
        return !!(card && card.getAttribute('data-registrado') === '1');
    }

    function actualizarEstadoCaptura() {
        const habilitar = puedeCapturarMunicipio() && !esModoEspecialModalidad();

        document.querySelectorAll('.presidente-select').forEach(function (select) {
            const municipioId = select.getAttribute('data-municipio-id');
            const registrado = esMunicipioRegistrado(municipioId);
            select.disabled = !habilitar || registrado;
        });

        document.querySelectorAll('.btn-guardar-municipio').forEach(function (button) {
            const municipioId = button.getAttribute('data-municipio-id');
            const registrado = esMunicipioRegistrado(municipioId);
            button.disabled = !habilitar || registrado;
            if (registrado) {
                button.textContent = 'Registrado';
            }
        });

        document.querySelectorAll('.presidente-option-input').forEach(function (input) {
            const municipioId = input.getAttribute('data-municipio-id');
            const registrado = esMunicipioRegistrado(municipioId);
            input.disabled = !habilitar || registrado;
        });

        document.querySelectorAll('.representante-option-input').forEach(function (input) {
            const municipioId = input.getAttribute('data-municipio-id');
            const presidenteSelect = document.getElementById('presidente_' + municipioId);
            const registrado = esMunicipioRegistrado(municipioId);
            const habilitarRepresentante = habilitar && !registrado && presidenteSelect && ['Representante', 'Ambos'].includes(presidenteSelect.value) && !!document.getElementById('representante_' + municipioId);
            input.disabled = !habilitarRepresentante;
        });

        if (capturaPrereqInfo) {
            capturaPrereqInfo.classList.toggle('d-none', habilitar);
        }
    }

    function actualizarBotonesPresidente(municipioId) {
        const presidenteSelect = document.getElementById('presidente_' + municipioId);
        if (!presidenteSelect) {
            return;
        }

        document.querySelectorAll('.presidente-option-input[data-municipio-id="' + municipioId + '"]').forEach(function (input) {
            input.checked = input.value === presidenteSelect.value;
        });
    }

    function actualizarBotonesRepresentante(municipioId) {
        const representanteSelect = document.getElementById('representante_' + municipioId);
        if (!representanteSelect) {
            return;
        }

        document.querySelectorAll('.representante-option-input[data-municipio-id="' + municipioId + '"]').forEach(function (input) {
            input.checked = input.value === representanteSelect.value;
        });
    }

    function sincronizarSelectsDesdeBotones(municipioId) {
        const presidenteSelect = document.getElementById('presidente_' + municipioId);
        const representanteSelect = document.getElementById('representante_' + municipioId);

        if (presidenteSelect) {
            const presidenteChecked = document.querySelector('.presidente-option-input[data-municipio-id="' + municipioId + '"]:checked');
            const valorPresidente = presidenteChecked ? presidenteChecked.value : '';
            if (valorPresidente) {
                presidenteSelect.value = valorPresidente;
            }
        }

        if (representanteSelect) {
            const representanteChecked = document.querySelector('.representante-option-input[data-municipio-id="' + municipioId + '"]:checked');
            const valorRepresentante = representanteChecked ? representanteChecked.value : '';
            if (valorRepresentante) {
                representanteSelect.value = valorRepresentante;
            }
        }
    }

    // Control y validación del campo de representante por municipio.
    function actualizarRepresentante(municipioId) {
        const presidenteSelect = document.getElementById('presidente_' + municipioId);
        const representanteWrap = document.getElementById('representante_wrap_' + municipioId);
        const representanteSelect = document.getElementById('representante_' + municipioId);

        if (!presidenteSelect) {
            return;
        }

        if (!representanteWrap || !representanteSelect) {
            actualizarBotonesPresidente(municipioId);
            return;
        }

        const requiereRepresentante = ['Representante', 'Ambos'].includes(presidenteSelect.value);
        representanteWrap.classList.toggle('d-none', !requiereRepresentante);
        representanteSelect.disabled = !requiereRepresentante;

        document.querySelectorAll('.representante-option-input[data-municipio-id="' + municipioId + '"]').forEach(function (input) {
            input.disabled = !requiereRepresentante || presidenteSelect.disabled;
        });

        if (!requiereRepresentante) {
            representanteSelect.value = '';
        }

        actualizarBotonesPresidente(municipioId);
        actualizarBotonesRepresentante(municipioId);
    }

    function renderContestados(items) {
        if (!listaContestados) return;

        if (!items || !items.length) {
            listaContestados.innerHTML = '<li class="list-group-item text-muted">Sin municipios contestados aún.</li>';
            return;
        }

        listaContestados.innerHTML = items.map(function (item) {
            // Ajuste de seguridad para evitar inyección de HTML en los nombres de municipios o presidentes.
            const municipio = escapeHtml(item.municipio || 'N/D');
            const presidente = escapeHtml(item.presidente || 'N/D');
            const asiste = item.asiste ? (' · Asiste: ' + escapeHtml(normalizarTextoCargo(item.asiste))) : '';

            return '<li class="list-group-item">'
                + '<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">'
                + '<div>'
                + '<div class="fw-bold">' + municipio + '</div>'
                + '</div>'
            + '<span class="badge bg-success">Registrado</span>'
                + '</div>'
            + '<div class="small text-muted mt-2 d-none contestado-detalle-item">Asistió: ' + presidente + asiste + '</div>'
                + '</div>'
                + '</li>';
        }).join('');

        actualizarVistaDetalleContestados();
    }

    function bloquearModalidadSiCorresponde() {
        if (!modalidadGlobalSelect || !delegadoAsistioGlobalSelect) {
            return;
        }

        modalidadGlobalSelect.disabled = false;
        delegadoAsistioGlobalSelect.disabled = false;
        if (modalidadInfo) {
            modalidadInfo.classList.add('d-none');
        }
    }

    // Mantiene visibles los municipios pendientes y oculta los capturados.
    function actualizarVisibilidadMunicipios() {
        const cards = document.querySelectorAll('.municipio-card');
        const emptyState = document.getElementById('sinMunicipiosPendientes');
        let pendientes = 0;

        cards.forEach(function (card) {
            const registrado = card.getAttribute('data-registrado') === '1';
            const ocultar = registrado;
            card.classList.toggle('d-none', ocultar);
            if (!ocultar) {
                pendientes += 1;
            }
        });

        if (emptyState) {
            emptyState.classList.toggle('d-none', pendientes > 0);
        }
    }

    // Acción de historial y carga en modal
    function bindHistorialActions() {
        document.querySelectorAll('.btn-ver-historial-fecha').forEach(function (button) {
            if (button.dataset.bound === '1') {
                return;
            }

            button.dataset.bound = '1';
            button.addEventListener('click', function () {
                const fechaRaw = this.getAttribute('data-fecha');
                const fecha = (fechaRaw || '').toString().trim().slice(0, 10);
                if (!fecha) {
                    return;
                }

                const modalBody = document.getElementById('historialDetalleBody');
                const modalFechaLabel = document.getElementById('historialDetalleFecha');
                if (!historialModal) {
                    return;
                }

                const modalInstance = bootstrap.Modal.getOrCreateInstance(historialModal);
                historialLastTrigger = this;

                modalFechaLabel.textContent = fecha;
                modalBody.innerHTML = '<div class="text-muted">Cargando detalle...</div>';
                modalInstance.show();

                fetch(historialDetalleUrl + '?fecha=' + encodeURIComponent(fecha), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { status: response.status, data: data };
                    });
                })
                .then(function (result) {
                    if (result.status >= 400 || !result.data.success) {
                        throw result.data;
                    }

                    if (!result.data.registros || !result.data.registros.length) {
                        modalBody.innerHTML = '<div class="alert alert-secondary mb-0">Sin registros para esta fecha.</div>';
                        return;
                    }

                    // Agrupar por microrregiones
                    const groups = {};
                    result.data.registros.forEach(function (registro) {
                        const mid = registro.microrregion_id || 0;
                        if (!groups[mid]) {
                            groups[mid] = {
                                nombre: registro.microrregion_nombre || 'Sin Microrregión',
                                cabecera: registro.microrregion_cabecera || '',
                                registros: [],
                                parte_items: [],
                                acuerdo_items: [],
                                evidencias: []
                            };
                        }
                        groups[mid].registros.push(registro);

                        // Acumular partes, acuerdos y evidencias únicos para el grupo
                        if (Array.isArray(registro.parte_observacion_items)) {
                            registro.parte_observacion_items.forEach(function (it) {
                                if (it && !groups[mid].parte_items.includes(it)) groups[mid].parte_items.push(it);
                            });
                        }
                        if (Array.isArray(registro.acuerdo_observacion_items)) {
                            registro.acuerdo_observacion_items.forEach(function (it) {
                                if (it && !groups[mid].acuerdo_items.includes(it)) groups[mid].acuerdo_items.push(it);
                            });
                        }
                        if (Array.isArray(registro.evidencia_urls)) {
                            registro.evidencia_urls.forEach(function (it) {
                                if (it && !groups[mid].evidencias.includes(it)) groups[mid].evidencias.push(it);
                            });
                        }
                    });

                    let modalHtml = '';
                    const groupEntries = Object.values(groups);
                    
                    groupEntries.forEach(function (group, index) {
                        const rows = group.registros.map(function (reg, i) {
                            const asisteTexto = normalizarTextoCargo(reg.asiste || 'N/D');
                            return '<tr>' +
                                '<td>' + (i + 1) + '</td>' +
                                '<td>' + escapeHtml(reg.municipio || 'N/D') + '</td>' +
                                '<td>' + escapeHtml(reg.presidente || 'N/D') + '</td>' +
                                '<td>' + escapeHtml(asisteTexto) + '</td>' +
                                '<td>' + escapeHtml(reg.modalidad || 'N/D') + '</td>' +
                                '</tr>';
                        }).join('');

                        const evidenciasHtml = group.evidencias.length
                            ? '<div class="mt-2"><strong>Evidencias:</strong><ul class="mb-0 mt-1">'
                                + group.evidencias.map(function (url, i) {
                                    return '<li><a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">Evidencia ' + (i + 1) + '</a></li>';
                                }).join('')
                                + '</ul></div>'
                            : '<div class="mt-2 text-muted"><strong>Evidencia:</strong> Sin evidencia disponible</div>';

                        const partesHtml = group.parte_items.length
                            ? '<div><strong>Parte:</strong><ul class="mb-2 mt-1">'
                                + group.parte_items.map(function (item) {
                                    return '<li>' + escapeHtml(item) + '</li>';
                                }).join('')
                                + '</ul></div>'
                            : '<div class="mb-2"><strong>Parte:</strong> <span class="text-muted">Sin registro de parte.</span></div>';

                        const acuerdosHtml = group.acuerdo_items.length
                            ? '<div><strong>Observación/Acuerdo:</strong><ul class="mb-2 mt-1">'
                                + group.acuerdo_items.map(function (item) {
                                    return '<li>' + escapeHtml(item) + '</li>';
                                }).join('')
                                + '</ul></div>'
                            : '';

                        modalHtml += '<div class="' + (index < groupEntries.length - 1 ? 'mb-4 border-bottom pb-4' : 'mb-2') + '">' +
                            '<h6 class="fw-bold mb-3" style="color: var(--clr-segob-red, #861e34);">' +
                            '<i class="bi bi-geo-alt-fill me-1"></i> Microrregión: ' + escapeHtml(group.nombre) + 
                            (group.cabecera ? ' (' + escapeHtml(group.cabecera) + ')' : '') +
                            '</h6>' +
                            '<div class="table-responsive">' +
                            '<table class="table table-sm table-bordered align-middle mb-2">' +
                            '<thead><tr class="table-light"><th>#</th><th>Municipio</th><th>Asistió</th><th>Asiste</th><th>Modalidad</th></tr></thead>' +
                            '<tbody>' + rows + '</tbody>' +
                            '</table></div>' +
                            partesHtml +
                            acuerdosHtml +
                            evidenciasHtml +
                            '</div>';
                    });

                    modalBody.innerHTML = modalHtml;
                })
                .catch(function (errorData) {
                    const backendErrors = errorData && errorData.errors ? Object.values(errorData.errors).flat() : [];
                    const message = backendErrors[0] || errorData.message || 'No fue posible cargar el detalle.';
                    modalBody.innerHTML = '<div class="alert alert-danger mb-0">' + message + '</div>';
                });
            });
        });
    }

    // Formato de fechas dd/mm/yyyy para el historial.
    function formatearFechaDDMMYYYY(fechaIso) {
        if (!fechaIso || typeof fechaIso !== 'string') {
            return 'N/D';
        }

        const fechaNormalizada = fechaIso.trim().slice(0, 10);
        const partes = fechaNormalizada.split('-');
        if (partes.length !== 3) {
            return fechaIso;
        }

        return partes[2] + '/' + partes[1] + '/' + partes[0];
    }

    // Actualización del historial sin recargar la página.
    function refrescarHistorialHoy(historialHoy) {
        if (!historialHoyWrapper) {
            return;
        }

        if (!historialHoy || !historialHoy.fecha_asist) {
            historialHoyWrapper.innerHTML = '<div class="alert alert-secondary mb-0" id="historialHoyEmpty">Sin registros por el momento.</div>';
            return;
        }

        const fechaIso = String(historialHoy.fecha_asist || '').trim().slice(0, 10);
        const fechaFormato = formatearFechaDDMMYYYY(fechaIso);
        const ultimaCaptura = historialHoy.ultima_captura || 'N/D';
        const totalRegistros = Number(historialHoy.total_registros || 0);

        historialHoyWrapper.innerHTML =
            '<div class="list-group" id="historialHoyList">'
            + '<div class="list-group-item">'
            + '<div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">'
            + '<div>'
            + '<div class="fw-bold">' + fechaFormato + '</div>'
            + '<div class="small text-muted">Registros: ' + totalRegistros + ' · Última captura: ' + ultimaCaptura + '</div>'
            + '</div>'
            + '<button type="button" class="btn btn-sm btn-outline-primary btn-ver-historial-fecha" data-fecha="' + fechaIso + '">Ver detalle</button>'
            + '</div>'
            + '</div>'
            + '</div>';

        bindHistorialActions();
    }

    document.querySelectorAll('.presidente-select').forEach(function (select) {
        actualizarRepresentante(select.getAttribute('data-municipio-id'));
        select.addEventListener('change', function () {
            actualizarRepresentante(this.getAttribute('data-municipio-id'));
        });
    });

    document.querySelectorAll('.presidente-option-input').forEach(function (input) {
        input.addEventListener('change', function () {
            if (input.disabled) {
                return;
            }

            const municipioId = input.getAttribute('data-municipio-id');
            const value = input.value || '';
            const presidenteSelect = document.getElementById('presidente_' + municipioId);

            if (!presidenteSelect) {
                return;
            }

            presidenteSelect.value = value;
            actualizarRepresentante(municipioId);
        });
    });

    document.querySelectorAll('.representante-option-input').forEach(function (input) {
        input.addEventListener('change', function () {
            if (input.disabled) {
                return;
            }

            const municipioId = input.getAttribute('data-municipio-id');
            const value = input.value || '';
            const representanteSelect = document.getElementById('representante_' + municipioId);

            if (!representanteSelect) {
                return;
            }

            representanteSelect.value = value;
            actualizarBotonesRepresentante(municipioId);
        });
    });

    if (modalidadGlobalSelect) {
        modalidadGlobalSelect.addEventListener('change', function () {
            aplicarReglaEspecialModalidad();
            actualizarVisibilidadDelegadoAsistio();
            actualizarVisibilidadPartePorModalidad();
            actualizarTextosObservacionPorModalidad();
            actualizarModoEspecialUI();
            actualizarEstadoCaptura();
        });
    }

    if (delegadoAsistioGlobalSelect) {
        delegadoAsistioGlobalSelect.addEventListener('change', function () {
            actualizarEstadoCaptura();
        });
    }

    if (btnToggleContestadosDetalle) {
        btnToggleContestadosDetalle.addEventListener('click', function () {
            mostrarDetalleContestados = !mostrarDetalleContestados;
            actualizarVistaDetalleContestados();
        });
    }

    function abrirModalEvidencia(opciones) {
        if (!evidenciaModal || !evidenciaModalImg || !evidenciaModalTitle || !evidenciaModalText || !btnConfirmarEliminarEvidencia) {
            return;
        }

        evidenciaModalTitle.textContent = opciones.titulo || 'Vista previa de evidencia';
        evidenciaModalText.textContent = opciones.texto || '';
        evidenciaModalImg.src = opciones.url || '';
        evidenciaModalImg.alt = opciones.titulo || 'Vista previa de evidencia';

        if (opciones.modoEliminar) {
            btnConfirmarEliminarEvidencia.classList.remove('d-none');
            btnConfirmarEliminarEvidencia.dataset.path = opciones.path || '';
        } else {
            btnConfirmarEliminarEvidencia.classList.add('d-none');
            btnConfirmarEliminarEvidencia.dataset.path = '';
        }

        const modalInstance = bootstrap.Modal.getOrCreateInstance(evidenciaModal);
        modalInstance.show();
    }

    function actualizarEvidenciasDesdeRespuesta(data) {
        if (!data || !Array.isArray(data.evidencias)) {
            return;
        }

        evidenciasHoy = data.evidencias
            .map(function (item) {
                if (!item || typeof item !== 'object') {
                    return null;
                }

                const path = String(item.path || '').trim();
                const url = String(item.url || '').trim();
                if (!path || !url) {
                    return null;
                }

                return { path: path, url: url };
            })
            .filter(function (item) {
                return !!item;
            });

        renderizarEvidenciaActual();
    }

    function subirUnaEvidencia(archivo) {
        const formData = new FormData();
        formData.append('evidencia', archivo);
        if (selectedMicrorregionId > 0) {
            formData.append('microrregion_id', String(selectedMicrorregionId));
        }
        if (app.dataset.fechaHoyIso) {
            formData.append('fecha_asist', app.dataset.fechaHoyIso);
        }

        return fetch(guardarEvidenciaHoyUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(function (response) {
            return response.json().then(function (data) {
                return { status: response.status, data: data };
            });
        })
        .then(function (result) {
            if (result.status >= 400 || !result.data.success) {
                throw result.data;
            }

            actualizarEvidenciasDesdeRespuesta(result.data);
            return result.data;
        });
    }

    function eliminarUnaEvidencia(path) {
        const payload = {
            evidencia_path: path
        };

        if (selectedMicrorregionId > 0) {
            payload.microrregion_id = selectedMicrorregionId;
        }
        if (app.dataset.fechaHoyIso) {
            payload.fecha_asist = app.dataset.fechaHoyIso;
        }

        return fetch(eliminarEvidenciaHoyUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
        .then(function (response) {
            return response.json().then(function (data) {
                return { status: response.status, data: data };
            });
        })
        .then(function (result) {
            if (result.status >= 400 || !result.data.success) {
                throw result.data;
            }

            actualizarEvidenciasDesdeRespuesta(result.data);
            return result.data;
        });
    }

    if (btnConfirmarEliminarEvidencia) {
        btnConfirmarEliminarEvidencia.addEventListener('click', function () {
            const path = btnConfirmarEliminarEvidencia.dataset.path || '';
            if (!path) {
                return;
            }

            btnConfirmarEliminarEvidencia.disabled = true;

            eliminarUnaEvidencia(path)
                .then(function (data) {
                    const modalInstance = evidenciaModal ? bootstrap.Modal.getOrCreateInstance(evidenciaModal) : null;
                    if (modalInstance) {
                        modalInstance.hide();
                    }

                    swal('Éxito', data.message || 'Evidencia eliminada correctamente.', 'success');
                })
                .catch(function (errorData) {
                    const backendErrors = errorData && errorData.errors ? Object.values(errorData.errors).flat() : [];
                    const message = backendErrors[0] || errorData.message || 'No fue posible eliminar la evidencia.';
                    swal('Error', message, 'error');
                })
                .finally(function () {
                    btnConfirmarEliminarEvidencia.disabled = false;
                });
        });
    }

    if (evidenciaActualBox) {
        evidenciaActualBox.addEventListener('click', function (event) {
            const previewBtn = event.target.closest('.btn-preview-evidencia');
            if (previewBtn) {
                const index = Number(previewBtn.getAttribute('data-index'));
                const item = evidenciasHoy[index];
                if (!item) {
                    return;
                }

                abrirModalEvidencia({
                    titulo: 'Vista previa de evidencia',
                    texto: '',
                    url: item.url,
                    modoEliminar: false,
                });
                return;
            }

            const eliminarBtn = event.target.closest('.btn-eliminar-evidencia');
            if (eliminarBtn) {
                const index = Number(eliminarBtn.getAttribute('data-index'));
                const item = evidenciasHoy[index];
                if (!item) {
                    return;
                }

                abrirModalEvidencia({
                    titulo: 'Eliminar evidencia',
                    texto: '¿Está seguro de borrar esta imagen?',
                    url: item.url,
                    path: item.path,
                    modoEliminar: true,
                });
            }
        });
    }

    const btnPegarEvidencia = document.getElementById('btnPegarEvidencia');
    const dropzoneEvidencia = document.getElementById('dropzoneEvidencia');
    const dropzonePlaceholder = document.getElementById('dropzonePlaceholder');

    function validarYSubirArchivos(archivos) {
        if (!archivos || !archivos.length) {
            return;
        }

        const disponibles = Math.max(0, maxEvidenciasHoy - evidenciasHoy.length);
        if (disponibles <= 0) {
            swal('Límite alcanzado', 'Solo puedes cargar hasta ' + maxEvidenciasHoy + ' imágenes.', 'warning');
            return;
        }

        const tiposPermitidos = ['image/jpeg', 'image/png', 'image/webp'];
        const maxSize = 10 * 1024 * 1024;

        const archivosValidos = [];
        archivos.forEach(function (archivo) {
            if (!tiposPermitidos.includes(archivo.type)) {
                return;
            }
            if (archivo.size > maxSize) {
                return;
            }
            archivosValidos.push(archivo);
        });

        if (!archivosValidos.length) {
            swal('Archivo no permitido', 'Solo se permiten imágenes JPG, PNG o WEBP de hasta 10MB.', 'warning');
            return;
        }

        const archivosParaSubir = archivosValidos.slice(0, disponibles);
        
        const textoOriginal = btnCargarEvidencia.innerHTML;
        btnCargarEvidencia.disabled = true;
        if (btnPegarEvidencia) btnPegarEvidencia.disabled = true;
        
        btnCargarEvidencia.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Subiendo...';

        let cadena = Promise.resolve();
        archivosParaSubir.forEach(function (archivo) {
            cadena = cadena.then(function () {
                return subirUnaEvidencia(archivo);
            });
        });

        cadena
            .then(function () {
                swal('Éxito', 'Evidencias guardadas correctamente.', 'success');
            })
            .catch(function (errorData) {
                const backendErrors = errorData && errorData.errors ? Object.values(errorData.errors).flat() : [];
                const message = backendErrors[0] || errorData.message || 'No fue posible guardar la evidencia.';
                swal('Error', message, 'error');
            })
            .finally(function () {
                btnCargarEvidencia.disabled = false;
                if (btnPegarEvidencia) btnPegarEvidencia.disabled = false;
                btnCargarEvidencia.innerHTML = textoOriginal;
                if (inputEvidenciaHoy) inputEvidenciaHoy.value = '';
                
                if (dropzonePlaceholder) {
                    if (evidenciasHoy.length > 0) {
                        dropzonePlaceholder.classList.add('d-none');
                    } else {
                        dropzonePlaceholder.classList.remove('d-none');
                    }
                }
            });
    }

    if (btnPegarEvidencia) {
        btnPegarEvidencia.addEventListener('click', function() {
            swal({
                title: "Pegar imagen",
                text: "Usa Ctrl+V para pegar una imagen desde tu portapapeles ahora mismo.",
                icon: "info",
                buttons: false,
                timer: 3000
            });
        });
    }

    if (dropzoneEvidencia) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzoneEvidencia.addEventListener(eventName, e => {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropzoneEvidencia.addEventListener(eventName, () => {
                dropzoneEvidencia.classList.add('is-dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzoneEvidencia.addEventListener(eventName, () => {
                dropzoneEvidencia.classList.remove('is-dragover');
            }, false);
        });

        dropzoneEvidencia.addEventListener('drop', e => {
            const dt = e.dataTransfer;
            const files = dt.files;
            validarYSubirArchivos(Array.from(files));
        });

        // Permitir clic en el dropzone para abrir el selector de archivos si el box está vacío
        dropzoneEvidencia.addEventListener('click', function(e) {
            if (e.target.closest('.evidencias-grid') || e.target.closest('.btn-preview-evidencia') || e.target.closest('.btn-eliminar-evidencia')) {
                return;
            }
            if (inputEvidenciaHoy) inputEvidenciaHoy.click();
        });
    }

    // Listener global de pegado
    window.addEventListener('paste', function(e) {
        // No interferir si el foco está en un textarea o input
        const target = e.target;
        if (target.tagName === 'TEXTAREA' || target.tagName === 'INPUT') {
            return;
        }

        const items = (e.clipboardData || e.originalEvent.clipboardData).items;
        const files = [];
        
        for (let i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                files.push(items[i].getAsFile());
            }
        }

        if (files.length > 0) {
            validarYSubirArchivos(files);
        }
    });

    if (btnCargarEvidencia && inputEvidenciaHoy && guardarEvidenciaHoyUrl) {
        btnCargarEvidencia.addEventListener('click', function () {
            if (btnCargarEvidencia.disabled) {
                return;
            }
            inputEvidenciaHoy.click();
        });

        inputEvidenciaHoy.addEventListener('change', function () {
            const archivos = Array.from(inputEvidenciaHoy.files || []);
            validarYSubirArchivos(archivos);
        });
    }

    // Lógica de asistencia por municipio.
    document.querySelectorAll('.btn-guardar-municipio').forEach(function (button) {
        button.addEventListener('click', function () {
            const municipioId = this.getAttribute('data-municipio-id');
            const presidenteSelect = document.getElementById('presidente_' + municipioId);
            const representanteSelect = document.getElementById('representante_' + municipioId);
            const estadoLabel = document.getElementById('status_municipio_' + municipioId);

            if (esMunicipioRegistrado(municipioId)) {
                swal('No permitido', 'La asistencia de este municipio ya fue registrada hoy y no puede editarse nuevamente.', 'warning');
                return;
            }

            sincronizarSelectsDesdeBotones(municipioId);
            actualizarRepresentante(municipioId);

            if (!modalidadGlobalSelect || !modalidadGlobalSelect.value || !delegadoAsistioGlobalSelect || !delegadoAsistioGlobalSelect.value) {
                swal('Información requerida', 'Antes de capturar municipios debes seleccionar primero la modalidad de la sesión y si el delegado asistió (Sí/No/SR).', 'warning');
                return;
            }

            if (!presidenteSelect || !presidenteSelect.value) {
                swal('Dato requerido', 'Selecciona la opción de asistencia.', 'warning');
                return;
            }

            const representanteValue = representanteSelect
                ? (representanteSelect.value || null)
                : (['Representante', 'Ambos'].includes(presidenteSelect.value) ? 'Director de seguridad' : null);

            const originalText = button.textContent;
            button.disabled = true;
            button.textContent = 'Guardando...';

            fetch(guardarMunicipioUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    modalidad: modalidadGlobalSelect.value,
                    delegado_asistio: delegadoAsistioGlobalSelect.value,
                    municipio_id: Number(municipioId),
                    presidente: presidenteSelect.value,
                    representante: representanteValue,
                    fecha_asist: app.dataset.fechaHoyIso
                })
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { status: response.status, data: data };
                });
            })
            .then(function (result) {
                if (result.status >= 400 || !result.data.success) {
                    throw result.data;
                }

                swal('Éxito', result.data.message || 'Municipio registrado.', 'success');
                button.textContent = 'Registrado';
                if (estadoLabel) {
                    estadoLabel.textContent = 'Registrado hoy';
                    estadoLabel.className = 'text-success';
                    estadoLabel.setAttribute('data-registrado', '1');
                }

                const card = document.getElementById('municipio_card_' + municipioId);
                if (card) {
                    card.setAttribute('data-registrado', '1');
                }

                renderContestados(result.data.respondidos || []);
                actualizarVisibilidadMunicipios();
                bloquearModalidadSiCorresponde();
                refrescarHistorialHoy(result.data.historial_hoy || null);
                if (btnCargarEvidencia) {
                    btnCargarEvidencia.disabled = false;
                }
            })
            .catch(function (errorData) {
                const backendErrors = errorData && errorData.errors ? Object.values(errorData.errors).flat() : [];
                const message = backendErrors[0] || errorData.message || 'No fue posible guardar este municipio.';
                swal('Error', message, 'error');
            })
            .finally(function () {
                if (esMunicipioRegistrado(municipioId)) {
                    button.disabled = true;
                    button.textContent = 'Registrado';
                } else {
                    button.disabled = false;
                    button.textContent = originalText;
                }
            });
        });
    });

    // Sección de captura de acuerdos del día.
    if (btnGuardarAcuerdoHoy && guardarAcuerdoHoyUrl) {
        btnGuardarAcuerdoHoy.addEventListener('click', function () {
            const esSuspensionActiva = esModalidadSuspension();
            const parteParaGuardar = obtenerParteItems();
            let acuerdosParaGuardar = obtenerAcuerdoItems();

            if (esSuspensionActiva && !acuerdosParaGuardar.length) {
                acuerdosParaGuardar = ['Motivo no registrado'];
            }

            if (!esSuspensionActiva && !parteParaGuardar.length && !acuerdosParaGuardar.length) {
                acuerdosParaGuardar = ['No se ha anotado nada'];
            }

            const textoOriginal = btnGuardarAcuerdoHoy.textContent;
            btnGuardarAcuerdoHoy.disabled = true;
            btnGuardarAcuerdoHoy.textContent = 'Guardando...';

            fetch(guardarAcuerdoHoyUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    parte_observacion_items: parteParaGuardar,
                    acuerdo_observacion_items: acuerdosParaGuardar,
                    modalidad: modalidadGlobalSelect ? modalidadGlobalSelect.value : null,
                    delegado_asistio: delegadoAsistioGlobalSelect ? delegadoAsistioGlobalSelect.value : null,
                    fecha_asist: app.dataset.fechaHoyIso
                })
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { status: response.status, data: data };
                });
            })
            .then(function (result) {
                if (result.status >= 400 || !result.data.success) {
                    throw result.data;
                }

                if (acuerdoObservacionHoy && Array.isArray(result.data.acuerdo_observacion_items)) {
                    acuerdoObservacionHoy.value = formatearAcuerdoItemsComoTexto(result.data.acuerdo_observacion_items);
                }

                if (parteObservacionHoy && Array.isArray(result.data.parte_observacion_items)) {
                    parteObservacionHoy.value = formatearAcuerdoItemsComoTexto(result.data.parte_observacion_items);
                }

                swal('Éxito', result.data.message || 'Parte y acuerdos guardados correctamente.', 'success');
            })
            .catch(function (errorData) {
                const backendErrors = errorData && errorData.errors ? Object.values(errorData.errors).flat() : [];
                const message = backendErrors[0] || errorData.message || 'No fue posible guardar parte/acuerdos.';
                swal('Error', message, 'error');
            })
            .finally(function () {
                btnGuardarAcuerdoHoy.disabled = false;
                btnGuardarAcuerdoHoy.textContent = textoOriginal;
            });
        });
    }

    function bindTextareaLista(textarea) {
        if (!textarea) {
            return;
        }

        if (!textarea.value || !textarea.value.trim()) {
            textarea.value = '• ';
        }

        textarea.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' || event.shiftKey) {
                return;
            }

            event.preventDefault();
            if (!textarea.value.trim()) {
                insertarTextoEnCursor(textarea, '• ');
                return;
            }

            insertarTextoEnCursor(textarea, '\n• ');
        });
    }

    // La tecla 'ENTER' agrega nuevo elemento de lista en parte/acuerdos.
    bindTextareaLista(parteObservacionHoy);
    bindTextareaLista(acuerdoObservacionHoy);

    const fechaSelectorMesas = document.getElementById('fechaSelectorMesas');
    const fechaSelectorWrapper = document.getElementById('fechaSelectorWrapper');

    if (fechaSelectorWrapper && fechaSelectorMesas) {
        fechaSelectorWrapper.addEventListener('click', function () {
            // Siempre forzamos el picker al hacer clic en el contenedor (que es lo que el usuario ve)
            if (typeof fechaSelectorMesas.showPicker === 'function') {
                try {
                    fechaSelectorMesas.showPicker();
                } catch (err) {
                    fechaSelectorMesas.focus();
                    fechaSelectorMesas.click();
                }
            } else {
                fechaSelectorMesas.focus();
                fechaSelectorMesas.click();
            }
        });
    }

    if (fechaSelectorMesas) {
        fechaSelectorMesas.addEventListener('change', function () {
            const newDate = this.value;
            if (newDate) {
                const parts = newDate.split('-');
                if (parts.length === 3) {
                    const dt = new Date(parts[0], parts[1] - 1, parts[2]);
                    dt.setHours(0, 0, 0, 0);

                    const now = new Date();
                    now.setHours(0, 0, 0, 0);

                    if (dt > now) {
                        swal('Fecha no permitida', 'No se permite seleccionar fechas de días posteriores a hoy.', 'warning');
                        this.value = app.dataset.fechaHoyIso;
                        return;
                    }

                    const day = dt.getDay(); // 0 is Sunday, 6 is Saturday
                    if (day === 0 || day === 6) {
                        swal('Fecha no permitida', 'Solo se permite seleccionar fechas de lunes a viernes.', 'warning');
                        this.value = app.dataset.fechaHoyIso;
                        return;
                    }
                }

                // Actualizar display visual antes de recargar
                const display = document.getElementById('fechaDisplay');
                if (display && newDate.includes('-')) {
                    const p = newDate.split('-');
                    display.textContent = p[2] + '/' + p[1] + '/' + p[0];
                }

                const url = new URL(window.location.href);
                url.searchParams.set('fecha', newDate);
                window.location.href = url.toString();
            }
        });
    }

    actualizarEstadoCaptura();
    bloquearModalidadSiCorresponde();
    actualizarEstadoCaptura();
    aplicarReglaEspecialModalidad();
    actualizarVisibilidadDelegadoAsistio();
    actualizarVisibilidadPartePorModalidad();
    actualizarTextosObservacionPorModalidad();
    actualizarModoEspecialUI();
    renderizarEvidenciaActual();
    actualizarVistaDetalleContestados();
    actualizarVisibilidadMunicipios();
    bindHistorialActions();
    setupHistorialModalFocusManagement();

    // Drag y Drop para Excel Importar
    const dropzoneExcel = document.getElementById('dropzoneExcel');
    const inputExcelHidden = document.getElementById('inputExcelHidden');
    const excelFileNameDisplay = document.getElementById('excelFileNameDisplay');
    const formImportarExcel = document.getElementById('formImportarExcel');
    const btnConfirmarImportacion = document.getElementById('btnConfirmarImportacion');
    const importarExcelError = document.getElementById('importarExcelError');
    const spinnerImportacion = document.getElementById('spinnerImportacion');

    if (dropzoneExcel && inputExcelHidden) {
        dropzoneExcel.addEventListener('click', function () {
            inputExcelHidden.click();
        });

        ['dragover', 'dragenter'].forEach(function(eventName) {
            dropzoneExcel.addEventListener(eventName, function (e) {
                e.preventDefault();
                dropzoneExcel.style.backgroundColor = '#e9ecef';
                dropzoneExcel.style.borderColor = '#0d6efd';
            });
        });

        ['dragleave', 'dragend', 'drop'].forEach(function(eventName) {
            dropzoneExcel.addEventListener(eventName, function (e) {
                e.preventDefault();
                dropzoneExcel.style.backgroundColor = '#f8f9fa';
                dropzoneExcel.style.borderColor = '#dee2e6';
            });
        });

        dropzoneExcel.addEventListener('drop', function (e) {
            if (e.dataTransfer.files.length > 0) {
                inputExcelHidden.files = e.dataTransfer.files;
                mostrarNombreArchivoExcel();
            }
        });

        inputExcelHidden.addEventListener('change', function () {
            mostrarNombreArchivoExcel();
        });

        const excelFileStatus = document.getElementById('excelFileStatus');
        const btnRemoveExcel = document.getElementById('btnRemoveExcel');

        if (btnRemoveExcel) {
            btnRemoveExcel.addEventListener('click', function (e) {
                e.stopPropagation(); // Avoid triggering dropzone click
                limpiarSeleccionExcel();
            });
        }

        function limpiarSeleccionExcel() {
            inputExcelHidden.value = '';
            mostrarNombreArchivoExcel();
        }

        function mostrarNombreArchivoExcel() {
            if (inputExcelHidden.files.length > 0) {
                const fileName = inputExcelHidden.files[0].name;
                excelFileNameDisplay.textContent = 'Archivo: ' + fileName;
                excelFileStatus.classList.remove('d-none');
                excelFileStatus.classList.add('d-flex');
                dropzoneExcel.querySelector('p').classList.add('d-none');
            } else {
                excelFileStatus.classList.add('d-none');
                excelFileStatus.classList.remove('d-flex');
                dropzoneExcel.querySelector('p').classList.remove('d-none');
            }
        }
    }

    if (btnConfirmarImportacion && formImportarExcel) {
        btnConfirmarImportacion.addEventListener('click', function () {
            importarExcelError.classList.add('d-none');
            importarExcelError.textContent = '';

            if (!inputExcelHidden.files.length) {
                importarExcelError.textContent = 'Por favor selecciona un archivo Excel (.xls, .xlsx).';
                importarExcelError.classList.remove('d-none');
                return;
            }

            const fechaImportacionModal = document.getElementById('fechaImportacionModal');
            if (fechaImportacionModal && fechaImportacionModal.value) {
                const parts = fechaImportacionModal.value.split('-');
                if (parts.length === 3) {
                    const dt = new Date(parts[0], parts[1] - 1, parts[2]);
                    dt.setHours(0, 0, 0, 0);
                    const now = new Date();
                    now.setHours(0, 0, 0, 0);

                    if (dt > now) {
                        importarExcelError.textContent = 'No se permite seleccionar fechas de días posteriores a hoy.';
                        importarExcelError.classList.remove('d-none');
                        return;
                    }
                }
            }

            const formData = new FormData(formImportarExcel);
            const url = importarExcelUrl; 

            btnConfirmarImportacion.disabled = true;
            spinnerImportacion.classList.remove('d-none');

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { status: response.status, data: data };
                });
            })
            .then(function (result) {
                if (result.status >= 400 || !result.data.success) {
                    throw result.data;
                }
                
                swal('Éxito', result.data.message || 'Datos importados correctamente.', 'success').then(function () {
                    const reloadUrl = new URL(window.location.href);
                    reloadUrl.searchParams.set('fecha', result.data.fecha_asist || formData.get('fecha_importacion'));
                    window.location.href = reloadUrl.toString();
                });
            })
            .catch(function (errorData) {
                const backendErrors = errorData && errorData.data && errorData.data.errors ? Object.values(errorData.data.errors).flat() : [];
                const message = backendErrors[0] || (errorData.data && errorData.data.message) || errorData.message || 'Error al importar datos del archivo Excel.';
                
                importarExcelError.textContent = message;
                importarExcelError.classList.remove('d-none');
            })
            .finally(function () {
                btnConfirmarImportacion.disabled = false;
                spinnerImportacion.classList.add('d-none');
            });
        });
    }

    const btnVaciarMicrorregion = document.getElementById('btnVaciarMicrorregion');
    if (btnVaciarMicrorregion) {
        btnVaciarMicrorregion.addEventListener('click', function () {
            swal({
                title: "¿Estás seguro?",
                text: "¿Estás seguro de vaciar todos los registros de asistencia de los municipios que pertenecen a tu microrregión en esta fecha? Esta acción no se puede deshacer.",
                icon: "warning",
                buttons: ["Cancelar", "Aceptar"],
                dangerMode: true,
            })
            .then(function(willDelete) {
                if (!willDelete) return;

                const originalText = btnVaciarMicrorregion.innerHTML;
                btnVaciarMicrorregion.disabled = true;
                btnVaciarMicrorregion.textContent = 'Vaciando...';

            const url = vaciarMicrorregionUrl;

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    fecha_asist: app.dataset.fechaHoyIso || document.getElementById('fechaSelectorMesas').value,
                    microrregion_id: selectedMicrorregionId
                })
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { status: response.status, data: data };
                });
            })
            .then(function (result) {
                if (result.status >= 400 || !result.data.success) {
                    throw result.data;
                }

                swal('Éxito', result.data.message || 'Registros vaciados.', 'success').then(function() {
                    window.location.reload();
                });
            })
            .catch(function (errorData) {
                const backendErrors = errorData && errorData.data && errorData.data.errors ? Object.values(errorData.data.errors).flat() : [];
                const message = backendErrors[0] || (errorData.data && errorData.data.message) || errorData.message || 'Error al vaciar los registros.';
                swal('Error', message, 'error');
                btnVaciarMicrorregion.disabled = false;
                btnVaciarMicrorregion.innerHTML = originalText;
            });
        });
    });
}

});
