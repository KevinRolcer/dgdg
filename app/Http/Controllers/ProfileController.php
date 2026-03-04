<?php

namespace App\Http\Controllers;

use App\Services\Profile\ProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $service)
    {
    }

    public function show(Request $request): View
    {
        $user = $request->user();
        $profileData = $this->service->buildProfileData($user);

        return view('profile.show', [
            'pageTitle' => 'Mi Perfil',
            'pageDescription' => 'Información personal y configuración de cuenta.',
            'topbarNotifications' => [],
            'user' => $user,
            ...$profileData,
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised(),
            ],
        ], [
            'current_password.required' => 'La contraseña actual es obligatoria.',
            'password.required' => 'La nueva contraseña es obligatoria.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.min' => 'La nueva contraseña debe tener al menos 12 caracteres.',
        ]);

        $user = $request->user();

        if (!$this->service->currentPasswordMatches($user, $validated['current_password'])) {
            return back()->with('error', 'La contraseña actual no es correcta.');
        }

        $this->service->updatePassword($user, $validated['password']);

        return back()->with('status', 'Contraseña actualizada correctamente.');
    }
}
