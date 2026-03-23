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

    // Lógica para Presentación PPT
    const bindPresentacionPPT = function() {
        const btnAbrir = document.getElementById('btnAbrirRangoFechasPresentacion');
        const modalEl = document.getElementById('rangoFechasPresentacionModal');
        const btnConfirmar = document.getElementById('btnConfirmarRangoFechasPresentacion');
        const inputRango = document.getElementById('fechaRangoPresentacion');

        if (!btnAbrir || !modalEl || !btnConfirmar || !inputRango) return;

        const modal = new bootstrap.Modal(modalEl);
        const urlBase = pageContainer.getAttribute('data-url-ppt');

        const fp = flatpickr(inputRango, {
            mode: "range",
            inline: true,
            locale: "es",
            dateFormat: "Y-m-d",
            onReady: function(selectedDates, dateStr, instance) {
                const container = instance.calendarContainer;
                const header = document.createElement('div');
                header.className = 'presentation-calendar-header border-bottom mb-2 text-center py-2';
                header.innerHTML = '<span class="presentation-calendar-label fw-bold">Periodo del reporte</span>';
                container.prepend(header);
            }
        });

        btnAbrir.addEventListener('click', function() {
            modal.show();
        });

        btnConfirmar.addEventListener('click', function() {
            const selectedDates = fp.selectedDates;
            if (selectedDates.length < 2) {
                if (typeof swal === 'function') {
                    swal("Atención", "Por favor selecciona un rango de fechas (Inicio y Fin)", "warning");
                } else {
                    alert("Por favor selecciona un rango de fechas");
                }
                return;
            }

            const start = selectedDates[0].toISOString().split('T')[0];
            const end = selectedDates[1].toISOString().split('T')[0];
            const url = `${urlBase}?start=${start}&end=${end}`;

            modal.hide();
            window.location.href = url;
        });
    };

    bindPresentacionPPT();
});function mostrarVistaPreviaEvidencia(url) {
    const modalEl = document.getElementById('evidenciaPreviewModal');
    const modalImg = document.getElementById('evidenciaPreviewModalImg');
    
    if (modalEl && modalImg) {
        modalImg.src = url;
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}
