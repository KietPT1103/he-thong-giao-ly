<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Admin\AssignClassTeachersRequest;
use App\Http\Requests\Admin\ClassIndexRequest;
use App\Http\Requests\Admin\StoreClassRequest;
use App\Http\Requests\Admin\UpdateClassEnrollmentsRequest;
use App\Http\Requests\Admin\UpdateClassRequest;
use App\Http\Requests\Admin\UpdateClassSchedulesRequest;
use App\Http\Resources\ClassResource;
use App\Models\AcademicYear;
use App\Models\CatechismClass;
use App\Models\CatechismLevel;
use App\Models\Child;
use App\Models\Classroom;
use App\Models\Parish;
use App\Models\TeacherProfile;
use App\Services\AuditLogger;
use App\Services\ClassEnrollmentService;
use App\Services\ClassScheduleService;
use App\Support\ScheduleConflict;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminClassController extends ApiController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ClassEnrollmentService $enrollmentService,
        private readonly ClassScheduleService $scheduleService,
    ) {}

    public function index(ClassIndexRequest $request)
    {
        $status = $request->string('status')->toString();
        $query = $status === 'archived'
            ? CatechismClass::onlyTrashed()
            : CatechismClass::query();

        $classes = $query
            ->with($this->listRelations())
            ->withCount([
                'activeEnrollments as enrollments_count',
                'teachers',
                'attendanceSessions',
            ])
            ->when($request->string('search')->toString(), fn (Builder $builder, string $search) => $builder->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->when($request->integer('parish_id'), fn (Builder $builder, int $parishId) => $builder->whereHas('academicYear', fn (Builder $year) => $year->where('parish_id', $parishId)))
            ->when($request->integer('academic_year_id'), fn (Builder $builder, int $yearId) => $builder->where('academic_year_id', $yearId))
            ->when($request->integer('catechism_level_id'), fn (Builder $builder, int $levelId) => $builder->where('catechism_level_id', $levelId))
            ->when(in_array($status, ['active', 'inactive'], true), fn (Builder $builder) => $builder->where('status', $status))
            ->orderByDesc(
                AcademicYear::select('is_current')
                    ->whereColumn('academic_years.id', 'catechism_classes.academic_year_id'),
            )
            ->orderBy(
                CatechismLevel::select('sort_order')
                    ->whereColumn('catechism_levels.id', 'catechism_classes.catechism_level_id'),
            )
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            ClassResource::collection($classes->getCollection()),
            'Đã tải danh sách lớp học.',
            [
                'current_page' => $classes->currentPage(),
                'last_page' => $classes->lastPage(),
                'per_page' => $classes->perPage(),
                'total' => $classes->total(),
            ],
        );
    }

    public function show(Request $request, int $class)
    {
        $request->validate(['include_archived' => ['nullable', 'boolean']]);
        $query = $request->boolean('include_archived')
            ? CatechismClass::withTrashed()
            : CatechismClass::query();
        $catechismClass = $query->findOrFail($class);
        $this->loadDetails($catechismClass);

        return $this->success(new ClassResource($catechismClass), 'Đã tải thông tin lớp học.');
    }

    public function options(Request $request)
    {
        $data = $request->validate([
            'parish_id' => ['nullable', 'integer', Rule::exists('parishes', 'id')],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $parishId = $data['parish_id'] ?? null;
        $search = trim((string) ($data['search'] ?? ''));

        return $this->success([
            'parishes' => Parish::query()->orderBy('name')->get(['id', 'name', 'code']),
            'academic_years' => $parishId ? AcademicYear::query()
                ->where('parish_id', $parishId)
                ->orderByDesc('is_current')
                ->orderByDesc('starts_on')
                ->get(['id', 'parish_id', 'name', 'starts_on', 'ends_on', 'is_current']) : [],
            'levels' => $parishId ? CatechismLevel::query()
                ->where('parish_id', $parishId)
                ->orderBy('sort_order')
                ->get(['id', 'parish_id', 'name', 'code', 'sort_order']) : [],
            'classrooms' => $parishId ? Classroom::query()
                ->where('parish_id', $parishId)
                ->orderBy('name')
                ->get(['id', 'parish_id', 'name', 'capacity']) : [],
            'teachers' => $parishId ? TeacherProfile::query()
                ->with('user:id,name,email,status,deleted_at')
                ->where('parish_id', $parishId)
                ->whereHas('user', fn (Builder $user) => $user
                    ->whereNull('deleted_at')
                    ->where('status', 'active')
                    ->when($search, fn (Builder $filtered) => $filtered
                        ->where(fn (Builder $inner) => $inner
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))))
                ->limit(50)
                ->get()
                ->map(fn (TeacherProfile $teacher) => [
                    'id' => $teacher->id,
                    'name' => $teacher->user->name,
                    'email' => $teacher->user->email,
                    'code' => $teacher->code,
                ])->values() : [],
            'children' => $parishId ? Child::query()
                ->where('parish_id', $parishId)
                ->where('status', 'studying')
                ->when($search, fn (Builder $builder) => $builder
                    ->where(fn (Builder $inner) => $inner
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")))
                ->orderBy('full_name')
                ->limit(50)
                ->get(['id', 'parish_id', 'full_name', 'code', 'status']) : [],
        ], 'Đã tải danh mục lớp học.');
    }

    public function store(StoreClassRequest $request)
    {
        $class = DB::transaction(function () use ($request) {
            $class = CatechismClass::create($request->validated());
            $this->auditLogger->record(
                $request,
                'class.created',
                $class,
                null,
                $this->auditPayload($class),
            );

            return $class;
        });
        $this->loadDetails($class);

        return $this->success(new ClassResource($class), 'Đã tạo lớp học.', [], 201);
    }

    public function update(UpdateClassRequest $request, int $class)
    {
        $catechismClass = CatechismClass::findOrFail($class);
        $data = $request->validated();

        if ($catechismClass->attendanceSessions()->exists()
            && ($catechismClass->academic_year_id !== $data['academic_year_id']
                || $catechismClass->catechism_level_id !== $data['catechism_level_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể đổi niên khóa hoặc khối khi lớp đã có lịch sử điểm danh.',
                'code' => 'CLASS_HISTORY_LOCKED',
            ], 422);
        }

        DB::transaction(function () use ($request, $catechismClass, $data) {
            $oldValues = $this->auditPayload($catechismClass);
            $catechismClass->update($data);
            $this->auditLogger->record(
                $request,
                'class.updated',
                $catechismClass,
                $oldValues,
                $this->auditPayload($catechismClass->fresh()),
            );
        });
        $this->loadDetails($catechismClass->refresh());

        return $this->success(new ClassResource($catechismClass), 'Đã cập nhật lớp học.');
    }

    public function destroy(Request $request, int $class)
    {
        $catechismClass = CatechismClass::findOrFail($class);
        DB::transaction(function () use ($request, $catechismClass) {
            $oldValues = $this->auditPayload($catechismClass);
            $catechismClass->delete();
            $this->auditLogger->record(
                $request,
                'class.archived',
                $catechismClass,
                $oldValues,
            );
        });

        return $this->success(null, 'Đã lưu trữ lớp học.');
    }

    public function restore(Request $request, int $class)
    {
        $catechismClass = CatechismClass::onlyTrashed()->findOrFail($class);
        DB::transaction(function () use ($request, $catechismClass) {
            $catechismClass->restore();
            $this->auditLogger->record(
                $request,
                'class.restored',
                $catechismClass,
                null,
                $this->auditPayload($catechismClass),
            );
        });
        $this->loadDetails($catechismClass);

        return $this->success(new ClassResource($catechismClass), 'Đã khôi phục lớp học.');
    }

    public function assignTeachers(AssignClassTeachersRequest $request, int $class)
    {
        $catechismClass = CatechismClass::with('academicYear')->findOrFail($class);
        $rows = collect($request->validated('teachers'));
        $teacherIds = $rows->pluck('teacher_id')->map(fn ($id) => (int) $id)->all();
        $teachers = TeacherProfile::query()
            ->with([
                'user',
                'classes' => fn ($query) => $query
                    ->whereKeyNot($catechismClass->id)
                    ->with('schedules'),
            ])
            ->whereIn('id', $teacherIds)
            ->get();
        $invalidIds = $teachers
            ->filter(fn (TeacherProfile $teacher) => $teacher->parish_id !== $catechismClass->academicYear->parish_id
                || $teacher->user->trashed()
                || $teacher->user->status !== 'active')
            ->pluck('id')
            ->merge(array_diff($teacherIds, $teachers->pluck('id')->all()))
            ->unique()
            ->values();

        if ($invalidIds->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Danh sách có giáo lý viên không hợp lệ hoặc khác giáo xứ.',
                'code' => 'INVALID_CLASS_TEACHERS',
                'data' => ['teacher_ids' => $invalidIds],
            ], 422);
        }

        $conflicts = $this->teacherScheduleConflicts($catechismClass, $teachers);
        if ($conflicts !== [] && ! $request->boolean('allow_teacher_conflicts')) {
            return response()->json([
                'success' => false,
                'message' => 'Một hoặc nhiều giáo lý viên đang có lịch dạy trùng.',
                'code' => 'TEACHER_SCHEDULE_CONFLICT',
                'data' => ['conflicts' => $conflicts],
            ], 422);
        }

        DB::transaction(function () use ($request, $catechismClass, $rows) {
            $oldValues = $catechismClass->teachers()
                ->get()
                ->map(fn ($teacher) => ['teacher_id' => $teacher->id, 'role' => $teacher->pivot->role])
                ->values()
                ->all();
            $sync = $rows->mapWithKeys(fn (array $row) => [
                (int) $row['teacher_id'] => ['role' => $row['role']],
            ])->all();
            $catechismClass->teachers()->sync($sync);
            $this->auditLogger->record(
                $request,
                'class.teachers_assigned',
                $catechismClass,
                $oldValues,
                $rows->values()->all(),
            );
        });
        $this->loadDetails($catechismClass->refresh());

        return $this->success(new ClassResource($catechismClass), 'Đã cập nhật giáo lý viên phụ trách.');
    }

    public function updateEnrollments(UpdateClassEnrollmentsRequest $request, int $class)
    {
        $catechismClass = CatechismClass::with(['academicYear', 'classroom'])->findOrFail($class);

        $result = DB::transaction(function () use ($request, $catechismClass) {
            $result = $this->enrollmentService->update(
                $catechismClass,
                $request->validated('enrollments'),
            );

            if (! isset($result['error'])) {
                $this->auditLogger->record(
                    $request,
                    'class.enrollments_updated',
                    $catechismClass,
                    $result['old'],
                    $result['new'],
                );
            }

            return $result;
        });

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                ...$result['error'],
            ], 422);
        }

        $this->loadDetails($catechismClass->refresh());

        return $this->success(new ClassResource($catechismClass), 'Đã cập nhật danh sách thiếu nhi.');
    }

    public function updateSchedules(UpdateClassSchedulesRequest $request, int $class)
    {
        $catechismClass = CatechismClass::with(['schedules', 'teachers.user'])->findOrFail($class);

        $result = DB::transaction(function () use ($request, $catechismClass) {
            $result = $this->scheduleService->update(
                $catechismClass,
                $request->validated('schedules'),
                $request->boolean('allow_teacher_conflicts'),
            );

            if (! isset($result['error'])) {
                $this->auditLogger->record(
                    $request,
                    'class.schedules_updated',
                    $catechismClass,
                    $result['old'],
                    $result['new'],
                );
            }

            return $result;
        });

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                ...$result['error'],
            ], 422);
        }

        $this->loadDetails($catechismClass->refresh());

        return $this->success(new ClassResource($catechismClass), 'Đã cập nhật lịch học.');
    }

    private function listRelations(): array
    {
        return [
            'academicYear.parish:id,name,code',
            'level:id,parish_id,name,code,sort_order',
            'classroom:id,parish_id,name,capacity',
            'schedules:id,catechism_class_id,weekday,starts_at,ends_at,starts_on,ends_on',
        ];
    }

    private function loadDetails(CatechismClass $class): void
    {
        $class->load([
            ...$this->listRelations(),
            'teachers.user:id,name,email,status,deleted_at',
            'enrollments.child:id,parish_id,code,full_name,status',
        ])->loadCount([
            'activeEnrollments as enrollments_count',
            'teachers',
            'attendanceSessions',
        ]);
    }

    private function auditPayload(CatechismClass $class): array
    {
        return $class->only([
            'name',
            'code',
            'academic_year_id',
            'catechism_level_id',
            'classroom_id',
            'status',
        ]);
    }

    private function teacherScheduleConflicts(CatechismClass $class, Collection $teachers): array
    {
        $class->loadMissing('schedules');
        if ($class->schedules->isEmpty() || $teachers->isEmpty()) {
            return [];
        }

        $conflicts = [];
        foreach ($teachers as $teacher) {
            foreach ($teacher->classes as $otherClass) {
                foreach ($class->schedules as $schedule) {
                    foreach ($otherClass->schedules as $otherSchedule) {
                        if (ScheduleConflict::overlaps(
                            $this->schedulePayload($schedule),
                            $this->schedulePayload($otherSchedule),
                        )) {
                            $conflicts["{$teacher->id}:{$otherClass->id}"] = [
                                'teacher_id' => $teacher->id,
                                'teacher_name' => $teacher->user->name,
                                'class_id' => $otherClass->id,
                                'class_name' => $otherClass->name,
                            ];
                        }
                    }
                }
            }
        }

        return array_values($conflicts);
    }

    private function schedulePayload($schedule): array
    {
        return [
            'weekday' => $schedule->weekday,
            'starts_at' => $schedule->starts_at,
            'ends_at' => $schedule->ends_at,
            'starts_on' => $schedule->starts_on?->toDateString(),
            'ends_on' => $schedule->ends_on?->toDateString(),
        ];
    }
}
