<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentTarget extends Model
{
    protected $fillable = ['assignment_id', 'catechism_class_id', 'child_id', 'due_at', 'attempt_limit'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime'];
    }

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function catechismClass()
    {
        return $this->belongsTo(CatechismClass::class);
    }

    public function child()
    {
        return $this->belongsTo(Child::class);
    }
}
