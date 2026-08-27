<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CatechismClass extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'academic_year_id',
        'catechism_level_id',
        'classroom_id',
        'name',
        'code',
        'status',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function level()
    {
        return $this->belongsTo(CatechismLevel::class, 'catechism_level_id');
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollments()
    {
        return $this->hasMany(Enrollment::class)->where('status', Enrollment::STATUS_ACTIVE);
    }

    public function children()
    {
        return $this->belongsToMany(Child::class, 'enrollments')
            ->wherePivot('status', Enrollment::STATUS_ACTIVE)
            ->withPivot('status')
            ->withTimestamps();
    }

    public function teachers()
    {
        return $this->belongsToMany(TeacherProfile::class, 'teacher_class_assignments')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function schedules()
    {
        return $this->hasMany(ClassSchedule::class);
    }

    public function attendanceSessions()
    {
        return $this->hasMany(AttendanceSession::class);
    }
}
