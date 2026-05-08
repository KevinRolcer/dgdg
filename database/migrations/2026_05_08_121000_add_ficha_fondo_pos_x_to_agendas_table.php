<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            if (! Schema::hasColumn('agendas', 'ficha_fondo_pos_x')) {
                $table->unsignedTinyInteger('ficha_fondo_pos_x')->default(50)->after('ficha_orientacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            if (Schema::hasColumn('agendas', 'ficha_fondo_pos_x')) {
                $table->dropColumn('ficha_fondo_pos_x');
            }
        });
    }
};
