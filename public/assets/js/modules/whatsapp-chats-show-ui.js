(function () {
    'use strict';

    function initPanelUI(workspace) {
        var toggleButton = document.getElementById('wasPanelToggle');
        var panel = document.getElementById('wasRightPanel');

        if (!toggleButton || !panel || !workspace) {
            return;
        }

        // Inicialmente: panel oculto (filtros escondidos)
        panel.classList.add('wa-panel--closed');
        panel.classList.remove('wa-panel--open');
        workspace.classList.add('wa-workspace--panel-hidden');
        toggleButton.setAttribute('aria-expanded', 'false');

        toggleButton.addEventListener('click', function () {
            var isClosed = panel.classList.toggle('wa-panel--closed');
            panel.classList.toggle('wa-panel--open', !isClosed);
            workspace.classList.toggle('wa-workspace--panel-hidden', isClosed);
            toggleButton.setAttribute('aria-expanded', (!isClosed).toString());
        });

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
                if (!target) {
                    return;
                }
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
    }

    function initSidebarSearch() {
        var sideSearch = document.getElementById('waSidebarSearchInput');
        var sideList = document.getElementById('waSidebarList');
        if (!sideSearch || !sideList) {
            return;
        }

        var sideItems = Array.from(sideList.querySelectorAll('.wa-chatlist-item'));
        var sideTimer;

        function applySideFilter() {
            var q = (sideSearch.value || '').toLowerCase().trim();
            sideItems.forEach(function (el) {
                var title = (el.getAttribute('data-title') || '').toLowerCase();
                el.hidden = !!q && title.indexOf(q) === -1;
            });
        }

        sideSearch.addEventListener('input', function () {
            clearTimeout(sideTimer);
            sideTimer = setTimeout(applySideFilter, 120);
        });
    }

    function initBrowserAjaxSwitch(root, workspace) {
        if (!root || root.getAttribute('data-wa-preview-mode') !== 'browser') {
            return;
        }

        var list = document.getElementById('waSidebarList');
        if (!list) {
            return;
        }

        var inflight = null;

        list.addEventListener('click', function (event) {
            var a = event.target.closest('a.wa-chatlist-item');
            if (!a) return;
            if (a.getAttribute('aria-disabled') === 'true') return;

            var href = a.getAttribute('href') || '';
            if (!href) return;

            event.preventDefault();

            if (inflight && typeof inflight.abort === 'function') {
                inflight.abort();
            }
            inflight = new AbortController();

            fetch(href, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: inflight.signal
            }).then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.text();
            }).then(function (html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var nextChat = doc.querySelector('.wa-workspace-chat');
                var nextPanel = doc.querySelector('.wa-workspace-panel');
                var curChat = document.querySelector('.wa-workspace-chat');
                var curPanel = document.querySelector('.wa-workspace-panel');

                if (!nextChat || !curChat) {
                    window.location.href = href;
                    return;
                }

                curChat.replaceWith(nextChat);
                if (nextPanel && curPanel) {
                    curPanel.replaceWith(nextPanel);
                }

                Array.from(list.querySelectorAll('.wa-chatlist-item')).forEach(function (el) {
                    el.classList.remove('wa-chatlist-item--active');
                });
                a.classList.add('wa-chatlist-item--active');

                try {
                    history.pushState({ waChatHref: href }, '', href);
                } catch (_e) {}

                // Re-init swapped DOM
                if (window.WAChatPreview && typeof window.WAChatPreview.init === 'function') {
                    window.WAChatPreview.init();
                }
                if (window.WAChatUI && typeof window.WAChatUI.init === 'function') {
                    window.WAChatUI.init();
                }
            }).catch(function (err) {
                if (err && err.name === 'AbortError') return;
                window.location.href = href;
            });
        });
    }

    function initSidebarCollapse(workspace) {
        var collapseBtn = document.getElementById('waChatsCollapseBtn');
        if (!workspace || !collapseBtn) {
            return;
        }
        if (collapseBtn.dataset.waBound === '1') {
            return;
        }
        collapseBtn.dataset.waBound = '1';
        collapseBtn.addEventListener('click', function () {
            var isOpen = workspace.classList.toggle('wa-workspace--sidebar-open');
            collapseBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    function swapFromFetchedHtml(href, html, list, activeAnchor) {
        var parser = new DOMParser();
        var doc = parser.parseFromString(html, 'text/html');

        var nextRoot = doc.getElementById('waPreviewRoot');
        var curRoot = document.getElementById('waPreviewRoot');
        if (nextRoot && curRoot) {
            var nextMode = nextRoot.getAttribute('data-wa-preview-mode');
            if (nextMode) {
                curRoot.setAttribute('data-wa-preview-mode', nextMode);
            }
        }

        var nextChat = doc.querySelector('.wa-workspace-chat');
        var nextPanel = doc.querySelector('.wa-workspace-panel');
        var curChat = document.querySelector('.wa-workspace-chat');
        var curPanel = document.querySelector('.wa-workspace-panel');

        if (!nextChat || !curChat) {
            window.location.href = href;
            return;
        }

        curChat.replaceWith(nextChat);
        if (nextPanel && curPanel) {
            curPanel.replaceWith(nextPanel);
        }

        // Update header info + delete form (show view)
        var nextHeadInfo = doc.querySelector('.wa-preview-head-info');
        var curHeadInfo = document.querySelector('.wa-preview-head-info');
        if (nextHeadInfo && curHeadInfo) {
            curHeadInfo.replaceWith(nextHeadInfo);
        }

        var nextDeleteForm = doc.querySelector('form.js-wa-chat-delete-form');
        var curDeleteForm = document.querySelector('form.js-wa-chat-delete-form');
        if (nextDeleteForm && curDeleteForm) {
            curDeleteForm.replaceWith(nextDeleteForm);
        }

        if (list && activeAnchor) {
            Array.from(list.querySelectorAll('.wa-chatlist-item')).forEach(function (el) {
                el.classList.remove('wa-chatlist-item--active');
            });
            activeAnchor.classList.add('wa-chatlist-item--active');
        }

        try {
            history.pushState({ waChatHref: href }, '', href);
        } catch (_e) {}

        if (window.WAChatPreview && typeof window.WAChatPreview.init === 'function') {
            window.WAChatPreview.init();
        }
        if (window.WAChatUI && typeof window.WAChatUI.init === 'function') {
            window.WAChatUI.init();
        }
    }

    function initShowAjaxSwitch(root) {
        if (!root || root.getAttribute('data-wa-preview-mode') === 'browser') {
            return;
        }

        var list = document.getElementById('waSidebarList');
        if (!list || list.dataset.waBound === '1') {
            return;
        }
        list.dataset.waBound = '1';

        var inflight = null;

        list.addEventListener('click', function (event) {
            var a = event.target.closest('a.wa-chatlist-item');
            if (!a) return;
            if (a.getAttribute('aria-disabled') === 'true') return;

            var href = a.getAttribute('href') || '';
            if (!href) return;

            event.preventDefault();

            if (inflight && typeof inflight.abort === 'function') {
                inflight.abort();
            }
            inflight = new AbortController();

            fetch(href, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: inflight.signal
            }).then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.text();
            }).then(function (html) {
                swapFromFetchedHtml(href, html, list, a);
            }).catch(function (err) {
                if (err && err.name === 'AbortError') return;
                window.location.href = href;
            });
        });
    }

    function initShowSidebarToggle(workspace) {
        var btn = document.getElementById('waChatsToggle');
        var sidebar = document.getElementById('waChatsSidebar');
        if (!workspace || !btn || !sidebar) {
            return;
        }
        if (btn.dataset.waBound === '1') {
            return;
        }
        btn.dataset.waBound = '1';

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var isOpen = workspace.classList.toggle('wa-workspace--sidebar-open');
            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    function initUI() {
        var root = document.getElementById('waPreviewRoot');
        var workspace = document.querySelector('.wa-workspace');

        initPanelUI(workspace);
        initSidebarSearch();
        initSidebarCollapse(workspace);
        initBrowserAjaxSwitch(root, workspace);
        initShowSidebarToggle(workspace);
        initShowAjaxSwitch(root);
    }

    if (typeof window !== 'undefined') {
        window.WAChatUI = window.WAChatUI || {};
        window.WAChatUI.init = initUI;
    }

    document.addEventListener('DOMContentLoaded', function () {
        initUI();
    });
})();
