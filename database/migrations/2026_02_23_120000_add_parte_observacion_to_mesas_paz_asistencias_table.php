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
        Schema::table('mesas_paz_asistencias', function (Blueprint $table) {
            $table->text('parte_observacion')->nullable()->after('evidencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mesas_paz_asistencias', function (Blueprint $table) {
            $table->dropColumn('parte_observacion');
        });
    }
};
