<?php

namespace App\Http\Requests\MesasPaz;

class DetallePorFechaRequest extends MesasPazFormRequest
{
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
        ];
    }
}
