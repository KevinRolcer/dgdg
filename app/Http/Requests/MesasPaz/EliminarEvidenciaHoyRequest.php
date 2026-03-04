<?php

namespace App\Http\Requests\MesasPaz;

class EliminarEvidenciaHoyRequest extends MesasPazFormRequest
{
    public function rules(): array
    {
        return [
            'evidencia_path' => ['required', 'string', 'max:1000'],
        ];
    }
}
