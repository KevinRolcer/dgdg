<?php

namespace App\Http\Controllers;

use App\Services\MesasPaz\MesasPazPresentationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class PowerPointController extends Controller
{
    private const PREVIEW_CACHE_PREFIX = 'ppt_preview:';

    private const PREVIEW_TTL_MINUTES = 30;

    public function generarPresentacion(Request $request)
    {
        $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ]);

        try {
            $service = new MesasPazPresentationService;
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
                'error' => 'Error al generar la presentación: '.$e->getMessage(),
            ], 500);
        }
    }

    public function prepararVistaPrevia(Request $request)
    {
        $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ]);

        try {
            $service = new MesasPazPresentationService;
            $fechaInicio = $request->input('fecha_inicio');
            $fechaFin = $request->input('fecha_fin');

            $resumen = $service->resumenPresentacion($fechaInicio, $fechaFin);
            $statsParaGrafica = [
                'total_mesas' => $resumen['total_mesas'],
                'mesas_con_asistencia' => $resumen['mesas_con_asistencia'],
                'mesas_con_inasistencia' => $resumen['mesas_con_inasistencia'],
                'municipios_distintos_con_registro' => 0,
                'por_dia' => [],
            ];
            $chartTmp = $service->crearPngGraficaBarras($resumen['meta_mesas'], $statsParaGrafica);

            $generatedPath = $service->generar($fechaInicio, $fechaFin);

            $token = Str::random(40);
            $relative = 'ppt_previews/'.$token.'.pptx';
            Storage::disk('local')->makeDirectory('ppt_previews');

            $destPath = Storage::disk('local')->path($relative);
            if (! @copy($generatedPath, $destPath)) {
                @unlink($generatedPath);
                if (is_string($chartTmp) && $chartTmp !== '' && is_file($chartTmp)) {
                    @unlink($chartTmp);
                }
                throw new \RuntimeException('No se pudo guardar la vista previa.');
            }
            @unlink($generatedPath);

            $chartDiskPath = null;
            if (is_string($chartTmp) && $chartTmp !== '' && is_file($chartTmp)) {
                $chartRelative = 'ppt_previews/'.$token.'_chart.png';
                $chartDiskPath = Storage::disk('local')->path($chartRelative);
                if (! @copy($chartTmp, $chartDiskPath)) {
                    $chartDiskPath = null;
                }
                @unlink($chartTmp);
            }

            $userId = (int) $request->user()->getAuthIdentifier();
            Cache::put(
                self::PREVIEW_CACHE_PREFIX.$token,
                [
                    'path' => $destPath,
                    'user_id' => $userId,
                    'chart_path' => $chartDiskPath,
                ],
                now()->addMinutes(self::PREVIEW_TTL_MINUTES)
            );

            $expires = now()->addMinutes(self::PREVIEW_TTL_MINUTES);
            $signedFileUrl = URL::temporarySignedRoute('ppt.preview-archivo', $expires, ['token' => $token]);
            $signedChartUrl = $chartDiskPath && is_file($chartDiskPath)
                ? URL::temporarySignedRoute('ppt.preview-chart', $expires, ['token' => $token])
                : null;

            $officeEmbedUrl = null;
            if (config('mesas_paz.ppt_office_online_embed', true) && $this->officeOnlineEmbedLikelyWorks()) {
                $officeEmbedUrl = 'https://view.officeapps.live.com/op/embed.aspx?src='.rawurlencode($signedFileUrl);
            }

            return response()->json([
                'resumen' => $resumen,
                'signed_file_url' => $signedFileUrl,
                'signed_chart_url' => $signedChartUrl,
                'office_embed_url' => $officeEmbedUrl,
                'download_url' => route('ppt.vista-previa.descargar', ['token' => $token]),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Error al generar la vista previa: '.$e->getMessage(),
            ], 500);
        }
    }

    public function vistaPrevia(Request $request, string $token)
    {
        $data = Cache::get(self::PREVIEW_CACHE_PREFIX.$token);
        if (! is_array($data) || ($data['user_id'] ?? null) !== (int) $request->user()->getAuthIdentifier()) {
            abort(404);
        }
        if (empty($data['path']) || ! is_file($data['path'])) {
            abort(410, 'La vista previa expiró. Genere una nueva desde Evidencias.');
        }

        $signedFileUrl = URL::temporarySignedRoute(
            'ppt.preview-archivo',
            now()->addMinutes(self::PREVIEW_TTL_MINUTES),
            ['token' => $token]
        );

        $officeEmbedUrl = null;
        if (config('mesas_paz.ppt_office_online_embed', true) && $this->officeOnlineEmbedLikelyWorks()) {
            $officeEmbedUrl = 'https://view.officeapps.live.com/op/embed.aspx?src='.rawurlencode($signedFileUrl);
        }

        return view('mesas_paz.ppt_vista_previa', [
            'officeEmbedUrl' => $officeEmbedUrl,
            'signedFileUrl' => $signedFileUrl,
            'downloadUrl' => route('ppt.vista-previa.descargar', ['token' => $token]),
            'evidenciasUrl' => route('mesas-paz.evidencias'),
        ]);
    }

    public function descargarVistaPrevia(Request $request, string $token)
    {
        $data = Cache::get(self::PREVIEW_CACHE_PREFIX.$token);
        if (! is_array($data) || ($data['user_id'] ?? null) !== (int) $request->user()->getAuthIdentifier()) {
            abort(404);
        }
        if (empty($data['path']) || ! is_file($data['path'])) {
            abort(410);
        }

        $nombre = 'mesas_paz_'.now()->format('Ymd_His').'.pptx';

        return response()->download($data['path'], $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ]);
    }

    public function previewArchivo(string $token)
    {
        $data = Cache::get(self::PREVIEW_CACHE_PREFIX.$token);
        if (! is_array($data) || empty($data['path']) || ! is_file($data['path'])) {
            abort(404, 'La vista previa expiró o no existe.');
        }

        return response()->file($data['path'], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'Content-Disposition' => 'inline; filename="mesas_paz_vista_previa.pptx"',
        ]);
    }

    public function previewChart(string $token)
    {
        $data = Cache::get(self::PREVIEW_CACHE_PREFIX.$token);
        if (! is_array($data) || empty($data['chart_path']) || ! is_file($data['chart_path'])) {
            abort(404);
        }

        return response()->file($data['chart_path'], [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="mesas_paz_grafica.png"',
        ]);
    }

    private function officeOnlineEmbedLikelyWorks(): bool
    {
        $base = (string) config('app.url');
        if (! str_starts_with($base, 'https://')) {
            return false;
        }

        $host = parse_url($base, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $hostLower = strtolower($host);
        if (in_array($hostLower, ['localhost', '127.0.0.1'], true)) {
            return false;
        }

        if (str_ends_with($hostLower, '.test') || str_ends_with($hostLower, '.local')) {
            return false;
        }

        return true;
    }
}
