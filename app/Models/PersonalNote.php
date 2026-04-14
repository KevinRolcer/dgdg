<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonalNote extends Model
{
    use HasFactory, SoftDeletes;

    public const PRIORITY_VALUES = ['none', 'low', 'medium', 'high'];

    /**
     * Get the attachments for the note.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(PersonalNoteAttachment::class, 'personal_note_id');
    }

    protected $fillable = [
        'user_id',
        'folder_id',
        'title',
        'content',
        'is_encrypted',
        'password_verify_hash',
        'color',
        'priority',
        'reminder_at',
        'scheduled_date',
        'scheduled_time',
        'is_archived',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
        'is_archived' => 'boolean',
        'reminder_at' => 'datetime',
        'scheduled_date' => 'date',
    ];

    /**
     * Get the user that owns the note.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the folder that contains the note.
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(PersonalNoteFolder::class, 'folder_id');
    }

    /**
     * Scope a query to only include notes of a given user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
