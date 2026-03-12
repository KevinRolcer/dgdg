<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class CanvaController extends Controller
{
    public function authRedirect(): RedirectResponse|Response
    {
        return redirect()->route('home')->with('toast', 'Integración Canva no configurada.');
    }

    public function generarDocumento(Request $request): Response
    {
        return response('Integración Canva no configurada.', 503);
    }
}
