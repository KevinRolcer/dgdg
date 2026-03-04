<?php

namespace App\Http\Controllers;

use App\Http\Requests\MesasPaz\DetallePorFechaRequest;
use App\Http\Requests\MesasPaz\EliminarEvidenciaHoyRequest;
use App\Http\Requests\MesasPaz\GuardarAcuerdoHoyRequest;
use App\Http\Requests\MesasPaz\GuardarEvidenciaHoyRequest;
use App\Http\Requests\MesasPaz\GuardarMunicipioRequest;
use App\Http\Requests\MesasPaz\StoreMesasPazRequest;
use App\Services\MesasPaz\MesasPazService;
use App\Services\MesasPaz\MesasPazServiceException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MesasPazController extends Controller
{
    private MesasPazService $service;

    public function __construct(MesasPazService $service)
    {
        $this->service = $service;
    }

    /**
     * Muestra la página principal de Mesas de Paz y Seguridad
     */
    public function index(Request $request)
    {
        abort_unless(Auth::check() && Auth::user()->can('Mesas-Paz'), 403);
        abort_unless($this->canAccessMesasPazRegistro(), 403);

        $viewData = array_merge($this->service->indexData((int) Auth::id()), [
            'pageTitle' => 'Mesas de Paz y Seguridad',
            'pageDescription' => 'Captura diaria de asistencias, acuerdos y evidencias.',
        ]);

        return response()
            ->view('mesas_paz.mesasPaz', $viewData)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Sube o reemplaza evidencia de la sesión del día actual.
     */
    public function guardarEvidenciaHoy(GuardarEvidenciaHoyRequest $request)
    {
        abort_unless($this->canAccessMesasPazRegistro(), 403);

        try {
            $response = $this->service->guardarEvidenciaHoy((int) Auth::id(), $request->file('evidencia'));
            return response()->json($response);
        } catch (MesasPazServiceException $e) {
            $payload = $e->payload();
            if (!empty($payload['log'])) {
                Log::error('MesasPaz: error al guardar evidencia del día.', $payload['log']);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->status());
        }
    }

    public function eliminarEvidenciaHoy(EliminarEvidenciaHoyRequest $request)
    {
        abort_unless($this->canAccessMesasPazRegistro(), 403);

        try {
            $response = $this->service->eliminarEvidenciaHoy((int) Auth::id(), (string) $request->input('evidencia_path'));
            return response()->json($response);
        } catch (MesasPazServiceException $e) {
            $payload = $e->payload();
            if (!empty($payload['log'])) {
                Log::error('MesasPaz: error al eliminar evidencia del día.', $payload['log']);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->status());
        }
    }

    /**
     * Guarda o actualiza asistencia de un municipio para la fecha actual.
     */
    public function guardarMunicipio(GuardarMunicipioRequest $request)
    {
        abort_unless($this->canAccessMesasPazRegistro(), 403);

        try {
            $response = $this->service->guardarMunicipio((int) Auth::id(), $request->validated());
            return response()->json($response);
        } catch (MesasPazServiceException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->status());
        }
    }

    /**
     * Guarda/actualiza el campo de acuerdos del día actual.
     */
    public function guardarAcuerdoHoy(GuardarAcuerdoHoyRequest $request)
    {
        abort_unless($this->canAccessMesasPazRegistro(), 403);

        try {
            $response = $this->service->guardarAcuerdoHoy((int) Auth::id(), $request->validated());
            return response()->json($response);
        } catch (MesasPazServiceException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->status());
        }
    }

    /**
     * Devuelve el detalle del historial por fecha para modal.
     */
    public function detallePorFecha(DetallePorFechaRequest $request)
    {
        abort_unless($this->canAccessMesasPazRegistro(), 403);

        $response = $this->service->detallePorFecha((int) Auth::id(), (string) $request->input('fecha'));
        return response()->json($response);
    }

    /**
     * Guarda asistencias de Mesas de Paz por municipio.
     */
    public function store(StoreMesasPazRequest $request)
    {
        abort_unless($this->canAccessMesasPazRegistro(), 403);

        try {
            $response = $this->service->storeAsistencias((int) Auth::id(), $request->validated());
            return response()->json($response);
        } catch (MesasPazServiceException $e) {
            return response()->json(array_merge([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->payload()), $e->status());
        }
    }

    private function canAccessMesasPazRegistro(): bool
    {
        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();
        if (!$user || !$user->can('Mesas-Paz')) {
            return false;
        }

        return DB::table('delegados')
            ->where('user_id', (int) $user->id)
            ->exists();
    }
}
