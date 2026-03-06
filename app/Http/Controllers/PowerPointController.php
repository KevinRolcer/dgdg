<?php

namespace App\Http\Controllers;

use App\Services\MesasPaz\MesasPazPresentationService;
use Illuminate\Http\Request;

class PowerPointController extends Controller
{
    public function generarPresentacion(Request $request)
    {
        $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ]);

        try {
            $service = new MesasPazPresentationService();
            $path = $service->generar(
                $request->input('fecha_inicio'),
                $request->input('fecha_fin')
            );

            return response()->download($path)->deleteFileAfterSend(true);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'error' => 'Error al generar la presentación: ' . $e->getMessage()
            ], 500);
        }
    }
}
