<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

class TemporaryModule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'expires_at',
        'is_active',
        'applies_to_all',
        'registration_scope',
        'show_microregion',
        'is_encrypted_event',
        'edit_permission_duration_hours',
        'pdf_password_encrypted',
        'target_municipios',
        'created_by',
        'exported_at',
        'seed_discard_log',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'applies_to_all' => 'boolean',
        'show_microregion' => 'boolean',
        'is_encrypted_event' => 'boolean',
        'edit_permission_duration_hours' => 'integer',
        'expires_at' => 'datetime',
        'exported_at' => 'datetime',
        'seed_discard_log' => 'array',
        'target_municipios' => 'array',
    ];

    public function targetUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'temporary_module_user', 'temporary_module_id', 'user_id')
            ->withTimestamps();
    }

    public function fields(): HasMany
    {
        return $this->hasMany(TemporaryModuleField::class)->orderBy('sort_order');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TemporaryModuleEntry::class)->latest('submitted_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isAvailable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (! $this->expires_at) {
            return true;
        }

        return $this->expires_at->greaterThanOrEqualTo(Carbon::now());
    }

    public function pdfPassword(): ?string
    {
        if (! is_string($this->pdf_password_encrypted) || trim($this->pdf_password_encrypted) === '') {
            return null;
        }

        try {
            $password = Crypt::decryptString($this->pdf_password_encrypted);
        } catch (\Throwable) {
            return null;
        }

        $password = trim($password);

        return $password !== '' ? $password : null;
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $builder) {
                $builder->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', Carbon::now());
            });
    }
}
