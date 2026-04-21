<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($pageTitle ?? 'Inicio') . ' | Dirección General de Delegaciones' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/gob_edo.png') }}">
    <script src="{{ asset('assets/js/theme-init.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/css/fonts-gilroy.css') }}?v={{ @filemtime(public_path('assets/css/fonts-gilroy.css')) ?: time() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="{{ asset('assets/css/app-shell.css') }}?v={{ @filemtime(public_path('assets/css/app-shell.css')) ?: time() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme-dark.css') }}?v={{ @filemtime(public_path('assets/css/theme-dark.css')) ?: time() }}">
    @stack('css')
    <link rel="stylesheet" href="{{ asset('assets/css/theme-dark-modules.css') }}?v={{ @filemtime(public_path('assets/css/theme-dark-modules.css')) ?: time() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme-dark-agenda.css') }}?v={{ @filemtime(public_path('assets/css/theme-dark-agenda.css')) ?: time() }}">
    {{-- jQuery vía cdnjs (CSP solo permite cdnjs/jsdelivr/unpkg, no code.jquery.com) --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('assets/js/vendor/simple-expand.min.js') }}"></script>
</head>
<body
    @can('Modulos-Temporales-Admin')
    @php
        $__exportStatusUrlTemplates = array_values(array_unique([
            url('/exportacion/estado/0'),
            url('/q/st/0'),
            url('/modulos-temporales/admin/export-status/0'),
            url('/exp-status/0'),
            url('/poller/export/0'),
        ]));
        // Convert to paths (relative to domain root) for consistency with JS expectation
        $__exportStatusUrlTemplates = array_map(function($u) {
            return parse_url($u, PHP_URL_PATH);
        }, $__exportStatusUrlTemplates);
    @endphp
    data-export-status-url="{{ $__exportStatusUrlTemplates[0] ?? '' }}"
    data-export-status-urls="{{ e(json_encode($__exportStatusUrlTemplates)) }}"
    @endcan
    @can('Chats-WhatsApp-Sensible') data-whatsapp-import-status-base="{{ url('/admin/whatsapp-chats') }}" @endcan
>
    @php
        $topbarNotifications = collect($topbarNotifications ?? []);
        $topbarUser = auth()->user();
        $topbarUserName = $topbarUser?->name ?? 'Usuario';
        $topbarAvatarRaw = trim((string) ($topbarUser?->avatar ?? ''));
        $topbarAvatarUrl = null;
        if ($topbarAvatarRaw !== '') {
            if (str_starts_with($topbarAvatarRaw, 'profilePhoto/')) {
                $topbarAvatarUrl = $topbarUser ? route('profile.avatar.serve', ['userId' => $topbarUser->id]) : null;
            } elseif (filter_var($topbarAvatarRaw, FILTER_VALIDATE_URL)) {
                $avatarScheme = strtolower((string) parse_url($topbarAvatarRaw, PHP_URL_SCHEME));
                if (in_array($avatarScheme, ['http', 'https'], true)) {
                    $topbarAvatarUrl = $topbarAvatarRaw;
                }
            } else {
                $normalizedAvatar = '/'.ltrim(str_replace('\\', '/', $topbarAvatarRaw), '/');
                if (str_starts_with($normalizedAvatar, '/storage/')
                    || str_starts_with($normalizedAvatar, '/images/')
                    || str_starts_with($normalizedAvatar, '/localstorage/')) {
                    $topbarAvatarUrl = $normalizedAvatar;
                }
            }
        }
        $topbarUserWords = \Illuminate\Support\Str::of($topbarUserName)
            ->squish()
            ->explode(' ')
            ->filter();
        $topbarUserNameShortWords = [];
        foreach ($topbarUserWords as $word) {
            $topbarUserNameShortWords[] = (string) $word;
            $containsDe = in_array('de', array_map(
                fn ($w) => mb_strtolower((string) $w),
                $topbarUserNameShortWords
            ), true);
            $maxWords = $containsDe ? 3 : 2;
            if (count($topbarUserNameShortWords) >= $maxWords) {
                break;
            }
        }
        $topbarUserNameShort = implode(' ', $topbarUserNameShortWords);
        if ($topbarUserNameShort === '') {
            $topbarUserNameShort = $topbarUserWords->take(2)->implode(' ');
        }

        if ($topbarUser) {
            $dbNotifications = $topbarUser->unreadNotifications->map(function ($noty) {
                $d = $noty->data ?? [];
                $waArchiveId = $d['whatsapp_archive_id'] ?? $d['whatsapp_chat_id'] ?? null;
                $waStatus = $d['whatsapp_import_status'] ?? null;
                if ($waStatus === null && array_key_exists('whatsapp_import_success', $d)) {
                    $waStatus = ($d['whatsapp_import_success'] ?? false) ? 'completed' : 'failed';
                }
                $waProgress = isset($d['whatsapp_import_progress']) ? (int) $d['whatsapp_import_progress'] : null;
                $waPhase = $d['whatsapp_import_phase'] ?? null;

                return [
                    'id' => $noty->id,
                    'icon' => $d['icon'] ?? 'fa-regular fa-bell',
                    'title' => $d['title'] ?? 'Nueva Notificación',
                    'time' => $noty->created_at->locale('es')->diffForHumans(),
                    'url' => $d['url'] ?? null,
                    'export_request_id' => $d['export_request_id'] ?? null,
                    'is_export_pending' => $noty->type === \App\Notifications\ExcelExportPending::class,
                    'whatsapp_archive_id' => $waArchiveId,
                    'whatsapp_import_progress' => $waProgress,
                    'whatsapp_import_phase' => $waPhase,
                    'whatsapp_import_status' => $waStatus,
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
                                    <li class="{{ $loop->first ? 'is-active' : '' }} wa-notify-item" style="position:relative; padding-right:42px;"
                                        @if(!empty($notification['export_request_id']) && !empty($notification['is_export_pending'])) data-export-request-id="{{ $notification['export_request_id'] }}" @endif
                                        @if(!empty($notification['whatsapp_archive_id']) && ($notification['whatsapp_import_status'] ?? '') === 'processing') data-whatsapp-import-archive-id="{{ $notification['whatsapp_archive_id'] }}" @endif
                                    >
                                        <span class="topbar-notify-icon">
                                            <i class="{{ $notification['icon'] ?? 'fa-regular fa-bell' }}" aria-hidden="true"></i>
                                        </span>
                                        <div>
                                            @if(!empty($targetUrl))
                                                <a href="{{ $targetUrl }}" style="text-decoration:none; color:inherit;" title="{{ $notification['file_name'] ?? ($notification['title'] ?? 'Notificación') }}">
                                                    <strong class="wa-notify-import-title" title="{{ $notification['file_name'] ?? ($notification['title'] ?? 'Notificación') }}">{{ $notification['title'] ?? 'Notificación' }}</strong>
                                                </a>
                                            @else
                                                <strong class="wa-notify-import-title" title="{{ $notification['file_name'] ?? ($notification['title'] ?? 'Notificación') }}">{{ $notification['title'] ?? 'Notificación' }}</strong>
                                            @endif
                                            @if(($notification['whatsapp_import_status'] ?? '') === 'processing')
                                                @php $waPct = min(100, max(0, (int) ($notification['whatsapp_import_progress'] ?? 0))); @endphp
                                                <div class="wa-notify-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $waPct }}">
                                                    <div class="wa-notify-progress-bar" style="width: {{ $waPct }}%;"></div>
                                                </div>
                                                @if(!empty($notification['whatsapp_import_phase']))
                                                    <small class="wa-notify-phase">{{ $notification['whatsapp_import_phase'] }}</small>
                                                @endif
                                            @endif
                                            <small class="wa-notify-time">{{ $notification['time'] ?? 'Reciente' }}</small>
                                        </div>
                                        @if(!empty($notification['id']))
                                            <form method="POST" action="{{ route('notifications.destroy', $notification['id']) }}" style="position:absolute; right:6px; top:50%; transform:translateY(-50%); margin:0; display:flex;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="topbar-notify-item-delete" title="Eliminar notificación">
                                                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @endif
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
                            title="{{ $topbarUserName }}"
                        >
                            <span class="topbar-avatar{{ $topbarAvatarUrl ? ' topbar-avatar--photo' : '' }}">
                                @if ($topbarAvatarUrl)
                                    <img src="{{ $topbarAvatarUrl }}" alt="Foto de {{ $topbarUserName }}" class="topbar-avatar-img">
                                @else
                                    {{ strtoupper(substr($topbarUserName, 0, 1)) }}
                                @endif
                            </span>
                            <div class="topbar-profile-meta">
                                <strong>{{ $topbarUserNameShort }}</strong>
                                <small>Ver mi perfil</small>
                            </div>
                            <i class="fa-solid fa-chevron-down topbar-profile-chevron" aria-hidden="true"></i>
                        </button>

                        <div class="topbar-dropdown topbar-profile-panel" id="topbarProfilePanel" role="menu" aria-hidden="true">
                            <a href="{{ route('profile.show') }}" class="topbar-menu-link {{ request()->routeIs('profile.show') ? 'is-active' : '' }}">
                                <i class="fa-regular fa-user" aria-hidden="true"></i>
                                <span>Mi perfil</span>
                            </a>
                            <a href="{{ route('settings.apariencia') }}" class="topbar-menu-link {{ request()->routeIs('settings.*') ? 'is-active' : '' }}">
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



    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Configuración global para que todos los Swal usen el mismo diseño ---
        if (typeof Swal !== 'undefined') {
            window.Swal = Swal.mixin({
                buttonsStyling: false,
                reverseButtons: true,
                iconColor: '#861e34',
                customClass: {
                    popup: 'tm-swal-popup',
                    title: 'tm-swal-title',
                    htmlContainer: 'tm-swal-text',
                    confirmButton: 'tm-swal-confirm',
                    cancelButton: 'tm-swal-cancel',
                    denyButton: 'tm-swal-deny'
                }
            });
        }

        // Objeto global de configuración Toast
        const Toast = typeof Swal !== 'undefined' ? Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        }) : null;

        function resolveSegobToastIcon(defaultIcon, message) {
            const text = String(message || '').toLowerCase();

            // Eliminar / borrar -> icono X
            if (/elimin|borr|vaciad|quitad/.test(text)) {
                return 'error';
            }

            // Actualizar / editar -> icono i
            if (/actualiz|modific|editad/.test(text)) {
                return 'info';
            }

            // Crear / registrar -> paloma de confirmación
            if (/cread|registrad|agregad|guardad/.test(text)) {
                return 'success';
            }

            return defaultIcon;
        }

        // Función para mostrar notificación emergente
        window.segobToast = function(icon, message) {
            if (Toast) {
                const mappedIcon = resolveSegobToastIcon(icon, message);
                let options = { icon: mappedIcon, title: message };

                // Para evitar desfases de geometría en toasts de éxito/error,
                // usamos un símbolo simple centrado dentro del círculo.
                if (mappedIcon === 'success') {
                    options.iconHtml = '&#10003;';
                } else if (mappedIcon === 'error') {
                    options.iconHtml = '&#10005;';
                }

                // En modo oscuro, usar color accent para el texto de todos los avisos
                if (document.documentElement.classList.contains('theme-dark')) {
                    options.color = '#ffffff';
                }
                Toast.fire(options);
            } else {
                console.warn('Swal not loaded');
            }
        };

        @if(session('toast'))
            window.segobToast('info', '{{ session('toast') }}');
        @endif
        @if(session('success') || session('status'))
            window.segobToast('success', '{{ session('success') ?: session('status') }}');
        @endif
        @if(session('error'))
            window.segobToast('error', '{{ session('error') }}');
        @endif
        @if(session('warning'))
            window.segobToast('warning', '{{ session('warning') }}');
        @endif
        @if(session('info'))
            window.segobToast('info', '{{ session('info') }}');
        @endif

        // Ocultar avisos inline clásicos para evitar duplicación con los "emergentes"
        const sessionMsg = '{{ session('success') ?: session('status') ?: session('error') ?: session('toast') ?: '' }}';
        if (sessionMsg) {
            document.querySelectorAll('.inline-alert, .app-toast').forEach(el => {
                if (el.textContent.trim().includes(sessionMsg) || sessionMsg.includes(el.textContent.trim())) {
                    el.style.display = 'none';
                }
            });
        }
    });
    </script>

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
                        <li class="wa-notify-item" style="position:relative; padding-right:42px;"
                            @if(!empty($notification['export_request_id']) && !empty($notification['is_export_pending'])) data-export-request-id="{{ $notification['export_request_id'] }}" @endif
                            @if(!empty($notification['whatsapp_archive_id']) && ($notification['whatsapp_import_status'] ?? '') === 'processing') data-whatsapp-import-archive-id="{{ $notification['whatsapp_archive_id'] }}" @endif
                        >
                            <span class="topbar-notify-icon">
                                <i class="{{ $notification['icon'] ?? 'fa-regular fa-bell' }}" aria-hidden="true"></i>
                            </span>
                            <div>
                                @if(!empty($targetUrl))
                                    <a href="{{ $targetUrl }}" style="text-decoration:none; color:inherit;" title="{{ $notification['file_name'] ?? ($notification['title'] ?? 'Notificación') }}">
                                        <strong class="wa-notify-import-title" title="{{ $notification['file_name'] ?? ($notification['title'] ?? 'Notificación') }}">{{ $notification['title'] ?? 'Notificación' }}</strong>
                                    </a>
                                @else
                                    <strong class="wa-notify-import-title" title="{{ $notification['file_name'] ?? ($notification['title'] ?? 'Notificación') }}">{{ $notification['title'] ?? 'Notificación' }}</strong>
                                @endif
                                @if(($notification['whatsapp_import_status'] ?? '') === 'processing')
                                    @php $waPctD = min(100, max(0, (int) ($notification['whatsapp_import_progress'] ?? 0))); @endphp
                                    <div class="wa-notify-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $waPctD }}">
                                        <div class="wa-notify-progress-bar" style="width: {{ $waPctD }}%;"></div>
                                    </div>
                                    @if(!empty($notification['whatsapp_import_phase']))
                                        <small class="wa-notify-phase">{{ $notification['whatsapp_import_phase'] }}</small>
                                    @endif
                                @endif
                                <small class="wa-notify-time">{{ $notification['time'] ?? 'Reciente' }}</small>
                            </div>
                            @if(!empty($notification['id']))
                                <form method="POST" action="{{ route('notifications.destroy', $notification['id']) }}" style="position:absolute; right:6px; top:50%; transform:translateY(-50%); margin:0; display:flex;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="topbar-notify-item-delete" title="Eliminar notificación">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                    </button>
                                </form>
                            @endif
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
