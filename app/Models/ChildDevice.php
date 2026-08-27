<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChildDevice extends Model
{
    protected $fillable = [
        'child_id',
        'token_hash',
        'activated_at',
        'expires_at',
        'last_used_at',
        'revoked_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function child()
    {
        return $this->belongsTo(Child::class);
    }
}
