<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('agenda_user')) {
            return;
        }
        $perm = Permission::findOrCreate('Agenda-Seguimiento', 'web');
        $ids = DB::table('agenda_user')->distinct()->pluck('user_id');
        foreach ($ids as $uid) {
            $u = User::find($uid);
            if ($u && !$u->can('Agenda-Seguimiento')) {
                $u->givePermissionTo($perm);
            }
        }
    }

    public function down(): void
    {
        // no revocamos masivamente
    }
};
