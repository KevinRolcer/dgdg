@php
    $httpCode = 403;
    $pageTitle = 'No tienes acceso aquí';
    $headline = 'No pudimos dejarte pasar';
    $message = 'Puede ser que tu usuario no tenga permiso para esta sección, o que el navegador esté bloqueando algo necesario para entrar (por ejemplo extensiones que bloquean anuncios o programas de seguridad).';
    $hints = [
        'Sal del sistema si ya habías entrado, y entra otra vez con tu usuario y contraseña.',
        'Prueba otra ventana del navegador o desactiva por un momento las extensiones en este sitio.',
        'Entra siempre por la página web oficial del sistema.',
    ];
@endphp
@include('errors._frame')
