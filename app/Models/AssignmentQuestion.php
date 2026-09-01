<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentQuestion extends Model
{
    protected $fillable = [
        'assignment_id', 'source_question_id', 'type', 'prompt', 'explanation',
        'points', 'position', 'options', 'accepted_answers', 'rubric', 'settings',
    ];

    protected function casts(): array
    {
        return ['options' => 'array', 'accepted_answers' => 'array', 'rubric' => 'array', 'settings' => 'array'];
    }

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function sourceQuestion()
    {
        return $this->belongsTo(QuestionBankItem::class, 'source_question_id');
    }

    public function answers()
    {
        return $this->hasMany(SubmissionAnswer::class);
    }
}
