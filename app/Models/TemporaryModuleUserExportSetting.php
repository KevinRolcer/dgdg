<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemporaryModuleUserExportSetting extends Model
{
    protected $table = 'temporary_module_user_export_settings';

    protected $fillable = [
        'user_id',
        'temporary_module_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function temporaryModule(): BelongsTo
    {
        return $this->belongsTo(TemporaryModule::class, 'temporary_module_id');
    }
}
