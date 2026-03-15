<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Lógica tipo PAP: permisos explícitos. Rol Auditor = solo *-consulta + evidencias.
 * Sin middleware global: quién puede escribir pasa por can:Mesas-Paz, can:Modulos-Temporales-Admin, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        $todos = [
            'Mesas-Paz',
            'Mesas-Paz-consulta',
            'Tableros-incidencias',
            'Modulos-Temporales',
            'Modulos-Temporales-consulta',
            'Modulos-Temporales-Admin',
            'Modulos-Temporales-Admin-consulta',
            'Agenda-Directiva',
            'Agenda-Seguimiento',
            'Agenda-consulta',
        ];
        foreach ($todos as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $auditor = Role::findOrCreate('Auditor', 'web');
        $auditor->syncPermissions([
            'Mesas-Paz-consulta',
            'Tableros-incidencias',
            'Modulos-Temporales-Admin-consulta',
            'Modulos-Temporales-consulta',
            'Agenda-consulta',
        ]);
    }

    public function down(): void
    {
        Role::query()->where('name', 'Auditor')->where('guard_name', 'web')->delete();
    }
};
