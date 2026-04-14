<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        if (Schema::hasTable('mesas_paz_asistencias')) {
            DB::table('mesas_paz_asistencias')
                ->whereNotIn('presidente', ['Si', 'No', 'Representante', 'Ambos', 'S/R'])
                ->update(['presidente' => 'No']);

            DB::table('mesas_paz_asistencias')
                ->whereNotNull('delegado_asistio')
                ->whereNotIn('delegado_asistio', ['Si', 'No', 'S/R'])
                ->update(['delegado_asistio' => 'No']);

            DB::statement('ALTER TABLE mesas_paz_asistencias MODIFY presidente VARCHAR(20) NOT NULL');
            DB::statement('ALTER TABLE mesas_paz_asistencias MODIFY delegado_asistio VARCHAR(10) NULL');
        }

        if (Schema::hasTable('personal_notes')) {
            DB::table('personal_notes')
                ->whereNull('priority')
                ->orWhereNotIn('priority', ['none', 'low', 'medium', 'high'])
                ->update(['priority' => 'none']);

            DB::statement("ALTER TABLE personal_notes MODIFY priority VARCHAR(10) NOT NULL DEFAULT 'none'");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        if (Schema::hasTable('mesas_paz_asistencias')) {
            DB::table('mesas_paz_asistencias')
                ->whereNotIn('presidente', ['Si', 'No', 'Representante', 'Ambos', 'S/R'])
                ->update(['presidente' => 'No']);

            DB::table('mesas_paz_asistencias')
                ->whereNotNull('delegado_asistio')
                ->whereNotIn('delegado_asistio', ['Si', 'No', 'S/R'])
                ->update(['delegado_asistio' => 'No']);

            DB::statement("ALTER TABLE mesas_paz_asistencias MODIFY presidente ENUM('Si','No','Representante','Ambos','S/R') NOT NULL");
            DB::statement("ALTER TABLE mesas_paz_asistencias MODIFY delegado_asistio ENUM('Si','No','S/R') NULL");
        }

        if (Schema::hasTable('personal_notes')) {
            DB::table('personal_notes')
                ->whereNull('priority')
                ->orWhereNotIn('priority', ['none', 'low', 'medium', 'high'])
                ->update(['priority' => 'none']);

            DB::statement("ALTER TABLE personal_notes MODIFY priority ENUM('none','low','medium','high') NOT NULL DEFAULT 'none'");
        }
    }
};
