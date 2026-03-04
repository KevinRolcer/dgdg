<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SupervisorMesasPazSeeder extends Seeder
{
    public function run(): void
    {
        $rolSupervisorSanctum = Role::query()
            ->where('name', 'Tablero Incidencias')
            ->where('guard_name', 'sanctum')
            ->first();

        if (!$rolSupervisorSanctum) {
            $rolSupervisorSanctum = Role::firstOrCreate([
                'name' => 'Tablero Incidencias',
                'guard_name' => 'sanctum',
            ]);
        }

        $rolSupervisorWeb = Role::firstOrCreate([
            'name' => 'Tablero Incidencias',
            'guard_name' => 'web',
        ]);

        $permisoRevisionSanctum = Permission::firstOrCreate([
            'name' => 'Tableros-incidencias',
            'guard_name' => 'sanctum',
        ]);

        if (!$rolSupervisorSanctum->hasPermissionTo($permisoRevisionSanctum)) {
            $rolSupervisorSanctum->givePermissionTo($permisoRevisionSanctum);
        }

        $permisoRevisionWeb = Permission::firstOrCreate([
            'name' => 'Tableros-incidencias',
            'guard_name' => 'web',
        ]);

        if (!$rolSupervisorWeb->hasPermissionTo($permisoRevisionWeb)) {
            $rolSupervisorWeb->givePermissionTo($permisoRevisionWeb);
        }

        $usuario = User::firstOrNew(['email' => 'supervisor.mesaspaz@prueba.com']);
        $usuario->forceFill([
            'name' => 'Supervisor MesasPaz',
            'password' => Hash::make('asdf1234'),
        ]);
        $usuario->save();

        if (!$usuario->hasRole($rolSupervisorWeb->name)) {
            $usuario->assignRole($rolSupervisorWeb);
        }

        DB::table('model_has_roles')->updateOrInsert(
            [
                'role_id' => $rolSupervisorSanctum->id,
                'model_type' => 'App\\User',
                'model_id' => $usuario->id,
            ],
            []
        );

        DB::table('model_has_roles')->updateOrInsert(
            [
                'role_id' => $rolSupervisorWeb->id,
                'model_type' => 'App\\Models\\User',
                'model_id' => $usuario->id,
            ],
            []
        );

        DB::table('model_has_roles')
            ->where('model_id', $usuario->id)
            ->where('model_type', 'App\\Models\\User')
            ->whereNotIn('role_id', [$rolSupervisorWeb->id])
            ->delete();

        DB::table('model_has_roles')
            ->where('model_id', $usuario->id)
            ->where('model_type', 'App\\User')
            ->whereNotIn('role_id', [$rolSupervisorSanctum->id])
            ->delete();

        DB::table('model_has_permissions')
            ->where('model_id', $usuario->id)
            ->whereIn('model_type', ['App\\User', 'App\\Models\\User'])
            ->delete();

        DB::table('model_has_roles')
            ->where('model_id', $usuario->id)
            ->where('model_type', 'App\\User')
            ->where('role_id', $rolSupervisorWeb->id)
            ->delete();

        DB::table('model_has_roles')
            ->where('model_id', $usuario->id)
            ->where('model_type', 'App\\Models\\User')
            ->where('role_id', $rolSupervisorSanctum->id)
            ->delete();
    }
}
