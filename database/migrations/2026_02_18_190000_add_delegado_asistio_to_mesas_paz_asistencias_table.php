<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Migración para agregar asistencia global del delegado por sesión.

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mesas_paz_asistencias', function (Blueprint $table) {
            // Campo global (Si/No) replicado en el lote diario para trazabilidad.
            $table->enum('delegado_asistio', ['Si', 'No'])->nullable()->after('presidente');
            $table->index(['delegado_asistio'], 'idx_mpa_delegado_asistio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mesas_paz_asistencias', function (Blueprint $table) {
            $table->dropIndex('idx_mpa_delegado_asistio');
            $table->dropColumn('delegado_asistio');
        });
    }
};
