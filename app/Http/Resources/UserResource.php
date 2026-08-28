<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_path ? '/storage/'.ltrim($this->avatar_path, '/') : null,
            'avatar_path' => $this->avatar_path,
            'status' => $this->status,
            'roles' => $this->getRoleNames()->values(),
            'permissions' => $this->effectivePermissions()->pluck('name')->values(),
            'granted_permissions' => $this->getDirectPermissions()->pluck('name')->values(),
            'denied_permissions' => $this->relationLoaded('deniedPermissions')
                ? $this->deniedPermissions->pluck('name')->values()
                : $this->deniedPermissions()->pluck('name')->values(),
            'must_change_password' => $this->must_change_password,
            'child_profile_id' => $this->hasRole('child') ? $this->child?->id : null,
            'last_login_at' => $this->last_login_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
