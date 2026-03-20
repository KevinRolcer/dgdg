<?php

namespace App\Http\Controllers;

use App\Models\Agenda;
use App\Services\Agenda\AgendaDirectivaCalendarService;
use App\Services\Agenda\AgendaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function __construct(
        private readonly AgendaService $agendaService,
        private readonly AgendaDirectivaCalendarService $agendaDirectivaCalendar
    ) {}

    public function calendar(Request $request)
    {
        $user = $request->user();
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        $clasificacionRaw = (string) $request->query('clasificacion', '');
        $clasificacion = in_array($clasificacionRaw, ['', 'gira', 'pre_gira', 'agenda'], true)
            ? $clasificacionRaw
            : '';
        $buscar = trim((string) $request->query('buscar', ''));

        $payload = $this->agendaDirectivaCalendar->buildMonthPayload($user, $year, $month, [
            'clasificacion' => $clasificacion,
            'buscar' => $buscar,
        ]);

        $qBase = array_filter([
            'clasificacion' => $clasificacion,
            'buscar' => $buscar,
        ]);
        $prevUrl = route('agenda.calendar', array_merge($qBase, ['year' => $payload['prev']['y'], 'month' => $payload['prev']['m']]));
        $nextUrl = route('agenda.calendar', array_merge($qBase, ['year' => $payload['next']['y'], 'month' => $payload['next']['m']]));
        $previewReturn = route('agenda.calendar', array_filter(array_merge(
            ['year' => $payload['year'], 'month' => $payload['month']],
            ['clasificacion' => $clasificacion !== '' ? $clasificacion : null, 'buscar' => $buscar !== '' ? $buscar : null]
        )));

        $partialRaw = $request->query('partial');
        $wantsPartial = $partialRaw === '1'
            || $partialRaw === 1
            || $partialRaw === 'true'
            || filter_var($partialRaw, FILTER_VALIDATE_BOOLEAN);

        if ($wantsPartial) {
            return response()
                ->view('agenda.partials.calendar-month-inner', [
                    'p' => $payload,
                    'clasificacion' => $clasificacion,
                    'buscar' => $buscar,
                    'prevUrl' => $prevUrl,
                    'nextUrl' => $nextUrl,
                    'previewReturn' => $previewReturn,
                ])
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        return view('agenda.calendar', [
            'payload' => $payload,
            'clasificacion' => $clasificacion,
            'buscar' => $buscar,
            'puedeEditarAgenda' => $this->agendaService->puedeEditarAgendaCompleta($user),
            'soloAsignaciones' => $this->agendaService->usuarioVeSoloSusAsignaciones($user),
        ]);
    }

    public function index(Request $request)
    {
        $filters = [
            'clasificacion' => $request->query('clasificacion', ''),
            'buscar' => $request->query('buscar', ''),
            'fecha' => $request->query('fecha'),
            'per_page' => (int) $request->query('per_page', 20),
        ];

        $agendas = $this->agendaService->paginateAgendas($filters, $request->user());

        if ($request->boolean('fragment')) {
            return response()->view('agenda.partials.list-fragment', [
                'agendas' => $agendas,
                'puedeEditarAgenda' => $this->agendaService->puedeEditarAgendaCompleta($request->user()),
            ]);
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
            'puedeEditarAgenda' => $this->agendaService->puedeEditarAgendaCompleta($request->user()),
            'soloAsignaciones' => $this->agendaService->usuarioVeSoloSusAsignaciones($request->user()),
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

        if (! $result['ok']) {
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
        abort_unless($this->agendaService->puedeEditarAgendaCompleta($request->user()), 403);
        $validated = $request->validate($this->reglasAgenda());
        $this->agendaService->crear($validated, $request);

        return redirect()->route('agenda.index')->with('toast', 'Asunto creado correctamente en la agenda');
    }

    public function update(Request $request, Agenda $agenda)
    {
        abort_unless($this->agendaService->puedeEditarAgendaCompleta($request->user()), 403);
        $validated = $request->validate($this->reglasAgenda());
        $this->agendaService->actualizar($agenda, $validated, $request);

        return redirect()->route('agenda.index')->with('toast', 'Agenda actualizada correctamente');
    }

    public function destroy(Agenda $agenda)
    {
        abort_unless($this->agendaService->puedeEditarAgendaCompleta(auth()->user()), 403);
        $this->agendaService->eliminar($agenda);

        return redirect()->route('agenda.index')->with('toast', 'Entrada eliminada de la agenda');
    }

    /**
     * Vista previa de la actividad (misma idea que el detalle en Asignaciones / popover de semana).
     */
    public function show(Request $request, Agenda $agenda): View
    {
        $agenda->load(['usuariosAsignados', 'creador']);
        abort_unless($this->agendaService->puedeVerAgenda($request->user(), $agenda), 403);

        $return = $request->query('return');
        $returnUrl = is_string($return) && str_starts_with($return, url('/'))
            ? $return
            : null;

        return view('agenda.preview', [
            'agenda' => $agenda,
            'returnUrl' => $returnUrl,
            'puedeEditarAgenda' => $this->agendaService->puedeEditarAgendaCompleta($request->user()),
        ]);
    }

    /**
     * La edición se hace con el modal en el listado; redirige allí para buscar el asunto.
     */
    public function edit(Request $request, Agenda $agenda): RedirectResponse
    {
        abort_unless($this->agendaService->puedeEditarAgendaCompleta($request->user()), 403);

        return redirect()
            ->route('agenda.index', ['buscar' => $agenda->asunto])
            ->with('toast', 'Busca el asunto en la tabla y usa el botón de editar (lápiz).');
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
