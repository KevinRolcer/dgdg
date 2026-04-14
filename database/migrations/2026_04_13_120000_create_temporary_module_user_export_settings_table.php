<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temporary_module_user_export_settings', function (Blueprint $table) {
            $table->id();
            // users.id in this project is int unsigned (legacy); foreignId() is bigint and fails MySQL FK 3780.
            $table->unsignedInteger('user_id');
            $table->foreign('user_id', 'tm_ues_user_id_fk')
                ->references('id')->on('users')
                ->cascadeOnDelete();
            // Default FK name exceeds MySQL 64-char identifier limit for this table name.
            $table->unsignedBigInteger('temporary_module_id');
            $table->foreign('temporary_module_id', 'tm_ues_tm_id_fk')
                ->references('id')->on('temporary_modules')
                ->cascadeOnDelete();
            $table->json('payload');
            $table->timestamps();

            $table->unique(['user_id', 'temporary_module_id'], 'tm_ues_user_tm_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temporary_module_user_export_settings');
    }
};
