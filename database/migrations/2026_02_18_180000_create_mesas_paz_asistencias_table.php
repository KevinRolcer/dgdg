<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Migración base de asistencias por municipio.

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mesas_paz_asistencias', function (Blueprint $table) {
            // PK custom según requerimiento funcional.
            $table->bigIncrements('asist_id');

            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('delegado_id')->nullable();
            $table->unsignedBigInteger('microrregion_id')->nullable();
            $table->unsignedBigInteger('municipio_id');

            // Campos de la captura diaria.
            $table->date('fecha_asist');
            $table->enum('presidente', ['Si', 'No', 'Representante']);
            $table->string('asiste', 160)->nullable();
            $table->string('modalidad', 120);
            $table->string('evidencia')->nullable();
            $table->text('acuerdo_observacion')->nullable();

            // created_at único para trazabilidad de captura.
            $table->timestamp('created_at')->useCurrent();

            // Integridad referencial y desempeño de consultas por fecha.
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('delegado_id')->references('id')->on('delegados')->onUpdate('cascade')->onDelete('set null');
            $table->foreign('microrregion_id')->references('id')->on('microrregiones')->onUpdate('cascade')->onDelete('set null');
            $table->foreign('municipio_id')->references('id')->on('municipios')->onUpdate('cascade')->onDelete('restrict');

            $table->index(['user_id', 'fecha_asist'], 'idx_mpa_user_fecha');
            $table->index(['microrregion_id', 'fecha_asist'], 'idx_mpa_micro_fecha');
            $table->index(['municipio_id', 'fecha_asist'], 'idx_mpa_municipio_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mesas_paz_asistencias');
    }
};
