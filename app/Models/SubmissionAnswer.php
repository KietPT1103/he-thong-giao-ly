<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionAnswer extends Model
{
    protected $fillable = [
        'submission_id', 'assignment_question_id', 'answer', 'auto_score',
        'manual_score', 'rubric_scores', 'feedback', 'graded_by', 'graded_at', 'saved_at',
    ];

    protected function casts(): array
    {
        return [
            'answer' => 'array', 'rubric_scores' => 'array',
            'graded_at' => 'datetime', 'saved_at' => 'datetime',
        ];
    }

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function question()
    {
        return $this->belongsTo(AssignmentQuestion::class, 'assignment_question_id');
    }

    public function grader()
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
