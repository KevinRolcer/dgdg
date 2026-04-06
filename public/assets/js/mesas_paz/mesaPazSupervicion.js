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
        const btnDescargarPdf = document.getElementById('btnDescargarPresentacionPdf');
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
        const modeTabs = document.getElementById('mpPptPreviewModeTabs');
        const tabBtnResumen = document.getElementById('mpPptTabBtnResumen');
        const tabBtnCompleto = document.getElementById('mpPptTabBtnCompleto');
        const panelResumen = document.getElementById('mpPptPanelResumen');
        const panelCompleto = document.getElementById('mpPptPanelCompleto');
        const officeIframe = document.getElementById('mpPptOfficeIframe');
        const htmlDeck = document.getElementById('mpPptHtmlDeck');
        const officeFallback = document.getElementById('mpPptOfficeFallback');
        const linkVistaPagina = document.getElementById('mpPptLinkVistaPagina');
        const linkSignedFile = document.getElementById('mpPptLinkSignedFile');
        const calendarMonthLabel = document.getElementById('mpPptCalendarMonthLabel');
        const pptLogoUrl = pageContainer.getAttribute('data-ppt-logo-url') || '';
        const pptSegobLogoUrl = pageContainer.getAttribute('data-ppt-segob-logo-url') || '';
        const pptCoverBgUrl = pageContainer.getAttribute('data-ppt-cover-bg-url') || '';

        if (!btnAbrir || !modalEl || !btnVistaPrevia || !btnDescargar || !inputRango) return;

        const modal = new bootstrap.Modal(modalEl);
        const urlVistaPrevia = pageContainer.getAttribute('data-url-ppt-vista-previa');
        let lastPreviewDownloadUrl = null;
        let lastPreviewDownloadPdfUrl = null;

        const disableWeekends = function(date) {
            const day = date.getDay();
            return day === 0 || day === 6;
        };

        const monthNames = [
            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
        ];
        const actualizarEtiquetaMesCalendario = function(instance) {
            if (!calendarMonthLabel || !instance) {
                return;
            }
            const m = monthNames[instance.currentMonth] || '';
            const y = instance.currentYear || '';
            calendarMonthLabel.textContent = (m + ' ' + y).trim();
        };

        const fp = flatpickr(inputRango, {
            mode: 'range',
            inline: true,
            locale: 'es',
            dateFormat: 'Y-m-d',
            disable: [disableWeekends],
            onReady: function(selectedDates, dateStr, instance) {
                instance.calendarContainer.classList.add('flatpickr-premium');
                actualizarEtiquetaMesCalendario(instance);
            },
            onMonthChange: function(selectedDates, dateStr, instance) {
                actualizarEtiquetaMesCalendario(instance);
            },
            onYearChange: function(selectedDates, dateStr, instance) {
                actualizarEtiquetaMesCalendario(instance);
            },
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

        const formatearFechaCorta = function(value) {
            if (!value) return '';
            const d = new Date(value + 'T00:00:00');
            if (isNaN(d.getTime())) return String(value);
            return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
        };

        const formatearFechaLarga = function(date) {
            return date.toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' });
        };

        const formatearSemanaTitulo = function(desde, hasta) {
            if (!desde || !hasta) {
                return '';
            }
            const d1 = new Date(desde + 'T00:00:00');
            const d2 = new Date(hasta + 'T00:00:00');
            if (isNaN(d1.getTime()) || isNaN(d2.getTime())) {
                return '';
            }
            const dia1 = d1.getDate();
            const dia2 = d2.getDate();
            const mes = d2.toLocaleDateString('es-MX', { month: 'long' });
            const anio = d2.getFullYear();
            return 'Semana del ' + dia1 + ' al ' + dia2 + ' de ' + mes + ' de ' + anio;
        };

        const clampPct = function(value) {
            const n = Number(value || 0);
            if (isNaN(n)) return 0;
            return Math.max(0, Math.min(100, n));
        };

        const donutChartHtml = function(asistencias, inasistencias, meta) {
            const asi = Number(asistencias || 0);
            const ina = Number(inasistencias || 0);
            const m = Number(meta || 0);
            const sinRegistro = Math.max(0, m - (asi + ina));
            const total = Math.max(1, asi + ina + sinRegistro);

            const pAsi = clampPct((asi / total) * 100);
            const pIna = clampPct((ina / total) * 100);
            const pSin = clampPct(100 - pAsi - pIna);
            const stop1 = pAsi.toFixed(2);
            const stop2 = (pAsi + pIna).toFixed(2);

            return ''
                + '<div class="mp-ppt-donut-wrap">'
                + '  <div class="mp-ppt-donut" style="--a:' + stop1 + '%; --b:' + stop2 + '%; --c:' + pSin.toFixed(2) + '%;">'
                + '    <div class="mp-ppt-donut-center">'
                + '      <span class="mp-ppt-donut-total">' + String(asi + ina) + '</span>'
                + '      <small>registros</small>'
                + '    </div>'
                + '  </div>'
                + '  <ul class="mp-ppt-donut-legend">'
                + '    <li><span class="dot dot-asi"></span>Asistencias: ' + String(asi) + '</li>'
                + '    <li><span class="dot dot-ina"></span>Inasistencias: ' + String(ina) + '</li>'
                + '    <li><span class="dot dot-sin"></span>Sin registro: ' + String(sinRegistro) + '</li>'
                + '  </ul>'
                + '</div>';
        };

        const renderHtmlDeck = function(resumen, chartUrl, startDate, endDate) {
            if (!htmlDeck) return;

            const rangeLabel = formatearFechaCorta(startDate) + ' al ' + formatearFechaCorta(endDate);
            const todayLabel = formatearFechaLarga(new Date());
            const weekLabel = resumen && resumen.semana_contada_desde && resumen.semana_contada_hasta
                ? formatearSemanaTitulo(resumen.semana_contada_desde, resumen.semana_contada_hasta)
                : (resumen && resumen.texto_semana_analizada ? resumen.texto_semana_analizada : '');
            const resumenSemanaMarcada = (window.__mpPptResumenSemanaMarcada && typeof window.__mpPptResumenSemanaMarcada === 'object')
                ? window.__mpPptResumenSemanaMarcada
                : ((window.__mpPptResumenSemanaAnterior && typeof window.__mpPptResumenSemanaAnterior === 'object')
                    ? window.__mpPptResumenSemanaAnterior
                    : null);
            const weekLabelMarcada = resumenSemanaMarcada && resumenSemanaMarcada.semana_contada_desde && resumenSemanaMarcada.semana_contada_hasta
                ? formatearSemanaTitulo(resumenSemanaMarcada.semana_contada_desde, resumenSemanaMarcada.semana_contada_hasta)
                : '';
            const porDia = resumen && resumen.por_dia && typeof resumen.por_dia === 'object' ? resumen.por_dia : {};
            const rows = Object.keys(porDia).sort();
            const microregions = [
                'MR1 XICOTEPEC',
                'MR2 HUAUCHINANGO',
                'MR3 CHIGNAHUAPAN',
                'MR4 ZACAPOAXTLA',
                'MR5 LIBRES',
                'MR6 TEZIUTLAN',
                'MR7 SAN MARTIN TEXMELUCAN',
                'MR8 HUEJOTZINGO',
                'MR9 PUEBLA',
                'MR10 PUEBLA',
                'MR11 PUEBLA',
                'MR12 AMOZOC',
                'MR13 TEPEACA',
                'MR14 CALCHICOMULA DE SESMA',
                'MR15 TECAMACHALCO',
                'MR16 PUEBLA',
                'MR17 PUEBLA',
                'MR18 CHOLULA',
                'MR19 PUEBLA',
                'MR20 PUEBLA',
                'MR21 ATLIXCO',
                'MR22 IZUCAR DE MATAMOROS',
                'MR23 ACATLAN DE OSORIO',
                'MR24 TEHUACAN',
                'MR25 TEHUACAN',
                'MR26 AJALPAN',
                'MR27 CUAUTEMPAN',
                'MR28 CHIAUTLA',
                'MR29 TEPEXI DE RODRIGUEZ',
                'MR30 ACATZINGO',
                'MR31 TLATLAUQUITEPEC'
            ];

            let tableRows = '';
            rows.forEach(function(key) {
                const row = porDia[key] || {};
                tableRows += '<tr>'
                    + '<td>' + formatearFechaCorta(key) + '</td>'
                    + '<td>' + (row.mesas != null ? String(row.mesas) : '0') + '</td>'
                    + '<td>' + (row.asistencias != null ? String(row.asistencias) : '0') + '</td>'
                    + '<td>' + (row.inasistencias != null ? String(row.inasistencias) : '0') + '</td>'
                    + '</tr>';
            });
            if (!tableRows) {
                tableRows = '<tr><td colspan="4">Sin registros por día en el rango seleccionado</td></tr>';
            }

            const logoMain = pptLogoUrl ? '<img src="' + pptLogoUrl + '" alt="Gobierno de Puebla">' : '';
            const logoSegob = pptSegobLogoUrl ? '<img src="' + pptSegobLogoUrl + '" alt="SEGOB Puebla">' : '';
            const coverStyle = pptCoverBgUrl ? ' style="background-image: linear-gradient(rgba(109,21,48,.88), rgba(109,21,48,.92)), url(\'' + pptCoverBgUrl + '\');"' : '';

            const html = ''
                + '<article class="mp-ppt-html-slide">'
                + '  <header class="mp-ppt-html-slide-head"><span>Diapositiva 1</span><span>Portada</span></header>'
                + '  <div class="mp-ppt-html-slide-cover"' + coverStyle + '>'
                + '    <div class="mp-ppt-html-logos">' + logoMain + logoSegob + '</div>'
                + '    <p class="mb-1">' + rangeLabel + '</p>'
                + '    <h2 class="mp-ppt-html-kicker">MESAS DE PAZ Y SEGURIDAD</h2>'
                + '    <p class="mp-ppt-html-sub">Dirección General de Delegaciones de Gobierno</p>'
                + '    <p class="mp-ppt-html-date-bottom">' + todayLabel + '</p>'
                + '  </div>'
                + '</article>'
                + '<article class="mp-ppt-html-slide">'
                + '  <header class="mp-ppt-html-slide-head"><span>Diapositiva 2</span><span>Orden del dia</span></header>'
                + '  <div class="mp-ppt-html-slide-body mp-ppt-html-agenda">'
                + '    <h3>ORDEN DEL DIA</h3>'
                + '    <p class="mp-ppt-html-subhead">' + todayLabel + '</p>'
                + '    <ol>'
                + '      <li>Reporte General.</li>'
                + '      <li>Reporte Diario.</li>'
                + '      <li>Reporte por Microrregion.</li>'
                + '      <li>Reportes Diarios por Microrregion.</li>'
                + '    </ol>'
                + '  </div>'
                + '</article>'
                + '<article class="mp-ppt-html-slide">'
                + '  <header class="mp-ppt-html-slide-head"><span>Diapositiva 3</span><span>Reporte general</span></header>'
                + '  <div class="mp-ppt-html-slide-body">'
                + '    <p class="mp-ppt-html-slide-title">1. REPORTE GENERAL</p>'
                + '    <div class="mp-ppt-html-metrics">'
                + '      <div class="mp-ppt-html-card"><div class="mp-ppt-html-card-num">' + (resumen.total_mesas != null ? String(resumen.total_mesas) : '0') + '</div><div class="mp-ppt-html-card-label">Mesas de seguridad</div></div>'
                + '      <div class="mp-ppt-html-card"><div class="mp-ppt-html-card-num">' + (resumen.mesas_con_asistencia != null ? String(resumen.mesas_con_asistencia) : '0') + '</div><div class="mp-ppt-html-card-label">Asistencias</div></div>'
                + '      <div class="mp-ppt-html-card"><div class="mp-ppt-html-card-num">' + (resumen.mesas_con_inasistencia != null ? String(resumen.mesas_con_inasistencia) : '0') + '</div><div class="mp-ppt-html-card-label">Inasistencias</div></div>'
                + '    </div>'
                + '    <div class="mp-ppt-html-kpis">'
                + '      <span><strong>Cumplimiento:</strong> ' + (Number(resumen.porcentaje_cumplimiento || 0)).toFixed(2) + '%</span>'
                + '      <span><strong>Meta:</strong> ' + (resumen.meta_mesas != null ? String(resumen.meta_mesas) : '0') + '</span>'
                + '      <span><strong>Mesas sin registro:</strong> ' + (resumen.mesas_sin_registro_semanal != null ? String(resumen.mesas_sin_registro_semanal) : '0') + '</span>'
                + '      <span><strong>Semana:</strong> ' + weekLabel + '</span>'
                + '    </div>'
                + '    <div class="mp-ppt-html-chart">'
                + donutChartHtml(
                    resumen.mesas_con_asistencia,
                    resumen.mesas_con_inasistencia,
                    resumen.meta_mesas
                )
                + '    </div>'
                + '  </div>'
                + '</article>'
                + '<article class="mp-ppt-html-slide">'
                + '  <header class="mp-ppt-html-slide-head"><span>Diapositiva 4</span><span>Reporte general</span></header>'
                + '  <div class="mp-ppt-html-slide-body">'
                + '    <p class="mp-ppt-html-slide-title">1. REPORTE GENERAL</p>'
                + '    <div class="mp-ppt-html-metrics">'
                + '      <div class="mp-ppt-html-card"><div class="mp-ppt-html-card-num">' + (resumenSemanaMarcada && resumenSemanaMarcada.total_mesas != null ? String(resumenSemanaMarcada.total_mesas) : '0') + '</div><div class="mp-ppt-html-card-label">Mesas de seguridad</div></div>'
                + '      <div class="mp-ppt-html-card"><div class="mp-ppt-html-card-num">' + (resumenSemanaMarcada && resumenSemanaMarcada.mesas_con_asistencia != null ? String(resumenSemanaMarcada.mesas_con_asistencia) : '0') + '</div><div class="mp-ppt-html-card-label">Asistencias</div></div>'
                + '      <div class="mp-ppt-html-card"><div class="mp-ppt-html-card-num">' + (resumenSemanaMarcada && resumenSemanaMarcada.mesas_con_inasistencia != null ? String(resumenSemanaMarcada.mesas_con_inasistencia) : '0') + '</div><div class="mp-ppt-html-card-label">Inasistencias</div></div>'
                + '    </div>'
                + '    <div class="mp-ppt-html-kpis">'
                + '      <span><strong>Semana:</strong> ' + (weekLabelMarcada || weekLabel) + '</span>'
                + '      <span><strong>Cumplimiento:</strong> ' + (resumenSemanaMarcada && resumenSemanaMarcada.porcentaje_cumplimiento != null ? Number(resumenSemanaMarcada.porcentaje_cumplimiento).toFixed(2) : '0.00') + '%</span>'
                + '    </div>'
                + '    <div class="mp-ppt-html-chart">'
                + donutChartHtml(
                    resumenSemanaMarcada ? resumenSemanaMarcada.mesas_con_asistencia : 0,
                    resumenSemanaMarcada ? resumenSemanaMarcada.mesas_con_inasistencia : 0,
                    resumenSemanaMarcada ? resumenSemanaMarcada.meta_mesas : 0
                )
                + '    </div>'
                + '  </div>'
                + '</article>'
                + '<article class="mp-ppt-html-slide">'
                + '  <header class="mp-ppt-html-slide-head"><span>Diapositiva 5</span><span>Reporte diario semanal</span></header>'
                + '  <div class="mp-ppt-html-slide-body">'
                + '    <p class="mp-ppt-html-slide-title">1. REPORTE DIARIO SEMANAL</p>'
                + '    <div class="mp-ppt-html-two-col">'
                + '      <section><h4>' + weekLabel + '</h4><div class="mp-ppt-html-image-placeholder">Contorno de tabla semanal</div></section>'
                + '      <section><h4>' + rangeLabel + '</h4><div class="mp-ppt-html-image-placeholder">Contorno de tabla semanal</div></section>'
                + '    </div>'
                + '  </div>'
                + '    <div class="mt-3">'
                + '    <table class="mp-ppt-html-table">'
                + '      <thead><tr><th>Fecha</th><th>Mesas</th><th>Asistencias</th><th>Inasistencias</th></tr></thead>'
                + '      <tbody>' + tableRows + '</tbody>'
                + '    </table>'
                + '    </div>'
                + '  </div>'
                + '</article>';

            let htmlFull = html;
            microregions.forEach(function(name, index) {
                const slideNumber = index + 6;
                htmlFull += ''
                    + '<article class="mp-ppt-html-slide">'
                    + '  <header class="mp-ppt-html-slide-head"><span>Diapositiva ' + slideNumber + '</span><span>Reporte por microrregion</span></header>'
                    + '  <div class="mp-ppt-html-slide-body">'
                    + '    <p class="mp-ppt-html-slide-title">3. REPORTE POR MICRORREGION</p>'
                    + '    <p class="mp-ppt-html-mr-title">' + name + '</p>'
                    + '    <div class="mp-ppt-html-kpis"><span><strong>Semana:</strong> ' + weekLabel + '</span></div>'
                    + '    <div class="mp-ppt-html-grid-2">'
                    + '      <div class="mp-ppt-html-image-placeholder">Contorno de mapa / indicador</div>'
                    + '      <div class="mp-ppt-html-image-placeholder">Contorno de grafica / tabla</div>'
                    + '    </div>'
                    + '  </div>'
                    + '</article>';
            });

            htmlDeck.innerHTML = htmlFull;
            htmlDeck.classList.remove('d-none');
        };

        const setPptPreviewTab = function(mode) {
            const isResumen = mode === 'resumen';
            if (tabBtnResumen && tabBtnCompleto) {
                tabBtnResumen.classList.toggle('active', isResumen);
                tabBtnCompleto.classList.toggle('active', !isResumen);
                tabBtnResumen.setAttribute('aria-selected', isResumen ? 'true' : 'false');
                tabBtnCompleto.setAttribute('aria-selected', !isResumen ? 'true' : 'false');
            }
            if (panelResumen) {
                panelResumen.classList.toggle('d-none', !isResumen);
            }
            if (panelCompleto) {
                panelCompleto.classList.toggle('d-none', isResumen);
            }
        };

        const resetPptPreviewUi = function() {
            lastPreviewDownloadUrl = null;
            lastPreviewDownloadPdfUrl = null;
            clearSlideReplica();
            if (officeIframe) {
                officeIframe.src = 'about:blank';
            }
            if (htmlDeck) {
                htmlDeck.innerHTML = '';
                htmlDeck.classList.add('d-none');
            }
            if (officeFallback) {
                officeFallback.classList.add('d-none');
            }
            if (linkSignedFile) {
                linkSignedFile.classList.add('d-none');
                linkSignedFile.setAttribute('href', '#');
            }
            if (linkVistaPagina) {
                linkVistaPagina.setAttribute('href', '#');
            }
            if (btnDescargarPdf) {
                btnDescargarPdf.classList.add('d-none');
            }
            if (modeTabs) {
                modeTabs.classList.add('d-none');
            }
            setPptPreviewTab('completo');
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
                    'Actualice la vista previa para cargar la versión final en PDF y PowerPoint.';
            }
        };

        modalEl.addEventListener('hidden.bs.modal', resetPptPreviewUi);

        if (tabBtnResumen) {
            tabBtnResumen.addEventListener('click', function() {
                setPptPreviewTab('resumen');
            });
        }
        if (tabBtnCompleto) {
            tabBtnCompleto.addEventListener('click', function() {
                setPptPreviewTab('completo');
            });
        }

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

        if (btnDescargarPdf) {
            btnDescargarPdf.addEventListener('click', function() {
                if (!lastPreviewDownloadPdfUrl) {
                    if (typeof swal === 'function') {
                        swal('Atención', 'Primero actualice la vista previa para generar el PDF.', 'warning');
                    } else {
                        alert('Primero actualice la vista previa para generar el PDF.');
                    }
                    return;
                }

                fetch(lastPreviewDownloadPdfUrl, { credentials: 'same-origin' })
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
                        a.download = 'mesas_paz.pdf';
                        a.rel = 'noopener';
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        URL.revokeObjectURL(url);
                    })
                    .catch(function() {
                        if (typeof swal === 'function') {
                            swal('Error', 'No se pudo descargar el PDF. Intente de nuevo.', 'error');
                        } else {
                            alert('No se pudo descargar el PDF.');
                        }
                    });
            });
        }

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
            fd.append('_preview_nonce', String(Date.now()));
            btnVistaPrevia.disabled = true;

            fetch(urlVistaPrevia, {
                method: 'POST',
                cache: 'no-store',
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
                    lastPreviewDownloadPdfUrl = data.download_pdf_url || null;
                    window.__mpPptResumenSemanaMarcada = data.resumen_semana_marcada || null;
                    window.__mpPptResumenSemanaAnterior = data.resumen_semana_anterior || null;

                    if (stepNote) {
                        stepNote.innerHTML = 'Vista previa actualizada. Puede descargar la presentación en PPTX y PDF cuando esté disponible.';
                    }

                    if (elContent) {
                        elContent.classList.remove('d-none');
                    }

                    if (modeTabs) {
                        modeTabs.classList.remove('d-none');
                    }
                    setPptPreviewTab('completo');

                    const vistaPagina = data.vista_previa_url || '';
                    if (btnDescargarPdf && data.download_pdf_url) {
                        btnDescargarPdf.classList.remove('d-none');
                    }
                    if (linkVistaPagina && vistaPagina) {
                        linkVistaPagina.setAttribute('href', vistaPagina);
                    }

                    if (officeIframe && data.signed_pdf_url) {
                        officeIframe.classList.remove('d-none');
                        officeIframe.src = data.signed_pdf_url;
                        if (htmlDeck) {
                            htmlDeck.classList.add('d-none');
                            htmlDeck.innerHTML = '';
                        }
                        if (officeFallback) {
                            officeFallback.classList.add('d-none');
                        }
                    } else if (officeIframe) {
                        officeIframe.classList.add('d-none');
                        officeIframe.src = 'about:blank';
                        renderHtmlDeck(
                            data.resumen || {},
                            data.signed_chart_url || '',
                            document.getElementById('inputStart') ? document.getElementById('inputStart').value : '',
                            document.getElementById('inputEnd') ? document.getElementById('inputEnd').value : ''
                        );
                        if (officeFallback) {
                            officeFallback.classList.add('d-none');
                        }
                        if (linkSignedFile && data.signed_file_url) {
                            linkSignedFile.setAttribute('href', data.signed_file_url);
                            linkSignedFile.textContent = 'Abrir .pptx';
                            linkSignedFile.classList.remove('d-none');
                        }
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
