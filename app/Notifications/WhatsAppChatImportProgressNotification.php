<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\WhatsAppChatArchive;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WhatsAppChatImportProgressNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $whatsappArchiveId,
        public string $zipDisplayName
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public static function formatTitle(int $progress, string $phase): string
    {
        $phase = trim($phase);

        return $phase !== ''
            ? 'WhatsApp: '.$progress.'% — '.$phase
            : 'WhatsApp: '.$progress.'%';
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function sync(User $user, int $archiveId, array $overrides): void
    {
        $n = $user->notifications()
            ->where('type', self::class)
            ->where('data->whatsapp_archive_id', $archiveId)
            ->orderByDesc('created_at')
            ->first();

        if (! $n) {
            return;
        }

        $current = $n->data;
        if (! is_array($current)) {
            $current = [];
        }

        $merged = array_merge($current, $overrides);

        if (($merged['whatsapp_import_status'] ?? 'processing') === 'processing') {
            $merged['title'] = self::formatTitle(
                (int) ($merged['whatsapp_import_progress'] ?? 0),
                (string) ($merged['whatsapp_import_phase'] ?? '')
            );
        }

        $n->update(['data' => $merged]);
    }

    public static function markCompleted(User $user, WhatsAppChatArchive $archive): void
    {
        self::sync($user, (int) $archive->id, [
            'whatsapp_import_status' => 'completed',
            'whatsapp_import_progress' => 100,
            'whatsapp_import_phase' => 'Listo',
            'title' => 'Importación lista: '.$archive->title,
            'url' => route('whatsapp-chats.admin.show', ['chat' => $archive->id]),
            'icon' => 'fa-brands fa-whatsapp',
            'body' => null,
        ]);
    }

    public static function markFailed(User $user, WhatsAppChatArchive $archive, string $message): void
    {
        self::sync($user, (int) $archive->id, [
            'whatsapp_import_status' => 'failed',
            'whatsapp_import_progress' => (int) ($archive->import_progress ?? 0),
            'whatsapp_import_phase' => 'Error',
            'title' => 'Error al importar WhatsApp',
            'url' => route('whatsapp-chats.admin.index'),
            'icon' => 'fa-solid fa-circle-exclamation',
            'body' => $message,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'icon' => 'fa-brands fa-whatsapp',
            'title' => self::formatTitle(0, 'En cola…'),
            'url' => null,
            'body' => null,
            'whatsapp_archive_id' => $this->whatsappArchiveId,
            'whatsapp_import_progress' => 0,
            'whatsapp_import_phase' => 'En cola…',
            'whatsapp_import_status' => 'processing',
            'whatsapp_zip_name' => $this->zipDisplayName,
        ];
    }
}
