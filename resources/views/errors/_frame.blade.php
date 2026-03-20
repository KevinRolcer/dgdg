{{--
  Marco común para páginas de error HTTP.
  Variables esperadas: $httpCode, $pageTitle, $headline, $message, $hints (iterable opcional)
--}}
@php
    $httpCode = (int) ($httpCode ?? 500);
    $pageTitle = $pageTitle ?? 'Error';
    $headline = $headline ?? 'Hubo un inconveniente';
    $message = $message ?? '';
    $hints = $hints ?? [];
@endphp
<!DOCTYPE html>
<html lang="es" class="error-page-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $pageTitle }} | Dirección General de Delegaciones</title>
    <link rel="icon" type="image/png" href="{{ asset('images/gob_edo.png') }}">
    <script>
        (function () {
            try {
                var k = 'segob_theme', d = 'segob_dark_variant';
                if (localStorage.getItem(k) !== 'dark') return;
                var v = localStorage.getItem(d) || 'deep';
                if (v !== 'soft' && v !== 'slate') v = 'deep';
                document.documentElement.classList.add('theme-dark', 'theme-dark--' + v);
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('assets/css/fonts-gilroy.css') }}?v={{ @filemtime(public_path('assets/css/fonts-gilroy.css')) ?: time() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/errors-page.css') }}?v={{ @filemtime(public_path('assets/css/errors-page.css')) ?: time() }}">
</head>
<body class="error-page-body">
    <div class="error-wrap">
        <div class="error-card">
            <div class="error-brand">
                <img
                    class="error-brand__logo--light"
                    src="{{ asset('images/Gobierno de Puebla_1-Versión horizontal.png') }}"
                    alt="Gobierno de Puebla"
                    width="320"
                    height="80"
                    decoding="async"
                >
                <img
                    class="error-brand__logo--dark"
                    src="{{ asset('images/Gobierno de Puebla_2-Versión horizontal.png') }}"
                    alt="Gobierno de Puebla"
                    width="320"
                    height="80"
                    decoding="async"
                >
            </div>
            <p class="error-code">Código {{ $httpCode }}</p>
            <h1 class="error-headline">{{ $headline }}</h1>
            @if ($message !== '')
                <p class="error-message">{{ $message }}</p>
            @endif
            @if (! empty($hints))
                <ul class="error-hints" role="list">
                    @foreach ($hints as $hint)
                        <li>{{ $hint }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="error-actions">
                <a href="{{ route('login') }}" class="error-btn error-btn--primary">Volver a entrar</a>
                @auth
                    <a href="{{ route('home') }}" class="error-btn error-btn--ghost">Ir al inicio</a>
                @endauth
            </div>
        </div>
    </div>
</body>
</html>
