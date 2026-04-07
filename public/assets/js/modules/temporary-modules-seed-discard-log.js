/**
 * Tabla del log de filas descartadas al crear módulo desde Excel: sugerencias de municipio + registro vía AJAX.
 */
(function (w) {
    'use strict';

    function esc(s) {
        if (s == null || s === '') return '—';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    /**
     * @param {HTMLTableSectionElement} tbody
     * @param {Array} rows
     * @param {{ registerUrl: string, csrfToken: string, onUpdateList?: function(Array), onEmpty?: function(), jsonScriptEl?: HTMLElement }} options
     */
    function renderRows(tbody, rows, options) {
        if (!tbody || !options || !options.registerUrl || !options.csrfToken) {
            return;
        }
        var registerUrl = options.registerUrl;
        var searchUrl = options.searchUrl || '';
        var csrfToken = options.csrfToken;
        var onUpdateList = options.onUpdateList;
        var onEmpty = options.onEmpty;
        var jsonScriptEl = options.jsonScriptEl;

        tbody.innerHTML = '';

        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            tr.dataset.discardUid = row.discard_uid || '';

            var td0 = document.createElement('td');
            td0.innerHTML = esc(row.row);
            tr.appendChild(td0);
            var td1 = document.createElement('td');
            td1.innerHTML = esc(row.reason);
            tr.appendChild(td1);
            var td2 = document.createElement('td');
            td2.innerHTML = esc(row.microrregion);
            tr.appendChild(td2);
            var td3 = document.createElement('td');
            td3.innerHTML = esc(row.municipio);
            tr.appendChild(td3);
            var td4 = document.createElement('td');
            td4.className = 'tm-seed-log-accion';
            td4.innerHTML = esc(row.accion);
            tr.appendChild(td4);

            var tdLink = document.createElement('td');
            tdLink.className = 'tm-seed-log-link-cell';

            var canLink =
                row.discard_uid &&
                row.entry_payload &&
                typeof row.entry_payload === 'object' &&
                String(row.reason || '').indexOf('Municipio no resuelto') === 0;

            if (canLink) {
                var sug = row.municipio_suggestions;
                var primarySelect = null;
                var anotherSelect = null;
                var anotherWrap = null;
                var anotherMsg = null;

                function setLinkControlsDisabled(disabled) {
                    var ctrls = tdLink.querySelectorAll('button,select,input');
                    ctrls.forEach(function (el) { el.disabled = !!disabled; });
                }

                function municipioIdToRegister() {
                    if (primarySelect) {
                        var p = parseInt(primarySelect.value, 10);
                        if (p > 0) return p;
                    }
                    if (anotherWrap && !anotherWrap.classList.contains('tm-hidden') && anotherSelect) {
                        var alt = parseInt(anotherSelect.value, 10);
                        if (alt > 0) return alt;
                    }
                    if (anotherSelect) {
                        var fallback = parseInt(anotherSelect.value, 10);
                        if (fallback > 0) return fallback;
                    }
                    return 0;
                }

                function syncSelectedOtherToPrimary() {
                    if (!primarySelect || !anotherSelect) return;
                    var value = String(anotherSelect.value || '').trim();
                    if (!value) return;

                    var selected = anotherSelect.options[anotherSelect.selectedIndex] || null;
                    var label = selected ? String(selected.textContent || '').trim() : '';
                    var existing = Array.prototype.find.call(primarySelect.options, function (opt) {
                        return String(opt.value) === value;
                    });

                    if (!existing) {
                        var opt = document.createElement('option');
                        opt.value = value;
                        opt.textContent = label || ('Municipio ' + value);
                        opt.dataset.fromOther = '1';
                        primarySelect.appendChild(opt);
                        existing = opt;
                    } else if (label && existing.textContent !== label) {
                        existing.textContent = label;
                    }

                    primarySelect.value = value;
                }

                function runRegister() {
                    var mid = municipioIdToRegister();
                    if (!mid) {
                        if (typeof w.Swal !== 'undefined') {
                            w.Swal.fire({ icon: 'warning', title: 'Selecciona municipio', text: 'Elige un municipio sugerido o usa "Otro" para buscar.' });
                        } else {
                            window.alert('Selecciona un municipio.');
                        }
                        return;
                    }
                    setLinkControlsDisabled(true);
                    fetch(registerUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            discard_uid: row.discard_uid,
                            municipio_id: mid,
                        }),
                    })
                        .then(function (r) {
                            return r.json().then(function (j) {
                                return { ok: r.ok, status: r.status, j: j };
                            });
                        })
                        .then(function (res) {
                            if (!res.ok) {
                                var errMsg =
                                    (res.j &&
                                        res.j.errors &&
                                        res.j.errors.discard_uid &&
                                        res.j.errors.discard_uid[0]) ||
                                    (res.j &&
                                        res.j.errors &&
                                        res.j.errors.municipio_id &&
                                        res.j.errors.municipio_id[0]) ||
                                    (res.j && res.j.message) ||
                                    'No se pudo registrar.';
                                if (typeof w.Swal !== 'undefined') {
                                    w.Swal.fire({ icon: 'error', title: 'Error', text: errMsg });
                                } else {
                                    window.alert(errMsg);
                                }
                                setLinkControlsDisabled(false);
                                return;
                            }
                            if (typeof w.Swal !== 'undefined') {
                                w.Swal.fire({
                                    icon: 'success',
                                    title: 'Listo',
                                    text: res.j.message || 'Registro creado.',
                                    timer: 1300,
                                    showConfirmButton: false,
                                });
                            }
                            tr.remove();
                            var newLog = res.j.seed_discard_log;
                            if (Array.isArray(newLog) && typeof onUpdateList === 'function') {
                                onUpdateList(newLog);
                            }
                            if (jsonScriptEl && Array.isArray(newLog)) {
                                jsonScriptEl.textContent = JSON.stringify(newLog);
                            }
                            if (tbody.children.length === 0 && typeof onEmpty === 'function') {
                                onEmpty();
                            }
                        })
                        .catch(function () {
                            if (typeof w.Swal !== 'undefined') {
                                w.Swal.fire({ icon: 'error', title: 'Red', text: 'Error de conexión.' });
                            } else {
                                window.alert('Error de conexión.');
                            }
                            setLinkControlsDisabled(false);
                        });
                }

                if (Array.isArray(sug) && sug.length > 0) {
                    primarySelect = document.createElement('select');
                    primarySelect.className = 'tm-seed-log-muni-select';
                    primarySelect.setAttribute('aria-label', 'Municipio sugerido');
                    sug.forEach(function (s) {
                        var o = document.createElement('option');
                        o.value = String(s.id);
                        o.textContent = String(s.municipio) + ' · ' + String(s.label || '');
                        primarySelect.appendChild(o);
                    });
                    tdLink.appendChild(primarySelect);
                } else {
                    tdLink.innerHTML = '<span class="tm-muted tm-seed-log-no-sug">Sin sugerencia automática</span>';
                }

                var btns = document.createElement('div');
                btns.className = 'tm-seed-log-link-actions';

                var registerBtn = document.createElement('button');
                registerBtn.type = 'button';
                registerBtn.className = 'tm-btn tm-btn-sm tm-btn-primary tm-seed-log-register-btn';
                registerBtn.textContent = 'Registrar';
                registerBtn.addEventListener('click', runRegister);
                btns.appendChild(registerBtn);

                var anotherBtn = document.createElement('button');
                anotherBtn.type = 'button';
                anotherBtn.className = 'tm-btn tm-btn-sm tm-btn-outline tm-seed-log-other-btn';
                anotherBtn.textContent = 'Otro';
                btns.appendChild(anotherBtn);
                tdLink.appendChild(btns);

                anotherWrap = document.createElement('div');
                anotherWrap.className = 'tm-seed-log-search-wrap tm-hidden';

                var searchInput = document.createElement('input');
                searchInput.type = 'search';
                searchInput.className = 'tm-seed-log-search-input';
                searchInput.placeholder = 'Buscar municipio...';
                searchInput.value = String(row.municipio || '').trim();

                var searchBtn = document.createElement('button');
                searchBtn.type = 'button';
                searchBtn.className = 'tm-btn tm-btn-sm tm-seed-log-search-btn';
                searchBtn.textContent = 'Buscar';

                anotherSelect = document.createElement('select');
                anotherSelect.className = 'tm-seed-log-muni-select tm-seed-log-muni-select--other';
                anotherSelect.setAttribute('aria-label', 'Resultados de búsqueda de municipios');

                anotherMsg = document.createElement('small');
                anotherMsg.className = 'tm-seed-log-search-msg tm-muted';

                var searchRow = document.createElement('div');
                searchRow.className = 'tm-seed-log-search-row';
                searchRow.appendChild(searchInput);
                searchRow.appendChild(searchBtn);
                anotherWrap.appendChild(searchRow);
                anotherWrap.appendChild(anotherSelect);
                anotherWrap.appendChild(anotherMsg);
                tdLink.appendChild(anotherWrap);

                function fillSearchResults(items) {
                    anotherSelect.innerHTML = '';
                    if (!Array.isArray(items) || items.length === 0) {
                        var emptyOpt = document.createElement('option');
                        emptyOpt.value = '';
                        emptyOpt.textContent = 'Sin resultados';
                        anotherSelect.appendChild(emptyOpt);
                        anotherMsg.textContent = 'No hubo coincidencias. Prueba otro texto.';
                        return;
                    }
                    items.forEach(function (it) {
                        var o = document.createElement('option');
                        o.value = String(it.id);
                        o.textContent = String(it.municipio) + ' · ' + String(it.label || '');
                        anotherSelect.appendChild(o);
                    });
                    anotherMsg.textContent = items.length + ' resultado(s).';
                    syncSelectedOtherToPrimary();
                }

                function runSearch() {
                    if (!searchUrl) {
                        anotherMsg.textContent = 'No hay endpoint de búsqueda configurado.';
                        return;
                    }
                    var query = String(searchInput.value || '').trim();
                    var glue = searchUrl.indexOf('?') === -1 ? '?' : '&';
                    var url = searchUrl + glue + 'q=' + encodeURIComponent(query);
                    searchBtn.disabled = true;
                    anotherMsg.textContent = 'Buscando...';
                    fetch(url, {
                        method: 'GET',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    })
                        .then(function (r) {
                            return r.json().then(function (j) {
                                return { ok: r.ok, j: j };
                            });
                        })
                        .then(function (res) {
                            if (!res.ok) {
                                anotherMsg.textContent = (res.j && res.j.message) || 'No se pudo buscar.';
                                return;
                            }
                            fillSearchResults(res.j && res.j.items ? res.j.items : []);
                        })
                        .catch(function () {
                            anotherMsg.textContent = 'Error de conexión al buscar.';
                        })
                        .finally(function () {
                            searchBtn.disabled = false;
                        });
                }

                searchBtn.addEventListener('click', runSearch);
                searchInput.addEventListener('keydown', function (ev) {
                    if (ev.key === 'Enter') {
                        ev.preventDefault();
                        runSearch();
                    }
                });

                anotherSelect.addEventListener('change', function () {
                    syncSelectedOtherToPrimary();
                });

                anotherBtn.addEventListener('click', function () {
                    var opening = anotherWrap.classList.contains('tm-hidden');
                    anotherWrap.classList.toggle('tm-hidden');
                    anotherBtn.textContent = opening ? 'Ocultar' : 'Otro';
                    if (opening && anotherSelect.options.length === 0) {
                        runSearch();
                    }
                });

                if (!primarySelect) {
                    anotherWrap.classList.remove('tm-hidden');
                    anotherBtn.textContent = 'Ocultar';
                    runSearch();
                }
            } else {
                tdLink.innerHTML = '<span class="tm-muted">—</span>';
            }

            tr.appendChild(tdLink);
            tbody.appendChild(tr);
        });
    }

    w.tmSeedDiscardLog = {
        esc: esc,
        renderRows: renderRows,
    };
})(window);
