<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class AgendaDirectivaPermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::findOrCreate('Agenda-Directiva', 'web');
    }
}
