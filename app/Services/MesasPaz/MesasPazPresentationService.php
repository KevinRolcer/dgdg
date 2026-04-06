<?php

namespace App\Services\MesasPaz;

use App\Models\MesaPazAsistencia;
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

        copy($templatePath, $rutaCompleta);

        $zip = new ZipArchive;
        if ($zip->open($rutaCompleta) !== true) {
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
                $textoSemanaAnterior,
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
                5000000,
                3300000,
                6400000,
                4000000,
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
                $textoSemanaMarcada,
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
                5000000,
                3300000,
                6400000,
                4000000,
            );

            $zip->addFromString('ppt/slides/slide4.xml', $slide4);
            if ($relsContent4 !== '') {
                $zip->addFromString($relsPath4, $relsContent4);
            }

            if (is_string($pngTemporal4) && $pngTemporal4 !== '' && is_file($pngTemporal4)) {
                $pngTemporales[] = $pngTemporal4;
            }
        }

        $zip->close();
        foreach ($pngTemporales as $pngTmp) {
            @unlink($pngTmp);
        }

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
        $w = 2800;
        $h = 1500;
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

        $cx = 1600;
        $cy = 560;
        $diam = 1080;
        $inner = 660;

        $start = -90.0;
        $segmentos = [
            ['v' => $asistencias, 'c' => $verde],
            ['v' => $inasistencias, 'c' => $guinda],
            ['v' => $sinRegistro, 'c' => $dorado],
        ];

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
        $totalConSinRegistro = $asistencias + $inasistencias + $sinRegistro;
        $textoCentro = (string) $totalConSinRegistro;
        $pctAsistencia = ($total > 0) ? round(($asistencias / $total) * 100, 1) : 0.0;
        $pctInasistencia = ($total > 0) ? round(($inasistencias / $total) * 100, 1) : 0.0;
        $pctSinRegistro = ($total > 0) ? round(($sinRegistro / $total) * 100, 1) : 0.0;

        if (is_file($font) && function_exists('imagettftext')) {
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
            $tyTotal = (int) round($cy + 245);
            @imagettftext($im, 58, 0, $txTotal, $tyTotal, $texto, $font, $totalText);
            @imagettftext($im, 58, 0, $txTotal + 1, $tyTotal, $texto, $font, $totalText);

            $sizeLabel = 72;
            $sizePct = 74;
            $baseY = 1340;

            $drawRow = function (string $label, float $pct, int $color, int $centerX) use ($im, $font, $sizeLabel, $sizePct, $baseY, $texto) {
                $labelTxt = $label.': ';
                $pctTxt = number_format($pct, 1).'%';

                $bboxL = imagettfbbox($sizeLabel, 0, $font, $labelTxt);
                $bboxP = imagettfbbox($sizePct, 0, $font, $pctTxt);
                $wL = $bboxL ? abs($bboxL[2] - $bboxL[0]) : 0;
                $wP = $bboxP ? abs($bboxP[2] - $bboxP[0]) : 0;
                $startX = (int) round($centerX - (($wL + $wP) / 2));

                @imagettftext($im, $sizeLabel, 0, $startX, $baseY, $texto, $font, $labelTxt);
                @imagettftext($im, $sizeLabel, 0, $startX + 1, $baseY, $texto, $font, $labelTxt);
                @imagettftext($im, $sizePct, 0, $startX + $wL, $baseY, $color, $font, $pctTxt);
                @imagettftext($im, $sizePct, 0, $startX + $wL + 1, $baseY, $color, $font, $pctTxt);
            };

            $drawRow('Asistencias', $pctAsistencia, $verde, 430);
            $drawRow('Inasistencias', $pctInasistencia, $guinda, 1420);
            $drawRow('Sin registro', $pctSinRegistro, $dorado, 2360);
        } else {
            imagestring($im, 5, $cx - 90, $cy - 16, $textoCentro, $texto);
            imagestring($im, 5, 120, 1390, 'Asistencias: '.number_format($pctAsistencia, 1).'%', $verde);
            imagestring($im, 5, 980, 1390, 'Inasistencias: '.number_format($pctInasistencia, 1).'%', $guinda);
            imagestring($im, 5, 1880, 1390, 'Sin registro: '.number_format($pctSinRegistro, 1).'%', $dorado);
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
        string $textoSemanaAnterior,
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

                $this->reemplazarParrafosSemanaEnSlide($xp, $textoSemanaAnterior);
                $reemplazados = $this->reemplazarTresMetricasNumericas($xp, $totalMesas, $mesasConAsistencia, $mesasConInasistencia);
                $this->parchearParrafoCumplimientoYSinRegistro($dom, $xp, $porcentajeCumplimiento, $mesasSinRegistroSemanal);
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

                return $this->forzarTextoCumplimientoEnXml($out, $porcentajeCumplimiento);
            }
        }

        $fallback = $this->parchearSlide3PorTextoPlano(
            $xml,
            $textoSemanaAnterior,
            $totalMesas,
            $mesasConAsistencia,
            $mesasConInasistencia,
            $porcentajeCumplimiento,
            $mesasSinRegistroSemanal,
        );

        return $this->forzarTextoCumplimientoEnXml($fallback, $porcentajeCumplimiento);
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

        $patron = '/<a:t>[^<]*\d+[.,]\d+\s*%?\s*de\s+cumplimiento[^<]*<\/a:t>/iu';
        if (preg_match($patron, $xml) === 1) {
            return preg_replace($patron, '<a:t>'.$this->xmlText($linea).'</a:t>', $xml, 1) ?? $xml;
        }

        $patron2 = '/<a:t>[^<]*cumplimiento[^<]*<\/a:t>/iu';

        return preg_replace($patron2, '<a:t>'.$this->xmlText($linea).'</a:t>', $xml, 1) ?? $xml;
    }

    private function textoMesasSinRegistroSemanal(int $n): string
    {
        return $n === 1
            ? '1 mesa sin registro en la semana'
            : $n.' mesas sin registro en la semana';
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
                $r1->appendChild($dom->importNode($tplRpr->cloneNode(true), true));
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

    private function reemplazarParrafosSemanaEnSlide(DOMXPath $xp, string $textoSemanaAnterior): void
    {
        $pList = $xp->query('//a:p');
        if (! $pList) {
            return;
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

            $tElements[0]->textContent = $textoSemanaAnterior;
            for ($k = 1, $kMax = count($tElements); $k < $kMax; $k++) {
                $tElements[$k]->textContent = '';
            }

            return;
        }
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
