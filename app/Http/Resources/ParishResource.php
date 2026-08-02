<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParishResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $teacherCount = (int) ($this->teachers_count ?? 0);
        $childrenCount = (int) ($this->children_count ?? 0);
        $academicYearsCount = (int) ($this->academic_years_count ?? 0);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone,
            'email' => $this->email,
            'teacher_count' => $teacherCount,
            'children_count' => $childrenCount,
            'academic_years_count' => $academicYearsCount,
            'dependency_counts' => [
                'teachers' => $teacherCount,
                'children' => $childrenCount,
                'academic_years' => $academicYearsCount,
                'levels' => (int) ($this->levels_count ?? 0),
                'classrooms' => (int) ($this->classrooms_count ?? 0),
                'announcements' => (int) ($this->announcements_count ?? 0),
            ],
            'teachers' => $this->whenLoaded('teachers', fn () => $this->teachers->map(fn ($teacher) => [
                'id' => $teacher->id,
                'code' => $teacher->code,
                'phone' => $teacher->phone,
                'parish_id' => $teacher->parish_id,
                'user' => [
                    'id' => $teacher->user->id,
                    'name' => $teacher->user->name,
                    'email' => $teacher->user->email,
                    'status' => $teacher->user->status,
                ],
            ])->values()),
            // Keep the generic directory fields until all admin directories have dedicated views.
            'secondary' => $this->phone ?: ($this->email ?: 'Chưa cập nhật liên hệ'),
            'details' => [
                "{$teacherCount} giáo lý viên",
                "{$childrenCount} thiếu nhi",
                "{$academicYearsCount} niên khóa",
            ],
            'status' => 'Đang hoạt động',
        ];
    }
}
