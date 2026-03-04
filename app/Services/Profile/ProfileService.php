<?php

namespace App\Services\Profile;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfileService
{
    public function buildProfileData(User $user): array
    {
        $roleName = $user->roles()->pluck('name')->first() ?? 'Sin rol';

        $delegado = DB::table('delegados')
            ->where('user_id', $user->id)
            ->first();

        $microrregion = null;
        $municipios = collect();

        if ($delegado && !empty($delegado->microrregion_id)) {
            $microrregion = DB::table('microrregiones')
                ->where('id', $delegado->microrregion_id)
                ->first();

            $municipios = DB::table('municipios')
                ->where('microrregion_id', $delegado->microrregion_id)
                ->orderBy('municipio')
                ->pluck('municipio');
        }

        $profilePhotoRaw = $delegado?->foto ?: ($user->avatar ?? null);

        return [
            'roleName' => $roleName,
            'profilePhoto' => $this->sanitizeProfilePhoto($profilePhotoRaw),
            'delegado' => $delegado,
            'microrregion' => $microrregion,
            'municipios' => $municipios,
        ];
    }

    public function currentPasswordMatches(User $user, string $currentPassword): bool
    {
        return Hash::check($currentPassword, $user->password);
    }

    public function updatePassword(User $user, string $newPassword): void
    {
        $user->password = $newPassword;
        $user->save();
    }

    private function sanitizeProfilePhoto(?string $photo): ?string
    {
        $value = trim((string) $photo);
        if ($value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

            return in_array($scheme, ['http', 'https'], true) ? $value : null;
        }

        $normalized = '/'.ltrim(str_replace('\\\\', '/', $value), '/');

        if (str_starts_with($normalized, '/images/') || str_starts_with($normalized, '/localstorage/')) {
            return $normalized;
        }

        return null;
    }
}
