@extends('layouts.app')

@section('title', 'Asignaciones de agenda')
@php
    $pageTitle = 'Asignaciones de agenda';
    $hidePageHeader = true;
@endphp

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/agenda.css') }}?v={{ @filemtime(public_path('assets/css/modules/agenda.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/agenda-seguimiento.css') }}?v={{ @filemtime(public_path('assets/css/modules/agenda-seguimiento.css')) ?: time() }}">
@endpush

@section('content')
<section class="agenda-seg-page agenda-shell app-density-compact">
    <div class="agenda-shell-main">
        <header class="agenda-shell-head">
            <h1 class="agenda-shell-title">Asignaciones de agenda</h1>
            <p class="agenda-shell-desc">Vista por usuario: qué actividades tiene asignadas cada quien y cómo les dan seguimiento.</p>
        </header>

        @if (session('toast'))
            <div class="inline-alert inline-alert-success" role="status">{{ session('toast') }}</div>
        @endif

        <article class="agenda-card agenda-card-in-shell">
            @if (empty($porUsuario))
                <p class="agenda-seg-empty">No hay usuarios con actividades asignadas en seguimiento.</p>
            @else
                <div class="agenda-seg-week-planner-card" id="agendaSegWeekPlannerCard">
                    <div class="agenda-seg-week-toolbar">
                        <button type="button" class="agenda-seg-week-nav" id="agendaSegWeekPrev" aria-label="Semana anterior" title="Semana anterior">
                            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                        </button>
                        <div class="agenda-seg-week-title" id="agendaSegWeekTitle">—</div>
                        <button type="button" class="agenda-seg-week-nav" id="agendaSegWeekNext" aria-label="Semana siguiente" title="Semana siguiente">
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="agenda-seg-week-days" id="agendaSegWeekDays" role="grid" aria-label="Calendario semanal de asignaciones de agenda"></div>
                    <div id="agendaSegWeekPopover" class="agenda-seg-week-popover" aria-hidden="true"></div>
                </div>

                <div class="agenda-seg-users-mini" id="agendaSegUsersMini" aria-label="Usuarios asignados (minimal)">
                    @foreach ($porUsuario as $userId => $bloque)
                        @php
                            $user = $bloque['user'];
                            $agendas = $bloque['agendas'];
                            $dates = $agendas
                                ->pluck('fecha_inicio')
                                ->map(fn($d) => $d ? $d->format('Y-m-d') : null)
                                ->filter()
                                ->unique()
                                ->sort()
                                ->values();
                        @endphp
                        <div class="agenda-seg-users-mini-user">
                            <div class="agenda-seg-users-mini-name">{{ $user->name }}</div>
                            <div class="agenda-seg-users-mini-days">
                                @foreach ($dates as $iso)
                                    <button type="button" class="agenda-seg-user-day" data-date="{{ $iso }}" title="Ir a {{ $iso }}">
                                        {{ \Carbon\Carbon::parse($iso)->format('d') }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                @foreach ($porUsuario as $userId => $bloque)
                    @php
                        $user = $bloque['user'];
                        $agendas = $bloque['agendas'];
                    @endphp
                    <div class="agenda-seg-admin-user">
                        <h2 class="agenda-seg-admin-user-title">
                            <span class="agenda-seg-admin-user-name">{{ $user->name }}</span>
                            <span class="agenda-seg-admin-user-count">({{ $agendas->count() }} {{ $agendas->count() === 1 ? 'actividad' : 'actividades' }})</span>
                        </h2>
                        <div class="agenda-seg-table-wrap">
                            <table class="agenda-table agenda-seg-table">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Asunto</th>
                                        <th>Fecha</th>
                                        <th>Seguimiento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($agendas as $item)
                                        <tr class="agenda-seg-row"
                                            data-agenda-seg-date="{{ $item->fecha_inicio->format('Y-m-d') }}"
                                            data-agenda-seg-kind="{{ $item->es_actualizacion ? 'act' : ($item->tipo === 'gira' ? (strtolower((string)($item->subtipo ?? '')) === 'pre-gira' ? 'pre-gira' : 'gira') : 'asunto') }}">
                                            <td>
                                                @if ($item->es_actualizacion)
                                                    <span class="agenda-seg-badge agenda-seg-badge--act">Actualización</span>
                                                @endif
                                                @if ($item->tipo === 'gira')
                                                    <span class="agenda-seg-badge agenda-seg-badge--gira">{{ strtolower((string)($item->subtipo ?? '')) === 'pre-gira' ? 'Pre-gira' : 'Gira' }}</span>
                                                @else
                                                    <span class="agenda-seg-badge agenda-seg-badge--asunto">Agenda</span>
                                                @endif
                                            </td>
                                            <td>
                                                <strong>{{ $item->asunto }}</strong>
                                                @if ($item->descripcion)
                                                    <small>{{ Str::limit($item->descripcionConAforoPersonas(), 56) }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $item->fecha_inicio->format('d/m/Y') }}
                                                @if ($item->habilitar_hora && $item->hora)
                                                    <small>{{ \Carbon\Carbon::parse($item->hora)->format('H:i') }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                @if (filled($item->seguimiento))
                                                    <span class="agenda-seg-seguimiento-text">{{ $item->seguimiento }}</span>
                                                @else
                                                    <span class="agenda-seg-text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @endif
        </article>
    </div>
</section>
@endsection

@push('scripts')
<script>
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
</script>
@endpush
