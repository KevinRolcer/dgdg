<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'whatsapp_totp_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'whatsapp_totp_secret' => 'encrypted',
            'whatsapp_totp_confirmed_at' => 'datetime',
        ];
    }

    public function microrregionesAsignadas()
    {
        return $this->belongsToMany(Microrregione::class, 'user_microrregion', 'user_id', 'microrregion_id');
    }

    public function delegado()
    {
        return $this->hasOne(\App\Models\Delegado::class, 'user_id');
    }

    public function enlace()
    {
        return $this->hasOne(\App\Models\Enlace::class, 'user_id');
    }
}
