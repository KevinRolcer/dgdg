<?php

namespace App\Services\Home;

use Illuminate\Support\Facades\Auth;

class HomeService
{
    public function __construct(
        private readonly HomeAgendaCalendarService $agendaCalendar
    ) {
    }

    public function indexData(): array
    {
        $user = Auth::user();
        $cal = $this->agendaCalendar->calendarDataForUser($user);

        return [
            'pageTitle' => 'Inicio',
            'pageDescription' => 'Panel principal del sistema',
            'topbarNotifications' => [],
            'upcomingEvents' => $cal['upcomingEvents'],
            'pastEvents' => $cal['pastEvents'],
            /** Por día Y-m-d → [{ title, time }, …] — para calendario (viñetas). */
            'agendaDays' => $cal['agendaDays'],
        ];
    }
}
