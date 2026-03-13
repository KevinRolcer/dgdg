<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            if (!Schema::hasColumn('agendas', 'tipo')) {
                $table->string('tipo', 20)->nullable()->after('descripcion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            if (Schema::hasColumn('agendas', 'tipo')) {
                $table->dropColumn('tipo');
            }
        });
    }
};

