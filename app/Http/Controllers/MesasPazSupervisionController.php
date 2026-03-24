<?php

namespace App\Http\Controllers;

use App\Models\MesaPazAsistencia;
use App\Services\MesasPaz\MesasPazSupervisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class MesasPazSupervisionController extends Controller
{
    private MesasPazSupervisionService $service;

    public function __construct(MesasPazSupervisionService $service)
    {
        $this->service = $service;
    }

    public function evidencias(Request $request)
    {
        $usuario = Auth::user();
        abort_unless($this->service->puedeSupervisarEvidencias($usuario), 403);

        $resultado = $this->service->construirVistaEvidencias($request, $usuario);
        if (!$resultado['valid']) {
            return redirect()->route('mesas-paz.evidencias')->withErrors($resultado['errors'])->withInput();
        }

        $resultado['data']['pageTitle'] = 'Mesas de Paz - Evidencias';
        $resultado['data']['pageDescription'] = 'Supervisión de asistencias y evidencias por fecha y microrregión.';

        // Pasar el paginador de registrosLista a la vista para paginación
        $resultado['data']['registrosPaginator'] = $resultado['data']['registrosLista'] ?? null;

        return view('mesas_paz.evidencias', $resultado['data']);
    }

    public function registrosBruto(Request $request): JsonResponse
    {
        $usuario = Auth::user();
        abort_unless($this->service->puedeSupervisarEvidencias($usuario), 403);

        $validated = $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'page'         => ['nullable', 'integer', 'min:1'],
        ]);

        $inicio = Carbon::parse($validated['fecha_inicio'])->startOfDay();
        $fin    = Carbon::parse($validated['fecha_fin'])->endOfDay();

        $registros = MesaPazAsistencia::query()
            ->whereBetween('fecha_asist', [$inicio->toDateString(), $fin->toDateString()])
            ->with([
                'municipio:id,municipio',
                'microrregion:id,microrregion,cabecera',
                'delegado:id,nombre,ap_paterno,ap_materno',
                'user:id,name,email',
            ])
            ->orderByDesc('fecha_asist')
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json([
            'data'         => $registros->items(),
            'current_page' => $registros->currentPage(),
            'last_page'    => $registros->lastPage(),
            'total'        => $registros->total(),
        ]);
    }

    public function eliminarRango(Request $request): JsonResponse
    {
        $usuario = Auth::user();
        abort_unless($this->service->puedeSupervisarEvidencias($usuario), 403);
        abort_unless(
            $usuario->hasRole('Superadmin') || $usuario->hasRole('superadmin')
            || $usuario->hasRole('Enlace')
            || $usuario->hasRole('Administrador'),
            403,
            'No tienes permiso para vaciar registros.'
        );

        $validated = $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ]);

        $inicio = Carbon::parse($validated['fecha_inicio'])->toDateString();
        $fin    = Carbon::parse($validated['fecha_fin'])->toDateString();

        $eliminados = MesaPazAsistencia::query()
            ->whereBetween('fecha_asist', [$inicio, $fin])
            ->delete();

        return response()->json([
            'success'    => true,
            'eliminados' => $eliminados,
            'message'    => "Se eliminaron {$eliminados} registro(s) del {$inicio} al {$fin}.",
        ]);
    }

    public function descargarPdf(Request $request)
    {
        return redirect()
            ->route('mesas-paz.evidencias', $request->query())
            ->with('warning', 'La generación de PDF está deshabilitada temporalmente.');
    }
}
