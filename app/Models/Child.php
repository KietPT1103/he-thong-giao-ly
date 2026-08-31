<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Child extends Model
{
    use SoftDeletes;

    protected $fillable = ['parish_id', 'user_id', 'code', 'full_name', 'saint_name', 'date_of_birth', 'status'];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function parish()
    {
        return $this->belongsTo(Parish::class);
    }

    public function parents()
    {
        return $this->belongsToMany(ParentProfile::class, 'parent_child')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollment()
    {
        return $this->hasOne(Enrollment::class)
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->latestOfMany();
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function device()
    {
        return $this->hasOne(ChildDevice::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function assignmentRecipients()
    {
        return $this->hasMany(AssignmentRecipient::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }
}
