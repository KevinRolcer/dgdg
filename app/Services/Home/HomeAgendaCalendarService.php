<?php

namespace App\Services\Home;

use App\Models\Agenda;
use App\Models\User;
use Carbon\Carbon;
/**
 * Eventos de Agenda Directiva visibles en inicio: asignados al usuario o creados por él.
 */
class HomeAgendaCalendarService
{
    private const WEEKDAY_MAP = [
        'lunes' => 1, 'martes' => 2, 'miercoles' => 3, 'miércoles' => 3,
        'jueves' => 4, 'viernes' => 5, 'sabado' => 6, 'sábado' => 6, 'domingo' => 7,
    ];

    /**
     * @return array{
     *   agendaDays: array<string, list<array{title: string, time: string}>>,
     *   upcomingEvents: list<array{summary: string, starts_at: \Carbon\Carbon, ends_at: \Carbon\Carbon}>,
     *   pastEvents: list<array{summary: string, starts_at: \Carbon\Carbon, ends_at: \Carbon\Carbon}>
     * }
     */
    public function calendarDataForUser(?User $user): array
    {
        if (!$user) {
            return ['agendaDays' => [], 'upcomingEvents' => [], 'pastEvents' => []];
        }

        // Asignado en agenda_user O quien creó el registro (administrativo siempre ve lo que crea)
        $agendas = Agenda::query()
            ->activas()
            ->where(function ($q) use ($user) {
                $q->where('creado_por', $user->id)
                    ->orWhereHas('usuariosAsignados', fn ($q2) => $q2->where('users.id', $user->id));
            })
            ->orderBy('fecha_inicio')
            ->get()
            ->unique('id')
            ->values();

        $tz = config('app.timezone', 'UTC');
        $now = Carbon::now($tz);

        $byDay = [];
        $occurrences = [];

        foreach ($agendas as $agenda) {
            if (!$agenda->fecha_inicio) {
                continue;
            }
            try {
                $occurrencesForAgenda = $this->expandOccurrences($agenda, $tz);
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($occurrencesForAgenda as $occ) {
                $dayKey = $occ['date']->format('Y-m-d');
                $title = $this->displayTitle($agenda);
                $time = $occ['time_label'];

                if (!isset($byDay[$dayKey])) {
                    $byDay[$dayKey] = [];
                }
                $byDay[$dayKey][] = ['title' => $title, 'time' => $time];

                $occurrences[] = [
                    'summary' => $title,
                    'starts_at' => $occ['starts_at'],
                    'ends_at' => $occ['ends_at'],
                ];
            }
        }

        foreach ($byDay as $k => $items) {
            $seen = [];
            $uniq = [];
            foreach ($items as $item) {
                $sig = $item['title'] . "\0" . $item['time'];
                if (isset($seen[$sig])) {
                    continue;
                }
                $seen[$sig] = true;
                $uniq[] = $item;
            }
            $byDay[$k] = $uniq;
        }

        usort($occurrences, function ($a, $b) {
            $ta = $a['starts_at'] instanceof Carbon ? $a['starts_at']->getTimestamp() : 0;
            $tb = $b['starts_at'] instanceof Carbon ? $b['starts_at']->getTimestamp() : 0;

            return $ta <=> $tb;
        });

        $upcoming = array_values(array_filter($occurrences, function ($o) use ($now) {
            if (!($o['starts_at'] instanceof Carbon)) {
                return false;
            }

            return $o['starts_at']->gte($now);
        }));
        $past = array_values(array_filter($occurrences, function ($o) use ($now) {
            if (!($o['starts_at'] instanceof Carbon)) {
                return false;
            }

            return $o['starts_at']->lt($now);
        }));
        usort($past, function ($a, $b) {
            $ta = $a['starts_at'] instanceof Carbon ? $a['starts_at']->getTimestamp() : 0;
            $tb = $b['starts_at'] instanceof Carbon ? $b['starts_at']->getTimestamp() : 0;

            return $tb <=> $ta;
        });
        $past = array_slice($past, 0, 5);

        return [
            'agendaDays' => $byDay,
            'upcomingEvents' => $upcoming,
            'pastEvents' => $past,
        ];
    }

    private function displayTitle(Agenda $agenda): string
    {
        $asunto = trim((string) ($agenda->asunto ?? ''));
        if ($agenda->tipo === 'gira') {
            $pre = (strtolower((string) ($agenda->subtipo ?? '')) === 'pre-gira') ? 'Pre-gira — ' : 'Gira — ';

            return $pre . ($asunto !== '' ? $asunto : 'Sin título');
        }

        return $asunto !== '' ? $asunto : 'Asunto';
    }

    /**
     * @return list<array{date: \Carbon\Carbon, starts_at: \Carbon\Carbon, ends_at: \Carbon\Carbon, time_label: string}>
     */
    private function expandOccurrences(Agenda $agenda, string $tz): array
    {
        $fechaInicio = $agenda->fecha_inicio;
        if (!$fechaInicio instanceof Carbon) {
            $fechaInicio = Carbon::parse($fechaInicio, $tz);
        }
        $start = $fechaInicio->copy()->timezone($tz)->startOfDay();
        $fin = $agenda->fecha_fin ?? $agenda->fecha_inicio;
        $end = $fin instanceof Carbon ? $fin->copy()->timezone($tz)->startOfDay() : Carbon::parse($fin, $tz)->startOfDay();
        if ($end->lt($start)) {
            $end = $start->copy();
        }

        $diasRaw = $agenda->dias_repeticion ?? [];
        $dias = is_array($diasRaw) ? $diasRaw : [];
        $repite = (bool) $agenda->repite && count($dias) > 0;

        $timeLabel = 'Todo el día';
        $hour = null;
        if ($agenda->habilitar_hora && !empty($agenda->hora)) {
            $h = trim((string) $agenda->hora);
            if (substr_count($h, ':') === 1) {
                $h .= ':00';
            }
            try {
                $hour = Carbon::parse('2000-01-01 ' . $h, $tz)->format('H:i');
                $timeLabel = $hour;
            } catch (\Throwable) {
                $hour = null;
            }
        }

        $out = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if ($repite) {
                $iso = (int) $d->format('N');
                $match = false;
                foreach ($dias as $dia) {
                    if (is_object($dia) || is_array($dia)) {
                        continue;
                    }
                    if (is_int($dia) || (is_string($dia) && ctype_digit($dia))) {
                        if ((int) $dia === $iso) {
                            $match = true;
                            break;
                        }
                    }
                    if (is_string($dia)) {
                        $n = self::WEEKDAY_MAP[strtolower(trim($dia))] ?? 0;
                        if ($n === $iso) {
                            $match = true;
                            break;
                        }
                    }
                }
                if (!$match) {
                    continue;
                }
            }

            $day = $d->copy();
            if ($hour !== null) {
                $startsAt = Carbon::parse($day->format('Y-m-d') . ' ' . $hour . ':00', $tz);
                $endsAt = $startsAt->copy()->addHour();
            } else {
                $startsAt = $day->copy()->startOfDay();
                $endsAt = $day->copy()->endOfDay();
            }

            $out[] = [
                'date' => $day,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'time_label' => $timeLabel,
            ];
        }

        return $out;
    }
}
