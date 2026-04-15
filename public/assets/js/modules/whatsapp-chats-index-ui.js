(function () {
    'use strict';

    var zipInput = document.getElementById('waiZipInput');
    var zipLabelText = document.getElementById('waiZipLabelText');

    if (zipInput && zipLabelText) {
        zipInput.addEventListener('change', function () {
            zipLabelText.textContent = this.files[0] ? this.files[0].name : 'Seleccionar archivo .zip';
        });
    }

    var searchInput = document.getElementById('waiSearchInput');
    var chips = Array.from(document.querySelectorAll('.wai-chip'));
    var grid = document.getElementById('waiChatsGrid');
    var emptyElement = document.getElementById('waiFilterEmpty');
    var editRoot = document.getElementById('waiChatsEditRoot');

    if (!grid) {
        return;
    }

    var cards = Array.from(grid.querySelectorAll('.wai-chat-card'));
    var activeFilter = 'all';
    var activePopup = null;

    function normalizeText(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function cardMatchesStatusFilter(cardStatus, filter) {
        if (filter === 'all') {
            return true;
        }
        // "Procesando" incluye fase de subida por carpeta + procesamiento en cola.
        if (filter === 'processing') {
            return cardStatus === 'processing' || cardStatus === 'uploading';
        }
        return cardStatus === filter;
    }

    function applyFilters() {
        var query = searchInput ? normalizeText(searchInput.value) : '';
        var visible = 0;

        cards.forEach(function (card) {
            var title = normalizeText(card.dataset.title || '');
            var filename = normalizeText(card.dataset.filename || '');
            var searchTarget = (title + ' ' + filename).trim();
            var cardStatus = String(card.dataset.status || '').toLowerCase();

            var matchesQuery = !query || searchTarget.indexOf(query) !== -1;
            var matchesFilter = cardMatchesStatusFilter(cardStatus, activeFilter);
            card.hidden = !(matchesQuery && matchesFilter);
            if (!card.hidden) {
                visible += 1;
            }
        });

        if (emptyElement) {
            emptyElement.hidden = visible > 0;
        }
    }

    function closePopup(popup) {
        if (!popup) {
            return;
        }
        popup.classList.remove('wai-card-popup--open');
        var card = popup.closest('.wai-chat-card');
        if (card) {
            card.classList.remove('wai-card--menu-open');
        }
        activePopup = null;
    }

    function openPopup(popup) {
        if (activePopup && activePopup !== popup) {
            closePopup(activePopup);
        }
        popup.classList.add('wai-card-popup--open');
        var card = popup.closest('.wai-chat-card');
        if (card) {
            card.classList.add('wai-card--menu-open');
        }
        activePopup = popup;
    }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            chips.forEach(function (item) {
                item.classList.remove('wai-chip--active');
            });
            chip.classList.add('wai-chip--active');
            activeFilter = chip.dataset.filter || 'all';
            applyFilters();
        });
    });

    if (searchInput) {
        var timer;
        searchInput.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(applyFilters, 200);
        });
    }

    grid.addEventListener('click', function (event) {
        var card = event.target.closest('.wai-chat-card');
        if (card) {
            if (event.target.closest('.wai-card-title-input') || event.target.closest('.wai-card-edit-actions')) {
                return;
            }

            if (event.target.closest('.wai-card-popup')) {
                return;
            }
            event.stopPropagation();
            var popup = card.querySelector('.wai-card-popup');
            if (!popup) {
                return;
            }
            if (popup.classList.contains('wai-card-popup--open')) {
                closePopup(popup);
            } else {
                openPopup(popup);
            }
            return;
        }
        closePopup(activePopup);
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.wai-chat-card')) {
            closePopup(activePopup);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closePopup(activePopup);
        }
    });

    function getUpdateUrl(chatId) {
        if (!editRoot) return null;
        var tpl = editRoot.getAttribute('data-update-url-template') || '';
        if (!tpl) return null;
        return tpl.replace('__ID__', String(chatId));
    }

    function getCsrf() {
        if (!editRoot) return '';
        return editRoot.getAttribute('data-csrf') || '';
    }

    function getImportStatusUrl(chatId) {
        if (!editRoot) return null;
        var tpl = editRoot.getAttribute('data-import-status-url-template') || '';
        if (!tpl) return null;
        return tpl.replace('__ID__', String(chatId));
    }

    function updateCardProcessingProgress(card, statusData) {
        if (!card || !statusData) return;
        var textEl = card.querySelector('.js-wa-resume-progress-text');
        var barEl = card.querySelector('.js-wa-resume-progress-bar');
        var track = card.querySelector('.wai-resume-progress-track');
        if (!textEl || !barEl || !track) return;

        var pct = Math.max(0, Math.min(100, parseInt(statusData.progress || 0, 10)));
        var phase = String(statusData.phase || '').trim();
        barEl.style.width = pct + '%';
        track.setAttribute('aria-valuenow', String(pct));
        textEl.textContent = (phase ? phase + ' ' : 'Importando... ') + pct + '%';
    }

    function startImportStatusPolling() {
        var pendingCards = cards.filter(function (card) {
            var st = String(card.dataset.status || '').toLowerCase();
            return st === 'uploading' || st === 'processing';
        });
        if (!pendingCards.length) {
            return;
        }

        var inFlight = {};
        var stopped = false;

        function hasPendingCards() {
            return cards.some(function (card) {
                var st = String(card.dataset.status || '').toLowerCase();
                return st === 'uploading' || st === 'processing';
            });
        }

        function pollOnce() {
            if (stopped) {
                return;
            }

            cards.forEach(function (card) {
                var chatId = card.getAttribute('data-chat-id');
                if (!chatId) return;

                var currentStatus = String(card.dataset.status || '').toLowerCase();
                if (currentStatus !== 'uploading' && currentStatus !== 'processing') {
                    return;
                }
                if (inFlight[chatId]) {
                    return;
                }

                var url = getImportStatusUrl(chatId);
                if (!url) return;

                inFlight[chatId] = true;
                fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Cache-Control': 'no-cache'
                    }
                }).then(function (res) {
                    return res.ok ? res.json() : null;
                }).then(function (data) {
                    inFlight[chatId] = false;
                    if (!data) return;

                    updateCardProcessingProgress(card, data);

                    var newStatus = String(data.status || '').toLowerCase();
                    var done = !!data.done;
                    if ((newStatus && newStatus !== currentStatus) || done) {
                        stopped = true;
                        window.location.reload();
                    }
                }).catch(function () {
                    inFlight[chatId] = false;
                });
            });
        }

        pollOnce();
        var timer = setInterval(function () {
            if (stopped || !hasPendingCards()) {
                clearInterval(timer);
                return;
            }
            pollOnce();
        }, 6000);
    }

    function enterEditMode(card) {
        if (!card) return;
        card.classList.add('wai-card--editing');

        var titleText = card.querySelector('.wai-card-title-text');
        var titleInput = card.querySelector('.js-wai-title-input');
        var actions = card.querySelector('.wai-card-edit-actions');
        if (titleText) titleText.hidden = true;
        if (titleInput) {
            titleInput.hidden = false;
            titleInput.focus();
            titleInput.setSelectionRange(titleInput.value.length, titleInput.value.length);
        }
        if (actions) actions.hidden = false;

        var pencil = card.querySelector('.wai-card-avatar-pencil');
        var fileInput = card.querySelector('.js-wai-avatar-file');
        if (pencil) pencil.style.display = '';
        if (fileInput) fileInput.hidden = false;
    }

    function exitEditMode(card, restore) {
        if (!card) return;
        card.classList.remove('wai-card--editing');

        var titleText = card.querySelector('.wai-card-title-text');
        var titleInput = card.querySelector('.js-wai-title-input');
        var actions = card.querySelector('.wai-card-edit-actions');
        if (restore && titleInput && titleText) {
            titleInput.value = titleText.textContent || '';
        }
        if (titleText) titleText.hidden = false;
        if (titleInput) titleInput.hidden = true;
        if (actions) actions.hidden = true;

        var fileInput = card.querySelector('.js-wai-avatar-file');
        if (fileInput) {
            fileInput.value = '';
            fileInput.hidden = true;
        }
    }

    grid.addEventListener('click', function (event) {
        var editBtn = event.target.closest('.js-wai-edit');
        if (editBtn) {
            event.preventDefault();
            event.stopPropagation();
            var card = editBtn.closest('.wai-chat-card');
            closePopup(activePopup);
            enterEditMode(card);
            return;
        }

        var saveBtn = event.target.closest('.js-wai-edit-save');
        if (saveBtn) {
            event.preventDefault();
            event.stopPropagation();
            var cardSave = saveBtn.closest('.wai-chat-card');
            if (!cardSave) return;

            var chatId = cardSave.getAttribute('data-chat-id');
            var url = getUpdateUrl(chatId);
            var csrf = getCsrf();
            if (!url || !csrf) return;

            var titleInput = cardSave.querySelector('.js-wai-title-input');
            var title = titleInput ? String(titleInput.value || '').trim() : '';
            if (!title) return;

            var fd = new FormData();
            fd.append('_method', 'PATCH');
            fd.append('_token', csrf);
            fd.append('title', title);

            var fileInput = cardSave.querySelector('.js-wai-avatar-file');
            if (fileInput && fileInput.files && fileInput.files[0]) {
                fd.append('avatar', fileInput.files[0]);
            }

            saveBtn.disabled = true;

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: fd
            }).then(function (res) {
                return res.text().then(function (txt) {
                    var data = {};
                    try { data = txt ? JSON.parse(txt) : {}; } catch (_e) {}
                    return { ok: res.ok, data: data };
                });
            }).then(function (out) {
                saveBtn.disabled = false;
                if (!out.ok || !out.data || !out.data.ok) {
                    var swal = window.Swal;
                    var msg = (out.data && out.data.message) ? String(out.data.message) : 'No se pudo guardar.';
                    if (swal && typeof swal.fire === 'function') {
                        swal.fire({ icon: 'error', title: 'Error', text: msg });
                    } else {
                        alert(msg);
                    }
                    return;
                }

                var titleText = cardSave.querySelector('.wai-card-title-text');
                if (titleText) {
                    titleText.textContent = out.data.title || title;
                }
                cardSave.setAttribute('data-title', String((out.data.title || title) || '').toLowerCase());

                if (out.data.avatar_url) {
                    var img = cardSave.querySelector('.wai-card-avatar-img');
                    if (!img) {
                        img = document.createElement('img');
                        img.className = 'wai-card-avatar-img';
                        img.alt = out.data.title || title;
                        var avatar = cardSave.querySelector('.wai-card-avatar');
                        if (avatar) {
                            avatar.insertBefore(img, avatar.firstChild);
                        }
                    }
                    img.src = out.data.avatar_url + '&t=' + Date.now();
                }

                exitEditMode(cardSave, false);
                applyFilters();
            }).catch(function () {
                saveBtn.disabled = false;
            });
            return;
        }

        var cancelBtn = event.target.closest('.js-wai-edit-cancel');
        if (cancelBtn) {
            event.preventDefault();
            event.stopPropagation();
            var cardCancel = cancelBtn.closest('.wai-chat-card');
            exitEditMode(cardCancel, true);
            return;
        }

        var avatar = event.target.closest('.wai-card-avatar');
        if (avatar) {
            var cardAvatar = avatar.closest('.wai-chat-card');
            if (cardAvatar && cardAvatar.classList.contains('wai-card--editing')) {
                var fileInput = cardAvatar.querySelector('.js-wai-avatar-file');
                if (fileInput) {
                    fileInput.click();
                }
            }
        }
    });

    applyFilters();
    startImportStatusPolling();
})();
