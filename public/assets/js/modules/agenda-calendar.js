/**
 * Agenda calendario: pestañas Mes | Eventos | Fichas y cambio de mes vía AJAX (sin recargar la página).
 */
(function () {
    'use strict';

    var ACTIVE_TAB_KEY = 'agendaCalActiveTab';

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
        try {
            sessionStorage.setItem(ACTIVE_TAB_KEY, name);
        } catch (e) {
            /* ignore */
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
        var saved = null;
        try {
            saved = sessionStorage.getItem(ACTIVE_TAB_KEY);
        } catch (e) {
            saved = null;
        }
        if (saved && ['mes', 'lista', 'fichas'].indexOf(saved) !== -1) {
            activateTabInScope(ajaxRoot, saved);
        } else {
            refreshAgendaCalCardDescMore(ajaxRoot);
        }
    }

    function appendPartialParam(url) {
        try {
            var u = new URL(url, window.location.origin);
            u.searchParams.set('partial', '1');
            return u.toString();
        } catch (e) {
            return url + (url.indexOf('?') === -1 ? '?' : '&') + 'partial=1';
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
                activateTabInScope(ajaxRoot, tabBefore);
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
                        history.pushState({ agendaCal: true }, '', stripPartialFromUrl(fullUrl));
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

    function boot() {
        initAgendaCalendarTabsFirstLoad();
        initMonthNavAjax();
        initPopState();
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
