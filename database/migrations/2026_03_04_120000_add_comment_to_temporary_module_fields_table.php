<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('temporary_module_fields', 'comment')) {
            Schema::table('temporary_module_fields', function (Blueprint $table): void {
                $table->string('comment', 500)->nullable()->after('label');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('temporary_module_fields', 'comment')) {
            Schema::table('temporary_module_fields', function (Blueprint $table): void {
                $table->dropColumn('comment');
            });
        }
    }
};
