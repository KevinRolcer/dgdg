<?php

namespace App\Services\MesasPaz;

use App\Models\MesaPazAsistencia;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Log;

class MesasPazPresentationService
{
    private const DRAWINGML_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    /**
     * Genera un archivo PPTX a partir de una plantilla.
     * Diapositiva 3: semana laboral anterior (lun–vie) a la semana que contiene la **fecha fin** del rango del reporte,
     * con totales calculados para esos cinco días (misma ventana que el texto de la semana).
     *
     * @return string Ruta absoluta del archivo generado.
     */
    public function generar(string $fechaInicio, string $fechaFin): string
    {
        $inicio = Carbon::parse($fechaInicio)->startOfDay();
        $fin = Carbon::parse($fechaFin)->startOfDay();

        for ($d = $inicio->copy(); $d <= $fin; $d->addDay()) {
            if ($d->isWeekend()) {
                throw new \InvalidArgumentException('El rango de fechas no debe incluir sábados ni domingos.');
            }
        }

        $rango = $inicio->format('d/m/Y').' al '.$fin->format('d/m/Y');
        $fechaActual = Carbon::now()->locale('es')->isoFormat('D [de] MMMM [del] YYYY');

        // Semana “del reporte”: ISO de la fecha de fin del rango; la semana a contar es la laboral anterior (lun–vie).
        [$lunesAnterior, $viernesAnterior] = $this->semanaLaboralAnterior($fin);
        $textoSemanaAnterior = $this->formatoRangoSemanaEspanol($lunesAnterior, $viernesAnterior);
        $stats = $this->estadisticasSemanaLaboral($lunesAnterior, $viernesAnterior);

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

        $zip = new \ZipArchive;
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
        if ($slide3 !== false) {
            $zip->addFromString('ppt/slides/slide3.xml', $this->parchearSlide3ReporteGeneral(
                $slide3,
                $textoSemanaAnterior,
                $stats['total_mesas'],
                $stats['municipios_presentes'],
                $stats['municipios_no_presentes'],
            ));
        } else {
            Log::warning('mesas_paz.pptx: no existe ppt/slides/slide3.xml; se omiten totales del reporte general.');
        }

        $zip->close();

        return $rutaCompleta;
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
     * @return array{total_mesas: int, municipios_presentes: int, municipios_no_presentes: int}
     */
    private function estadisticasSemanaLaboral(Carbon $lunes, Carbon $viernes): array
    {
        $desde = $lunes->toDateString();
        $hasta = $viernes->toDateString();

        $filas = MesaPazAsistencia::query()
            ->whereDate('fecha_asist', '>=', $desde)
            ->whereDate('fecha_asist', '<=', $hasta)
            ->orderBy('fecha_asist')
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

        $municipiosPresentes = collect($porMunicipioDia)
            ->filter(fn ($r) => MesaPazAsistencia::asistenciaEsPresente($r->asiste))
            ->pluck('municipio_id')
            ->unique()
            ->count();

        $municipiosNoPresentes = collect($porMunicipioDia)
            ->filter(fn ($r) => MesaPazAsistencia::asistenciaEsNoPresente($r->asiste))
            ->pluck('municipio_id')
            ->unique()
            ->count();

        return [
            'total_mesas' => $totalMesas,
            'municipios_presentes' => $municipiosPresentes,
            'municipios_no_presentes' => $municipiosNoPresentes,
        ];
    }

    /**
     * Parchea slide 3: reemplazo de la línea “Semana del…” aunque PowerPoint parta el texto en varios &lt;a:t&gt;,
     * y los tres totales (orden documento: mesas, asistencias, inasistencias) aunque la plantilla traiga otros números.
     */
    private function parchearSlide3ReporteGeneral(
        string $xml,
        string $textoSemanaAnterior,
        int $totalMesas,
        int $municipiosPresentes,
        int $municipiosNoPresentes,
    ): string {
        $domOk = false;
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;

        try {
            if (@$dom->loadXML($xml, LIBXML_NONET)) {
                $xp = new DOMXPath($dom);
                $xp->registerNamespace('a', self::DRAWINGML_NS);

                $this->reemplazarParrafosSemanaEnSlide($xp, $textoSemanaAnterior);
                $reemplazados = $this->reemplazarTresMetricasNumericas($xp, $totalMesas, $municipiosPresentes, $municipiosNoPresentes);

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
                return $this->reemplazarNumerosSueltosEnXml($out, $totalMesas, $municipiosPresentes, $municipiosNoPresentes);
            }
        }

        return $this->parchearSlide3PorTextoPlano(
            $xml,
            $textoSemanaAnterior,
            $totalMesas,
            $municipiosPresentes,
            $municipiosNoPresentes,
        );
    }

    /**
     * Sustituye valores típicos de plantilla en un único &lt;a:t&gt; (respaldo tras DOM o si el número quedó partido en otro nodo).
     */
    private function reemplazarNumerosSueltosEnXml(
        string $xml,
        int $totalMesas,
        int $municipiosPresentes,
        int $municipiosNoPresentes,
    ): string {
        $m1 = (string) $totalMesas;
        $m2 = (string) $municipiosPresentes;
        $m3 = (string) $municipiosNoPresentes;
        foreach (
            [
                ['1029', $m1], ['517', $m2], ['568', $m3],
                ['399', $m1], ['111', $m2], ['74', $m3],
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

    /**
     * @return int Cantidad de nodos numéricos reemplazados (0–3).
     */
    private function reemplazarTresMetricasNumericas(
        DOMXPath $xp,
        int $totalMesas,
        int $municipiosPresentes,
        int $municipiosNoPresentes,
    ): int {
        $tAll = $xp->query('//a:t');
        if (! $tAll) {
            return 0;
        }

        $valores = [(string) $totalMesas, (string) $municipiosPresentes, (string) $municipiosNoPresentes];
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

    /**
     * Respaldo cuando el XML no carga en DOM o faltan nodos: literales y números en un solo &lt;a:t&gt;.
     */
    private function parchearSlide3PorTextoPlano(
        string $xml,
        string $textoSemanaAnterior,
        int $totalMesas,
        int $municipiosPresentes,
        int $municipiosNoPresentes,
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

        return $this->reemplazarNumerosSueltosEnXml(
            $xml,
            $totalMesas,
            $municipiosPresentes,
            $municipiosNoPresentes,
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
