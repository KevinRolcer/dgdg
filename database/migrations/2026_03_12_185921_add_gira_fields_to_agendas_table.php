<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            $table->string('tipo')->default('asunto')->after('id');
            $table->string('microrregion')->nullable()->after('descripcion');
            $table->string('municipio')->nullable()->after('microrregion');
            $table->text('lugar')->nullable()->after('municipio');
            $table->text('seguimiento')->nullable()->after('lugar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'microrregion', 'municipio', 'lugar', 'seguimiento']);
        });
    }
};
