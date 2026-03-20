@php
    $httpCode = 400;
    $pageTitle = 'No se pudo completar';
    $headline = 'Algo no cuadró al enviar la información';
    $message = 'A veces pasa si la página llevaba mucho tiempo abierta o si faltó algún dato. No es culpa tuya; solo hay que intentarlo otra vez.';
    $hints = [
        'Actualiza la página (F5 o el botón de recargar) y vuelve a intentar.',
        'Si estabas llenando un formulario, revisa que todo esté completo antes de enviar.',
    ];
@endphp
@include('errors._frame')
