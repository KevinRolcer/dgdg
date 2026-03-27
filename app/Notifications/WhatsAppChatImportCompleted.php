<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WhatsAppChatImportCompleted extends Notification
{
    use Queueable;

    public function __construct(
        public int $chatId,
        public string $chatTitle,
        public bool $success,
        public ?string $errorMessage = null
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'icon' => $this->success ? 'fa-brands fa-whatsapp' : 'fa-solid fa-circle-exclamation',
            'title' => $this->success
                ? 'Importación de WhatsApp lista: '.$this->chatTitle
                : 'Error al importar WhatsApp',
            'url' => $this->success
                ? route('whatsapp-chats.admin.show', ['chat' => $this->chatId])
                : route('whatsapp-chats.admin.index'),
            'body' => $this->errorMessage,
            'whatsapp_import_success' => $this->success,
            'whatsapp_chat_id' => $this->chatId,
        ];
    }
}
