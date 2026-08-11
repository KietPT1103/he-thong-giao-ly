<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParentProfile extends Model
{
    protected $fillable = ['user_id', 'parish_id', 'phone'];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function parish()
    {
        return $this->belongsTo(Parish::class);
    }

    public function children()
    {
        return $this->belongsToMany(Child::class, 'parent_child')
            ->withPivot('relationship')
            ->withTimestamps();
    }
}
