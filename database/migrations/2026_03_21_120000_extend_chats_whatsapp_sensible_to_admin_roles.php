<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El permiso Chats-WhatsApp-Sensible solo se había enlazado al rol "Administrador".
 * En muchos despliegues el usuario administrativo usa "Super Administrador", "SuperAdmin" o "Admin".
 */
return new class extends Migration
{
    private const PERM = 'Chats-WhatsApp-Sensible';

    private const ROLE_NAMES = [
        'Administrador',
        'Super Administrador',
        'SuperAdmin',
        'Admin',
    ];

    public function up(): void
    {
        $permId = (int) DB::table('permissions')
            ->where('name', self::PERM)
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permId) {
            return;
        }

        foreach (self::ROLE_NAMES as $roleName) {
            $roleId = DB::table('roles')
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->value('id');

            if (! $roleId) {
                continue;
            }

            $exists = DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permId)
                ->exists();

            if (! $exists) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permId = (int) DB::table('permissions')
            ->where('name', self::PERM)
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permId) {
            return;
        }

        /* No quitar el permiso al rol "Administrador" (lo añadió la migración anterior). */
        $roleIds = DB::table('roles')
            ->whereIn('name', ['Super Administrador', 'SuperAdmin', 'Admin'])
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roleIds as $rid) {
            DB::table('role_has_permissions')
                ->where('permission_id', $permId)
                ->where('role_id', $rid)
                ->delete();
        }
    }
};
