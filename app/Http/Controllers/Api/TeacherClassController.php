<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Teacher\StoreTeacherClassEnrollmentRequest;
use App\Http\Requests\Teacher\StoreTeacherClassRequest;
use App\Http\Requests\Teacher\UpdateTeacherClassEnrollmentRequest;
use App\Http\Requests\Teacher\UpdateTeacherClassRequest;
use App\Http\Resources\CatechismClassResource;
use App\Http\Resources\ChildResource;
use App\Models\AcademicYear;
use App\Models\CatechismClass;
use App\Models\CatechismLevel;
use App\Models\Child;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Services\AuditLogger;
use App\Services\TeacherClassEnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeacherClassController extends ApiController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly TeacherClassEnrollmentService $enrollmentService,
    ) {}

    public function workspace(Request $request, int $class)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['studying', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $catechismClass = CatechismClass::findOrFail($class);
        $this->authorize('view', $catechismClass);
        $this->loadDetails($catechismClass);
        $search = trim((string) ($data['search'] ?? ''));
        $children = $catechismClass->children()
            ->with('user:id,email,avatar_path')
            ->when($search, fn ($query) => $query->where(fn ($filtered) => $filtered
                ->where('children.full_name', 'like', "%{$search}%")
                ->orWhere('children.code', 'like', "%{$search}%")
                ->orWhere('children.saint_name', 'like', "%{$search}%")))
            ->when($data['status'] ?? null, fn ($query, $status) => $query
                ->where('children.status', $status))
            ->orderBy('children.full_name')
            ->paginate(15);

        return $this->success([
            'class' => (new CatechismClassResource($catechismClass))->resolve($request),
            'children' => ChildResource::collection($children->getCollection())->resolve($request),
            'children_meta' => [
                'current_page' => $children->currentPage(),
                'last_page' => $children->lastPage(),
                'per_page' => $children->perPage(),
                'total' => $children->total(),
            ],
        ], 'Đã tải không gian lớp học.');
    }

    public function options(Request $request)
    {
        $teacher = $request->user()->teacherProfile;
        abort_unless($teacher && $request->user()->can('view-classes'), 403);
        $parish = $teacher->parish;

        return $this->success([
            'parishes' => [[
                'id' => $parish->id,
                'name' => $parish->name,
                'code' => $parish->code,
            ]],
            'academic_years' => AcademicYear::query()
                ->where('parish_id', $parish->id)
                ->orderByDesc('is_current')
                ->orderByDesc('starts_on')
                ->get(['id', 'parish_id', 'name', 'starts_on', 'ends_on', 'is_current']),
            'levels' => CatechismLevel::query()
                ->where('parish_id', $parish->id)
                ->orderBy('sort_order')
                ->get(['id', 'parish_id', 'name', 'code', 'sort_order']),
            'classrooms' => Classroom::query()
                ->where('parish_id', $parish->id)
                ->orderBy('name')
                ->get(['id', 'parish_id', 'name', 'capacity']),
        ], 'Đã tải danh mục lớp học.');
    }

    public function store(StoreTeacherClassRequest $request)
    {
        $this->authorize('create', CatechismClass::class);
        $teacher = $request->user()->teacherProfile;

        $class = DB::transaction(function () use ($request, $teacher) {
            $class = CatechismClass::create($request->validated());
            $class->teachers()->attach($teacher->id, ['role' => 'primary']);
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

        return $this->success(new CatechismClassResource($class), 'Đã tạo lớp học.', [], 201);
    }

    public function update(UpdateTeacherClassRequest $request, int $class)
    {
        $catechismClass = CatechismClass::findOrFail($class);
        $this->authorize('update', $catechismClass);
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

        return $this->success(new CatechismClassResource($catechismClass), 'Đã cập nhật lớp học.');
    }

    public function destroy(Request $request, int $class)
    {
        $catechismClass = CatechismClass::findOrFail($class);
        $this->authorize('delete', $catechismClass);

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

    public function enrollmentOptions(Request $request, int $class)
    {
        $catechismClass = CatechismClass::with('academicYear')->findOrFail($class);
        $this->authorize('manageEnrollments', $catechismClass);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim((string) ($data['search'] ?? ''));
        $children = Child::query()
            ->select(['id', 'parish_id', 'code', 'full_name', 'saint_name', 'date_of_birth', 'status'])
            ->where('parish_id', $catechismClass->academicYear->parish_id)
            ->where('status', 'studying')
            ->when($search, fn ($query) => $query->where(fn ($filtered) => $filtered
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('saint_name', 'like', "%{$search}%")))
            ->withExists(['enrollments as has_current_class' => fn ($query) => $query
                ->where('status', Enrollment::STATUS_ACTIVE)
                ->whereHas('catechismClass', fn ($classQuery) => $classQuery
                    ->where('academic_year_id', $catechismClass->academic_year_id))])
            ->orderBy('has_current_class')
            ->orderBy('full_name')
            ->limit(30)
            ->with('user:id,avatar_path')
            ->get();
        $currentEnrollments = Enrollment::query()
            ->whereIn('child_id', $children->pluck('id'))
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->whereHas('catechismClass', fn ($query) => $query
                ->where('academic_year_id', $catechismClass->academic_year_id))
            ->with('catechismClass:id,name,code,academic_year_id')
            ->get()
            ->keyBy('child_id');
        $teacher = $request->user()->teacherProfile;
        $transferClasses = $teacher->classes()
            ->wherePivot('role', 'primary')
            ->where('catechism_classes.academic_year_id', $catechismClass->academic_year_id)
            ->where('catechism_classes.status', 'active')
            ->whereKeyNot($catechismClass->id)
            ->orderBy('catechism_classes.name')
            ->get(['catechism_classes.id', 'name', 'code'])
            ->map(fn (CatechismClass $target) => $target->only(['id', 'name', 'code']))
            ->values();

        return $this->success([
            'children' => $children->map(function (Child $child) use ($currentEnrollments) {
                $currentClass = $currentEnrollments->get($child->id)?->catechismClass;

                return [
                    'id' => $child->id,
                    'code' => $child->code,
                    'full_name' => $child->full_name,
                    'saint_name' => $child->saint_name,
                    'date_of_birth' => $child->date_of_birth?->toDateString(),
                    'status' => $child->status,
                    'avatar_url' => $child->user?->avatarUrl(),
                    'current_class' => $currentClass ? $currentClass->only(['id', 'name', 'code']) : null,
                ];
            })->values(),
            'transfer_classes' => $transferClasses,
        ], 'Đã tải danh sách thiếu nhi có thể xếp lớp.');
    }

    public function storeEnrollment(
        StoreTeacherClassEnrollmentRequest $request,
        int $class,
    ) {
        $catechismClass = CatechismClass::findOrFail($class);
        $this->authorize('manageEnrollments', $catechismClass);

        $result = DB::transaction(function () use ($request, $catechismClass) {
            $result = $this->enrollmentService->enroll(
                $catechismClass,
                $request->integer('child_id'),
            );
            if (! isset($result['error'])) {
                $this->auditLogger->record(
                    $request,
                    'class.child_enrolled',
                    $catechismClass,
                    null,
                    ['child_id' => $request->integer('child_id')],
                );
            }

            return $result;
        });
        if (isset($result['error'])) {
            return $this->enrollmentError($result['error']);
        }

        return $this->success(
            $this->enrollmentPayload($result['enrollment']),
            'Đã thêm thiếu nhi vào lớp.',
            [],
            201,
        );
    }

    public function updateEnrollment(
        UpdateTeacherClassEnrollmentRequest $request,
        int $class,
        int $child,
    ) {
        $source = CatechismClass::findOrFail($class);
        $this->authorize('manageEnrollments', $source);
        $action = $request->string('action')->toString();
        $target = null;
        if ($action === 'transfer') {
            $target = CatechismClass::findOrFail($request->integer('target_class_id'));
            $this->authorize('manageEnrollments', $target);
        }

        $result = DB::transaction(function () use ($request, $source, $target, $child, $action) {
            $result = $target
                ? $this->enrollmentService->transfer(
                    $source,
                    $target,
                    $child,
                    $request->user()->id,
                )
                : $this->enrollmentService->end(
                    $source,
                    $child,
                    $action,
                    $request->user()->id,
                );
            if (! isset($result['error'])) {
                $auditAction = match ($action) {
                    'transfer' => 'class.child_transferred',
                    'stop' => 'class.child_stopped',
                    default => 'class.child_removed',
                };
                $this->auditLogger->record(
                    $request,
                    $auditAction,
                    $source,
                    ['child_id' => $child, 'class_id' => $source->id],
                    ['child_id' => $child, 'target_class_id' => $target?->id, 'action' => $action],
                );
            }

            return $result;
        });
        if (isset($result['error'])) {
            return $this->enrollmentError($result['error']);
        }
        if ($target) {
            return $this->success([
                'source' => $this->enrollmentPayload($result['source']),
                'target' => $this->enrollmentPayload($result['target']),
            ], 'Đã chuyển thiếu nhi sang lớp mới.');
        }

        return $this->success(
            $this->enrollmentPayload($result['enrollment']),
            $action === 'stop' ? 'Đã ghi nhận thiếu nhi thôi học.' : 'Đã gỡ thiếu nhi khỏi lớp.',
        );
    }

    private function loadDetails(CatechismClass $class): void
    {
        $class->load([
            'academicYear.parish:id,name,code',
            'level:id,parish_id,name,code,sort_order',
            'classroom:id,parish_id,name,capacity',
            'schedules:id,catechism_class_id,weekday,starts_at,ends_at,starts_on,ends_on',
            'teachers.user:id,name,email,status,deleted_at',
        ])->loadCount([
            'activeEnrollments as children_count',
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

    private function enrollmentPayload(Enrollment $enrollment): array
    {
        return [
            'id' => $enrollment->id,
            'child_id' => $enrollment->child_id,
            'catechism_class_id' => $enrollment->catechism_class_id,
            'status' => $enrollment->status,
            'ended_at' => $enrollment->ended_at?->toISOString(),
            'ended_reason' => $enrollment->ended_reason,
            'child' => [
                'id' => $enrollment->child->id,
                'code' => $enrollment->child->code,
                'full_name' => $enrollment->child->full_name,
            ],
        ];
    }

    private function enrollmentError(array $error)
    {
        return response()->json(['success' => false, ...$error], 422);
    }
}
