<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class TemporaryModuleActionAuthorization extends Model
{
    use HasFactory;

    public const ACTION_VIEW = 'view';
    public const ACTION_CREATE = 'create';
    public const ACTION_EDIT = 'edit';
    public const ACTION_DELETE = 'delete';

    public const ACTIONS = [
        self::ACTION_VIEW,
        self::ACTION_CREATE,
        self::ACTION_EDIT,
        self::ACTION_DELETE,
    ];

    public const ACTION_LABELS = [
        self::ACTION_VIEW => 'Ver registros',
        self::ACTION_CREATE => 'Registrar',
        self::ACTION_EDIT => 'Editar en hoja',
        self::ACTION_DELETE => 'Eliminar',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'temporary_module_id',
        'requested_by',
        'approved_by',
        'action',
        'status',
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
