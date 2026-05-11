<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temporary_modules', function (Blueprint $table): void {
            $table->boolean('is_encrypted_event')->default(false)->after('show_microregion');
            $table->unsignedTinyInteger('edit_permission_duration_hours')->default(1)->after('is_encrypted_event');
            $table->text('pdf_password_encrypted')->nullable()->after('edit_permission_duration_hours');
        });
    }

    public function down(): void
    {
        Schema::table('temporary_modules', function (Blueprint $table): void {
            $table->dropColumn([
                'is_encrypted_event',
                'edit_permission_duration_hours',
                'pdf_password_encrypted',
            ]);
        });
    }
};
