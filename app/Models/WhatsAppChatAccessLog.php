<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppChatAccessLog extends Model
{
    public $timestamps = false;

    protected $table = 'whatsapp_chat_access_logs';

    protected $fillable = [
        'whatsapp_chat_archive_id',
        'user_id',
        'action',
        'resource_path',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    public function archive(): BelongsTo
    {
        return $this->belongsTo(WhatsAppChatArchive::class, 'whatsapp_chat_archive_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
