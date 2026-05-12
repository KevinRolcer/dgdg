/**
 * Log de filas descartadas al crear modulo desde Excel:
 * correccion por grupos + correccion fila por fila.
 */
(function (w) {
    'use strict';

    function esc(s) {
        if (s == null || s === '') return '-';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function normalizeGroupText(value) {
        var s = String(value || '').trim().toUpperCase();
        if (s.normalize) {
            s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        return s.replace(/\s+/g, ' ').replace(/\b0+(\d+)\b/g, '$1');
    }

    function canLinkRow(row) {
        return !!(
            row &&
            row.discard_uid &&
            row.entry_payload &&
            typeof row.entry_payload === 'object' &&
            String(row.reason || '').indexOf('Municipio no resuelto') === 0
        );
    }

    function buildGroups(rows) {
        var map = {};
        rows.forEach(function (row) {
            if (!canLinkRow(row)) return;
            var label = String(row.municipio || '').trim();
            var key = normalizeGroupText(label);
            if (!key) return;
            if (!map[key]) {
                map[key] = {
                    key: key,
                    label: label,
                    rows: [],
                    suggestions: Array.isArray(row.municipio_suggestions) ? row.municipio_suggestions : [],
                };
            }
            map[key].rows.push(row);
            if (map[key].suggestions.length === 0 && Array.isArray(row.municipio_suggestions)) {
                map[key].suggestions = row.municipio_suggestions;
            }
        });

        return Object.keys(map).map(function (key) { return map[key]; })
            .filter(function (group) { return group.rows.length > 1; })
            .sort(function (a, b) { return b.rows.length - a.rows.length; });
    }

    function renderRows(tbody, rows, options) {
        if (!tbody || !options || !options.registerUrl || !options.csrfToken) return;

        var registerUrl = options.registerUrl;
        var searchUrl = options.searchUrl || '';
        var csrfToken = options.csrfToken;
        var onUpdateList = options.onUpdateList;
        var onEmpty = options.onEmpty;
        var jsonScriptEl = options.jsonScriptEl;
        var compactResponse = !!options.compactResponse;
        var onAfterMutation = options.onAfterMutation;

        rows = Array.isArray(rows) ? rows : [];
        tbody.innerHTML = '';

        function refreshLog(newLog) {
            if (Array.isArray(newLog) && typeof onUpdateList === 'function') {
                onUpdateList(newLog);
            }
            if (jsonScriptEl && Array.isArray(newLog)) {
                jsonScriptEl.textContent = JSON.stringify(newLog);
            }
            if (Array.isArray(newLog)) {
                renderRows(tbody, newLog, options);
                if (newLog.length === 0 && typeof onEmpty === 'function') onEmpty();
            }
        }

        function showMessage(type, title, text) {
            if (typeof w.Swal !== 'undefined') {
                w.Swal.fire({ icon: type, title: title, text: text });
            } else {
                window.alert(text || title);
            }
        }

        function municipioIdFromControls(primarySelect, anotherWrap, anotherSelect) {
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

        function postRegister(discardUids, municipioId, setDisabled) {
            discardUids = Array.isArray(discardUids) ? discardUids.filter(Boolean) : [];
            if (!municipioId || discardUids.length === 0) {
                showMessage('warning', 'Selecciona municipio', 'Elige un municipio para aplicar la correccion.');
                return;
            }
            if (typeof setDisabled === 'function') setDisabled(true);

            var body = { municipio_id: municipioId };
            if (compactResponse) {
                body.compact_response = true;
            }
            if (discardUids.length === 1) {
                body.discard_uid = discardUids[0];
            } else {
                body.discard_uids = discardUids;
            }

            fetch(registerUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            })
                .then(function (r) {
                    return r.json().then(function (j) {
                        return { ok: r.ok, j: j };
                    });
                })
                .then(function (res) {
                    if (!res.ok) {
                        var errMsg =
                            (res.j && res.j.errors && res.j.errors.discard_uid && res.j.errors.discard_uid[0]) ||
                            (res.j && res.j.errors && res.j.errors.discard_uids && res.j.errors.discard_uids[0]) ||
                            (res.j && res.j.errors && res.j.errors.municipio_id && res.j.errors.municipio_id[0]) ||
                            (res.j && res.j.message) ||
                            'No se pudo registrar.';
                        showMessage('error', 'Error', errMsg);
                        if (typeof setDisabled === 'function') setDisabled(false);
                        return;
                    }

                    if (typeof w.Swal !== 'undefined') {
                        w.Swal.fire({
                            icon: 'success',
                            title: 'Listo',
                            text: res.j.message || 'Registros creados.',
                            timer: 1400,
                            showConfirmButton: false,
                        });
                    }
                    if (compactResponse && typeof onAfterMutation === 'function') {
                        onAfterMutation(res.j);
                    } else {
                        refreshLog(res.j.seed_discard_log);
                    }
                })
                .catch(function () {
                    showMessage('error', 'Red', 'Error de conexion.');
                    if (typeof setDisabled === 'function') setDisabled(false);
                });
        }

        function renderMunicipioControls(container, suggestions, initialQuery, onApply, applyLabel) {
            var primarySelect = null;
            var anotherSelect = null;
            var anotherWrap = null;

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
                    primarySelect.appendChild(opt);
                    existing = opt;
                } else if (label && existing.textContent !== label) {
                    existing.textContent = label;
                }
                primarySelect.value = value;
            }

            if (Array.isArray(suggestions) && suggestions.length > 0) {
                primarySelect = document.createElement('select');
                primarySelect.className = 'tm-seed-log-muni-select';
                primarySelect.setAttribute('aria-label', 'Municipio sugerido');
                suggestions.forEach(function (s) {
                    var o = document.createElement('option');
                    o.value = String(s.id);
                    o.textContent = String(s.municipio) + ' - ' + String(s.label || '');
                    primarySelect.appendChild(o);
                });
                container.appendChild(primarySelect);
            } else {
                var noSug = document.createElement('span');
                noSug.className = 'tm-muted tm-seed-log-no-sug';
                noSug.textContent = 'Sin sugerencia automatica';
                container.appendChild(noSug);
            }

            var btns = document.createElement('div');
            btns.className = 'tm-seed-log-link-actions';

            var applyBtn = document.createElement('button');
            applyBtn.type = 'button';
            applyBtn.className = 'tm-btn tm-btn-sm tm-btn-primary tm-seed-log-register-btn';
            applyBtn.textContent = applyLabel || 'Registrar';
            btns.appendChild(applyBtn);

            var anotherBtn = document.createElement('button');
            anotherBtn.type = 'button';
            anotherBtn.className = 'tm-btn tm-btn-sm tm-btn-outline tm-seed-log-other-btn';
            anotherBtn.textContent = 'Otro';
            btns.appendChild(anotherBtn);
            container.appendChild(btns);

            anotherWrap = document.createElement('div');
            anotherWrap.className = 'tm-seed-log-search-wrap tm-hidden';

            var searchInput = document.createElement('input');
            searchInput.type = 'search';
            searchInput.className = 'tm-seed-log-search-input';
            searchInput.placeholder = 'Buscar municipio...';
            searchInput.value = String(initialQuery || '').trim();

            var searchBtn = document.createElement('button');
            searchBtn.type = 'button';
            searchBtn.className = 'tm-btn tm-btn-sm tm-seed-log-search-btn';
            searchBtn.textContent = 'Buscar';

            anotherSelect = document.createElement('select');
            anotherSelect.className = 'tm-seed-log-muni-select tm-seed-log-muni-select--other';
            anotherSelect.setAttribute('aria-label', 'Resultados de busqueda de municipios');

            var msg = document.createElement('small');
            msg.className = 'tm-seed-log-search-msg tm-muted';

            var searchRow = document.createElement('div');
            searchRow.className = 'tm-seed-log-search-row';
            searchRow.appendChild(searchInput);
            searchRow.appendChild(searchBtn);
            anotherWrap.appendChild(searchRow);
            anotherWrap.appendChild(anotherSelect);
            anotherWrap.appendChild(msg);
            container.appendChild(anotherWrap);

            function fillSearchResults(items) {
                anotherSelect.innerHTML = '';
                if (!Array.isArray(items) || items.length === 0) {
                    var emptyOpt = document.createElement('option');
                    emptyOpt.value = '';
                    emptyOpt.textContent = 'Sin resultados';
                    anotherSelect.appendChild(emptyOpt);
                    msg.textContent = 'No hubo coincidencias. Prueba otro texto.';
                    return;
                }
                items.forEach(function (it) {
                    var o = document.createElement('option');
                    o.value = String(it.id);
                    o.textContent = String(it.municipio) + ' - ' + String(it.label || '');
                    anotherSelect.appendChild(o);
                });
                msg.textContent = items.length + ' resultado(s).';
                syncSelectedOtherToPrimary();
            }

            function runSearch() {
                if (!searchUrl) {
                    msg.textContent = 'No hay endpoint de busqueda configurado.';
                    return;
                }
                var glue = searchUrl.indexOf('?') === -1 ? '?' : '&';
                var url = searchUrl + glue + 'q=' + encodeURIComponent(String(searchInput.value || '').trim());
                searchBtn.disabled = true;
                msg.textContent = 'Buscando...';
                fetch(url, {
                    method: 'GET',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (r) {
                        return r.json().then(function (j) {
                            return { ok: r.ok, j: j };
                        });
                    })
                    .then(function (res) {
                        if (!res.ok) {
                            msg.textContent = (res.j && res.j.message) || 'No se pudo buscar.';
                            return;
                        }
                        fillSearchResults(res.j && res.j.items ? res.j.items : []);
                    })
                    .catch(function () {
                        msg.textContent = 'Error de conexion al buscar.';
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
            anotherSelect.addEventListener('change', syncSelectedOtherToPrimary);
            anotherBtn.addEventListener('click', function () {
                var opening = anotherWrap.classList.contains('tm-hidden');
                anotherWrap.classList.toggle('tm-hidden');
                anotherBtn.textContent = opening ? 'Ocultar' : 'Otro';
                if (opening && anotherSelect.options.length === 0) runSearch();
            });
            if (!primarySelect) {
                anotherWrap.classList.remove('tm-hidden');
                anotherBtn.textContent = 'Ocultar';
                runSearch();
            }

            applyBtn.addEventListener('click', function () {
                onApply(municipioIdFromControls(primarySelect, anotherWrap, anotherSelect), function (disabled) {
                    container.querySelectorAll('button,select,input').forEach(function (el) {
                        el.disabled = !!disabled;
                    });
                });
            });
        }

        function appendGroupRows() {
            var groups = Array.isArray(options.groups) ? options.groups : buildGroups(rows);
            if (groups.length === 0) return;

            var head = document.createElement('tr');
            head.className = 'tm-seed-log-group-head';
            var headTd = document.createElement('td');
            headTd.colSpan = 6;
            headTd.innerHTML = '<strong>Correccion por grupos</strong><br><span class="tm-muted">Aplica un municipio a todas las filas con la misma respuesta similar. La correccion uno por uno queda abajo.</span>';
            head.appendChild(headTd);
            tbody.appendChild(head);

            groups.forEach(function (group) {
                var tr = document.createElement('tr');
                tr.className = 'tm-seed-log-group-row';
                var td = document.createElement('td');
                td.colSpan = 6;
                var first = group.rows && group.rows[0] ? group.rows[0] : { row: group.first_row };
                var last = group.rows && group.rows.length ? group.rows[group.rows.length - 1] : { row: group.last_row };
                var groupRowCount =
                    group.rows && group.rows.length
                        ? group.rows.length
                        : (Number(group.count) || (Array.isArray(group.discard_uids) ? group.discard_uids.length : 0) || 0);
                td.innerHTML = '<div class="tm-seed-log-group-box">'
                    + '<div><strong>' + esc(group.label) + '</strong> <span class="tm-muted">(' + esc(String(groupRowCount)) + ' filas)</span>'
                    + '<br><small>Filas ' + esc(first.row) + ' a ' + esc(last.row) + '</small></div>'
                    + '<div class="tm-seed-log-group-controls"></div>'
                    + '<button type="button" class="tm-btn tm-btn-sm tm-btn-outline tm-seed-log-focus-one">Corregir 1 por 1</button>'
                    + '</div>';
                tr.appendChild(td);
                tbody.appendChild(tr);

                renderMunicipioControls(
                    td.querySelector('.tm-seed-log-group-controls'),
                    group.suggestions || group.municipio_suggestions || [],
                    group.label,
                    function (municipioId, setDisabled) {
                        var uids = Array.isArray(group.discard_uids)
                            ? group.discard_uids
                            : (Array.isArray(group.rows)
                                ? group.rows.map(function (r) { return r.discard_uid; })
                                : []);
                        postRegister(uids, municipioId, setDisabled);
                    },
                    'Aplicar grupo'
                );

                var focusBtn = td.querySelector('.tm-seed-log-focus-one');
                if (focusBtn) {
                    focusBtn.addEventListener('click', function () {
                        var uid = group.rows && group.rows[0] && group.rows[0].discard_uid;
                        var target = uid ? tbody.querySelector('[data-discard-uid="' + String(uid).replace(/"/g, '\\"') + '"]') : null;
                        if (target) {
                            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            target.classList.add('tm-seed-log-row-highlight');
                            setTimeout(function () { target.classList.remove('tm-seed-log-row-highlight'); }, 1800);
                        }
                    });
                }
            });

            var one = document.createElement('tr');
            one.className = 'tm-seed-log-onebyone-head';
            var oneTd = document.createElement('td');
            oneTd.colSpan = 6;
            oneTd.innerHTML = '<strong>Correccion 1 por 1</strong>';
            one.appendChild(oneTd);
            tbody.appendChild(one);
        }

        appendGroupRows();

        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            tr.dataset.discardUid = row.discard_uid || '';

            ['row', 'reason', 'microrregion', 'municipio'].forEach(function (key) {
                var td = document.createElement('td');
                td.innerHTML = esc(row[key]);
                tr.appendChild(td);
            });

            var tdAccion = document.createElement('td');
            tdAccion.className = 'tm-seed-log-accion';
            tdAccion.innerHTML = esc(row.accion);
            tr.appendChild(tdAccion);

            var tdLink = document.createElement('td');
            tdLink.className = 'tm-seed-log-link-cell';

            if (canLinkRow(row)) {
                renderMunicipioControls(
                    tdLink,
                    row.municipio_suggestions,
                    row.municipio,
                    function (municipioId, setDisabled) {
                        postRegister([row.discard_uid], municipioId, setDisabled);
                    },
                    'Registrar'
                );
            } else {
                tdLink.innerHTML = '<span class="tm-muted">-</span>';
            }

            tr.appendChild(tdLink);
            tbody.appendChild(tr);
        });

        if (options.pagination && typeof options.onPageChange === 'function') {
            var p = options.pagination;
            var pager = document.createElement('tr');
            pager.className = 'tm-seed-log-pager-row';
            var tdPager = document.createElement('td');
            tdPager.colSpan = 6;
            var page = Number(p.page || 1);
            var last = Number(p.last_page || 1);
            tdPager.innerHTML = '<div class="tm-seed-log-pager">'
                + '<button type="button" class="tm-btn tm-btn-sm tm-btn-outline" data-seed-log-prev ' + (page <= 1 ? 'disabled' : '') + '>Anterior</button>'
                + '<span>Mostrando ' + esc(p.from || 0) + '-' + esc(p.to || 0) + ' de ' + esc(p.total || 0) + ' · pagina ' + esc(page) + ' de ' + esc(last) + '</span>'
                + '<button type="button" class="tm-btn tm-btn-sm tm-btn-outline" data-seed-log-next ' + (page >= last ? 'disabled' : '') + '>Siguiente</button>'
                + '</div>';
            pager.appendChild(tdPager);
            tbody.appendChild(pager);
            var prev = tdPager.querySelector('[data-seed-log-prev]');
            var next = tdPager.querySelector('[data-seed-log-next]');
            if (prev) prev.addEventListener('click', function () { if (page > 1) options.onPageChange(page - 1); });
            if (next) next.addEventListener('click', function () { if (page < last) options.onPageChange(page + 1); });
        }
    }

    w.tmSeedDiscardLog = {
        esc: esc,
        renderRows: renderRows,
        buildGroups: buildGroups,
    };
})(window);
