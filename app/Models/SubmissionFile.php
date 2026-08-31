<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionFile extends Model
{
    protected $fillable = ['submission_id', 'uploaded_by', 'path', 'original_name', 'mime_type', 'size', 'version'];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
