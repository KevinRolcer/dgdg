<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temporary_module_fields', function (Blueprint $table) {
            $table->unsignedTinyInteger('subsection_index')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('temporary_module_fields', function (Blueprint $table) {
            $table->dropColumn('subsection_index');
        });
    }
};
