<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_chat_archives', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_chat_archives', 'avatar_file')) {
                $table->string('avatar_file')->nullable()->after('storage_root_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_chat_archives', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_chat_archives', 'avatar_file')) {
                $table->dropColumn('avatar_file');
            }
        });
    }
};

