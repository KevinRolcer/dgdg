<?php

namespace App\Http\Controllers;

use App\Models\Agenda;
use App\Services\Agenda\AgendaSeguimientoService;
use App\Services\Agenda\AgendaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgendaSeguimientoController extends Controller
{
    public function __construct(
        private readonly AgendaSeguimientoService $seguimiento,
        private readonly AgendaService $agendaService
    ) {}

    public function adminSeguimiento(): View
    {
        $porUsuario = $this->seguimiento->listarPorUsuarioParaAdmin();

        return view('agenda.seguimiento.admin', [
            'porUsuario' => $porUsuario,
        ]);
    }

    public function index(Request $request): View
    {
        $items = $this->seguimiento->listarAsignadosActivosFiltrados($request->user(), [
            'clasificacion' => $request->input('clasificacion', 'todos'),
            'buscar' => $request->input('buscar'),
            'fecha' => $request->input('fecha'),
            'per_page' => $request->input('per_page', 15),
        ]);

        return view('agenda.seguimiento.index', [
            'items' => $items,
            'clasificacion' => $request->input('clasificacion', 'todos'),
            'buscar' => $request->input('buscar'),
            'fechaDia' => $request->input('fecha'),
            'perPage' => (int) $request->input('per_page', 15),
            'microrregiones' => $this->agendaService->microrregionesParaFormulario(),
            'municipios' => $this->agendaService->municipiosParaFormulario(),
        ]);
    }

    public function pasarGira(Request $request, Agenda $agenda): RedirectResponse
    {
        $validated = $request->validate([
            'asunto' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
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
        ]);

        $this->seguimiento->pasarPreGiraAGira($agenda, $validated, $request);

        return redirect()->route('agenda.seguimiento.index')->with('toast', 'Registro pasado a Gira. La pre-gira quedó concluida.');
    }

    public function actualizacion(Request $request, Agenda $agenda): RedirectResponse
    {
        $validated = $request->validate([
            'asunto' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
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
        ]);

        $this->seguimiento->registrarActualizacion($agenda, $validated, $request);

        return redirect()->route('agenda.seguimiento.index')->with('toast', 'Actualización registrada. El registro anterior quedó concluido.');
    }
}
