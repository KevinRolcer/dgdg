@php
    $month = $month ?? now()->month;
    $year = $year ?? now()->year;
    
    $firstDayOfMonth = \Carbon\Carbon::create($year, $month, 1);
    $daysInMonth = $firstDayOfMonth->daysInMonth;
    $startOfWeek = $firstDayOfMonth->dayOfWeekIso; // 1 (Mon) to 7 (Sun)
    
    $prevMonth = (clone $firstDayOfMonth)->subMonth();
    $daysInPrevMonth = $prevMonth->daysInMonth;
    
    // Group notes by day for easy access
    $notesByDay = $notes->groupBy(function($note) {
        return $note->scheduled_date ? $note->scheduled_date->day : 0;
    });

    $dayNames = ['LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB', 'DOM'];
    $monthName = $firstDayOfMonth->translatedFormat('F Y');
@endphp

<div class="pa-calendar-view">
    <div class="pa-calendar-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding: 0 5px;">
        <h3 style="margin: 0; font-size: 0.9rem; font-weight: 800; text-transform: uppercase; color: var(--clr-primary-dark);">{{ $monthName }}</h3>
        <div style="display: flex; gap: 5px;">
            <button class="pa-cal-nav-btn" onclick="navCalendar('prev')" title="Mes Anterior">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <button class="pa-cal-nav-btn" onclick="navCalendar('next')" title="Mes Siguiente">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
    </div>
    <div class="pa-calendar-days">
        @foreach($dayNames as $name)
            <div class="pa-cal-day-name">{{ $name }}</div>
        @endforeach
    </div>

    <div class="pa-calendar-grid">
        {{-- Days from previous month --}}
        @for($i = 1; $i < $startOfWeek; $i++)
            @php $dayNum = $daysInPrevMonth - ($startOfWeek - $i - 1); @endphp
            <div class="pa-cal-cell is-muted">
                <span class="pa-cal-num">{{ $dayNum }}</span>
            </div>
        @endfor

        {{-- Days of current month --}}
        @for($day = 1; $day <= $daysInMonth; $day++)
            @php 
                $dayNotes = $notesByDay->get($day, []);
                $isToday = now()->isSameDay(\Carbon\Carbon::create($year, $month, $day));
            @endphp
            <div class="pa-cal-cell {{ $isToday ? 'is-today' : '' }}">
                <span class="pa-cal-num">{{ $day }}</span>
                <div class="pa-cal-notes">
                    @foreach($dayNotes as $note)
                        <div class="pa-cal-note-dot" 
                             style="background-color: {{ $note->color ?: 'var(--pa-blue)' }};" 
                             title="{{ $note->title }}"
                             onclick="editNote({{ $note->id }})">
                             <span class="pa-cal-note-title">{{ $note->title }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endfor
    </div>
</div>

<style>
.pa-calendar-view {
    background: #fff;
    border-radius: 16px;
    border: 1px solid var(--clr-border);
    padding: 15px;
    box-shadow: var(--shadow-soft);
    animation: fadeIn 0.3s ease;
}

.pa-calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    border-bottom: 1px solid var(--clr-border);
    padding-bottom: 10px;
    margin-bottom: 5px;
}

.pa-cal-day-name {
    text-align: center;
    font-size: 0.7rem;
    font-weight: 800;
    color: #888;
}

.pa-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    grid-auto-rows: minmax(80px, auto);
}

.pa-cal-cell {
    border-right: 1px solid #f0f0f0;
    border-bottom: 1px solid #f0f0f0;
    padding: 8px;
    position: relative;
    transition: background 0.2s;
}

.pa-cal-cell:nth-child(7n) { border-right: none; }

.pa-cal-cell:hover {
    background: #fcfcfc;
}

.pa-cal-cell.is-muted {
    background: #fafafa;
    color: #ccc;
}

.pa-cal-cell.is-today {
    background: rgba(199, 155, 102, 0.05);
}

.pa-cal-num {
    font-size: 0.75rem;
    font-weight: 700;
}

.is-today .pa-cal-num {
    color: var(--clr-accent);
}

.pa-cal-notes {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: 5px;
}

.pa-cal-note-dot {
    font-size: 0.65rem;
    padding: 2px 6px;
    border-radius: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--clr-text-main);
    cursor: pointer;
    font-weight: 600;
    border: 1px solid rgba(0,0,0,0.05);
}

.pa-cal-note-dot:hover {
    filter: brightness(0.95);
    transform: scale(1.02);
}

/* Mobile Adjustments */
@media (max-width: 768px) {
    .pa-calendar-grid {
        grid-auto-rows: minmax(60px, auto);
    }
    .pa-cal-note-title {
        display: none;
    }
    .pa-cal-note-dot {
        width: 8px;
        height: 8px;
        padding: 0;
        border-radius: 50%;
    }
}
.pa-cal-nav-btn {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 1px solid #eee;
    background: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    color: #666;
    transition: all 0.2s;
}

.pa-cal-nav-btn:hover {
    background: #f8f9fa;
    border-color: #ddd;
    color: var(--clr-primary);
}
