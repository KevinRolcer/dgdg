<?php

namespace App\Http\Controllers;

use App\Models\Agenda;
use App\Services\Agenda\AgendaService;
use Illuminate\Http\Request;

class AgendaController extends Controller
{
    public function __construct(
        private readonly AgendaService $agendaService
    ) {}

    public function index(Request $request)
    {
        $filters = [
            'clasificacion' => $request->query('clasificacion', ''),
            'buscar' => $request->query('buscar', ''),
            'fecha' => $request->query('fecha'),
            'per_page' => (int) $request->query('per_page', 20),
        ];

        $agendas = $this->agendaService->paginateAgendas($filters);

        if ($request->boolean('fragment')) {
            return response()->view('agenda.partials.list-fragment', ['agendas' => $agendas]);
        }

        $clasificacion = in_array($filters['clasificacion'], ['', 'gira', 'pre_gira', 'agenda'], true)
            ? $filters['clasificacion']
            : '';
        $buscar = trim((string) $filters['buscar']);
        $fechaDia = $filters['fecha'];
        $perPage = min(max((int) $filters['per_page'], 5), 100);

        return view('agenda.index', [
            'agendas' => $agendas,
            'users' => $this->agendaService->usuariosEnlacesDelegados(),
            'microrregiones' => $this->agendaService->microrregionesParaFormulario(),
            'municipios' => $this->agendaService->municipiosParaFormulario(),
            'puedeAsignarModuloAgenda' => $this->agendaService->usuarioPuedeAsignarModuloAgenda($request->user()),
            'clasificacion' => $clasificacion,
            'buscar' => $buscar,
            'fechaDia' => $fechaDia,
            'perPage' => $perPage,
        ]);
    }

    public function moduloEnlaces()
    {
        abort_unless($this->agendaService->usuarioPuedeAsignarModuloAgenda(auth()->user()), 403);

        return response()->json(['enlaces' => $this->agendaService->listarEnlacesParaModuloAgenda()]);
    }

    public function moduloAsignar(Request $request)
    {
        abort_unless($this->agendaService->usuarioPuedeAsignarModuloAgenda(auth()->user()), 403);

        $validated = $request->validate(['user_id' => 'required|exists:users,id']);
        $result = $this->agendaService->asignarModuloAgendaDirectiva((int) $validated['user_id']);

        if (!$result['ok']) {
            return response()->json(
                ['ok' => false, 'message' => $result['message']],
                $result['http_code'] ?? 422
            );
        }

        return response()->json(['ok' => true, 'message' => $result['message']]);
    }

    public function moduloQuitar(Request $request)
    {
        abort_unless($this->agendaService->usuarioPuedeAsignarModuloAgenda(auth()->user()), 403);

        $validated = $request->validate(['user_id' => 'required|exists:users,id']);
        $this->agendaService->quitarModuloAgendaDirectiva((int) $validated['user_id']);

        return response()->json(['ok' => true, 'message' => 'Acceso a Agenda Directiva retirado.']);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->reglasAgenda());
        $this->agendaService->crear($validated, $request);

        return redirect()->route('agenda.index')->with('toast', 'Asunto creado correctamente en la agenda');
    }

    public function update(Request $request, Agenda $agenda)
    {
        $validated = $request->validate($this->reglasAgenda());
        $this->agendaService->actualizar($agenda, $validated, $request);

        return redirect()->route('agenda.index')->with('toast', 'Agenda actualizada correctamente');
    }

    public function destroy(Agenda $agenda)
    {
        $this->agendaService->eliminar($agenda);

        return redirect()->route('agenda.index')->with('toast', 'Entrada eliminada de la agenda');
    }

    /**
     * @return array<string, mixed>
     */
    private function reglasAgenda(): array
    {
        return [
            'asunto' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo' => 'nullable|string|in:asunto,gira',
            'subtipo' => 'nullable|string|in:gira,pre-gira',
            'microrregion' => 'nullable|string|max:255',
            'municipio' => 'nullable|string|max:255',
            'lugar' => 'nullable|string',
            'semaforo' => 'nullable|string|in:rojo,amarillo,verde',
            'seguimiento' => 'nullable|string',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'habilitar_hora' => 'boolean',
            'hora' => 'nullable|required_if:habilitar_hora,1',
            'repite' => 'boolean',
            'dias_repeticion' => 'nullable|array',
            'recordatorio_minutos' => 'nullable|integer|min:30',
            'direcciones_adicionales' => 'nullable|array',
            'direcciones_adicionales.*' => 'nullable|string|max:25|regex:/^[0-9\s\-+()]*$/',
            'usuarios_asignados' => 'nullable|array',
            'usuarios_asignados.*' => 'exists:users,id',
        ];
    }
}
