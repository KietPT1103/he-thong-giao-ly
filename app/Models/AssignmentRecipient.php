<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentRecipient extends Model
{
    protected $fillable = [
        'assignment_id', 'catechism_class_id', 'child_id', 'enrollment_id',
        'assigned_at', 'due_at', 'access_status',
    ];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'due_at' => 'datetime'];
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

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }
}
