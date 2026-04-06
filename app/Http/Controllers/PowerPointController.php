<?php

namespace App\Http\Controllers;

use App\Services\MesasPaz\MesasPazPresentationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

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
            $inicioObj = Carbon::parse($fechaInicio);
            $finObj = Carbon::parse($fechaFin);

            $resumen = $service->resumenPresentacion($fechaInicio, $fechaFin);
            $resumenSemanaMarcada = $service->resumenPresentacion(
                $inicioObj->copy()->addDays(7)->toDateString(),
                $finObj->copy()->addDays(7)->toDateString()
            );
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

            $pdfDiskPath = $this->crearPdfVistaPreviaDesdePptx($destPath, $token);
            $pdfWarning = null;
            if ($pdfDiskPath === null && (bool) config('mesas_paz.ppt_pdf_require_identical', true)) {
                $pdfWarning = 'No se pudo convertir la presentación a PDF idéntico. Configure LibreOffice (soffice) en el servidor para habilitar la vista previa PDF.';
            }

            $userId = (int) $request->user()->getAuthIdentifier();
            Cache::put(
                self::PREVIEW_CACHE_PREFIX.$token,
                [
                    'path' => $destPath,
                    'user_id' => $userId,
                    'chart_path' => $chartDiskPath,
                    'pdf_path' => $pdfDiskPath,
                ],
                now()->addMinutes(self::PREVIEW_TTL_MINUTES)
            );

            $expires = now()->addMinutes(self::PREVIEW_TTL_MINUTES);
            $signedFileUrl = URL::temporarySignedRoute('ppt.preview-archivo', $expires, ['token' => $token]);
            $signedChartUrl = $chartDiskPath && is_file($chartDiskPath)
                ? URL::temporarySignedRoute('ppt.preview-chart', $expires, ['token' => $token])
                : null;
            $signedPdfUrl = $pdfDiskPath && is_file($pdfDiskPath)
                ? URL::temporarySignedRoute('ppt.preview-pdf', $expires, ['token' => $token])
                : null;
            $downloadPdfUrl = $pdfDiskPath && is_file($pdfDiskPath)
                ? route('ppt.vista-previa.descargar-pdf', ['token' => $token])
                : null;

            $officeEmbedUrl = null;
            if (config('mesas_paz.ppt_office_online_embed', true) && $this->officeOnlineEmbedLikelyWorks()) {
                $officeEmbedUrl = 'https://view.officeapps.live.com/op/embed.aspx?src='.rawurlencode($signedFileUrl);
            }

            return response()->json([
                'resumen' => $resumen,
                'resumen_semana_marcada' => $resumenSemanaMarcada,
                'signed_file_url' => $signedFileUrl,
                'signed_chart_url' => $signedChartUrl,
                'signed_pdf_url' => $signedPdfUrl,
                'pdf_warning' => $pdfWarning,
                'office_embed_url' => $officeEmbedUrl,
                'download_url' => route('ppt.vista-previa.descargar', ['token' => $token]),
                'download_pdf_url' => $downloadPdfUrl,
                'vista_previa_url' => route('ppt.vista-previa', ['token' => $token]),
            ], 200, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
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
        $signedPdfUrl = ! empty($data['pdf_path']) && is_file($data['pdf_path'])
            ? URL::temporarySignedRoute(
                'ppt.preview-pdf',
                now()->addMinutes(self::PREVIEW_TTL_MINUTES),
                ['token' => $token]
            )
            : null;

        $officeEmbedUrl = null;
        if (config('mesas_paz.ppt_office_online_embed', true) && $this->officeOnlineEmbedLikelyWorks()) {
            $officeEmbedUrl = 'https://view.officeapps.live.com/op/embed.aspx?src='.rawurlencode($signedFileUrl);
        }

        return view('mesas_paz.ppt_vista_previa', [
            'officeEmbedUrl' => $officeEmbedUrl,
            'signedFileUrl' => $signedFileUrl,
            'signedPdfUrl' => $signedPdfUrl,
            'downloadUrl' => route('ppt.vista-previa.descargar', ['token' => $token]),
            'downloadPdfUrl' => $signedPdfUrl ? route('ppt.vista-previa.descargar-pdf', ['token' => $token]) : null,
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

    public function descargarVistaPreviaPdf(Request $request, string $token)
    {
        $data = Cache::get(self::PREVIEW_CACHE_PREFIX.$token);
        if (! is_array($data) || ($data['user_id'] ?? null) !== (int) $request->user()->getAuthIdentifier()) {
            abort(404);
        }
        if (empty($data['pdf_path']) || ! is_file($data['pdf_path'])) {
            abort(410);
        }

        $nombre = 'mesas_paz_'.now()->format('Ymd_His').'.pdf';

        return response()->download($data['pdf_path'], $nombre, [
            'Content-Type' => 'application/pdf',
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

    public function previewPdf(string $token)
    {
        $data = Cache::get(self::PREVIEW_CACHE_PREFIX.$token);
        if (! is_array($data) || empty($data['pdf_path']) || ! is_file($data['pdf_path'])) {
            abort(404, 'La vista previa PDF expiró o no existe.');
        }

        return response()->file($data['pdf_path'], [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="mesas_paz_vista_previa.pdf"',
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

    private function crearPdfVistaPreviaDesdePptx(string $pptxPath, string $token): ?string
    {
        if (! is_file($pptxPath)) {
            return null;
        }

        $sofficeBin = $this->resolverSofficeBin();
        if ($sofficeBin === null) {
            Log::warning('mesas_paz pdf preview: no se encontró LibreOffice (soffice) para convertir PPTX a PDF.');

            return null;
        }

        $outDir = Storage::disk('local')->path('ppt_previews');
        if (! is_dir($outDir)) {
            @mkdir($outDir, 0775, true);
        }

        $baseName = pathinfo($pptxPath, PATHINFO_FILENAME);
        $convertedPdf = rtrim($outDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$baseName.'.pdf';

        @unlink($convertedPdf);

        try {
            $process = new Process([
                $sofficeBin,
                '--headless',
                '--nologo',
                '--norestore',
                '--nodefault',
                '--nolockcheck',
                '--convert-to',
                'pdf:impress_pdf_Export',
                '--outdir',
                $outDir,
                $pptxPath,
            ]);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::warning('mesas_paz pdf preview: conversión PPTX->PDF falló.', [
                    'exit_code' => $process->getExitCode(),
                    'stderr' => $process->getErrorOutput(),
                    'stdout' => $process->getOutput(),
                ]);

                return null;
            }

            if (! is_file($convertedPdf) || (int) @filesize($convertedPdf) <= 0) {
                Log::warning('mesas_paz pdf preview: soffice terminó pero no produjo PDF válido.', [
                    'expected_pdf' => $convertedPdf,
                ]);

                return null;
            }

            $finalPdf = rtrim($outDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$token.'.pdf';
            if (strcasecmp($convertedPdf, $finalPdf) !== 0) {
                @unlink($finalPdf);
                @rename($convertedPdf, $finalPdf);
            }

            return is_file($finalPdf) ? $finalPdf : (is_file($convertedPdf) ? $convertedPdf : null);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function resolverSofficeBin(): ?string
    {
        $configured = trim((string) config('mesas_paz.ppt_pdf_converter_bin', ''));
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $candidates = [
            'soffice',
            'soffice.exe',
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        ];

        foreach ($candidates as $candidate) {
            if (str_contains($candidate, ':\\')) {
                if (is_file($candidate)) {
                    return $candidate;
                }
                continue;
            }

            try {
                $probe = new Process([$candidate, '--version']);
                $probe->setTimeout(8);
                $probe->run();
                if ($probe->isSuccessful()) {
                    return $candidate;
                }
            } catch (\Throwable) {
                // Ignorado: seguimos buscando otro binario.
            }
        }

        return null;
    }
}
