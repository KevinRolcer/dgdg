(function () {
    var toggleButton = document.getElementById('wasPanelToggle');
    var panel = document.getElementById('wasRightPanel');
    var workspace = document.querySelector('.wa-workspace');

    if (toggleButton && panel && workspace) {
        toggleButton.addEventListener('click', function () {
            var isOpen = panel.classList.toggle('wa-panel--open');
            var isCollapsed = panel.classList.toggle('wa-panel--closed');
            workspace.classList.toggle('wa-workspace--panel-hidden', !isOpen && isCollapsed);
            toggleButton.setAttribute('aria-expanded', (!isCollapsed).toString());
        });
        panel.classList.add('wa-panel--open');
    }

    var tabButtons = document.querySelectorAll('.wa-panel-tab');
    tabButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            tabButtons.forEach(function (item) {
                item.classList.remove('wa-panel-tab--active');
                item.setAttribute('aria-selected', 'false');
            });
            button.classList.add('wa-panel-tab--active');
            button.setAttribute('aria-selected', 'true');

            var target = button.getAttribute('aria-controls');
            document.querySelectorAll('.wa-tab-content').forEach(function (pane) {
                var isTarget = pane.id === target;
                pane.classList.toggle('wa-tab-content--active', isTarget);
                if (isTarget) {
                    pane.removeAttribute('hidden');
                } else {
                    pane.setAttribute('hidden', '');
                }
            });
        });
    });
})();
