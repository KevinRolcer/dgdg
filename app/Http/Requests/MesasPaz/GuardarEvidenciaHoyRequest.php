<?php

namespace App\Http\Requests\MesasPaz;

class GuardarEvidenciaHoyRequest extends MesasPazFormRequest
{
    public function rules(): array
    {
        return [
            'evidencia' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'microrregion_id' => ['nullable', 'integer'],
            'fecha_asist' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }
}
