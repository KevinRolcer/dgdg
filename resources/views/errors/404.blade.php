@php
    $httpCode = 404;
    $pageTitle = 'Página no encontrada';
    $headline = 'La página solicitada no existe';
    $message = 'El enlace puede ser incorrecto, estar incompleto o el contenido ya no se encuentra disponible.';
    $hints = [
        'Verifique que la dirección esté escrita correctamente.',
        'Utilice el menú del sistema o inicie sesión nuevamente desde la página principal.',
    ];
@endphp
@include('errors._frame')
