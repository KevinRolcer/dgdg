<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temporary_module_action_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('temporary_module_id');
            $table->unsignedInteger('requested_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->string('action', 20);
            $table->string('status', 20)->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['temporary_module_id', 'requested_by', 'action', 'status'], 'tm_action_auth_scope_idx');
            $table->index(['status', 'expires_at']);
            $table->foreign('temporary_module_id', 'tm_action_auth_module_fk')->references('id')->on('temporary_modules')->cascadeOnDelete();
            $table->foreign('requested_by', 'tm_action_auth_requested_by_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('approved_by', 'tm_action_auth_approved_by_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temporary_module_action_authorizations');
    }
};
