<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE mesas_paz_asistencias MODIFY presidente ENUM('Si','No','Representante','Ambos') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE mesas_paz_asistencias MODIFY presidente ENUM('Si','No','Representante') NOT NULL");
    }
};
