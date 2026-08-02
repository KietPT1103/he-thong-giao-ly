<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassSchedule extends Model
{
    protected $fillable = [
        'catechism_class_id',
        'weekday',
        'starts_at',
        'ends_at',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function catechismClass()
    {
        return $this->belongsTo(CatechismClass::class);
    }

    public function normalizedWeekday(): int
    {
        return $this->weekday === 0 ? 7 : $this->weekday;
    }
}
