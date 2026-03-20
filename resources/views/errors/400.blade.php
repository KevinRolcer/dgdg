@php
    $httpCode = 400;
    $pageTitle = 'Solicitud no procesada';
    $headline = 'No fue posible procesar la información enviada';
    $message = 'Puede deberse a que la página permaneció abierta demasiado tiempo o a que faltó algún dato obligatorio. Le sugerimos intentar de nuevo.';
    $hints = [
        'Actualice la página (F5 o el botón de recargar) e intente nuevamente.',
        'Si completaba un formulario, verifique que todos los campos requeridos estén llenos antes de enviar.',
    ];
@endphp
@include('errors._frame')
