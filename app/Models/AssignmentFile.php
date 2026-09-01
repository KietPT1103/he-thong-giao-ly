<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentFile extends Model
{
    protected $fillable = ['assignment_id', 'uploaded_by', 'path', 'original_name', 'mime_type', 'size'];

    protected $hidden = ['path'];

    protected $appends = ['download_url'];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getDownloadUrlAttribute(): string
    {
        return "/api/learning-files/assignments/{$this->id}";
    }
}
