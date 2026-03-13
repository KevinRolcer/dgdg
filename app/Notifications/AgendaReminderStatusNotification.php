<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AgendaReminderStatusNotification extends Notification
{
    use Queueable;

    protected $agendaId;
    protected $status;
    protected $message;

    /**
     * Create a new notification instance.
     */
    public function __construct($agendaId, $status, $message)
    {
        $this->agendaId = $agendaId;
        $this->status = $status;
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable)
    {
        return [
            'agenda_id' => $this->agendaId,
            'status' => $this->status,
            'message' => $this->message,
        ];
    }
}
