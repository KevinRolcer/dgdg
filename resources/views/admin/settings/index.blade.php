@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/admin-settings.css') }}?v={{ @filemtime(public_path('assets/css/modules/admin-settings.css')) ?: time() }}">
@endpush

@section('content')
<section class="home-layout admin-settings-wrap">
    <article class="content-card home-main-card admin-settings-card">
        @if (session('status'))
            <div class="inline-alert inline-alert-success" role="alert">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="inline-alert inline-alert-error" role="alert">{{ session('error') }}</div>
        @endif

        @php
            $hasTmPdfPassword = \App\Models\TemporaryModuleSecuritySetting::pdfPassword() !== null;
        @endphp
        <h2>Seguridad de PDF</h2>
        <p>Configura la contraseña requerida para abrir los PDF exportados desde módulos temporales.</p>

        <form method="POST" action="{{ route('settings.temporary-modules.pdf-password') }}" style="margin-top: 16px; display:grid; gap:12px; max-width:520px;">
            @csrf
            <label style="display:grid; gap:6px;">
                <span>Nueva contraseña</span>
                <input type="password" name="pdf_password" class="tm-input" autocomplete="new-password" placeholder="{{ $hasTmPdfPassword ? 'Hay una contraseña configurada' : 'Sin contraseña configurada' }}">
            </label>
            <label style="display:grid; gap:6px;">
                <span>Confirmar contraseña</span>
                <input type="password" name="pdf_password_confirmation" class="tm-input" autocomplete="new-password">
            </label>
            <label style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" name="clear_pdf_password" value="1">
                <span>Eliminar contraseña actual</span>
            </label>
            @error('pdf_password')
                <div class="inline-alert inline-alert-error" role="alert">{{ $message }}</div>
            @enderror
            <button type="submit" class="tm-btn tm-btn-primary">Guardar contraseña PDF</button>
        </form>

        <hr style="margin:28px 0;">

        <h2>Configuracion de imagenes</h2>
        <p>Esta herramienta migra imagenes de Localstorage hacia una ruta privada compartida.</p>

        <form method="POST" action="{{ route('settings.images.migrate') }}" style="margin-top: 16px;">
            @csrf

            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                <input type="checkbox" name="delete_originals" value="1">
                <span>Eliminar archivos originales despues de copiar (recomendado solo despues de validar).</span>
            </label>

            <button type="submit" class="tm-btn tm-btn-primary">Migrar imagenes a ruta privada</button>
        </form>

        @if (is_array($migrationReport ?? null))
            <div style="margin-top: 18px;">
                <h3 style="margin-bottom: 8px;">Resultado de ultima migracion</h3>
                <div class="admin-settings-summary">
                    <span class="tm-upload-meta-pill"><strong>Inicio:</strong> {{ $migrationReport['started_at'] ?? '-' }}</span>
                    <span class="tm-upload-meta-pill"><strong>Fin:</strong> {{ $migrationReport['finished_at'] ?? '-' }}</span>
                    <span class="tm-upload-meta-pill"><strong>Copiados:</strong> {{ $migrationReport['files_copied'] ?? 0 }}</span>
                    <span class="tm-upload-meta-pill"><strong>Omitidos:</strong> {{ $migrationReport['files_skipped_existing'] ?? 0 }}</span>
                    <span class="tm-upload-meta-pill"><strong>Eliminados origen:</strong> {{ $migrationReport['files_deleted_original'] ?? 0 }}</span>
                    <span class="tm-upload-meta-pill"><strong>Errores:</strong> {{ $migrationReport['files_failed'] ?? 0 }}</span>
                    <span class="tm-upload-meta-pill"><strong>Registros Mesas revisados:</strong> {{ $migrationReport['mesas_records_scanned'] ?? 0 }}</span>
                    <span class="tm-upload-meta-pill"><strong>Registros Mesas actualizados:</strong> {{ $migrationReport['mesas_records_updated'] ?? 0 }}</span>
                    <span class="tm-upload-meta-pill"><strong>Archivos Módulos revisados:</strong> {{ $migrationReport['temporary_modules_files_scanned'] ?? 0 }}</span>
                    <span class="tm-upload-meta-pill"><strong>Archivos Módulos migrados:</strong> {{ $migrationReport['temporary_modules_files_migrated'] ?? 0 }}</span>
                </div>

                @php
                    $errors = $migrationReport['errors'] ?? [];
                @endphp
                @if (is_array($errors) && count($errors) > 0)
                    <div class="inline-alert inline-alert-error" role="alert" style="margin-top: 12px;">
                        <strong>Se detectaron errores:</strong>
                        <ul style="margin: 8px 0 0 18px;">
                            @foreach (array_slice($errors, 0, 20) as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    </article>
</section>
@endsection
