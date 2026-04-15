<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Profile\ProfileService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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

        // Store in shared_uploads/profilePhoto/ (falls back to public disk locally if not configured)
        $disk = config('filesystems.disks.secure_shared.root') ? 'secure_shared' : 'public';
        $relativePath = 'profilePhoto/users/'.$user->id.'/profile.'.$ext;

        Storage::disk($disk)->putFileAs('profilePhoto/users/'.$user->id, $file, 'profile.'.$ext);

        try {
            $user->forceFill(['avatar' => $relativePath])->save();
        } catch (QueryException) {
            return back()->with('error', 'Falta aplicar migraciones para usar foto de perfil. Ejecuta php artisan migrate.');
        }

        // Clean up old file
        if ($oldAvatar !== '' && $oldAvatar !== $relativePath) {
            if (str_starts_with($oldAvatar, 'profilePhoto/users/'.$user->id.'/')) {
                Storage::disk($disk)->delete($oldAvatar);
            } elseif (str_starts_with($oldAvatar, 'avatars/users/'.$user->id.'/')) {
                Storage::disk('public')->delete($oldAvatar);
            }
        }

        return back()->with('status', 'Foto de perfil actualizada correctamente.');
    }

    public function serveAvatar(Request $request, int $userId): Response
    {
        abort_unless($request->user() !== null, 403);

        $user = User::findOrFail($userId);
        $avatar = trim((string) ($user->avatar ?? ''));

        abort_unless($avatar !== '' && str_starts_with($avatar, 'profilePhoto/'), 404);

        // Try secure_shared first, then public disk
        $disk = 'secure_shared';
        if (!Storage::disk($disk)->exists($avatar)) {
            $disk = 'public';
        }
        abort_unless(Storage::disk($disk)->exists($avatar), 404);

        $fullPath = Storage::disk($disk)->path($avatar);
        $mime = mime_content_type($fullPath) ?: 'image/jpeg';

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
