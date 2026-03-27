<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppChatArchive;
use App\Notifications\WhatsAppChatImportProgressNotification;

/**
 * Persiste progreso en el registro del chat y sincroniza la notificación en BD (con throttling).
 */
final class WhatsAppImportProgressReporter
{
    private int $lastNotifiedPercent = -100;

    public function __construct(
        private readonly User $user,
        private readonly WhatsAppChatArchive $archive
    ) {}

    /**
     * @return callable(int, string): void
     */
    public function asCallable(): callable
    {
        return function (int $percent, string $phase): void {
            $this->report($percent, $phase);
        };
    }

    public function report(int $percent, string $phase, bool $forceNotification = false): void
    {
        $p = min(100, max(0, $percent));

        $this->archive->forceFill([
            'import_progress' => $p,
            'import_phase' => $phase,
        ])->saveQuietly();

        $delta = abs($p - $this->lastNotifiedPercent);
        if ($forceNotification || $p >= 100 || $p <= 10 || $delta >= 4) {
            $this->lastNotifiedPercent = $p;
            WhatsAppChatImportProgressNotification::sync($this->user, (int) $this->archive->id, [
                'whatsapp_import_progress' => $p,
                'whatsapp_import_phase' => $phase,
                'whatsapp_import_status' => 'processing',
                'title' => WhatsAppChatImportProgressNotification::formatTitle($p, $phase),
                'icon' => 'fa-brands fa-whatsapp',
                'url' => null,
            ]);
        }
    }
}
