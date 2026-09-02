<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatechismLevel extends Model
{
    protected $fillable = ['parish_id', 'name', 'code', 'sort_order', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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
