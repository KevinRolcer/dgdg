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
<div class="mesas-paz-shell app-density-compact d-flex flex-column min-vh-100">
    <div class="mesas-paz-shell-main grow d-flex flex-column">
        <header class="mesas-paz-shell-head d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h1 class="mesas-paz-shell-title mb-1">Vista previa de la presentación</h1>
                <p class="mesas-paz-shell-desc mb-0">Revise el contenido antes de descargar. El enlace del visor en línea caduca en unos minutos.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ $evidenciasUrl }}" class="btn btn-outline-secondary btn-sm">Volver a evidencias</a>
                @if (!empty($downloadPdfUrl))
                    <a href="{{ $downloadPdfUrl }}" class="btn btn-outline-secondary btn-sm">Descargar .pdf</a>
                @endif
                <a href="{{ $downloadUrl }}" class="btn btn-primary btn-sm">Descargar .pptx</a>
            </div>
        </header>

        @if (!empty($signedPdfUrl))
            <div class="border rounded overflow-hidden bg-white shadow-sm ppt-vista-previa-frame grow d-flex flex-column" style="min-height: calc(100vh - 11rem);">
                <iframe
                    title="Vista previa PDF"
                    src="{{ $signedPdfUrl }}"
                    class="w-100 border-0 d-block grow ppt-vista-previa-iframe"
                    style="min-height: min(85vh, 920px); height: calc(100vh - 11rem);"
                    allowfullscreen
                ></iframe>
            </div>
        @elseif ($officeEmbedUrl)
            <div class="border rounded overflow-hidden bg-white shadow-sm ppt-vista-previa-frame grow d-flex flex-column" style="min-height: calc(100vh - 11rem);">
                <iframe
                    title="Vista previa PowerPoint"
                    src="{{ $officeEmbedUrl }}"
                    class="w-100 border-0 d-block grow ppt-vista-previa-iframe"
                    style="min-height: min(85vh, 920px); height: calc(100vh - 11rem);"
                    allowfullscreen
                ></iframe>
            </div>
        @else
            <div class="alert alert-info">
                <p class="mb-2">
                    No fue posible cargar la vista previa embebida. Puede abrir el archivo en una pestaña nueva o descargarlo directamente.
                </p>
                <p class="mb-2">Si requiere revisión página por página en navegador, use <strong>Descargar .pdf</strong> cuando esté disponible.</p>
                @if (!empty($signedPdfUrl))
                    <a href="{{ $signedPdfUrl }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener noreferrer">Abrir PDF de vista previa</a>
                @else
                    <a href="{{ $signedFileUrl }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener noreferrer">Abrir archivo de vista previa</a>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
