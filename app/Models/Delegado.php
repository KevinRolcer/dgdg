<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Delegado extends Model
{
    use HasFactory;

    protected $table = 'delegados';

    protected $fillable = [
        'user_id',
        'nombre',
        'ap_paterno',
        'ap_materno',
        'microrregion_id',
        'dependencia_gob_id',
        'telefono',
        'email',
        'foto',
    ];

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->ap_paterno} {$this->ap_materno}");
    }

    public function getFotoUrlAttribute(): ?string
    {
        if (!$this->foto) {
            return null;
        }

        if (Storage::disk('public')->exists($this->foto)) {
            return Storage::url($this->foto);
        }

        return '/'.ltrim(str_replace('\\', '/', (string) $this->foto), '/');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function microrregion()
    {
        return $this->belongsTo(Microrregione::class, 'microrregion_id');
    }
}
