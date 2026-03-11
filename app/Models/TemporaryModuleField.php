<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemporaryModuleField extends Model
{
    use HasFactory;

    protected $fillable = [
        'temporary_module_id',
        'label',
        'comment',
        'key',
        'type',
        'is_required',
        'options',
        'sort_order',
        'subsection_index',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'options' => 'array',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(TemporaryModule::class, 'temporary_module_id');
    }
}
