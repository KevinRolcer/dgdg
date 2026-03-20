@php
    $httpCode = 500;
    $pageTitle = 'Error del servidor';
    $headline = 'El sistema no pudo completar la operación';
    $message = 'Se produjo un error en el servidor. No se trata de un fallo en su equipo.';
    $hints = [
        'Intente nuevamente en unos minutos.',
        'Si el problema persiste, comuníquese con el área de soporte técnico e indique la hora aproximada y la acción que realizaba.',
    ];
@endphp
@include('errors._frame')
