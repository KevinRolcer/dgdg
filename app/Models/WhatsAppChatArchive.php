<?php

namespace App\Models;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class WhatsAppChatArchive extends Model
{
    public const IMPORT_STATUS_READY = 'ready';

    public const IMPORT_STATUS_PROCESSING = 'processing';

    public const IMPORT_STATUS_FAILED = 'failed';

    protected $table = 'whatsapp_chat_archives';

    public $timestamps = false;

    protected $fillable = [
        'title',
        'original_zip_name',
        'storage_root_path',
        'message_parts',
        'message_parts_count',
        'created_by',
        'is_encrypted',
        'wrapped_dek',
        'encrypted_key_version',
        'storage_disk',
        'import_status',
        'import_error',
        'import_progress',
        'import_phase',
        'imported_at',
    ];

    protected $casts = [
        'message_parts' => 'array',
        'message_parts_count' => 'integer',
        'imported_at' => 'datetime',
        'is_encrypted' => 'boolean',
        'encrypted_key_version' => 'integer',
        'import_progress' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function storageDiskName(): string
    {
        $d = (string) ($this->storage_disk ?? '');

        return $d !== '' ? $d : 'public';
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->storageDiskName());
    }
}
