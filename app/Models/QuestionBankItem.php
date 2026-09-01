<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionBankItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parish_id', 'owner_id', 'scope', 'type', 'prompt', 'explanation',
        'default_points', 'difficulty', 'tags', 'options', 'accepted_answers',
        'rubric', 'settings', 'version',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array', 'options' => 'array',
            'accepted_answers' => 'array', 'rubric' => 'array', 'settings' => 'array',
        ];
    }

    public function parish()
    {
        return $this->belongsTo(Parish::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function assignmentQuestions()
    {
        return $this->hasMany(AssignmentQuestion::class, 'source_question_id');
    }
}
