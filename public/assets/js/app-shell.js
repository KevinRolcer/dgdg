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
        if (topbarNotifyToggle && topbarNotifyPanel) {
            topbarNotifyToggle.setAttribute('aria-expanded', 'false');
            topbarNotifyPanel.classList.remove('is-open');
            topbarNotifyPanel.setAttribute('aria-hidden', 'true');
        }

        if (topbarProfileToggle && topbarProfilePanel) {
            topbarProfileToggle.setAttribute('aria-expanded', 'false');
            topbarProfilePanel.classList.remove('is-open');
            topbarProfilePanel.setAttribute('aria-hidden', 'true');
        }
    }

    function openNotificationsDrawer() {
        if (!notificationsDrawer || !notificationsDrawerBackdrop) {
            return;
        }

        notificationsDrawer.classList.add('is-open');
        notificationsDrawerBackdrop.classList.add('is-visible');
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
        notificationsDrawer.setAttribute('aria-hidden', 'true');
        if (topbarNotifyViewAll) {
            topbarNotifyViewAll.setAttribute('aria-expanded', 'false');
        }
    }

    var exportPollIntervals = {};

    function stopAllExportPolling() {
        Object.keys(exportPollIntervals).forEach(function (id) {
            clearInterval(exportPollIntervals[id]);
            delete exportPollIntervals[id];
        });
    }

    function startExportStatusPolling() {
        stopAllExportPolling();
        var urlTemplate = document.body.getAttribute('data-export-status-url');
        if (!urlTemplate) return;
        document.querySelectorAll('[data-export-request-id]').forEach(function (el) {
            var id = el.getAttribute('data-export-request-id');
            if (!id || exportPollIntervals[id]) return;
            var url = urlTemplate.replace(/\/0$/, '/' + id);
            var intervalId = setInterval(function () {
                fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data.status === 'completed' || data.status === 'failed') {
                            clearInterval(exportPollIntervals[id]);
                            delete exportPollIntervals[id];
                            refreshNotifications();
                        }
                    })
                    .catch(function () {});
            }, 4000);
            exportPollIntervals[id] = intervalId;
        });
    }

    function refreshNotifications() {
        if (window.__segobRefreshingNotifications) {
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

        var requestUrl = window.location.href.split('#')[0];

        fetch(requestUrl, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                return response.text();
            })
            .then(function (html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');

                var newTopList = doc.querySelector('#topbarNotifyPanel .topbar-notify-list');
                var currentTopList = document.querySelector('#topbarNotifyPanel .topbar-notify-list');
                if (newTopList && currentTopList) {
                    currentTopList.innerHTML = newTopList.innerHTML;
                }

                var newDrawerBody = doc.querySelector('.notifications-drawer-body');
                var currentDrawerBody = document.querySelector('.notifications-drawer-body');
                if (newDrawerBody && currentDrawerBody) {
                    currentDrawerBody.innerHTML = newDrawerBody.innerHTML;
                }

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
            });
    }

    if (document.body.getAttribute('data-export-status-url')) {
        startExportStatusPolling();
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

    if (topbarNotifyRefresh) {
        topbarNotifyRefresh.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            refreshNotifications();
        });
    }

    if (notificationsDrawerRefresh) {
        notificationsDrawerRefresh.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            refreshNotifications();
        });
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
        });
    });

    document.addEventListener('click', function (event) {
        var targetElement = event.target;

        if (
            targetElement instanceof Element
            && !targetElement.closest('#topbarNotifyToggle, #topbarNotifyPanel, #topbarProfileToggle, #topbarProfilePanel')
        ) {
            closeTopbarDropdowns();
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
            }, 2000);
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

    var agendaTooltipEl = null;
    function hideAgendaTooltip() {
        if (agendaTooltipEl && agendaTooltipEl.parentNode) {
            agendaTooltipEl.parentNode.removeChild(agendaTooltipEl);
        }
        agendaTooltipEl = null;
    }
    function showAgendaTooltip(dayCell, items) {
        hideAgendaTooltip();
        if (!items || !items.length) return;
        var el = document.createElement('div');
        el.className = 'calendar-agenda-tooltip';
        el.setAttribute('role', 'tooltip');
        items.forEach(function (it) {
            var row = document.createElement('div');
            row.className = 'calendar-agenda-tooltip-row';
            var t = document.createElement('div');
            t.className = 'calendar-agenda-tooltip-title';
            t.textContent = it.title || '';
            var time = document.createElement('div');
            time.className = 'calendar-agenda-tooltip-time';
            time.textContent = it.time || '';
            row.appendChild(t);
            row.appendChild(time);
            el.appendChild(row);
        });
        document.body.appendChild(el);
        el.style.position = 'fixed';
        el.style.zIndex = '10000';
        var rect = dayCell.getBoundingClientRect();
        var tw = el.offsetWidth;
        var th = el.offsetHeight;
        var left = rect.left + rect.width / 2 - tw / 2;
        var top = rect.top - th - 8;
        if (left < 8) left = 8;
        if (left + tw > window.innerWidth - 8) left = window.innerWidth - tw - 8;
        if (top < 8) top = rect.bottom + 8;
        el.style.left = left + 'px';
        el.style.top = top + 'px';
        agendaTooltipEl = el;
    }

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
            var dayEvents = agendaDaysMap[dayKey];
            var num = document.createElement('span');
            num.className = 'calendar-day-num';
            num.textContent = String(day);
            dayCell.appendChild(num);

            if (dayEvents && dayEvents.length) {
                (function (cell, list, y, m, d) {
                    cell.classList.add('has-agenda');
                    var dot = document.createElement('span');
                    var cellMidnight = new Date(y, m, d).setHours(0, 0, 0, 0);
                    var todayMidnight = new Date(today.getFullYear(), today.getMonth(), today.getDate()).setHours(0, 0, 0, 0);
                    var dotKind = 'future';
                    if (cellMidnight < todayMidnight) {
                        dotKind = 'past';
                    } else if (cellMidnight === todayMidnight) {
                        dotKind = 'today';
                    }
                    dot.className = 'calendar-agenda-dot calendar-agenda-dot--' + dotKind;
                    dot.setAttribute('aria-hidden', 'true');
                    cell.appendChild(dot);
                    cell.addEventListener('mouseenter', function () {
                        showAgendaTooltip(cell, list);
                    });
                    cell.addEventListener('mouseleave', hideAgendaTooltip);
                    cell.addEventListener('focus', function () {
                        showAgendaTooltip(cell, list);
                    });
                    cell.addEventListener('blur', hideAgendaTooltip);
                    cell.setAttribute('tabindex', '0');
                })(dayCell, dayEvents, year, month, day);
            }

            if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
                dayCell.classList.add('is-today');
            }

            calendarGrid.appendChild(dayCell);
        }
    }

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

    setCalendarView('upcoming');
    renderMonthGrid();
});
