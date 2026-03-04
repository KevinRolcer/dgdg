<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class EnlacesSeeder extends Seeder
{
    public function run(): void
    {
        $distributionByEmail = [
            'fernanda.martinezlazo@gmail.com' => ['01', '02', '03', '04', '27'],
            'kevinastridroldan@gmail.com' => ['05', '06', '14', '31'],
            'adamberick818@gmail.com' => ['09', '10', '11', '16', '17', '19', '20'],
            'anettcruz.dgd@gmail.com' => ['07', '08', '18', '21'],
            'rousserrano4982@gmail.com' => ['22', '23', '28', '29'],
            'andrea.montiel@puebla.gob.mx' => ['12', '13', '15', '24', '25', '26', '30'],
        ];

        $rolEnlaceWeb = Role::firstOrCreate([
            'name' => 'Enlace',
            'guard_name' => 'web',
        ]);

        $rolEnlaceSanctum = Role::firstOrCreate([
            'name' => 'Enlace',
            'guard_name' => 'sanctum',
        ]);

        $permisos = ['Mesas-Paz', 'Modulos-Temporales'];
        foreach ($permisos as $permisoNombre) {
            $permisoWeb = Permission::firstOrCreate([
                'name' => $permisoNombre,
                'guard_name' => 'web',
            ]);

            if (!$rolEnlaceWeb->hasPermissionTo($permisoWeb)) {
                $rolEnlaceWeb->givePermissionTo($permisoWeb);
            }

            $permisoSanctum = Permission::firstOrCreate([
                'name' => $permisoNombre,
                'guard_name' => 'sanctum',
            ]);

            if (!$rolEnlaceSanctum->hasPermissionTo($permisoSanctum)) {
                $rolEnlaceSanctum->givePermissionTo($permisoSanctum);
            }
        }

        $microrregionIdsByClave = DB::table('microrregiones')
            ->whereRaw('CAST(microrregion AS UNSIGNED) BETWEEN 1 AND 31')
            ->pluck('id', 'microrregion')
            ->mapWithKeys(fn ($id, $clave) => [str_pad((string) $clave, 2, '0', STR_PAD_LEFT) => (int) $id])
            ->all();

        foreach ($distributionByEmail as $email => $microrregiones) {
            $user = User::firstOrNew(['email' => $email]);
            $user->forceFill([
                'name' => 'N N.',
                'password' => Hash::make('asdf1234'),
                'telefono' => null,
                'area_id' => 8,
                'cargo_id' => 8,
                'micro_region' => null,
                'activo' => 1,
            ]);
            $user->save();

            if (!$user->hasRole($rolEnlaceWeb->name)) {
                $user->assignRole($rolEnlaceWeb);
            }

            DB::table('model_has_roles')->updateOrInsert(
                [
                    'role_id' => $rolEnlaceSanctum->id,
                    'model_type' => 'App\\User',
                    'model_id' => $user->id,
                ],
                []
            );

            DB::table('model_has_roles')->updateOrInsert(
                [
                    'role_id' => $rolEnlaceWeb->id,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $user->id,
                ],
                []
            );

            DB::table('user_microrregion')->where('user_id', $user->id)->delete();
            foreach ($microrregiones as $clave) {
                if (!isset($microrregionIdsByClave[$clave])) {
                    continue;
                }

                DB::table('user_microrregion')->updateOrInsert(
                    [
                        'user_id' => $user->id,
                        'microrregion_id' => $microrregionIdsByClave[$clave],
                    ],
                    [
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
