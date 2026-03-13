<?php

namespace App\Services\Agenda;

use App\Models\Agenda;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class AgendaService
{
    public const ROLES_ASIGNAR_MODULO_AGENDA = ['Administrador', 'Super Administrador', 'SuperAdmin', 'Admin'];

    public const PERMISO_AGENDA_DIRECTIVA = 'Agenda-Directiva';

    /**
     * @param  array{clasificacion?: string, buscar?: string, fecha?: string|null, per_page?: int, page?: int}  $filters
     */
    public function paginateAgendas(array $filters): LengthAwarePaginator
    {
        $clasificacion = $filters['clasificacion'] ?? '';
        if (!in_array($clasificacion, ['', 'gira', 'pre_gira', 'agenda'], true)) {
            $clasificacion = '';
        }
        $buscar = trim((string) ($filters['buscar'] ?? ''));
        $fechaDia = $filters['fecha'] ?? null;
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 5), 100);

        $query = Agenda::query()
            ->with(['creador', 'usuariosAsignados'])
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id');

        if ($clasificacion === 'gira') {
            $query->where('tipo', 'gira')
                ->where(function ($q) {
                    $q->where('subtipo', 'gira')->orWhereNull('subtipo');
                });
        } elseif ($clasificacion === 'pre_gira') {
            $query->where('tipo', 'gira')->where('subtipo', 'pre-gira');
        } elseif ($clasificacion === 'agenda') {
            $query->where(function ($q) {
                $q->where('tipo', 'asunto')->orWhereNull('tipo');
            });
        }

        if ($buscar !== '') {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $buscar) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('asunto', 'like', $term)
                    ->orWhere('descripcion', 'like', $term);
            });
        }

        if ($fechaDia && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fechaDia)) {
            try {
                $query->whereDate('fecha_inicio', $fechaDia);
            } catch (\Throwable) {
                // ignore invalid date
            }
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Microrregiones con delegados activos (misma lógica que módulos temporales).
     */
    public function microrregionesParaFormulario(): Collection
    {
        return DB::table('delegados as d')
            ->join('users as u', 'u.id', '=', 'd.user_id')
            ->join('microrregiones as m', 'm.id', '=', 'd.microrregion_id')
            ->where('u.activo', 1)
            ->where('u.area_id', 8)
            ->where('u.cargo_id', 250)
            ->whereNotNull('d.user_id')
            ->select(
                'm.id',
                'm.microrregion',
                'm.cabecera',
                DB::raw("TRIM(CONCAT(COALESCE(d.nombre, ''), ' ', COALESCE(d.ap_paterno, ''), ' ', COALESCE(d.ap_materno, ''))) as delegado_nombre")
            )
            ->orderByRaw('CAST(m.microrregion AS UNSIGNED)')
            ->distinct()
            ->get();
    }

    public function municipiosParaFormulario(): Collection
    {
        return DB::table('municipios as mu')
            ->join('microrregiones as mr', 'mr.id', '=', 'mu.microrregion_id')
            ->whereNotNull('mu.microrregion_id')
            ->select('mu.id', 'mu.municipio', 'mu.microrregion_id', 'mr.microrregion as microrregion_codigo')
            ->orderBy('mu.municipio')
            ->get();
    }

    public function usuariosEnlacesDelegados(): Collection
    {
        return User::role(['Enlace', 'Delegado'])->get(['id', 'name', 'email']);
    }

    /**
     * @return list<array{id: int, name: string, email: string, tiene_agenda: bool}>
     */
    public function listarEnlacesParaModuloAgenda(): array
    {
        Permission::findOrCreate(self::PERMISO_AGENDA_DIRECTIVA, 'web');

        return User::role('Enlace')
            ->where('activo', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'tiene_agenda' => $u->can(self::PERMISO_AGENDA_DIRECTIVA),
            ])
            ->all();
    }

    /**
     * @return array{ok: bool, message: string, http_code?: int}
     */
    public function asignarModuloAgendaDirectiva(int $userId): array
    {
        Permission::findOrCreate(self::PERMISO_AGENDA_DIRECTIVA, 'web');
        $user = User::findOrFail($userId);
        if (!$user->hasRole('Enlace')) {
            return [
                'ok' => false,
                'message' => 'Solo se puede asignar a usuarios con rol Enlace.',
                'http_code' => 422,
            ];
        }
        $user->givePermissionTo(self::PERMISO_AGENDA_DIRECTIVA);

        return ['ok' => true, 'message' => 'Módulo Agenda Directiva asignado.'];
    }

    public function quitarModuloAgendaDirectiva(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->revokePermissionTo(self::PERMISO_AGENDA_DIRECTIVA);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function crear(array $validated, \Illuminate\Http\Request $request): Agenda
    {
        return DB::transaction(function () use ($validated, $request) {
            $agenda = Agenda::create(array_merge($validated, [
                'creado_por' => $request->user()->id,
                'habilitar_hora' => $request->has('habilitar_hora'),
                'repite' => $request->has('repite'),
                'tipo' => $request->input('tipo', 'asunto'),
                'subtipo' => $request->input('tipo') === 'gira' ? $request->input('subtipo', 'gira') : null,
                'direcciones_adicionales' => array_values(array_filter(array_map('trim', $request->input('direcciones_adicionales', [])))),
            ]));

            if (!empty($validated['usuarios_asignados'])) {
                $agenda->usuariosAsignados()->sync($validated['usuarios_asignados']);
            }

            return $agenda;
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function actualizar(Agenda $agenda, array $validated, \Illuminate\Http\Request $request): void
    {
        DB::transaction(function () use ($agenda, $validated, $request) {
            $agenda->update(array_merge($validated, [
                'habilitar_hora' => $request->has('habilitar_hora'),
                'repite' => $request->has('repite'),
                'subtipo' => $request->input('tipo') === 'gira' ? $request->input('subtipo', 'gira') : null,
                'dias_repeticion' => $request->input('dias_repeticion', []),
                'direcciones_adicionales' => array_values(array_filter(array_map('trim', $request->input('direcciones_adicionales', [])))),
            ]));
            $agenda->usuariosAsignados()->sync($request->input('usuarios_asignados', []));
        });
    }

    public function eliminar(Agenda $agenda): void
    {
        $agenda->delete();
    }

    public function usuarioPuedeAsignarModuloAgenda(?User $user): bool
    {
        return $user && $user->hasAnyRole(self::ROLES_ASIGNAR_MODULO_AGENDA);
    }
}
