<?php

namespace App\Http\Requests\MesasPaz;

use App\Models\MesaPazAsistencia;
use Illuminate\Validation\Rule;

class GuardarMunicipioRequest extends MesasPazFormRequest
{
    public function rules(): array
    {
        $municipiosPermitidos = $this->municipiosPermitidos();

        $municipioRules = ['required', 'integer'];
        if (!empty($municipiosPermitidos)) {
            $municipioRules[] = Rule::in($municipiosPermitidos);
        }

        return [
            'modalidad' => ['required', 'string', Rule::in($this->modalidadesPermitidas())],
            'delegado_asistio' => ['required', 'string', Rule::in(MesaPazAsistencia::DELEGADO_ASISTIO_VALUES)],
            'municipio_id' => $municipioRules,
            'presidente' => ['required', 'string', Rule::in($this->opcionesPresidentePermitidas())],
            'representante' => ['nullable', 'string', 'max:160'],
            'fecha_asist' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }
}
