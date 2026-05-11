<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temporary_module_edit_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('temporary_module_id')->constrained('temporary_modules')->cascadeOnDelete();
            $table->unsignedBigInteger('temporary_module_entry_id');
            $table->unsignedInteger('requested_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['temporary_module_entry_id', 'requested_by', 'status'], 'tm_edit_auth_entry_user_status_idx');
            $table->foreign('temporary_module_entry_id', 'tm_edit_auth_entry_fk')->references('id')->on('temporary_module_entries')->cascadeOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temporary_module_edit_authorizations');
    }
};
