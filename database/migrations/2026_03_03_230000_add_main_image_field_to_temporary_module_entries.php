<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temporary_module_entries', function (Blueprint $table) {
            $table->string('main_image_field_key')->nullable()->after('data');
        });
    }

    public function down(): void
    {
        Schema::table('temporary_module_entries', function (Blueprint $table) {
            $table->dropColumn('main_image_field_key');
        });
    }
};
