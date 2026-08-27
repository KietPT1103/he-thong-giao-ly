<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const ENDED_REMOVED = 'removed';

    public const ENDED_STOPPED = 'stopped';

    public const ENDED_TRANSFERRED = 'transferred';

    protected $fillable = [
        'child_id',
        'catechism_class_id',
        'status',
        'ended_at',
        'ended_reason',
        'ended_by',
    ];

    protected function casts(): array
    {
        return ['ended_at' => 'datetime'];
    }

    public function child()
    {
        return $this->belongsTo(Child::class);
    }

    public function catechismClass()
    {
        return $this->belongsTo(CatechismClass::class);
    }

    public function endedBy()
    {
        return $this->belongsTo(User::class, 'ended_by');
    }
}
