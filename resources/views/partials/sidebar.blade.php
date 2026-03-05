@php
    $menuItems = config('sidebar.menu', []);
    $currentRouteName = optional(request()->route())->getName();

    $hasPermission = static function (array $item): bool {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();
        $userEmail = mb_strtolower(trim((string) ($user->email ?? '')));

        if (isset($item['hidden_if_can']) && $user->can($item['hidden_if_can'])) {
            return false;
        }

        if (!empty($item['hidden_for_emails']) && is_array($item['hidden_for_emails'])) {
            $blockedEmails = array_map(static function ($email) {
                return mb_strtolower(trim((string) $email));
            }, $item['hidden_for_emails']);

            if ($userEmail !== '' && in_array($userEmail, $blockedEmails, true)) {
                return false;
            }
        }

        if (!isset($item['permission'])) {
            return true;
        }

        return $user->can($item['permission']);
    };

    $isItemVisible = static function (array $item) use (&$isItemVisible, $hasPermission): bool {
        if (!$hasPermission($item)) {
            return false;
        }

        if (empty($item['children'])) {
            return true;
        }

        foreach ($item['children'] as $child) {
            if ($isItemVisible($child)) {
                return true;
            }
        }

        return false;
    };

    $hasActiveChild = static function (array $item) use (&$hasActiveChild, $currentRouteName): bool {
        if (!empty($item['route']) && $item['route'] === $currentRouteName) {
            return true;
        }

        foreach ($item['children'] ?? [] as $child) {
            if ($hasActiveChild($child)) {
                return true;
            }
        }

        return false;
    };
@endphp

<aside class="app-sidebar" id="appSidebar" aria-label="Menú principal">
    <div class="sidebar-header">
        <img src="{{ asset('images/logo-gobierno.png') }}" alt="Gobierno de Puebla" class="sidebar-logo">
        <img src="{{ asset('images/Gobierno de Puebla_1-Puebla.png') }}" alt="Gobierno de Puebla" class="sidebar-logo-collapsed">
    </div>

    <button type="button" class="sidebar-collapse-btn" id="sidebarCollapseToggle" aria-label="Ocultar menú lateral" aria-expanded="true">
        <i class="fa-solid fa-angles-left" aria-hidden="true"></i>
    </button>

    <nav class="sidebar-nav" aria-label="Navegación lateral">
        @foreach ($menuItems as $item)
            @continue(!$isItemVisible($item))

            @php
                $itemHasChildren = !empty($item['children']);
                $itemIsActive = $hasActiveChild($item);
                $itemRoute = !empty($item['route']) ? route($item['route']) : '#';
                $itemId = 'menu-' . md5(($item['title'] ?? 'item') . ($item['route'] ?? 'no-route'));
                $visibleChildrenCount = 0;
                if ($itemHasChildren) {
                    foreach ($item['children'] as $childItem) {
                        if ($isItemVisible($childItem)) {
                            $visibleChildrenCount++;
                        }
                    }
                }
            @endphp

            <div class="menu-group {{ $itemIsActive ? 'is-active' : '' }}">
                @if ($itemHasChildren)
                    <button
                        type="button"
                        class="menu-link menu-link-toggle"
                        data-submenu-toggle="{{ $itemId }}"
                        title="{{ $item['title'] }}"
                        aria-expanded="{{ $itemIsActive ? 'true' : 'false' }}"
                    >
                        <span class="menu-link-main">
                            <span class="menu-icon-wrap">
                                <i class="{{ $item['icon'] ?? 'fa-solid fa-circle' }} menu-icon" aria-hidden="true"></i>
                                @if ($visibleChildrenCount > 0)
                                    <span class="menu-count" aria-hidden="true">{{ $visibleChildrenCount }}</span>
                                @endif
                            </span>
                            <span class="menu-text">{{ $item['title'] }}</span>
                        </span>
                        <i class="fa-solid fa-chevron-down menu-chevron" aria-hidden="true"></i>
                    </button>

                    <div class="submenu {{ $itemIsActive ? 'is-open' : '' }}" id="{{ $itemId }}">
                        @php $submenuVisibleIndex = 0; @endphp
                        @foreach ($item['children'] as $child)
                            @continue(!$isItemVisible($child))
                            @php
                                $submenuVisibleIndex++;
                                $childRoute = !empty($child['route']) ? route($child['route']) : '#';
                                $childActive = !empty($child['route']) && $child['route'] === $currentRouteName;
                            @endphp
                            <a href="{{ $childRoute }}" class="submenu-link {{ $childActive ? 'is-active' : '' }}" title="{{ $child['title'] }}">
                                <span class="submenu-index" aria-hidden="true">{{ $submenuVisibleIndex }}</span>
                                <span class="submenu-text">{{ $child['title'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <a href="{{ $itemRoute }}" class="menu-link {{ $itemIsActive ? 'is-current' : '' }}" title="{{ $item['title'] }}">
                        <span class="menu-link-main">
                            <span class="menu-icon-wrap">
                                <i class="{{ $item['icon'] ?? 'fa-solid fa-circle' }} menu-icon" aria-hidden="true"></i>
                            </span>
                            <span class="menu-text">{{ $item['title'] }}</span>
                        </span>
                    </a>
                @endif
            </div>
        @endforeach
    </nav>

    <div class="sidebar-footer">
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="sidebar-logout-btn" title="Cerrar sesión">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                <span>Cerrar sesión</span>
            </button>
        </form>
    </div>
</aside>
