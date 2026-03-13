@extends('layouts.app')

@php
    $pageTitle = 'Gestión de Usuarios';
    $pageDescription = 'Administra los usuarios Delegados y Enlaces de la plataforma.';
@endphp

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/user-management.css') }}?v={{ @filemtime(public_path('assets/css/modules/user-management.css')) ?: time() }}">
@endpush

@section('content')
<section class="tm-page">
    <article class="content-card tm-card">

        @if (session('success'))
            <div class="inline-alert inline-alert-success" role="alert">{{ session('success') }}</div>
        @endif

        {{-- Filtros y Acciones --}}
        <div class="um-filter-bar">
            {{-- Filtro por rol (preserva status actual) --}}
            <form method="GET" action="{{ route('admin.usuarios.index') }}" class="um-filter-form">
                @if($status)
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                <div class="tm-section-switch">
                    <button type="submit" name="role" value="" class="tm-section-tab {{ !$role ? 'is-active' : '' }}">Todos</button>
                    <button type="submit" name="role" value="Delegado" class="tm-section-tab {{ strtolower($role ?? '') === 'delegado' ? 'is-active' : '' }}">Delegados</button>
                    <button type="submit" name="role" value="Enlace"   class="tm-section-tab {{ strtolower($role ?? '') === 'enlace'   ? 'is-active' : '' }}">Enlaces</button>
                </div>
            </form>

            <span class="um-filter-divider"></span>

            {{-- Filtro por estado (preserva rol actual) --}}
            <form method="GET" action="{{ route('admin.usuarios.index') }}" class="um-filter-form">
                @if($role)
                    <input type="hidden" name="role" value="{{ $role }}">
                @endif
                <div class="tm-section-switch">
                    <button type="submit" name="status" value=""        class="tm-section-tab {{ !$status ? 'is-active' : '' }}">Todos</button>
                    <button type="submit" name="status" value="activo"  class="tm-section-tab {{ $status === 'activo'   ? 'is-active' : '' }}">Activos</button>
                    <button type="submit" name="status" value="inactivo" class="tm-section-tab {{ $status === 'inactivo' ? 'is-active' : '' }}">Inactivos</button>
                </div>
            </form>

            <div class="um-filter-actions">
                <a href="{{ route('admin.usuarios.create') }}" class="tm-btn tm-btn-primary">
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                    Nuevo usuario
                </a>
            </div>
        </div>

        <div class="tm-table-wrap">
            <table class="tm-table um-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Microrregiones</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr>
                        <td>
                            <div class="um-user-item">
                                <span class="um-user-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                <div class="um-user-data">
                                    <strong>{{ $user->name }}</strong>
                                    <span class="um-text-muted">{{ $user->email }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            @php $roleName = $user->roles->first()?->name ?? '—'; @endphp
                            <span class="um-role-pill um-role-{{ strtolower($roleName) }}">{{ ucfirst($roleName) }}</span>
                        </td>
                        <td>
                            @php
                                $nums = $user->microrregionesAsignadas->pluck('microrregion')->sort()->values();
                            @endphp
                            @if($nums->isNotEmpty())
                                <span class="um-micros-text">{{ $nums->implode(', ') }}</span>
                            @else
                                <span class="um-text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($user->activo ?? true)
                                <span class="tm-badge is-active">Activo</span>
                            @else
                                <span class="tm-badge is-inactive">Inactivo</span>
                            @endif
                        </td>
                        <td>
                            <div class="um-actions">
                                <a href="{{ route('admin.usuarios.edit', $user->id) }}" class="tm-btn um-btn-edit um-btn-icon" title="Editar">
                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                </a>

                                <form action="{{ route('admin.usuarios.destroy', $user->id) }}" method="POST" class="tm-inline-form" data-confirm-delete data-user-name="{{ $user->name }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="tm-btn um-btn-delete um-btn-icon" title="Eliminar">
                                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                    </button>
                                </form>

                                <form action="{{ route('admin.usuarios.toggle-status', $user->id) }}" method="POST" class="tm-inline-form">
                                    @csrf
                                    @if($user->activo ?? true)
                                        <button type="submit" class="tm-btn um-btn-status-off" title="Desactivar">
                                            <i class="fa-solid fa-ban" aria-hidden="true"></i>
                                            Desactivar
                                        </button>
                                    @else
                                        <button type="submit" class="tm-btn um-btn-status-on" title="Activar">
                                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                            Activar
                                        </button>
                                    @endif
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="um-empty">
                            <i class="fa-solid fa-users-slash" aria-hidden="true"></i>
                            <span>No se encontraron usuarios.</span>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        @if($users->lastPage() > 1)
        <div class="um-pagination-wrap">
            <p class="um-pagination-info">
                Mostrando {{ $users->firstItem() }}–{{ $users->lastItem() }} de {{ $users->total() }} usuarios
            </p>
            <div class="um-pagination">
                {{ $users->withQueryString()->links('pagination::bootstrap-5') }}
            </div>
        </div>
        @endif
    </article>
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-confirm-delete]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const name = form.getAttribute('data-user-name') || 'este usuario';
            Swal.fire({
                title: '¿Eliminar usuario?',
                text: 'Se eliminará a "' + name + '" de manera permanente.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                buttonsStyling: false,
                customClass: {
                    popup: 'tm-swal-popup',
                    title: 'tm-swal-title',
                    htmlContainer: 'tm-swal-text',
                    confirmButton: 'tm-swal-confirm',
                    cancelButton: 'tm-swal-cancel'
                }
            }).then(function (result) {
                if (result.isConfirmed) form.submit();
            });
        });
    });
});
</script>
@endpush
