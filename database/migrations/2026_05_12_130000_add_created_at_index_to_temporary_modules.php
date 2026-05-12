<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temporary_modules', function (Blueprint $table): void {
            $table->index('created_at', 'temporary_modules_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('temporary_modules', function (Blueprint $table): void {
            $table->dropIndex('temporary_modules_created_at_index');
        });
    }
};
