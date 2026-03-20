<?php

namespace App\Services\Agenda;

use App\Models\Agenda;
use App\Models\User;
use App\Services\Home\HomeAgendaCalendarService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AgendaDirectivaCalendarService
{
    public function __construct(
        private readonly HomeAgendaCalendarService $homeAgendaCalendar
    ) {}

    /**
     * @param  array{clasificacion?: string, buscar?: string}  $filters
     * @return array{
     *   year: int,
     *   month: int,
     *   month_label: string,
     *   prev: array{y:int,m:int},
     *   next: array{y:int,m:int},
     *   grid_weeks: list<list<array<string,mixed>>>,
     *   list_rows: list<array<string,mixed>>,
     *   cards: list<array<string,mixed>>,
     *   today: string
     * }
     */
    public function buildMonthPayload(User $viewer, int $year, int $month, array $filters): array
    {
        $tz = config('app.timezone', 'UTC');

        $monthStart = Carbon::create($year, $month, 1, 0, 0, 0, $tz)->startOfMonth()->locale('es');
        $monthEnd = $monthStart->copy()->endOfMonth();

        $agendas = $this->queryAgendasForMonth($viewer, $monthStart, $monthEnd, $filters);

        $byDay = [];
        foreach ($agendas as $agenda) {
            $occs = $this->homeAgendaCalendar->occurrencesInCalendarMonth($agenda, $year, $month);
            foreach ($occs as $occ) {
                $key = $occ['date']->format('Y-m-d');
                if (! isset($byDay[$key])) {
                    $byDay[$key] = [];
                }
                $byDay[$key][] = [
                    'agenda_id' => $agenda->id,
                    'title' => $this->homeAgendaCalendar->displayTitle($agenda),
                    'time' => $occ['time_label'],
                ];
            }
        }

        foreach ($byDay as $k => $items) {
            usort($items, fn ($a, $b) => strcmp((string) $a['time'], (string) $b['time']));
            $byDay[$k] = $items;
        }

        $gridWeeks = $this->buildGridWeeks($year, $month, $byDay, $tz);

        $listRows = [];
        foreach ($agendas as $agenda) {
            $occs = $this->homeAgendaCalendar->occurrencesInCalendarMonth($agenda, $year, $month);
            foreach ($occs as $occ) {
                $d = $occ['date'];
                $listRows[] = [
                    'agenda_id' => $agenda->id,
                    'date' => $d->format('Y-m-d'),
                    'weekday' => mb_convert_case($d->copy()->locale('es')->isoFormat('ddd'), MB_CASE_TITLE, 'UTF-8'),
                    'day' => $d->day,
                    'time_label' => $occ['time_label'],
                    'title' => $this->homeAgendaCalendar->displayTitle($agenda),
                    'lugar' => trim((string) ($agenda->lugar ?? '')) !== '' ? (string) $agenda->lugar : '—',
                ];
            }
        }
        usort($listRows, function ($a, $b) {
            $c = strcmp((string) $a['date'], (string) $b['date']);
            if ($c !== 0) {
                return $c;
            }

            return strcmp((string) $a['time_label'], (string) $b['time_label']);
        });

        $cards = [];
        foreach ($agendas as $agenda) {
            $occs = $this->homeAgendaCalendar->occurrencesInCalendarMonth($agenda, $year, $month);
            if (count($occs) === 0) {
                continue;
            }
            $fi = $agenda->fecha_inicio instanceof Carbon
                ? $agenda->fecha_inicio->copy()->locale('es')
                : Carbon::parse($agenda->fecha_inicio)->locale('es');
            $ff = $agenda->fecha_fin
                ? ($agenda->fecha_fin instanceof Carbon
                    ? $agenda->fecha_fin->copy()->locale('es')
                    : Carbon::parse($agenda->fecha_fin)->locale('es'))
                : $fi->copy();

            if ($fi->toDateString() === $ff->toDateString()) {
                $rangeLabel = $fi->translatedFormat('j \d\e F \d\e Y');
            } elseif ($fi->format('Y-m') === $ff->format('Y-m')) {
                $rangeLabel = $fi->format('j').' al '.$ff->translatedFormat('j \d\e F \d\e Y');
            } elseif ($fi->year === $ff->year) {
                $rangeLabel = $fi->translatedFormat('j \d\e F').' al '.$ff->translatedFormat('j \d\e F \d\e Y');
            } else {
                $rangeLabel = $fi->translatedFormat('j M Y').' – '.$ff->translatedFormat('j M Y');
            }

            $desc = trim((string) ($agenda->descripcion ?? ''));
            $desc = $desc !== '' ? Str::limit($agenda->descripcionConAforoPersonas(), 180) : '';

            $cards[] = [
                'agenda_id' => $agenda->id,
                'range_label' => $rangeLabel,
                'badge_day' => $occs[0]['date']->day,
                'title' => $this->homeAgendaCalendar->displayTitle($agenda),
                'descripcion' => $desc,
            ];
        }

        $current = $monthStart->copy();
        $prev = $current->copy()->subMonth();
        $next = $current->copy()->addMonth();

        return [
            'year' => $year,
            'month' => $month,
            'month_label' => $monthStart->translatedFormat('F Y'),
            'prev' => ['y' => (int) $prev->year, 'm' => (int) $prev->month],
            'next' => ['y' => (int) $next->year, 'm' => (int) $next->month],
            'grid_weeks' => $gridWeeks,
            'list_rows' => $listRows,
            'cards' => $cards,
            'today' => Carbon::now($tz)->format('Y-m-d'),
        ];
    }

    /**
     * @param  array{clasificacion?: string, buscar?: string}  $filters
     * @return Collection<int, Agenda>
     */
    private function queryAgendasForMonth(User $viewer, Carbon $monthStart, Carbon $monthEnd, array $filters): Collection
    {
        $clasificacion = $filters['clasificacion'] ?? '';
        if (! in_array($clasificacion, ['', 'gira', 'pre_gira', 'agenda'], true)) {
            $clasificacion = '';
        }
        $buscar = trim((string) ($filters['buscar'] ?? ''));

        $query = Agenda::query()
            ->activas()
            ->with(['creador', 'usuariosAsignados'])
            ->whereDate('fecha_inicio', '<=', $monthEnd->toDateString())
            ->whereRaw('COALESCE(DATE(fecha_fin), DATE(fecha_inicio)) >= ?', [$monthStart->toDateString()]);

        if ($clasificacion === 'gira') {
            $query->where('tipo', 'gira')
                ->where(function ($q) {
                    $q->where('subtipo', 'gira')->orWhereNull('subtipo');
                });
        } elseif ($clasificacion === 'pre_gira') {
            $query->where('tipo', 'gira')->where('subtipo', 'pre-gira');
        } elseif ($clasificacion === 'agenda') {
            $query->where(function ($q) {
                $q->where('tipo', 'asunto')->orWhereNull('tipo');
            });
        }

        if ($buscar !== '') {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $buscar).'%';
            $query->where(function ($q) use ($term) {
                $q->where('asunto', 'like', $term)
                    ->orWhere('descripcion', 'like', $term);
            });
        }

        $agendaService = app(AgendaService::class);
        if ($agendaService->usuarioVeSoloSusAsignaciones($viewer)) {
            $query->whereHas('usuariosAsignados', fn ($q) => $q->where('users.id', $viewer->id));
        }

        return $query->orderBy('fecha_inicio')->orderBy('id')->get();
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $byDay
     * @return list<list<array<string, mixed>>>
     */
    private function buildGridWeeks(int $year, int $month, array $byDay, string $tz): array
    {
        $monthStart = Carbon::create($year, $month, 1, 0, 0, 0, $tz)->startOfMonth();
        $pad = (int) $monthStart->format('N') - 1;
        $cur = $monthStart->copy()->subDays($pad);

        $weeks = [];
        for ($w = 0; $w < 6; $w++) {
            $row = [];
            for ($d = 0; $d < 7; $d++) {
                $key = $cur->format('Y-m-d');
                $events = $byDay[$key] ?? [];
                $row[] = [
                    'date' => $key,
                    'in_month' => (int) $cur->month === $month,
                    'day' => $cur->day,
                    'is_today' => $cur->isToday(),
                    'events' => array_slice($events, 0, 3),
                    'events_more' => max(0, count($events) - 3),
                ];
                $cur->addDay();
            }
            $weeks[] = $row;
        }

        return $weeks;
    }
}
