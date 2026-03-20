@php
    $httpCode = 419;
    $pageTitle = 'Sesión de página expirada';
    $headline = 'La ventana permaneció abierta demasiado tiempo';
    $message = 'Por motivos de seguridad, el sistema requiere volver a cargar la página tras un periodo prolongado. También puede ocurrir si el navegador no conserva correctamente la información del sitio.';
    $hints = [
        'Recargue la página y complete o envíe nuevamente el formulario.',
        'Permita que este sitio utilice cookies o almacenamiento local; evite modos de navegación excesivamente restrictivos para esta página.',
    ];
@endphp
@include('errors._frame')
