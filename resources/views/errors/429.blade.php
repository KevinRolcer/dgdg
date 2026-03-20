@php
    $httpCode = 429;
    $pageTitle = 'Límite de intentos';
    $headline = 'Debe esperar antes de continuar';
    $message = 'Se registraron varios intentos consecutivos en poco tiempo (por ejemplo, al ingresar la contraseña). El sistema aplica una pausa temporal para proteger su cuenta.';
    $hints = [
        'Espere uno o dos minutos e intente nuevamente.',
        'Si usted no realizó esos intentos, una vez que recupere el acceso le recomendamos cambiar su contraseña.',
    ];
@endphp
@include('errors._frame')
