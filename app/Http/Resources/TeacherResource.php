<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user;
        $classesCount = (int) ($this->classes_count ?? 0);
        $isArchived = $user->trashed();
        $accountStatus = $isArchived ? 'archived' : $user->status;

        return [
            'id' => $this->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $this->phone ?? $user->phone,
            'code' => $this->code,
            'parish_id' => $this->parish_id,
            'parish' => $this->whenLoaded('parish', fn () => [
                'id' => $this->parish->id,
                'name' => $this->parish->name,
                'code' => $this->parish->code,
            ]),
            'account_status' => $accountStatus,
            'is_archived' => $isArchived,
            'must_change_password' => (bool) $user->must_change_password,
            'classes_count' => $classesCount,
            'classes' => $this->whenLoaded('classes', fn () => $this->classes->map(fn ($class) => [
                'id' => $class->id,
                'name' => $class->name,
                'code' => $class->code,
                'status' => $class->status,
                'role' => $class->pivot->role,
                'academic_year' => $class->academicYear ? [
                    'id' => $class->academicYear->id,
                    'name' => $class->academicYear->name,
                ] : null,
                'level' => $class->level ? [
                    'id' => $class->level->id,
                    'name' => $class->level->name,
                ] : null,
            ])->values()),
            'created_at' => $this->created_at,
            // Compatibility fields for the shared directory picker.
            'secondary' => $user->email,
            'details' => [
                $this->relationLoaded('parish') ? $this->parish->name : '',
                "{$classesCount} lớp phụ trách",
            ],
            'status' => match ($accountStatus) {
                'active' => 'Đang hoạt động',
                'blocked' => 'Đã khóa',
                default => 'Đã lưu trữ',
            },
        ];
    }
}
