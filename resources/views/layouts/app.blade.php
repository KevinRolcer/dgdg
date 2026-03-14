<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($pageTitle ?? 'Inicio') . ' | Dirección General de Delegaciones' }}</title>
    <script>
    (function(){try{var k='segob_theme',d='segob_dark_variant';if(localStorage.getItem(k)!=='dark')return;var v=localStorage.getItem(d)||'deep';if(v!=='soft'&&v!=='slate')v='deep';document.documentElement.classList.add('theme-dark','theme-dark--'+v);}catch(e){}})();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="{{ asset('assets/css/app-shell.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme-dark.css') }}">
    @stack('css')
    <link rel="stylesheet" href="{{ asset('assets/css/theme-dark-modules.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme-dark-agenda.css') }}">
    {{-- jQuery vía cdnjs (CSP solo permite cdnjs/jsdelivr/unpkg, no code.jquery.com) --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="{{ asset('assets/js/vendor/simple-expand.min.js') }}"></script>
</head>
<body data-export-status-url="{{ route('temporary-modules.admin.export-status', ['exportRequest' => 0]) }}">
    @php
        $topbarNotifications = collect($topbarNotifications ?? []);
        if (auth()->check()) {
            $dbNotifications = auth()->user()->unreadNotifications->map(function ($noty) {
                return [
                    'id' => $noty->id,
                    'icon' => $noty->data['icon'] ?? 'fa-regular fa-bell',
                    'title' => $noty->data['title'] ?? 'Nueva Notificación',
                    'time' => $noty->created_at->locale('es')->diffForHumans(),
                    'url' => $noty->data['url'] ?? null,
                    'export_request_id' => $noty->data['export_request_id'] ?? null,
                ];
            });
            $topbarNotifications = $dbNotifications->concat($topbarNotifications);
        }
    @endphp

    <div class="app-shell">
        @include('partials.sidebar')

        <div class="app-main">
            <header class="app-topbar">
                <button type="button" class="menu-toggle" id="menuToggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="appSidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="topbar-right">
                    <div class="topbar-dropdown-wrap">
                        <button
                            type="button"
                            class="topbar-notify"
                            id="topbarNotifyToggle"
                            aria-label="Notificaciones"
                            aria-expanded="false"
                            aria-controls="topbarNotifyPanel"
                        >
                            <i class="fa-regular fa-bell" aria-hidden="true"></i>
                            @if ($topbarNotifications->isNotEmpty())
                                <span class="topbar-notify-dot" aria-hidden="true"></span>
                            @endif
                        </button>

                        <div class="topbar-dropdown topbar-notify-panel" id="topbarNotifyPanel" role="menu" aria-hidden="true">
                            <div class="topbar-dropdown-header">
                                <strong>Notificaciones</strong>
                                <button
                                    type="button"
                                    class="topbar-notify-refresh"
                                    title="Recargar notificaciones"
                                    id="topbarNotifyRefresh"
                                >
                                    <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                                </button>
                            </div>
                            <ul class="topbar-notify-list">
                                @forelse ($topbarNotifications->take(6) as $notification)
                                    @php
                                        $rawUrl = $notification['url'] ?? null;
                                        $targetUrl = $rawUrl;
                                        if (is_string($rawUrl) && str_contains($rawUrl, '/temporary-exports/')) {
                                            $pathPart = parse_url($rawUrl, PHP_URL_PATH) ?: '';
                                            $fileName = basename($pathPart);
                                            if ($fileName !== '') {
                                                $targetUrl = route('temporary-modules.admin.exports.download', ['file' => $fileName]);
                                            }
                                        }
                                    @endphp
                                    <li class="{{ $loop->first ? 'is-active' : '' }}" @if(!empty($notification['export_request_id'])) data-export-request-id="{{ $notification['export_request_id'] }}" @endif>
                                        <span class="topbar-notify-icon">
                                            <i class="{{ $notification['icon'] ?? 'fa-regular fa-bell' }}" aria-hidden="true"></i>
                                        </span>
                                        <div>
                                            @if(!empty($targetUrl))
                                                <a href="{{ $targetUrl }}" style="text-decoration:none; color:inherit;">
                                                    <strong>{{ $notification['title'] ?? 'Notificación' }}</strong>
                                                </a>
                                            @else
                                                <strong>{{ $notification['title'] ?? 'Notificación' }}</strong>
                                            @endif
                                            <small>{{ $notification['time'] ?? 'Reciente' }}</small>
                                        </div>
                                    </li>
                                @empty
                                    <li class="topbar-notify-empty">No hay notificaciones por ahora.</li>
                                @endforelse
                            </ul>

                            <button
                                type="button"
                                class="topbar-notify-view-all"
                                id="topbarNotifyViewAll"
                                aria-controls="notificationsDrawer"
                                aria-expanded="false"
                            >
                                Ver todo
                            </button>
                        </div>
                    </div>

                    <div class="topbar-dropdown-wrap">
                        <button
                            type="button"
                            class="topbar-profile"
                            id="topbarProfileToggle"
                            aria-expanded="false"
                            aria-controls="topbarProfilePanel"
                            title="{{ auth()->user()->name }}"
                        >
                            <span class="topbar-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>
                            <div class="topbar-profile-meta">
                                <strong>{{ auth()->user()->name }}</strong>
                                <small>Ver mi perfil</small>
                            </div>
                            <i class="fa-solid fa-chevron-down topbar-profile-chevron" aria-hidden="true"></i>
                        </button>

                        <div class="topbar-dropdown topbar-profile-panel" id="topbarProfilePanel" role="menu" aria-hidden="true">
                            <a href="{{ route('profile.show') }}" class="topbar-menu-link {{ request()->routeIs('profile.show') ? 'is-active' : '' }}">
                                <i class="fa-regular fa-user" aria-hidden="true"></i>
                                <span>Mi perfil</span>
                            </a>
                            <a href="{{ route('settings.index') }}" class="topbar-menu-link {{ request()->routeIs('settings.index') ? 'is-active' : '' }}">
                                <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                                <span>Ajustes</span>
                            </a>

                            @can('Administrar-Usuarios')
                                <a href="{{ route('admin.usuarios.index') }}" class="topbar-menu-link {{ request()->routeIs('admin.usuarios.*') ? 'is-active' : '' }}">
                                    <i class="fa-solid fa-users-gear" aria-hidden="true"></i>
                                    <span>Gestión de Usuarios</span>
                                </a>
                            @endcan

                            <form method="POST" action="{{ route('logout') }}" class="topbar-menu-form">
                                @csrf
                                <button type="submit" class="topbar-menu-link topbar-menu-link-danger">
                                    <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                                    <span>Salir</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="app-content">
                @if (empty($hidePageHeader))
                    <section class="page-header">
                        <h1>{{ $pageTitle ?? 'Inicio' }}</h1>
                        @if (!empty($pageDescription))
                            <p>{{ $pageDescription }}</p>
                        @endif
                    </section>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @if (session('toast'))
        <div class="app-toast" id="appToast">
            <span>{{ session('toast') }}</span>
        </div>
    @endif

    <aside class="notifications-drawer" id="notificationsDrawer" aria-hidden="true">
        <header class="notifications-drawer-header">
            <strong>Todas las notificaciones</strong>
            <div class="notifications-drawer-actions">
                <button type="button" class="notifications-drawer-refresh" title="Recargar notificaciones" id="notificationsDrawerRefresh">
                    <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                </button>
                <form method="POST" action="{{ route('notifications.clear') }}" data-notifications-clear="1">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="notifications-drawer-clear">
                        Vaciar bandeja
                    </button>
                </form>
                <button type="button" class="notifications-drawer-close" id="notificationsDrawerClose" aria-label="Cerrar panel de notificaciones">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
        </header>

        <div class="notifications-drawer-body">
            @if ($topbarNotifications->isNotEmpty())
                <ul class="topbar-notify-list notifications-drawer-list">
                    @foreach ($topbarNotifications as $notification)
                        @php
                            $rawUrl = $notification['url'] ?? null;
                            $targetUrl = $rawUrl;
                            if (is_string($rawUrl) && str_contains($rawUrl, '/temporary-exports/')) {
                                $pathPart = parse_url($rawUrl, PHP_URL_PATH) ?: '';
                                $fileName = basename($pathPart);
                                if ($fileName !== '') {
                                    $targetUrl = route('temporary-modules.admin.exports.download', ['file' => $fileName]);
                                }
                            }
                        @endphp
                        <li @if(!empty($notification['export_request_id'])) data-export-request-id="{{ $notification['export_request_id'] }}" @endif>
                            <span class="topbar-notify-icon">
                                <i class="{{ $notification['icon'] ?? 'fa-regular fa-bell' }}" aria-hidden="true"></i>
                            </span>
                            <div>
                                @if(!empty($targetUrl))
                                    <a href="{{ $targetUrl }}" style="text-decoration:none; color:inherit;">
                                        <strong>{{ $notification['title'] ?? 'Notificación' }}</strong>
                                    </a>
                                @else
                                    <strong>{{ $notification['title'] ?? 'Notificación' }}</strong>
                                @endif
                                <small>{{ $notification['time'] ?? 'Reciente' }}</small>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="notifications-drawer-empty">No hay notificaciones por ahora.</p>
            @endif
        </div>
    </aside>

    <button type="button" class="notifications-drawer-backdrop" id="notificationsDrawerBackdrop" aria-label="Cerrar panel de notificaciones"></button>

    <div class="app-overlay" id="appOverlay" aria-hidden="true"></div>
    <script src="{{ asset('assets/js/app-shell.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
