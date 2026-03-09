<?php

namespace App\Http\Requests\MesasPaz;

class EliminarEvidenciaHoyRequest extends MesasPazFormRequest
{
    public function rules(): array
    {
        return [
            'evidencia_path' => ['required', 'string', 'max:1000'],
            'microrregion_id' => ['nullable', 'integer'],
            'fecha_asist' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }
}
