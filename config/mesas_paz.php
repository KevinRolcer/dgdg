<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Meta de mesas de seguridad (presentación / cumplimiento)
    |--------------------------------------------------------------------------
    |
    | Por defecto 217 municipios × dias_laborales_semana (5) = 1085 mesas/semana.
    | Si define MESAS_PAZ_TOTAL_MUNICIPIOS=0, se usa el conteo de la tabla municipios.
    |
    */
    'total_municipios' => is_numeric(env('MESAS_PAZ_TOTAL_MUNICIPIOS'))
        ? (int) env('MESAS_PAZ_TOTAL_MUNICIPIOS')
        : 217,

    'dias_laborales_semana' => (int) env('MESAS_PAZ_DIAS_LABORALES', 5),

    /*
    |--------------------------------------------------------------------------
    | Vista previa PPTX (Evidencias / presentación)
    |--------------------------------------------------------------------------
    |
    | Si es true y la URL de la app es HTTPS accesible públicamente (no localhost),
    | la página de vista previa usa el visor embebido de Microsoft Office Online.
    | En http://127.0.0.1 o dominios .test suele fallar: use descarga o abrir enlace.
    |
    */
    'ppt_office_online_embed' => filter_var(env('MESAS_PAZ_PPT_OFFICE_EMBED', true), FILTER_VALIDATE_BOOLEAN),

];
