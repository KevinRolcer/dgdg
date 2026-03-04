<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TemporaryModuleAccessService
{
    public function delegadoMunicipios(int $userId, ?int $selectedMicrorregionId = null): array
    {
        $microrregionIds = $this->microrregionIdsPorUsuario($userId);
        if (empty($microrregionIds)) {
            return [null, []];
        }

        $microrregionId = $microrregionIds[0];
        if ($selectedMicrorregionId !== null && in_array($selectedMicrorregionId, $microrregionIds, true)) {
            $microrregionId = $selectedMicrorregionId;
        }

        $municipios = DB::table('municipios')
            ->where('microrregion_id', $microrregionId)
            ->orderBy('municipio')
            ->pluck('municipio')
            ->all();

        return [$microrregionId, $municipios];
    }

    public function microrregionIdsPorUsuario(int $userId): array
    {
        $delegado = DB::table('delegados')
            ->where('user_id', $userId)
            ->first();

        if (!empty($delegado?->microrregion_id)) {
            return [(int) $delegado->microrregion_id];
        }

        return DB::table('user_microrregion')
            ->where('user_id', $userId)
            ->pluck('microrregion_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function microrregionesConMunicipiosPorUsuario(int $userId): Collection
    {
        $microrregionIds = $this->microrregionIdsPorUsuario($userId);
        if (empty($microrregionIds)) {
            return collect();
        }

        $microrregiones = DB::table('microrregiones')
            ->whereIn('id', $microrregionIds)
            ->select(['id', 'microrregion', 'cabecera'])
            ->orderByRaw('CAST(microrregion AS UNSIGNED)')
            ->get()
            ->keyBy('id');

        $municipiosPorMicro = DB::table('municipios')
            ->whereIn('microrregion_id', $microrregionIds)
            ->select(['microrregion_id', 'municipio'])
            ->orderBy('municipio')
            ->get()
            ->groupBy('microrregion_id')
            ->map(function ($group) {
                return $group->pluck('municipio')->values()->all();
            });

        return collect($microrregionIds)
            ->map(function ($id) use ($microrregiones, $municipiosPorMicro) {
                $micro = $microrregiones->get($id);
                if (!$micro) {
                    return null;
                }

                return (object) [
                    'id' => (int) $micro->id,
                    'microrregion' => str_pad((string) $micro->microrregion, 2, '0', STR_PAD_LEFT),
                    'cabecera' => (string) ($micro->cabecera ?? ''),
                    'municipios' => $municipiosPorMicro->get($id, []),
                ];
            })
            ->filter()
            ->values();
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
        $delegados = DB::table('delegados as d')
            ->join('users as u', 'u.id', '=', 'd.user_id')
            ->join('microrregiones as m', 'm.id', '=', 'd.microrregion_id')
            ->whereBetween(DB::raw('CAST(m.microrregion AS UNSIGNED)'), [1, 31])
            ->where('u.activo', 1)
            ->where('u.area_id', 8)
            ->where('u.cargo_id', 250)
            ->whereNotNull('d.user_id')
            ->select([
                'u.id',
                'u.name',
                'u.email',
                'm.microrregion',
                'm.cabecera',
                DB::raw("'Delegado' as scope"),
            ])
            ->orderByRaw('CAST(m.microrregion AS UNSIGNED)')
            ->orderBy('u.name')
            ->distinct()
            ->get();

        $enlaces = DB::table('users as u')
            ->join('model_has_roles as mhr', function ($join) {
                $join->on('mhr.model_id', '=', 'u.id')
                    ->whereIn('mhr.model_type', ['App\\Models\\User', 'App\\User']);
            })
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->leftJoin('user_microrregion as um', 'um.user_id', '=', 'u.id')
            ->leftJoin('microrregiones as m', 'm.id', '=', 'um.microrregion_id')
            ->where('r.name', 'Enlace')
            ->where('u.activo', 1)
            ->where('u.area_id', 8)
            ->where('u.cargo_id', 8)
            ->groupBy('u.id', 'u.name', 'u.email')
            ->select([
                'u.id',
                'u.name',
                'u.email',
                DB::raw('NULL as microrregion'),
                DB::raw("CONCAT('MR ', COALESCE(GROUP_CONCAT(DISTINCT LPAD(m.microrregion, 2, '0') ORDER BY CAST(m.microrregion AS UNSIGNED) SEPARATOR ', '), 'Sin asignación')) as cabecera"),
                DB::raw("'Enlace' as scope"),
            ])
            ->orderBy('u.name')
            ->get();

        return $delegados
            ->merge($enlaces)
            ->unique('id')
            ->values();
    }
}
