document.addEventListener('DOMContentLoaded', function () {
    const pageContainer = document.getElementById('supervisionEvidenciasPage');
    if (!pageContainer) {
        return;
    }

    // Modal de Detalle de Municipios (Global)
    window.verDetalleMunicipios = function(delegado, presentes, noPresentes) {
        const modalEl = document.getElementById('modalDetalleMunicipios');
        if (!modalEl) return;

        const modal = new bootstrap.Modal(modalEl);
        document.getElementById('detalleMunicipiosDelegado').innerText = delegado;

        const containerPres = document.getElementById('listaMunicipiosPresentes');
        const containerNo = document.getElementById('listaMunicipiosNoPresentes');

        containerPres.innerHTML = '';
        containerNo.innerHTML = '';

        if (presentes && presentes.length > 0) {
            presentes.forEach(m => {
                const span = document.createElement('span');
                span.className = 'badge bg-success-subtle text-success border border-success-subtle px-2 py-1';
                span.style.fontSize = '0.75rem';
                span.innerText = m;
                containerPres.appendChild(span);
            });
        } else {
            containerPres.innerHTML = '<span class="text-muted small">Ninguno</span>';
        }

        if (noPresentes && noPresentes.length > 0) {
            noPresentes.forEach(m => {
                const span = document.createElement('span');
                span.className = 'badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1';
                span.style.fontSize = '0.75rem';
                span.innerText = m;
                containerNo.appendChild(span);
            });
        } else {
            containerNo.innerHTML = '<span class="text-muted small">Ninguno</span>';
        }

        modal.show();
    };

    const bindSupervisionAjax = function () {
        const form = document.getElementById('supervisionFiltersForm');
        const clearLink = document.getElementById('btnLimpiarSupervision');
        const collapseNode = document.getElementById('analisisFiltrosCollapse');
        const fechaListaInput = document.getElementById('fecha_lista');
        const fechaAnalisisInput = document.getElementById('fecha_analisis');
        const analisisAsisteInput = document.getElementById('analisis_asiste');
        const analisisMicrorregionInput = document.getElementById('analisis_microrregion_id');

        const doRefresh = function (url, preserveOpen) {
            const estabaAbierto = preserveOpen && collapseNode && collapseNode.classList.contains('show');

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function (response) {
                return response.text();
            })
            .then(function (html) {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const nuevoContenedor = doc.getElementById('supervisionEvidenciasPage');
                if (!nuevoContenedor) {
                    window.location.href = url;
                    return;
                }

                pageContainer.innerHTML = nuevoContenedor.innerHTML;

                if (estabaAbierto) {
                    const nuevoCollapse = document.getElementById('analisisFiltrosCollapse');
                    if (nuevoCollapse) {
                        nuevoCollapse.classList.add('show');
                    }
                }

                bindSupervisionAjax();
                bindPresentacionPPT();
                bindToggleVistas();
                bindRegistrosBruto();
            })
            .catch(function () {
                window.location.href = url;
            });
        };

        const refreshByCurrentFilters = function (preserveOpen) {
            if (!form) {
                return;
            }

            const params = new URLSearchParams(new FormData(form));
            const action = form.getAttribute('action') || window.location.pathname;
            const url = action + '?' + params.toString();
            doRefresh(url, preserveOpen);
        };

        if (typeof flatpickr !== 'undefined') {
            const fechasDatosRaw = pageContainer.getAttribute('data-fechas-datos');
            let fechasConDatos = [];
            try {
                fechasConDatos = JSON.parse(fechasDatosRaw) || [];
            } catch(e) {
                console.error("Error parsing fechas-datos:", e);
            }

            const commonPickerConfig = {
                locale: "es",
                dateFormat: "Y-m-d",
                disableMobile: "true",
                enable: fechasConDatos.length > 0 ? fechasConDatos : undefined,
                onClose: function(selectedDates, dateStr, instance) {
                    if (dateStr) {
                        // Sincronizar ambos inputs si uno cambia
                        if (fechaListaInput) fechaListaInput.value = dateStr;
                        if (fechaAnalisisInput) fechaAnalisisInput.value = dateStr;

                        refreshByCurrentFilters(true);
                    }
                }
            };

            if (fechaListaInput) {
                flatpickr(fechaListaInput, commonPickerConfig);
            }
            if (fechaAnalisisInput) {
                flatpickr(fechaAnalisisInput, commonPickerConfig);
            }
        }

        [analisisAsisteInput, analisisMicrorregionInput].forEach(function (input) {
            if (!input) {
                return;
            }

            input.addEventListener('change', function () {
                refreshByCurrentFilters(true);
            });
        });

        if (clearLink) {
            clearLink.addEventListener('click', function (event) {
                event.preventDefault();
                doRefresh(clearLink.getAttribute('href'), false);
            });
        }
    };

    // Inicializar lógica AJAX
    bindSupervisionAjax();

    // Lógica para Presentación PPT (vista previa en modal, estilo importar Excel)
    const bindPresentacionPPT = function() {
        const btnAbrir = document.getElementById('btnAbrirRangoFechasPresentacion');
        const modalEl = document.getElementById('rangoFechasPresentacionModal');
        const btnVistaPrevia = document.getElementById('btnVistaPreviaPresentacion');
        const btnDescargar = document.getElementById('btnDescargarPresentacion');
        const inputRango = document.getElementById('fechaRangoPresentacion');
        const elLoading = document.getElementById('mpPptPreviewLoading');
        const elEmpty = document.getElementById('mpPptPreviewEmpty');
        const elContent = document.getElementById('mpPptPreviewContent');
        const chartImg = document.getElementById('mpPptChartImg');
        const elReplicaSemana = document.getElementById('mpPptReplicaSemana');
        const elReplicaTotal = document.getElementById('mpPptReplicaTotal');
        const elReplicaAsist = document.getElementById('mpPptReplicaAsist');
        const elReplicaInasist = document.getElementById('mpPptReplicaInasist');
        const elReplicaCumpl = document.getElementById('mpPptReplicaCumplimiento');
        const elReplicaSinReg = document.getElementById('mpPptReplicaSinReg');
        const elErr = document.getElementById('mpPptPreviewErr');
        const stepNote = document.getElementById('mpPptStepNote');

        if (!btnAbrir || !modalEl || !btnVistaPrevia || !btnDescargar || !inputRango) return;

        const modal = new bootstrap.Modal(modalEl);
        const urlVistaPrevia = pageContainer.getAttribute('data-url-ppt-vista-previa');
        let lastPreviewDownloadUrl = null;

        const disableWeekends = function(date) {
            const day = date.getDay();
            return day === 0 || day === 6;
        };

        const fp = flatpickr(inputRango, {
            mode: 'range',
            inline: true,
            locale: 'es',
            dateFormat: 'Y-m-d',
            disable: [disableWeekends],
            onReady: function(selectedDates, dateStr, instance) {
                instance.calendarContainer.classList.add('flatpickr-premium');
            }
        });

        const clearSlideReplica = function() {
            const blanks = [
                elReplicaSemana,
                elReplicaTotal,
                elReplicaAsist,
                elReplicaInasist,
                elReplicaCumpl,
                elReplicaSinReg,
            ];
            blanks.forEach(function(el) {
                if (el) {
                    el.textContent = '';
                }
            });
            if (chartImg) {
                chartImg.removeAttribute('src');
            }
        };

        const fillSlideReplica = function(r) {
            if (!r) {
                return;
            }
            if (elReplicaSemana) {
                elReplicaSemana.textContent = r.texto_semana_analizada || '';
            }
            if (elReplicaTotal) {
                elReplicaTotal.textContent = r.total_mesas != null ? String(r.total_mesas) : '';
            }
            if (elReplicaAsist) {
                elReplicaAsist.textContent = r.mesas_con_asistencia != null ? String(r.mesas_con_asistencia) : '';
            }
            if (elReplicaInasist) {
                elReplicaInasist.textContent = r.mesas_con_inasistencia != null ? String(r.mesas_con_inasistencia) : '';
            }
            if (elReplicaCumpl) {
                const p = r.porcentaje_cumplimiento != null ? Number(r.porcentaje_cumplimiento) : 0;
                elReplicaCumpl.textContent =
                    'Cumplimiento del ' + p.toFixed(2) + '% de avance de meta';
            }
            if (elReplicaSinReg) {
                const n = r.mesas_sin_registro_semanal != null ? parseInt(r.mesas_sin_registro_semanal, 10) : 0;
                elReplicaSinReg.textContent =
                    n === 1 ? '1 mesa sin registro en la semana' : n + ' mesas sin registro en la semana';
            }
        };

        const resetPptPreviewUi = function() {
            lastPreviewDownloadUrl = null;
            clearSlideReplica();
            if (elContent) {
                elContent.classList.add('d-none');
            }
            if (elEmpty) {
                elEmpty.classList.remove('d-none');
            }
            if (elLoading) {
                elLoading.classList.add('d-none');
            }
            if (elErr) {
                elErr.classList.add('d-none');
                elErr.textContent = '';
            }
            if (stepNote) {
                stepNote.innerHTML =
                    'Seleccione fechas y pulse <strong>Actualizar vista previa</strong> para ver una réplica de la diapositiva con los mismos datos que el .pptx. <strong>Descargar</strong> genera el archivo.';
            }
        };

        modalEl.addEventListener('hidden.bs.modal', resetPptPreviewUi);

        btnAbrir.addEventListener('click', function() {
            modal.show();
        });

        const aplicarRangoSeleccionado = function() {
            const selectedDates = fp.selectedDates;
            if (selectedDates.length < 2) {
                if (typeof swal === 'function') {
                    swal('Atención', 'Por favor selecciona un rango de fechas (Inicio y Fin)', 'warning');
                } else {
                    alert('Por favor selecciona un rango de fechas');
                }
                return null;
            }

            const startStr = fp.formatDate(selectedDates[0], 'Y-m-d');
            const endStr = fp.formatDate(selectedDates[1], 'Y-m-d');

            const startHidden = document.getElementById('inputStart');
            const endHidden = document.getElementById('inputEnd');

            if (!startHidden || !endHidden) {
                return null;
            }

            startHidden.value = startStr;
            endHidden.value = endStr;

            return { startStr: startStr, endStr: endStr };
        };

        btnDescargar.addEventListener('click', function() {
            if (lastPreviewDownloadUrl) {
                fetch(lastPreviewDownloadUrl, { credentials: 'same-origin' })
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error('No se pudo descargar');
                        }
                        return response.blob();
                    })
                    .then(function(blob) {
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'mesas_paz.pptx';
                        a.rel = 'noopener';
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        URL.revokeObjectURL(url);
                    })
                    .catch(function() {
                        if (typeof swal === 'function') {
                            swal('Error', 'No se pudo descargar el archivo. Intente de nuevo o use una nueva vista previa.', 'error');
                        } else {
                            alert('No se pudo descargar el archivo.');
                        }
                    });
                return;
            }

            if (!aplicarRangoSeleccionado()) {
                return;
            }
            modal.hide();
            const formDl = document.getElementById('formRangoFechasPresentation');
            if (formDl) {
                formDl.submit();
            }
        });

        btnVistaPrevia.addEventListener('click', function() {
            if (!urlVistaPrevia) {
                if (typeof swal === 'function') {
                    swal('Error', 'No está configurada la ruta de vista previa.', 'error');
                } else {
                    alert('No está configurada la ruta de vista previa.');
                }
                return;
            }

            if (!aplicarRangoSeleccionado()) {
                return;
            }

            const form = document.getElementById('formRangoFechasPresentation');
            if (!form) {
                return;
            }

            if (elErr) {
                elErr.classList.add('d-none');
                elErr.textContent = '';
            }
            if (elEmpty) {
                elEmpty.classList.add('d-none');
            }
            if (elContent) {
                elContent.classList.add('d-none');
            }
            if (elLoading) {
                elLoading.classList.remove('d-none');
            }

            const fd = new FormData(form);
            btnVistaPrevia.disabled = true;

            fetch(urlVistaPrevia, {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            })
                .then(function(response) {
                    return response.json().then(function(data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function(result) {
                    if (elLoading) {
                        elLoading.classList.add('d-none');
                    }

                    if (!result.ok) {
                        const d = result.data || {};
                        const msg =
                            d.message ||
                            d.error ||
                            (d.errors && typeof d.errors === 'object'
                                ? Object.values(d.errors)
                                      .flat()
                                      .filter(Boolean)
                                      .join(' ')
                                : '') ||
                            'No se pudo generar la vista previa.';
                        if (elErr) {
                            elErr.textContent = msg;
                            elErr.classList.remove('d-none');
                        }
                        if (elEmpty) {
                            elEmpty.classList.remove('d-none');
                        }
                        if (typeof swal === 'function') {
                            swal('Error', msg, 'error');
                        } else {
                            alert(msg);
                        }
                        return;
                    }

                    const data = result.data || {};
                    if (!data.download_url) {
                        if (typeof swal === 'function') {
                            swal('Error', 'Respuesta inválida del servidor.', 'error');
                        } else {
                            alert('Respuesta inválida del servidor.');
                        }
                        if (elEmpty) {
                            elEmpty.classList.remove('d-none');
                        }
                        return;
                    }

                    lastPreviewDownloadUrl = data.download_url;

                    if (stepNote) {
                        stepNote.innerHTML =
                            'Réplica de la diapositiva con los datos que llevará el PowerPoint. <strong>Descargar .pptx</strong> obtiene el archivo ya generado (sin volver a calcular).';
                    }

                    if (elContent) {
                        elContent.classList.remove('d-none');
                    }

                    fillSlideReplica(data.resumen);
                    if (chartImg && data.signed_chart_url) {
                        chartImg.src = data.signed_chart_url;
                    }
                })
                .catch(function() {
                    if (elLoading) {
                        elLoading.classList.add('d-none');
                    }
                    if (elEmpty) {
                        elEmpty.classList.remove('d-none');
                    }
                    if (typeof swal === 'function') {
                        swal('Error', 'No se pudo contactar al servidor.', 'error');
                    } else {
                        alert('No se pudo contactar al servidor.');
                    }
                })
                .finally(function() {
                    btnVistaPrevia.disabled = false;
                });
        });
    };

    bindPresentacionPPT();

    // ═══════════════════════════════════════════════════
    //  Toggle entre vista Evidencias ↔ Registros en bruto
    // ═══════════════════════════════════════════════════
    const bindToggleVistas = function () {
        const vistaEvidencias = document.getElementById('vistaEvidencias');
        const vistaRegistros = document.getElementById('vistaRegistrosBruto');
        const btnIr = document.getElementById('btnToggleBruto');
        const btnVolver = document.getElementById('btnVolverEvidencias');

        if (!vistaEvidencias || !vistaRegistros) return;

        if (btnIr) {
            btnIr.addEventListener('click', function () {
                vistaEvidencias.classList.add('d-none');
                vistaRegistros.classList.remove('d-none');
            });
        }

        if (btnVolver) {
            btnVolver.addEventListener('click', function () {
                vistaRegistros.classList.add('d-none');
                vistaEvidencias.classList.remove('d-none');
            });
        }
    };

    bindToggleVistas();

    // ═══════════════════════════════════════════════════
    //  Registros en bruto — buscar, paginar, vaciar
    // ═══════════════════════════════════════════════════
    const bindRegistrosBruto = function () {
        const urlBruto = pageContainer.getAttribute('data-url-bruto');
        const urlEliminar = pageContainer.getAttribute('data-url-eliminar-rango');
        const csrfToken = pageContainer.getAttribute('data-csrf');

        if (!urlBruto) return;

        const inputInicio = document.getElementById('brutoFechaInicio');
        const inputFin = document.getElementById('brutoFechaFin');
        const btnBuscar = document.getElementById('btnBuscarBruto');
        const btnEliminar = document.getElementById('btnEliminarRango');
        const loading = document.getElementById('brutoLoading');
        const tableWrap = document.getElementById('brutoTableWrap');
        const tableBody = document.getElementById('brutoTableBody');
        const emptyMsg = document.getElementById('brutoEmpty');
        const resumen = document.getElementById('brutoResumen');
        const totalBadge = document.getElementById('brutoTotalBadge');
        const pagInfo = document.getElementById('brutoPagInfo');
        const pagPrev = document.getElementById('brutoPagPrev');
        const pagNext = document.getElementById('brutoPagNext');

        if (!inputInicio || !inputFin || !btnBuscar) return;

        flatpickr(inputInicio, { dateFormat: 'Y-m-d', locale: 'es' });
        flatpickr(inputFin, { dateFormat: 'Y-m-d', locale: 'es' });

        let currentPage = 1;
        let lastPage = 1;

        const escapeHtml = (val) => String(val ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');

        const formatDate = (d) => {
            if (!d) return '—';
            return String(d).substring(0, 10);
        };

        const fetchPage = function (page) {
            const fi = inputInicio.value;
            const ff = inputFin.value;
            if (!fi || !ff) {
                if (typeof swal === 'function') swal('Atención', 'Selecciona ambas fechas.', 'warning');
                return;
            }

            loading.classList.remove('d-none');
            tableWrap.classList.add('d-none');
            emptyMsg.classList.add('d-none');
            resumen.classList.add('d-none');

            const params = new URLSearchParams({ fecha_inicio: fi, fecha_fin: ff, page: page });

            fetch(urlBruto + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => { if (!r.ok) throw new Error('Error ' + r.status); return r.json(); })
            .then(json => {
                loading.classList.add('d-none');
                currentPage = json.current_page;
                lastPage = json.last_page;

                if (json.total === 0) {
                    emptyMsg.classList.remove('d-none');
                    btnEliminar.classList.add('d-none');
                    return;
                }

                resumen.classList.remove('d-none');
                totalBadge.textContent = json.total + ' registro(s) encontrados';

                tableBody.innerHTML = '';
                json.data.forEach(r => {
                    const delegadoNombre = r.delegado
                        ? [r.delegado.nombre, r.delegado.ap_paterno, r.delegado.ap_materno].filter(Boolean).join(' ')
                        : (r.user ? r.user.name : '—');
                    const microLabel = r.microrregion
                        ? (r.microrregion.id + ' - ' + (r.microrregion.cabecera || r.microrregion.microrregion || ''))
                        : '—';
                    const municipioLabel = r.municipio ? r.municipio.municipio : '—';
                    const evidenciaCount = (() => {
                        try {
                            const ev = typeof r.evidencia === 'string' ? JSON.parse(r.evidencia) : r.evidencia;
                            return Array.isArray(ev) ? ev.filter(Boolean).length : 0;
                        } catch { return 0; }
                    })();

                    const tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + escapeHtml(r.asist_id) + '</td>' +
                        '<td>' + escapeHtml(formatDate(r.fecha_asist)) + '</td>' +
                        '<td>' + escapeHtml(delegadoNombre) + '</td>' +
                        '<td>' + escapeHtml(microLabel) + '</td>' +
                        '<td>' + escapeHtml(municipioLabel) + '</td>' +
                        '<td>' + escapeHtml(r.presidente) + '</td>' +
                        '<td>' + escapeHtml(r.asiste) + '</td>' +
                        '<td>' + escapeHtml(r.delegado_asistio) + '</td>' +
                        '<td>' + escapeHtml(r.modalidad || '—') + '</td>' +
                        '<td>' + (evidenciaCount > 0 ? '<span class="badge bg-success">' + evidenciaCount + '</span>' : '<span class="text-muted">0</span>') + '</td>' +
                        '<td>' + escapeHtml(r.created_at ? r.created_at.substring(0, 16).replace('T', ' ') : '—') + '</td>';
                    tableBody.appendChild(tr);
                });

                tableWrap.classList.remove('d-none');
                pagInfo.textContent = 'Página ' + currentPage + ' de ' + lastPage;
                pagPrev.disabled = currentPage <= 1;
                pagNext.disabled = currentPage >= lastPage;

                btnEliminar.classList.remove('d-none');
            })
            .catch(err => {
                loading.classList.add('d-none');
                if (typeof swal === 'function') swal('Error', err.message, 'error');
            });
        };

        btnBuscar.addEventListener('click', () => fetchPage(1));
        pagPrev.addEventListener('click', () => { if (currentPage > 1) fetchPage(currentPage - 1); });
        pagNext.addEventListener('click', () => { if (currentPage < lastPage) fetchPage(currentPage + 1); });

        if (btnEliminar && urlEliminar) {
            btnEliminar.addEventListener('click', function () {
                const fi = inputInicio.value;
                const ff = inputFin.value;
                if (!fi || !ff) return;

                if (typeof swal === 'function') {
                    swal({
                        title: '¿Estás seguro?',
                        text: 'Se eliminarán TODOS los registros del ' + fi + ' al ' + ff + '. Esta acción no se puede deshacer.',
                        icon: 'warning',
                        buttons: ['Cancelar', 'Sí, eliminar'],
                        dangerMode: true,
                    }).then(function (confirmar) {
                        if (!confirmar) return;
                        ejecutarEliminacion(fi, ff);
                    });
                } else if (confirm('¿Eliminar todos los registros del ' + fi + ' al ' + ff + '?')) {
                    ejecutarEliminacion(fi, ff);
                }
            });

            const ejecutarEliminacion = function (fi, ff) {
                btnEliminar.disabled = true;
                btnEliminar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Eliminando...';

                fetch(urlEliminar, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ fecha_inicio: fi, fecha_fin: ff })
                })
                .then(r => { if (!r.ok) throw new Error('Error ' + r.status); return r.json(); })
                .then(json => {
                    btnEliminar.disabled = false;
                    btnEliminar.innerHTML = '<i class="fa fa-trash me-1"></i> Vaciar rango';
                    if (json.success) {
                        if (typeof swal === 'function') {
                            swal('Eliminado', json.message, 'success');
                        }
                        fetchPage(1);
                    } else {
                        throw new Error(json.message || 'Error desconocido');
                    }
                })
                .catch(err => {
                    btnEliminar.disabled = false;
                    btnEliminar.innerHTML = '<i class="fa fa-trash me-1"></i> Vaciar rango';
                    if (typeof swal === 'function') swal('Error', err.message, 'error');
                });
            };
        }
    };

    bindRegistrosBruto();
});
function mostrarVistaPreviaEvidencia(url) {
    const modalEl = document.getElementById('evidenciaPreviewModal');
    const modalImg = document.getElementById('evidenciaPreviewModalImg');

    if (modalEl && modalImg) {
        modalImg.src = url;
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}
