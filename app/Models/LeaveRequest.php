<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $fillable = ['child_id', 'attendance_session_id', 'absence_date', 'reason', 'status', 'reviewed_by', 'review_note'];

    protected function casts(): array
    {
        return ['absence_date' => 'date'];
    }

    public function child()
    {
        return $this->belongsTo(Child::class);
    }

    public function session()
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
