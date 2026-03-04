<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TemporaryModuleAccessService
{
    public function delegadoMunicipios(int $userId): array
    {
        $delegado = DB::table('delegados')
            ->where('user_id', $userId)
            ->first();

        $microrregionId = $delegado->microrregion_id ?? null;
        $municipios = [];

        if ($microrregionId) {
            $municipios = DB::table('municipios')
                ->where('microrregion_id', $microrregionId)
                ->orderBy('municipio')
                ->pluck('municipio')
                ->all();
        }

        return [$microrregionId, $municipios];
    }

    public function userCanAccessModule(TemporaryModule $module, int $userId): bool
    {
        if ($module->applies_to_all) {
            return true;
        }

        return $module->targetUsers()->where('users.id', $userId)->exists();
    }

    public function delegates(): Collection
    {
        $delegadosTableUserIds = DB::table('delegados')
            ->whereNotNull('user_id')
            ->pluck('user_id');

        return User::query()
            ->where(function ($query) use ($delegadosTableUserIds): void {
                $query->whereHas('roles', function ($roleQuery): void {
                    $roleQuery->where('name', 'Delegado');
                });

                if ($delegadosTableUserIds->isNotEmpty()) {
                    $query->orWhereIn('id', $delegadosTableUserIds);
                }
            })
            ->orderBy('name')
            ->distinct('id')
            ->get(['id', 'name', 'email']);
    }
}
