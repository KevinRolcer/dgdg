<?php

namespace App\Jobs;

use App\Models\Agenda;
use App\Notifications\AgendaReminderNotification;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SendAgendaRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $now = Carbon::now();

        // Find agendas that:
        // 1. Have a reminder configured (recordatorio_minutos is not null)
        // 2. Haven't been notified yet (recordatorio_enviado is false)
        // 3. The scheduled time - recordatorio_minutos is before or equal to now
        $agendas = Agenda::whereNotNull('recordatorio_minutos')
            ->where('recordatorio_enviado', false)
            ->get();

        foreach ($agendas as $agenda) {
            $startDateTime = Carbon::parse($agenda->fecha_inicio->format('Y-m-d') . ($agenda->habilitar_hora ? ' ' . $agenda->hora : ' 00:00:00'));
            $reminderTime = $startDateTime->copy()->subMinutes((int) $agenda->recordatorio_minutos);

            if ($now->greaterThanOrEqualTo($reminderTime)) {
                // Notify the creator
                $agenda->creador->notify(new AgendaReminderNotification($agenda));

                // Notify assigned users
                Notification::send($agenda->usuariosAsignados, new AgendaReminderNotification($agenda));

                // Notify extra addresses
                if (!empty($agenda->direcciones_adicionales)) {
                    foreach ($agenda->direcciones_adicionales as $email) {
                        Notification::route('mail', $email)->notify(new AgendaReminderNotification($agenda));
                    }
                }

                // Mark as sent
                $agenda->update(['recordatorio_enviado' => true]);
                // Notificación de estado
                try {
                    $agenda->creador->notify(new \App\Notifications\AgendaReminderStatusNotification($agenda->id, 'success', 'Correo de recordatorio enviado correctamente.'));
                    foreach ($agenda->usuariosAsignados as $usuario) {
                        $usuario->notify(new \App\Notifications\AgendaReminderStatusNotification($agenda->id, 'success', 'Correo de recordatorio enviado correctamente.'));
                    }
                } catch (\Exception $e) {
                    $agenda->creador->notify(new \App\Notifications\AgendaReminderStatusNotification($agenda->id, 'error', 'Error al enviar el correo de recordatorio: ' . $e->getMessage()));
                    foreach ($agenda->usuariosAsignados as $usuario) {
                        $usuario->notify(new \App\Notifications\AgendaReminderStatusNotification($agenda->id, 'error', 'Error al enviar el correo de recordatorio: ' . $e->getMessage()));
                    }
                }
            }
        }
    }
}
