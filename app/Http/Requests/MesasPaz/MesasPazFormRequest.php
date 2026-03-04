<?php

namespace App\Http\Requests\MesasPaz;

use App\Models\Delegado;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

abstract class MesasPazFormRequest extends FormRequest
{
    private ?Delegado $delegadoCache = null;

    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->can('Mesas-Paz');
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422));
    }

    protected function delegadoActual(): ?Delegado
    {
        if ($this->delegadoCache !== null) {
            return $this->delegadoCache;
        }

        $this->delegadoCache = Delegado::with('microrregion')
            ->where('user_id', Auth::id())
            ->first();

        return $this->delegadoCache;
    }

    protected function municipiosPermitidos(): array
    {
        $delegado = $this->delegadoActual();

        if (!$delegado || !$delegado->microrregion_id || !$delegado->microrregion) {
            return [];
        }

        return $delegado->microrregion->municipios()
            ->where('microrregion_id', $delegado->microrregion_id)
            ->pluck('municipios.id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->values()
            ->all();
    }

    protected function mapPresidenteForStorage(?string $presidente): ?string
    {
        $valor = trim((string) $presidente);

        if (in_array($valor, ['Presidente', 'Si'], true)) {
            return 'Si';
        }

        if (in_array($valor, ['Ninguno', 'No'], true)) {
            return 'No';
        }

        return $valor === '' ? null : $valor;
    }

    protected function modalidadesPermitidas(): array
    {
        return [
            'Virtual',
            'Presencial',
            'Sin reporte de Delegado',
            'Sin información de enlace',
            'Suspención de mesa de Seguridad',
            'Suspención de la Mesa de Seguridad',
        ];
    }

    protected function opcionesPresidentePermitidas(): array
    {
        return ['Presidente', 'Si', 'No', 'Ninguno', 'Representante', 'Ambos', 'S/R'];
    }
}
