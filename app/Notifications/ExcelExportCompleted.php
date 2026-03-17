<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExcelExportCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $fileName,
        public ?string $downloadUrl = null
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'icon' => 'fa-solid fa-file-excel',
            'title' => 'Documento generado exitosamente: '.$this->fileName,
            'url' => $this->downloadUrl,
        ];
    }
}

