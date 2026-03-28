<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppChatArchiveUploadFile extends Model
{
    protected $table = 'whatsapp_chat_archive_upload_files';

    protected $fillable = [
        'whatsapp_chat_archive_id',
        'relative_path',
        'relative_path_hash',
        'file_size',
        'client_last_modified_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'client_last_modified_at' => 'integer',
    ];

    public function archive(): BelongsTo
    {
        return $this->belongsTo(WhatsAppChatArchive::class, 'whatsapp_chat_archive_id');
    }
}
