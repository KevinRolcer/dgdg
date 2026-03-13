@extends('layouts.app')

@php
    $pageTitle = 'Nuevo Usuario';
    $pageDescription = 'Crea un nuevo usuario Delegado o Enlace.';
@endphp

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/user-management.css') }}?v={{ @filemtime(public_path('assets/css/modules/user-management.css')) ?: time() }}">
@endpush

@section('content')
<section class="tm-page">
    <article class="content-card tm-card">

        <div class="tm-head">
            <div><p>Completa los datos para crear un nuevo usuario.</p></div>
            <div class="tm-inline-actions">
                <a href="{{ route('admin.usuarios.index') }}" class="tm-btn">
                    <i class="fa-solid fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>

        @if ($errors->any())
            <div class="inline-alert inline-alert-error" role="alert">
                <ul style="margin:0;padding-left:18px;">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.usuarios.store') }}" class="tm-form">
            @csrf

            {{-- ===== Datos de cuenta ===== --}}
            <div class="um-section-title"><i class="fa-solid fa-user-circle"></i> Datos de cuenta</div>

            <div class="tm-grid um-grid-3">
                <label for="name">
                    Nombre completo <span class="um-required">*</span>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required placeholder="Ej. Juan Pérez López">
                </label>

                <label for="email">
                    Correo electrónico <span class="um-required">*</span>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="correo@ejemplo.com">
                </label>

                <label for="telefono">
                    Teléfono
                    <input type="text" id="telefono" name="telefono" value="{{ old('telefono') }}" placeholder="Ej. 2221234567">
                </label>

                <label for="password">
                    Contraseña <span class="um-required">*</span>
                    <input type="password" id="password" name="password" required minlength="8" placeholder="Mínimo 8 caracteres">
                </label>

                <label for="password_confirmation">
                    Confirmar contraseña <span class="um-required">*</span>
                    <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" placeholder="Repite la contraseña">
                </label>

                <div class="um-role-activo-row">
                    <label for="role">
                        Rol <span class="um-required">*</span>
                        <select id="role" name="role" required onchange="onRoleChange(this.value)">
                            <option value="">Selecciona…</option>
                            <option value="Delegado" {{ old('role') === 'Delegado' ? 'selected' : '' }}>Delegado</option>
                            <option value="Enlace"   {{ old('role') === 'Enlace'   ? 'selected' : '' }}>Enlace</option>
                        </select>
                    </label>

                    <label class="um-check-label" style="align-self:flex-end;padding-bottom:4px;">
                        <input type="checkbox" name="activo" value="1" {{ old('activo', '1') ? 'checked' : '' }}>
                        Usuario activo
                    </label>
                </div>
            </div>

            {{-- ===== Delegado: solo microrregión ===== --}}
            <div id="section-delegado" class="um-conditional-section" style="display:none;">
                <div class="um-section-title"><i class="fa-solid fa-map-location-dot"></i> Microrregión asignada</div>
                <label for="microrregion_id">
                    Microrregión <span class="um-required">*</span>
                    <select id="microrregion_id" name="microrregion_id">
                        <option value="">— Selecciona —</option>
                        @foreach($microrregiones as $mr)
                            <option value="{{ $mr->id }}" {{ old('microrregion_id') == $mr->id ? 'selected' : '' }}>
                                MR {{ $mr->microrregion }} – {{ $mr->cabecera }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            {{-- ===== Enlace: varias microrregiones ===== --}}
            <div id="section-enlace" class="um-conditional-section" style="display:none;">
                <div class="um-section-title"><i class="fa-solid fa-map-location-dot"></i> Microrregiones asignadas</div>
                <label for="microrregion_ids">
                    Microrregiones <span class="um-required">*</span>
                    <small style="font-weight:400;color:var(--clr-text-light);">(Ctrl para seleccionar varias)</small>
                    <select id="microrregion_ids" name="microrregion_ids[]" multiple style="min-height:110px;">
                        @foreach($microrregiones as $mr)
                            <option value="{{ $mr->id }}" {{ in_array($mr->id, (array) old('microrregion_ids', [])) ? 'selected' : '' }}>
                                MR {{ $mr->microrregion }} – {{ $mr->cabecera }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="tm-actions" style="gap:8px;">
                <a href="{{ route('admin.usuarios.index') }}" class="tm-btn">Cancelar</a>
                <button type="submit" class="tm-btn tm-btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar usuario
                </button>
            </div>
        </form>
    </article>
</section>
@endsection

@push('scripts')
<script>
function onRoleChange(role) {
    const sd   = document.getElementById('section-delegado');
    const se   = document.getElementById('section-enlace');
    const mrD  = document.getElementById('microrregion_id');
    const mrE  = document.getElementById('microrregion_ids');

    if (role === 'Delegado' || role === 'delegado') {
        sd.style.display = 'block'; se.style.display = 'none';
        if (mrD) mrD.required = true;
        if (mrE) mrE.required = false;
    } else if (role === 'Enlace' || role === 'enlace') {
        sd.style.display = 'none'; se.style.display = 'block';
        if (mrD) mrD.required = false;
        if (mrE) mrE.required = true;
    } else {
        sd.style.display = 'none'; se.style.display = 'none';
    }
}
document.addEventListener('DOMContentLoaded', () => {
    const old = '{{ old("role") }}';
    if (old) onRoleChange(old);
});
</script>
@endpush
