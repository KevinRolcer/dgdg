<?php

namespace App\Services\Agenda;

use App\Models\Agenda;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgendaSeguimientoService
{
    /**
     * Lista asignados activos con filtros y paginación (estilo Agenda Directiva).
     *
     * @param  array{clasificacion?: string, buscar?: string, fecha?: string, per_page?: int}  $filters
     */
    public function listarAsignadosActivosFiltrados(User $user, array $filters = []): LengthAwarePaginator
    {
        $q = Agenda::query()
            ->activas()
            ->whereHas('usuariosAsignados', fn ($q) => $q->where('users.id', $user->id))
            ->with(['usuariosAsignados', 'creador', 'padre']);

        $clasificacion = $filters['clasificacion'] ?? 'todos';
        if ($clasificacion === 'agenda') {
            $q->where('tipo', 'asunto');
        } elseif ($clasificacion === 'pre_gira') {
            $q->where('tipo', 'gira')->where(function ($q) {
                $q->whereRaw('LOWER(TRIM(COALESCE(subtipo, ""))) = ?', ['pre-gira']);
            });
        } elseif ($clasificacion === 'gira') {
            $q->where('tipo', 'gira')->where(function ($q) {
                $q->whereNull('subtipo')
                    ->orWhereRaw('LOWER(TRIM(subtipo)) != ?', ['pre-gira']);
            });
        }

        if (!empty($filters['buscar'])) {
            $term = '%' . trim($filters['buscar']) . '%';
            $q->where(function ($q) use ($term) {
                $q->where('asunto', 'like', $term)->orWhere('descripcion', 'like', $term);
            });
        }

        if (!empty($filters['fecha'])) {
            $q->whereDate('fecha_inicio', $filters['fecha']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = $perPage >= 5 && $perPage <= 100 ? $perPage : 15;

        return $q->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function puedeGestionar(Agenda $agenda, User $user): bool
    {
        return $agenda->usuariosAsignados()->where('users.id', $user->id)->exists();
    }

    /**
     * Pre-gira → Gira: nuevo registro; anterior queda concluido.
     */
    public function pasarPreGiraAGira(Agenda $anterior, array $validated, Request $request): Agenda
    {
        if ($anterior->estado_seguimiento !== Agenda::ESTADO_ACTIVO) {
            abort(422, 'El registro ya no está activo.');
        }
        if ($anterior->tipo !== 'gira' || strtolower((string) $anterior->subtipo) !== 'pre-gira') {
            abort(422, 'Solo se puede pasar a Gira desde una Pre-gira.');
        }
        if (!$this->puedeGestionar($anterior, $request->user())) {
            abort(403);
        }

        return DB::transaction(function () use ($anterior, $validated, $request) {
            $anterior->update(['estado_seguimiento' => Agenda::ESTADO_CONCLUIDO]);

            $userIds = $anterior->usuariosAsignados->pluck('id')->all();

            $nuevo = Agenda::create([
                'asunto' => $validated['asunto'],
                'descripcion' => $validated['descripcion'] ?? $anterior->descripcion,
                'tipo' => 'gira',
                'subtipo' => 'gira',
                'microrregion' => $validated['microrregion'] ?? $anterior->microrregion,
                'municipio' => $validated['municipio'] ?? $anterior->municipio,
                'lugar' => $validated['lugar'] ?? $anterior->lugar,
                'semaforo' => $validated['semaforo'] ?? $anterior->semaforo,
                'seguimiento' => $validated['seguimiento'] ?? $anterior->seguimiento,
                'fecha_inicio' => $validated['fecha_inicio'],
                'fecha_fin' => $validated['fecha_fin'] ?? null,
                'hora' => $request->has('habilitar_hora') ? $validated['hora'] ?? null : null,
                'habilitar_hora' => $request->has('habilitar_hora'),
                'repite' => $request->has('repite'),
                'dias_repeticion' => $request->input('dias_repeticion', []),
                'recordatorio_minutos' => $validated['recordatorio_minutos'] ?? $anterior->recordatorio_minutos,
                'direcciones_adicionales' => array_values(array_filter(array_map('trim', $request->input('direcciones_adicionales', $anterior->direcciones_adicionales ?? [])))),
                'creado_por' => $request->user()->id,
                'parent_id' => $anterior->id,
                'estado_seguimiento' => Agenda::ESTADO_ACTIVO,
                'es_actualizacion' => false,
            ]);

            $nuevo->usuariosAsignados()->sync($userIds);

            return $nuevo;
        });
    }

    /**
     * Asunto de agenda: nueva fila marcada Actualización; anterior concluido.
     */
    public function registrarActualizacion(Agenda $anterior, array $validated, Request $request): Agenda
    {
        if ($anterior->estado_seguimiento !== Agenda::ESTADO_ACTIVO) {
            abort(422, 'El registro ya no está activo.');
        }
        if (($anterior->tipo ?? 'asunto') !== 'asunto') {
            abort(422, 'Las actualizaciones de seguimiento aplican solo a ítems de agenda (asunto).');
        }
        if (!$this->puedeGestionar($anterior, $request->user())) {
            abort(403);
        }

        return DB::transaction(function () use ($anterior, $validated, $request) {
            $anterior->update(['estado_seguimiento' => Agenda::ESTADO_CONCLUIDO]);
            $userIds = $anterior->usuariosAsignados->pluck('id')->all();

            $nuevo = Agenda::create([
                'asunto' => $validated['asunto'],
                'descripcion' => $validated['descripcion'] ?? null,
                'tipo' => 'asunto',
                'subtipo' => null,
                'microrregion' => $validated['microrregion'] ?? $anterior->microrregion,
                'municipio' => $validated['municipio'] ?? $anterior->municipio,
                'lugar' => $validated['lugar'] ?? $anterior->lugar,
                'semaforo' => $validated['semaforo'] ?? $anterior->semaforo,
                'seguimiento' => $validated['seguimiento'] ?? $anterior->seguimiento,
                'fecha_inicio' => $validated['fecha_inicio'],
                'fecha_fin' => $validated['fecha_fin'] ?? null,
                'hora' => $request->has('habilitar_hora') ? $validated['hora'] ?? null : null,
                'habilitar_hora' => $request->has('habilitar_hora'),
                'repite' => $request->has('repite'),
                'dias_repeticion' => $request->input('dias_repeticion', []),
                'recordatorio_minutos' => $validated['recordatorio_minutos'] ?? $anterior->recordatorio_minutos,
                'direcciones_adicionales' => array_values(array_filter(array_map('trim', $request->input('direcciones_adicionales', $anterior->direcciones_adicionales ?? [])))),
                'creado_por' => $request->user()->id,
                'parent_id' => $anterior->id,
                'estado_seguimiento' => Agenda::ESTADO_ACTIVO,
                'es_actualizacion' => true,
            ]);

            $nuevo->usuariosAsignados()->sync($userIds);

            return $nuevo;
        });
    }
}
