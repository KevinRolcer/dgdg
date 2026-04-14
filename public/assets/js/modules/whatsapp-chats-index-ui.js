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

    function applyFilters() {
        var query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        var visible = 0;

        cards.forEach(function (card) {
            var matchesQuery = !query || (card.dataset.title || '').includes(query);
            var matchesFilter = activeFilter === 'all' || card.dataset.status === activeFilter;
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
})();
