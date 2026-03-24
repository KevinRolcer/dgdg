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
     *   today: string,
     *   fichas_cards_included: bool
     * }
     */
    public function buildMonthPayload(User $viewer, int $year, int $month, array $filters, bool $includeFichasCards = true): array
    {
        $tz = config('app.timezone', 'UTC');

        $monthStart = Carbon::create($year, $month, 1, 0, 0, 0, $tz)->startOfMonth()->locale('es');
        $monthEnd = $monthStart->copy()->endOfMonth();

        $agendas = $this->queryAgendasForMonth($viewer, $monthStart, $monthEnd, $filters);
        $preparedAgendaMonthData = $this->prepareAgendaMonthData($agendas, $year, $month);

        $byDay = [];
        foreach ($preparedAgendaMonthData as $item) {
            $agenda = $item['agenda'];
            $occs = $item['occurrences'];
            foreach ($occs as $occ) {
                $key = $occ['date']->format('Y-m-d');
                if (! isset($byDay[$key])) {
                    $byDay[$key] = [];
                }
                $byDay[$key][] = [
                    'agenda_id' => $agenda->id,
                    'title' => $item['display_title'],
                    'time' => $occ['time_label'],
                    'kind' => $this->cardKindForFicha($agenda),
                ];
            }
        }

        foreach ($byDay as $k => $items) {
            usort($items, fn ($a, $b) => strcmp((string) $a['time'], (string) $b['time']));
            $byDay[$k] = $items;
        }

        $gridWeeks = $this->buildGridWeeks($year, $month, $byDay, $tz);

        $listRows = [];
        foreach ($preparedAgendaMonthData as $item) {
            $agenda = $item['agenda'];
            $occs = $item['occurrences'];
            foreach ($occs as $occ) {
                $d = $occ['date'];
                $listRows[] = [
                    'agenda_id' => $agenda->id,
                    'date' => $d->format('Y-m-d'),
                    'weekday' => mb_convert_case($d->copy()->locale('es')->isoFormat('ddd'), MB_CASE_TITLE, 'UTF-8'),
                    'day' => $d->day,
                    'time_label' => $occ['time_label'],
                    'title' => $item['display_title'],
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

        $cards = $includeFichasCards ? $this->buildFichasCardsFromPreparedMonthData($preparedAgendaMonthData) : [];

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
            'fichas_cards_included' => $includeFichasCards,
        ];
    }

    /**
     * Tarjetas de la pestaña Fichas para un mes (misma forma que en buildMonthPayload).
     *
     * @param  array{clasificacion?: string, buscar?: string}  $filters
     * @return list<array<string, mixed>>
     */
    public function buildFichasCardsForMonth(User $viewer, int $year, int $month, array $filters): array
    {
        $tz = config('app.timezone', 'UTC');
        $monthStart = Carbon::create($year, $month, 1, 0, 0, 0, $tz)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $agendas = $this->queryAgendasForMonth($viewer, $monthStart, $monthEnd, $filters);

        return $this->buildFichasCardsFromPreparedMonthData($this->prepareAgendaMonthData($agendas, $year, $month));
    }

    /**
     * @param  Collection<int, Agenda>  $agendas
     * @return list<array{agenda: Agenda, occurrences: list<array{date: \Carbon\Carbon, starts_at: \Carbon\Carbon, ends_at: \Carbon\Carbon, time_label: string}>, display_title: string}>
     */
    private function prepareAgendaMonthData(Collection $agendas, int $year, int $month): array
    {
        $prepared = [];
        foreach ($agendas as $agenda) {
            $occurrences = $this->homeAgendaCalendar->occurrencesInCalendarMonth($agenda, $year, $month);
            if ($occurrences === []) {
                continue;
            }

            $prepared[] = [
                'agenda' => $agenda,
                'occurrences' => $occurrences,
                'display_title' => $this->homeAgendaCalendar->displayTitle($agenda),
            ];
        }

        return $prepared;
    }

    /**
     * @param  list<array{agenda: Agenda, occurrences: list<array{date: \Carbon\Carbon, starts_at: \Carbon\Carbon, ends_at: \Carbon\Carbon, time_label: string}>, display_title: string}>  $preparedAgendaMonthData
     * @return list<array<string, mixed>>
     */
    private function buildFichasCardsFromPreparedMonthData(array $preparedAgendaMonthData): array
    {
        $cards = [];
        foreach ($preparedAgendaMonthData as $item) {
            $agenda = $item['agenda'];
            $occs = $item['occurrences'];

            // Línea bajo el día grande en fichas: solo mes y año de esa misma fecha (el día ya va aparte).
            $headAnchor = $occs[0]['date']->copy()->locale('es');
            $monthYearLabel = $headAnchor->translatedFormat('F \d\e Y');

            $descBody = $agenda->descripcionPrimerElementoSinAforo();
            $descNorm = $descBody !== '' ? $this->sentenceCaseIfAllCaps($descBody) : '';
            $desc = $descNorm !== '' ? Str::limit($descNorm, 320) : '';

            $asunto = trim((string) ($agenda->asunto ?? ''));
            $titleRaw = $asunto !== '' ? $asunto : ($agenda->tipo === 'gira' ? 'Sin título' : 'Asunto');
            $titleFicha = $this->sentenceCaseIfAllCaps($titleRaw);

            $lugarRaw = trim((string) ($agenda->lugar ?? ''));
            $lugarCard = $lugarRaw !== '' ? $this->sentenceCaseIfAllCaps($lugarRaw) : '';

            $cards[] = [
                'agenda_id' => $agenda->id,
                'month_year_label' => $monthYearLabel,
                'badge_day' => $occs[0]['date']->day,
                'hora_ficha' => $this->fichaHoraLabel12hForGira($agenda),
                'title' => $titleFicha,
                'lugar' => $lugarCard,
                'descripcion' => $desc,
                'aforo_label' => $agenda->aforoEtiquetaTarjeta(),
                'kind' => $this->cardKindForFicha($agenda),
            ];
        }

        return $cards;
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

    /**
     * Si el texto parece escrito todo en mayúsculas (sin letras minúsculas), pasarlo a oración:
     * primera letra en mayúscula y el resto en minúsculas (UTF-8).
     */
    private function sentenceCaseIfAllCaps(string $text): string
    {
        $t = trim($text);
        if ($t === '') {
            return $text;
        }
        if (preg_match('/\p{Ll}/u', $t)) {
            return $text;
        }
        if (! preg_match('/\p{L}/u', $t)) {
            return $text;
        }
        if (! preg_match('/\p{Lu}/u', $t)) {
            return $text;
        }

        $lower = mb_strtolower($t, 'UTF-8');
        $first = mb_strtoupper(mb_substr($lower, 0, 1, 'UTF-8'), 'UTF-8');
        $rest = mb_substr($lower, 1, null, 'UTF-8');

        return $first.$rest;
    }

    /**
     * Tipo de ficha para textura de encabezado: agenda (asuntos) | pre_gira | gira.
     */
    private function cardKindForFicha(Agenda $agenda): string
    {
        if ($agenda->tipo === 'gira') {
            return strtolower((string) ($agenda->subtipo ?? '')) === 'pre-gira' ? 'pre_gira' : 'gira';
        }

        return 'agenda';
    }

    /**
     * Hora en 12 h (p. ej. 06:00 pm) solo para giras y pre-giras con hora habilitada.
     */
    private function fichaHoraLabel12hForGira(Agenda $agenda): ?string
    {
        if ($agenda->tipo !== 'gira') {
            return null;
        }
        if (! $agenda->habilitar_hora) {
            return null;
        }
        $raw = trim((string) ($agenda->hora ?? ''));
        if ($raw === '') {
            return null;
        }
        $tz = config('app.timezone', 'UTC');
        $h = $raw;
        if (substr_count($h, ':') === 1) {
            $h .= ':00';
        }
        try {
            return Carbon::parse('2000-01-01 '.$h, $tz)->format('h:i a');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Fichas para PDF: incluye contenido completo salvo líneas de encargado/creador (sanitizado).
     *
     * @param  array{clasificacion?: string, buscar?: string}  $filters
     * @param  list<array{0: int, 1: int}>|null  $customMonths  pares [año, mes]
     * @return list<array<string, mixed>>
     */
    public function buildCardsForFichasPdf(User $viewer, array $filters, string $scope, ?int $year = null, ?int $month = null, ?array $customMonths = null): array
    {
        $tz = config('app.timezone', 'UTC');

        if ($scope === 'all') {
            $agendas = $this->queryAgendasAllForPdf($viewer, $filters);
            $cards = [];
            foreach ($agendas as $agenda) {
                $fi = $agenda->fecha_inicio instanceof Carbon
                    ? $agenda->fecha_inicio->copy()->timezone($tz)->startOfDay()
                    : Carbon::parse($agenda->fecha_inicio, $tz)->startOfDay();
                $cards[] = $this->pdfCardFromAgenda($agenda, $fi->copy()->locale('es'));
            }
            usort($cards, fn ($a, $b) => strcmp((string) $a['sort_key'], (string) $b['sort_key']));

            return $cards;
        }

        if ($scope === 'current_month' && $year !== null && $month !== null) {
            $monthStart = Carbon::create($year, $month, 1, 0, 0, 0, $tz)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $agendas = $this->queryAgendasForMonth($viewer, $monthStart, $monthEnd, $filters);
            $cards = [];
            foreach ($agendas as $agenda) {
                $occs = $this->homeAgendaCalendar->occurrencesInCalendarMonth($agenda, $year, $month);
                if (count($occs) === 0) {
                    continue;
                }
                $cards[] = $this->pdfCardFromAgenda($agenda, $occs[0]['date']->copy()->locale('es'));
            }
            usort($cards, fn ($a, $b) => strcmp((string) $a['sort_key'], (string) $b['sort_key']));

            return $cards;
        }

        if ($scope === 'custom_months' && is_array($customMonths) && $customMonths !== []) {
            $merged = [];
            foreach ($customMonths as $pair) {
                $y = (int) ($pair[0] ?? 0);
                $m = (int) ($pair[1] ?? 0);
                if ($y < 2000 || $y > 2100 || $m < 1 || $m > 12) {
                    continue;
                }
                $monthStart = Carbon::create($y, $m, 1, 0, 0, 0, $tz)->startOfMonth();
                $monthEnd = $monthStart->copy()->endOfMonth();
                $agendas = $this->queryAgendasForMonth($viewer, $monthStart, $monthEnd, $filters);
                foreach ($agendas as $agenda) {
                    $occs = $this->homeAgendaCalendar->occurrencesInCalendarMonth($agenda, $y, $m);
                    if (count($occs) === 0) {
                        continue;
                    }
                    $card = $this->pdfCardFromAgenda($agenda, $occs[0]['date']->copy()->locale('es'));
                    $id = $card['agenda_id'];
                    if (! isset($merged[$id]) || strcmp((string) $card['sort_key'], (string) $merged[$id]['sort_key']) < 0) {
                        $merged[$id] = $card;
                    }
                }
            }
            $cards = array_values($merged);
            usort($cards, fn ($a, $b) => strcmp((string) $a['sort_key'], (string) $b['sort_key']));

            return $cards;
        }

        return [];
    }

    /**
     * Ficha única para vista previa/descarga individual.
     *
     * @return array<string, mixed>
     */
    public function buildSingleFichaCardForAgenda(Agenda $agenda): array
    {
        $tz = config('app.timezone', 'UTC');
        $anchor = $agenda->fecha_inicio instanceof Carbon
            ? $agenda->fecha_inicio->copy()->timezone($tz)
            : Carbon::parse((string) $agenda->fecha_inicio, $tz);

        return $this->pdfCardFromAgenda($agenda, $anchor->copy()->locale('es'));
    }

    /**
     * @param  array{clasificacion?: string, buscar?: string}  $filters
     * @return Collection<int, Agenda>
     */
    private function queryAgendasAllForPdf(User $viewer, array $filters): Collection
    {
        $tz = config('app.timezone', 'UTC');
        $wideStart = Carbon::create(2000, 1, 1, 0, 0, 0, $tz)->startOfMonth();
        $wideEnd = Carbon::create(2100, 12, 31, 0, 0, 0, $tz)->endOfMonth();

        return $this->queryAgendasForMonth($viewer, $wideStart, $wideEnd, $filters);
    }

    /**
     * @param  \Carbon\Carbon  $anchor  fecha de referencia para día y mes/año en la ficha
     * @return array<string, mixed>
     */
    private function pdfCardFromAgenda(Agenda $agenda, Carbon $anchor): array
    {
        $monthYearLabel = mb_convert_case($anchor->translatedFormat('F Y'), MB_CASE_TITLE, 'UTF-8');

        $descBody = trim(Agenda::textoParaPdfSinMetaUsuarios($agenda->descripcionSinLineaAforo()));
        $desc = $descBody !== '' ? Str::limit($descBody, 4000) : '';

        $asunto = trim((string) ($agenda->asunto ?? ''));
        $titleRaw = $asunto !== '' ? $asunto : ($agenda->tipo === 'gira' ? 'Sin título' : 'Asunto');
        $titleFicha = $this->sentenceCaseIfAllCaps($titleRaw);

        $lugarClean = trim(Agenda::textoParaPdfSinMetaUsuarios(trim((string) ($agenda->lugar ?? ''))));
        $lugarCard = $lugarClean !== '' ? $this->sentenceCaseIfAllCaps($lugarClean) : '';

        return [
            'agenda_id' => $agenda->id,
            'month_year_label' => $monthYearLabel,
            'badge_day' => $anchor->day,
            'hora_ficha' => $this->fichaHoraLabel12hForGira($agenda),
            'title' => $titleFicha,
            'lugar' => $lugarCard,
            'descripcion' => $desc,
            'aforo_label' => $agenda->aforoEtiquetaTarjeta(),
            'kind' => $this->cardKindForFicha($agenda),
            'sort_key' => $anchor->format('Y-m-d'),
        ];
    }
}
