<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemporaryModuleEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'temporary_module_id',
        'user_id',
        'microrregion_id',
        'data',
        'main_image_field_key',
        'submitted_at',
    ];

    protected $casts = [
        'data' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(TemporaryModule::class, 'temporary_module_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function microrregion(): BelongsTo
    {
        return $this->belongsTo(Microrregione::class, 'microrregion_id');
    }
}
