<?php

namespace App\Http\Controllers;

use App\Services\Admin\ImageStorageMigrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSettingsController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.settings.index', [
            'pageTitle' => 'Configuracion',
            'pageDescription' => 'Opciones administrativas del sistema.',
            'topbarNotifications' => [],
            'sharedUploadsPath' => (string) env('SHARED_UPLOADS_PATH', ''),
            'legacyLocalStoragePath' => public_path('localstorage'),
            'legacyTemporaryModulesPath' => storage_path('app/public/temporary-modules'),
            'migrationReport' => session('migration_report'),
        ]);
    }

    public function migrateImages(Request $request, ImageStorageMigrationService $service): RedirectResponse
    {
        $deleteOriginals = (bool) $request->boolean('delete_originals');

        $report = $service->migrate($deleteOriginals);

        $message = 'Migracion completada. Copiados: '.$report['files_copied']
            .', omitidos: '.$report['files_skipped_existing']
            .', errores: '.$report['files_failed'].'.';

        return redirect()
            ->route('admin.settings.index')
            ->with('status', $message)
            ->with('migration_report', $report);
    }
}
