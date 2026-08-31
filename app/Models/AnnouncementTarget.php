<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementTarget extends Model
{
    protected $fillable = ['announcement_id', 'catechism_class_id', 'child_id', 'audience'];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }

    public function catechismClass()
    {
        return $this->belongsTo(CatechismClass::class);
    }

    public function child()
    {
        return $this->belongsTo(Child::class);
    }
}
