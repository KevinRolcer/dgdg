<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Municipio extends Model
{
    protected $table = 'municipios';

    protected $fillable = [
        'cve_inegi',
        'cve_edo',
        'municipio',
        'df',
        'dl',
        'region',
        'micro_region',
        'fil',
        'www',
        'presidencia_domicilio',
        'presidencia_telefono',
        'presidente_nombre',
        'presidente_ap_paterno',
        'presidente_ap_materno',
        'foto_presidente',
        'telefono_presidente',
        'email_presidente',
        'filiacion',
        'glifo',
        'toponimia',
        'agenero',
        'prioridad',
        'secciones',
        'padron',
        'padron_h',
        'padron_m',
        'ln',
        'ln_h',
        'ln_m',
        'metapj',
        'dj',
    ];

    public function microrregion()
    {
        return $this->belongsTo(Microrregione::class, 'microrregion_id', 'id');
    }
}
