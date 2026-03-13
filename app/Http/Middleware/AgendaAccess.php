<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AgendaAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }
        if ($user->can('Modulos-Temporales-Admin') || $user->can('Agenda-Directiva')) {
            return $next($request);
        }
        abort(403);
    }
}
