<?php

namespace App\Http\Requests\MesasPaz;

use App\Models\MesaPazAsistencia;
use Illuminate\Validation\Rule;

class StoreMesasPazRequest extends MesasPazFormRequest
{
    public function rules(): array
    {
        $municipiosPermitidos = $this->municipiosPermitidos();

        $municipioRules = ['required', 'integer', 'distinct'];
        if (!empty($municipiosPermitidos)) {
            $municipioRules[] = Rule::in($municipiosPermitidos);
        }

        return [
            'modalidad' => ['required', 'string', Rule::in($this->modalidadesPermitidas())],
            'delegado_asistio' => ['required', 'string', Rule::in(['Si', 'No', 'S/R'])],
            'parte_observacion_items' => ['nullable', 'array', 'max:100'],
            'parte_observacion_items.*' => ['nullable', 'string', 'max:500'],
            'parte_observacion' => ['nullable', 'string', 'max:5000'],
            'acuerdo_observacion_items' => ['nullable', 'array', 'max:100'],
            'acuerdo_observacion_items.*' => ['nullable', 'string', 'max:500'],
            'acuerdo_observacion' => ['nullable', 'string', 'max:5000'],
            'registros' => ['required', 'array', 'min:1'],
            'registros.*.municipio_id' => $municipioRules,
            'registros.*.presidente' => ['required', 'string', Rule::in($this->opcionesPresidentePermitidas())],
            'registros.*.representante' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $parteItems = MesaPazAsistencia::normalizeAcuerdoItems(
                $this->has('parte_observacion_items')
                    ? $this->input('parte_observacion_items', [])
                    : $this->input('parte_observacion')
            );

            $acuerdoItems = MesaPazAsistencia::normalizeAcuerdoItems(
                $this->has('acuerdo_observacion_items')
                    ? $this->input('acuerdo_observacion_items', [])
                    : $this->input('acuerdo_observacion')
            );

            if (mb_strlen(implode("\n", $parteItems)) > 5000) {
                $validator->errors()->add('parte_observacion_items', 'El total de texto en parte no debe exceder 5000 caracteres.');
            }

            if (mb_strlen(implode("\n", $acuerdoItems)) > 5000) {
                $validator->errors()->add('acuerdo_observacion_items', 'El total de texto en acuerdos no debe exceder 5000 caracteres.');
            }
        });
    }
}
