<?php

namespace App\Http\Controllers;

use App\Models\Microrregione;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('settings.apariencia');
    }

    public function apariencia(): View
    {
        return view('settings.apariencia', $this->settingsViewData('Apariencia', 'Tema y visualización de la aplicación.'));
    }

    public function importacionExportacion(): View
    {
        $data = $this->settingsViewData(
            'Importación y exportación',
            'Herramientas de migración de archivos (solo administración).'
        );
        $data['microrregionesConMunicipios'] = Microrregione::with(['municipios' => fn ($q) => $q->orderBy('municipio')])
            ->orderByRaw('CAST(microrregion AS UNSIGNED)')
            ->get();

        return view('settings.importacion-exportacion', $data);
    }

    /** @return array<string, mixed> */
    private function settingsViewData(string $sectionTitle, string $sectionDescription): array
    {
        return [
            'pageTitle' => 'Ajustes',
            'pageDescription' => '',
            'topbarNotifications' => [],
            'hidePageHeader' => true,
            'settingsSectionTitle' => $sectionTitle,
            'settingsSectionDescription' => $sectionDescription,
            'migrationReport' => session('migration_report'),
        ];
    }
}
