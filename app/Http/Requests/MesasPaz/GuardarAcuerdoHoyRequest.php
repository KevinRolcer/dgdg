<?php

namespace App\Http\Requests\MesasPaz;

use App\Models\MesaPazAsistencia;
use Illuminate\Validation\Rule;

class GuardarAcuerdoHoyRequest extends MesasPazFormRequest
{
    public function rules(): array
    {
        return [
            'parte_observacion_items' => ['nullable', 'array', 'max:100'],
            'parte_observacion_items.*' => ['nullable', 'string', 'max:500'],
            'parte_observacion' => ['nullable', 'string', 'max:5000'],
            'acuerdo_observacion_items' => ['nullable', 'array', 'max:100'],
            'acuerdo_observacion_items.*' => ['nullable', 'string', 'max:500'],
            'acuerdo_observacion' => ['nullable', 'string', 'max:5000'],
            'modalidad' => ['nullable', 'string', Rule::in($this->modalidadesPermitidas())],
            'delegado_asistio' => ['nullable', 'string', Rule::in(MesaPazAsistencia::DELEGADO_ASISTIO_VALUES)],
            'fecha_asist' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $modalidad = trim((string) $this->input('modalidad', ''));
            $esSuspension = in_array($modalidad, ['Suspención de mesa de Seguridad', 'Suspención de la Mesa de Seguridad'], true);

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

            if (!$esSuspension && mb_strlen(implode("\n", $parteItems)) > 5000) {
                $validator->errors()->add('parte_observacion_items', 'El total de texto en parte no debe exceder 5000 caracteres.');
            }

            if (mb_strlen(implode("\n", $acuerdoItems)) > 5000) {
                $validator->errors()->add('acuerdo_observacion_items', 'El total de texto en acuerdos no debe exceder 5000 caracteres.');
            }
        });
    }
}
