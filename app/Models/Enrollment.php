<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = ['child_id', 'catechism_class_id', 'status'];

    public function child()
    {
        return $this->belongsTo(Child::class);
    }

    public function catechismClass()
    {
        return $this->belongsTo(CatechismClass::class);
    }
}
