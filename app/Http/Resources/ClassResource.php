<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassResource extends JsonResource
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
            'is_archived' => $this->trashed(),
            'academic_year_id' => $this->academic_year_id,
            'catechism_level_id' => $this->catechism_level_id,
            'classroom_id' => $this->classroom_id,
            'parish' => $parish ? [
                'id' => $parish->id,
                'name' => $parish->name,
                'code' => $parish->code,
            ] : null,
            'academic_year' => $academicYear ? [
                'id' => $academicYear->id,
                'name' => $academicYear->name,
                'starts_on' => $academicYear->starts_on?->toDateString(),
                'ends_on' => $academicYear->ends_on?->toDateString(),
                'is_current' => (bool) $academicYear->is_current,
            ] : null,
            'level' => $this->whenLoaded('level', fn () => [
                'id' => $this->level->id,
                'name' => $this->level->name,
                'code' => $this->level->code,
            ]),
            'classroom' => $this->whenLoaded('classroom', fn () => $this->classroom ? [
                'id' => $this->classroom->id,
                'name' => $this->classroom->name,
                'capacity' => $this->classroom->capacity,
            ] : null),
            'enrollments_count' => (int) ($this->enrollments_count ?? 0),
            'teachers_count' => (int) ($this->teachers_count ?? 0),
            'attendance_sessions_count' => (int) ($this->attendance_sessions_count ?? 0),
            'teachers' => $this->whenLoaded('teachers', fn () => $this->teachers->map(fn ($teacher) => [
                'id' => $teacher->id,
                'name' => $teacher->user->name,
                'email' => $teacher->user->email,
                'code' => $teacher->code,
                'role' => $teacher->pivot->role,
            ])->values()),
            'enrollments' => $this->whenLoaded('enrollments', fn () => $this->enrollments->map(fn ($enrollment) => [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
                'child' => [
                    'id' => $enrollment->child->id,
                    'code' => $enrollment->child->code,
                    'full_name' => $enrollment->child->full_name,
                ],
            ])->values()),
            'schedules' => $this->whenLoaded('schedules', fn () => $this->schedules->map(fn ($schedule) => [
                'id' => $schedule->id,
                'weekday' => $schedule->normalizedWeekday(),
                'starts_at' => substr((string) $schedule->starts_at, 0, 5),
                'ends_at' => substr((string) $schedule->ends_at, 0, 5),
                'starts_on' => $schedule->starts_on?->toDateString(),
                'ends_on' => $schedule->ends_on?->toDateString(),
            ])->values()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
