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
        Schema::dropIfExists('personal_notes');
        Schema::create('personal_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->string('password_verify_hash')->nullable(); // To verify password
            $table->string('color')->nullable(); // e.g., #ff0000 or category-blue
            $table->enum('priority', ['low', 'medium', 'high'])->default('low');
            $table->dateTime('reminder_at')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_notes');
    }
};
