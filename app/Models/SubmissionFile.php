<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionFile extends Model
{
    protected $fillable = ['submission_id', 'uploaded_by', 'path', 'original_name', 'mime_type', 'size', 'version'];

    protected $hidden = ['path'];

    protected $appends = ['download_url'];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getDownloadUrlAttribute(): string
    {
        return "/api/learning-files/submissions/{$this->id}";
    }
}
