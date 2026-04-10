document.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.getElementById('appSidebar');
    var overlay = document.getElementById('appOverlay');
    var menuToggle = document.getElementById('menuToggle');
    var sidebarCollapseToggle = document.getElementById('sidebarCollapseToggle');
    var topbarNotifyToggle = document.getElementById('topbarNotifyToggle');
    var topbarNotifyPanel = document.getElementById('topbarNotifyPanel');
    var topbarNotifyViewAll = document.getElementById('topbarNotifyViewAll');
    var topbarProfileToggle = document.getElementById('topbarProfileToggle');
    var topbarProfilePanel = document.getElementById('topbarProfilePanel');
    var notificationsDrawer = document.getElementById('notificationsDrawer');
    var notificationsDrawerClose = document.getElementById('notificationsDrawerClose');
    var notificationsDrawerBackdrop = document.getElementById('notificationsDrawerBackdrop');
    var topbarNotifyRefresh = document.getElementById('topbarNotifyRefresh');
    var notificationsDrawerRefresh = document.getElementById('notificationsDrawerRefresh');
    var suppressNextDrawerAutoClose = false;
    var collapseStorageKey = 'segob_sidebar_collapsed';
    var mobileBreakpoint = 768;

    function isMobileViewport() {
        return window.innerWidth <= mobileBreakpoint;
    }

    function setCollapsedState(collapsed) {
        if (isMobileViewport()) {
            document.body.classList.remove('sidebar-collapsed');
            return;
        }

        document.body.classList.toggle('sidebar-collapsed', collapsed);
        if (sidebarCollapseToggle) {
            sidebarCollapseToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            sidebarCollapseToggle.setAttribute('aria-label', collapsed ? 'Mostrar menú lateral' : 'Ocultar menú lateral');
        }
    }

    function loadCollapsedState() {
        var savedState = localStorage.getItem(collapseStorageKey) === '1';
        setCollapsedState(savedState);
    }

    function toggleCollapsedState() {
        var willCollapse = !document.body.classList.contains('sidebar-collapsed');
        setCollapsedState(willCollapse);
        localStorage.setItem(collapseStorageKey, willCollapse ? '1' : '0');

        if (willCollapse) {
            document.querySelectorAll('[data-submenu-toggle]').forEach(function (toggleButton) {
                toggleButton.setAttribute('aria-expanded', 'false');
            });
            document.querySelectorAll('.submenu.is-open').forEach(function (openSubmenu) {
                openSubmenu.classList.remove('is-open');
            });
        }
    }

    function closeAllSubmenus(exceptId) {
        document.querySelectorAll('[data-submenu-toggle]').forEach(function (toggleButton) {
            var targetId = toggleButton.getAttribute('data-submenu-toggle');
            var shouldKeepOpen = exceptId && targetId === exceptId;
            toggleButton.setAttribute('aria-expanded', shouldKeepOpen ? 'true' : 'false');
        });

        document.querySelectorAll('.submenu').forEach(function (submenuItem) {
            if (exceptId && submenuItem.id === exceptId) {
                return;
            }
            submenuItem.classList.remove('is-open');
        });
    }

    function openSidebar() {
        if (!sidebar || !overlay || !menuToggle) {
            return;
        }

        sidebar.classList.add('is-open');
        overlay.classList.add('is-visible');
        menuToggle.setAttribute('aria-expanded', 'true');
        overlay.setAttribute('aria-hidden', 'false');
    }

    function closeSidebar() {
        if (!sidebar || !overlay || !menuToggle) {
            return;
        }

        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-visible');
        menuToggle.setAttribute('aria-expanded', 'false');
        overlay.setAttribute('aria-hidden', 'true');
    }

    function closeTopbarDropdowns() {
        [
            [topbarNotifyToggle, topbarNotifyPanel],
            [topbarProfileToggle, topbarProfilePanel],
        ].forEach(function (pair) {
            var toggle = pair[0];
            var panel = pair[1];
            if (!toggle || !panel) {
                return;
            }
            toggle.setAttribute('aria-expanded', 'false');
            panel.classList.remove('is-open');
            panel.setAttribute('aria-hidden', 'true');
        });
    }

    function openNotificationsDrawer() {
        if (!notificationsDrawer || !notificationsDrawerBackdrop) {
            return;
        }

        notificationsDrawer.classList.add('is-open');
        notificationsDrawerBackdrop.classList.add('is-visible');
        document.body.classList.add('notifications-modal-open');
        notificationsDrawer.setAttribute('aria-hidden', 'false');
        if (topbarNotifyViewAll) {
            topbarNotifyViewAll.setAttribute('aria-expanded', 'true');
        }
    }

    function closeNotificationsDrawer() {
        if (!notificationsDrawer || !notificationsDrawerBackdrop) {
            return;
        }

        notificationsDrawer.classList.remove('is-open');
        notificationsDrawerBackdrop.classList.remove('is-visible');
        document.body.classList.remove('notifications-modal-open');
        notificationsDrawer.setAttribute('aria-hidden', 'true');
        if (topbarNotifyViewAll) {
            topbarNotifyViewAll.setAttribute('aria-expanded', 'false');
        }
    }

    var exportPollIntervals = {};
    var waImportPollIntervals = {};

    function stopAllWaImportPolling() {
        Object.keys(waImportPollIntervals).forEach(function (id) {
            clearInterval(waImportPollIntervals[id]);
            delete waImportPollIntervals[id];
        });
    }

    function updateWaImportNotificationRows(archiveId, progress, phase) {
        var pct = Math.min(100, Math.max(0, parseInt(progress, 10) || 0));
        var titleText = phase
            ? 'WhatsApp: ' + pct + '% — ' + phase
            : 'WhatsApp: ' + pct + '%';
        document.querySelectorAll('[data-whatsapp-import-archive-id="' + archiveId + '"]').forEach(function (row) {
            var bar = row.querySelector('.wa-notify-progress-bar');
            if (bar) {
                bar.style.width = pct + '%';
            }
            var progEl = row.querySelector('.wa-notify-progress');
            if (progEl) {
                progEl.setAttribute('aria-valuenow', String(pct));
            }
            var strong = row.querySelector('.wa-notify-import-title');
            if (strong) {
                strong.textContent = titleText;
            }
            var phaseLine = row.querySelector('.wa-notify-phase');
            if (phaseLine) {
                phaseLine.textContent = phase || '';
            }
        });
    }

    function startWhatsAppImportPolling() {
        stopAllWaImportPolling();
        var base = document.body.getAttribute('data-whatsapp-import-status-base');
        if (!base) {
            return;
        }
        var baseTrim = base.replace(/\/?$/, '');
        document.querySelectorAll('[data-whatsapp-import-archive-id]').forEach(function (el) {
            var id = el.getAttribute('data-whatsapp-import-archive-id');
            if (!id || waImportPollIntervals[id]) {
                return;
            }
            var url = baseTrim + '/' + id + '/import-status';
            var failCount = 0;
            function pollWaImportOnce() {
                if (!document.querySelector('[data-whatsapp-import-archive-id="' + id + '"]')) {
                    if (waImportPollIntervals[id]) {
                        clearInterval(waImportPollIntervals[id]);
                        delete waImportPollIntervals[id];
                    }
                    return;
                }
                fetch(url, {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (res) {
                        if (res.status === 401 || res.status === 403) {
                            if (waImportPollIntervals[id]) {
                                clearInterval(waImportPollIntervals[id]);
                                delete waImportPollIntervals[id];
                            }
                            return Promise.reject(new Error('unauthorized'));
                        }
                        if (!res.ok) {
                            failCount++;
                            if (failCount >= 5 && waImportPollIntervals[id]) {
                                clearInterval(waImportPollIntervals[id]);
                                delete waImportPollIntervals[id];
                            }
                            return Promise.reject(new Error('http'));
                        }
                        failCount = 0;
                        return res.json();
                    })
                    .then(function (data) {
                        if (!data) {
                            return;
                        }
                        updateWaImportNotificationRows(id, data.progress, data.phase);
                        if (data.done) {
                            if (waImportPollIntervals[id]) {
                                clearInterval(waImportPollIntervals[id]);
                                delete waImportPollIntervals[id];
                            }
                            refreshNotifications();
                        }
                    })
                    .catch(function () {
                        failCount++;
                        if (failCount >= 5 && waImportPollIntervals[id]) {
                            clearInterval(waImportPollIntervals[id]);
                            delete waImportPollIntervals[id];
                        }
                    });
            }
            pollWaImportOnce();
            waImportPollIntervals[id] = setInterval(pollWaImportOnce, 3000);
        });
    }

    function stopAllExportPolling() {
        Object.keys(exportPollIntervals).forEach(function (id) {
            clearInterval(exportPollIntervals[id]);
            delete exportPollIntervals[id];
        });
    }

    function buildExportStatusUrl(template, exportId) {
        return template.replace(/\/0$/, '/' + exportId);
    }

    /**
     * Prueba varias plantillas de URL (poller corto + ruta larga) por si el hosting devuelve 404 en una.
     */
    function fetchExportStatusWithFallback(templates, exportId, templateIndex) {
        templateIndex = templateIndex || 0;
        if (templateIndex >= templates.length) {
            return Promise.reject(new Error('no-templates'));
        }
        var url = buildExportStatusUrl(templates[templateIndex], exportId);
        return fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (res.status === 404 && templateIndex + 1 < templates.length) {
                return fetchExportStatusWithFallback(templates, exportId, templateIndex + 1);
            }
            return res;
        });
    }

    function startExportStatusPolling() {
        stopAllExportPolling();
        var multiRaw = document.body.getAttribute('data-export-status-urls');
        var urlTemplates = [];
        if (multiRaw) {
            try {
                var parsed = JSON.parse(multiRaw);
                if (Array.isArray(parsed)) {
                    urlTemplates = parsed.filter(function (t) { return t && typeof t === 'string'; });
                }
            } catch (eMulti) { /* ignore */ }
        }
        if (!urlTemplates.length) {
            var single = document.body.getAttribute('data-export-status-url');
            if (single) urlTemplates = [single];
        }
        if (!urlTemplates.length) return;

        document.querySelectorAll('[data-export-request-id]').forEach(function (el) {
            var id = el.getAttribute('data-export-request-id');
            if (!id || exportPollIntervals[id]) return;
            var failCount = 0;
            var intervalId = setInterval(function () {
                fetchExportStatusWithFallback(urlTemplates, id, 0)
                    .then(function (res) {
                        if (res.status === 401 || res.status === 403) {
                            clearInterval(exportPollIntervals[id]);
                            delete exportPollIntervals[id];
                            return Promise.reject(new Error('unauthorized'));
                        }
                        if (res.status === 404) {
                            clearInterval(exportPollIntervals[id]);
                            delete exportPollIntervals[id];
                            return { status: 'gone' };
                        }
                        if (!res.ok) {
                            return Promise.reject(new Error('http'));
                        }
                        failCount = 0;
                        return res.json();
                    })
                    .then(function (data) {
                        if (!data) return;
                        if (data.status === 'gone') {
                            clearInterval(exportPollIntervals[id]);
                            delete exportPollIntervals[id];
                            refreshNotifications();
                            return;
                        }
                        if (data.status === 'completed' || data.status === 'failed') {
                            clearInterval(exportPollIntervals[id]);
                            delete exportPollIntervals[id];
                            refreshNotifications();
                        }
                    })
                    .catch(function (err) {
                        if (err && err.message === 'unauthorized') {
                            return;
                        }
                        failCount++;
                        if (failCount >= 3) {
                            clearInterval(exportPollIntervals[id]);
                            delete exportPollIntervals[id];
                        }
                    });
            }, 4000);
            exportPollIntervals[id] = intervalId;
        });
    }

    function refreshNotifications() {
        if (window.__segobRefreshingNotifications) {
            window.__segobPendingNotificationRefresh = true;
            return;
        }

        var refreshButtons = [];
        if (topbarNotifyRefresh) {
            refreshButtons.push(topbarNotifyRefresh);
        }
        if (notificationsDrawerRefresh) {
            refreshButtons.push(notificationsDrawerRefresh);
        }

        window.__segobRefreshingNotifications = true;
        refreshButtons.forEach(function (btn) {
            btn.classList.add('is-loading');
            btn.disabled = true;
        });

        var pageUrl = window.location.href.split('#')[0];
        var requestUrl;
        try {
            var ru = new URL(pageUrl, window.location.origin);
            ru.searchParams.set('_nc', String(Date.now()));
            requestUrl = ru.toString();
        } catch (eUrl) {
            requestUrl = pageUrl + (pageUrl.indexOf('?') === -1 ? '?' : '&') + '_nc=' + Date.now();
        }

        // Sin X-Requested-With: si la sesión expiró, Laravel redirige al login (302) en lugar de 401 JSON;
        // la cookie de sesión sigue yendo en same-origin igual que una navegación normal.
        fetch(requestUrl, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8'
            }
        })
            .then(function (response) {
                if (response.status === 401 || response.status === 403) {
                    return Promise.reject(new Error('unauthorized'));
                }
                return response.text();
            })
            .then(function (html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');

                function syncInnerHtmlFromParsedDoc(selector) {
                    var nextEl = doc.querySelector(selector);
                    var curEl = document.querySelector(selector);
                    if (nextEl && curEl) {
                        curEl.innerHTML = nextEl.innerHTML;
                    }
                }

                syncInnerHtmlFromParsedDoc('#topbarNotifyPanel .topbar-notify-list');
                syncInnerHtmlFromParsedDoc('.notifications-drawer-body');

                var newDot = doc.querySelector('.topbar-notify-dot');
                var currentDot = document.querySelector('.topbar-notify-dot');
                var notifyButton = topbarNotifyToggle;

                if (notifyButton) {
                    if (newDot && !currentDot) {
                        var dot = document.createElement('span');
                        dot.className = 'topbar-notify-dot';
                        dot.setAttribute('aria-hidden', 'true');
                        notifyButton.appendChild(dot);
                    } else if (!newDot && currentDot) {
                        currentDot.remove();
                    }
                }
                startExportStatusPolling();
                startWhatsAppImportPolling();
            })
            .catch(function () {
                // opcional: podríamos mostrar un toast de error si existe swal
                if (typeof window.swal === 'function') {
                    window.swal('Error', 'No se pudieron recargar las notificaciones.', 'error');
                }
            })
            .finally(function () {
                window.__segobRefreshingNotifications = false;
                refreshButtons.forEach(function (btn) {
                    btn.classList.remove('is-loading');
                    btn.disabled = false;
                });
                if (window.__segobPendingNotificationRefresh) {
                    window.__segobPendingNotificationRefresh = false;
                    setTimeout(function () {
                        refreshNotifications();
                    }, 0);
                }
            });
    }

    window.refreshSegobNotifications = refreshNotifications;

    if (document.body.getAttribute('data-export-status-url')) {
        startExportStatusPolling();
    }

    if (document.body.getAttribute('data-whatsapp-import-status-base')) {
        startWhatsAppImportPolling();
    }

    function toggleTopbarDropdown(toggleButton, panelElement) {
        if (!toggleButton || !panelElement) {
            return;
        }

        var willOpen = toggleButton.getAttribute('aria-expanded') !== 'true';
        closeTopbarDropdowns();

        if (willOpen) {
            toggleButton.setAttribute('aria-expanded', 'true');
            panelElement.classList.add('is-open');
            panelElement.setAttribute('aria-hidden', 'false');
        }
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', function () {
            var isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
            if (isExpanded) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function () {
            closeSidebar();
            closeAllSubmenus();
            closeTopbarDropdowns();
        });
    }

    if (topbarNotifyToggle && topbarNotifyPanel) {
        topbarNotifyToggle.addEventListener('click', function (event) {
            event.stopPropagation();
            toggleTopbarDropdown(topbarNotifyToggle, topbarNotifyPanel);
        });
    }

    if (topbarProfileToggle && topbarProfilePanel) {
        topbarProfileToggle.addEventListener('click', function (event) {
            event.stopPropagation();
            toggleTopbarDropdown(topbarProfileToggle, topbarProfilePanel);
        });
    }

    if (topbarNotifyViewAll) {
        topbarNotifyViewAll.addEventListener('click', function () {
            closeTopbarDropdowns();
            openNotificationsDrawer();
        });
    }

    if (notificationsDrawerClose) {
        notificationsDrawerClose.addEventListener('click', function () {
            closeNotificationsDrawer();
        });
    }

    function onNotificationsRefreshClick(event) {
        event.preventDefault();
        event.stopPropagation();
        refreshNotifications();
    }

    if (topbarNotifyRefresh) {
        topbarNotifyRefresh.addEventListener('click', onNotificationsRefreshClick);
    }

    if (notificationsDrawerRefresh) {
        notificationsDrawerRefresh.addEventListener('click', onNotificationsRefreshClick);
    }

    document.querySelectorAll('form[data-notifications-clear]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            event.stopPropagation();

            var submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
            }

            var formData = new FormData(form);

            fetch(form.action, {
                method: form.method || 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
                .then(function () {
                    refreshNotifications();
                })
                .catch(function () {
                    if (typeof window.swal === 'function') {
                        window.swal('Error', 'No se pudieron vaciar las notificaciones.', 'error');
                    }
                })
                .finally(function () {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                });
        });
    });

    // Delegación para eliminar notificación individual
    document.addEventListener('click', function (event) {
        var deleteBtn = event.target.closest('.topbar-notify-item-delete');
        if (deleteBtn) {
            event.preventDefault();
            event.stopPropagation();
            suppressNextDrawerAutoClose = true;

            var form = deleteBtn.closest('form');
            if (!form) return;

            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>';

            var formData = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
                .then(function () {
                    refreshNotifications();
                })
                .catch(function () {
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = '<i class="fa-solid fa-trash-can" aria-hidden="true"></i>';
                    if (typeof window.swal === 'function') {
                        window.swal('Error', 'No se pudo eliminar la notificación.', 'error');
                    }
                })
                .finally(function () {
                    setTimeout(function () {
                        suppressNextDrawerAutoClose = false;
                    }, 0);
                });
        }
    });

    if (notificationsDrawerBackdrop) {
        notificationsDrawerBackdrop.addEventListener('click', function () {
            closeNotificationsDrawer();
        });
    }

    if (sidebarCollapseToggle) {
        sidebarCollapseToggle.addEventListener('click', function () {
            toggleCollapsedState();
        });
    }

    document.querySelectorAll('[data-submenu-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var targetId = button.getAttribute('data-submenu-toggle');
            if (!targetId) {
                return;
            }

            var submenu = document.getElementById(targetId);
            if (!submenu) {
                return;
            }

            var isExpanded = button.getAttribute('aria-expanded') === 'true';

            if (isExpanded) {
                button.setAttribute('aria-expanded', 'false');
                submenu.classList.remove('is-open');
                return;
            }

            closeAllSubmenus(targetId);
            submenu.classList.add('is-open');
            button.setAttribute('aria-expanded', 'true');
        });
    });

    document.addEventListener('click', function (event) {
        var targetElement = event.target;

        if (suppressNextDrawerAutoClose) {
            return;
        }

        if (
            targetElement instanceof Element
            && !targetElement.closest('#topbarNotifyToggle, #topbarNotifyPanel, #topbarProfileToggle, #topbarProfilePanel')
        ) {
            closeTopbarDropdowns();
        }

        if (
            targetElement instanceof Element
            && !targetElement.closest('#notificationsDrawer, #topbarNotifyViewAll, #notificationsDrawerRefresh')
        ) {
            closeNotificationsDrawer();
        }

        if (targetElement instanceof Element && targetElement.closest('[data-submenu-toggle], .submenu')) {
            return;
        }

        if (!isMobileViewport() && document.body.classList.contains('sidebar-collapsed')) {
            closeAllSubmenus();
        }
    });

    window.addEventListener('resize', function () {
        closeTopbarDropdowns();
        closeNotificationsDrawer();

        if (!isMobileViewport()) {
            closeSidebar();
            loadCollapsedState();
            var existingCalendarCard = document.getElementById('calendarCard');
            var existingCalendarBackdrop = document.getElementById('calendarMobileBackdrop');
            var existingCalendarToggle = document.getElementById('calendarDrawerToggle');
            if (existingCalendarCard) {
                existingCalendarCard.classList.remove('is-open');
            }
            if (existingCalendarBackdrop) {
                existingCalendarBackdrop.classList.remove('is-visible');
            }
            if (existingCalendarToggle) {
                existingCalendarToggle.classList.remove('is-open');
                existingCalendarToggle.setAttribute('aria-expanded', 'false');
            }
            return;
        }

        document.body.classList.remove('sidebar-collapsed');
        closeAllSubmenus();
    });

    loadCollapsedState();

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeTopbarDropdowns();
            closeNotificationsDrawer();
        }
    });

    var toastElement = document.getElementById('appToast');
    if (toastElement) {
        requestAnimationFrame(function () {
            toastElement.classList.add('is-visible');
            setTimeout(function () {
                toastElement.classList.remove('is-visible');
            }, 1500);
        });
    }

    var calendarCard = document.getElementById('calendarCard');
    if (!calendarCard) {
        return;
    }

    var viewButtons = calendarCard.querySelectorAll('[data-calendar-view]');
    var viewPanels = calendarCard.querySelectorAll('[data-calendar-panel]');
    var calendarGrid = document.getElementById('calendarGrid');
    var calendarMonthLabel = document.getElementById('calendarMonthLabel');
    var calendarPrev = document.getElementById('calendarPrev');
    var calendarNext = document.getElementById('calendarNext');
    var calendarDrawerToggle = document.getElementById('calendarDrawerToggle');
    var calendarDrawerClose = document.getElementById('calendarDrawerClose');
    var calendarMobileBackdrop = document.getElementById('calendarMobileBackdrop');
    var today = new Date();
    var currentMonthDate = new Date(today.getFullYear(), today.getMonth(), 1);

    var agendaDaysMap = {};
    try {
        var jsonEl = document.getElementById('homeCalendarAgendaDays');
        var raw = (jsonEl && jsonEl.textContent) ? jsonEl.textContent.trim() : (calendarCard.getAttribute('data-agenda-days') || '{}');
        agendaDaysMap = JSON.parse(raw);
        if (!agendaDaysMap || typeof agendaDaysMap !== 'object') {
            agendaDaysMap = {};
        }
    } catch (e) {
        agendaDaysMap = {};
    }

    var noteDaysMap = {};
    try {
        var noteJsonEl = document.getElementById('homeCalendarNoteDays');
        var noteRaw = (noteJsonEl && noteJsonEl.textContent) ? noteJsonEl.textContent.trim() : '{}';
        noteDaysMap = JSON.parse(noteRaw);
        if (!noteDaysMap || typeof noteDaysMap !== 'object' || Array.isArray(noteDaysMap)) {
            noteDaysMap = {};
        }
    } catch (e2) {
        noteDaysMap = {};
    }

    var personalAgendaUrl = calendarCard.getAttribute('data-personal-agenda-url') || '';

    var agendaTooltipEl = null;
    var agendaTooltipHideTimer = null;

    function cancelAgendaTooltipHide() {
        if (agendaTooltipHideTimer) {
            clearTimeout(agendaTooltipHideTimer);
            agendaTooltipHideTimer = null;
        }
    }

    function scheduleHideAgendaTooltip() {
        cancelAgendaTooltipHide();
        agendaTooltipHideTimer = setTimeout(function () {
            agendaTooltipHideTimer = null;
            hideAgendaTooltip();
        }, 200);
    }

    function hideAgendaTooltip() {
        cancelAgendaTooltipHide();
        if (agendaTooltipEl && agendaTooltipEl.parentNode) {
            agendaTooltipEl.parentNode.removeChild(agendaTooltipEl);
        }
        agendaTooltipEl = null;
    }

    function positionHomeCalendarTooltip(el, dayCell) {
        el.style.position = 'fixed';
        el.style.zIndex = '10000';
        var rect = dayCell.getBoundingClientRect();
        var tw = el.offsetWidth;
        var th = el.offsetHeight;
        var edgeGap = 2;
        var left = rect.left + rect.width / 2 - tw / 2;
        var top = rect.top - th - edgeGap;
        if (left < 8) left = 8;
        if (left + tw > window.innerWidth - 8) left = window.innerWidth - tw - 8;
        if (top < 8) top = rect.bottom + edgeGap;
        el.style.left = left + 'px';
        el.style.top = top + 'px';
    }

    function showHomeCalendarTooltip(dayCell, rows) {
        hideAgendaTooltip();
        if (!rows || !rows.length) return;
        var hasNotes = rows.some(function (r) { return r.kind === 'note'; });
        var el = document.createElement('div');
        el.className = 'calendar-agenda-tooltip' + (hasNotes ? ' calendar-agenda-tooltip--interactive' : '');
        el.setAttribute('role', 'tooltip');

        rows.forEach(function (row) {
            if (row.kind === 'note' && row.payload) {
                var rowEl = document.createElement('div');
                rowEl.className = 'calendar-agenda-tooltip-row';
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'calendar-agenda-tooltip-note-title';
                btn.textContent = row.payload.title || 'Sin título';
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    hideAgendaTooltip();
                    if (typeof window.previewNoteFromHomeCalendar === 'function') {
                        window.previewNoteFromHomeCalendar(row.payload);
                    }
                });
                rowEl.appendChild(btn);
                el.appendChild(rowEl);
            } else {
                var rowEl2 = document.createElement('div');
                rowEl2.className = 'calendar-agenda-tooltip-row';
                var t = document.createElement('div');
                t.className = 'calendar-agenda-tooltip-title';
                t.textContent = row.title || '';
                var time = document.createElement('div');
                time.className = 'calendar-agenda-tooltip-time';
                time.textContent = row.time || '';
                rowEl2.appendChild(t);
                rowEl2.appendChild(time);
                el.appendChild(rowEl2);
            }
        });

        if (hasNotes) {
            el.addEventListener('mouseenter', cancelAgendaTooltipHide);
            el.addEventListener('mouseleave', scheduleHideAgendaTooltip);
        }

        document.body.appendChild(el);
        positionHomeCalendarTooltip(el, dayCell);
        agendaTooltipEl = el;
    }

    function normalizeAgendaDayItems(raw) {
        if (!raw || !raw.length) return [];
        return raw.map(function (it) {
            return {
                title: it.title || '',
                time: it.time || '',
                kind: it.kind || 'agenda',
            };
        });
    }

    function mergeHomeCalendarTooltipRows(agendaItems, notePayloads) {
        var rows = [];
        agendaItems.forEach(function (it) {
            rows.push({ kind: 'agenda', title: it.title, time: it.time });
        });
        notePayloads.forEach(function (p) {
            rows.push({ kind: 'note', payload: p });
        });
        return rows;
    }

    var homeCalendarCtxMenuEl = null;

    function hideHomeCalendarCtxMenu() {
        if (homeCalendarCtxMenuEl && homeCalendarCtxMenuEl.parentNode) {
            homeCalendarCtxMenuEl.parentNode.removeChild(homeCalendarCtxMenuEl);
        }
        homeCalendarCtxMenuEl = null;
    }

    function showHomeCalendarContextMenu(clientX, clientY, dayKey) {
        hideHomeCalendarCtxMenu();
        var menu = document.createElement('div');
        menu.className = 'home-calendar-ctx-menu';
        menu.setAttribute('role', 'menu');

        function addItem(label, onActivate) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'home-calendar-ctx-menu-item';
            b.textContent = label;
            b.setAttribute('role', 'menuitem');
            b.addEventListener('click', function (ev) {
                ev.stopPropagation();
                hideHomeCalendarCtxMenu();
                onActivate();
            });
            menu.appendChild(b);
        }

        addItem('Crear nota personal', function () {
            if (typeof window.openPersonalNoteModal === 'function') {
                window.openPersonalNoteModal({ scheduled_date: dayKey });
                return;
            }
            if (personalAgendaUrl) {
                window.location.href = personalAgendaUrl;
            }
        });

        addItem('Ir a agenda personal', function () {
            var base = personalAgendaUrl || (window.location.origin + '/personal-agenda');
            var hash = 'filter=calendar&tab=month&calendar_date=' + encodeURIComponent(dayKey);
            window.location.href = base.split('#')[0] + '#' + hash;
        });

        document.body.appendChild(menu);
        menu.style.position = 'fixed';
        menu.style.left = clientX + 'px';
        menu.style.top = clientY + 'px';
        menu.style.zIndex = '10001';
        homeCalendarCtxMenuEl = menu;

        requestAnimationFrame(function () {
            var r = menu.getBoundingClientRect();
            var x = clientX;
            var y = clientY;
            if (r.right > window.innerWidth - 8) x = Math.max(8, clientX - r.width);
            if (r.bottom > window.innerHeight - 8) y = Math.max(8, clientY - r.height);
            menu.style.left = x + 'px';
            menu.style.top = y + 'px';
        });
    }

    document.addEventListener('click', function (e) {
        if (homeCalendarCtxMenuEl && !e.target.closest('.home-calendar-ctx-menu')) {
            hideHomeCalendarCtxMenu();
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            hideHomeCalendarCtxMenu();
        }
    });
    document.addEventListener('scroll', function () {
        hideHomeCalendarCtxMenu();
    }, true);

    function openCalendarDrawer() {
        if (!calendarCard || !calendarMobileBackdrop) {
            return;
        }
        if (isMobileViewport()) {
            closeSidebar();
        }
        calendarCard.classList.add('is-open');
        calendarMobileBackdrop.classList.add('is-visible');
        if (calendarDrawerToggle) {
            calendarDrawerToggle.classList.add('is-hidden');
            calendarDrawerToggle.setAttribute('aria-expanded', 'true');
        }
    }

    function closeCalendarDrawer() {
        if (!calendarCard || !calendarMobileBackdrop) {
            return;
        }
        calendarCard.classList.remove('is-open');
        calendarMobileBackdrop.classList.remove('is-visible');
        if (calendarDrawerToggle) {
            calendarDrawerToggle.classList.remove('is-hidden');
            calendarDrawerToggle.setAttribute('aria-expanded', 'false');
        }
    }

    function setCalendarView(view) {
        viewButtons.forEach(function (button) {
            button.classList.toggle('is-active', button.getAttribute('data-calendar-view') === view);
        });

        viewPanels.forEach(function (panel) {
            panel.classList.toggle('is-active', panel.getAttribute('data-calendar-panel') === view);
        });
    }

    function renderMonthGrid() {
        if (!calendarGrid || !calendarMonthLabel) {
            return;
        }

        var year = currentMonthDate.getFullYear();
        var month = currentMonthDate.getMonth();
        var firstDayOfMonth = new Date(year, month, 1);
        var lastDayOfMonth = new Date(year, month + 1, 0);
        var startOffset = (firstDayOfMonth.getDay() + 6) % 7;
        var daysInMonth = lastDayOfMonth.getDate();

        calendarMonthLabel.textContent = firstDayOfMonth.toLocaleDateString('es-MX', {
            month: 'long',
            year: 'numeric',
        });

        calendarGrid.innerHTML = '';

        for (var i = 0; i < startOffset; i++) {
            var emptyCell = document.createElement('span');
            emptyCell.className = 'calendar-day is-empty';
            emptyCell.setAttribute('aria-hidden', 'true');
            calendarGrid.appendChild(emptyCell);
        }

        function pad(n) {
            return n < 10 ? '0' + n : String(n);
        }

        for (var day = 1; day <= daysInMonth; day++) {
            var dayCell = document.createElement('span');
            dayCell.className = 'calendar-day';
            var dayKey = year + '-' + pad(month + 1) + '-' + pad(day);
            var agendaItems = normalizeAgendaDayItems(agendaDaysMap[dayKey]);
            var noteItems = noteDaysMap[dayKey] || [];
            var hasDirectiva = agendaItems.length > 0;
            var tooltipRows = mergeHomeCalendarTooltipRows(agendaItems, noteItems);

            var num = document.createElement('span');
            num.className = 'calendar-day-num';
            num.textContent = String(day);
            dayCell.appendChild(num);

            if (tooltipRows.length) {
                (function (cell, rows, y, m, d, dk, notesForDots, directiva) {
                    var cellMidnight = new Date(y, m, d).setHours(0, 0, 0, 0);
                    var todayMidnight = new Date(today.getFullYear(), today.getMonth(), today.getDate()).setHours(0, 0, 0, 0);
                    var dotKind = 'future';
                    if (cellMidnight < todayMidnight) {
                        dotKind = 'past';
                    } else if (cellMidnight === todayMidnight) {
                        dotKind = 'today';
                    }

                    cell.classList.add('has-cal-tooltip');

                    if (directiva) {
                        cell.classList.add('has-agenda');
                        var dot = document.createElement('span');
                        dot.className = 'calendar-agenda-dot calendar-agenda-dot--' + dotKind;
                        dot.setAttribute('aria-hidden', 'true');
                        cell.appendChild(dot);
                    }

                    if (notesForDots && notesForDots.length) {
                        cell.classList.add('has-pa-notes');
                        var dotsWrap = document.createElement('span');
                        dotsWrap.className = 'calendar-day-dots';
                        var cap = 4;
                        for (var ni = 0; ni < Math.min(notesForDots.length, cap); ni++) {
                            var nd = document.createElement('span');
                            nd.className = 'calendar-pa-note-dot calendar-agenda-dot--' + dotKind;
                            var col = notesForDots[ni].dot_color || notesForDots[ni].color;
                            if (col) {
                                nd.style.background = col;
                                nd.style.borderColor = col;
                            }
                            nd.setAttribute('aria-hidden', 'true');
                            dotsWrap.appendChild(nd);
                        }
                        var overflowCount = notesForDots.length - cap;
                        if (overflowCount > 0) {
                            var plusEl = document.createElement('span');
                            plusEl.className = 'calendar-pa-note-overflow';
                            plusEl.textContent = '+' + overflowCount;
                            plusEl.setAttribute('title', overflowCount + ' nota' + (overflowCount === 1 ? '' : 's') + ' más');
                            dotsWrap.appendChild(plusEl);
                        }
                        cell.appendChild(dotsWrap);
                    }

                    function showTip() {
                        cancelAgendaTooltipHide();
                        showHomeCalendarTooltip(cell, rows);
                    }

                    cell.addEventListener('mouseenter', showTip);
                    cell.addEventListener('mouseleave', scheduleHideAgendaTooltip);
                    cell.addEventListener('focus', showTip);
                    cell.addEventListener('blur', hideAgendaTooltip);
                    cell.setAttribute('tabindex', '0');
                })(dayCell, tooltipRows, year, month, day, dayKey, noteItems, hasDirectiva);
            }

            (function (cell, dk) {
                cell.addEventListener('contextmenu', function (e) {
                    e.preventDefault();
                    showHomeCalendarContextMenu(e.clientX, e.clientY, dk);
                });
            })(dayCell, dayKey);

            if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
                dayCell.classList.add('is-today');
            }

            calendarGrid.appendChild(dayCell);
        }
    }

    window.segobRenderHomeCalendarMonth = renderMonthGrid;

    window.addEventListener('segob:home-calendar-notes-patch', function (ev) {
        var d = ev.detail;
        if (!d || !calendarGrid) {
            return;
        }
        var nid = d.noteId;
        if (d.action === 'remove' && nid != null) {
            Object.keys(noteDaysMap).forEach(function (k) {
                var arr = noteDaysMap[k] || [];
                noteDaysMap[k] = arr.filter(function (n) {
                    return Number(n.id) !== Number(nid);
                });
                if (noteDaysMap[k].length === 0) {
                    delete noteDaysMap[k];
                }
            });
        } else if (d.action === 'upsert' && d.entry && d.dayKey) {
            Object.keys(noteDaysMap).forEach(function (k) {
                var arr = noteDaysMap[k] || [];
                noteDaysMap[k] = arr.filter(function (n) {
                    return Number(n.id) !== Number(nid);
                });
                if (noteDaysMap[k].length === 0) {
                    delete noteDaysMap[k];
                }
            });
            if (!noteDaysMap[d.dayKey]) {
                noteDaysMap[d.dayKey] = [];
            }
            noteDaysMap[d.dayKey].push(d.entry);
        } else {
            return;
        }
        hideAgendaTooltip();
        hideHomeCalendarCtxMenu();
        renderMonthGrid();
    });

    viewButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var view = button.getAttribute('data-calendar-view') || 'month';
            setCalendarView(view);
        });
    });

    if (calendarPrev) {
        calendarPrev.addEventListener('click', function () {
            currentMonthDate = new Date(currentMonthDate.getFullYear(), currentMonthDate.getMonth() - 1, 1);
            renderMonthGrid();
        });
    }

    if (calendarNext) {
        calendarNext.addEventListener('click', function () {
            currentMonthDate = new Date(currentMonthDate.getFullYear(), currentMonthDate.getMonth() + 1, 1);
            renderMonthGrid();
        });
    }

    if (calendarDrawerToggle) {
        calendarDrawerToggle.addEventListener('click', function () {
            var isOpen = calendarCard.classList.contains('is-open');
            if (isOpen) {
                closeCalendarDrawer();
                return;
            }

            openCalendarDrawer();
        });
    }

    if (calendarMobileBackdrop) {
        calendarMobileBackdrop.addEventListener('click', function () {
            closeCalendarDrawer();
        });
    }

    if (calendarDrawerClose) {
        calendarDrawerClose.addEventListener('click', function () {
            closeCalendarDrawer();
        });
    }

    setCalendarView('month');
    renderMonthGrid();
});
