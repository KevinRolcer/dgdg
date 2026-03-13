<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            if (!Schema::hasColumn('agendas', 'recordatorio_minutos')) {
                $table->unsignedSmallInteger('recordatorio_minutos')->nullable()->default(30)->after('dias_repeticion');
            }
        });

        if (Schema::hasColumn('agendas', 'recordatorio_horas')) {
            DB::table('agendas')
                ->whereNotNull('recordatorio_horas')
                ->update(['recordatorio_minutos' => DB::raw('recordatorio_horas * 60')]);
            Schema::table('agendas', function (Blueprint $table) {
                $table->dropColumn('recordatorio_horas');
            });
        }
    }

    public function down(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            if (!Schema::hasColumn('agendas', 'recordatorio_horas')) {
                $table->integer('recordatorio_horas')->nullable()->after('dias_repeticion');
            }
        });
        if (Schema::hasColumn('agendas', 'recordatorio_minutos')) {
            DB::table('agendas')
                ->whereNotNull('recordatorio_minutos')
                ->update(['recordatorio_horas' => DB::raw('recordatorio_minutos DIV 60')]);
            Schema::table('agendas', function (Blueprint $table) {
                $table->dropColumn('recordatorio_minutos');
            });
        }
    }
};
