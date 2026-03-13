<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            if (!Schema::hasColumn('agendas', 'microrregion')) {
                $table->string('microrregion', 10)->nullable()->after('tipo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            if (Schema::hasColumn('agendas', 'microrregion')) {
                $table->dropColumn('microrregion');
            }
        });
    }
};
