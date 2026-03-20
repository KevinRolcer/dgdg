@php
    $httpCode = 500;
    $pageTitle = 'Fallo temporal';
    $headline = 'El sistema no pudo terminar lo que pediste';
    $message = 'Ocurrió un fallo de nuestro lado. No es por algo que hayas hecho mal en tu computadora.';
    $hints = [
        'Intenta otra vez en unos minutos.',
        'Si sigue igual, avisa a quien da soporte del sistema e indica la hora aproximada y qué estabas haciendo.',
    ];
@endphp
@include('errors._frame')
