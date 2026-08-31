<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeHistory extends Model
{
    protected $fillable = ['submission_id', 'changed_by', 'old_score', 'new_score', 'reason', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
