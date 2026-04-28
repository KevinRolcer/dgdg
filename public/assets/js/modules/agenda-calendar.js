/**
 * Agenda calendario: pestañas Mes | Eventos | Fichas y cambio de mes vía AJAX (sin recargar la página).
 */
(function () {
    'use strict';

    var VISTA_QUERY = 'vista';

    function readVistaFromUrl() {
        try {
            var u = new URL(window.location.href);
            var v = (u.searchParams.get(VISTA_QUERY) || '').toLowerCase();
            if (v === 'lista' || v === 'fichas') {
                return v;
            }
            return null;
        } catch (e) {
            return null;
        }
    }

    function applyVistaToSearchParams(u, tabName) {
        if (tabName === 'lista' || tabName === 'fichas') {
            u.searchParams.set(VISTA_QUERY, tabName);
        } else {
            u.searchParams.delete(VISTA_QUERY);
        }
    }

    /** Actualiza ?vista= sin nueva entrada en el historial (F5 conserva pestaña; enlace sin vista = predeterminado). */
    function syncVistaQueryToUrl(tabName) {
        try {
            var u = new URL(window.location.href);
            applyVistaToSearchParams(u, tabName);
            history.replaceState({ agendaCal: true }, '', u.pathname + u.search + u.hash);
        } catch (e2) {
            /* ignore */
        }
    }

    function mergeVistaIntoCalendarUrl(urlString, tabName) {
        try {
            var u = new URL(urlString, window.location.origin);
            applyVistaToSearchParams(u, tabName);
            return u.pathname + u.search + u.hash;
        } catch (e3) {
            return urlString;
        }
    }

    /** Aviso flotante (misma idea que #appToast en app-shell). */
    function showAgendaCalToast(message, durationMs) {
        var baseMs = durationMs == null ? 4200 : durationMs;
        var ms = Math.max(900, baseMs - 500);
        var el = document.createElement('div');
        el.className = 'app-toast';
        el.setAttribute('role', 'status');
        var span = document.createElement('span');
        span.textContent = message;
        el.appendChild(span);
        document.body.appendChild(el);
        requestAnimationFrame(function () {
            el.classList.add('is-visible');
        });
        setTimeout(function () {
            el.classList.remove('is-visible');
            setTimeout(function () {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            }, 280);
        }, ms);
    }

    function getRoot() {
        return document.getElementById('agendaCalAjaxRoot');
    }

    function getPage() {
        return document.getElementById('agendaCalPage');
    }

    function activateTabInScope(scope, name) {
        var root = scope || document;
        if (!name || ['mes', 'lista', 'fichas'].indexOf(name) === -1) {
            name = 'mes';
        }
        var tabs = root.querySelectorAll('[data-agenda-cal-tab]');
        var panels = root.querySelectorAll('[data-agenda-cal-panel]');
        tabs.forEach(function (btn) {
            var on = btn.getAttribute('data-agenda-cal-tab') === name;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panels.forEach(function (panel) {
            var on = panel.getAttribute('data-agenda-cal-panel') === name;
            panel.classList.toggle('is-active', on);
            panel.hidden = !on;
        });
        requestAnimationFrame(function () {
            refreshAgendaCalCardDescMore(root);
        });
        if (name === 'fichas') {
            loadAgendaCalFichasIfNeeded(root);
        }
    }

    function loadAgendaCalFichasIfNeeded(scope) {
        var root = scope || document;
        var page = getPage();
        var mount = root.querySelector('[data-agenda-cal-fichas-mount]');
        if (!mount || !page) {
            return;
        }
        if (mount.getAttribute('data-loaded') === '1') {
            return;
        }
        if (mount.getAttribute('data-loading') === '1') {
            return;
        }
        var meta = root.querySelector('.agenda-cal-state-meta');
        if (!meta) {
            return;
        }
        var y = meta.getAttribute('data-agenda-cal-year');
        var m = meta.getAttribute('data-agenda-cal-month');
        var base = page.getAttribute('data-agenda-cal-base-url');
        if (!y || !m || !base) {
            return;
        }

        mount.setAttribute('data-loading', '1');

        var u = new URL(base, window.location.origin);
        u.searchParams.set('year', y);
        u.searchParams.set('month', m);
        var cl = meta.getAttribute('data-agenda-cal-clasificacion') || '';
        var bq = meta.getAttribute('data-agenda-cal-buscar') || '';
        if (cl) {
            u.searchParams.set('clasificacion', cl);
        }
        if (bq) {
            u.searchParams.set('buscar', bq);
        }
        u.searchParams.set('partial', '1');
        u.searchParams.set('fichas_only', '1');

        fetch(u.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'text/html',
            },
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.text();
            })
            .then(function (html) {
                mount.innerHTML = html;
                mount.setAttribute('data-loaded', '1');
                requestAnimationFrame(function () {
                    refreshAgendaCalCardDescMore(root);
                });
            })
            .catch(function () {
                mount.innerHTML =
                    '<div class="agenda-cal-fichas-placeholder" role="alert"><p class="agenda-cal-empty">No se pudieron cargar las fichas. Intenta de nuevo o cambia de mes.</p></div>';
            })
            .finally(function () {
                mount.removeAttribute('data-loading');
            });
    }

    function bindAgendaCalendarTabs(scope) {
        var root = scope || document;
        var tabs = root.querySelectorAll('[data-agenda-cal-tab]');
        if (!tabs.length) {
            return;
        }
        tabs.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var name = btn.getAttribute('data-agenda-cal-tab');
                if (name) {
                    activateTabInScope(root, name);
                    syncVistaQueryToUrl(name);
                }
            });
        });
    }

    function getActiveTabName(scope) {
        var root = scope || document;
        var active = root.querySelector('.agenda-cal-tab.is-active');
        return active ? active.getAttribute('data-agenda-cal-tab') : 'mes';
    }

    function updateCardDescMoreBtn(wrap) {
        var inner = wrap.querySelector('[data-agenda-cal-desc-inner]');
        var btn = wrap.querySelector('[data-agenda-cal-desc-more]');
        if (!inner || !btn) {
            return;
        }
        var expanded = inner.classList.contains('is-expanded');
        if (expanded) {
            btn.hidden = false;
            btn.textContent = '• Ver menos';
            btn.setAttribute('aria-expanded', 'true');
            return;
        }
        btn.setAttribute('aria-expanded', 'false');
        var overflow = inner.scrollHeight > inner.clientHeight + 2;
        btn.hidden = !overflow;
        btn.textContent = '• Ver más';
    }

    function refreshAgendaCalCardDescMore(scope) {
        var root = scope || document;
        root.querySelectorAll('.agenda-cal-card-desc-wrap').forEach(updateCardDescMoreBtn);
    }

    var resizeDescTimer;

    function onResizeCardDescMore() {
        clearTimeout(resizeDescTimer);
        resizeDescTimer = setTimeout(function () {
            var ajaxRoot = getRoot();
            if (ajaxRoot) {
                refreshAgendaCalCardDescMore(ajaxRoot);
            }
        }, 150);
    }

    function onCardDescMoreClick(e) {
        var btn = e.target.closest('[data-agenda-cal-desc-more]');
        if (!btn) {
            return;
        }
        var page = getPage();
        if (!page || !page.contains(btn)) {
            return;
        }
        e.preventDefault();
        var wrap = btn.closest('.agenda-cal-card-desc-wrap');
        if (!wrap) {
            return;
        }
        var inner = wrap.querySelector('[data-agenda-cal-desc-inner]');
        if (!inner) {
            return;
        }
        var nowExpanded = inner.classList.toggle('is-expanded');
        if (!nowExpanded) {
            inner.scrollTop = 0;
        }
        updateCardDescMoreBtn(wrap);
        if (nowExpanded) {
            try {
                inner.focus({ preventScroll: true });
            } catch (err2) {
                inner.focus();
            }
        }
    }

    function initAgendaCalendarTabsFirstLoad() {
        var ajaxRoot = getRoot();
        if (!ajaxRoot) {
            return;
        }
        bindAgendaCalendarTabs(ajaxRoot);
        /* ?vista=lista|fichas: recarga conserva pestaña. Sin parámetro (p. ej. desde listado): Mes. */
        activateTabInScope(ajaxRoot, readVistaFromUrl() || 'mes');
    }

    function appendPartialParam(url) {
        try {
            var u = new URL(url, window.location.origin);
            u.searchParams.set('partial', '1');
            u.searchParams.set('fichas_cards', '0');
            return u.toString();
        } catch (e) {
            var sep = url.indexOf('?') === -1 ? '?' : '&';
            return url + sep + 'partial=1&fichas_cards=0';
        }
    }

    function stripPartialFromUrl(url) {
        try {
            var u = new URL(url, window.location.origin);
            u.searchParams.delete('partial');
            return u.pathname + u.search + u.hash;
        } catch (e) {
            return url;
        }
    }

    function setLoading(loading) {
        var el = getRoot();
        if (!el) {
            return;
        }
        el.classList.toggle('is-loading', !!loading);
        el.setAttribute('aria-busy', loading ? 'true' : 'false');
    }

    function loadMonthFromUrl(fullUrl, pushHistory) {
        var ajaxRoot = getRoot();
        var page = getPage();
        if (!ajaxRoot || !page) {
            return Promise.resolve();
        }

        var fetchUrl = appendPartialParam(fullUrl);
        var tabBefore = getActiveTabName(ajaxRoot);

        setLoading(true);

        return fetch(fetchUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'text/html',
            },
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.text();
            })
            .then(function (html) {
                var inner = html;
                try {
                    var parsed = new DOMParser().parseFromString(html, 'text/html');
                    var nested = parsed.getElementById('agendaCalAjaxRoot');
                    if (nested) {
                        inner = nested.innerHTML;
                    } else if (parsed.querySelector('html') && parsed.querySelector('.app-shell')) {
                        var root2 = parsed.querySelector('#agendaCalAjaxRoot');
                        inner = root2 ? root2.innerHTML : html;
                    }
                } catch (eParse) {
                    inner = html;
                }
                ajaxRoot.innerHTML = inner;
                bindAgendaCalendarTabs(ajaxRoot);
                /* pushHistory: la URL del navegador sigue siendo la del mes anterior → usar tabBefore. popstate: la URL ya es la destino → priorizar ?vista=. */
                var tabToShow = pushHistory ? tabBefore : (readVistaFromUrl() || tabBefore || 'mes');
                activateTabInScope(ajaxRoot, tabToShow);
                try {
                    var doc2 = new DOMParser().parseFromString('<div>' + inner + '</div>', 'text/html');
                    var label = doc2.querySelector('.agenda-cal-month-label');
                    if (label && document.title) {
                        document.title = 'Agenda — ' + (label.textContent || '').trim();
                    }
                } catch (e3) {
                    /* ignore */
                }
                if (pushHistory) {
                    try {
                        var pathQs = mergeVistaIntoCalendarUrl(stripPartialFromUrl(fullUrl), tabBefore);
                        history.pushState({ agendaCal: true }, '', pathQs);
                    } catch (e4) {
                        /* ignore */
                    }
                }
            })
            .catch(function () {
                window.location.href = fullUrl;
            })
            .finally(function () {
                setLoading(false);
            });
    }

    function onNavClick(e) {
        var a = e.target.closest('a.agenda-cal-nav-ajax');
        if (!a || !a.getAttribute('data-agenda-cal-nav')) {
            return;
        }
        var page = getPage();
        if (!page || !page.contains(a)) {
            return;
        }
        e.preventDefault();
        loadMonthFromUrl(a.href, true);
    }

    function initMonthNavAjax() {
        var page = getPage();
        if (!page) {
            return;
        }
        page.addEventListener('click', onNavClick);
    }

    function initPopState() {
        window.addEventListener('popstate', function () {
            if (!getPage()) {
                return;
            }
            loadMonthFromUrl(window.location.href, false);
        });
    }

    function getStateMeta() {
        var root = getRoot();
        return root ? root.querySelector('.agenda-cal-state-meta') : null;
    }

    function getPrintModal() {
        return document.getElementById('agendaCalFichasPrintModal');
    }

    function getCsrfToken() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function setPrintModalOpen(open) {
        var modal = getPrintModal();
        if (!modal) {
            return;
        }
        modal.hidden = !open;
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        if (open) {
            var err = document.getElementById('agendaCalPrintError');
            if (err) {
                err.hidden = true;
                err.textContent = '';
            }
            syncPrintMonthInputFromCalendar();
            updatePrintScopeUi();
        }
    }

    function onOpenPrintFichasClick(e) {
        var btn = e.target.closest('[data-agenda-cal-print-fichas]');
        if (!btn) {
            return;
        }
        var page = getPage();
        if (!page || !page.contains(btn)) {
            return;
        }
        e.preventDefault();
        setPrintModalOpen(true);
    }

    function syncCustomMonthsHiddenFromList() {
        var ul = document.getElementById('agendaCalPrintMonthList');
        var hid = document.getElementById('agendaCalCustomMonthsJson');
        if (!ul || !hid) {
            return;
        }
        var pairs = [];
        ul.querySelectorAll('li[data-y][data-m]').forEach(function (li) {
            pairs.push([
                parseInt(li.getAttribute('data-y'), 10),
                parseInt(li.getAttribute('data-m'), 10),
            ]);
        });
        hid.value = JSON.stringify(pairs);
    }

    function monthLabelEs(y, m) {
        var d = new Date(y, m - 1, 1);
        var s = d.toLocaleDateString('es-MX', { month: 'long', year: 'numeric' });
        if (!s) {
            return y + '-' + m;
        }
        return s.charAt(0).toUpperCase() + s.slice(1);
    }

    function addCustomMonthToList(y, m) {
        var ul = document.getElementById('agendaCalPrintMonthList');
        if (!ul || !y || !m || m < 1 || m > 12) {
            return;
        }
        var key = y + '-' + m;
        if (ul.querySelector('li[data-key="' + key + '"]')) {
            return;
        }
        var li = document.createElement('li');
        li.className = 'agenda-cal-print-month-item';
        li.setAttribute('data-y', String(y));
        li.setAttribute('data-m', String(m));
        li.setAttribute('data-key', key);
        var span = document.createElement('span');
        span.className = 'agenda-cal-print-month-item-label';
        span.textContent = monthLabelEs(y, m);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'agenda-cal-print-month-remove';
        btn.setAttribute('aria-label', 'Quitar mes');
        btn.innerHTML = '&times;';
        li.appendChild(span);
        li.appendChild(btn);
        ul.appendChild(li);
        syncCustomMonthsHiddenFromList();
    }

    function updatePrintScopeUi() {
        var form = document.getElementById('agendaCalFichasPrintForm');
        var box = document.getElementById('agendaCalPrintCustomMonthsBox');
        var scopeFieldset = document.getElementById('agendaCalPrintScopeFieldset');
        var calSection = document.getElementById('agendaCalPrintCalendarSection');
        if (!form || !box) {
            return;
        }
        var templateEl = form.querySelector('input[name="template"]:checked');
        var t = templateEl ? templateEl.value : '';
        var isCalendar = t === 'calendar';

        if (isCalendar) {
            if (scopeFieldset) {
                scopeFieldset.hidden = true;
            }
            if (calSection) {
                calSection.hidden = false;
            }
            // Force scope to custom_months for calendar template
            var customMonthsRadio = form.querySelector('input[name="scope"][value="custom_months"]');
            if (customMonthsRadio) {
                customMonthsRadio.checked = true;
            }
            box.hidden = false;
            // Pre-add current month if list is empty
            var ul = document.getElementById('agendaCalPrintMonthList');
            if (ul && ul.querySelectorAll('li').length === 0) {
                var meta = getStateMeta();
                if (meta) {
                    var py = parseInt(meta.getAttribute('data-agenda-cal-year') || '0', 10);
                    var pm = parseInt(meta.getAttribute('data-agenda-cal-month') || '0', 10);
                    if (py && pm) {
                        addCustomMonthToList(py, pm);
                    }
                }
            }
        } else {
            if (scopeFieldset) {
                scopeFieldset.hidden = false;
            }
            if (calSection) {
                calSection.hidden = true;
            }
            var scopeEl = form.querySelector('input[name="scope"]:checked');
            var v = scopeEl ? scopeEl.value : '';
            box.hidden = v !== 'custom_months';
        }
    }

    function syncPrintMonthInputFromCalendar() {
        var meta = getStateMeta();
        var inp = document.getElementById('agendaCalPrintMonthInput');
        if (!meta || !inp) {
            return;
        }
        var y = meta.getAttribute('data-agenda-cal-year');
        var m = meta.getAttribute('data-agenda-cal-month');
        if (!y || !m) {
            return;
        }
        var mm = String(parseInt(m, 10));
        if (mm.length === 1) {
            mm = '0' + mm;
        }
        inp.value = y + '-' + mm;
    }

    function onPrintFormSubmit(e) {
        e.preventDefault();
        var page = getPage();
        var modal = getPrintModal();
        var form = document.getElementById('agendaCalFichasPrintForm');
        var errEl = document.getElementById('agendaCalPrintError');
        var submitBtn = document.getElementById('agendaCalPrintSubmit');
        if (!page || !form || !modal || modal.hidden) {
            return;
        }
        var pdfUrl = page.getAttribute('data-agenda-cal-fichas-pdf-url');
        if (!pdfUrl) {
            return;
        }
        var meta = getStateMeta();
        if (!meta) {
            if (errEl) {
                errEl.textContent = 'No se pudo leer el mes actual. Recarga la página.';
                errEl.hidden = false;
            }
            return;
        }

        var kg = form.querySelector('input[name="kind_gira"]');
        var kp = form.querySelector('input[name="kind_pre_gira"]');
        var ka = form.querySelector('input[name="kind_agenda"]');
        var kc = form.querySelector('input[name="kind_personalizada"]');
        if (errEl && kg && kp && ka && kc && !kg.checked && !kp.checked && !ka.checked && !kc.checked) {
            errEl.textContent = 'Selecciona al menos un tipo: Gira, Pre-gira, Agenda o Fichas personalizadas.';
            errEl.hidden = false;
            return;
        }

        var scopeEl = form.querySelector('input[name="scope"]:checked');
        var scope = scopeEl ? scopeEl.value : 'current_month';
        var templateEl = form.querySelector('input[name="template"]:checked');
        var template = templateEl ? templateEl.value : 'summary';

        if (template === 'calendar' && scope === 'all') {
            if (errEl) {
                errEl.textContent = 'El calendario mensual se genera por mes. Elige el mes actual o varios meses.';
                errEl.hidden = false;
            }
            return;
        }

        syncCustomMonthsHiddenFromList();

        if (scope === 'custom_months') {
            var hidM = document.getElementById('agendaCalCustomMonthsJson');
            var parsed = [];
            try {
                parsed = JSON.parse(hidM && hidM.value ? hidM.value : '[]');
            } catch (e1) {
                parsed = [];
            }
            if (!Array.isArray(parsed) || parsed.length === 0) {
                if (errEl) {
                    errEl.textContent = 'Agrega al menos un mes a la lista (meses elegidos).';
                    errEl.hidden = false;
                }
                return;
            }
        }

        var fd = new FormData(form);
        if (scope === 'current_month') {
            fd.append('year', meta.getAttribute('data-agenda-cal-year') || '');
            fd.append('month', meta.getAttribute('data-agenda-cal-month') || '');
        }
        fd.append('clasificacion', meta.getAttribute('data-agenda-cal-clasificacion') || '');
        fd.append('buscar', meta.getAttribute('data-agenda-cal-buscar') || '');
        fd.append('_token', getCsrfToken());

        if (errEl) {
            errEl.hidden = true;
            errEl.textContent = '';
        }
        if (submitBtn) {
            submitBtn.disabled = true;
        }

        setPrintModalOpen(false);
        showAgendaCalToast('Generando el PDF… Revisa notificaciones cuando esté listo.', 5200);

        fetch(pdfUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        })
            .then(function (res) {
                var ct = (res.headers.get('Content-Type') || '').split(';')[0].trim();
                if (res.status === 422 && ct === 'application/json') {
                    return res.json().then(function (data) {
                        throw new Error((data && data.message) || 'No se pudo generar el PDF.');
                    });
                }
                if (!res.ok) {
                    if (ct === 'application/json') {
                        return res.json().then(function (data) {
                            throw new Error((data && data.message) || 'Error al encolar el PDF (' + res.status + ').');
                        });
                    }
                    throw new Error('Error al generar el PDF (' + res.status + ').');
                }
                if (ct !== 'application/json') {
                    return res.text().then(function () {
                        throw new Error('Respuesta inesperada del servidor.');
                    });
                }
                return res.json();
            })
            .then(function (data) {
                if (!data || !data.queued) {
                    throw new Error('No se pudo iniciar la generación del PDF.');
                }
                if (typeof window.refreshSegobNotifications === 'function') {
                    setTimeout(function () {
                        window.refreshSegobNotifications();
                    }, 350);
                }
            })
            .catch(function (err) {
                setPrintModalOpen(true);
                showAgendaCalToast(err.message || 'No se pudo generar el PDF.', 5200);
                if (errEl) {
                    errEl.textContent = err.message || 'Error al generar el PDF.';
                    errEl.hidden = false;
                }
            })
            .finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            });
    }

    function initAgendaCalPrintFichas() {
        var page = getPage();
        if (!page) {
            return;
        }
        page.addEventListener('click', onOpenPrintFichasClick);

        var modal = getPrintModal();
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target.getAttribute('data-agenda-cal-print-close') != null) {
                    setPrintModalOpen(false);
                }
            });
        }

        var form = document.getElementById('agendaCalFichasPrintForm');
        if (form) {
            form.addEventListener('submit', onPrintFormSubmit);
            form.addEventListener('change', function (ev) {
                if (ev.target && (ev.target.name === 'scope' || ev.target.name === 'template')) {
                    updatePrintScopeUi();
                }
            });
        }
        var addBtn = document.getElementById('agendaCalPrintMonthAdd');
        var monthInp = document.getElementById('agendaCalPrintMonthInput');
        if (addBtn && monthInp) {
            addBtn.addEventListener('click', function () {
                var v = monthInp.value;
                if (!v || v.indexOf('-') === -1) {
                    return;
                }
                var parts = v.split('-');
                var yy = parseInt(parts[0], 10);
                var mm = parseInt(parts[1], 10);
                if (!yy || !mm) {
                    return;
                }
                addCustomMonthToList(yy, mm);
            });
        }
        var monthList = document.getElementById('agendaCalPrintMonthList');
        if (monthList) {
            monthList.addEventListener('click', function (ev) {
                var btn = ev.target.closest('.agenda-cal-print-month-remove');
                if (!btn || !monthList.contains(btn)) {
                    return;
                }
                var li = btn.closest('li');
                if (li && li.parentNode === monthList) {
                    li.remove();
                    syncCustomMonthsHiddenFromList();
                }
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') {
                return;
            }
            var m = getPrintModal();
            if (m && !m.hidden) {
                setPrintModalOpen(false);
            }
        });
    }

    function boot() {
        initAgendaCalendarTabsFirstLoad();
        initMonthNavAjax();
        initPopState();
        initAgendaCalPrintFichas();
        var page = getPage();
        if (page) {
            page.addEventListener('click', onCardDescMoreClick);
        }
        window.addEventListener('resize', onResizeCardDescMore);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
