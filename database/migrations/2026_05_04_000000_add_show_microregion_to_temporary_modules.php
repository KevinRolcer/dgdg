<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('temporary_modules', function (Blueprint $table) {
            $table->boolean('show_microregion')->default(true)->after('registration_scope');
        });
    }

    public function down(): void
    {
        Schema::table('temporary_modules', function (Blueprint $table) {
            $table->dropColumn('show_microregion');
        });
    }
};
