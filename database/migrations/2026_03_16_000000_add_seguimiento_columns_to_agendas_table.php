<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            if (!Schema::hasColumn('agendas', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('id');
                $table->foreign('parent_id')->references('id')->on('agendas')->nullOnDelete();
            }
            if (!Schema::hasColumn('agendas', 'estado_seguimiento')) {
                $table->string('estado_seguimiento', 24)->default('activo')->after('creado_por');
            }
            if (!Schema::hasColumn('agendas', 'es_actualizacion')) {
                $table->boolean('es_actualizacion')->default(false)->after('estado_seguimiento');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            if (Schema::hasColumn('agendas', 'parent_id')) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            }
            if (Schema::hasColumn('agendas', 'estado_seguimiento')) {
                $table->dropColumn('estado_seguimiento');
            }
            if (Schema::hasColumn('agendas', 'es_actualizacion')) {
                $table->dropColumn('es_actualizacion');
            }
        });
    }
};
