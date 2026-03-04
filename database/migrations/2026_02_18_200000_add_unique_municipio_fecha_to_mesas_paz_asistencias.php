<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Migración para garantizar una sola asistencia por municipio por día.

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mesas_paz_asistencias', function (Blueprint $table) {
            // Regla de negocio en capa de BD para prevenir duplicados por concurrencia.
            $table->unique(['municipio_id', 'fecha_asist'], 'uq_mpa_municipio_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mesas_paz_asistencias', function (Blueprint $table) {
            $table->dropUnique('uq_mpa_municipio_fecha');
        });
    }
};
