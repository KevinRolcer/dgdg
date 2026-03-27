@php
    $month = $month ?? now()->month;
    $year = $year ?? now()->year;

    $firstDayOfMonth = \Carbon\Carbon::create($year, $month, 1);
    $daysInMonth = $firstDayOfMonth->daysInMonth;

    $notesByDay = $notes->groupBy(function ($note) {
        if ($note->scheduled_date) {
            return (int) $note->scheduled_date->day;
        }

        return (int) $note->created_at->day;
    });

    $prioritySort = static function ($collection) {
        return $collection->sortBy(function ($n) {
            return match ($n->priority ?? 'none') {
                'high' => 1,
                'medium' => 2,
                'low' => 3,
                default => 4,
            };
        })->values();
    };

    /* Franjas de 6:00 a 5:00 (24 h), orden: mañana → madrugada siguiente */
    $hours = [];
    for ($i = 0; $i < 24; $i++) {
        $h = (6 + $i) % 24;
        $hours[] = sprintf('%02d:00', $h);
    }

    $dayNames = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

    $weeks = [];
    $currentWeek = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $date = \Carbon\Carbon::create($year, $month, $d);
        $currentWeek[] = [
            'day' => $d,
            'name' => $dayNames[$date->dayOfWeekIso - 1],
            'isToday' => now()->isSameDay($date),
        ];

        if ($date->dayOfWeekIso == 7 || $d == $daysInMonth) {
            $weeks[] = $currentWeek;
            $currentWeek = [];
        }
    }

    $hourLabel = static function (string $hourStr): string {
        $h = (int) explode(':', $hourStr)[0];
        if ($h === 12) {
            return 'Mediodía';
        }
        if ($h === 0) {
            return 'Medianoche';
        }
        $c = \Carbon\Carbon::createFromTime($h, 0, 0);

        return $c->format('g A');
    };

    $noteHasTime = static function ($n): bool {
        $t = $n->scheduled_time;
        if ($t === null) {
            return false;
        }
        if (is_string($t)) {
            return trim($t) !== '';
        }

        return true;
    };

    $noteThumbData = function ($note) use ($noteHasTime): array {
        return [
            'id' => $note->id,
            'title' => $note->title,
            'content' => $note->is_encrypted ? null : (string) $note->content,
            'priority' => $note->priority,
            'color' => $note->color,
            'folder_id' => $note->folder_id,
            'is_encrypted' => (bool) $note->is_encrypted,
            'is_archived' => (bool) $note->is_archived,
            'scheduled_date' => $note->scheduled_date ? $note->scheduled_date->format('Y-m-d') : null,
            'scheduled_time' => $noteHasTime($note)
                ? \Carbon\Carbon::parse($note->scheduled_time)->format('H:i')
                : null,
            'displayDate' => $note->scheduled_date
                ? $note->scheduled_date->translatedFormat('d M Y')
                    .($noteHasTime($note) ? ' · '.\Carbon\Carbon::parse($note->scheduled_time)->format('H:i') : ' · Todo el día')
                : '',
            'attachments' => $note->attachments->map(fn ($a) => [
                'id' => $a->id,
                'file_name' => $a->file_name,
                'file_path' => route('personal-agenda.attachments.serve', $a->id),
                'file_type' => $a->file_type,
            ]),
        ];
    };

    $jsonEncodeNoteData = static function ($note) use ($noteThumbData): string {
        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        return htmlspecialchars(json_encode($noteThumbData($note), $flags), ENT_QUOTES, 'UTF-8');
    };

    /** Misma lógica que getContrastClass en index: icono legible según color de carpeta (o nota). */
    $calIconContrastClass = static function ($note): string {
        $hexColor = $note->folder?->color ?: $note->color;
        if (! $hexColor || ! str_starts_with($hexColor, '#')) {
            return 'pa-cal-icon--dark';
        }

        $hexColor = str_replace('#', '', $hexColor);
        if (strlen($hexColor) == 3) {
            $r = hexdec(substr($hexColor, 0, 1).substr($hexColor, 0, 1));
            $g = hexdec(substr($hexColor, 1, 1).substr($hexColor, 1, 1));
            $b = hexdec(substr($hexColor, 2, 1).substr($hexColor, 2, 1));
        } else {
            $r = hexdec(substr($hexColor, 0, 2));
            $g = hexdec(substr($hexColor, 2, 2));
            $b = hexdec(substr($hexColor, 4, 2));
        }

        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.6 ? 'pa-cal-icon--dark' : 'pa-cal-icon--light';
    };

    $folderIconClass = static function ($note): string {
        $raw = trim((string) ($note->folder?->icon ?? ''));
        if ($raw === '') {
            return 'fa-folder';
        }
        $raw = preg_replace('/^fa-(solid|regular|brands)\s+/i', '', $raw) ?? $raw;

        return str_contains($raw, 'fa-') ? $raw : 'fa-folder';
    };
@endphp

