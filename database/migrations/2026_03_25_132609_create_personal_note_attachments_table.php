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
        Schema::create('personal_note_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_note_id')->constrained('personal_notes')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type'); // image, document
            $table->unsignedBigInteger('file_size');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_note_attachments');
    }
};
