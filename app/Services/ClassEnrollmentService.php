<?php

namespace App\Services;

use App\Models\CatechismClass;
use App\Models\Child;
use App\Models\Enrollment;
use Illuminate\Support\Collection;

class ClassEnrollmentService
{
    public function update(CatechismClass $class, array $rows): array
    {
        $class = CatechismClass::query()
            ->whereKey($class->id)
            ->lockForUpdate()
            ->firstOrFail();
        $class->loadMissing('academicYear', 'classroom');
        $childIds = collect($rows)->pluck('child_id')->map(fn ($id) => (int) $id)->values();
        $children = Child::query()
            ->whereIn('id', $childIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $invalidIds = $this->invalidChildIds($class, $childIds, $children);

        if ($invalidIds->isNotEmpty()) {
            return $this->error('INVALID_CLASS_CHILDREN', 'Danh sách có thiếu nhi không hợp lệ hoặc khác giáo xứ.', [
                'child_ids' => $invalidIds->values()->all(),
            ]);
        }

        $activeIds = collect($rows)
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->pluck('child_id')
            ->map(fn ($id) => (int) $id)
            ->values();
        $conflictingIds = Enrollment::query()
            ->whereIn('child_id', $activeIds)
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->where('catechism_class_id', '!=', $class->id)
            ->whereHas('catechismClass', fn ($query) => $query
                ->where('academic_year_id', $class->academic_year_id))
            ->lockForUpdate()
            ->pluck('child_id')
            ->unique()
            ->values();

        if ($conflictingIds->isNotEmpty()) {
            return $this->error('CHILD_ALREADY_ENROLLED', 'Thiếu nhi đã được ghi danh vào lớp khác trong cùng niên khóa.', [
                'child_ids' => $conflictingIds->all(),
            ]);
        }

        $existing = Enrollment::query()
            ->where('catechism_class_id', $class->id)
            ->lockForUpdate()
            ->get()
            ->keyBy('child_id');
        $resultingActiveCount = $existing
            ->reject(fn (Enrollment $enrollment) => $childIds->contains($enrollment->child_id))
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->count() + $activeIds->count();

        if ($class->classroom?->capacity !== null
            && $resultingActiveCount > $class->classroom->capacity) {
            return $this->error('CLASS_CAPACITY_EXCEEDED', 'Số lượng thiếu nhi vượt quá sức chứa của phòng học.', [
                'capacity' => $class->classroom->capacity,
                'requested_active_count' => $resultingActiveCount,
            ]);
        }

        $oldValues = $existing->map(fn (Enrollment $enrollment) => [
            'child_id' => $enrollment->child_id,
            'status' => $enrollment->status,
        ])->values()->all();

        foreach ($rows as $row) {
            Enrollment::updateOrCreate(
                [
                    'child_id' => (int) $row['child_id'],
                    'catechism_class_id' => $class->id,
                ],
                ['status' => $row['status']],
            );
        }

        return [
            'old' => $oldValues,
            'new' => collect($rows)->map(fn (array $row) => [
                'child_id' => (int) $row['child_id'],
                'status' => $row['status'],
            ])->values()->all(),
        ];
    }

    private function invalidChildIds(CatechismClass $class, Collection $childIds, Collection $children): Collection
    {
        $validIds = $children
            ->filter(fn (Child $child) => $child->parish_id === $class->academicYear->parish_id
                && $child->status === 'studying')
            ->pluck('id');

        return $childIds->diff($validIds)->unique();
    }

    private function error(string $code, string $message, array $data): array
    {
        return ['error' => compact('code', 'message', 'data')];
    }
}
