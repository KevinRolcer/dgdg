<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temporary_modules', function (Blueprint $table) {
            // 'microrregion' = comportamiento actual (requerido)
            // 'free'         = sin microregión (microrregion_id queda null)
            // 'municipios'   = lista fija de municipios para seleccionar al registrar
            $table->string('registration_scope', 20)->default('microrregion')->after('applies_to_all');

            // JSON con lista de nombres de municipios configurada por el admin.
            // Solo aplica cuando registration_scope = 'municipios'.
            $table->json('target_municipios')->nullable()->after('registration_scope');
        });
    }

    public function down(): void
    {
        Schema::table('temporary_modules', function (Blueprint $table) {
            $table->dropColumn(['registration_scope', 'target_municipios']);
        });
    }
};
