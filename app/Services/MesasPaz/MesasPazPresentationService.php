<?php

namespace App\Services\MesasPaz;

use Carbon\Carbon;

class MesasPazPresentationService
{
    /**
     * Genera un archivo PPTX a partir de una plantilla.
     * Reemplaza la palabra "Fecha" por el rango de fechas 
     * y añade la fecha actual en la esquina inferior derecha.
     *
     * @return string URL pública del archivo generado.
     */
    public function generar(string $fechaInicio, string $fechaFin): string
    {
        $inicio = Carbon::parse($fechaInicio);
        $fin    = Carbon::parse($fechaFin);

        for ($d = $inicio->copy(); $d <= $fin; $d->addDay()) {
            if ($d->isWeekend()) {
                throw new \InvalidArgumentException('El rango de fechas no debe incluir sábados ni domingos.');
            }
        }

        $rango = $inicio->format('d/m/Y') . ' al ' . $fin->format('d/m/Y');
        $fechaActual = Carbon::now()->locale('es')->isoFormat('D [de] MMMM [del] YYYY');

        $templatePath = storage_path('templates/Dgdg Mesas de paz.pptx');
        if (!file_exists($templatePath)) {
            throw new \Exception('No se encontró la plantilla en: ' . $templatePath);
        }

        // Configurar ruta de destino
        $directorio = storage_path('app/public/ppt');
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $nombreArchivo = 'mesas_paz_' . $inicio->format('Ymd') . '_' . $fin->format('Ymd') . '_' . time() . '.pptx';
        $rutaCompleta  = $directorio . DIRECTORY_SEPARATOR . $nombreArchivo;

        // Copiar la plantilla al destino para trabajar sobre la copia (y mantener los fondos 100% intactos)
        copy($templatePath, $rutaCompleta);

        // Usar ZipArchive para modificar directamente los textos en el XML de la copia
        $zip = new \ZipArchive();
        if ($zip->open($rutaCompleta) === true) {
            // Normalmente la primera diapositiva está en ppt/slides/slide1.xml
            $slideXmlStr = $zip->getFromName('ppt/slides/slide1.xml');
            
            if ($slideXmlStr !== false) {
                // Reemplazar "Fecha" (Asegurándonos que está dentro de texto <a:t>)
                $slideXmlStr = str_replace('<a:t>Fecha</a:t>', '<a:t>' . $rango . '</a:t>', $slideXmlStr);
                $slideXmlStr = preg_replace('/>Fecha([^<]*?)</i', '>' . $rango . '$1<', $slideXmlStr);

                // Reemplazar la fecha de prueba "30 de Febrero del 2026"
                // PowerPoint puede dividir la cadena en múltiples nodos <a:t>. 
                // Usamos regex con strip_tags virtual para hacer match de la fecha completa a lo largo de etiquetas XML
                
                // 1. Caso simple (todo en un nodo):
                $slideXmlStr = str_replace('30 de Febrero del 2026', $fechaActual, $slideXmlStr);
                
                // 2. Si PowerPoint lo separó, p. ej. <a:t>30 </a:t><a:r>...<a:t>de Febrero del 2026</a:t>
                // Para no romper el XML, reemplazamos "30" por la fecha completa, y "de Febrero del 2026" por vacío.
                if (strpos($slideXmlStr, $fechaActual) === false) {
                    $slideXmlStr = preg_replace('/>30\s*</', '>' . $fechaActual . '<', $slideXmlStr);
                    $slideXmlStr = preg_replace('/>de Febrero del 2026\s*</', '><', $slideXmlStr);
                    // Por si estaba "30 de Febrero" y "del 2026" separados
                    $slideXmlStr = preg_replace('/>30 de Febrero\s*</', '>' . $fechaActual . '<', $slideXmlStr);
                    $slideXmlStr = preg_replace('/>del 2026\s*</', '><', $slideXmlStr);
                }

                $zip->addFromString('ppt/slides/slide1.xml', $slideXmlStr);
            }
            
            $zip->close();
        } else {
            throw new \Exception('No se pudo procesar la plantilla PPTX.');
        }

        return $rutaCompleta;
    }
}
