<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Elimina la columna usada solo para el envío de invitaciones por correo (deshabilitado).
     */
    public function up(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            if (Schema::hasColumn('agendas', 'calendar_sent_at')) {
                $table->dropColumn('calendar_sent_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            $table->timestamp('calendar_sent_at')->nullable()->after('direcciones_adicionales');
        });
    }
};
