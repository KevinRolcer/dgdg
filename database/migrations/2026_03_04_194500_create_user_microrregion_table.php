<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_microrregion', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('microrregion_id');
            $table->timestamps();

            $table->unique(['user_id', 'microrregion_id'], 'uq_user_microrregion');
            $table->index('microrregion_id', 'idx_user_microrregion_micro');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('microrregion_id')
                ->references('id')
                ->on('microrregiones')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_microrregion');
    }
};
