document.addEventListener('DOMContentLoaded', function () {
    const pageContainer = document.getElementById('supervisionEvidenciasPage');
    if (!pageContainer) {
        return;
    }

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

        if (fechaListaInput) {
            fechaListaInput.addEventListener('change', function () {
                refreshByCurrentFilters(true);
            });
        }

        [fechaAnalisisInput, analisisAsisteInput, analisisMicrorregionInput].forEach(function (input) {
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

        const bindEvidencePreview = function () {
            if (pageContainer.dataset.previewBound === '1') {
                return;
            }

            pageContainer.dataset.previewBound = '1';
            pageContainer.addEventListener('click', function (event) {
                const thumb = event.target.closest('.evidencia-thumb-mini');
                if (!thumb) {
                    return;
                }

                const modalEl = document.getElementById('evidenciaPreviewModal');
                const modalImg = document.getElementById('evidenciaPreviewModalImg');
                if (!modalEl || !modalImg) {
                    return;
                }

                const url = thumb.getAttribute('data-evidencia-url') || thumb.getAttribute('src') || '';
                if (!url) {
                    return;
                }

                modalImg.src = url;

                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modalInstance.show();
                    return;
                }

                if (typeof swal === 'function') {
                    swal({
                        title: 'Vista previa de evidencia',
                        text: '',
                        icon: url,
                        button: 'Cerrar'
                    });
                }
            });
        };

        const asistentesBody = document.getElementById('asistentesMicrorregionTableBody');
        const asistentesSearch = document.getElementById('asistentesMicroSearch');
        const asistentesLimit = document.getElementById('asistentesMicroLimit');
        const asistentesSort = document.getElementById('asistentesMicroSort');
        const asistentesPrev = document.getElementById('asistentesMicroPrev');
        const asistentesNext = document.getElementById('asistentesMicroNext');
        const asistentesPageInfo = document.getElementById('asistentesMicroPageInfo');

        let asistentesCurrentPage = 1;

        const applyAsistentesView = function (resetPage) {
            if (!asistentesBody) {
                return;
            }

            if (resetPage) {
                asistentesCurrentPage = 1;
            }

            const term = (asistentesSearch && asistentesSearch.value ? asistentesSearch.value : '').toLowerCase().trim();
            const limitValue = asistentesLimit ? asistentesLimit.value : '5';
            const maxRows = limitValue === 'all' ? Number.MAX_SAFE_INTEGER : Math.max(1, parseInt(limitValue, 10) || 5);
            const sortDirection = asistentesSort ? asistentesSort.value : 'asc';

            const rows = Array.from(asistentesBody.querySelectorAll('tr[data-micro-label]'));
            rows.sort(function (a, b) {
                const aId = parseInt(a.getAttribute('data-micro-id') || '0', 10);
                const bId = parseInt(b.getAttribute('data-micro-id') || '0', 10);

                if (aId === bId) {
                    const aLabel = (a.getAttribute('data-micro-label') || '').toLowerCase();
                    const bLabel = (b.getAttribute('data-micro-label') || '').toLowerCase();
                    return sortDirection === 'desc' ? bLabel.localeCompare(aLabel) : aLabel.localeCompare(bLabel);
                }

                return sortDirection === 'desc' ? bId - aId : aId - bId;
            });

            rows.forEach(function (row) {
                asistentesBody.appendChild(row);
            });

            const filteredRows = rows.filter(function (row) {
                const label = (row.getAttribute('data-micro-label') || '').toLowerCase();
                return term === '' || label.indexOf(term) !== -1;
            });

            const totalRows = filteredRows.length;
            const totalPages = maxRows === Number.MAX_SAFE_INTEGER ? 1 : Math.max(1, Math.ceil(totalRows / maxRows));
            if (asistentesCurrentPage > totalPages) {
                asistentesCurrentPage = totalPages;
            }

            const startIndex = maxRows === Number.MAX_SAFE_INTEGER ? 0 : (asistentesCurrentPage - 1) * maxRows;
            const endIndex = maxRows === Number.MAX_SAFE_INTEGER ? totalRows : startIndex + maxRows;

            rows.forEach(function (row) {
                row.style.display = 'none';
            });

            filteredRows.forEach(function (row, index) {
                if (index < startIndex || index >= endIndex) {
                    row.style.display = 'none';
                    return;
                }

                row.style.display = '';
            });

            if (asistentesPageInfo) {
                if (totalRows === 0) {
                    asistentesPageInfo.textContent = 'Sin resultados';
                } else if (maxRows === Number.MAX_SAFE_INTEGER) {
                    asistentesPageInfo.textContent = 'Mostrando todas (' + totalRows + ')';
                } else {
                    const from = startIndex + 1;
                    const to = Math.min(endIndex, totalRows);
                    asistentesPageInfo.textContent = 'Mostrando ' + from + '-' + to + ' de ' + totalRows + ' (página ' + asistentesCurrentPage + '/' + totalPages + ')';
                }
            }

            if (asistentesPrev) {
                asistentesPrev.disabled = maxRows === Number.MAX_SAFE_INTEGER || asistentesCurrentPage <= 1 || totalRows === 0;
            }

            if (asistentesNext) {
                asistentesNext.disabled = maxRows === Number.MAX_SAFE_INTEGER || asistentesCurrentPage >= totalPages || totalRows === 0;
            }
        };

        if (asistentesSearch) {
            asistentesSearch.addEventListener('input', function () {
                applyAsistentesView(true);
            });
        }

        if (asistentesLimit) {
            asistentesLimit.addEventListener('change', function () {
                applyAsistentesView(true);
            });
        }

        if (asistentesSort) {
            asistentesSort.addEventListener('change', function () {
                applyAsistentesView(true);
            });
        }

        if (asistentesPrev) {
            asistentesPrev.addEventListener('click', function () {
                asistentesCurrentPage = Math.max(1, asistentesCurrentPage - 1);
                applyAsistentesView(false);
            });
        }

        if (asistentesNext) {
            asistentesNext.addEventListener('click', function () {
                asistentesCurrentPage += 1;
                applyAsistentesView(false);
            });
        }

        applyAsistentesView(true);
        bindEvidencePreview();
    };

    bindSupervisionAjax();
});
