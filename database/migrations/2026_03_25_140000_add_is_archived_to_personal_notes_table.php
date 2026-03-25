<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('personal_notes', 'is_archived')) {
            Schema::table('personal_notes', function (Blueprint $table) {
                $table->boolean('is_archived')->default(false)->after('scheduled_time');
            });
        }
    }

    public function down(): void
    {
        Schema::table('personal_notes', function (Blueprint $table) {
            $table->dropColumn('is_archived');
        });
    }
};
