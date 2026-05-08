<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            if (! Schema::hasColumn('agendas', 'ficha_orientacion')) {
                $table->string('ficha_orientacion', 16)->nullable()->after('ficha_fondo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            if (Schema::hasColumn('agendas', 'ficha_orientacion')) {
                $table->dropColumn('ficha_orientacion');
            }
        });
    }
};
