<?php

namespace App\Services;

use App\Models\CatechismClass;
use App\Models\Child;
use App\Models\Enrollment;

class ChildEnrollmentService
{
    public function assignCurrentClass(Child $child, ?int $classId): array
    {
        $activeEnrollments = Enrollment::query()
            ->where('child_id', $child->id)
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->lockForUpdate()
            ->get();
        $oldClassIds = $activeEnrollments->pluck('catechism_class_id')->all();

        if ($classId === null) {
            Enrollment::query()
                ->whereKey($activeEnrollments->pluck('id'))
                ->update(['status' => Enrollment::STATUS_INACTIVE]);

            return ['old_class_ids' => $oldClassIds, 'new_class_id' => null];
        }

        $class = CatechismClass::query()
            ->with(['academicYear', 'classroom'])
            ->whereKey($classId)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if (! $class
            || $class->status !== 'active'
            || $class->academicYear->parish_id !== $child->parish_id
            || $child->status !== 'studying') {
            return $this->error('INVALID_CHILD_CLASS', 'Lớp học không phù hợp với giáo xứ hoặc trạng thái của thiếu nhi.');
        }

        $alreadyActive = $activeEnrollments->contains('catechism_class_id', $class->id);
        $occupied = $class->activeEnrollments()
            ->when($alreadyActive, fn ($query) => $query->where('child_id', '!=', $child->id))
            ->count();

        if ($class->classroom?->capacity !== null && $occupied >= $class->classroom->capacity) {
            return $this->error('CLASS_CAPACITY_EXCEEDED', 'Lớp học đã đạt sức chứa tối đa.');
        }

        Enrollment::query()
            ->whereKey($activeEnrollments->where('catechism_class_id', '!=', $class->id)->pluck('id'))
            ->update(['status' => Enrollment::STATUS_INACTIVE]);
        Enrollment::updateOrCreate(
            ['child_id' => $child->id, 'catechism_class_id' => $class->id],
            ['status' => Enrollment::STATUS_ACTIVE],
        );

        return ['old_class_ids' => $oldClassIds, 'new_class_id' => $class->id];
    }

    private function error(string $code, string $message): array
    {
        return ['error' => compact('code', 'message')];
    }
}
