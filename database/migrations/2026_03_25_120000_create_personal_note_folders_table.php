<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_note_folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('name');
            $table->string('color')->nullable();
            $table->string('icon')->default('fa-folder');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('personal_notes', function (Blueprint $table) {
            $table->unsignedBigInteger('folder_id')->nullable()->after('user_id');
            $table->foreign('folder_id')->references('id')->on('personal_note_folders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personal_notes', function (Blueprint $table) {
            $table->dropForeign(['folder_id']);
            $table->dropColumn('folder_id');
        });
        Schema::dropIfExists('personal_note_folders');
    }
};
