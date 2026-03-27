<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_chat_archives', function (Blueprint $table) {
            $table->unsignedTinyInteger('import_progress')->default(0)->after('import_error');
            $table->string('import_phase', 255)->nullable()->after('import_progress');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_chat_archives', function (Blueprint $table) {
            $table->dropColumn(['import_progress', 'import_phase']);
        });
    }
};
