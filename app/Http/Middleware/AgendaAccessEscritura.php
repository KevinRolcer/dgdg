<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Igual que PAP: acceso a mutaciones de agenda solo con permisos de escritura
 * (sin permiso solo-consulta).
 */
class AgendaAccessEscritura
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }
        if ($user->can('Modulos-Temporales-Admin')
            || $user->can('Agenda-Directiva')
            || $user->can('Agenda-Seguimiento')) {
            return $next($request);
        }
        abort(403);
    }
}
