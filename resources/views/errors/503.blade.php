@php
    $httpCode = 503;
    $pageTitle = 'Sistema no disponible';
    $headline = 'Ahora no podemos atenderte';
    $message = 'El sistema está en pausa por mantenimiento o está muy ocupado. En un rato debería volver a la normalidad.';
    $hints = [
        'Vuelve a intentar en unos minutos.',
    ];
@endphp
@include('errors._frame')
