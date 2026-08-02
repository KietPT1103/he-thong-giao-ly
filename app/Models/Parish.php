<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parish extends Model
{
    protected $fillable = ['name', 'code', 'phone', 'email'];

    public function academicYears()
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function levels()
    {
        return $this->hasMany(CatechismLevel::class);
    }

    public function classrooms()
    {
        return $this->hasMany(Classroom::class);
    }

    public function teachers()
    {
        return $this->hasMany(TeacherProfile::class);
    }

    public function children()
    {
        return $this->hasMany(Child::class);
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }
}
