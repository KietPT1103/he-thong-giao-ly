<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    use HasRoles {
        hasPermissionTo as protected hasPermissionToFromRoles;
    }

    protected $fillable = [
        'name', 'email', 'phone', 'avatar_path', 'password', 'status', 'must_change_password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
            'deleted_at' => 'datetime',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
            'mfa_confirmed_at' => 'datetime',
        ];
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $resolvedPermission = $this->filterPermission($permission, $guardName);
        $this->loadMissing('deniedPermissions');

        if ($this->deniedPermissions->contains('id', $resolvedPermission->getKey())) {
            return false;
        }

        return $this->hasPermissionToFromRoles($resolvedPermission, $guardName);
    }

    public function deniedPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'denied_permissions')->withTimestamps();
    }

    public function effectivePermissions()
    {
        $this->loadMissing('deniedPermissions');
        $denied = $this->deniedPermissions->pluck('id');

        return $this->getAllPermissions()->reject(fn (Permission $permission) => $denied->contains($permission->id))->values();
    }

    public function teacherProfile()
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function parentProfile()
    {
        return $this->hasOne(ParentProfile::class);
    }

    public function child()
    {
        return $this->hasOne(Child::class);
    }

    public function avatarImage(): HasOne
    {
        return $this->hasOne(UserAvatar::class);
    }

    public function avatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        if (str_starts_with($this->avatar_path, 'database:')) {
            $version = substr($this->avatar_path, strlen('database:'));

            return "/api/avatars/{$this->getKey()}?v=".rawurlencode($version);
        }

        return '/storage/'.ltrim($this->avatar_path, '/');
    }
}
