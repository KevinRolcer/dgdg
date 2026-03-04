<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temporary_modules', function (Blueprint $table) {
            $table->boolean('applies_to_all')->default(true)->after('is_active');
            $table->softDeletes();
        });

        Schema::create('temporary_module_user', function (Blueprint $table) {
            $table->foreignId('temporary_module_id')->constrained('temporary_modules')->cascadeOnDelete();
            $table->unsignedInteger('user_id');
            $table->timestamps();

            $table->primary(['temporary_module_id', 'user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temporary_module_user');

        Schema::table('temporary_modules', function (Blueprint $table) {
            $table->dropColumn(['applies_to_all', 'deleted_at']);
        });
    }
};
