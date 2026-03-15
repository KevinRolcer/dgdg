<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\Paginator;
use Spatie\Permission\Models\Permission;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /* Evita "There is no permission named …" si aún no corrió la migración de consulta */
        foreach ([
            'Mesas-Paz-consulta',
            'Modulos-Temporales-Admin-consulta',
            'Modulos-Temporales-consulta',
            'Agenda-consulta',
        ] as $permName) {
            Permission::findOrCreate($permName, 'web');
        }

        Gate::define('mesas-paz-ver', function ($user) {
            if (!$user) {
                return false;
            }
            if (Gate::forUser($user)->allows('Mesas-Paz')) {
                return true;
            }
            try {
                return $user->hasPermissionTo('Mesas-Paz-consulta');
            } catch (\Throwable) {
                return false;
            }
        });

        Gate::define('modulos-temporales-admin-ver', function ($user) {
            if (!$user) {
                return false;
            }
            if ($user->can('Modulos-Temporales-Admin')) {
                return true;
            }
            try {
                return $user->hasPermissionTo('Modulos-Temporales-Admin-consulta');
            } catch (\Throwable) {
                return false;
            }
        });

        Gate::define('modulos-temporales-ver', function ($user) {
            if (!$user) {
                return false;
            }
            if ($user->can('Modulos-Temporales')) {
                return true;
            }
            try {
                return $user->hasPermissionTo('Modulos-Temporales-consulta');
            } catch (\Throwable) {
                return false;
            }
        });

        Gate::define('Mesas-Paz', function ($user) {
            if (!$user) {
                return false;
            }

            $blockedMesasPazEmails = [
                'dgdg.admon@gmail.com',
            ];

            $email = mb_strtolower((string) ($user->email ?? ''));
            if (in_array($email, $blockedMesasPazEmails, true)) {
                return false;
            }

            $modelTypes = array_values(array_unique([
                get_class($user),
                'App\\Models\\User',
                'App\\User',
            ]));

            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }

            $hasDelegadoOrEnlaceRole = DB::table('model_has_roles as mhr')
                ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                ->whereIn('mhr.model_type', $modelTypes)
                ->where('mhr.model_id', (int) $user->id)
                ->whereIn('r.name', ['Delegado', 'Enlace'])
                ->exists();

            if ($hasDelegadoOrEnlaceRole) {
                return true;
            }

            // Resolve permission by name across role assignments without guard coupling.
            $hasRolePermission = DB::table('model_has_roles as mhr')
                ->join('role_has_permissions as rhp', 'rhp.role_id', '=', 'mhr.role_id')
                ->join('permissions as p', 'p.id', '=', 'rhp.permission_id')
                ->whereIn('mhr.model_type', $modelTypes)
                ->where('mhr.model_id', (int) $user->id)
                ->where('p.name', 'Mesas-Paz')
                ->exists();

            if ($hasRolePermission) {
                return true;
            }

            return DB::table('model_has_permissions as mhp')
                ->join('permissions as p', 'p.id', '=', 'mhp.permission_id')
                ->whereIn('mhp.model_type', $modelTypes)
                ->where('mhp.model_id', (int) $user->id)
                ->where('p.name', 'Mesas-Paz')
                ->exists();
        });

        RateLimiter::for('login', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email', ''));

            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
            ];
        });

        if (!app()->isLocal() && config('app.force_https', false)) {
            URL::forceScheme('https');
        }

        // Sin Tailwind, la vista por defecto (tailwind) rompe la UI (SVG gigantes). Bootstrap encaja con el layout.
        Paginator::useBootstrapFive();
    }
}
