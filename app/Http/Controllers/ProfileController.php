<?php

namespace App\Http\Controllers;

use App\Services\Profile\ProfileService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

    public function updateAvatar(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'file', 'image', 'max:5120'],
        ], [
            'avatar.required' => 'Selecciona una imagen para actualizar la foto de perfil.',
            'avatar.image' => 'El archivo seleccionado no es una imagen válida.',
            'avatar.max' => 'La imagen no puede superar los 5 MB.',
        ]);

        $user = $request->user();
        $file = $validated['avatar'];
        $oldAvatar = trim((string) ($user->avatar ?? ''));

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === '') {
            $ext = 'jpg';
        }

        $path = $file->storeAs('avatars/users/'.$user->id, 'profile.'.$ext, 'public');
        $publicUrl = Storage::disk('public')->url($path);

        try {
            $user->forceFill([
                'avatar' => $publicUrl,
            ])->save();
        } catch (QueryException) {
            return back()->with('error', 'Falta aplicar migraciones para usar foto de perfil. Ejecuta php artisan migrate.');
        }

        if ($oldAvatar !== '') {
            $oldPath = str_starts_with($oldAvatar, '/storage/')
                ? ltrim(substr($oldAvatar, strlen('/storage/')), '/')
                : ltrim($oldAvatar, '/');
            if ($oldPath !== '' && $oldPath !== $path && str_starts_with($oldPath, 'avatars/users/'.$user->id.'/')) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        return back()->with('status', 'Foto de perfil actualizada correctamente.');
    }
}
