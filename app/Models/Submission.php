<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_GRADING = 'grading';

    public const STATUS_GRADED = 'graded';

    public const STATUS_RELEASED = 'released';

    public const STATUS_REOPENED = 'reopened';

    protected $fillable = [
        'assignment_id', 'child_id', 'attempt_number', 'status', 'started_at',
        'submitted_at', 'graded_at', 'released_at', 'auto_score', 'manual_score',
        'final_score', 'is_late', 'time_spent_seconds', 'version',
        'assigned_grader_id', 'general_feedback',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime', 'submitted_at' => 'datetime',
            'graded_at' => 'datetime', 'released_at' => 'datetime',
            'is_late' => 'boolean',
        ];
    }

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function child()
    {
        return $this->belongsTo(Child::class);
    }

    public function answers()
    {
        return $this->hasMany(SubmissionAnswer::class);
    }

    public function files()
    {
        return $this->hasMany(SubmissionFile::class);
    }

    public function histories()
    {
        return $this->hasMany(GradeHistory::class);
    }

    public function assignedGrader()
    {
        return $this->belongsTo(User::class, 'assigned_grader_id');
    }
}
