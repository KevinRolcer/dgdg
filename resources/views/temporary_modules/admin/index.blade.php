@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
@endpush

@php
    $hidePageHeader = true;
@endphp

@section('content')
<section class="tm-page tm-shell app-density-compact">
    <div class="tm-shell-main">
        <header class="tm-shell-head">
            <h1 class="tm-shell-title">Eventos temporales</h1>
            <p class="tm-shell-desc">Administración de módulos: crear, editar, vigencia y exportación de registros.</p>
        </header>

        <article class="content-card tm-card tm-card-in-shell">
        @if (session('status'))
            <div class="inline-alert inline-alert-success" role="alert">{{ session('status') }}</div>
        @endif

        <div class="tm-head">
            <div>
                <p class="tm-head-desc-only">Gestiona módulos creados: editar, eliminar y ajustar vigencia/alcance.</p>
            </div>
            <div class="tm-inline-actions">
                <a href="{{ route('temporary-modules.admin.records') }}" class="tm-btn">Ver registros</a>
                <a href="{{ route('temporary-modules.admin.create-from-excel') }}" class="tm-btn">Módulo desde Excel (base)</a>
                <a href="{{ route('temporary-modules.admin.create') }}" class="tm-btn tm-btn-primary">Nuevo módulo</a>
            </div>
        </div>

        <div class="tm-table-wrap">
            <table class="tm-table">
                <thead>
                    <tr>
                        <th>Módulo</th>
                        <th>Vence</th>
                        <th>Alcance</th>
                        <th>Campos</th>
                        <th>Registros</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($modules as $module)
                        <tr>
                            <td>
                                <strong>{{ $module->name }}</strong>
                                @if (!empty($module->description))
                                    <small>{{ $module->description }}</small>
                                @endif
                            </td>
                            <td>{{ optional($module->expires_at)->format('d/m/Y H:i') ?? 'Sin límite' }}</td>
                            <td>
                                @if ($module->applies_to_all)
                                    Todos los delegados
                                @else
                                    {{ $module->target_users_count }} delegado(s)
                                @endif
                            </td>
                            <td>{{ $module->fields_count }}</td>
                            <td>{{ $module->entries_count }}</td>
                            <td>
                                <span class="tm-badge {{ $module->isAvailable() ? 'is-active' : 'is-inactive' }}">
                                    {{ $module->isAvailable() ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('temporary-modules.admin.edit', $module->id) }}" class="tm-btn">Editar</a>
                                <form method="POST" action="{{ route('temporary-modules.admin.destroy', $module->id) }}" class="tm-inline-form" data-confirm-delete data-module-name="{{ $module->name }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="tm-btn tm-btn-danger">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">Aún no existen módulos temporales.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </article>
    </div>
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('assets/js/modules/temporary-modules-admin-index.js') }}?v={{ @filemtime(public_path('assets/js/modules/temporary-modules-admin-index.js')) ?: time() }}"></script>
@endpush
