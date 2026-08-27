<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CatechismClassResource;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherController extends ApiController
{
    public function children(Request $request)
    {
        $teacher = $request->user()->teacherProfile;
        abort_unless($teacher && $request->user()->can('view-children'), 403);
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'class_id' => ['nullable', 'integer', Rule::exists('catechism_classes', 'id')->whereNull('deleted_at')],
            'status' => ['nullable', Rule::in(['studying', 'paused', 'graduated', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim((string) ($data['search'] ?? ''));
        $classes = $teacher->classes()
            ->where('catechism_classes.status', 'active')
            ->with('schedules')
            ->withCount('activeEnrollments')
            ->orderBy('catechism_classes.name')
            ->get(['catechism_classes.id', 'name', 'code']);
        $classIds = $classes->pluck('id');
        if (isset($data['class_id'])) {
            abort_unless($classIds->contains((int) $data['class_id']), 403);
        }
        $enrollments = Enrollment::query()
            ->join('catechism_classes', 'catechism_classes.id', '=', 'enrollments.catechism_class_id')
            ->join('children', 'children.id', '=', 'enrollments.child_id')
            ->whereIn('enrollments.catechism_class_id', $classIds)
            ->where('enrollments.status', Enrollment::STATUS_ACTIVE)
            ->whereNull('catechism_classes.deleted_at')
            ->whereNull('children.deleted_at')
            ->when($data['class_id'] ?? null, fn ($query, $classId) => $query
                ->where('enrollments.catechism_class_id', $classId))
            ->when($data['status'] ?? null, fn ($query, $status) => $query
                ->where('children.status', $status))
            ->when($search, fn ($query) => $query->where(fn ($filtered) => $filtered
                ->where('children.full_name', 'like', "%{$search}%")
                ->orWhere('children.code', 'like', "%{$search}%")
                ->orWhere('children.saint_name', 'like', "%{$search}%")))
            ->select('enrollments.*')
            ->with([
                'child:id,user_id,code,full_name,saint_name,date_of_birth,status',
                'child.user:id,avatar_path',
                'catechismClass:id,name,code',
            ])
            ->orderBy('catechism_classes.name')
            ->orderBy('children.full_name')
            ->paginate(10);
        $studyingCount = Enrollment::query()
            ->join('children', 'children.id', '=', 'enrollments.child_id')
            ->whereIn('enrollments.catechism_class_id', $classIds)
            ->where('enrollments.status', Enrollment::STATUS_ACTIVE)
            ->where('children.status', 'studying')
            ->whereNull('children.deleted_at')
            ->count();

        return $this->success(
            $enrollments->getCollection()->map(function (Enrollment $enrollment) {
                $child = $enrollment->child;

                return [
                    'id' => $child->id,
                    'code' => $child->code,
                    'full_name' => $child->full_name,
                    'saint_name' => $child->saint_name,
                    'date_of_birth' => $child->date_of_birth?->toDateString(),
                    'status' => $child->status,
                    'avatar_url' => $child->user?->avatar_path
                        ? '/storage/'.ltrim($child->user->avatar_path, '/')
                        : null,
                    'class' => [
                        'id' => $enrollment->catechismClass->id,
                        'name' => $enrollment->catechismClass->name,
                        'code' => $enrollment->catechismClass->code,
                    ],
                ];
            })->values(),
            'Đã tải danh sách thiếu nhi.',
            [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
                'summary' => [
                    'total_children' => $classes->sum('active_enrollments_count'),
                    'studying_children' => $studyingCount,
                    'class_count' => $classes->count(),
                    'next_schedule' => $this->nextSchedule($classes),
                ],
                'filters' => [
                    'classes' => $classes->map(fn ($class) => $class->only(['id', 'name', 'code']))->values(),
                ],
            ],
        );
    }

    private function nextSchedule($classes): ?array
    {
        $now = CarbonImmutable::now();
        $next = $classes->flatMap(fn ($class) => $class->schedules->map(function ($schedule) use ($class, $now) {
            $weekday = $schedule->normalizedWeekday();
            $daysAhead = ($weekday - $now->isoWeekday() + 7) % 7;
            $startsAt = $now->startOfDay()->addDays($daysAhead)->setTimeFromTimeString((string) $schedule->starts_at);
            if ($startsAt->lessThanOrEqualTo($now)) {
                $startsAt = $startsAt->addWeek();
            }
            if ($schedule->starts_on && $startsAt->startOfDay()->lessThan($schedule->starts_on)) {
                $startsAt = CarbonImmutable::parse($schedule->starts_on)->startOfDay();
                $startsAt = $startsAt->addDays(($weekday - $startsAt->isoWeekday() + 7) % 7)
                    ->setTimeFromTimeString((string) $schedule->starts_at);
            }
            if ($schedule->ends_on && $startsAt->startOfDay()->greaterThan($schedule->ends_on)) {
                return null;
            }

            return compact('class', 'schedule', 'startsAt', 'weekday');
        }))->filter()->sortBy('startsAt')->first();

        if (! $next) {
            return null;
        }

        return [
            'class_id' => $next['class']->id,
            'class_name' => $next['class']->name,
            'weekday' => $next['weekday'],
            'date' => $next['startsAt']->toDateString(),
            'starts_at' => substr((string) $next['schedule']->starts_at, 0, 5),
            'ends_at' => substr((string) $next['schedule']->ends_at, 0, 5),
        ];
    }

    public function classes(Request $request)
    {
        $teacher = $request->user()->teacherProfile;
        abort_unless($teacher, 403);
        $classes = $teacher->classes()
            ->with(['academicYear.parish', 'level', 'classroom', 'schedules'])
            ->withCount(['activeEnrollments as children_count'])
            ->paginate(15);

        return $this->success(
            CatechismClassResource::collection($classes),
            'Fetched classes',
            [
                'current_page' => $classes->currentPage(),
                'last_page' => $classes->lastPage(),
                'per_page' => $classes->perPage(),
                'total' => $classes->total(),
            ],
        );
    }

    public function dashboard(Request $request)
    {
        $teacher = $request->user()->teacherProfile;
        abort_unless($teacher, 403);
        $classes = $teacher->classes()
            ->with(['academicYear.parish', 'level', 'classroom', 'schedules'])
            ->withCount(['activeEnrollments as children_count'])
            ->get();
        $ids = $classes->pluck('id');
        $sessions = AttendanceSession::whereIn('catechism_class_id', $ids)
            ->with('catechismClass')
            ->latest('held_at')
            ->take(5)
            ->get();

        return $this->success([
            'teacher' => ['id' => $teacher->id, 'name' => $request->user()->name],
            'summary' => [
                'class_count' => $classes->count(),
                'child_count' => $classes->sum('children_count'),
                'pending_leave_requests' => 0,
                'today_sessions' => 0,
            ],
            'classes' => CatechismClassResource::collection($classes),
            'recent_attendance_sessions' => $sessions,
            'upcoming_sessions' => $classes->flatMap->schedules,
            'recent_announcements' => [],
        ]);
    }
}
