<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatechismClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $academicYear = $this->whenLoaded('academicYear');
        $parish = $academicYear && $academicYear->relationLoaded('parish')
            ? $academicYear->parish
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'status' => $this->status,
            'academic_year_id' => $this->academic_year_id,
            'catechism_level_id' => $this->catechism_level_id,
            'classroom_id' => $this->classroom_id,
            'can_manage' => $request->user()?->can('update', $this->resource) === true,
            'can_manage_enrollments' => $request->user()?->can('manageEnrollments', $this->resource) === true,
            'parish' => $parish ? [
                'id' => $parish->id,
                'name' => $parish->name,
                'code' => $parish->code,
            ] : null,
            'academic_year' => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear->id,
                'name' => $this->academicYear->name,
            ]),
            'level' => $this->whenLoaded('level', fn () => [
                'id' => $this->level->id,
                'name' => $this->level->name,
            ]),
            'classroom' => $this->whenLoaded('classroom', fn () => [
                'id' => $this->classroom?->id,
                'name' => $this->classroom?->name,
            ]),
            'children_count' => $this->when(
                isset($this->children_count),
                $this->children_count,
            ),
            'teachers' => $this->whenLoaded('teachers', fn () => $this->teachers->map(fn ($teacher) => [
                'id' => $teacher->id,
                'name' => $teacher->user->name,
                'code' => $teacher->code,
                'email' => $teacher->user->email,
                'phone' => $teacher->phone ?? $teacher->user->phone,
                'role' => $teacher->pivot->role,
            ])->values()),
            'schedules' => $this->whenLoaded('schedules', fn () => $this->schedules->map(fn ($schedule) => [
                'id' => $schedule->id,
                'weekday' => $schedule->normalizedWeekday(),
                'starts_at' => substr((string) $schedule->starts_at, 0, 5),
                'ends_at' => substr((string) $schedule->ends_at, 0, 5),
                'starts_on' => $schedule->starts_on?->toDateString(),
                'ends_on' => $schedule->ends_on?->toDateString(),
            ])->values()),
        ];
    }
}
