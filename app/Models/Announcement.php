<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'parish_id', 'created_by', 'title', 'body', 'importance', 'status',
        'scheduled_at', 'sent_at', 'expires_at', 'withdrawn_at', 'is_pinned',
        'requires_acknowledgement', 'source_type', 'source_id', 'version',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime', 'sent_at' => 'datetime',
            'expires_at' => 'datetime', 'withdrawn_at' => 'datetime',
            'is_pinned' => 'boolean', 'requires_acknowledgement' => 'boolean',
        ];
    }

    public function parish()
    {
        return $this->belongsTo(Parish::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targets()
    {
        return $this->hasMany(AnnouncementTarget::class);
    }

    public function recipients()
    {
        return $this->belongsToMany(User::class, 'announcement_recipients')
            ->withPivot(['read_at', 'acknowledged_at', 'reminded_at'])
            ->withTimestamps();
    }
}
