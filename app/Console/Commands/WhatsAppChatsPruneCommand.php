<?php

namespace App\Console\Commands;

use App\Models\WhatsAppChatArchive;
use Illuminate\Console\Command;

class WhatsAppChatsPruneCommand extends Command
{
    protected $signature = 'whatsapp-chats:prune';

    protected $description = 'Elimina exportaciones de chat más antiguas que whatsapp_chats.retention_days';

    public function handle(): int
    {
        $days = (int) config('whatsapp_chats.retention_days', 0);
        if ($days <= 0) {
            $this->info('Retención desactivada (WHATSAPP_CHATS_RETENTION_DAYS=0).');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $ids = WhatsAppChatArchive::query()
            ->where('import_status', WhatsAppChatArchive::IMPORT_STATUS_READY)
            ->where('imported_at', '<', $cutoff)
            ->pluck('id');

        $removed = 0;
        foreach ($ids as $id) {
            $chat = WhatsAppChatArchive::query()->find($id);
            if (! $chat) {
                continue;
            }
            $storageRoot = trim((string) $chat->storage_root_path, '/');
            if ($storageRoot !== '') {
                $chat->disk()->deleteDirectory($storageRoot);
                $segments = explode('/', str_replace('\\', '/', $storageRoot));
                if (count($segments) >= 2) {
                    $container = implode('/', array_slice($segments, 0, 2));
                    if ($container !== '' && count($chat->disk()->allFiles($container)) === 0) {
                        $chat->disk()->deleteDirectory($container);
                    }
                }
            }
            $chat->delete();
            $removed++;
        }

        $this->info("Eliminados por retención ({$days} días): {$removed}");

        return self::SUCCESS;
    }
}
