<?php

namespace App\Services\MesasPaz;

use App\Models\MesaPazAsistencia;
use App\Models\Microrregione;
use App\Models\Municipio;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class MesasPazPresentationService
{
    private const DRAWINGML_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    private const PRESENTATIONML_NS = 'http://schemas.openxmlformats.org/presentationml/2006/main';

    private const REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const OFFICE_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** Lienzo PNG de la gráfica circular (alto extra para dona grande + leyenda). */
    private const PPT_CIRCULAR_PNG_W = 3600;

    private const PPT_CIRCULAR_PNG_H = 2000;

    /** Posición y tamaño de la imagen en PPTX (slides 3 y 4), en EMU (914400 EMU = 1 pulgada). */
    private const PPT_CIRCULAR_PIC_X = 4920000;

    private const PPT_CIRCULAR_PIC_Y = 3020000;

    private const PPT_CIRCULAR_PIC_CX = 8800000;

    /**
     * Mismo ratio que el PNG (W×H). Si cx/cy no coincide, el stretch deforma la dona.
     */
    private const PPT_CIRCULAR_PIC_CY = 4888889;

    /** Tamaño de diapositiva de la plantilla PPTX (`ppt/presentation.xml` sldSz). */
    private const PPTX_SLIDE_CX_EMU = 13716000;

    private const PPTX_SLIDE_CY_EMU = 10287000;

    /** Origen Y del panel microrregión (solo se alarga hacia abajo al subir picCy). */
    private const PPT_MICROREGION_PANEL_Y_EMU = 2780000;

    /**
     * Datos para reporte y vista previa
     * @return array{
     *     rango_formulario: string,
     *     fecha_generacion: string,
     *     texto_semana_analizada: string,
     *     semana_contada_desde: string,
     *     semana_contada_hasta: string,
     *     total_mesas: int,
     *     mesas_con_asistencia: int,
     *     mesas_con_inasistencia: int,
     *     meta_mesas: int,
     *     porcentaje_cumplimiento: float,
     *     mesas_sin_registro_semanal: int,
     *     total_municipios_catalogo: int
     * }
     */
    public function resumenPresentacion(string $fechaInicio, string $fechaFin): array
    {
        $inicio = Carbon::parse($fechaInicio)->startOfDay();
        $fin = Carbon::parse($fechaFin)->startOfDay();
        $ctx = $this->resolverContextoPresentacion($inicio, $fin);

        return [
            'rango_formulario' => $ctx['rango'],
            'fecha_generacion' => $ctx['fecha_actual_texto'],
            'texto_semana_analizada' => $ctx['texto_semana_anterior'],
            'semana_contada_desde' => $ctx['lunes_anterior']->toDateString(),
            'semana_contada_hasta' => $ctx['viernes_anterior']->toDateString(),
            'total_mesas' => $ctx['stats']['total_mesas'],
            'mesas_con_asistencia' => $ctx['stats']['mesas_con_asistencia'],
            'mesas_con_inasistencia' => $ctx['stats']['mesas_con_inasistencia'],
            'meta_mesas' => $ctx['meta_mesas'],
            'porcentaje_cumplimiento' => $ctx['pct_cumplimiento'],
            'mesas_sin_registro_semanal' => $ctx['mesas_sin_registro_semanal'],
            'total_municipios_catalogo' => $ctx['total_municipios_catalogo'],
        ];
    }

    /**
     * @param  array{total_mesas: int, mesas_con_asistencia: int, mesas_con_inasistencia: int, por_dia: array, municipios_distintos_con_registro: int}  $stats
     * @return string|null Ruta absoluta a un PNG temporal
     */
    public function crearPngGraficaBarras(int $metaMesas, array $stats): ?string
    {
        return $this->crearArchivoPngGraficaBarras(
            $metaMesas,
            $stats['mesas_con_asistencia'],
            $stats['mesas_con_inasistencia'],
            $stats['total_mesas'],
        );
    }

    /**
     * Genera archivo PPTX
     * @return string Ruta absoluta del archivo generado.
     */
    public function generar(string $fechaInicio, string $fechaFin): string
    {
        $inicio = Carbon::parse($fechaInicio)->startOfDay();
        $fin = Carbon::parse($fechaFin)->startOfDay();
        $ctx = $this->resolverContextoPresentacion($inicio, $fin);
        $ctxSemanaMarcada = $this->resolverContextoPresentacion($inicio->copy()->addDays(7), $fin->copy()->addDays(7));

        $rango = $ctx['rango'];
        $fechaActual = $ctx['fecha_actual_texto'];
        $lunesAnterior = $ctx['lunes_anterior'];
        $viernesAnterior = $ctx['viernes_anterior'];
        $textoSemanaAnterior = $ctx['texto_semana_anterior'];
        $stats = $ctx['stats'];
        $metaMesas = $ctx['meta_mesas'];
        $mesasSinRegistroSemanal = $ctx['mesas_sin_registro_semanal'];
        $pctCumplimiento = $ctx['pct_cumplimiento'];
        $textoSemanaMarcada = $ctxSemanaMarcada['texto_semana_anterior'];
        $statsSemanaMarcada = $ctxSemanaMarcada['stats'];
        $metaSemanaMarcada = $ctxSemanaMarcada['meta_mesas'];
        $mesasSinRegistroSemanaMarcada = $ctxSemanaMarcada['mesas_sin_registro_semanal'];
        $pctCumplimientoSemanaMarcada = $ctxSemanaMarcada['pct_cumplimiento'];
        $lunesActual = $ctxSemanaMarcada['lunes_anterior'];
        $viernesActual = $ctxSemanaMarcada['viernes_anterior'];

        Log::info('mesas_paz ppt: totales semana laboral previa al reporte', [
            'semana_contada' => $lunesAnterior->toDateString().' a '.$viernesAnterior->toDateString(),
            'total_mesas_municipio_dia' => $stats['total_mesas'],
            'mesas_con_asistencia' => $stats['mesas_con_asistencia'],
            'mesas_con_inasistencia' => $stats['mesas_con_inasistencia'],
            'meta_mesas_esperadas' => $metaMesas,
            'porcentaje_cumplimiento' => $pctCumplimiento,
            'mesas_sin_registro_semanal' => $mesasSinRegistroSemanal,
            'municipios_sin_captura_catalogo' => $ctx['municipios_sin_registro_catalogo'],
            'por_dia' => $stats['por_dia'],
        ]);

        $templatePath = storage_path('templates/Dgdg Mesas de paz.pptx');
        if (! file_exists($templatePath)) {
            throw new \Exception('No se encontró la plantilla en: '.$templatePath);
        }

        $directorio = storage_path('app/public/ppt');
        if (! is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $nombreArchivo = 'mesas_paz_'.$inicio->format('Ymd').'_'.$fin->format('Ymd').'_'.time().'.pptx';
        $rutaCompleta = $directorio.DIRECTORY_SEPARATOR.$nombreArchivo;

        $tmpBase = tempnam(sys_get_temp_dir(), 'mpptx_');
        if ($tmpBase === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal para la presentación.');
        }
        $rutaTrabajo = $tmpBase.'.pptx';
        @unlink($tmpBase);

        if (! @copy($templatePath, $rutaTrabajo)) {
            @unlink($rutaTrabajo);
            throw new \RuntimeException('No se pudo copiar la plantilla a un archivo temporal.');
        }

        $zip = new ZipArchive;
        if ($zip->open($rutaTrabajo) !== true) {
            @unlink($rutaTrabajo);
            throw new \Exception('No se pudo procesar la plantilla PPTX.');
        }

        $slideXmlStr = $zip->getFromName('ppt/slides/slide1.xml');
        if ($slideXmlStr !== false) {
            $slideXmlStr = str_replace('<a:t>Fecha</a:t>', '<a:t>'.$this->xmlText($rango).'</a:t>', $slideXmlStr);
            $slideXmlStr = preg_replace('/>Fecha([^<]*?)</i', '>'.$this->xmlText($rango).'$1<', $slideXmlStr);

            $slideXmlStr = str_replace('30 de Febrero del 2026', $this->xmlText($fechaActual), $slideXmlStr);
            if (strpos($slideXmlStr, $fechaActual) === false) {
                $slideXmlStr = preg_replace('/>30\s*</', '>'.$this->xmlText($fechaActual).'<', $slideXmlStr);
                $slideXmlStr = preg_replace('/>de Febrero del 2026\s*</', '><', $slideXmlStr);
                $slideXmlStr = preg_replace('/>30 de Febrero\s*</', '>'.$this->xmlText($fechaActual).'<', $slideXmlStr);
                $slideXmlStr = preg_replace('/>del 2026\s*</', '><', $slideXmlStr);
            }

            $zip->addFromString('ppt/slides/slide1.xml', $slideXmlStr);
        }

        $slide3 = $zip->getFromName('ppt/slides/slide3.xml');
        $slide4 = $zip->getFromName('ppt/slides/slide4.xml');
        $pngTemporales = [];

        if ($slide3 !== false) {
            $relsPath = 'ppt/slides/_rels/slide3.xml.rels';
            $relsContent = $zip->getFromName($relsPath);
            if (! is_string($relsContent)) {
                $relsContent = '';
            }

            $slide3 = $this->parchearSlide3ReporteGeneral(
                $slide3,
                $lunesAnterior,
                $viernesAnterior,
                $stats['total_mesas'],
                $stats['mesas_con_asistencia'],
                $stats['mesas_con_inasistencia'],
                $pctCumplimiento,
                $mesasSinRegistroSemanal,
            );

            [$slide3, $relsContent, $pngTemporal] = $this->inyectarGraficaCircularEnSlide(
                $zip,
                $slide3,
                $relsContent,
                $metaMesas,
                $stats,
                'Grafica circular semana anterior',
                self::PPT_CIRCULAR_PIC_X,
                self::PPT_CIRCULAR_PIC_Y,
                self::PPT_CIRCULAR_PIC_CX,
                self::PPT_CIRCULAR_PIC_CY,
            );

            $zip->addFromString('ppt/slides/slide3.xml', $slide3);
            if ($relsContent !== '') {
                $zip->addFromString($relsPath, $relsContent);
            }
            if (is_string($pngTemporal) && $pngTemporal !== '' && is_file($pngTemporal)) {
                $pngTemporales[] = $pngTemporal;
            }
        } else {
            Log::warning('mesas_paz.pptx: no existe ppt/slides/slide3.xml; se omiten totales del reporte general.');
        }

        if ($slide4 !== false) {
            $relsPath4 = 'ppt/slides/_rels/slide4.xml.rels';
            $relsContent4 = $zip->getFromName($relsPath4);
            if (! is_string($relsContent4)) {
                $relsContent4 = '';
            }

            $slide4 = $this->parchearSlide3ReporteGeneral(
                $slide4,
                $lunesActual,
                $viernesActual,
                $statsSemanaMarcada['total_mesas'],
                $statsSemanaMarcada['mesas_con_asistencia'],
                $statsSemanaMarcada['mesas_con_inasistencia'],
                $pctCumplimientoSemanaMarcada,
                $mesasSinRegistroSemanaMarcada,
            );

            [$slide4, $relsContent4, $pngTemporal4] = $this->inyectarGraficaCircularEnSlide(
                $zip,
                $slide4,
                $relsContent4,
                $metaSemanaMarcada,
                $statsSemanaMarcada,
                'Grafica circular semana marcada',
                self::PPT_CIRCULAR_PIC_X,
                self::PPT_CIRCULAR_PIC_Y,
                self::PPT_CIRCULAR_PIC_CX,
                self::PPT_CIRCULAR_PIC_CY,
            );

            $zip->addFromString('ppt/slides/slide4.xml', $slide4);
            if ($relsContent4 !== '') {
                $zip->addFromString($relsPath4, $relsContent4);
            }

            if (is_string($pngTemporal4) && $pngTemporal4 !== '' && is_file($pngTemporal4)) {
                $pngTemporales[] = $pngTemporal4;
            }
        }

        $slide5 = $zip->getFromName('ppt/slides/slide5.xml');
        if ($slide5 !== false) {
            $relsPath5 = 'ppt/slides/_rels/slide5.xml.rels';
            $relsContent5 = $zip->getFromName($relsPath5);
            if (! is_string($relsContent5)) {
                $relsContent5 = '';
            }

            $slide5 = $this->parchearSlide5ReporteDiarioSemanal(
                $slide5,
                $lunesAnterior,
                $viernesAnterior,
                $lunesActual,
                $viernesActual
            );

            [$slide5, $relsContent5, $pngTemporalesSlide5] = $this->inyectarGraficasDiariasEnSlide5(
                $zip,
                $slide5,
                $relsContent5,
                $lunesAnterior,
                $stats['por_dia'],
                $lunesActual,
                $statsSemanaMarcada['por_dia'],
            );

            $zip->addFromString('ppt/slides/slide5.xml', $slide5);
            if ($relsContent5 !== '') {
                $zip->addFromString($relsPath5, $relsContent5);
            }
            foreach ($pngTemporalesSlide5 as $pngTmp5) {
                if (is_string($pngTmp5) && $pngTmp5 !== '' && is_file($pngTmp5)) {
                    $pngTemporales[] = $pngTmp5;
                }
            }
        }

        $microSlides = [];
        for ($i = 6; $i <= 40; $i++) {
            $xml = $zip->getFromName('ppt/slides/slide'.$i.'.xml');
            if ($xml === false) {
                continue;
            }
            $mrN = $this->extraerNumeroMicroregionDesdeSlideXml((string) $xml);
            if ($mrN !== null) {
                $microSlides[$i] = $mrN;
            }
        }

        if (! empty($microSlides)) {
            $microPorNumero = [];
            $microIds = [];
            foreach (array_unique(array_values($microSlides)) as $mrNumero) {
                $micro = $this->resolverMicroregionPorNumero($mrNumero);
                if ($micro) {
                    $microPorNumero[$mrNumero] = $micro;
                    $microIds[] = (int) $micro->getKey();
                }
            }
            $microIds = array_values(array_unique(array_filter($microIds)));

            if (! empty($microIds)) {
                $municipiosRows = Municipio::query()
                    ->whereIn('microrregion_id', $microIds)
                    ->orderBy('municipio')
                    ->get(['id', 'microrregion_id', 'municipio']);

                $municipiosPorMicro = [];
                foreach ($municipiosRows as $row) {
                    $mid = (int) $row->microrregion_id;
                    $municipiosPorMicro[$mid] ??= [];
                    $municipiosPorMicro[$mid][(int) $row->id] = (string) $row->municipio;
                }

                $desdeAll = $lunesAnterior->toDateString();
                $hastaAll = $viernesActual->toDateString();

                $filas = MesaPazAsistencia::query()
                    ->whereIn('microrregion_id', $microIds)
                    ->whereDate('fecha_asist', '>=', $desdeAll)
                    ->whereDate('fecha_asist', '<=', $hastaAll)
                    ->orderByDesc('created_at')
                    ->get(['microrregion_id', 'municipio_id', 'fecha_asist', 'asiste', 'presidente', 'modalidad', 'created_at']);

                $registrosPorMicroDia = [];
                foreach ($filas as $fila) {
                    $microId = (int) $fila->microrregion_id;
                    $munId = (int) $fila->municipio_id;
                    if ($microId <= 0 || $munId <= 0) {
                        continue;
                    }
                    $fecha = $fila->fecha_asist instanceof Carbon
                        ? $fila->fecha_asist->format('Y-m-d')
                        : Carbon::parse((string) $fila->fecha_asist)->format('Y-m-d');

                    $registrosPorMicroDia[$microId] ??= [];
                    $registrosPorMicroDia[$microId][$fecha] ??= [];
                    if (isset($registrosPorMicroDia[$microId][$fecha][$munId])) {
                        continue;
                    }

                    $registrosPorMicroDia[$microId][$fecha][$munId] = [
                        'municipio_id' => $munId,
                        'fecha' => $fecha,
                        'asiste' => $fila->asiste,
                        'presidente' => $fila->presidente,
                        'modalidad' => $fila->modalidad,
                    ];
                }

                $statsPrev = [];
                $statsCur = [];
                foreach ($microIds as $microId) {
                    $munMap = $municipiosPorMicro[$microId] ?? [];
                    $statsPrev[$microId] = $this->estadisticasSemanaMicroregionDesdeCache(
                        $lunesAnterior,
                        $viernesAnterior,
                        $microId,
                        $munMap,
                        $registrosPorMicroDia[$microId] ?? []
                    );
                    $statsCur[$microId] = $this->estadisticasSemanaMicroregionDesdeCache(
                        $lunesActual,
                        $viernesActual,
                        $microId,
                        $munMap,
                        $registrosPorMicroDia[$microId] ?? []
                    );
                }

                foreach ($microSlides as $slideN => $mrNumero) {
                    $micro = $microPorNumero[$mrNumero] ?? null;
                    if (! $micro) {
                        continue;
                    }
                    $microId = (int) $micro->getKey();

                    $slideXml = $zip->getFromName('ppt/slides/slide'.$slideN.'.xml');
                    if ($slideXml === false) {
                        continue;
                    }

                    $relsPath = 'ppt/slides/_rels/slide'.$slideN.'.xml.rels';
                    $relsContent = $zip->getFromName($relsPath);
                    if (! is_string($relsContent)) {
                        $relsContent = '';
                    }

                    $cabecera = (string) ($micro->cabecera ?? '');
                    if ($cabecera === '') {
                        $cabecera = (string) ($micro->microrregion ?? '');
                    }

                    $slideXml = $this->parchearSlideMicroregion(
                        (string) $slideXml,
                        $mrNumero,
                        $cabecera,
                        $lunesAnterior,
                        $viernesAnterior
                    );

                    [$slideXml, $relsContent, $pngTmpMicro] = $this->inyectarPanelMicroregionEnSlide(
                        $zip,
                        (string) $slideXml,
                        (string) $relsContent,
                        $mrNumero,
                        $cabecera,
                        $lunesAnterior,
                        $viernesAnterior,
                        $lunesActual,
                        $viernesActual,
                        $statsPrev[$microId] ?? null,
                        $statsCur[$microId] ?? null,
                    );

                    $zip->addFromString('ppt/slides/slide'.$slideN.'.xml', $slideXml);
                    if ($relsContent !== '') {
                        $zip->addFromString($relsPath, $relsContent);
                    }
                    if (is_string($pngTmpMicro) && $pngTmpMicro !== '' && is_file($pngTmpMicro)) {
                        $pngTemporales[] = $pngTmpMicro;
                    }
                }
            }
        }

        $zip->close();
        foreach ($pngTemporales as $pngTmp) {
            @unlink($pngTmp);
        }

        if (! @copy($rutaTrabajo, $rutaCompleta)) {
            @unlink($rutaTrabajo);
            throw new \RuntimeException('No se pudo guardar la presentación generada.');
        }
        @unlink($rutaTrabajo);

        return $rutaCompleta;
    }

    /**
     * @return array{
     *     rango: string,
     *     fecha_actual_texto: string,
     *     lunes_anterior: Carbon,
     *     viernes_anterior: Carbon,
     *     texto_semana_anterior: string,
     *     stats: array,
     *     meta_mesas: int,
     *     total_municipios_catalogo: int,
     *     municipios_sin_registro_catalogo: int,
     *     mesas_sin_registro_semanal: int,
     *     pct_cumplimiento: float
     * }
     */
    private function resolverContextoPresentacion(Carbon $inicio, Carbon $fin): array
    {
        for ($d = $inicio->copy(); $d <= $fin; $d->addDay()) {
            if ($d->isWeekend()) {
                throw new \InvalidArgumentException('El rango de fechas no debe incluir sábados ni domingos.');
            }
        }

        $rango = $inicio->format('d/m/Y').' al '.$fin->format('d/m/Y');
        $fechaActualTexto = Carbon::now()->locale('es')->isoFormat('D [de] MMMM [del] YYYY');

        [$lunesAnterior, $viernesAnterior] = $this->semanaLaboralAnterior($fin);
        $textoSemanaAnterior = $this->formatoRangoSemanaEspanol($lunesAnterior, $viernesAnterior);
        $stats = $this->estadisticasSemanaLaboral($lunesAnterior, $viernesAnterior);

        $metaMesas = $this->metaMesasSeguridadEsperadas();
        $totalMunicipiosCatalogo = $this->totalMunicipiosCatalogo();
        $municipiosSinRegistroCatalogo = max(0, $totalMunicipiosCatalogo - $stats['municipios_distintos_con_registro']);
        $mesasSinRegistroSemanal = max(0, $metaMesas - $stats['total_mesas']);
        $pctCumplimiento = $this->calcularPorcentajeCumplimientoCombinado(
            $metaMesas,
            $stats['mesas_con_asistencia'],
            $mesasSinRegistroSemanal,
        );

        return [
            'rango' => $rango,
            'fecha_actual_texto' => $fechaActualTexto,
            'lunes_anterior' => $lunesAnterior,
            'viernes_anterior' => $viernesAnterior,
            'texto_semana_anterior' => $textoSemanaAnterior,
            'stats' => $stats,
            'meta_mesas' => $metaMesas,
            'total_municipios_catalogo' => $totalMunicipiosCatalogo,
            'municipios_sin_registro_catalogo' => $municipiosSinRegistroCatalogo,
            'mesas_sin_registro_semanal' => $mesasSinRegistroSemanal,
            'pct_cumplimiento' => $pctCumplimiento,
        ];
    }

    private function calcularPorcentajeCumplimientoCombinado(int $metaMesas, int $asistencias, int $sinRegistro): float
    {
        if ($metaMesas <= 0) {
            return 0.0;
        }

        $pctAsistencias = ($asistencias / $metaMesas) * 100;
        $pctSinRegistro = ($sinRegistro / $metaMesas) * 100;

        return round(($pctAsistencias + $pctSinRegistro) / 2, 2);
    }

    private function metaMesasSeguridadEsperadas(): int
    {
        $n = (int) config('mesas_paz.total_municipios', 217);
        $dias = max(1, (int) config('mesas_paz.dias_laborales_semana', 5));
        if ($n <= 0) {
            try {
                $n = (int) Municipio::query()->count();
            } catch (\Throwable) {
                $n = 217;
            }
        }

        return $n * $dias;
    }

    private function totalMunicipiosCatalogo(): int
    {
        $n = (int) config('mesas_paz.total_municipios', 217);
        if ($n > 0) {
            return $n;
        }
        try {
            return (int) Municipio::query()->count();
        } catch (\Throwable) {
            return 217;
        }
    }

    /**
     * @param  array{total_mesas: int, mesas_con_asistencia: int, mesas_con_inasistencia: int, por_dia: array, municipios_distintos_con_registro: int}  $stats
     * @return array{0: string, 1: string, 2: string|null} Tercer valor: ruta PNG temporal a borrar tras ZipArchive::close(), o null
     */
    private function inyectarGraficaBarrasEnSlide3(
        ZipArchive $zip,
        string $slide3Xml,
        string $relsContent,
        int $metaMesas,
        array $stats,
    ): array {
        if (! extension_loaded('gd')) {
            Log::warning('mesas_paz ppt: extensión GD no disponible; no se inserta gráfica.');

            return [$slide3Xml, $relsContent, null];
        }

        $pngPath = $this->crearArchivoPngGraficaBarras(
            $metaMesas,
            $stats['mesas_con_asistencia'],
            $stats['mesas_con_inasistencia'],
            $stats['total_mesas'],
        );

        if ($pngPath === null || ! is_file($pngPath)) {
            return [$slide3Xml, $relsContent, null];
        }

        $mediaName = $this->siguienteNombreImagenPngEnZip($zip);
        if (! $zip->addFile($pngPath, 'ppt/media/'.$mediaName)) {
            @unlink($pngPath);
            Log::warning('mesas_paz ppt: no se pudo añadir la imagen al zip.');

            return [$slide3Xml, $relsContent, null];
        }

        $nextRid = $this->siguienteRIdEnRels($relsContent);
        $relsContent = $this->relsAgregarRelacionImagen($relsContent, $nextRid, $mediaName);

        try {
            $slide3Xml = $this->slideInsertarPicture(
                $slide3Xml,
                'rId'.$nextRid,
                'Grafica barras mesas de seguridad',
                4180000,
                1680000,
                4680000,
                2320000,
            );
        } catch (\Throwable $e) {
            Log::warning('mesas_paz ppt: no se pudo insertar p:pic en slide3. '.$e->getMessage());
        }

        return [$slide3Xml, $relsContent, $pngPath];
    }

    /**
     * @param  array{total_mesas: int, mesas_con_asistencia: int, mesas_con_inasistencia: int, por_dia: array, municipios_distintos_con_registro: int}  $stats
     * @return array{0: string, 1: string, 2: string|null}
     */
    private function inyectarGraficaCircularEnSlide(
        ZipArchive $zip,
        string $slideXml,
        string $relsContent,
        int $metaMesas,
        array $stats,
        string $shapeName,
        int $x,
        int $y,
        int $cx,
        int $cy,
    ): array {
        if (! extension_loaded('gd')) {
            Log::warning('mesas_paz ppt: extensión GD no disponible; no se inserta gráfica circular.');

            return [$slideXml, $relsContent, null];
        }

        $pngPath = $this->crearArchivoPngGraficaCircular(
            $metaMesas,
            $stats['mesas_con_asistencia'],
            $stats['mesas_con_inasistencia'],
            $stats['total_mesas'],
        );

        if ($pngPath === null || ! is_file($pngPath)) {
            return [$slideXml, $relsContent, null];
        }

        $mediaName = $this->siguienteNombreImagenPngEnZip($zip);
        if (! $zip->addFile($pngPath, 'ppt/media/'.$mediaName)) {
            @unlink($pngPath);
            Log::warning('mesas_paz ppt: no se pudo añadir la imagen circular al zip.');

            return [$slideXml, $relsContent, null];
        }

        $nextRid = $this->siguienteRIdEnRels($relsContent);
        $relsContent = $this->relsAgregarRelacionImagen($relsContent, $nextRid, $mediaName);

        try {
            $slideXml = $this->slideInsertarPicture($slideXml, 'rId'.$nextRid, $shapeName, $x, $y, $cx, $cy);
        } catch (\Throwable $e) {
            Log::warning('mesas_paz ppt: no se pudo insertar p:pic circular. '.$e->getMessage());
        }

        return [$slideXml, $relsContent, $pngPath];
    }

    private function siguienteNombreImagenPngEnZip(ZipArchive $zip): string
    {
        $max = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && preg_match('#^ppt/media/image(\d+)\.png$#i', $name, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return 'image'.($max + 1).'.png';
    }

    private function siguienteRIdEnRels(string $relsXml): int
    {
        if ($relsXml === '' || ! str_contains($relsXml, 'Relationship')) {
            return 1;
        }
        $max = 0;
        if (preg_match_all('/Id="rId(\d+)"/', $relsXml, $matches)) {
            foreach ($matches[1] as $n) {
                $max = max($max, (int) $n);
            }
        }

        return $max + 1;
    }

    private function relsAgregarRelacionImagen(string $relsXml, int $ridNum, string $mediaFileName): string
    {
        $id = 'rId'.$ridNum;
        $fragment = '<Relationship Id="'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/'.$this->xmlAttr($mediaFileName).'"/>';

        if (trim($relsXml) === '') {
            return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="'.self::REL_NS.'">'.$fragment.'</Relationships>';
        }

        if (str_contains($relsXml, 'Id="'.$id.'"')) {
            return $relsXml;
        }

        return preg_replace(
            '#</Relationships>\s*$#',
            $fragment.'</Relationships>',
            $relsXml,
            1
        ) ?? $relsXml;
    }

    private function xmlAttr(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function slideInsertarPicture(
        string $slideXml,
        string $rId,
        string $shapeName,
        int $x,
        int $y,
        int $cx,
        int $cy
    ): string
    {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;
        if (! @$dom->loadXML($slideXml, LIBXML_NONET)) {
            return $slideXml;
        }

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('p', self::PRESENTATIONML_NS);

        $spTreeList = $xp->query('//p:spTree');
        if (! $spTreeList || $spTreeList->length === 0) {
            return $slideXml;
        }

        $spTree = $spTreeList->item(0);
        if (! $spTree instanceof DOMElement) {
            return $slideXml;
        }

        $nextShapeId = $this->siguienteIdFormaEnDocumento($dom);
        $pic = $this->construirElementoPic($dom, $nextShapeId, $rId, $shapeName, $x, $y, $cx, $cy);

        $elementos = [];
        foreach ($spTree->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $elementos[] = $child;
            }
        }

        if (count($elementos) >= 2) {
            $spTree->insertBefore($pic, $elementos[count($elementos) - 1]);
        } else {
            $spTree->appendChild($pic);
        }

        $out = $dom->saveXML();
        if (! is_string($out) || $out === '') {
            return $slideXml;
        }

        return $out;
    }

    private function siguienteIdFormaEnDocumento(DOMDocument $dom): int
    {
        $max = 0;
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('p', self::PRESENTATIONML_NS);
        $nodes = $xp->query('//*[@id]');
        if ($nodes) {
            for ($i = 0; $i < $nodes->length; $i++) {
                $n = $nodes->item($i);
                if ($n instanceof DOMElement && $n->hasAttribute('id')) {
                    $v = (int) $n->getAttribute('id');
                    if ($v > $max) {
                        $max = $v;
                    }
                }
            }
        }

        return $max + 1;
    }

    private function construirElementoPic(
        DOMDocument $dom,
        int $shapeId,
        string $rId,
        string $shapeName,
        int $x,
        int $y,
        int $cx,
        int $cy,
    ): DOMElement
    {
        $pic = $dom->createElementNS(self::PRESENTATIONML_NS, 'p:pic');

        $nvPicPr = $dom->createElementNS(self::PRESENTATIONML_NS, 'p:nvPicPr');
        $cNvPr = $dom->createElementNS(self::PRESENTATIONML_NS, 'p:cNvPr');
        $cNvPr->setAttribute('id', (string) $shapeId);
        $cNvPr->setAttribute('name', $shapeName);
        $nvPicPr->appendChild($cNvPr);

        $cNvPicPr = $dom->createElementNS(self::PRESENTATIONML_NS, 'p:cNvPicPr');
        $picLocks = $dom->createElementNS(self::DRAWINGML_NS, 'a:picLocks');
        $picLocks->setAttribute('noChangeAspect', '1');
        $cNvPicPr->appendChild($picLocks);
        $nvPicPr->appendChild($cNvPicPr);

        $nvPicPr->appendChild($dom->createElementNS(self::PRESENTATIONML_NS, 'p:nvPr'));
        $pic->appendChild($nvPicPr);

        $blipFill = $dom->createElementNS(self::PRESENTATIONML_NS, 'p:blipFill');
        $blip = $dom->createElementNS(self::DRAWINGML_NS, 'a:blip');
        $blip->setAttributeNS(self::OFFICE_REL, 'r:embed', $rId);
        $blipFill->appendChild($blip);
        $stretch = $dom->createElementNS(self::DRAWINGML_NS, 'a:stretch');
        $stretch->appendChild($dom->createElementNS(self::DRAWINGML_NS, 'a:fillRect'));
        $blipFill->appendChild($stretch);
        $pic->appendChild($blipFill);

        $spPr = $dom->createElementNS(self::PRESENTATIONML_NS, 'p:spPr');
        $xfrm = $dom->createElementNS(self::DRAWINGML_NS, 'a:xfrm');
        $off = $dom->createElementNS(self::DRAWINGML_NS, 'a:off');
        $off->setAttribute('x', (string) $x);
        $off->setAttribute('y', (string) $y);
        $ext = $dom->createElementNS(self::DRAWINGML_NS, 'a:ext');
        $ext->setAttribute('cx', (string) $cx);
        $ext->setAttribute('cy', (string) $cy);
        $xfrm->appendChild($off);
        $xfrm->appendChild($ext);
        $spPr->appendChild($xfrm);
        $prst = $dom->createElementNS(self::DRAWINGML_NS, 'a:prstGeom');
        $prst->setAttribute('prst', 'rect');
        $prst->appendChild($dom->createElementNS(self::DRAWINGML_NS, 'a:avLst'));
        $spPr->appendChild($prst);
        $pic->appendChild($spPr);

        return $pic;
    }

    /**
     * @return string|null Ruta temporal al PNG o null si falla.
     */
    private function crearArchivoPngGraficaBarras(int $meta, int $asistencias, int $inasistencias, int $totalRegistrado): ?string
    {
        $w = 640;
        $h = 210;
        $im = imagecreatetruecolor($w, $h);
        if ($im === false) {
            return null;
        }

        $blanco = imagecolorallocate($im, 255, 255, 255);
        $gris = imagecolorallocate($im, 230, 230, 230);
        $dorado = imagecolorallocate($im, 184, 155, 106);
        $grisOscuro = imagecolorallocate($im, 58, 58, 58);
        $guinda = imagecolorallocate($im, 134, 30, 61);

        imagefilledrectangle($im, 0, 0, $w, $h, $blanco);

        $font = public_path('fonts/agenda-pdf/Gilroy-ExtraBold.ttf');
        if (! is_file($font)) {
            $font = public_path('fonts/agenda-pdf/Gilroy-Bold.ttf');
        }
        if (! is_file($font)) {
            $font = public_path('fonts/agenda-pdf/Montserrat-Bold.ttf');
        }
        if (! is_file($font)) {
            $font = public_path('fonts/agenda-pdf/Montserrat-Regular.ttf');
        }
        if (! is_file($font)) {
            $font = null;
        }

        $maxVal = max($meta, 1);
        $barStart = 100;
        $margenDer = 16;
        $y0 = 22;
        $altoFila = 54;
        $anchoBarra = $w - $barStart - $margenDer;
        $fontSize = 20;
        $barH = 32;

        $filas = [
            ['val' => $totalRegistrado, 'color' => $dorado],
            ['val' => $asistencias, 'color' => $grisOscuro],
            ['val' => $inasistencias, 'color' => $guinda],
        ];

        $row = 0;
        foreach ($filas as $fila) {
            $y = $y0 + $row * $altoFila;
            $frac = min(1.0, $fila['val'] / $maxVal);
            $bw = (int) round($anchoBarra * $frac);
            imagefilledrectangle($im, $barStart, $y, $barStart + $anchoBarra, $y + $barH, $gris);
            imagefilledrectangle($im, $barStart, $y, $barStart + max(2, $bw), $y + $barH, $fila['color']);

            $numStr = (string) $fila['val'];
            if ($font) {
                $bbox = imagettfbbox($fontSize, 0, $font, $numStr);
                if ($bbox !== false) {
                    $tw = (int) abs($bbox[2] - $bbox[0]);
                    $nx = $barStart - 10 - $tw;
                    @imagettftext($im, $fontSize, 0, $nx, $y + 28, $fila['color'], $font, $numStr);
                }
            } else {
                imagestring($im, 4, max(4, $barStart - 44), $y + 8, $numStr, $fila['color']);
            }
            $row++;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mppt_');
        if ($tmp === false) {
            imagedestroy($im);

            return null;
        }
        $path = $tmp.'.png';
        rename($tmp, $path);
        imagepng($im, $path);
        imagedestroy($im);

        return $path;
    }

    /**
     * @return string|null Ruta temporal al PNG o null si falla.
     */
    private function crearArchivoPngGraficaCircular(int $meta, int $asistencias, int $inasistencias, int $totalRegistrado): ?string
    {
        $w = self::PPT_CIRCULAR_PNG_W;
        $h = self::PPT_CIRCULAR_PNG_H;
        $im = imagecreatetruecolor($w, $h);
        if ($im === false) {
            return null;
        }

        imagealphablending($im, false);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefilledrectangle($im, 0, 0, $w, $h, $transparent);
        imagealphablending($im, true);

        $verde = imagecolorallocate($im, 11, 78, 73);
        $guinda = imagecolorallocate($im, 134, 30, 61);
        $dorado = imagecolorallocate($im, 184, 155, 106);
        $blanco = imagecolorallocate($im, 255, 255, 255);
        $texto = imagecolorallocate($im, 58, 58, 58);
        $textoSuave = imagecolorallocate($im, 107, 114, 128);

        $sinRegistro = max(0, $meta - $totalRegistrado);
        $total = max(1, $asistencias + $inasistencias + $sinRegistro);

        $font = public_path('fonts/agenda-pdf/Gilroy-ExtraBold.ttf');
        if (! is_file($font)) {
            $font = public_path('fonts/agenda-pdf/Gilroy-Bold.ttf');
        }
        if (! is_file($font)) {
            $font = public_path('fonts/agenda-pdf/Montserrat-Bold.ttf');
        }
        if (! is_file($font)) {
            $font = public_path('fonts/agenda-pdf/Montserrat-Regular.ttf');
        }

        /* Misma familia y peso que "X% de cumplimiento" en la plantilla (slide3: Gilroy + b="1"). */
        $fontLegend = public_path('fonts/agenda-pdf/Gilroy-Bold.ttf');
        if (! is_file($fontLegend)) {
            $fontLegend = public_path('fonts/agenda-pdf/Gilroy-SemiBold.ttf');
        }
        if (! is_file($fontLegend)) {
            $fontLegend = public_path('fonts/agenda-pdf/Gilroy-Medium.ttf');
        }
        if (! is_file($fontLegend)) {
            $fontLegend = $font;
        }

        $segmentos = [
            ['v' => $asistencias, 'c' => $verde],
            ['v' => $inasistencias, 'c' => $guinda],
            ['v' => $sinRegistro, 'c' => $dorado],
        ];

        $totalConSinRegistro = $asistencias + $inasistencias + $sinRegistro;
        $textoCentro = (string) $totalConSinRegistro;
        $pctAsistencia = ($total > 0) ? round(($asistencias / $total) * 100, 1) : 0.0;
        $pctInasistencia = ($total > 0) ? round(($inasistencias / $total) * 100, 1) : 0.0;
        $pctSinRegistro = ($total > 0) ? round(($sinRegistro / $total) * 100, 1) : 0.0;

        $cx = (int) round($w / 2);
        $diam = 1240;
        $inner = 768;
        $cy = (int) round($h * 0.42);

        if (is_file($font) && is_file($fontLegend) && function_exists('imagettftext')) {
            /*
             * Texto nativo cumplimiento = 28 pt en slide; compensar por escalado del PNG completo.
             * Leyenda anclada abajo; la dona usa el mayor tamaño posible y se baja en la franja (más aire arriba).
             */
            $slideHInch = self::PPT_CIRCULAR_PIC_CY / 914400;
            $neededLinePx = (28.0 / 72.0) * (float) $h / max(0.001, $slideHInch);
            $probe = imagettfbbox(28, 0, $fontLegend, 'Mg');
            $probeH = $probe ? abs($probe[7] - $probe[1]) : 34.0;
            $legendPt = (int) max(32, min(220, (int) round(28.0 * ($neededLinePx / max(1.0, $probeH)))));
            $legendLineStep = (int) max(52, (int) round($legendPt * 1.48));
            $marginBottom = 52;
            $baseRow2 = $h - $marginBottom;
            $baseRow1 = $baseRow2 - $legendLineStep;
            $donutHalf = (int) ceil($diam / 2);
            $minGapChartToLegend = (int) max(118, (int) round($legendPt * 1.02));
            $topMargin = 68;
            $cyMin = $topMargin + (int) floor($diam / 2);
            $cyMax = $baseRow1 - $donutHalf - $minGapChartToLegend;
            if ($cyMax < $cyMin) {
                $diam = 1120;
                $inner = 693;
                $donutHalf = (int) ceil($diam / 2);
                $cyMin = $topMargin + (int) floor($diam / 2);
                $cyMax = $baseRow1 - $donutHalf - $minGapChartToLegend;
            }
            if ($cyMax < $cyMin) {
                $diam = 1020;
                $inner = 631;
                $donutHalf = (int) ceil($diam / 2);
                $cyMin = $topMargin + (int) floor($diam / 2);
                $cyMax = $baseRow1 - $donutHalf - $minGapChartToLegend;
            }
            if ($cyMax >= $cyMin) {
                /* Sesgo hacia abajo (cy alto): bloque gráfica+leyenda más bajo en el lienzo, mejor equilibrio con el pie del slide. */
                $cy = (int) round($cyMin + ($cyMax - $cyMin) * 0.68);
            } else {
                $cy = $cyMin;
            }

            $start = -90.0;
            foreach ($segmentos as $seg) {
                if ($seg['v'] <= 0) {
                    continue;
                }
                $delta = 360.0 * ((float) $seg['v'] / (float) $total);
                imagefilledarc(
                    $im,
                    $cx,
                    $cy,
                    $diam,
                    $diam,
                    (int) round($start),
                    (int) round($start + $delta),
                    $seg['c'],
                    IMG_ARC_PIE
                );
                $start += $delta;
            }

            imagefilledellipse($im, $cx, $cy, $inner, $inner, $blanco);

            $bbox = imagettfbbox(148, 0, $font, $textoCentro);
            $textW = $bbox ? abs($bbox[2] - $bbox[0]) : 0;
            $textH = $bbox ? abs($bbox[7] - $bbox[1]) : 0;
            $tx = (int) round($cx - ($textW / 2));
            $ty = (int) round($cy + ($textH / 2));
            @imagettftext($im, 148, 0, $tx, $ty, $texto, $font, $textoCentro);
            @imagettftext($im, 148, 0, $tx + 1, $ty, $texto, $font, $textoCentro);

            $totalText = 'TOTAL';
            $bboxTotal = imagettfbbox(58, 0, $font, $totalText);
            $totalW = $bboxTotal ? abs($bboxTotal[2] - $bboxTotal[0]) : 0;
            $txTotal = (int) round($cx - ($totalW / 2));
            $tyTotal = (int) round($cy + max(220, (int) round($diam * 0.215)));
            @imagettftext($im, 58, 0, $txTotal, $tyTotal, $texto, $font, $totalText);
            @imagettftext($im, 58, 0, $txTotal + 1, $tyTotal, $texto, $font, $totalText);

            $legendCenterX = (int) round($w / 2);

            $drawLegendItem = function (string $label, float $pct, int $color, int $centerX, int $baseY) use ($im, $fontLegend, $legendPt, $texto) {
                $labelTxt = $label.': ';
                $pctTxt = number_format($pct, 1).'%';

                $bboxL = imagettfbbox($legendPt, 0, $fontLegend, $labelTxt);
                $bboxP = imagettfbbox($legendPt, 0, $fontLegend, $pctTxt);
                $wL = $bboxL ? abs($bboxL[2] - $bboxL[0]) : 0;
                $wP = $bboxP ? abs($bboxP[2] - $bboxP[0]) : 0;
                $startX = (int) round($centerX - (($wL + $wP) / 2));

                @imagettftext($im, $legendPt, 0, $startX, $baseY, $texto, $fontLegend, $labelTxt);
                @imagettftext($im, $legendPt, 0, $startX + 1, $baseY, $texto, $fontLegend, $labelTxt);
                @imagettftext($im, $legendPt, 0, $startX + $wL, $baseY, $color, $fontLegend, $pctTxt);
                @imagettftext($im, $legendPt, 0, $startX + $wL + 1, $baseY, $color, $fontLegend, $pctTxt);
            };

            $drawLegendItem('Asistencias', $pctAsistencia, $verde, (int) round($w * 0.235), $baseRow1);
            $drawLegendItem('Inasistencias', $pctInasistencia, $guinda, (int) round($w * 0.765), $baseRow1);
            $drawLegendItem('Sin registro', $pctSinRegistro, $dorado, $legendCenterX, $baseRow2);
        } else {
            $start = -90.0;
            foreach ($segmentos as $seg) {
                if ($seg['v'] <= 0) {
                    continue;
                }
                $delta = 360.0 * ((float) $seg['v'] / (float) $total);
                imagefilledarc(
                    $im,
                    $cx,
                    $cy,
                    $diam,
                    $diam,
                    (int) round($start),
                    (int) round($start + $delta),
                    $seg['c'],
                    IMG_ARC_PIE
                );
                $start += $delta;
            }
            imagefilledellipse($im, $cx, $cy, $inner, $inner, $blanco);
            imagestring($im, 5, $cx - 90, $cy - 16, $textoCentro, $texto);
            imagestring($im, 5, (int) round($w * 0.08), $h - 90, 'Asistencias: '.number_format($pctAsistencia, 1).'%', $verde);
            imagestring($im, 5, (int) round($w * 0.38), $h - 90, 'Inasistencias: '.number_format($pctInasistencia, 1).'%', $guinda);
            imagestring($im, 5, (int) round($w * 0.62), $h - 90, 'Sin registro: '.number_format($pctSinRegistro, 1).'%', $dorado);
            imagestring($im, 5, $cx - 60, $cy + 80, 'TOTAL', $texto);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mppt_circ_');
        if ($tmp === false) {
            imagedestroy($im);

            return null;
        }
        $path = $tmp.'.png';
        rename($tmp, $path);
        imagepng($im, $path);
        imagedestroy($im);

        return $path;
    }

    /**
     * Lunes a viernes de la semana ISO inmediatamente anterior a la semana que contiene {@see $fechaReferencia}.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function semanaLaboralAnterior(Carbon $fechaReferencia): array
    {
        $lunesSemanaMarcada = $fechaReferencia->copy()->locale('es')->startOfWeek(Carbon::MONDAY);
        $lunesAnterior = $lunesSemanaMarcada->copy()->subDays(7);
        $viernesAnterior = $lunesAnterior->copy()->addDays(4);

        return [$lunesAnterior, $viernesAnterior];
    }

    private function formatoRangoSemanaEspanol(Carbon $inicio, Carbon $fin): string
    {
        $a = $inicio->copy()->locale('es');
        $b = $fin->copy()->locale('es');

        if ($a->month === $b->month && $a->year === $b->year) {
            return sprintf(
                'Semana del %d al %d de %s de %d',
                $a->day,
                $b->day,
                mb_strtolower($a->translatedFormat('F')),
                $a->year
            );
        }

        return sprintf(
            'Semana del %d de %s al %d de %s de %d',
            $a->day,
            mb_strtolower($a->translatedFormat('F')),
            $b->day,
            mb_strtolower($b->translatedFormat('F')),
            $b->year
        );
    }

    /**
     * @return array{
     *     total_mesas: int,
     *     mesas_con_asistencia: int,
     *     mesas_con_inasistencia: int,
     *     municipios_distintos_con_registro: int,
     *     por_dia: array<string, array{mesas: int, asistencias: int, inasistencias: int}>
     * }
     */
    private function estadisticasSemanaLaboral(Carbon $lunes, Carbon $viernes): array
    {
        $desde = $lunes->toDateString();
        $hasta = $viernes->toDateString();

        $filas = MesaPazAsistencia::query()
            ->whereDate('fecha_asist', '>=', $desde)
            ->whereDate('fecha_asist', '<=', $hasta)
            ->orderByDesc('created_at')
            ->get(['municipio_id', 'fecha_asist', 'asiste']);

        $porMunicipioDia = [];
        foreach ($filas as $fila) {
            if (! $fila->municipio_id) {
                continue;
            }
            $fecha = $fila->fecha_asist instanceof Carbon
                ? $fila->fecha_asist->format('Y-m-d')
                : Carbon::parse($fila->fecha_asist)->format('Y-m-d');
            $clave = $fila->municipio_id.'|'.$fecha;
            $porMunicipioDia[$clave] = $fila;
        }

        $totalMesas = count($porMunicipioDia);

        $mesasConInasistencia = collect($porMunicipioDia)
            ->filter(fn ($r) => MesaPazAsistencia::asistenciaEsNoPresente($r->asiste))
            ->count();

        $mesasConAsistencia = $totalMesas - $mesasConInasistencia;

        $municipiosDistintos = collect(array_keys($porMunicipioDia))
            ->map(fn ($k) => explode('|', (string) $k, 2)[0])
            ->filter()
            ->unique()
            ->count();

        $porDia = [];
        foreach ($porMunicipioDia as $clave => $r) {
            $parts = explode('|', $clave, 2);
            $fechaStr = $parts[1] ?? '';
            if ($fechaStr === '') {
                continue;
            }
            if (! isset($porDia[$fechaStr])) {
                $porDia[$fechaStr] = ['mesas' => 0, 'asistencias' => 0, 'inasistencias' => 0];
            }
            $porDia[$fechaStr]['mesas']++;
            if (MesaPazAsistencia::asistenciaEsNoPresente($r->asiste)) {
                $porDia[$fechaStr]['inasistencias']++;
            } else {
                $porDia[$fechaStr]['asistencias']++;
            }
        }
        ksort($porDia);

        return [
            'total_mesas' => $totalMesas,
            'mesas_con_asistencia' => $mesasConAsistencia,
            'mesas_con_inasistencia' => $mesasConInasistencia,
            'municipios_distintos_con_registro' => $municipiosDistintos,
            'por_dia' => $porDia,
        ];
    }

    private function parchearSlide3ReporteGeneral(
        string $xml,
        Carbon $lunesSemana,
        Carbon $viernesSemana,
        int $totalMesas,
        int $mesasConAsistencia,
        int $mesasConInasistencia,
        float $porcentajeCumplimiento,
        int $mesasSinRegistroSemanal,
    ): string {
        $domOk = false;
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;

        try {
            if (@$dom->loadXML($xml, LIBXML_NONET)) {
                $xp = new DOMXPath($dom);
                $xp->registerNamespace('a', self::DRAWINGML_NS);
                $xp->registerNamespace('p', self::PRESENTATIONML_NS);

                $this->reemplazarParrafosSemanaEnSlide($xp, $lunesSemana, $viernesSemana);
                $reemplazados = $this->reemplazarTresMetricasNumericas($xp, $totalMesas, $mesasConAsistencia, $mesasConInasistencia);
                $this->moverShapeSemanaHaciaArriba($xp, 380000);

                if ($reemplazados < 3) {
                    Log::warning('mesas_paz ppt slide3: solo se reemplazaron '.$reemplazados.' de 3 métricas numéricas; se aplicará respaldo en nodos sueltos.');
                }
                $domOk = true;
            }
        } catch (\Throwable $e) {
            Log::warning('mesas_paz ppt slide3: DOM falló, respaldo por texto. '.$e->getMessage());
        }

        if ($domOk) {
            $out = $dom->saveXML();
            if (is_string($out) && $out !== '') {
                $out = $this->reemplazarNumerosSueltosEnXml($out, $totalMesas, $mesasConAsistencia, $mesasConInasistencia);
                $out = $this->forzarTextoMesasSinRegistroEnXml($out, $mesasSinRegistroSemanal);

                return $this->forzarTextoCumplimientoEnXml($out, $porcentajeCumplimiento);
            }
        }

        $fallback = $this->parchearSlide3PorTextoPlano(
            $xml,
            $this->formatoSemanaTemplate($lunesSemana, $viernesSemana),
            $totalMesas,
            $mesasConAsistencia,
            $mesasConInasistencia,
            $porcentajeCumplimiento,
            $mesasSinRegistroSemanal,
        );

        return $this->forzarTextoCumplimientoEnXml($fallback, $porcentajeCumplimiento);
    }

    private function forzarTextoMesasSinRegistroEnXml(string $xml, int $mesasSinRegistroSemanal): string
    {
        $linea = $this->textoMesasSinRegistroSemanal($mesasSinRegistroSemanal);

        $xml = preg_replace(
            '/<a:t>\d+\s+municipios?\s+sin\s+registro<\/a:t>/iu',
            '<a:t>'.$this->xmlText($linea).'</a:t>',
            $xml
        ) ?? $xml;

        if (preg_match('/<a:t>[^<]*cumplimiento[^<]*<\/a:t>/iu', $xml)
            && ! preg_match('/mesas?\s+sin\s+registro\s+en\s+la\s+semana/iu', $xml)) {
            $xml = preg_replace(
                '/(<a:t>[^<]*cumplimiento[^<]*<\/a:t>)/iu',
                '$1<a:br/><a:r><a:t>'.$this->xmlText($linea).'</a:t></a:r>',
                $xml,
                1
            ) ?? $xml;
        }

        return $xml;
    }

    private function extraerNumeroMicroregionDesdeSlideXml(string $slideXml): ?int
    {
        if (preg_match('/\bMR\s*(\d+)\b/u', $slideXml, $m) !== 1) {
            return null;
        }
        $n = (int) $m[1];

        return $n > 0 ? $n : null;
    }

    private function resolverMicroregionPorNumero(int $mrNumero): ?Microrregione
    {
        if ($mrNumero <= 0) {
            return null;
        }

        try {
            $micro = Microrregione::query()->whereKey($mrNumero)->first();
            if ($micro) {
                return $micro;
            }

            $q = Microrregione::query();
            $micro = $q->where('microrregion', 'like', '%'.$mrNumero.'%')->first();
            if ($micro) {
                return $micro;
            }
        } catch (\Throwable $e) {
            Log::warning('mesas_paz ppt micro: no se pudo resolver microrregión '.$mrNumero.'. '.$e->getMessage());
        }

        return null;
    }

    private function parchearSlideMicroregion(
        string $xml,
        int $mrNumero,
        string $cabecera,
        Carbon $lunesSemana,
        Carbon $viernesSemana,
    ): string {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;

        try {
            if (! @$dom->loadXML($xml, LIBXML_NONET)) {
                return $xml;
            }

            $xp = new DOMXPath($dom);
            $xp->registerNamespace('a', self::DRAWINGML_NS);
            $xp->registerNamespace('p', self::PRESENTATIONML_NS);

            $tNodes = $xp->query('//a:t');
            if ($tNodes) {
                for ($i = 0; $i < $tNodes->length; $i++) {
                    $t = $tNodes->item($i);
                    if (! $t instanceof DOMElement) {
                        continue;
                    }
                    $txt = trim((string) $t->textContent);
                    if ($txt === '') {
                        continue;
                    }

                    if (preg_match('/^MR\s*\d+\b/ui', $txt) === 1) {
                        $t->textContent = 'MR'.$mrNumero.' '.mb_strtoupper($cabecera);
                        break;
                    }
                }
            }

            $this->eliminarCajaTextoSemanaInferiorSlideMicroregion($xp);

            $out = $dom->saveXML();
            if (is_string($out) && $out !== '') {
                return $out;
            }
        } catch (\Throwable $e) {
            Log::warning('mesas_paz ppt micro slide: no se pudo parchear slide MR'.$mrNumero.'. '.$e->getMessage());
        }

        return $xml;
    }

    /**
     * Quita el recuadro de texto grande con el rango de semana (plantilla) en slides de microrregión.
     */
    private function eliminarCajaTextoSemanaInferiorSlideMicroregion(DOMXPath $xp): void
    {
        $spList = $xp->query('//p:sp');
        if (! $spList) {
            return;
        }

        $eliminar = [];
        for ($i = 0; $i < $spList->length; $i++) {
            $sp = $spList->item($i);
            if (! $sp instanceof DOMElement) {
                continue;
            }

            $tNodes = $xp->query('.//a:t', $sp);
            if (! $tNodes || $tNodes->length === 0) {
                continue;
            }

            $merged = '';
            for ($j = 0; $j < $tNodes->length; $j++) {
                $tn = $tNodes->item($j);
                if ($tn instanceof DOMElement) {
                    $merged .= $tn->textContent;
                }
            }
            $norm = preg_replace('/\s+/u', ' ', trim($merged)) ?? trim($merged);
            if ($norm === '') {
                continue;
            }

            if (preg_match('/^MR\s*\d+/ui', $norm) === 1) {
                continue;
            }

            $pareceSemanaGrande = (bool) preg_match('/^Semana\b/ui', $norm)
                && (
                    (bool) preg_match('/\d+\s+al\s+\d+/u', $norm)
                    || (bool) preg_match('/\bde\s+\d{4}\b/u', $norm)
                );

            if ($pareceSemanaGrande) {
                $eliminar[] = $sp;
            }
        }

        foreach ($eliminar as $sp) {
            if ($sp->parentNode !== null) {
                $sp->parentNode->removeChild($sp);
            }
        }
    }

    private function esModalidadVirtual(?string $modalidad): bool
    {
        return mb_strtolower(trim((string) $modalidad)) === 'virtual';
    }

    private function esModalidadPresencial(?string $modalidad): bool
    {
        return mb_strtolower(trim((string) $modalidad)) === 'presencial';
    }

    private function esModalidadSuspension(?string $modalidad): bool
    {
        $m = mb_strtolower(trim((string) $modalidad));

        return str_contains($m, 'suspenci');
    }

    private function esModalidadSinReporte(?string $modalidad): bool
    {
        $m = mb_strtolower(trim((string) $modalidad));

        return str_contains($m, 'sin reporte') || str_contains($m, 'sin informaci');
    }

    /**
     * Etiqueta corta para la fila “Inasistencias por día” según modalidad de captura.
     */
    private function etiquetaModalidadIncidenciaDia(?string $modalidad): ?string
    {
        $m = mb_strtolower(trim((string) $modalidad));
        if ($m === '') {
            return null;
        }
        if (str_contains($m, 'suspenci')) {
            return 'Suspensión';
        }
        if (str_contains($m, 'enlace') || str_contains($m, 'informaci')) {
            return 'Sin información de enlace';
        }
        if (str_contains($m, 'sin reporte')) {
            return 'Sin reporte de delegado';
        }

        return null;
    }

    private function microregionPresidenteEsSinReporteDelegado(?string $presidente): bool
    {
        return mb_strtoupper(trim((string) $presidente)) === 'S/R';
    }

    /**
     * Misma idea que {@see MesasPazSupervisionService} resolverTiposAsistente: quién asiste según presidente + asiste.
     */
    private function microregionAsisteIndicaDirectorSeguridad(?string $asisteRaw): bool
    {
        $a = mb_strtolower(trim((string) $asisteRaw));
        if ($a === '') {
            return false;
        }

        return in_array($a, ['director de seguridad', 'director de seguridad municipal', 'representante'], true)
            || str_contains($a, 'director de seguridad');
    }

    private function microregionPresidentePresenteParaEstadistica(?string $presidente, ?string $asisteRaw): bool
    {
        if (MesaPazAsistencia::asistenciaEsNoPresente($asisteRaw)) {
            return false;
        }
        $pres = trim((string) $presidente);
        if ($this->microregionPresidenteEsSinReporteDelegado($presidente)) {
            return false;
        }
        if (in_array($pres, ['No', 'Ninguno'], true)) {
            return false;
        }
        if (in_array($pres, ['Si', 'Ambos', 'Presidente'], true)) {
            return true;
        }
        $asiste = mb_strtolower(trim((string) $asisteRaw));

        return $asiste === 'presidente' || str_contains($asiste, 'presidente y representante');
    }

    private function microregionDirectorPresenteParaEstadistica(?string $presidente, ?string $asisteRaw): bool
    {
        if (MesaPazAsistencia::asistenciaEsNoPresente($asisteRaw)) {
            return false;
        }
        $pres = trim((string) $presidente);
        if (in_array($pres, ['Representante', 'Ambos'], true)) {
            return true;
        }
        if ($this->microregionPresidenteEsSinReporteDelegado($presidente)) {
            return $this->microregionAsisteIndicaDirectorSeguridad($asisteRaw);
        }
        if (in_array($pres, ['No', 'Ninguno'], true)) {
            return $this->microregionAsisteIndicaDirectorSeguridad($asisteRaw);
        }

        return $this->microregionAsisteIndicaDirectorSeguridad($asisteRaw);
    }

    /**
     * Totales de mesa de seguridad: como máximo una por día laboral (lun–vie), hasta 5 por semana; presencial gana sobre virtual el mismo día.
     * Asistencias y ponderaciones por municipio no usan esta regla.
     *
     * @param  array<int, string>  $municipiosMap municipio_id => nombre
     * @param  array<string, array<int, array{municipio_id: int, fecha: string, asiste: mixed, presidente: mixed, modalidad: mixed}>>  $cachePorFecha
     * @return array{
     *   municipios_total: int,
     *   mesas_realizadas: int,
     *   virtual: int,
     *   presencial: int,
     *   sin_reporte: int,
     *   suspension: int,
     *   pct_presidente: float,
     *   pct_director: float,
     *   sin_captura_semanal: int,
     *   falta_ambos_ninguno: int,
     *   pct_sin_registro_sobre_esperado: float,
     *   pct_falta_ambos_sobre_esperado: float,
     *   top_inasistencias: list<array{municipio: string, inasistencias: int, pct_asistencia: float}>,
     *   incidencias_por_dia: array<string, array{faltantes: int, sin_reporte: int, suspension: int, detalle_texto: string}>
     * }
     */
    private function estadisticasSemanaMicroregionDesdeCache(
        Carbon $lunes,
        Carbon $viernes,
        int $microId,
        array $municipiosMap,
        array $cachePorFecha,
    ): array {
        $municipiosTotal = count($municipiosMap);
        $esperadoSemana = $municipiosTotal * 5;

        $mesasRealizadas = 0;
        $virtual = 0;
        $presencial = 0;
        $sinReporte = 0;
        $suspension = 0;

        $presidentePresente = 0;
        $directorPresente = 0;
        $sinCapturaSemanal = 0;
        $faltaAmbosNinguno = 0;

        $incidencias = [];

        $asistenciasPorMunicipio = [];
        foreach (array_keys($municipiosMap) as $munId) {
            $asistenciasPorMunicipio[(int) $munId] = 0;
        }

        for ($d = $lunes->copy(); $d <= $viernes; $d->addDay()) {
            $fecha = $d->toDateString();
            $registrosDia = $cachePorFecha[$fecha] ?? [];

            $registrosCount = count($registrosDia);
            $faltantes = max(0, $municipiosTotal - $registrosCount);
            $sinCapturaSemanal += $faltantes;

            $sinReporteDia = 0;
            $suspensionDia = 0;
            $diaAlgunaPresencial = false;
            $diaAlgunaVirtual = false;

            foreach ($registrosDia as $munId => $r) {
                $modalidad = (string) ($r['modalidad'] ?? '');
                if ($this->esModalidadSuspension($modalidad)) {
                    $suspension++;
                    $suspensionDia++;

                    continue;
                }
                if ($this->esModalidadSinReporte($modalidad)) {
                    $sinReporte++;
                    $sinReporteDia++;

                    continue;
                }

                if ($this->esModalidadVirtual($modalidad)) {
                    $diaAlgunaVirtual = true;
                } elseif ($this->esModalidadPresencial($modalidad)) {
                    $diaAlgunaPresencial = true;
                } else {
                    // Modalidad desconocida: no cuenta como mesa de seguridad del día.
                    continue;
                }

                $asiste = (string) ($r['asiste'] ?? '');
                if (MesaPazAsistencia::asistenciaEsPresente($asiste)) {
                    if (isset($asistenciasPorMunicipio[$munId])) {
                        $asistenciasPorMunicipio[$munId]++;
                    }
                }

                $presidente = (string) ($r['presidente'] ?? '');
                $srPres = $this->microregionPresidenteEsSinReporteDelegado($presidente);
                if ($srPres) {
                    $sinReporte++;
                    $sinReporteDia++;
                }
                $presOk = $this->microregionPresidentePresenteParaEstadistica($presidente, $asiste);
                $dirOk = $this->microregionDirectorPresenteParaEstadistica($presidente, $asiste);
                if ($presOk) {
                    $presidentePresente++;
                }
                if ($dirOk) {
                    $directorPresente++;
                }
                if (! $srPres && (MesaPazAsistencia::asistenciaEsNoPresente($asiste) || (! $presOk && ! $dirOk))) {
                    $faltaAmbosNinguno++;
                }
            }

            /* Mesa de seguridad: máximo 1 por día laboral; prioridad presencial si hubo ambas modalidades. */
            if ($diaAlgunaPresencial) {
                $presencial++;
                $mesasRealizadas++;
            } elseif ($diaAlgunaVirtual) {
                $virtual++;
                $mesasRealizadas++;
            }

            $incidencias[$fecha] = [
                'faltantes' => $faltantes,
                'sin_reporte' => $sinReporteDia,
                'suspension' => $suspensionDia,
                'detalle_texto' => $this->construirDetalleInasistenciasDiaMicroregion($municipiosMap, $registrosDia),
            ];
        }

        $pctPresidente = $esperadoSemana > 0 ? round(($presidentePresente / $esperadoSemana) * 100, 2) : 0.0;
        $pctDirector = $esperadoSemana > 0 ? round(($directorPresente / $esperadoSemana) * 100, 2) : 0.0;

        $sinRegistroMasCaptura = $sinReporte + $sinCapturaSemanal;
        $pctSinRegistroSobreEsperado = $esperadoSemana > 0
            ? round(($sinRegistroMasCaptura / $esperadoSemana) * 100, 2)
            : 0.0;
        $pctFaltaAmbosSobreEsperado = $esperadoSemana > 0
            ? round(($faltaAmbosNinguno / $esperadoSemana) * 100, 2)
            : 0.0;

        $top = [];
        foreach ($municipiosMap as $munId => $nombre) {
            $asist = (int) ($asistenciasPorMunicipio[(int) $munId] ?? 0);
            $inasist = max(0, 5 - $asist);
            $pct = round(($asist / 5) * 100, 1);
            $top[] = [
                'municipio' => (string) $nombre,
                'inasistencias' => $inasist,
                'pct_asistencia' => $pct,
            ];
        }
        usort($top, static function (array $a, array $b) {
            if ($a['inasistencias'] !== $b['inasistencias']) {
                return $b['inasistencias'] <=> $a['inasistencias'];
            }
            if ($a['pct_asistencia'] !== $b['pct_asistencia']) {
                return $a['pct_asistencia'] <=> $b['pct_asistencia'];
            }

            return strcmp($a['municipio'], $b['municipio']);
        });
        $top = array_values(array_slice($top, 0, 5));

        return [
            'municipios_total' => $municipiosTotal,
            'mesas_realizadas' => $mesasRealizadas,
            'virtual' => $virtual,
            'presencial' => $presencial,
            'sin_reporte' => $sinReporte,
            'suspension' => $suspension,
            'pct_presidente' => $pctPresidente,
            'pct_director' => $pctDirector,
            'sin_captura_semanal' => $sinCapturaSemanal,
            'falta_ambos_ninguno' => $faltaAmbosNinguno,
            'pct_sin_registro_sobre_esperado' => $pctSinRegistroSobreEsperado,
            'pct_falta_ambos_sobre_esperado' => $pctFaltaAmbosSobreEsperado,
            'top_inasistencias' => $top,
            'incidencias_por_dia' => $incidencias,
        ];
    }

    /**
     * @param  array<int, string>  $municipiosMap
     * @param  array<int, array{municipio_id: int, fecha: string, asiste: mixed, presidente: mixed, modalidad: mixed}>  $registrosDia
     */
    private function construirDetalleInasistenciasDiaMicroregion(array $municipiosMap, array $registrosDia): string
    {
        $noAsistieron = [];
        $susp = [];
        $srDel = [];
        $sie = [];

        foreach ($municipiosMap as $munId => $nombre) {
            $mid = (int) $munId;
            if (! isset($registrosDia[$mid])) {
                $noAsistieron[] = (string) $nombre;

                continue;
            }
            $r = $registrosDia[$mid];
            $mod = (string) ($r['modalidad'] ?? '');
            $et = $this->etiquetaModalidadIncidenciaDia($mod);
            if ($et === 'Suspensión') {
                $susp[] = (string) $nombre;

                continue;
            }
            if ($et === 'Sin reporte de delegado') {
                $srDel[] = (string) $nombre;

                continue;
            }
            if ($et === 'Sin información de enlace') {
                $sie[] = (string) $nombre;

                continue;
            }
            if ($this->esModalidadVirtual($mod) || $this->esModalidadPresencial($mod)) {
                $asiste = (string) ($r['asiste'] ?? '');
                if (MesaPazAsistencia::asistenciaEsNoPresente($asiste)) {
                    $noAsistieron[] = (string) $nombre;
                }
            } else {
                $noAsistieron[] = (string) $nombre;
            }
        }

        $partes = [];
        if ($noAsistieron !== []) {
            $partes[] = implode(', ', $noAsistieron);
        }
        if ($susp !== []) {
            $partes[] = 'Suspensión: '.implode(', ', $susp);
        }
        if ($srDel !== []) {
            $partes[] = 'Sin reporte de delegado: '.implode(', ', $srDel);
        }
        if ($sie !== []) {
            $partes[] = 'Sin información de enlace: '.implode(', ', $sie);
        }

        return $partes === [] ? 'Sin inasistencias' : implode(' | ', $partes);
    }

    /**
     * @param  array<string, array{municipios_total: int, mesas_realizadas: int, virtual: int, presencial: int, sin_reporte: int, suspension: int, pct_presidente: float, pct_director: float, sin_captura_semanal?: int, falta_ambos_ninguno?: int, pct_sin_registro_sobre_esperado?: float, pct_falta_ambos_sobre_esperado?: float, top_inasistencias: array, incidencias_por_dia: array}>|null  $statsPrev
     * @param  array<string, array{municipios_total: int, mesas_realizadas: int, virtual: int, presencial: int, sin_reporte: int, suspension: int, pct_presidente: float, pct_director: float, sin_captura_semanal?: int, falta_ambos_ninguno?: int, pct_sin_registro_sobre_esperado?: float, pct_falta_ambos_sobre_esperado?: float, top_inasistencias: array, incidencias_por_dia: array}>|null  $statsCur
     * @return array{0: string, 1: string, 2: string|null}
     */
    private function inyectarPanelMicroregionEnSlide(
        ZipArchive $zip,
        string $slideXml,
        string $relsContent,
        int $mrNumero,
        string $cabecera,
        Carbon $lunesPrev,
        Carbon $viernesPrev,
        Carbon $lunesCur,
        Carbon $viernesCur,
        ?array $statsPrev,
        ?array $statsCur,
    ): array {
        if (! extension_loaded('gd')) {
            return [$slideXml, $relsContent, null];
        }
        if (! is_array($statsPrev) || ! is_array($statsCur)) {
            return [$slideXml, $relsContent, null];
        }

        $pngPath = $this->crearArchivoPngPanelMicroregion(
            $mrNumero,
            $cabecera,
            $lunesPrev,
            $viernesPrev,
            $statsPrev,
            $lunesCur,
            $viernesCur,
            $statsCur,
        );

        if (! is_string($pngPath) || $pngPath === '' || ! is_file($pngPath)) {
            return [$slideXml, $relsContent, null];
        }

        $mediaName = $this->siguienteNombreImagenPngEnZip($zip);
        if (! $zip->addFile($pngPath, 'ppt/media/'.$mediaName)) {
            @unlink($pngPath);

            return [$slideXml, $relsContent, null];
        }

        $nextRid = $this->siguienteRIdEnRels($relsContent);
        $relsContent = $this->relsAgregarRelacionImagen($relsContent, $nextRid, $mediaName);

        /* Mismo ratio PNG/EMU; alto mayor = más contenido abajo sin mover el borde superior (picY fijo). */
        $pngW = 3600;
        $pngH = 2320;
        $picCx = 11400000;
        $picCy = (int) round($picCx * $pngH / $pngW);
        $picX = (int) max(0, (self::PPTX_SLIDE_CX_EMU - $picCx) / 2);
        $picY = self::PPT_MICROREGION_PANEL_Y_EMU;
        $slideXml = $this->slideInsertarPicture(
            $slideXml,
            'rId'.$nextRid,
            'Panel microrregion MR'.$mrNumero,
            $picX,
            $picY,
            $picCx,
            $picCy,
        );

        return [$slideXml, $relsContent, $pngPath];
    }

    private function anchoTextoTtf(string $texto, int $tamFuentePx, string $rutaFuente): int
    {
        $box = @imagettfbbox($tamFuentePx, 0, $rutaFuente, $texto);
        if ($box === false || ! isset($box[2], $box[0])) {
            return 0;
        }

        return abs((int) $box[2] - (int) $box[0]);
    }

    /**
     * Dibuja fragmentos en una o varias líneas; {@see $chunks} usa 'y'=>true para porcentaje en color amarillo.
     *
     * @param  list<array{s: string, y?: bool}>  $chunks
     * @return int Baseline Y de la última línea usada + salto (para seguir dibujando debajo).
     */
    private function imagenDibujarChunksTtfMultilinea(
        $im,
        int $x0,
        int $yBaseline,
        int $size,
        string $font,
        int $colorNegro,
        int $colorAmarillo,
        int $maxAncho,
        array $chunks,
    ): int {
        if (! is_file($font)) {
            return $yBaseline;
        }
        $x = $x0;
        $y = $yBaseline;
        $lineH = (int) max($size + 10, (int) round($size * 1.24));
        foreach ($chunks as $ch) {
            $s = (string) ($ch['s'] ?? '');
            if ($s === '') {
                continue;
            }
            $useYellow = (bool) ($ch['y'] ?? false);
            $w = $this->anchoTextoTtf($s, $size, $font);
            if ($x > $x0 && $x + $w > $x0 + $maxAncho) {
                $y += $lineH;
                $x = $x0;
            }
            $color = $useYellow ? $colorAmarillo : $colorNegro;
            @imagettftext($im, $size, 0, $x, $y, $color, $font, $s);
            $x += $w;
        }

        return $y + $lineH;
    }

    /**
     * Tablero KPI por día: día, barra proporcional a faltantes y % de inasistencia (faltantes / municipios × 100; 0% si no hay, 100% si faltan todos).
     *
     * @param  array<string, array{faltantes: int, sin_reporte?: int, suspension?: int}>  $incidenciasPorDia
     * @param  array{0?: int, 1?: int, 2?: int}  $accentRgb color corporativo de la columna (guinda / verde)
     * @return int Baseline Y bajo el gráfico
     */
    private function imagenDibujarGraficaKpiFaltantesSemana(
        $im,
        int $x0,
        int $yTop,
        int $boxW,
        Carbon $lunes,
        Carbon $viernes,
        array $incidenciasPorDia,
        int $municipiosTotal,
        string $font,
        int $colorTexto,
        array $accentRgb,
    ): int {
        if (! is_file($font) || $municipiosTotal < 1) {
            return $yTop;
        }

        $ar = (int) ($accentRgb[0] ?? 80);
        $ag = (int) ($accentRgb[1] ?? 80);
        $ab = (int) ($accentRgb[2] ?? 80);
        $track = imagecolorallocate($im, 236, 238, 241);
        $trackBorder = imagecolorallocate($im, 206, 210, 216);
        $headerMuted = imagecolorallocate($im, 110, 118, 128);
        $divider = imagecolorallocate($im, 225, 228, 232);
        $barFill = imagecolorallocate($im, max(0, $ar - 12), max(0, $ag - 12), max(0, $ab - 12));

        $rows = [];
        for ($d = $lunes->copy(); $d <= $viernes; $d->addDay()) {
            $fecha = $d->toDateString();
            $falt = (int) (($incidenciasPorDia[$fecha] ?? [])['faltantes'] ?? 0);
            $dObj = $d->copy()->locale('es');
            $lab = mb_strtolower($dObj->translatedFormat('D'));
            $dayLabel = match ($lab) {
                'lun' => 'Lun',
                'mar' => 'Mar',
                'mié', 'mie' => 'Mié',
                'jue' => 'Jue',
                'vie' => 'Vie',
                default => ucfirst($lab),
            };
            $rows[] = ['label' => $dayLabel.'.', 'falt' => $falt];
        }

        if ($rows === []) {
            return $yTop;
        }

        $szHead = 38;
        $szLab = 46;
        $szMet = 44;
        $rowH = 72;
        $barH = 34;
        $labelColW = 108;
        $gap = 18;
        $pctRightX = $x0 + $boxW - 4;
        $boxMaxPct = @imagettfbbox($szMet, 0, $font, '100.0%');
        $wPctReserve = ($boxMaxPct !== false && isset($boxMaxPct[0], $boxMaxPct[2]))
            ? abs((int) $boxMaxPct[2] - (int) $boxMaxPct[0])
            : 160;
        $pctZonePad = 14;
        $pctZoneLeft = (int) ($pctRightX - $wPctReserve - $pctZonePad);
        $barX = $x0 + $labelColW + $gap;
        $barW = (int) max(0, $pctZoneLeft - $barX);
        if ($barW < 40) {
            return $yTop;
        }

        @imagettftext($im, $szHead, 0, $x0, $yTop, $headerMuted, $font, 'Día');
        $pctHead = '%';
        $boxPctH = @imagettfbbox($szHead, 0, $font, $pctHead);
        $wPctH = ($boxPctH !== false && isset($boxPctH[0], $boxPctH[2]))
            ? abs((int) $boxPctH[2] - (int) $boxPctH[0])
            : 0;
        @imagettftext($im, $szHead, 0, (int) ($pctRightX - $wPctH), $yTop, $headerMuted, $font, $pctHead);

        $y = $yTop + 58;
        $scale = max(1, $municipiosTotal);

        $lastIdx = count($rows) - 1;
        foreach ($rows as $i => $row) {
            $falt = (int) $row['falt'];
            $lab = (string) $row['label'];
            $inasPct = round(($falt / $scale) * 100, 1);
            $frac = min(1.0, max(0.0, $falt / $scale));
            $fillPx = min($barW, (int) round($barW * $frac));

            $yBar = $y - 12;
            imagefilledrectangle($im, $barX, $yBar, $barX + $barW, $yBar + $barH, $track);
            imagerectangle($im, $barX, $yBar, $barX + $barW, $yBar + $barH, $trackBorder);

            if ($fillPx >= 2) {
                imagefilledrectangle($im, $barX, $yBar, $barX + $fillPx, $yBar + $barH, $barFill);
            }

            @imagettftext($im, $szLab, 0, $x0, $y, $colorTexto, $font, $lab);

            $pctStr = sprintf('%.1f%%', $inasPct);
            $boxPct = @imagettfbbox($szMet, 0, $font, $pctStr);
            $wPct = ($boxPct !== false && isset($boxPct[0], $boxPct[2]))
                ? abs((int) $boxPct[2] - (int) $boxPct[0])
                : 0;
            @imagettftext($im, $szMet, 0, (int) ($pctRightX - $wPct), $y, $colorTexto, $font, $pctStr);

            if ($i !== $lastIdx) {
                imageline($im, $x0, $yBar + $barH + 14, $x0 + $boxW, $yBar + $barH + 14, $divider);
            }

            $y += $rowH;
        }

        return $y + 28;
    }

    /**
     * Parte texto en líneas que caben en un ancho máximo (TTF).
     *
     * @return list<string>
     */
    private function partirLineasParaAnchoTtf(string $texto, int $tamFuentePx, string $rutaFuente, int $anchoMaxPx): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return [];
        }
        if (! is_file($rutaFuente)) {
            return [$texto];
        }

        $palabras = preg_split('/\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY);
        if ($palabras === false || $palabras === []) {
            return [$texto];
        }

        $lineas = [];
        $linea = '';
        foreach ($palabras as $p) {
            $prueba = $linea === '' ? $p : $linea.' '.$p;
            $box = @imagettfbbox($tamFuentePx, 0, $rutaFuente, $prueba);
            $ancho = ($box !== false && isset($box[2], $box[0])) ? abs((int) $box[2] - (int) $box[0]) : 0;
            if ($ancho > $anchoMaxPx && $linea !== '') {
                $lineas[] = $linea;
                $linea = $p;
            } else {
                $linea = $prueba;
            }
        }
        if ($linea !== '') {
            $lineas[] = $linea;
        }

        return $lineas;
    }

    /**
     * @param  array{municipios_total: int, mesas_realizadas: int, virtual: int, presencial: int, sin_reporte: int, suspension: int, pct_presidente: float, pct_director: float, sin_captura_semanal?: int, falta_ambos_ninguno?: int, pct_sin_registro_sobre_esperado?: float, pct_falta_ambos_sobre_esperado?: float, top_inasistencias: list<array{municipio: string, inasistencias: int, pct_asistencia: float}>, incidencias_por_dia: array<string, array{faltantes: int, sin_reporte: int, suspension: int, detalle_texto?: string}>}  $prev
     * @param  array{municipios_total: int, mesas_realizadas: int, virtual: int, presencial: int, sin_reporte: int, suspension: int, pct_presidente: float, pct_director: float, sin_captura_semanal?: int, falta_ambos_ninguno?: int, pct_sin_registro_sobre_esperado?: float, pct_falta_ambos_sobre_esperado?: float, top_inasistencias: list<array{municipio: string, inasistencias: int, pct_asistencia: float}>, incidencias_por_dia: array<string, array{faltantes: int, sin_reporte: int, suspension: int, detalle_texto?: string}>}  $cur
     */
    private function crearArchivoPngPanelMicroregion(
        int $mrNumero,
        string $cabecera,
        Carbon $lunesPrev,
        Carbon $viernesPrev,
        array $prev,
        Carbon $lunesCur,
        Carbon $viernesCur,
        array $cur,
    ): ?string {
        $w = 3600;
        $h = 2320;
        $im = imagecreatetruecolor($w, $h);
        if ($im === false) {
            return null;
        }

        imagealphablending($im, false);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefilledrectangle($im, 0, 0, $w, $h, $transparent);
        imagealphablending($im, true);

        $txt = imagecolorallocate($im, 58, 58, 58);
        $grid = imagecolorallocatealpha($im, 58, 58, 58, 90);
        $guinda = imagecolorallocate($im, 134, 30, 61);
        $verde = imagecolorallocate($im, 11, 78, 73);
        $dorado = imagecolorallocate($im, 184, 155, 106);
        $negroPond = imagecolorallocate($im, 12, 12, 12);
        $amarilloPct = imagecolorallocate($im, 220, 170, 25);

        $fontReg = public_path('fonts/agenda-pdf/Gilroy-Regular.ttf');
        $fontBold = public_path('fonts/agenda-pdf/Gilroy-Bold.ttf');
        $fontXB = public_path('fonts/agenda-pdf/Gilroy-ExtraBold.ttf');
        if (! is_file($fontReg)) {
            $fontReg = public_path('fonts/agenda-pdf/Montserrat-Regular.ttf');
        }
        if (! is_file($fontBold)) {
            $fontBold = public_path('fonts/agenda-pdf/Montserrat-Bold.ttf');
        }
        if (! is_file($fontXB)) {
            $fontXB = $fontBold;
        }

        $padL = 44;
        $padR = 44;
        $gutter = 308;
        $colW = (int) floor(($w - $padL - $padR - $gutter) / 2);
        $xL = $padL;
        $xR = $padL + $colW + $gutter;
        $gridX = $xL + $colW + (int) ($gutter / 2);
        $y = 168;

        imageline($im, $gridX, 88, $gridX, $h - 88, $grid);

        $fmtRango = static function (Carbon $a, Carbon $b): string {
            $aa = $a->copy()->locale('es');
            $bb = $b->copy()->locale('es');
            if ($aa->month === $bb->month && $aa->year === $bb->year) {
                return sprintf('%d al %d de %s de %d', $aa->day, $bb->day, mb_strtolower($aa->translatedFormat('F')), $aa->year);
            }

            return sprintf('%d de %s al %d de %s de %d', $aa->day, mb_strtolower($aa->translatedFormat('F')), $bb->day, mb_strtolower($bb->translatedFormat('F')), $bb->year);
        };

        $titlePrev = 'Semana anterior ('.$fmtRango($lunesPrev, $viernesPrev).')';
        $titleCur = 'Semana actual ('.$fmtRango($lunesCur, $viernesCur).')';

        $szTitle = 68;
        @imagettftext($im, $szTitle, 0, $xL, $y, $guinda, $fontXB, $titlePrev);
        @imagettftext($im, $szTitle, 0, $xR, $y, $verde, $fontXB, $titleCur);
        $y += 132;

        $renderCol = function (int $x, int $y0, array $st, int $accentColor, array $accentRgb, Carbon $lunSem, Carbon $vieSem) use ($im, $txt, $negroPond, $amarilloPct, $fontReg, $fontBold, $fontXB, $colW) {
            $y = $y0;
            $szBody = 54;
            $szSmall = 48;
            $szPond = 50;
            $szNum = 96;
            $szTop = 48;
            $textoMax = max(300, $colW - 52);

            @imagettftext($im, $szBody, 0, $x, $y, $txt, $fontBold, 'Total mesas realizadas:');
            @imagettftext($im, $szNum, 0, $x + 920, $y + 22, $accentColor, $fontXB, (string) (int) $st['mesas_realizadas']);
            $y += 128;

            @imagettftext($im, $szBody, 0, $x, $y, $txt, $fontBold, 'Modalidad:');
            $y += 72;
            $modP = sprintf('Presencial %d', (int) $st['presencial']);
            $modV = sprintf('Virtual %d', (int) $st['virtual']);
            @imagettftext($im, $szBody, 0, $x, $y, $txt, $fontReg, $modP);
            $y += 78;
            @imagettftext($im, $szBody, 0, $x, $y, $txt, $fontReg, $modV);
            $y += 88;

            $sinRegTotal = (int) $st['sin_reporte'] + (int) ($st['sin_captura_semanal'] ?? 0);
            $pctSinReg = (float) ($st['pct_sin_registro_sobre_esperado'] ?? 0.0);
            $faltaAmbos = (int) ($st['falta_ambos_ninguno'] ?? 0);
            $pctFaltaAmbos = (float) ($st['pct_falta_ambos_sobre_esperado'] ?? 0.0);
            $susp = (int) $st['suspension'];
            $chunksPond = [];
            if ($sinRegTotal > 0 && $pctSinReg > 0) {
                array_push(
                    $chunksPond,
                    ['s' => 'Sin registro o sin captura: ', 'y' => false],
                    ['s' => (string) $sinRegTotal, 'y' => false],
                    ['s' => ' (', 'y' => false],
                    ['s' => sprintf('%.2f%%', $pctSinReg), 'y' => true],
                    ['s' => ')', 'y' => false],
                );
            }
            if ($susp > 0) {
                if ($chunksPond !== []) {
                    $chunksPond[] = ['s' => '    ', 'y' => false];
                }
                array_push(
                    $chunksPond,
                    ['s' => 'Suspensión: ', 'y' => false],
                    ['s' => (string) $susp, 'y' => false],
                );
            }
            if ($faltaAmbos > 0 && $pctFaltaAmbos > 0) {
                if ($chunksPond !== []) {
                    $chunksPond[] = ['s' => '    ', 'y' => false];
                }
                array_push(
                    $chunksPond,
                    ['s' => 'Falta ambos (Ninguno): ', 'y' => false],
                    ['s' => (string) $faltaAmbos, 'y' => false],
                    ['s' => ' (', 'y' => false],
                    ['s' => sprintf('%.2f%%', $pctFaltaAmbos), 'y' => true],
                    ['s' => ')', 'y' => false],
                );
            }
            if ($chunksPond !== []) {
                $y = $this->imagenDibujarChunksTtfMultilinea(
                    $im,
                    $x,
                    $y,
                    $szPond,
                    $fontBold,
                    $negroPond,
                    $amarilloPct,
                    $textoMax,
                    $chunksPond,
                );
            }
            $y += 28;

            $p1 = sprintf('Asistencia Presidente Municipal: %.2f%%', (float) $st['pct_presidente']);
            $p2 = sprintf('Asistencia Director de Seguridad: %.2f%%', (float) $st['pct_director']);
            @imagettftext($im, $szSmall, 0, $x, $y, $txt, $fontReg, $p1);
            $y += 66;
            @imagettftext($im, $szSmall, 0, $x, $y, $txt, $fontReg, $p2);
            $y += 82;

            @imagettftext($im, $szBody, 0, $x, $y, $txt, $fontBold, 'Municipios con menor presencia:');
            $y += 72;

            $top = $st['top_inasistencias'] ?? [];
            $idx = 1;
            foreach ($top as $row) {
                if ($idx > 5) {
                    break;
                }
                $line = sprintf(
                    '%d) %s. Inasistencia %d. Asistencia %.1f%%.',
                    $idx,
                    (string) $row['municipio'],
                    (int) $row['inasistencias'],
                    (float) $row['pct_asistencia'],
                );
                $lineasTop = $this->partirLineasParaAnchoTtf($line, $szTop, $fontReg, $textoMax);
                foreach ($lineasTop as $ln) {
                    @imagettftext($im, $szTop, 0, $x, $y, $txt, $fontReg, $ln);
                    $y += 60;
                }
                $y += 8;
                $idx++;
            }

            $y += 32;
            @imagettftext($im, $szBody, 0, $x, $y, $txt, $fontBold, 'Inasistencias por día:');
            $y += 68;

            $y = $this->imagenDibujarGraficaKpiFaltantesSemana(
                $im,
                $x,
                $y,
                $textoMax,
                $lunSem,
                $vieSem,
                $st['incidencias_por_dia'] ?? [],
                (int) $st['municipios_total'],
                $fontReg,
                $txt,
                $accentRgb,
            );
        };

        $renderCol($xL, $y, $prev, $guinda, [134, 30, 61], $lunesPrev, $viernesPrev);
        $renderCol($xR, $y, $cur, $verde, [11, 78, 73], $lunesCur, $viernesCur);

        $tmp = tempnam(sys_get_temp_dir(), 'mppt_mr_');
        if ($tmp === false) {
            imagedestroy($im);

            return null;
        }
        $path = $tmp.'.png';
        rename($tmp, $path);
        imagepng($im, $path);
        imagedestroy($im);

        return $path;
    }

    private function moverShapeSemanaHaciaArriba(DOMXPath $xp, int $deltaY): void
    {
        $spList = $xp->query('//p:sp');
        if (! $spList) {
            return;
        }

        for ($i = 0; $i < $spList->length; $i++) {
            $sp = $spList->item($i);
            if (! $sp instanceof DOMElement) {
                continue;
            }

            $tNodes = $xp->query('.//a:t', $sp);
            if (! $tNodes || $tNodes->length === 0) {
                continue;
            }

            $texto = '';
            for ($j = 0; $j < $tNodes->length; $j++) {
                $tn = $tNodes->item($j);
                if ($tn instanceof DOMElement) {
                    $texto .= $tn->textContent;
                }
            }

            if (! preg_match('/\bSemana\b/ui', $texto)) {
                continue;
            }

            $off = $xp->query('.//a:xfrm/a:off', $sp);
            if (! $off || $off->length === 0) {
                continue;
            }
            $offNode = $off->item(0);
            if (! $offNode instanceof DOMElement || ! $offNode->hasAttribute('y')) {
                continue;
            }

            $yActual = (int) $offNode->getAttribute('y');
            $offNode->setAttribute('y', (string) max(0, $yActual - $deltaY));

            return;
        }
    }

    private function forzarTextoCumplimientoEnXml(string $xml, float $porcentajeCumplimiento): string
    {
        $linea = sprintf('%.2f%% de cumplimiento', $porcentajeCumplimiento);
        // Limpia residuos como "47.60% de" que puedan quedar en runs separados.
        $xml = preg_replace('/<a:t>\s*\d+[.,]\d+\s*%?\s*de\s*<\/a:t>/iu', '<a:t></a:t>', $xml) ?? $xml;

        $hex = $this->colorHexCumplimientoPorNivel($porcentajeCumplimiento);
        $rPrXml = '<a:rPr b="1" lang="es-MX" sz="2800">'
            .'<a:solidFill><a:srgbClr val="'.$hex.'"/></a:solidFill>'
            .'<a:latin typeface="Gilroy"/><a:ea typeface="Gilroy"/><a:cs typeface="Gilroy"/><a:sym typeface="Gilroy"/>'
            .'</a:rPr>';
        $nuevoRun = '<a:r>'.$rPrXml.'<a:t>'.$this->xmlText($linea).'</a:t></a:r>';

        $patronRun = '/<a:r\b[^>]*>(?:(?!<\/a:r>)[\s\S])*?<a:t>[^<]*\d+[.,]\d+\s*%?\s*de\s+cumplimiento[^<]*<\/a:t>\s*<\/a:r>/iu';
        $xmlRun = preg_replace($patronRun, $nuevoRun, $xml, 1);
        if ($xmlRun !== null && $xmlRun !== $xml) {
            return $xmlRun;
        }

        $patron = '/<a:t>[^<]*\d+[.,]\d+\s*%?\s*de\s+cumplimiento[^<]*<\/a:t>/iu';
        if (preg_match($patron, $xml) === 1) {
            return preg_replace($patron, '<a:t>'.$this->xmlText($linea).'</a:t>', $xml, 1) ?? $xml;
        }

        $patron2 = '/<a:t>[^<]*cumplimiento[^<]*<\/a:t>/iu';

        return preg_replace($patron2, '<a:t>'.$this->xmlText($linea).'</a:t>', $xml, 1) ?? $xml;
    }

    private function parchearSlide5ReporteDiarioSemanal(
        string $xml,
        Carbon $lunesSemanaPasada,
        Carbon $viernesSemanaPasada,
        Carbon $lunesSemanaActual,
        Carbon $viernesSemanaActual,
    ): string {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;

        try {
            if (! @$dom->loadXML($xml, LIBXML_NONET)) {
                return $xml;
            }

            $xp = new DOMXPath($dom);
            $xp->registerNamespace('a', self::DRAWINGML_NS);
            $xp->registerNamespace('p', self::PRESENTATIONML_NS);

            $spList = $xp->query('//p:sp');
            if (! $spList) {
                return $xml;
            }

            for ($i = 0; $i < $spList->length; $i++) {
                $sp = $spList->item($i);
                if (! $sp instanceof DOMElement) {
                    continue;
                }

                $tNodes = $xp->query('.//a:t', $sp);
                if (! $tNodes || $tNodes->length === 0) {
                    continue;
                }

                $texto = '';
                for ($j = 0; $j < $tNodes->length; $j++) {
                    $tn = $tNodes->item($j);
                    if ($tn instanceof DOMElement) {
                        $texto .= $tn->textContent;
                    }
                }

                if (preg_match('/\b1\s+al\s+6\s+de\b/ui', $texto) === 1) {
                    $this->shapeReemplazarRangoSinSemana($xp, $sp, $lunesSemanaPasada, $viernesSemanaPasada);
                } elseif (preg_match('/\b7\s+al\s+13\s+de\b/ui', $texto) === 1) {
                    $this->shapeReemplazarRangoSinSemana($xp, $sp, $lunesSemanaActual, $viernesSemanaActual);
                }
            }

            $out = $dom->saveXML();
            if (is_string($out) && $out !== '') {
                return $out;
            }
        } catch (\Throwable $e) {
            Log::warning('mesas_paz ppt slide5: no se pudo reemplazar rangos. '.$e->getMessage());
        }

        return $xml;
    }

    private function shapeReemplazarRangoSinSemana(DOMXPath $xp, DOMElement $sp, Carbon $inicio, Carbon $fin): void
    {
        $tNodes = $xp->query('.//a:t', $sp);
        if (! $tNodes || $tNodes->length === 0) {
            return;
        }

        [$parte1, $mes, $parte3] = $this->descomponerRangoSinSemanaEspanol($inicio, $fin);

        $primero = $tNodes->item(0);
        if ($primero instanceof DOMElement) {
            $primero->nodeValue = $parte1;
        }

        if ($tNodes->length >= 2) {
            $segundo = $tNodes->item(1);
            if ($segundo instanceof DOMElement) {
                $segundo->nodeValue = $mes;
            }
        }

        if ($tNodes->length >= 3) {
            $tercero = $tNodes->item(2);
            if ($tercero instanceof DOMElement) {
                $tercero->nodeValue = $parte3;
            }
        }

        for ($k = 3; $k < $tNodes->length; $k++) {
            $extra = $tNodes->item($k);
            if ($extra instanceof DOMElement) {
                $extra->nodeValue = '';
            }
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string} parte1, mes, parte3 (para 3 runs: "1 al 6 de " + "febrero" + " de 2026")
     */
    private function descomponerRangoSinSemanaEspanol(Carbon $inicio, Carbon $fin): array
    {
        $a = $inicio->copy()->locale('es');
        $b = $fin->copy()->locale('es');

        if ($a->month === $b->month && $a->year === $b->year) {
            return [
                sprintf('%d al %d de ', $a->day, $b->day),
                mb_strtolower($a->translatedFormat('F')),
                sprintf(' de %d', $a->year),
            ];
        }

        $full = sprintf(
            '%d de %s al %d de %s de %d',
            $a->day,
            mb_strtolower($a->translatedFormat('F')),
            $b->day,
            mb_strtolower($b->translatedFormat('F')),
            $b->year
        );

        return [$full, '', ''];
    }

    /**
     * @param  array<string, array{mesas: int, asistencias: int, inasistencias: int}>  $porDia
     * @return array{0: list<string>, 1: list<int>}
     */
    private function serieMesasPorDiaSemanaLaboral(Carbon $lunes, array $porDia): array
    {
        $labels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie'];
        $values = [];
        for ($i = 0; $i < 5; $i++) {
            $fecha = $lunes->copy()->addDays($i)->toDateString();
            $values[] = (int) (($porDia[$fecha]['mesas'] ?? 0) ?: 0);
        }

        return [$labels, $values];
    }

    /**
     * @param  list<string>  $labels
     * @param  list<int>  $values
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function crearArchivoPngGraficaBarrasPorDia(
        array $labels,
        array $values,
        int $maxVal,
        array $rgb,
    ): ?string {
        $n = count($labels);
        if ($n === 0 || $n !== count($values)) {
            return null;
        }

        $w = 900;
        $h = 700;
        $im = imagecreatetruecolor($w, $h);
        if ($im === false) {
            return null;
        }

        imagealphablending($im, false);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefilledrectangle($im, 0, 0, $w, $h, $transparent);
        imagealphablending($im, true);

        $barColor = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
        $axis = imagecolorallocatealpha($im, 58, 58, 58, 40);
        $grid = imagecolorallocatealpha($im, 58, 58, 58, 85);
        $text = imagecolorallocate($im, 58, 58, 58);

        $font = public_path('fonts/agenda-pdf/Gilroy-Bold.ttf');
        if (! is_file($font)) {
            $font = public_path('fonts/agenda-pdf/Gilroy-Medium.ttf');
        }
        if (! is_file($font)) {
            $font = public_path('fonts/agenda-pdf/Montserrat-Bold.ttf');
        }
        if (! is_file($font)) {
            $font = null;
        }

        $padL = 78;
        $padR = 34;
        $padT = 24;
        $padB = 120;
        $plotW = $w - $padL - $padR;
        $plotH = $h - $padT - $padB;
        $baseY = $padT + $plotH;

        $maxVal = max(1, $maxVal);
        $maxValAdj = (int) max(1, (int) ceil($maxVal * 1.15));

        imagesetthickness($im, 2);
        imageline($im, $padL, $baseY, $padL + $plotW, $baseY, $axis);
        imageline($im, $padL, $padT, $padL, $baseY, $axis);

        $gridLines = 3;
        for ($g = 1; $g <= $gridLines; $g++) {
            $gy = (int) round($baseY - ($plotH * ($g / ($gridLines + 1))));
            imagesetthickness($im, 1);
            imageline($im, $padL, $gy, $padL + $plotW, $gy, $grid);
        }
        imagesetthickness($im, 2);

        $slotW = (float) $plotW / (float) $n;
        $barW = (int) max(22, floor($slotW * 0.56));
        $valueFontSize = 28;
        $labelFontSize = 28;
        $labelY = $baseY + 70;

        for ($i = 0; $i < $n; $i++) {
            $val = (int) $values[$i];
            $frac = min(1.0, $val / max(1, $maxValAdj));
            $barH = (int) round($plotH * $frac);
            $x0 = (int) round($padL + ($slotW * $i) + (($slotW - $barW) / 2));
            $x1 = $x0 + $barW;
            $y0 = $baseY - $barH;
            $y1 = $baseY - 1;
            imagefilledrectangle($im, $x0, $y0, $x1, $y1, $barColor);

            $valStr = (string) $val;
            if ($font && function_exists('imagettftext')) {
                $bbox = imagettfbbox($valueFontSize, 0, $font, $valStr);
                if ($bbox !== false) {
                    $tw = (int) abs($bbox[2] - $bbox[0]);
                    $tx = (int) round($x0 + (($barW - $tw) / 2));
                    $ty = max($padT + 34, $y0 - 12);
                    @imagettftext($im, $valueFontSize, 0, $tx, $ty, $text, $font, $valStr);
                }
            } else {
                imagestring($im, 4, $x0 + (int) round($barW / 3), max(2, $y0 - 16), $valStr, $text);
            }

            $lab = (string) $labels[$i];
            if ($font && function_exists('imagettftext')) {
                $bboxL = imagettfbbox($labelFontSize, 0, $font, $lab);
                if ($bboxL !== false) {
                    $twL = (int) abs($bboxL[2] - $bboxL[0]);
                    $txL = (int) round($x0 + (($barW - $twL) / 2));
                    @imagettftext($im, $labelFontSize, 0, $txL, $labelY, $text, $font, $lab);
                }
            } else {
                imagestring($im, 4, $x0 + 2, $baseY + 14, $lab, $text);
            }
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mppt_day_');
        if ($tmp === false) {
            imagedestroy($im);

            return null;
        }
        $path = $tmp.'.png';
        rename($tmp, $path);
        imagepng($im, $path);
        imagedestroy($im);

        return $path;
    }

    /**
     * @param  array<string, array{mesas: int, asistencias: int, inasistencias: int}>  $porDiaSemanaPasada
     * @param  array<string, array{mesas: int, asistencias: int, inasistencias: int}>  $porDiaSemanaActual
     * @return array{0: string, 1: string, 2: list<string>} xml, rels, pngTmpPaths
     */
    private function inyectarGraficasDiariasEnSlide5(
        ZipArchive $zip,
        string $slideXml,
        string $relsContent,
        Carbon $lunesSemanaPasada,
        array $porDiaSemanaPasada,
        Carbon $lunesSemanaActual,
        array $porDiaSemanaActual,
    ): array {
        if (! extension_loaded('gd')) {
            Log::warning('mesas_paz ppt: extensión GD no disponible; no se insertan gráficas diarias (slide5).');

            return [$slideXml, $relsContent, []];
        }

        [$labelsA, $valuesA] = $this->serieMesasPorDiaSemanaLaboral($lunesSemanaPasada, $porDiaSemanaPasada);
        [$labelsB, $valuesB] = $this->serieMesasPorDiaSemanaLaboral($lunesSemanaActual, $porDiaSemanaActual);

        $maxVal = 1;
        foreach ($valuesA as $v) {
            $maxVal = max($maxVal, (int) $v);
        }
        foreach ($valuesB as $v) {
            $maxVal = max($maxVal, (int) $v);
        }

        $pngs = [];

        $pngA = $this->crearArchivoPngGraficaBarrasPorDia($labelsA, $valuesA, $maxVal, [134, 30, 61]); // guinda
        if (is_string($pngA) && $pngA !== '' && is_file($pngA)) {
            $pngs[] = $pngA;
            $mediaNameA = $this->siguienteNombreImagenPngEnZip($zip);
            if ($zip->addFile($pngA, 'ppt/media/'.$mediaNameA)) {
                $ridA = $this->siguienteRIdEnRels($relsContent);
                $relsContent = $this->relsAgregarRelacionImagen($relsContent, $ridA, $mediaNameA);
                $slideXml = $this->slideInsertarPicture(
                    $slideXml,
                    'rId'.$ridA,
                    'Grafica diaria semana pasada',
                    1240000,
                    3320000,
                    5600000,
                    4350000,
                );
            }
        }

        $pngB = $this->crearArchivoPngGraficaBarrasPorDia($labelsB, $valuesB, $maxVal, [11, 78, 73]); // verde
        if (is_string($pngB) && $pngB !== '' && is_file($pngB)) {
            $pngs[] = $pngB;
            $mediaNameB = $this->siguienteNombreImagenPngEnZip($zip);
            if ($zip->addFile($pngB, 'ppt/media/'.$mediaNameB)) {
                $ridB = $this->siguienteRIdEnRels($relsContent);
                $relsContent = $this->relsAgregarRelacionImagen($relsContent, $ridB, $mediaNameB);
                $slideXml = $this->slideInsertarPicture(
                    $slideXml,
                    'rId'.$ridB,
                    'Grafica diaria semana actual',
                    7140000,
                    3320000,
                    5600000,
                    4350000,
                );
            }
        }

        return [$slideXml, $relsContent, $pngs];
    }

    private function textoMesasSinRegistroSemanal(int $n): string
    {
        return $n === 1
            ? '1 mesa sin registro en la semana'
            : $n.' mesas sin registro en la semana';
    }

    /** Por debajo de 45: guinda; de 45 a 71.99: dorado; desde 72: verde (misma paleta que la gráfica). */
    private const CUMPLIMIENTO_COLOR_MEDIO_DESDE = 45.0;

    private const CUMPLIMIENTO_COLOR_ALTO_DESDE = 72.0;

    /** RGB hex sin #, mismos tonos que la gráfica circular PNG. */
    private function colorHexCumplimientoPorNivel(float $pct): string
    {
        if ($pct < self::CUMPLIMIENTO_COLOR_MEDIO_DESDE) {
            return '861E3D';
        }
        if ($pct < self::CUMPLIMIENTO_COLOR_ALTO_DESDE) {
            return 'B89B6A';
        }

        return '0B4E49';
    }

    private function aplicarRPrNegritaYColorCumplimiento(DOMDocument $dom, DOMElement $rPr, float $pct): void
    {
        $rPr->setAttribute('b', '1');
        $removeTags = ['solidFill', 'gradFill', 'noFill', 'blipFill', 'pattFill', 'grpFill'];
        for ($c = $rPr->firstChild; $c !== null; ) {
            $next = $c->nextSibling;
            if ($c instanceof DOMElement && $c->namespaceURI === self::DRAWINGML_NS) {
                $ln = $c->localName;
                if (in_array($ln, $removeTags, true)) {
                    $rPr->removeChild($c);
                }
            }
            $c = $next;
        }

        $solid = $dom->createElementNS(self::DRAWINGML_NS, 'a:solidFill');
        $srgb = $dom->createElementNS(self::DRAWINGML_NS, 'a:srgbClr');
        $srgb->setAttribute('val', $this->colorHexCumplimientoPorNivel($pct));
        $solid->appendChild($srgb);

        if ($rPr->firstChild !== null) {
            $rPr->insertBefore($solid, $rPr->firstChild);
        } else {
            $rPr->appendChild($solid);
        }
    }

    private function primerRPrEnParrafo(DOMXPath $xp, DOMElement $p): ?DOMElement
    {
        $runs = $xp->query('./a:r', $p);
        if (! $runs) {
            return null;
        }
        for ($i = 0; $i < $runs->length; $i++) {
            $r = $runs->item($i);
            if (! $r instanceof DOMElement) {
                continue;
            }
            $rprList = $xp->query('./a:rPr', $r);
            if ($rprList && $rprList->length > 0) {
                $node = $rprList->item(0);

                return $node instanceof DOMElement ? $node : null;
            }
        }

        return null;
    }

    private function obtenerRPrPlantillaTextoCuerpo(DOMXPath $xp, DOMElement $parrafoCumplimiento): ?DOMElement
    {
        $fromPie = $this->primerRPrEnParrafo($xp, $parrafoCumplimiento);
        if ($fromPie instanceof DOMElement) {
            return $fromPie;
        }

        $pList = $xp->query('//a:p');
        if (! $pList) {
            return null;
        }
        for ($i = 0; $i < $pList->length; $i++) {
            $p = $pList->item($i);
            if (! $p instanceof DOMElement) {
                continue;
            }
            $merged = '';
            $tNodes = $xp->query('.//a:t', $p);
            if (! $tNodes) {
                continue;
            }
            for ($j = 0; $j < $tNodes->length; $j++) {
                $tn = $tNodes->item($j);
                if ($tn instanceof DOMElement) {
                    $merged .= $tn->textContent;
                }
            }
            if (! preg_match('/^\s*Semana\b/ui', $merged)) {
                continue;
            }
            $tpl = $this->primerRPrEnParrafo($xp, $p);
            if ($tpl instanceof DOMElement) {
                return $tpl;
            }
        }

        return null;
    }

    private function parchearParrafoCumplimientoYSinRegistro(DOMDocument $dom, DOMXPath $xp, float $pct, int $mesasSinRegistroSemanal): void
    {
        unset($mesasSinRegistroSemanal);
        $pList = $xp->query('//a:p');
        if (! $pList) {
            return;
        }

        for ($i = 0; $i < $pList->length; $i++) {
            $p = $pList->item($i);
            if (! $p instanceof DOMElement) {
                continue;
            }

            $merged = '';
            $tNodes = $xp->query('.//a:t', $p);
            if (! $tNodes || $tNodes->length === 0) {
                continue;
            }
            for ($j = 0; $j < $tNodes->length; $j++) {
                $tn = $tNodes->item($j);
                if ($tn instanceof DOMElement) {
                    $merged .= $tn->textContent;
                }
            }

            if (! str_contains(mb_strtolower($merged), 'cumplimiento')) {
                continue;
            }

            if ($i > 0) {
                $prev = $pList->item($i - 1);
                if ($prev instanceof DOMElement) {
                    $prevText = '';
                    $prevNodes = $xp->query('.//a:t', $prev);
                    if ($prevNodes) {
                        for ($k = 0; $k < $prevNodes->length; $k++) {
                            $pn = $prevNodes->item($k);
                            if ($pn instanceof DOMElement) {
                                $prevText .= $pn->textContent;
                            }
                        }
                    }
                    $prevNorm = preg_replace('/\s+/u', ' ', trim($prevText)) ?? trim($prevText);
                    if (preg_match('/^\d+[.,]\d+\s*%?\s*de\s*$/iu', $prevNorm)) {
                        while ($prev->firstChild) {
                            $prev->removeChild($prev->firstChild);
                        }
                    }
                }
            }

            $tplRpr = $this->obtenerRPrPlantillaTextoCuerpo($xp, $p);

            while ($p->firstChild) {
                $p->removeChild($p->firstChild);
            }

            $linea1 = sprintf('%.2f%% de cumplimiento', $pct);

            $r1 = $dom->createElementNS(self::DRAWINGML_NS, 'a:r');
            if ($tplRpr instanceof DOMElement) {
                $rPrClon = $tplRpr->cloneNode(true);
                if ($rPrClon instanceof DOMElement) {
                    $this->aplicarRPrNegritaYColorCumplimiento($dom, $rPrClon, $pct);
                    $r1->appendChild($rPrClon);
                }
            } else {
                $rPrNuevo = $dom->createElementNS(self::DRAWINGML_NS, 'a:rPr');
                $rPrNuevo->setAttribute('lang', 'es-MX');
                $rPrNuevo->setAttribute('sz', '2800');
                foreach (['latin', 'ea', 'cs', 'sym'] as $tipo) {
                    $f = $dom->createElementNS(self::DRAWINGML_NS, 'a:'.$tipo);
                    $f->setAttribute('typeface', 'Gilroy');
                    $rPrNuevo->appendChild($f);
                }
                $this->aplicarRPrNegritaYColorCumplimiento($dom, $rPrNuevo, $pct);
                $r1->appendChild($rPrNuevo);
            }
            $t1 = $dom->createElementNS(self::DRAWINGML_NS, 'a:t');
            $t1->textContent = $linea1;
            $r1->appendChild($t1);
            $p->appendChild($r1);

            return;
        }
    }

    private function reemplazarNumerosSueltosEnXml(
        string $xml,
        int $totalMesas,
        int $mesasConAsistencia,
        int $mesasConInasistencia,
    ): string {
        $m1 = (string) $totalMesas;
        $m2 = (string) $mesasConAsistencia;
        $m3 = (string) $mesasConInasistencia;
        foreach (
            [
                ['1029', $m1], ['517', $m2], ['568', $m3],
                ['399', $m1], ['111', $m2], ['74', $m3],
                ['741', $m1],
            ] as [$viejo, $nuevo]
        ) {
            $xml = $this->reemplazarNumeroEnNodoAT($xml, $viejo, $nuevo);
        }

        return $xml;
    }

    private function reemplazarParrafosSemanaEnSlide(DOMXPath $xp, Carbon $lunesSemana, Carbon $viernesSemana): void
    {
        $pList = $xp->query('//a:p');
        if (! $pList) {
            return;
        }

        [$runSemana, $runRango, $runMes, $runAnio, $fallback] = $this->descomponerSemanaTemplate($lunesSemana, $viernesSemana);

        /*
         * Plantilla: el primer run del párrafo suele ser exactamente "Semana".
         * Mejor apuntar directo a ese marcador para evitar falsos negativos por merges/espaciados.
         */
        $markerNodes = $xp->query("//a:t[normalize-space(.)='Semana']");
        if ($markerNodes && $markerNodes->length > 0) {
            $marker = $markerNodes->item(0);
            if ($marker instanceof DOMElement) {
                $p = $xp->query('ancestor::a:p[1]', $marker);
                if ($p && $p->length > 0) {
                    $pNode = $p->item(0);
                    if ($pNode instanceof DOMElement) {
                        $tNodes = $xp->query('.//a:t', $pNode);
                        if ($tNodes && $tNodes->length > 0) {
                            $tElements = [];
                            for ($j = 0; $j < $tNodes->length; $j++) {
                                $n = $tNodes->item($j);
                                if ($n instanceof DOMElement) {
                                    $tElements[] = $n;
                                }
                            }

                            if (count($tElements) >= 4 && $runMes !== '') {
                                $tElements[0]->textContent = $runSemana;
                                $tElements[1]->textContent = $runRango;
                                $tElements[2]->textContent = $runMes;
                                $tElements[3]->textContent = $runAnio;
                                for ($k = 4, $kMax = count($tElements); $k < $kMax; $k++) {
                                    $tElements[$k]->textContent = '';
                                }
                            } else {
                                $tElements[0]->textContent = $fallback;
                                for ($k = 1, $kMax = count($tElements); $k < $kMax; $k++) {
                                    $tElements[$k]->textContent = '';
                                }
                            }

                            return;
                        }
                    }
                }
            }
        }

        for ($i = 0; $i < $pList->length; $i++) {
            $p = $pList->item($i);
            if (! $p instanceof DOMElement) {
                continue;
            }

            $tNodes = $xp->query('.//a:t', $p);
            if (! $tNodes || $tNodes->length === 0) {
                continue;
            }

            $merged = '';
            $tElements = [];
            for ($j = 0; $j < $tNodes->length; $j++) {
                $node = $tNodes->item($j);
                if ($node instanceof DOMElement) {
                    $tElements[] = $node;
                    $merged .= $node->textContent;
                }
            }

            $norm = preg_replace('/\s+/u', ' ', trim($merged)) ?? trim($merged);
            if ($norm === '') {
                continue;
            }

            $pareceSemana = (bool) preg_match('/^Semana\b/ui', $norm)
                && (
                    (bool) preg_match('/\d+\s+al\s+\d+/u', $norm)
                    || (bool) preg_match('/de\s+[a-záéíóúñ]+\s+de\s+\d{4}/ui', $norm)
                );

            if (! $pareceSemana) {
                continue;
            }

            if (count($tElements) >= 4 && $runMes !== '') {
                $tElements[0]->textContent = $runSemana;
                $tElements[1]->textContent = $runRango;
                $tElements[2]->textContent = $runMes;
                $tElements[3]->textContent = $runAnio;
                for ($k = 4, $kMax = count($tElements); $k < $kMax; $k++) {
                    $tElements[$k]->textContent = '';
                }
            } else {
                $tElements[0]->textContent = $fallback;
                for ($k = 1, $kMax = count($tElements); $k < $kMax; $k++) {
                    $tElements[$k]->textContent = '';
                }
            }

            return;
        }
    }

    private function formatoSemanaTemplate(Carbon $lunes, Carbon $viernes): string
    {
        $a = $lunes->copy()->locale('es');
        $b = $viernes->copy()->locale('es');

        if ($a->month === $b->month && $a->year === $b->year) {
            return sprintf(
                'Semana %d al %d de %s de %d',
                $a->day,
                $b->day,
                mb_strtolower($a->translatedFormat('F')),
                $a->year
            );
        }

        return sprintf(
            'Semana %d de %s al %d de %s de %d',
            $a->day,
            mb_strtolower($a->translatedFormat('F')),
            $b->day,
            mb_strtolower($b->translatedFormat('F')),
            $b->year
        );
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string} runSemana, runRango, runMes, runAnio, fallback
     */
    private function descomponerSemanaTemplate(Carbon $lunes, Carbon $viernes): array
    {
        $a = $lunes->copy()->locale('es');
        $b = $viernes->copy()->locale('es');

        if ($a->month === $b->month && $a->year === $b->year) {
            return [
                'Semana',
                sprintf(' %d al %d de ', $a->day, $b->day),
                mb_strtolower($a->translatedFormat('F')),
                sprintf(' de %d', $a->year),
                $this->formatoSemanaTemplate($lunes, $viernes),
            ];
        }

        return [
            'Semana',
            '',
            '',
            '',
            $this->formatoSemanaTemplate($lunes, $viernes),
        ];
    }

    private function reemplazarTresMetricasNumericas(
        DOMXPath $xp,
        int $totalMesas,
        int $mesasConAsistencia,
        int $mesasConInasistencia,
    ): int {
        $tAll = $xp->query('//a:t');
        if (! $tAll) {
            return 0;
        }

        $valores = [(string) $totalMesas, (string) $mesasConAsistencia, (string) $mesasConInasistencia];
        $metricNodes = [];

        for ($i = 0; $i < $tAll->length; $i++) {
            $node = $tAll->item($i);
            if (! $node instanceof DOMElement) {
                continue;
            }
            $txt = trim($node->textContent);
            if ($txt === '' || ! preg_match('/^\d+$/', $txt)) {
                continue;
            }
            $n = (int) $txt;
            if ($n >= 2010 && $n <= 2035 && strlen($txt) === 4) {
                continue;
            }

            $metricNodes[] = $node;
        }

        $count = min(3, count($metricNodes));
        for ($m = 0; $m < $count; $m++) {
            $metricNodes[$m]->textContent = $valores[$m];
        }

        return $count;
    }

    private function parchearSlide3PorTextoPlano(
        string $xml,
        string $textoSemanaAnterior,
        int $totalMesas,
        int $mesasConAsistencia,
        int $mesasConInasistencia,
        float $porcentajeCumplimiento,
        int $mesasSinRegistroSemanal,
    ): string {
        $reemplazosLiteral = [
            'Semana del 1 al 6 de febrero de 2026' => $textoSemanaAnterior,
            'Semana 1 al 6 de febrero de 2026' => $textoSemanaAnterior,
            'Semana del 1 al 6 de Febrero de 2026' => $textoSemanaAnterior,
        ];
        foreach ($reemplazosLiteral as $buscar => $valor) {
            if (str_contains($xml, $buscar)) {
                $xml = str_replace($buscar, $this->xmlText($valor), $xml);
            }
        }

        $xml = preg_replace(
            '/<a:t>(\d+[.,]\d+)\s*%?\s*de\s+cumplimiento<\/a:t>/iu',
            '<a:t>'.$this->xmlText(sprintf('%.2f%% de cumplimiento', $porcentajeCumplimiento)).'</a:t>',
            $xml,
            1
        ) ?? $xml;

        $linea2 = $this->textoMesasSinRegistroSemanal($mesasSinRegistroSemanal);

        $xml = preg_replace(
            '/<a:t>\d+\s+municipios?\s+sin\s+registro<\/a:t>/iu',
            '<a:t>'.$this->xmlText($linea2).'</a:t>',
            $xml
        ) ?? $xml;

        if (preg_match('/<a:t>[^<]*cumplimiento[^<]*<\/a:t>/iu', $xml)
            && ! preg_match('/mesas?\s+sin\s+registro\s+en\s+la\s+semana/iu', $xml)) {
            $xml = preg_replace(
                '/(<a:t>[^<]*cumplimiento[^<]*<\/a:t>)/iu',
                '$1<a:br/><a:r><a:t>'.$this->xmlText($linea2).'</a:t></a:r>',
                $xml,
                1
            ) ?? $xml;
        }

        return $this->reemplazarNumerosSueltosEnXml(
            $xml,
            $totalMesas,
            $mesasConAsistencia,
            $mesasConInasistencia,
        );
    }

    private function reemplazarNumeroEnNodoAT(string $xml, string $numeroPlantilla, string $nuevoValor): string
    {
        $patron = '#<a:t>\s*'.preg_quote($numeroPlantilla, '#').'\s*</a:t>#u';
        $sustituto = '<a:t>'.$this->xmlText($nuevoValor).'</a:t>';

        return preg_replace($patron, $sustituto, $xml, 1) ?? $xml;
    }

    private function xmlText(string $texto): string
    {
        return htmlspecialchars($texto, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
