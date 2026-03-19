<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('whatsapp_totp_secret')->nullable()->after('remember_token');
            $table->timestamp('whatsapp_totp_confirmed_at')->nullable()->after('whatsapp_totp_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_totp_secret', 'whatsapp_totp_confirmed_at']);
        });
    }
};
