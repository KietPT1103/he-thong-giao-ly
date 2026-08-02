<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Requests\Admin\AssignParishTeachersRequest;
use App\Http\Requests\Admin\StoreParishRequest;
use App\Http\Requests\Admin\UpdateParishRequest;
use App\Http\Resources\ParishResource;
use App\Models\Parish;
use App\Models\TeacherProfile;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AdminParishController extends ApiController
{
    private const DEPENDENCY_RELATIONS = [
        'teachers',
        'children',
        'academicYears',
        'levels',
        'classrooms',
        'announcements',
    ];

    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function index(AdminIndexRequest $request)
    {
        $parishes = Parish::query()
            ->withCount(self::DEPENDENCY_RELATIONS)
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) =>
                $query->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            ParishResource::collection($parishes->getCollection()),
            'Đã tải danh sách giáo xứ.',
            [
                'current_page' => $parishes->currentPage(),
                'last_page' => $parishes->lastPage(),
                'per_page' => $parishes->perPage(),
                'total' => $parishes->total(),
            ],
        );
    }

    public function show(Parish $parish)
    {
        $this->loadDetails($parish);

        return $this->success(new ParishResource($parish), 'Đã tải thông tin giáo xứ.');
    }

    public function store(StoreParishRequest $request)
    {
        $parish = Parish::create($request->validated());
        $this->auditLogger->record(
            $request,
            'parish.created',
            $parish,
            null,
            $this->auditPayload($parish),
        );
        $parish->loadCount(self::DEPENDENCY_RELATIONS);

        return $this->success(new ParishResource($parish), 'Đã tạo giáo xứ.', [], 201);
    }

    public function update(UpdateParishRequest $request, Parish $parish)
    {
        $oldValues = $this->auditPayload($parish);
        $parish->update($request->validated());
        $this->auditLogger->record(
            $request,
            'parish.updated',
            $parish,
            $oldValues,
            $this->auditPayload($parish),
        );
        $parish->loadCount(self::DEPENDENCY_RELATIONS);

        return $this->success(new ParishResource($parish), 'Đã cập nhật giáo xứ.');
    }

    public function assignTeachers(AssignParishTeachersRequest $request, Parish $parish)
    {
        $teacherIds = $request->validated('teacher_ids');

        DB::transaction(function () use ($request, $parish, $teacherIds) {
            $teachers = TeacherProfile::query()
                ->with('parish:id,name')
                ->whereKey($teacherIds)
                ->lockForUpdate()
                ->get();

            foreach ($teachers as $teacher) {
                if ($teacher->parish_id === $parish->id) {
                    continue;
                }

                $oldValues = [
                    'parish_id' => $teacher->parish_id,
                    'parish_name' => $teacher->parish->name,
                ];
                $teacher->update(['parish_id' => $parish->id]);
                $this->auditLogger->record(
                    $request,
                    'teacher.parish_changed',
                    $teacher,
                    $oldValues,
                    ['parish_id' => $parish->id, 'parish_name' => $parish->name],
                );
            }
        });

        $this->loadDetails($parish);

        return $this->success(new ParishResource($parish), 'Đã phân giáo lý viên vào giáo xứ.');
    }

    public function destroy(AdminIndexRequest $request, Parish $parish)
    {
        $parish->loadCount(self::DEPENDENCY_RELATIONS);
        $dependencyCounts = $this->dependencyCounts($parish);

        if (array_sum($dependencyCounts) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa giáo xứ khi vẫn còn dữ liệu liên quan.',
                'code' => 'PARISH_HAS_DEPENDENCIES',
                'data' => ['dependency_counts' => $dependencyCounts],
            ], 422);
        }

        DB::transaction(function () use ($request, $parish) {
            $snapshot = $this->auditPayload($parish);
            $parish->delete();
            $this->auditLogger->record($request, 'parish.deleted', $parish, $snapshot);
        });

        return $this->success(null, 'Đã xóa giáo xứ.');
    }

    private function loadDetails(Parish $parish): void
    {
        $parish->loadCount(self::DEPENDENCY_RELATIONS)->load([
            'teachers' => fn ($query) => $query
                ->with('user:id,name,email,status')
                ->orderBy('code'),
        ]);
    }

    private function auditPayload(Parish $parish): array
    {
        return [
            'name' => $parish->name,
            'parish_code' => $parish->code,
            'phone' => $parish->phone,
            'email' => $parish->email,
        ];
    }

    private function dependencyCounts(Parish $parish): array
    {
        return [
            'teachers' => (int) $parish->teachers_count,
            'children' => (int) $parish->children_count,
            'academic_years' => (int) $parish->academic_years_count,
            'levels' => (int) $parish->levels_count,
            'classrooms' => (int) $parish->classrooms_count,
            'announcements' => (int) $parish->announcements_count,
        ];
    }
}
