<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChildResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $enrollment = $this->relationLoaded('activeEnrollment') ? $this->activeEnrollment : null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'email' => $this->relationLoaded('user') ? $this->user?->email : null,
            'avatar_url' => $this->relationLoaded('user') ? $this->user?->avatarUrl() : null,
            'code' => $this->code,
            'full_name' => $this->full_name,
            'saint_name' => $this->saint_name,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'status' => $this->status,
            'is_archived' => $this->trashed(),
            'parish_id' => $this->parish_id,
            'parish' => $this->whenLoaded('parish', fn () => [
                'id' => $this->parish->id,
                'name' => $this->parish->name,
                'code' => $this->parish->code,
            ]),
            'parents_count' => (int) ($this->parents_count ?? 0),
            'parents' => $this->whenLoaded('parents', fn () => $this->parents->map(fn ($parent) => [
                'id' => $parent->id,
                'name' => $parent->user->name,
                'email' => $parent->user->email,
                'phone' => $parent->phone ?? $parent->user->phone,
            ])->values()),
            'current_class' => $enrollment?->catechismClass ? [
                'id' => $enrollment->catechismClass->id,
                'name' => $enrollment->catechismClass->name,
                'code' => $enrollment->catechismClass->code,
                'academic_year' => $enrollment->catechismClass->academicYear?->name,
            ] : null,
            'created_at' => $this->created_at,
        ];
    }
}
