@php
    $httpCode = 429;
    $pageTitle = 'Demasiados intentos';
    $headline = 'Hay que esperar un poco';
    $message = 'Se hicieron varios intentos muy seguidos (por ejemplo al poner la contraseña). Es normal: el sistema se detiene un momento para cuidar tu cuenta.';
    $hints = [
        'Espera uno o dos minutos y vuelve a intentar.',
        'Si no intentaste tú entrar varias veces, cuando puedas entrar conviene cambiar la contraseña.',
    ];
@endphp
@include('errors._frame')
