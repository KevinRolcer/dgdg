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
            }, 1200);
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

    function openCalendarDrawer() {
        if (!calendarCard || !calendarMobileBackdrop || !isMobileViewport()) {
            return;
        }

        closeSidebar();
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

        for (var day = 1; day <= daysInMonth; day++) {
            var dayCell = document.createElement('span');
            dayCell.className = 'calendar-day';
            dayCell.textContent = String(day);

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

    setCalendarView('month');
    renderMonthGrid();
});
