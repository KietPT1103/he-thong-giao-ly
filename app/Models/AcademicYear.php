<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    protected $fillable = ['parish_id', 'name', 'starts_on', 'ends_on', 'is_current', 'is_active'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function parish()
    {
        return $this->belongsTo(Parish::class);
    }

    public function classes()
    {
        return $this->hasMany(CatechismClass::class);
    }
}
