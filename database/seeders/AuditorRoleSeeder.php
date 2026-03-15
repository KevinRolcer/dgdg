<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Misma base que migración 2026_03_17 — permisos tipo PAP (solo consulta). */
class AuditorRoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Mesas-Paz-consulta',
            'Modulos-Temporales-Admin-consulta',
            'Modulos-Temporales-consulta',
            'Agenda-consulta',
            'Tableros-incidencias',
        ] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $role = Role::findOrCreate('Auditor', 'web');
        $role->syncPermissions([
            'Mesas-Paz-consulta',
            'Tableros-incidencias',
            'Modulos-Temporales-Admin-consulta',
            'Modulos-Temporales-consulta',
            'Agenda-consulta',
        ]);
    }
}
