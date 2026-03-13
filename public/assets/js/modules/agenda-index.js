/**
 * Agenda Directiva — filtros, listado AJAX y paginación.
 * Depende de: agenda.js (modal, SweetAlert). Contenedor: #agendaAjaxContainer[data-agenda-index-url]
 */
(function () {
    'use strict';

    function initAgendaIndexPage() {
        var container = document.getElementById('agendaAjaxContainer');
        var loading = document.getElementById('agendaAjaxLoading');
        var form = document.getElementById('agendaFiltersForm');
        if (!container || !form) return;

        var baseUrl = container.getAttribute('data-agenda-index-url') || form.getAttribute('action') || '';
        var debounceTimer;
        var agendaAjaxSeq = 0;
        var agendaAjaxAbort = null;

        function agendaFragmentUrl(params) {
            var u = new URL(baseUrl, window.location.origin);
            Object.keys(params).forEach(function (k) {
                if (params[k] !== '' && params[k] != null) u.searchParams.set(k, params[k]);
            });
            u.searchParams.set('fragment', '1');
            return u.toString();
        }

        function readFormParams() {
            var fd = new FormData(form);
            var o = {};
            fd.forEach(function (v, k) { o[k] = v; });
            return o;
        }

        function loadFromForm() {
            agendaAjaxLoad(agendaFragmentUrl(readFormParams()));
        }

        function loadFromFormDebounced() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(loadFromForm, 320);
        }

        function agendaAjaxLoad(url) {
            if (agendaAjaxAbort) {
                try { agendaAjaxAbort.abort(); } catch (e) {}
            }
            agendaAjaxAbort = new AbortController();
            var mySeq = ++agendaAjaxSeq;

            if (loading) {
                loading.hidden = false;
                loading.style.display = 'flex';
                loading.setAttribute('aria-hidden', 'false');
            }
            container.classList.add('is-loading');

            fetch(url, {
                credentials: 'same-origin',
                signal: agendaAjaxAbort.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
            })
                .then(function (r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.text();
                })
                .then(function (html) {
                    if (mySeq !== agendaAjaxSeq) return;
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var root = doc.getElementById('agendaAjaxRoot');
                    var inner = container.querySelector('#agendaAjaxRoot');
                    if (root && inner) {
                        inner.outerHTML = root.outerHTML;
                    } else if (root) {
                        container.insertAdjacentHTML('beforeend', root.outerHTML);
                    }
                    bindPagination();
                    try {
                        var clean = new URL(url);
                        clean.searchParams.delete('fragment');
                        var q = clean.searchParams.toString();
                        history.replaceState(null, '', clean.pathname + (q ? '?' + q : '') + clean.hash);
                    } catch (e) {}
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') return;
                    if (mySeq !== agendaAjaxSeq) return;
                    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'No se pudo actualizar la lista' });
                })
                .finally(function () {
                    if (mySeq !== agendaAjaxSeq) return;
                    if (loading) {
                        loading.hidden = true;
                        loading.style.display = 'none';
                        loading.setAttribute('aria-hidden', 'true');
                    }
                    container.classList.remove('is-loading');
                });
        }

        function bindPagination() {
            container.querySelectorAll('.agenda-pagination a[href]').forEach(function (a) {
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    var href = a.getAttribute('href');
                    if (!href) return;
                    var u = new URL(href, window.location.origin);
                    u.searchParams.set('fragment', '1');
                    agendaAjaxLoad(u.toString());
                });
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
        });

        var inputBuscar = document.getElementById('agendaInputBuscar');
        if (inputBuscar) inputBuscar.addEventListener('input', loadFromFormDebounced);

        var inputFecha = document.getElementById('agendaInputFecha');
        if (inputFecha) inputFecha.addEventListener('change', loadFromForm);

        var selectPerPage = document.getElementById('agendaSelectPerPage');
        if (selectPerPage) selectPerPage.addEventListener('change', loadFromForm);

        document.querySelectorAll('[data-agenda-clasificacion]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                clearTimeout(debounceTimer);
                var v = btn.getAttribute('data-agenda-clasificacion') || '';
                var hidden = document.getElementById('agendaFilterClasificacion');
                if (hidden) hidden.value = v;
                document.querySelectorAll('[data-agenda-clasificacion]').forEach(function (b) {
                    b.classList.toggle('is-active', b.getAttribute('data-agenda-clasificacion') === v);
                });
                loadFromForm();
            });
        });

        var btnClearExtra = document.getElementById('agendaBtnClearExtra');
        if (btnClearExtra) {
            btnClearExtra.addEventListener('click', function () {
                clearTimeout(debounceTimer);
                if (inputBuscar) inputBuscar.value = '';
                if (inputFecha) inputFecha.value = '';
                if (selectPerPage) selectPerPage.value = '20';
                loadFromForm();
            });
        }

        var clearLink = document.querySelector('[data-agenda-clear-filters]');
        if (clearLink) {
            clearLink.addEventListener('click', function (e) {
                e.preventDefault();
                clearTimeout(debounceTimer);
                var hidden = document.getElementById('agendaFilterClasificacion');
                if (hidden) hidden.value = '';
                if (inputBuscar) inputBuscar.value = '';
                if (inputFecha) inputFecha.value = '';
                if (selectPerPage) selectPerPage.value = '20';
                document.querySelectorAll('[data-agenda-clasificacion]').forEach(function (b) { b.classList.remove('is-active'); });
                agendaAjaxLoad(agendaFragmentUrl({ per_page: '20' }));
            });
        }

        bindPagination();

        var panel = document.getElementById('agendaFiltersAdvanced');
        var btnMas = document.getElementById('agendaBtnMasFiltros');
        var label = document.getElementById('agendaBtnMasFiltrosText');
        if (panel && btnMas) {
            btnMas.addEventListener('click', function () {
                var open = panel.classList.toggle('is-open');
                panel.hidden = !open;
                btnMas.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (label) label.textContent = open ? 'Menos filtros' : 'Más filtros';
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAgendaIndexPage);
    } else {
        initAgendaIndexPage();
    }
})();
