<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminTestUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['Administrador', 'Admin', 'Super Administrador', 'SuperAdmin'])
            ->first();

        if (!$adminRole) {
            $adminRole = Role::findOrCreate('Administrador', 'web');
        }

        $adminPermission = Permission::findOrCreate('Modulos-Temporales-Admin', 'web');

        $webPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        if (!in_array($adminPermission->name, $webPermissions, true)) {
            $webPermissions[] = $adminPermission->name;
        }

        if (!empty($webPermissions)) {
            $adminRole->syncPermissions($webPermissions);
        }

        $adminUser = User::updateOrCreate(
            ['email' => 'dgdg.admon@gmail.com'],
            [
                'name' => 'Dirección General de Delegaciones',
                'password' => Hash::make('Segob2026!'),
            ]
        );

        $adminUser->syncRoles([$adminRole]);
    }
}
