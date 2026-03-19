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
                if (Array.isArray(sug) && sug.length > 0) {
                    var sel = document.createElement('select');
                    sel.className = 'tm-seed-log-muni-select';
                    sel.setAttribute('aria-label', 'Municipio del catálogo');
                    sug.forEach(function (s) {
                        var o = document.createElement('option');
                        o.value = String(s.id);
                        o.textContent = String(s.municipio) + ' · ' + String(s.label || '');
                        sel.appendChild(o);
                    });
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'tm-btn tm-btn-sm tm-btn-primary tm-seed-log-register-btn';
                    btn.textContent = 'Registrar';
                    btn.addEventListener('click', function () {
                        var mid = parseInt(sel.value, 10);
                        if (!mid) return;
                        btn.disabled = true;
                        sel.disabled = true;
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
                                    btn.disabled = false;
                                    sel.disabled = false;
                                    return;
                                }
                                if (typeof w.Swal !== 'undefined') {
                                    w.Swal.fire({
                                        icon: 'success',
                                        title: 'Listo',
                                        text: res.j.message || 'Registro creado.',
                                        timer: 1800,
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
                                btn.disabled = false;
                                sel.disabled = false;
                            });
                    });
                    tdLink.appendChild(sel);
                    tdLink.appendChild(btn);
                } else {
                    tdLink.innerHTML =
                        '<span class="tm-muted tm-seed-log-no-sug">Sin sugerencia automática</span>';
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
