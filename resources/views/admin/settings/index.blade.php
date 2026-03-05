@extends('layouts.app')

@section('content')
<section class="home-layout">
    <article class="content-card home-main-card">
        @if (session('status'))
            <div class="inline-alert inline-alert-success" role="alert">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="inline-alert inline-alert-error" role="alert">{{ session('error') }}</div>
        @endif

        <h2>Configuracion de imagenes</h2>
        <p>Desde aqui puedes migrar contenido legado de <code>public/localstorage</code> y <code>storage/app/public/temporary-modules</code> hacia la ruta privada compartida.</p>

        <div class="tm-upload-meta-row" style="margin-top: 12px;">
            <span class="tm-upload-meta-pill"><strong>Origen legacy:</strong> {{ $legacyLocalStoragePath }}</span>
            <span class="tm-upload-meta-pill"><strong>Origen módulos temporales:</strong> {{ $legacyTemporaryModulesPath }}</span>
            <span class="tm-upload-meta-pill"><strong>Destino compartido:</strong> {{ $sharedUploadsPath !== '' ? $sharedUploadsPath : 'No definido en SHARED_UPLOADS_PATH' }}</span>
        </div>

        <form method="POST" action="{{ route('admin.settings.images.migrate') }}" style="margin-top: 16px;">
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
                <div class="tm-upload-meta-row">
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
