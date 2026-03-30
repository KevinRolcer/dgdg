@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/settings.css') }}?v={{ @filemtime(public_path('assets/css/modules/settings.css')) ?: time() }}">
@endpush

@section('content')
@php
    $hidePageHeader = true;
@endphp
<div class="settings-shell app-density-compact">
    <button type="button" class="settings-shell-nav-toggle" id="settingsNavToggle" aria-label="Abrir menú de ajustes" aria-expanded="false" aria-controls="settingsShellNav">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
        <span class="settings-shell-nav-toggle-text">Ajustes</span>
    </button>
    <aside class="settings-shell-nav" id="settingsShellNav" aria-label="Secciones de Ajustes">
        <div class="settings-shell-brand">
            <span class="settings-shell-brand-title">Ajustes</span>
            <span class="settings-shell-brand-desc">Preferencias</span>
        </div>
        <nav class="settings-shell-menu">
            <a href="{{ route('settings.apariencia') }}" class="settings-shell-item @if(request()->routeIs('settings.apariencia')) is-active @endif">
                <span class="settings-shell-badge" aria-hidden="true">1</span>
                <span class="settings-shell-label">Apariencia</span>
            </a>
            @can('Modulos-Temporales-Admin')
            <a href="{{ route('settings.importacion-exportacion') }}" class="settings-shell-item @if(request()->routeIs('settings.importacion-exportacion')) is-active @endif">
                <span class="settings-shell-badge" aria-hidden="true">2</span>
                <span class="settings-shell-label">Importación y exportación</span>
            </a>
            @endcan
            @can('Chats-WhatsApp-Sensible')
            <a href="{{ route('settings.whatsapp-totp-reset') }}" class="settings-shell-item @if(request()->routeIs('settings.whatsapp-totp-reset')) is-active @endif">
                <span class="settings-shell-badge settings-shell-badge--icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
                <span class="settings-shell-label">Chats WhatsApp (autenticador)</span>
            </a>
            @endcan
        </nav>
    </aside>
    <div class="settings-shell-main">
        <header class="settings-shell-main-head">
            <h1 class="settings-shell-main-title">{{ $settingsSectionTitle ?? 'Ajustes' }}</h1>
            @if(!empty($settingsSectionDescription))
                <p class="settings-shell-main-desc">{{ $settingsSectionDescription }}</p>
            @endif
        </header>
        @yield('settings_panel')
    </div>
    <div class="settings-shell-backdrop" id="settingsShellBackdrop" aria-hidden="true"></div>
</div>

@push('scripts')
<script src="{{ asset('assets/js/modules/settings-layout.js') }}?v={{ @filemtime(public_path('assets/js/modules/settings-layout.js')) ?: time() }}"></script>
@endpush
@endsection
