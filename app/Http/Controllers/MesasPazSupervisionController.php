<?php

namespace App\Http\Controllers;

use App\Services\MesasPaz\MesasPazSupervisionService;
use Illuminate\Http\Request;
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

    public function descargarPdf(Request $request)
    {
        return redirect()
            ->route('mesas-paz.evidencias', $request->query())
            ->with('warning', 'La generación de PDF está deshabilitada temporalmente.');
    }
}
