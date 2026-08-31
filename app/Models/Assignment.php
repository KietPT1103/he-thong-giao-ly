<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_GRADING = 'grading';

    public const STATUS_RELEASED = 'released';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'created_by', 'title', 'description', 'type', 'status', 'max_score',
        'passing_score', 'opens_at', 'due_at', 'time_limit_minutes',
        'allowed_attempts', 'score_method', 'allow_resume', 'allow_late',
        'late_penalty_percent', 'shuffle_questions', 'shuffle_options',
        'allow_backtracking', 'result_release_mode', 'results_release_at',
        'show_answers', 'version', 'published_at', 'closed_at', 'released_at',
        'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime', 'due_at' => 'datetime',
            'results_release_at' => 'datetime', 'published_at' => 'datetime',
            'closed_at' => 'datetime', 'released_at' => 'datetime',
            'withdrawn_at' => 'datetime', 'allow_resume' => 'boolean',
            'allow_late' => 'boolean', 'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean', 'allow_backtracking' => 'boolean',
            'show_answers' => 'boolean',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions()
    {
        return $this->hasMany(AssignmentQuestion::class)->orderBy('position');
    }

    public function targets()
    {
        return $this->hasMany(AssignmentTarget::class);
    }

    public function recipients()
    {
        return $this->hasMany(AssignmentRecipient::class);
    }

    public function accommodations()
    {
        return $this->hasMany(AssignmentAccommodation::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }
}
