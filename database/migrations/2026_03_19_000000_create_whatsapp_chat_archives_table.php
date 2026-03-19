<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_chat_archives', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('original_zip_name')->nullable();
            /**
             * Ruta relativa dentro del disco `public` (storage/app/public),
             * apuntando a la carpeta del chat exportado.
             */
            $table->string('storage_root_path');
            /**
             * Lista de archivos HTML relativos dentro del disco `public`.
             * Ej: whatsapp-chats/{uuid}/WhatsApp Chat - X/messages/message_1.html
             */
            $table->json('message_parts')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('imported_at')->useCurrent();

            $table->index(['created_by', 'imported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_chat_archives');
    }
};
