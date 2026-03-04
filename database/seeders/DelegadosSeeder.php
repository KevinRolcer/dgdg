<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DelegadosSeeder extends Seeder
{
    public function run(): void
    {
        $rolDelegadoSanctum = Role::query()
            ->where('id', 17)
            ->where('name', 'Delegado')
            ->where('guard_name', 'sanctum')
            ->first();

        if (!$rolDelegadoSanctum) {
            $rolDelegadoSanctum = Role::firstOrCreate([
                'name' => 'Delegado',
                'guard_name' => 'sanctum',
            ]);
        }

        $rolDelegadoWeb = Role::firstOrCreate([
            'name' => 'Delegado',
            'guard_name' => 'web',
        ]);

        $permisosDelegado = ['Mesas-Paz', 'Incidencias', 'Incidencias-Delegado', 'Modulos-Temporales'];

        foreach ($permisosDelegado as $permisoNombre) {
            $permisoSanctum = Permission::firstOrCreate([
                'name' => $permisoNombre,
                'guard_name' => 'sanctum',
            ]);

            if (!$rolDelegadoSanctum->hasPermissionTo($permisoSanctum)) {
                $rolDelegadoSanctum->givePermissionTo($permisoSanctum);
            }

            $permisoWeb = Permission::firstOrCreate([
                'name' => $permisoNombre,
                'guard_name' => 'web',
            ]);

            if (!$rolDelegadoWeb->hasPermissionTo($permisoWeb)) {
                $rolDelegadoWeb->givePermissionTo($permisoWeb);
            }
        }

        $delegadosPrueba = [
            [
                'name' => 'Juan Delegado Prueba',
                'email' => 'delegado@prueba.com',
                'telefono' => '2221234567',
                'microrregion_id' => 1,
                'nombre' => 'Juan',
                'ap_paterno' => 'Pérez',
                'ap_materno' => 'López',
            ],
            [
                'name' => 'Delegado Prueba MR6',
                'email' => 'delegado.mr6@prueba.com',
                'telefono' => '2221000006',
                'microrregion_id' => 6,
                'nombre' => 'Delegado',
                'ap_paterno' => 'MR6',
                'ap_materno' => 'Prueba',
            ],
            [
                'name' => 'Delegado Prueba MR3',
                'email' => 'delegado.mr3@prueba.com',
                'telefono' => '2221000003',
                'microrregion_id' => 3,
                'nombre' => 'Delegado',
                'ap_paterno' => 'MR3',
                'ap_materno' => 'Prueba',
            ],
            [
                'name' => 'Delegado Prueba MR15',
                'email' => 'delegado.mr15@prueba.com',
                'telefono' => '2221000015',
                'microrregion_id' => 15,
                'nombre' => 'Delegado',
                'ap_paterno' => 'MR15',
                'ap_materno' => 'Prueba',
            ],
        ];

        $dependenciaGobId = null;
        if (Schema::hasTable('dependencias_gobs')) {
            $dependenciaGobId = DB::table('dependencias_gobs')->value('id');
        }

        foreach ($delegadosPrueba as $data) {
            $user = User::firstOrNew(['email' => $data['email']]);

            $payload = [
                'name' => $data['name'],
                'password' => Hash::make('asdf1234'),
            ];

            if (Schema::hasColumn('users', 'cargo_id')) {
                $payload['cargo_id'] = 250;
            }

            if (Schema::hasColumn('users', 'telefono')) {
                $payload['telefono'] = $data['telefono'];
            }

            $user->forceFill($payload);
            $user->save();

            if (!$user->hasRole($rolDelegadoWeb->name)) {
                $user->assignRole($rolDelegadoWeb);
            }

            DB::table('model_has_roles')->updateOrInsert(
                [
                    'role_id' => $rolDelegadoSanctum->id,
                    'model_type' => 'App\\User',
                    'model_id' => $user->id,
                ],
                []
            );

            DB::table('model_has_roles')->updateOrInsert(
                [
                    'role_id' => $rolDelegadoWeb->id,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $user->id,
                ],
                []
            );

            DB::table('model_has_roles')
                ->where('model_id', $user->id)
                ->where('model_type', 'App\\Models\\User')
                ->whereNotIn('role_id', [$rolDelegadoWeb->id])
                ->delete();

            DB::table('model_has_roles')
                ->where('model_id', $user->id)
                ->where('model_type', 'App\\User')
                ->whereNotIn('role_id', [$rolDelegadoSanctum->id])
                ->delete();

            DB::table('model_has_permissions')
                ->where('model_id', $user->id)
                ->whereIn('model_type', ['App\\User', 'App\\Models\\User'])
                ->delete();

            DB::table('model_has_roles')
                ->where('model_id', $user->id)
                ->where('model_type', 'App\\User')
                ->where('role_id', $rolDelegadoWeb->id)
                ->delete();

            DB::table('model_has_roles')
                ->where('model_id', $user->id)
                ->where('model_type', 'App\\Models\\User')
                ->where('role_id', $rolDelegadoSanctum->id)
                ->delete();

            if (Schema::hasColumn('users', 'micro_region')) {
                DB::table('users')->where('id', $user->id)->update([
                    'micro_region' => $data['microrregion_id'],
                    'updated_at' => now(),
                ]);
            }

            if (Schema::hasTable('delegados')) {
                $microId = null;
                if (Schema::hasTable('microrregiones')) {
                    $existsMicro = DB::table('microrregiones')->where('id', $data['microrregion_id'])->exists();
                    $microId = $existsMicro ? $data['microrregion_id'] : null;
                }

                $delegadoPayload = [
                    'nombre' => $data['nombre'],
                    'ap_paterno' => $data['ap_paterno'],
                    'ap_materno' => $data['ap_materno'],
                    'telefono' => $data['telefono'],
                    'email' => $data['email'],
                    'foto' => 'avatars/default.png',
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('delegados', 'microrregion_id')) {
                    $delegadoPayload['microrregion_id'] = $microId;
                }

                if (Schema::hasColumn('delegados', 'dependencia_gob_id')) {
                    $delegadoPayload['dependencia_gob_id'] = $dependenciaGobId;
                }

                DB::table('delegados')->updateOrInsert(
                    ['user_id' => $user->id],
                    array_merge(['created_at' => now()], $delegadoPayload)
                );
            }
        }
    }
}
