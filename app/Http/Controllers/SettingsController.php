<?php

namespace App\Http\Controllers;

use App\Models\Microrregione;
use App\Models\WhatsAppChatAccessLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

    public function whatsappTotpReset(): View
    {
        return view('settings.whatsapp-totp-reset', $this->settingsViewData(
            'Chats WhatsApp (autenticador)',
            'Restablecer Google Authenticator para volver a escanear el código QR al entrar al módulo sensible.'
        ));
    }

    public function whatsappTotpResetSubmit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
        ], [
            'current_password.required' => 'Debes escribir tu contraseña de administrador.',
        ]);

        $user = $request->user();
        if (! Hash::check($validated['current_password'], (string) $user->password)) {
            return back()->withErrors(['current_password' => 'La contraseña no es correcta.'])->withInput();
        }

        $user->forceFill([
            'whatsapp_totp_secret' => null,
            'whatsapp_totp_confirmed_at' => null,
        ])->save();

        $request->session()->forget(['whatsapp_totp_session_ok', 'whatsapp_totp_pending_secret']);

        try {
            WhatsAppChatAccessLog::create([
                'whatsapp_chat_archive_id' => null,
                'user_id' => $user->id,
                'action' => 'totp_reset',
                'resource_path' => 'settings/chats-whatsapp-autenticador',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 2000),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // no bloquear
        }

        return redirect()
            ->route('settings.whatsapp-totp-reset')
            ->with('status', 'Autenticador restablecido. La próxima vez que entres a Chats WhatsApp podrás escanear un código QR nuevo.');
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
