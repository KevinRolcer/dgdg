<?php

namespace App\Http\Requests\MesasPaz;

class ImportarExcelRequest extends MesasPazFormRequest
{
    public function rules(): array
    {
        return [
            'fecha_importacion' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'archivo_excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ];
    }
    
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'fecha_importacion.required' => 'La fecha de importación es requerida.',
            'fecha_importacion.before_or_equal' => 'No puedes importar datos de una fecha futura.',
            'archivo_excel.required' => 'El archivo Excel es obligatorio.',
            'archivo_excel.mimes' => 'El archivo debe ser tipo Excel (.xls, .xlsx).',
            'archivo_excel.max' => 'El archivo no debe pesar más de 10 MB.',
        ]);
    }
}
