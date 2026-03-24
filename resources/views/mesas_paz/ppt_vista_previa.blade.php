@extends('layouts.app')

@section('title', 'Vista previa — Presentación Mesas de Paz')

@push('css')
<link href="{{ asset('assets/css/mesas_paz/mesas-paz-shell.css') }}?v={{ @filemtime(public_path('assets/css/mesas_paz/mesas-paz-shell.css')) ?: time() }}" rel="stylesheet" />
<link href="{{ asset('assets/css/mesas_paz/mesaPazSupervision.css') }}?v={{ @filemtime(public_path('assets/css/mesas_paz/mesaPazSupervision.css')) ?: time() }}" rel="stylesheet" />
<link href="{{ asset('assets/css/theme-dark-mesas-paz.css') }}?v={{ @filemtime(public_path('assets/css/theme-dark-mesas-paz.css')) ?: time() }}" rel="stylesheet" />
@endpush

@section('content')
@php
    $hidePageHeader = true;
@endphp
<div class="mesas-paz-shell app-density-compact">
    <div class="mesas-paz-shell-main">
        <header class="mesas-paz-shell-head d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h1 class="mesas-paz-shell-title mb-1">Vista previa de la presentación</h1>
                <p class="mesas-paz-shell-desc mb-0">Revise el contenido antes de descargar. El enlace del visor en línea caduca en unos minutos.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ $evidenciasUrl }}" class="btn btn-outline-secondary btn-sm">Volver a evidencias</a>
                <a href="{{ $downloadUrl }}" class="btn btn-primary btn-sm">Descargar .pptx</a>
            </div>
        </header>

        @if ($officeEmbedUrl)
            <div class="border rounded overflow-hidden bg-white shadow-sm" style="min-height: 75vh;">
                <iframe
                    title="Vista previa PowerPoint"
                    src="{{ $officeEmbedUrl }}"
                    class="w-100 border-0 d-block ppt-vista-previa-iframe"
                    style="min-height: 75vh;"
                    allowfullscreen
                ></iframe>
            </div>
        @else
            <div class="alert alert-info">
                <p class="mb-2">
                    El visor de Microsoft en el navegador solo funciona si la aplicación usa <strong>HTTPS</strong> y una URL que Microsoft pueda alcanzar desde internet (no suele funcionar en <code>http://127.0.0.1</code> ni en dominios <code>.test</code> / <code>.local</code>).
                </p>
                <p class="mb-2">Puede abrir el archivo en una pestaña nueva (según el navegador puede iniciar la descarga o abrir PowerPoint) o usar <strong>Descargar .pptx</strong>.</p>
                <a href="{{ $signedFileUrl }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener noreferrer">Abrir archivo de vista previa</a>
            </div>
        @endif
    </div>
</div>
@endsection
