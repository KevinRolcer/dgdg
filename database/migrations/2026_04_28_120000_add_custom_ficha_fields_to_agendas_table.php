<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            if (! Schema::hasColumn('agendas', 'ficha_titulo')) {
                $table->string('ficha_titulo', 80)->nullable()->after('subtipo');
            }
            if (! Schema::hasColumn('agendas', 'ficha_fondo')) {
                $table->string('ficha_fondo', 24)->nullable()->after('ficha_titulo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            if (Schema::hasColumn('agendas', 'ficha_fondo')) {
                $table->dropColumn('ficha_fondo');
            }
            if (Schema::hasColumn('agendas', 'ficha_titulo')) {
                $table->dropColumn('ficha_titulo');
            }
        });
    }
};
