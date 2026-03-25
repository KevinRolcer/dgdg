<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalNoteAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'personal_note_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(PersonalNote::class, 'personal_note_id');
    }
}
