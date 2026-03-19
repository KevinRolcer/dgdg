<?php

use App\Models\WhatsAppChatArchive;
use App\Services\WhatsApp\WhatsAppChatPathNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_chat_archives', function (Blueprint $table) {
            $table->unsignedInteger('message_parts_count')->nullable()->after('message_parts');
        });

        WhatsAppChatArchive::query()->orderBy('id')->chunkById(50, function ($chats) {
            foreach ($chats as $chat) {
                $parts = is_array($chat->message_parts) ? $chat->message_parts : [];
                $norm = WhatsAppChatPathNormalizer::normalizeStoragePaths($parts, (string) $chat->storage_root_path);
                $chat->message_parts = $norm;
                $chat->message_parts_count = count($norm);
                $chat->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_chat_archives', function (Blueprint $table) {
            $table->dropColumn('message_parts_count');
        });
    }
};
