<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonalNoteFolder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'name', 'color', 'icon'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function notes()
    {
        return $this->hasMany(PersonalNote::class, 'folder_id');
    }
}
