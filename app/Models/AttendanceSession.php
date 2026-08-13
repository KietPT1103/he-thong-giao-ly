<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSession extends Model
{
    protected $fillable = ['catechism_class_id', 'held_at', 'qr_expires_at', 'taken_by', 'note'];

    protected function casts(): array
    {
        return ['held_at' => 'datetime', 'qr_expires_at' => 'datetime'];
    }

    public function catechismClass()
    {
        return $this->belongsTo(CatechismClass::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function taker()
    {
        return $this->belongsTo(User::class, 'taken_by');
    }
}
