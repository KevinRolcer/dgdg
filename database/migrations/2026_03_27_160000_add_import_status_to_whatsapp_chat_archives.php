<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_chat_archives', function (Blueprint $table) {
            $table->string('import_status', 32)->default('ready')->after('storage_disk');
            $table->text('import_error')->nullable()->after('import_status');
        });

        DB::table('whatsapp_chat_archives')->update(['import_status' => 'ready']);
    }

    public function down(): void
    {
        Schema::table('whatsapp_chat_archives', function (Blueprint $table) {
            $table->dropColumn(['import_status', 'import_error']);
        });
    }
};
