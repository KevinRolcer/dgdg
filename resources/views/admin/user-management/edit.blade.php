@extends('layouts.app')

@php
    $pageTitle = 'Editar Usuario';
    $pageDescription = 'Modifica los datos del usuario seleccionado.';
    $userRole = strtolower($user->roles->first()?->name ?? '');
    $delegado = $user->delegado;
@endphp

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/user-management.css') }}?v={{ @filemtime(public_path('assets/css/modules/user-management.css')) ?: time() }}">
@endpush

@section('content')
<section class="tm-page">
    <article class="content-card tm-card">

        <div class="tm-head">
            <div><p>Modifica los datos de <strong>{{ $user->name }}</strong>.</p></div>
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

        <form method="POST" action="{{ route('admin.usuarios.update', $user->id) }}" class="tm-form">
            @csrf

            {{-- ===== Datos de cuenta ===== --}}
            <div class="um-section-title"><i class="fa-solid fa-user-circle"></i> Datos de cuenta</div>

            <div class="tm-grid um-grid-3">
                <label for="name">
                    Nombre completo <span class="um-required">*</span>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                </label>

                <label for="email">
                    Correo electrónico <span class="um-required">*</span>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                </label>

                <label for="telefono">
                    Teléfono
                    <input type="text" id="telefono" name="telefono" value="{{ old('telefono', $user->telefono ?? '') }}">
                </label>

                <label for="password">
                    Nueva contraseña
                    <input type="password" id="password" name="password" minlength="8" placeholder="Dejar vacío para no cambiar">
                </label>

                <label for="password_confirmation">
                    Confirmar nueva contraseña
                    <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" placeholder="Repite la nueva contraseña">
                </label>

                <div class="um-role-activo-row">
                    <label for="role">
                        Rol <span class="um-required">*</span>
                        <select id="role" name="role" required onchange="onRoleChange(this.value)">
                            <option value="Delegado" {{ $userRole === 'delegado' ? 'selected' : '' }}>Delegado</option>
                            <option value="Enlace"   {{ $userRole === 'enlace'   ? 'selected' : '' }}>Enlace</option>
                        </select>
                    </label>

                    <label class="um-check-label" style="align-self:flex-end;padding-bottom:4px;">
                        <input type="checkbox" name="activo" value="1" {{ old('activo', $user->activo) ? 'checked' : '' }}>
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
                            @php $sel = old('microrregion_id', $user->microrregionesAsignadas->first()?->id) == $mr->id ? 'selected' : ''; @endphp
                            <option value="{{ $mr->id }}" {{ $sel }}>MR {{ $mr->microrregion }} – {{ $mr->cabecera }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            {{-- ===== Enlace: varias microrregiones ===== --}}
            <div id="section-enlace" class="um-conditional-section" style="display:none;">
                <div class="um-section-title"><i class="fa-solid fa-map-location-dot"></i> Microrregiones asignadas</div>
                @php $assignedIds = old('microrregion_ids', $user->microrregionesAsignadas->pluck('id')->toArray()); @endphp
                <label for="microrregion_ids">
                    Microrregiones <span class="um-required">*</span>
                    <small style="font-weight:400;color:var(--clr-text-light);">(Ctrl para seleccionar varias)</small>
                    <select id="microrregion_ids" name="microrregion_ids[]" multiple style="min-height:110px;">
                        @foreach($microrregiones as $mr)
                            <option value="{{ $mr->id }}" {{ in_array($mr->id, (array)$assignedIds) ? 'selected' : '' }}>
                                MR {{ $mr->microrregion }} – {{ $mr->cabecera }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="tm-actions" style="gap:8px;">
                <a href="{{ route('admin.usuarios.index') }}" class="tm-btn">Cancelar</a>
                <button type="submit" class="tm-btn tm-btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Actualizar usuario
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
    onRoleChange(document.getElementById('role').value);
});
</script>
@endpush
