<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * S/R (sin reporte del delegado): valores permitidos en ENUM de MySQL.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE mesas_paz_asistencias MODIFY presidente ENUM('Si','No','Representante','Ambos','S/R') NOT NULL");
        DB::statement("ALTER TABLE mesas_paz_asistencias MODIFY delegado_asistio ENUM('Si','No','S/R') NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE mesas_paz_asistencias SET presidente = 'No' WHERE presidente = 'S/R'");
        DB::statement("UPDATE mesas_paz_asistencias SET delegado_asistio = 'No' WHERE delegado_asistio = 'S/R'");
        DB::statement("ALTER TABLE mesas_paz_asistencias MODIFY presidente ENUM('Si','No','Representante','Ambos') NOT NULL");
        DB::statement("ALTER TABLE mesas_paz_asistencias MODIFY delegado_asistio ENUM('Si','No') NULL");
    }
};
