<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Slide;
use PhpOffice\PhpPresentation\Shape\RichText;
use Carbon\Carbon;

class PowerPointController extends Controller
{
    public function generarPresentacion(Request $request)
    {
        $fecha_inicio = $request->input('fecha_inicio');
        $fecha_fin = $request->input('fecha_fin');

        // Validar que el rango no incluya sábados ni domingos
        if ($fecha_inicio && $fecha_fin) {
            $inicio = Carbon::parse($fecha_inicio);
            $fin = Carbon::parse($fecha_fin);
            for ($date = $inicio->copy(); $date <= $fin; $date->addDay()) {
                $dayOfWeek = $date->dayOfWeek;
                if ($dayOfWeek === 6 || $dayOfWeek === 0) {
                    return response()->json(['error' => 'El rango de fechas no debe incluir sábados ni domingos.'], 422);
                }
            }
            $rango = $inicio->format('d/m/Y') . ' al ' . $fin->format('d/m/Y');
        } else {
            $rango = $fecha_inicio ?: $fecha_fin ?: '';
        }

        // Cargar la plantilla
        $templatePath = storage_path('templates/Dgdg Mesas de paz.pptx');
        $ppt = IOFactory::load($templatePath);

        // Modificar el campo de fecha en la primera diapositiva
        /** @var Slide $slide */
        $slide = $ppt->getSlide(0);
        foreach ($slide->getShapeCollection() as $shape) {
            if ($shape instanceof RichText) {
                $text = $shape->getPlainText();
                if (stripos($text, 'fecha') !== false) {
                    $shape->getActiveParagraph()->createTextRun($rango);
                    break;
                }
            }
        }

        // Guardar el nuevo archivo
        $outputName = 'Presentacion_Mesas_de_Paz_' . date('Ymd_His') . '.pptx';
        $outputPath = storage_path('app/' . $outputName);
        $writer = IOFactory::createWriter($ppt, 'PowerPoint2007');
        $writer->save($outputPath);

        // Devuelve el enlace de descarga
        $url = asset('storage/app/' . $outputName);
        return response()->json(['url' => $url]);
    }
}
