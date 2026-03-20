@php
    $httpCode = 404;
    $pageTitle = 'Página no encontrada';
    $headline = 'Esta página no existe';
    $message = 'El enlace puede estar equivocado, incompleto o la página ya no está disponible.';
    $hints = [
        'Revisa que la dirección esté bien escrita.',
        'Usa el menú del sistema o vuelve a entrar desde el inicio.',
    ];
@endphp
@include('errors._frame')
