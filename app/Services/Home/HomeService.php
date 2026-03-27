<?php

namespace App\Services\Home;

use App\Services\PersonalNoteService;
use Illuminate\Support\Facades\Auth;

class HomeService
{
    public function __construct(
        private readonly HomeAgendaCalendarService $agendaCalendar,
        private readonly PersonalNoteService $personalNoteService,
    ) {
    }

    public function indexData(): array
    {
        $user = Auth::user();
        $cal = $this->agendaCalendar->calendarDataForUser($user);

        $personalAgendaHomeNotes = $user
            ? $this->personalNoteService->getPersonalNotesScheduledTodayOrTomorrow((int) $user->id)
            : collect();

        $paFoldersForHomeJson = $user
            ? $this->personalNoteService->getFolders((int) $user->id)
            : collect();

        return [
            'pageTitle' => 'Inicio',
            'pageDescription' => 'Panel principal del sistema',
            'topbarNotifications' => [],
            'upcomingEvents' => $cal['upcomingEvents'],
            'pastEvents' => $cal['pastEvents'],
            /** Por día Y-m-d → [{ title, time }, …] — para calendario (viñetas). */
            'agendaDays' => $cal['agendaDays'],
            'personalAgendaHomeNotes' => $personalAgendaHomeNotes,
            'paFoldersForHomeJson' => $paFoldersForHomeJson,
        ];
    }
}
