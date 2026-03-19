<?php

namespace App\Console\Commands;

use App\Models\WhatsAppChatArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

class WhatsAppChatsBackupCommand extends Command
{
    protected $signature = 'whatsapp-chats:backup {--chat= : ID de un chat concreto}';

    protected $description = 'Copia respaldos ZIP (archivos ya cifrados en disco) a la carpeta configurada en whatsapp_chats.backup_path';

    public function handle(): int
    {
        $destBase = (string) config('whatsapp_chats.backup_path');
        File::ensureDirectoryExists($destBase);

        $dayFolder = $destBase.DIRECTORY_SEPARATOR.now()->format('Y-m-d');
        File::ensureDirectoryExists($dayFolder);

        $query = WhatsAppChatArchive::query()->orderBy('id');
        if ($this->option('chat')) {
            $query->whereKey((int) $this->option('chat'));
        }

        $count = 0;
        foreach ($query->cursor() as $chat) {
            /** @var WhatsAppChatArchive $chat */
            $disk = $chat->disk();
            $root = trim((string) $chat->storage_root_path, '/');
            if ($root === '' || ! $disk->exists($root)) {
                continue;
            }

            $zipName = 'chat_'.$chat->id.'_'.preg_replace('/[^a-zA-Z0-9_-]+/', '_', $chat->title).'.zip';
            $zipPath = $dayFolder.DIRECTORY_SEPARATOR.$zipName;

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $this->error("No se pudo crear ZIP: {$zipPath}");

                return self::FAILURE;
            }

            foreach ($disk->allFiles($root) as $rel) {
                $rel = str_replace('\\', '/', (string) $rel);
                $zip->addFromString($rel, $disk->get($rel));
            }

            $zip->close();
            $count++;
            $this->line("Respaldo: {$zipPath}");
        }

        $this->info("Listo. Chats respaldados: {$count} (contenido en disco ya va cifrado WA1 si is_encrypted=1).");

        return self::SUCCESS;
    }
}
