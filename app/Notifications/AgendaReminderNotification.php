<?php

namespace App\Notifications;

use App\Models\Agenda;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AgendaReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $agenda;

    /**
     * Create a new notification instance.
     */
    public function __construct(Agenda $agenda)
    {
        $this->agenda = $agenda;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Recordatorio de Agenda: ' . $this->agenda->asunto)
            ->greeting('Hola, ' . $notifiable->name)
            ->line('Este es un recordatorio para el siguiente asunto en tu agenda:');

        if ($this->agenda->tipo === 'gira') {
            $mail->line('**Tipo:** Gira/Pre-Gira')
                 ->line('**Microrregión:** ' . ($this->agenda->microrregion ?? 'N/A'))
                 ->line('**Municipio:** ' . ($this->agenda->municipio ?? 'N/A'))
                 ->line('**Lugar:** ' . ($this->agenda->lugar ?? 'N/A'));
            
            if ($this->agenda->seguimiento) {
                $mail->line('**Seguimiento:** ' . $this->agenda->seguimiento);
            }
        }

        $mail->line('**Asunto:** ' . $this->agenda->asunto)
             ->line('**Fecha y Hora:** ' . $this->agenda->fecha_inicio->format('d/m/Y') . ($this->agenda->habilitar_hora ? ' ' . $this->agenda->hora : ''))
             ->line($this->agenda->descripcion ?? '')
             ->action('Ver en el sistema', url('/agenda'))
             ->line('También puedes agregarlo a tu Google Calendar usando el siguiente botón:')
             ->action('Agregar a Google Calendar', $this->agenda->getGoogleCalendarUrl())
             ->line('Gracias por estar al pendiente.');

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'agenda_id' => $this->agenda->id,
            'title' => 'Recordatorio de Agenda: ' . $this->agenda->asunto,
            'icon' => 'fa-regular fa-calendar-check',
            'url' => url('/agenda'),
        ];
    }
}
