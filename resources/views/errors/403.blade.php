@php
    $httpCode = 403;
    $pageTitle = 'Acceso no autorizado';
    $headline = 'No tiene permiso para acceder a este recurso';
    $message = 'Es posible que su usuario no cuente con los permisos necesarios para esta sección, o que el navegador o alguna extensión esté impidiendo el acceso (por ejemplo, bloqueadores de contenido o software de seguridad).';
    $hints = [
        'Cierre sesión si ya había ingresado e inicie sesión nuevamente con su usuario y contraseña.',
        'Pruebe en otra ventana del navegador o desactive temporalmente las extensiones para este sitio.',
        'Acceda siempre mediante la dirección oficial del sistema.',
    ];
@endphp
@include('errors._frame')
