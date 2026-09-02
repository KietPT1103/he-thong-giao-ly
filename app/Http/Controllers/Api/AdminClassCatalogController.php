<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Admin\ClassCatalogIndexRequest;
use App\Http\Requests\Admin\UpsertAcademicYearRequest;
use App\Http\Requests\Admin\UpsertCatechismLevelRequest;
use App\Http\Requests\Admin\UpsertClassroomRequest;
use App\Models\AcademicYear;
use App\Models\CatechismLevel;
use App\Models\Classroom;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AdminClassCatalogController extends ApiController
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(ClassCatalogIndexRequest $request): JsonResponse
    {
        $parishId = $request->integer('parish_id');

        return $this->success([
            'academic_years' => AcademicYear::query()
                ->where('parish_id', $parishId)
                ->withCount(['classes' => fn ($query) => $query->withTrashed()])
                ->orderByDesc('is_current')
                ->orderByDesc('starts_on')
                ->get()
                ->map(fn (AcademicYear $year) => $this->academicYearData($year)),
            'levels' => CatechismLevel::query()
                ->where('parish_id', $parishId)
                ->withCount(['classes' => fn ($query) => $query->withTrashed()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (CatechismLevel $level) => $this->levelData($level)),
            'classrooms' => Classroom::query()
                ->where('parish_id', $parishId)
                ->withCount(['classes' => fn ($query) => $query->withTrashed()])
                ->orderBy('name')
                ->get()
                ->map(fn (Classroom $classroom) => $this->classroomData($classroom)),
        ], 'Đã tải danh mục lớp học.');
    }

    public function storeAcademicYear(UpsertAcademicYearRequest $request): JsonResponse
    {
        $year = DB::transaction(function () use ($request): AcademicYear {
            $data = $request->validated();
            if ($data['is_current']) {
                AcademicYear::where('parish_id', $data['parish_id'])->update(['is_current' => false]);
            }
            $year = AcademicYear::create($data);
            $this->auditLogger->record($request, 'academic_year.created', $year, null, $this->academicYearAudit($year));

            return $year;
        });

        return $this->success($this->academicYearData($year->loadCount('classes')), 'Đã tạo niên khóa.', [], 201);
    }

    public function updateAcademicYear(UpsertAcademicYearRequest $request, AcademicYear $academic_year): JsonResponse
    {
        DB::transaction(function () use ($request, $academic_year): void {
            $old = $this->academicYearAudit($academic_year);
            $data = Arr::except($request->validated(), 'parish_id');
            if ($data['is_current']) {
                AcademicYear::where('parish_id', $academic_year->parish_id)
                    ->whereKeyNot($academic_year->id)
                    ->update(['is_current' => false]);
            }
            $academic_year->update($data);
            $this->auditLogger->record($request, 'academic_year.updated', $academic_year, $old, $this->academicYearAudit($academic_year));
        });

        return $this->success($this->academicYearData($academic_year->refresh()->loadCount('classes')), 'Đã cập nhật niên khóa.');
    }

    public function destroyAcademicYear(Request $request, AcademicYear $academic_year): JsonResponse
    {
        abort_unless($request->user()?->can('delete-academic-years'), 403);

        return $this->destroyCatalog($request, $academic_year, 'academic_year', 'niên khóa', $this->academicYearAudit($academic_year));
    }

    public function storeLevel(UpsertCatechismLevelRequest $request): JsonResponse
    {
        $level = CatechismLevel::create($request->validated());
        $this->auditLogger->record($request, 'catechism_level.created', $level, null, $this->levelAudit($level));

        return $this->success($this->levelData($level->loadCount('classes')), 'Đã tạo khối giáo lý.', [], 201);
    }

    public function updateLevel(UpsertCatechismLevelRequest $request, CatechismLevel $catechism_level): JsonResponse
    {
        $old = $this->levelAudit($catechism_level);
        $catechism_level->update(Arr::except($request->validated(), 'parish_id'));
        $this->auditLogger->record($request, 'catechism_level.updated', $catechism_level, $old, $this->levelAudit($catechism_level));

        return $this->success($this->levelData($catechism_level->loadCount('classes')), 'Đã cập nhật khối giáo lý.');
    }

    public function destroyLevel(Request $request, CatechismLevel $catechism_level): JsonResponse
    {
        abort_unless($request->user()?->can('delete-levels'), 403);

        return $this->destroyCatalog($request, $catechism_level, 'catechism_level', 'khối giáo lý', $this->levelAudit($catechism_level));
    }

    public function storeClassroom(UpsertClassroomRequest $request): JsonResponse
    {
        $classroom = Classroom::create($request->validated());
        $this->auditLogger->record($request, 'classroom.created', $classroom, null, $this->classroomAudit($classroom));

        return $this->success($this->classroomData($classroom->loadCount('classes')), 'Đã tạo phòng học.', [], 201);
    }

    public function updateClassroom(UpsertClassroomRequest $request, Classroom $classroom): JsonResponse
    {
        $old = $this->classroomAudit($classroom);
        $classroom->update(Arr::except($request->validated(), 'parish_id'));
        $this->auditLogger->record($request, 'classroom.updated', $classroom, $old, $this->classroomAudit($classroom));

        return $this->success($this->classroomData($classroom->loadCount('classes')), 'Đã cập nhật phòng học.');
    }

    public function destroyClassroom(Request $request, Classroom $classroom): JsonResponse
    {
        abort_unless($request->user()?->can('delete-classrooms'), 403);

        return $this->destroyCatalog($request, $classroom, 'classroom', 'phòng học', $this->classroomAudit($classroom));
    }

    private function destroyCatalog(Request $request, Model $catalog, string $actionPrefix, string $label, array $snapshot): JsonResponse
    {
        $classesCount = $catalog->classes()->withTrashed()->count();
        if ($classesCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Không thể xóa {$label} đang được lớp học sử dụng. Hãy ngừng sử dụng nếu không muốn chọn cho lớp mới.",
                'code' => 'CLASS_CATALOG_IN_USE',
                'data' => ['classes_count' => $classesCount],
            ], 422);
        }

        DB::transaction(function () use ($request, $catalog, $actionPrefix, $snapshot): void {
            $catalog->delete();
            $this->auditLogger->record($request, "{$actionPrefix}.deleted", $catalog, $snapshot);
        });

        return $this->success(null, "Đã xóa {$label}.");
    }

    private function academicYearData(AcademicYear $year): array
    {
        return [
            'id' => $year->id,
            'parish_id' => $year->parish_id,
            'name' => $year->name,
            'starts_on' => $year->starts_on?->toDateString(),
            'ends_on' => $year->ends_on?->toDateString(),
            'is_current' => (bool) $year->is_current,
            'is_active' => (bool) $year->is_active,
            'classes_count' => (int) ($year->classes_count ?? $year->classes()->withTrashed()->count()),
        ];
    }

    private function levelData(CatechismLevel $level): array
    {
        return [
            'id' => $level->id,
            'parish_id' => $level->parish_id,
            'name' => $level->name,
            'code' => $level->code,
            'sort_order' => (int) $level->sort_order,
            'is_active' => (bool) $level->is_active,
            'classes_count' => (int) ($level->classes_count ?? $level->classes()->withTrashed()->count()),
        ];
    }

    private function classroomData(Classroom $classroom): array
    {
        return [
            'id' => $classroom->id,
            'parish_id' => $classroom->parish_id,
            'name' => $classroom->name,
            'capacity' => $classroom->capacity,
            'is_active' => (bool) $classroom->is_active,
            'classes_count' => (int) ($classroom->classes_count ?? $classroom->classes()->withTrashed()->count()),
        ];
    }

    private function academicYearAudit(AcademicYear $year): array
    {
        return Arr::only($year->attributesToArray(), ['parish_id', 'name', 'starts_on', 'ends_on', 'is_current', 'is_active']);
    }

    private function levelAudit(CatechismLevel $level): array
    {
        return [
            ...Arr::only($level->attributesToArray(), ['parish_id', 'name', 'sort_order', 'is_active']),
            'level_code' => $level->code,
        ];
    }

    private function classroomAudit(Classroom $classroom): array
    {
        return Arr::only($classroom->attributesToArray(), ['parish_id', 'name', 'capacity', 'is_active']);
    }
}
