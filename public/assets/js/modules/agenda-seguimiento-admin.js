document.addEventListener('DOMContentLoaded', function () {
    var weekDaysEl = document.getElementById('agendaSegWeekDays');
    var weekTitleEl = document.getElementById('agendaSegWeekTitle');
    var btnPrev = document.getElementById('agendaSegWeekPrev');
    var btnNext = document.getElementById('agendaSegWeekNext');
    var plannerCard = document.getElementById('agendaSegWeekPlannerCard');
    var popover = document.getElementById('agendaSegWeekPopover');
    if (!weekDaysEl || !weekTitleEl) { return; }

    var escapeHtml = function (s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    var rows = Array.from(document.querySelectorAll('.agenda-seg-row[data-agenda-seg-date]'));

    // dateISO => Set(kinds)
    var dateKinds = {};
    rows.forEach(function (r) {
        var d = r.getAttribute('data-agenda-seg-date');
        var kind = r.getAttribute('data-agenda-seg-kind') || 'asunto';
        if (!d) { return; }
        dateKinds[d] = dateKinds[d] || new Set();
        dateKinds[d].add(kind);
    });

    var selectedDate = null; // ISO YYYY-MM-DD

    var pad2 = function (n) { return String(n).padStart(2, '0'); };
    var toISO = function (d) {
        return String(d.getFullYear()) + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    };
    var parseISO = function (iso) {
        var parts = String(iso).split('-');
        return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    };
    var getWeekStartMonday = function (d) {
        var copy = new Date(d);
        copy.setHours(0, 0, 0, 0);
        var day = (copy.getDay() + 6) % 7; // Monday=0 ... Sunday=6
        copy.setDate(copy.getDate() - day);
        return copy;
    };

    var weekdayLabels = ['L', 'M', 'X', 'J', 'V', 'S', 'D']; // Monday..Sunday
    var kindOrder = ['act', 'pre-gira', 'gira', 'asunto'];
    var kindToIcon = {
        'act': 'fa-pen-to-square',
        'pre-gira': 'fa-flag',
        'gira': 'fa-map-location-dot',
        'asunto': 'fa-list'
    };

    var viewWeekStart = getWeekStartMonday(new Date());

    var hidePopover = function () {
        if (!popover) return;
        popover.classList.remove('is-open');
        popover.setAttribute('aria-hidden', 'true');
    };

    var popoverHideTimer = null;
    var hidePopoverDelayed = function () {
        if (!popover) return;
        window.clearTimeout(popoverHideTimer);
        popoverHideTimer = window.setTimeout(function () {
            hidePopover();
        }, 120);
    };

    var showPopover = function (iso, btnEl) {
        if (!popover || !iso) return;
        var events = rows.filter(function (r) { return r.getAttribute('data-agenda-seg-date') === iso; });
        var kinds = {};
        events.forEach(function (r) { kinds[r.getAttribute('data-agenda-seg-kind') || 'asunto'] = true; });

        var fmt = iso;
        try {
            var dt = parseISO(iso);
            fmt = dt.toLocaleDateString('es-MX', { weekday: 'short', day: '2-digit', month: 'short' });
        } catch (e) {
            // keep fmt
        }

        if (!events.length) {
            popover.innerHTML = '';
            hidePopover();
            return;
        }

        var kindLabel = {
            'act': 'Actualización',
            'pre-gira': 'Pre-gira',
            'gira': 'Gira',
            'asunto': 'Asunto'
        };

        var iconsHtml = '';
        var max = Math.min(events.length, 4);

        var cardRect = plannerCard ? plannerCard.getBoundingClientRect() : null;
        var btnRect = btnEl ? btnEl.getBoundingClientRect() : null;
        var left = null;
        if (cardRect && btnRect) {
            left = (btnRect.left + btnRect.width / 2) - cardRect.left;
        }

        popover.style.left = left !== null ? (left + 'px') : '';

        var itemsHtml = events.slice(0, max).map(function (r) {
            var kind = r.getAttribute('data-agenda-seg-kind') || 'asunto';
            var icon = kindToIcon[kind] || 'fa-circle';

            var tds = r.children || [];
            var subjectTd = tds[1] || null;
            var titleEl = subjectTd ? subjectTd.querySelector('strong') : null;
            var title = titleEl ? (titleEl.textContent || '').trim() : '';
            var descEl = subjectTd ? subjectTd.querySelector('small') : null;
            var desc = descEl ? (descEl.textContent || '').trim() : '';

            var followTd = tds[3] || null;
            var followEl = followTd ? followTd.querySelector('.agenda-seg-seguimiento-text, .agenda-seg-text-muted') : null;
            var seguimiento = followEl ? (followEl.textContent || '').trim() : '';

            var label = kindLabel[kind] || kind;
            var extra = desc ? '<div class="agenda-seg-week-pop-sub">' + escapeHtml(desc) + '</div>' : '';
            var seg = seguimiento && seguimiento !== '—' ? '<div class="agenda-seg-week-pop-seg">Seguimiento: ' + escapeHtml(seguimiento) + '</div>' : '';

            return '' +
                '<div class="agenda-seg-week-pop-item">' +
                    '<div class="agenda-seg-week-pop-main">' +
                        '<div class="agenda-seg-week-pop-title"><i class="fa-solid ' + icon + '" aria-hidden="true" style="margin-right:7px; font-size:0.75rem; color: var(--clr-primary)"></i>' + escapeHtml(title || 'Actividad') + '</div>' +
                        '<div class="agenda-seg-week-pop-meta">' + escapeHtml(label) + (desc ? ' • ' + (desc.length > 28 ? desc.slice(0, 28) + '…' : desc) : '') + '</div>' +
                        extra +
                        seg +
                    '</div>' +
                '</div>';
        }).join('');

        var more = events.length > max ? ('<div class="agenda-seg-week-pop-more">+' + (events.length - max) + ' más</div>') : '';

        popover.innerHTML = '' +
            '<div class="agenda-seg-week-pop-head">' +
                '<div class="agenda-seg-week-pop-date">' + escapeHtml(fmt) + '</div>' +
            '</div>' +
            '<div class="agenda-seg-week-pop-items">' + itemsHtml + '</div>' +
            more;

        popover.classList.add('is-open');
        popover.setAttribute('aria-hidden', 'false');
    };

    if (popover) {
        popover.addEventListener('mouseenter', function () {
            window.clearTimeout(popoverHideTimer);
        });
        popover.addEventListener('mouseleave', function () {
            hidePopover();
        });
    }

    var applyFilter = function () {
        rows.forEach(function (r) {
            var d = r.getAttribute('data-agenda-seg-date');
            var show = !selectedDate || d === selectedDate;
            r.hidden = !show;
        });
        document.querySelectorAll('.agenda-seg-admin-user').forEach(function (userBlock) {
            var userRows = Array.from(userBlock.querySelectorAll('.agenda-seg-row'));
            var anyVisible = userRows.some(function (r) { return !r.hidden; });
            userBlock.hidden = !anyVisible;
        });

        document.querySelectorAll('.agenda-seg-user-day').forEach(function (chip) {
            chip.classList.toggle('is-selected', !!selectedDate && chip.dataset.date === selectedDate);
        });
    };

    var renderWeek = function () {
        hidePopover();
        weekDaysEl.classList.add('is-switching');
        // render async for smoother animation
        requestAnimationFrame(function () {
            weekDaysEl.innerHTML = '';

            var start = new Date(viewWeekStart);
            var end = new Date(viewWeekStart);
            end.setDate(end.getDate() + 6);

            var titleA = start.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
            var titleB = end.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
            weekTitleEl.textContent = titleA + ' — ' + titleB;

            for (var i = 0; i < 7; i++) {
                var d = new Date(start);
                d.setDate(start.getDate() + i);
                var iso = toISO(d);
                var kinds = dateKinds[iso] ? Array.from(dateKinds[iso]) : [];
                var orderedKinds = kindOrder.filter(function (k) { return kinds.indexOf(k) !== -1; });

                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'agenda-seg-week-day' + (selectedDate === iso ? ' is-selected' : '') + (orderedKinds.length ? ' has-events' : '');
                btn.dataset.date = iso;
                btn.setAttribute('aria-label', iso + (orderedKinds.length ? ' (' + orderedKinds.length + ' actividades)' : ''));

                var dayLabel = weekdayLabels[i];
                btn.innerHTML = '<div class="agenda-seg-week-day-top"><span class="agenda-seg-week-day-label">' + dayLabel + '</span><span class="agenda-seg-week-day-num">' + d.getDate() + '</span></div>'
                    + '<div class="agenda-seg-week-day-icons">';

                orderedKinds.slice(0, 3).forEach(function (kind) {
                    var cls = kindToIcon[kind] || 'fa-circle';
                    btn.querySelector('.agenda-seg-week-day-icons').innerHTML += '<i class="fa-solid ' + cls + '" aria-hidden="true"></i>';
                });

                btn.innerHTML += '</div>';

                btn.addEventListener('click', function () {
                    var iso2 = this.dataset.date;
                    selectedDate = (selectedDate === iso2) ? null : (iso2 || null);
                    if (selectedDate) {
                        viewWeekStart = getWeekStartMonday(parseISO(selectedDate));
                    }
                    applyFilter();
                    renderWeek();
                });

                btn.addEventListener('mouseenter', function () {
                    showPopover(iso, this);
                });
                btn.addEventListener('mouseleave', function () {
                    hidePopoverDelayed();
                });

                weekDaysEl.appendChild(btn);
            }
            window.setTimeout(function () {
                weekDaysEl.classList.remove('is-switching');
            }, 180);
        });
    };

    document.querySelectorAll('.agenda-seg-user-day').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var iso = this.dataset.date || null;
            selectedDate = (selectedDate === iso) ? null : iso;
            if (selectedDate) {
                viewWeekStart = getWeekStartMonday(parseISO(selectedDate));
            }
            applyFilter();
            renderWeek();
        });
    });

    if (btnPrev) {
        btnPrev.addEventListener('click', function () {
            viewWeekStart.setDate(viewWeekStart.getDate() - 7);
            renderWeek();
        });
    }
    if (btnNext) {
        btnNext.addEventListener('click', function () {
            viewWeekStart.setDate(viewWeekStart.getDate() + 7);
            renderWeek();
        });
    }

    applyFilter();
    renderWeek();
});
