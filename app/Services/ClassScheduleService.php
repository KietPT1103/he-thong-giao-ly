<?php

namespace App\Services;

use App\Models\CatechismClass;
use App\Models\Classroom;
use App\Models\ClassSchedule;
use App\Support\ScheduleConflict;
use Illuminate\Support\Collection;

class ClassScheduleService
{
    public function update(CatechismClass $class, array $rows, bool $allowTeacherConflicts): array
    {
        if ($class->classroom_id !== null) {
            Classroom::query()->whereKey($class->classroom_id)->lockForUpdate()->firstOrFail();
        }
        $class = CatechismClass::query()
            ->whereKey($class->id)
            ->lockForUpdate()
            ->firstOrFail();
        $class->loadMissing('schedules', 'teachers.user');
        $roomConflicts = $this->roomConflicts($class, $rows);
        if ($roomConflicts !== []) {
            return $this->error(
                'CLASSROOM_SCHEDULE_CONFLICT',
                'Phòng học đang có lịch trùng với lớp khác.',
                ['conflicts' => $roomConflicts],
            );
        }

        $teacherConflicts = $this->teacherConflicts($class, $rows);
        if ($teacherConflicts !== [] && ! $allowTeacherConflicts) {
            return $this->error(
                'TEACHER_SCHEDULE_CONFLICT',
                'Một hoặc nhiều giáo lý viên đang có lịch dạy trùng.',
                ['conflicts' => $teacherConflicts],
            );
        }

        $oldValues = $class->schedules->map(fn (ClassSchedule $schedule) => $this->payload($schedule))->values()->all();
        $class->schedules()->delete();
        foreach ($rows as $row) {
            $class->schedules()->create([
                ...$row,
                'weekday' => (int) $row['weekday'],
            ]);
        }

        return [
            'old' => $oldValues,
            'new' => collect($rows)->map(fn (array $row) => [
                ...$row,
                'weekday' => (int) $row['weekday'],
            ])->values()->all(),
        ];
    }

    private function roomConflicts(CatechismClass $class, array $rows): array
    {
        if ($class->classroom_id === null || $rows === []) {
            return [];
        }

        $otherClasses = CatechismClass::query()
            ->whereKeyNot($class->id)
            ->where('classroom_id', $class->classroom_id)
            ->whereHas('schedules')
            ->with('schedules')
            ->lockForUpdate()
            ->get();

        return $this->conflicts($rows, $otherClasses)
            ->map(fn (array $conflict) => [
                'class_id' => $conflict['class']->id,
                'class_name' => $conflict['class']->name,
                'schedule' => $this->payload($conflict['schedule']),
            ])->values()->all();
    }

    private function teacherConflicts(CatechismClass $class, array $rows): array
    {
        if ($class->teachers->isEmpty() || $rows === []) {
            return [];
        }

        $teacherIds = $class->teachers->pluck('id');
        $otherClasses = CatechismClass::query()
            ->whereKeyNot($class->id)
            ->whereHas('teachers', fn ($query) => $query->whereIn('teacher_profiles.id', $teacherIds))
            ->with(['schedules', 'teachers' => fn ($query) => $query
                ->whereIn('teacher_profiles.id', $teacherIds)
                ->with('user')])
            ->lockForUpdate()
            ->get();
        $conflicts = [];

        foreach ($this->conflicts($rows, $otherClasses) as $conflict) {
            foreach ($conflict['class']->teachers as $teacher) {
                $conflicts["{$teacher->id}:{$conflict['class']->id}"] = [
                    'teacher_id' => $teacher->id,
                    'teacher_name' => $teacher->user->name,
                    'class_id' => $conflict['class']->id,
                    'class_name' => $conflict['class']->name,
                ];
            }
        }

        return array_values($conflicts);
    }

    private function conflicts(array $rows, Collection $classes): Collection
    {
        return $classes->flatMap(function (CatechismClass $otherClass) use ($rows) {
            return $otherClass->schedules->filter(function (ClassSchedule $schedule) use ($rows) {
                return collect($rows)->contains(fn (array $row) => ScheduleConflict::overlaps(
                    $row,
                    $this->payload($schedule),
                ));
            })->map(fn (ClassSchedule $schedule) => [
                'class' => $otherClass,
                'schedule' => $schedule,
            ]);
        })->values();
    }

    private function payload(ClassSchedule $schedule): array
    {
        return [
            'weekday' => $schedule->normalizedWeekday(),
            'starts_at' => substr((string) $schedule->starts_at, 0, 5),
            'ends_at' => substr((string) $schedule->ends_at, 0, 5),
            'starts_on' => $schedule->starts_on?->toDateString(),
            'ends_on' => $schedule->ends_on?->toDateString(),
        ];
    }

    private function error(string $code, string $message, array $data): array
    {
        return ['error' => compact('code', 'message', 'data')];
    }
}
