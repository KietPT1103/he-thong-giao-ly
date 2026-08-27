<?php

namespace App\Services;

use App\Models\CatechismClass;
use App\Models\Child;
use App\Models\Enrollment;

class TeacherClassEnrollmentService
{
    public function enroll(CatechismClass $class, int $childId): array
    {
        $class = $this->lockClass($class->id);
        if ($class->status !== 'active') {
            return $this->error('SOURCE_CLASS_INACTIVE', 'Không thể thêm thiếu nhi vào lớp đang tạm ngưng.');
        }
        $child = Child::query()->lockForUpdate()->findOrFail($childId);
        if ($child->parish_id !== $class->academicYear->parish_id || $child->status !== 'studying') {
            return $this->error('INVALID_CLASS_CHILD', 'Thiếu nhi không hợp lệ hoặc không thuộc giáo xứ của lớp.');
        }

        $conflict = Enrollment::query()
            ->where('child_id', $child->id)
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->where('catechism_class_id', '!=', $class->id)
            ->whereHas('catechismClass', fn ($query) => $query
                ->where('academic_year_id', $class->academic_year_id))
            ->lockForUpdate()
            ->exists();
        if ($conflict) {
            return $this->error('CHILD_ALREADY_ENROLLED', 'Thiếu nhi đang học lớp khác trong cùng niên khóa.');
        }

        $existing = Enrollment::query()
            ->where('child_id', $child->id)
            ->where('catechism_class_id', $class->id)
            ->lockForUpdate()
            ->first();
        if ($existing?->status !== Enrollment::STATUS_ACTIVE && ! $this->hasCapacity($class)) {
            return $this->error('CLASS_CAPACITY_EXCEEDED', 'Lớp đã đạt sức chứa tối đa.');
        }

        $enrollment = Enrollment::updateOrCreate(
            ['child_id' => $child->id, 'catechism_class_id' => $class->id],
            [
                'status' => Enrollment::STATUS_ACTIVE,
                'ended_at' => null,
                'ended_reason' => null,
                'ended_by' => null,
            ],
        );

        return ['enrollment' => $enrollment->load('child')];
    }

    public function end(CatechismClass $class, int $childId, string $action, int $actorId): array
    {
        $this->lockClass($class->id);
        $enrollment = Enrollment::query()
            ->where('child_id', $childId)
            ->where('catechism_class_id', $class->id)
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->lockForUpdate()
            ->first();
        if (! $enrollment) {
            return $this->error('CHILD_NOT_ENROLLED', 'Thiếu nhi không còn trong lớp này.');
        }

        $enrollment->update([
            'status' => Enrollment::STATUS_INACTIVE,
            'ended_at' => now(),
            'ended_reason' => $action === 'stop'
                ? Enrollment::ENDED_STOPPED
                : Enrollment::ENDED_REMOVED,
            'ended_by' => $actorId,
        ]);

        return ['enrollment' => $enrollment->fresh('child')];
    }

    public function transfer(
        CatechismClass $source,
        CatechismClass $target,
        int $childId,
        int $actorId,
    ): array {
        $classes = CatechismClass::query()
            ->whereIn('id', [$source->id, $target->id])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $source = $classes->get($source->id)?->loadMissing('academicYear', 'classroom');
        $target = $classes->get($target->id)?->loadMissing('academicYear', 'classroom');
        if (! $source || ! $target || $source->academic_year_id !== $target->academic_year_id) {
            return $this->error('TRANSFER_YEAR_MISMATCH', 'Chỉ có thể chuyển lớp trong cùng niên khóa.');
        }
        if ($target->status !== 'active') {
            return $this->error('TARGET_CLASS_INACTIVE', 'Không thể chuyển thiếu nhi vào lớp đang tạm ngưng.');
        }

        $child = Child::query()->lockForUpdate()->findOrFail($childId);
        if ($child->parish_id !== $target->academicYear->parish_id || $child->status !== 'studying') {
            return $this->error('INVALID_CLASS_CHILD', 'Thiếu nhi không hợp lệ hoặc không thuộc giáo xứ của lớp.');
        }

        $sourceEnrollment = Enrollment::query()
            ->where('child_id', $child->id)
            ->where('catechism_class_id', $source->id)
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->lockForUpdate()
            ->first();
        if (! $sourceEnrollment) {
            return $this->error('CHILD_NOT_ENROLLED', 'Thiếu nhi không còn trong lớp hiện tại.');
        }

        $unexpectedConflict = Enrollment::query()
            ->where('child_id', $child->id)
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->where('catechism_class_id', '!=', $source->id)
            ->whereHas('catechismClass', fn ($query) => $query
                ->where('academic_year_id', $source->academic_year_id))
            ->lockForUpdate()
            ->exists();
        if ($unexpectedConflict) {
            return $this->error('CHILD_ALREADY_ENROLLED', 'Thiếu nhi đang học lớp khác trong cùng niên khóa.');
        }

        $targetEnrollment = Enrollment::query()
            ->where('child_id', $child->id)
            ->where('catechism_class_id', $target->id)
            ->lockForUpdate()
            ->first();
        if ($targetEnrollment?->status !== Enrollment::STATUS_ACTIVE && ! $this->hasCapacity($target)) {
            return $this->error('CLASS_CAPACITY_EXCEEDED', 'Lớp chuyển đến đã đạt sức chứa tối đa.');
        }

        $sourceEnrollment->update([
            'status' => Enrollment::STATUS_INACTIVE,
            'ended_at' => now(),
            'ended_reason' => Enrollment::ENDED_TRANSFERRED,
            'ended_by' => $actorId,
        ]);
        $targetEnrollment = Enrollment::updateOrCreate(
            ['child_id' => $child->id, 'catechism_class_id' => $target->id],
            [
                'status' => Enrollment::STATUS_ACTIVE,
                'ended_at' => null,
                'ended_reason' => null,
                'ended_by' => null,
            ],
        );

        return [
            'source' => $sourceEnrollment->fresh('child'),
            'target' => $targetEnrollment->load('child'),
        ];
    }

    private function lockClass(int $classId): CatechismClass
    {
        return CatechismClass::query()
            ->whereKey($classId)
            ->lockForUpdate()
            ->firstOrFail()
            ->loadMissing('academicYear', 'classroom');
    }

    private function hasCapacity(CatechismClass $class): bool
    {
        return $class->classroom?->capacity === null
            || $class->activeEnrollments()->count() < $class->classroom->capacity;
    }

    private function error(string $code, string $message): array
    {
        return ['error' => compact('code', 'message')];
    }
}
