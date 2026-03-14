<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temporary_modules', function (Blueprint $table): void {
            $table->timestamp('exported_at')->nullable()->after('created_by');
            $table->json('seed_discard_log')->nullable()->after('exported_at');
        });
    }

    public function down(): void
    {
        Schema::table('temporary_modules', function (Blueprint $table): void {
            $table->dropColumn(['exported_at', 'seed_discard_log']);
        });
    }
};
