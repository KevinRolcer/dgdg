<!DOCTYPE html>
<html lang="es" class="auth-page">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingreso al Sistema | Dirección General de Delegaciones</title>
    <script src="{{ asset('assets/js/theme-init.js') }}"></script>
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="Sat, 01 Jan 2000 00:00:00 GMT">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/login.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme-dark.css') }}">
</head>
<body>

    <main class="auth-container">
        <section class="auth-card">

            <header class="auth-header">
                <div class="auth-logo-box">
                    <img class="auth-logo-light" src="{{ asset('images/logo-gobierno.png') }}" alt="Logo Gobierno de Puebla">
                    <img class="auth-logo-dark" src="{{ asset('images/Gobierno de Puebla_2-Versión horizontal.png') }}" alt="Logo Gobierno de Puebla">
                </div>
                <h1 class="auth-title">Dirección General de Delegaciones</h1>
            </header>

            <div class="auth-form-content">
                @if ($errors->any())
                    <div class="auth-alert" role="alert">
                        {{ $errors->first() }}
                        @if(session('error_code') === 419)
                            <div class="auth-alert-csrf">
                                Error de autenticidad de sesión (CSRF).<br>
                                Por favor asegúrate de:
                                <ul>
                                    <li>No tener bloqueadas las cookies en tu navegador.</li>
                                    <li>Acceder siempre por <strong>https://kevinrolcer.com</strong>.</li>
                                    <li>No usar modo incógnito o extensiones que bloqueen cookies.</li>
                                    <li>Si el problema persiste, recarga la página o borra las cookies.</li>
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" novalidate>
                    @csrf
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">

                    <div class="form-field">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input
                            type="email"
                            name="email"
                            id="email"
                            class="form-input"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="usuario@puebla.gob.mx"
                            pattern="^[^@\s]+@[^@\s]+\.[^@\s]+$"
                        >
                    </div>

                    <div class="form-field">
                        <label for="password" class="form-label">Contraseña</label>
                        <div class="input-group-pass">
                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-input"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                            >
                            <button type="button" id="togglePassword" class="pass-toggle-btn" aria-label="Ver contraseña">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <label class="form-options" for="remember">
                        <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                        <span>Recordar sesión</span>
                    </label>

                    <button type="submit" class="btn-primary">Entrar</button>
                </form>
            </div>

        </section>
    </main>

    <script src="{{ asset('assets/js/login.js') }}" defer></script>
</body>
</html>
