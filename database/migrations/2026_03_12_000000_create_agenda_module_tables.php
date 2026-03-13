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
        Schema::create('agendas', function (Blueprint $header) {
            $header->id();
            $header->string('asunto');
            $header->text('descripcion')->nullable();
            $header->date('fecha_inicio');
            $header->date('fecha_fin')->nullable();
            $header->time('hora')->nullable();
            $header->boolean('habilitar_hora')->default(false);
            $header->boolean('repite')->default(false);
            $header->json('dias_repeticion')->nullable();
            $header->integer('recordatorio_horas')->nullable();
            $header->boolean('recordatorio_enviado')->default(false);
            $header->unsignedInteger('creado_por');
            $header->foreign('creado_por')->references('id')->on('users')->onDelete('cascade');
            $header->timestamps();
        });

        Schema::create('agenda_user', function (Blueprint $header) {
            $header->unsignedBigInteger('agenda_id');
            $header->unsignedInteger('user_id');
            $header->foreign('agenda_id')->references('id')->on('agendas')->onDelete('cascade');
            $header->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $header->primary(['agenda_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agenda_user');
        Schema::dropIfExists('agendas');
    }
};
