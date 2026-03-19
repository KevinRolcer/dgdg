<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_chat_archives', function (Blueprint $table) {
            $table->boolean('is_encrypted')->default(false)->after('message_parts');
            $table->text('wrapped_dek')->nullable()->after('is_encrypted');
            $table->unsignedTinyInteger('encrypted_key_version')->default(1)->after('wrapped_dek');
            $table->string('storage_disk', 64)->default('public')->after('encrypted_key_version');
        });

        Schema::create('whatsapp_chat_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_chat_archive_id')->nullable()->constrained('whatsapp_chat_archives')->nullOnDelete();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('action', 48);
            $table->string('resource_path', 1024)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        $permName = 'Chats-WhatsApp-Sensible';
        $exists = DB::table('permissions')
            ->where('name', $permName)
            ->where('guard_name', 'web')
            ->exists();

        if (! $exists) {
            DB::table('permissions')->insert([
                'name' => $permName,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permId = (int) DB::table('permissions')
            ->where('name', $permName)
            ->where('guard_name', 'web')
            ->value('id');

        $roleId = DB::table('roles')
            ->where('name', 'Administrador')
            ->where('guard_name', 'web')
            ->value('id');

        if ($roleId && $permId) {
            $already = DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permId)
                ->exists();
            if (! $already) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_chat_access_logs');

        Schema::table('whatsapp_chat_archives', function (Blueprint $table) {
            $table->dropColumn(['is_encrypted', 'wrapped_dek', 'encrypted_key_version', 'storage_disk']);
        });

        $permId = DB::table('permissions')
            ->where('name', 'Chats-WhatsApp-Sensible')
            ->where('guard_name', 'web')
            ->value('id');
        if ($permId) {
            DB::table('role_has_permissions')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
    }
};
