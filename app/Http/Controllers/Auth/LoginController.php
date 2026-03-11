<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
            '_token' => 'required',
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'password.required' => 'La contraseña es obligatoria.',
            '_token.required' => 'Error de autenticidad de sesión. Refresca la página.',
        ]);

        $credentials = [
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'password' => (string) $request->input('password'),
        ];

        $remember = $request->has('remember');

        if (Auth::attempt($credentials, $remember)) {
            // Regenerar la sesión y el token CSRF para evitar reutilización
            $request->session()->regenerate();
            $request->session()->regenerateToken();

            // Evitar cache en la respuesta de login
            return redirect()->intended('/home')
                ->withHeaders([
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma' => 'no-cache',
                    'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
                ]);
        }
        // Detectar error CSRF
        if ($request->has('_token') && !$request->session()->token() && !$request->input('_token')) {
            return back()->withErrors(['email' => 'Error de autenticidad de sesión (CSRF).'])->with('error_code', 419);
        }
        return back()->withErrors(['email' => 'Credenciales incorrectas'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
