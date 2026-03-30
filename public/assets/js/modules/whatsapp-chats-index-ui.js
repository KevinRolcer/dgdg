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

    applyFilters();
})();
