<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Microrregione extends Model
{
    /** Tabla: microrregiones (id, microrregion, cabecera, municipios [contador], juntas_auxiliares, timestamps). */
    protected $table = 'microrregiones';

    protected $fillable = [
        'microrregion',
        'cabecera',
        'municipios',
        'juntas_auxiliares',
    ];

    /** Municipios que pertenecen a esta microrregión (la columna "municipios" en la tabla es un contador entero). */
    public function municipios()
    {
        return $this->hasMany(Municipio::class, 'microrregion_id', 'id');
    }

    /** Usuarios asignados a esta microrregión (pivot user_microrregion: user_id, microrregion_id). */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_microrregion', 'microrregion_id', 'user_id');
    }

    /** Delegados asignados a esta microrregión. */
    public function delegados()
    {
        return $this->hasMany(Delegado::class, 'microrregion_id', 'id');
    }

    /** Asistencias de Mesas de Paz en esta microrregión. */
    public function mesasPazAsistencias()
    {
        return $this->hasMany(MesaPazAsistencia::class, 'microrregion_id', 'id');
    }

    /** Registros de módulos temporales con esta microrregión. */
    public function temporaryModuleEntries()
    {
        return $this->hasMany(TemporaryModuleEntry::class, 'microrregion_id', 'id');
    }

    /**
     * Si la relación municipios está cargada, devuelve la colección; si no, el valor de la columna (contador).
     * Evita que la columna "municipios" (entero) pise la relación en Blade al hacer @foreach ($micro->municipios).
     */
    public function getMunicipiosAttribute($value)
    {
        if ($this->relationLoaded('municipios')) {
            return $this->getRelation('municipios');
        }

        return $value;
    }
}
