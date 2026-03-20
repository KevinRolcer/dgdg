@php
    $httpCode = 419;
    $pageTitle = 'Página caducada';
    $headline = 'Esta ventana estuvo abierta demasiado tiempo';
    $message = 'Por seguridad, el sistema pide volver a cargar la página cuando pasó mucho rato. También puede pasar si el navegador no guarda bien la información del sitio.';
    $hints = [
        'Recarga la página y vuelve a llenar o enviar el formulario.',
        'Permite que este sitio use cookies o almacenamiento (no uses un modo muy restrictivo solo para esta página).',
    ];
@endphp
@include('errors._frame')