<div class="pa-schedule-wrapper" id="pa-schedule-wrapper" data-cal-month="{{ $month }}" data-cal-year="{{ $year }}">
    <div class="pa-schedule-container-scroll">
        <table class="pa-schedule-grid-premium">
            <thead>
                <tr class="pa-cal-week-labels">
                    <td class="pa-cal-time-corner"></td>
                    @foreach ($weeks as $index => $week)
                        @php $weekHasToday = collect($week)->contains(fn ($d) => $d['isToday']); @endphp
                        <th colspan="{{ count($week) }}" class="pa-cal-week-title {{ $weekHasToday ? 'is-week-active' : '' }}">
                            Semana {{ $index + 1 }}
                        </th>
                    @endforeach
                </tr>
                <tr class="pa-cal-day-labels">
                    <td class="pa-cal-time-corner"></td>
                    @foreach ($weeks as $week)
                        @foreach ($week as $dayInfo)
                            <th class="pa-cal-day-head {{ $dayInfo['isToday'] ? 'is-today' : '' }}">
                                <span class="pa-cal-day-name">{{ $dayInfo['name'] }}</span>
                                <span class="pa-cal-day-num">{{ $dayInfo['day'] }}</span>
                            </th>
                        @endforeach
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr class="pa-cal-allday-row">
                    <td class="pa-cal-time-cell pa-cal-time-cell--allday">Todo el día</td>
                    @for ($d = 1; $d <= $daysInMonth; $d++)
                        @php
                            $date = \Carbon\Carbon::create($year, $month, $d);
                            $isTodayCol = now()->isSameDay($date);
                            $allDayNotes = $prioritySort(
                                collect($notesByDay->get($d, []))->filter(fn ($n) => ! $noteHasTime($n))
                            );
                        @endphp
                        <td class="pa-cal-slot pa-cal-slot--allday {{ $isTodayCol ? 'is-today-col' : '' }}" data-day="{{ $d }}">
                            <div class="pa-cal-slot-grid">
                                @foreach ($allDayNotes as $note)
                                    <button type="button"
                                            class="pa-cal-note pa-cal-note--allday {{ ($note->priority ?? 'none') !== 'none' ? 'has-priority' : '' }}"
                                            data-note-id="{{ $note->id }}"
                                            data-note-data="{{ $jsonEncodeNoteData($note) }}"
                                            data-note-encrypted="{{ $note->is_encrypted ? '1' : '0' }}"
                                            style="--pa-note-bg: {{ $note->color ?: '#e8f4fc' }};"
                                            title="{{ e($note->title ?: 'Sin título') }}">
                                        @if (($note->priority ?? 'none') !== 'none')
                                            <span class="pa-cal-priority-dot pa-cal-priority-dot--{{ $note->priority }}" aria-hidden="true"></span>
                                        @endif
                                        <span class="pa-cal-note-icon"><i class="fa-solid {{ $folderIconClass($note) }} {{ $calIconContrastClass($note) }}"></i></span>
                                    </button>
                                @endforeach
                            </div>
                        </td>
                    @endfor
                </tr>

                @foreach ($hours as $hour)
                    @php
                        $hourNum = (int) explode(':', $hour)[0];
                    @endphp
                    <tr class="pa-cal-hour-row">
                        <td class="pa-cal-time-cell">{{ $hourLabel($hour) }}</td>
                        @for ($d = 1; $d <= $daysInMonth; $d++)
                            @php
                                $date = \Carbon\Carbon::create($year, $month, $d);
                                $isTodayCol = now()->isSameDay($date);
                                $hourNotes = $prioritySort(
                                    collect($notesByDay->get($d, []))->filter(function ($n) use ($hourNum, $noteHasTime) {
                                        if (! $noteHasTime($n)) {
                                            return false;
                                        }
                                        $t = $n->scheduled_time;
                                        $ts = is_string($t) ? $t : $t->format('H:i:s');
                                        $h = (int) substr($ts, 0, 2);

                                        return $h === $hourNum;
                                    })
                                );
                            @endphp
                            <td class="pa-cal-slot {{ $isTodayCol ? 'is-today-col' : '' }}" data-day="{{ $d }}" data-hour="{{ $hourNum }}">
                                <div class="pa-cal-slot-grid">
                                    @foreach ($hourNotes as $note)
                                        <button type="button"
                                                class="pa-cal-note {{ ($note->priority ?? 'none') !== 'none' ? 'has-priority' : '' }}"
                                                data-note-id="{{ $note->id }}"
                                                data-note-data="{{ $jsonEncodeNoteData($note) }}"
                                                data-note-encrypted="{{ $note->is_encrypted ? '1' : '0' }}"
                                                style="--pa-note-bg: {{ $note->color ?: '#eef6ff' }};"
                                                title="{{ e(($note->scheduled_time ? \Carbon\Carbon::parse($note->scheduled_time)->format('H:i').' · ' : '').($note->title ?: 'Sin título')) }}">
                                            @if (($note->priority ?? 'none') !== 'none')
                                                <span class="pa-cal-priority-dot pa-cal-priority-dot--{{ $note->priority }}" aria-hidden="true"></span>
                                            @endif
                                            <span class="pa-cal-note-icon"><i class="fa-solid {{ $folderIconClass($note) }} {{ $calIconContrastClass($note) }}"></i></span>
                                        </button>
                                    @endforeach
                                </div>
                            </td>
                        @endfor
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
