<?php

namespace App\Http\Controllers;

use App\Services\Admin\ImageStorageMigrationService;
use App\Services\Settings\DistribuirMunicipiosExcelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminSettingsController extends Controller
{
    public function migrateImages(Request $request, ImageStorageMigrationService $service): RedirectResponse
    {
        $deleteOriginals = (bool) $request->boolean('delete_originals');

        $report = $service->migrate($deleteOriginals);

        $message = 'Migracion completada. Copiados: '.$report['files_copied']
            .', omitidos: '.$report['files_skipped_existing']
            .', errores: '.$report['files_failed'].'.';

        return redirect()
            ->route('settings.importacion-exportacion')
            ->with('status', $message)
            ->with('migration_report', $report);
    }

    public function distribuirMunicipiosExcel(Request $request, DistribuirMunicipiosExcelService $service): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'archivo_excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'],
        ], [
            'archivo_excel.required' => 'Selecciona un archivo Excel.',
            'archivo_excel.mimes' => 'El archivo debe ser Excel (.xlsx o .xls).',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('settings.importacion-exportacion')
                ->withErrors($validator)
                ->with('distribuir_errors', $validator->errors()->get('archivo_excel'));
        }

        $result = $service->distribuirDesdeExcel($request->file('archivo_excel'));

        if (! empty($result['errors'])) {
            return redirect()
                ->route('settings.importacion-exportacion')
                ->with('distribuir_errors', $result['errors'])
                ->with('distribuir_result', $result)
                ->withInput();
        }

        $updated = $result['updated'] ?? 0;
        $missingCount = count($result['missing_municipios'] ?? []);
        $missingMicroCount = count($result['missing_microrregiones'] ?? []);

        if ($updated === 0 && $missingCount === 0 && $missingMicroCount === 0) {
            $message = 'Distribución aplicada. No se realizaron cambios (todos los municipios ya estaban asignados a esas microrregiones, o no hubo filas válidas).';
        } else {
            $message = 'Distribución aplicada. Municipios actualizados: ' . $updated . '.';
            if ($missingCount > 0) {
                $message .= ' No encontrados en BD: ' . $missingCount . ' (revisa nombres en el Excel).';
            }
            if ($missingMicroCount > 0) {
                $message .= ' Microrregiones no encontradas: ' . implode(', ', array_slice($result['missing_microrregiones'], 0, 5));
                if ($missingMicroCount > 5) {
                    $message .= '…';
                }
            }
        }

        return redirect()
            ->route('settings.importacion-exportacion')
            ->with('status', $message)
            ->with('distribuir_result', $result);
    }
}
