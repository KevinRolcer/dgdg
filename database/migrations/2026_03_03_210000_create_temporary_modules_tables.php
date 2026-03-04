<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temporary_modules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by');
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('temporary_module_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temporary_module_id')->constrained('temporary_modules')->cascadeOnDelete();
            $table->string('label');
            $table->string('key');
            $table->string('type');
            $table->boolean('is_required')->default(false);
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['temporary_module_id', 'key']);
            $table->index(['temporary_module_id', 'sort_order']);
        });

        Schema::create('temporary_module_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temporary_module_id')->constrained('temporary_modules')->cascadeOnDelete();
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('microrregion_id')->nullable();
            $table->json('data');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['temporary_module_id', 'user_id']);
            $table->index(['temporary_module_id', 'submitted_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temporary_module_entries');
        Schema::dropIfExists('temporary_module_fields');
        Schema::dropIfExists('temporary_modules');
    }
};
