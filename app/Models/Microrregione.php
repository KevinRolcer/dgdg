<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Microrregione extends Model
{
    protected $table = 'microrregiones';

    protected $fillable = [
        'microrregion',
        'cabecera',
        'municipios',
        'juntas_auxiliares',
    ];

    public function municipios()
    {
        return $this->hasMany(Municipio::class, 'microrregion_id', 'id');
    }
}
