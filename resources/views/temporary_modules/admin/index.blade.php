@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
@endpush

@section('content')
<section class="tm-page">
    <article class="content-card tm-card">
        @if (session('status'))
            <div class="inline-alert inline-alert-success" role="alert">{{ session('status') }}</div>
        @endif

        <div class="tm-head">
            <div>
                <p>Gestiona módulos creados: editar, eliminar y ajustar vigencia/alcance.</p>
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
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const deleteForms = Array.from(document.querySelectorAll('form[data-confirm-delete]'));
        
        deleteForms.forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                const moduleName = form.getAttribute('data-module-name') || 'este módulo';
                
                Swal.fire({
                    title: '¿Eliminar módulo?',
                    text: '¿Estás seguro de eliminar el módulo "' + moduleName + '"? Los registros capturados se conservarán.',
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
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    });
</script>
@endpush
