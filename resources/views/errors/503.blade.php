@php
    $httpCode = 503;
    $pageTitle = 'Sistema no disponible';
    $headline = 'Servicio temporalmente no disponible';
    $message = 'El sistema se encuentra en mantenimiento o con alta demanda. En breve debería restablecerse el servicio con normalidad.';
    $hints = [
        'Intente nuevamente en unos minutos.',
    ];
@endphp
@include('errors._frame')
