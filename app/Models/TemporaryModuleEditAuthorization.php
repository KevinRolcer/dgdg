<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class TemporaryModuleEditAuthorization extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'temporary_module_id',
        'temporary_module_entry_id',
        'requested_by',
        'approved_by',
        'status',
        'reason',
        'requested_at',
        'approved_at',
        'expires_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(TemporaryModule::class, 'temporary_module_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(TemporaryModuleEntry::class, 'temporary_module_entry_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->expires_at instanceof Carbon
            && $this->expires_at->isFuture();
    }
}
