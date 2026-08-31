<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentAccommodation extends Model
{
    protected $fillable = ['assignment_id', 'child_id', 'due_at', 'extra_attempts', 'granted_by', 'reason'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime'];
    }

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function child()
    {
        return $this->belongsTo(Child::class);
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
