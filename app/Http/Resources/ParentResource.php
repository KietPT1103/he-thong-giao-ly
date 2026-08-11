<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user;
        $isArchived = $user->trashed();
        $childrenCount = (int) ($this->children_count ?? 0);

        return [
            'id' => $this->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $this->phone ?? $user->phone,
            'parish_id' => $this->parish_id,
            'parish' => $this->whenLoaded('parish', fn () => [
                'id' => $this->parish->id,
                'name' => $this->parish->name,
                'code' => $this->parish->code,
            ]),
            'account_status' => $isArchived ? 'archived' : $user->status,
            'is_archived' => $isArchived,
            'children_count' => $childrenCount,
            'children' => $this->whenLoaded('children', fn () => $this->children->map(fn ($child) => [
                'id' => $child->id,
                'full_name' => $child->full_name,
                'code' => $child->code,
                'saint_name' => $child->saint_name,
            ])->values()),
            'created_at' => $this->created_at,
        ];
    }
}
