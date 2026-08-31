<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Learning\UpsertAssignmentRequest;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\QuestionBankItem;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TeacherAssignmentController extends ApiController
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Assignment::class);
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,scheduled,published,closed,grading,released,archived,withdrawn'],
            'class_id' => ['nullable', 'integer', 'exists:catechism_classes,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $teacherId = $request->user()->teacherProfile->id;
        $search = trim((string) ($data['search'] ?? ''));
        $assignments = Assignment::query()
            ->whereHas('targets.catechismClass.teachers', fn ($query) => $query->where('teacher_profiles.id', $teacherId))
            ->when($search, fn ($query) => $query->where(fn ($filtered) => $filtered
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['class_id'] ?? null, fn ($query, $classId) => $query
                ->whereHas('targets', fn ($targets) => $targets->where('catechism_class_id', $classId)))
            ->with(['targets.catechismClass:id,name,code', 'creator:id,name'])
            ->withCount(['questions', 'recipients', 'submissions'])
            ->latest()->paginate(15);

        return $this->success($assignments, 'Đã tải danh sách bài tập.');
    }

    public function store(UpsertAssignmentRequest $request)
    {
        $data = $request->validated();
        if ($error = $this->targetScopeError($request, $data['targets'])) {
            return $error;
        }

        $assignment = DB::transaction(function () use ($request, $data) {
            $assignment = Assignment::create([
                ...Arr::except($data, ['targets', 'questions', 'version']),
                'created_by' => $request->user()->id,
            ]);
            $this->syncStructure($request, $assignment, $data);
            $this->auditLogger->record($request, 'assignment.created', $assignment, null, [
                'title' => $assignment->title, 'target_count' => count($data['targets']),
                'question_count' => count($data['questions']),
            ]);

            return $assignment;
        });

        return $this->success($this->detail($assignment->refresh()), 'Đã tạo bản nháp bài tập.', [], 201);
    }

    public function show(Request $request, Assignment $assignment)
    {
        $this->authorize('view', $assignment);

        return $this->success($this->detail($assignment), 'Đã tải bài tập.');
    }

    public function update(UpsertAssignmentRequest $request, Assignment $assignment)
    {
        $data = $request->validated();
        if (($data['version'] ?? $assignment->version) !== $assignment->version) {
            return response()->json([
                'success' => false, 'message' => 'Bài tập đã được cập nhật ở nơi khác.',
                'code' => 'VERSION_CONFLICT',
            ], 409);
        }
        if ($assignment->submissions()->exists() && $request->has('questions')) {
            return response()->json([
                'success' => false, 'message' => 'Không thể sửa đề sau khi đã có lượt làm.',
                'code' => 'ASSIGNMENT_CONTENT_LOCKED',
            ], 422);
        }
        if ($error = $this->targetScopeError($request, $data['targets'])) {
            return $error;
        }

        DB::transaction(function () use ($request, $assignment, $data) {
            $old = $assignment->only(['title', 'status', 'due_at', 'version']);
            $assignment->update([
                ...Arr::except($data, ['targets', 'questions', 'version']),
                'version' => $assignment->version + 1,
            ]);
            $this->syncStructure($request, $assignment, $data);
            $this->auditLogger->record($request, 'assignment.updated', $assignment, $old, $assignment->only([
                'title', 'status', 'due_at', 'version',
            ]));
        });

        return $this->success($this->detail($assignment->fresh()), 'Đã cập nhật bài tập.');
    }

    public function destroy(Request $request, Assignment $assignment)
    {
        $this->authorize('archive', $assignment);
        if ($assignment->status !== Assignment::STATUS_DRAFT || $assignment->submissions()->exists()) {
            $assignment->update(['status' => Assignment::STATUS_ARCHIVED]);
        } else {
            $assignment->delete();
        }
        $this->auditLogger->record($request, 'assignment.archived', $assignment);

        return $this->success(null, 'Đã lưu trữ bài tập.');
    }

    private function syncStructure(Request $request, Assignment $assignment, array $data): void
    {
        $assignment->questions()->delete();
        foreach ($data['questions'] as $question) {
            if (! empty($question['source_question_id'])) {
                $source = QuestionBankItem::findOrFail($question['source_question_id']);
                abort_unless($request->user()->can('view', $source), 403);
            }
            $assignment->questions()->create(Arr::only($question, [
                'source_question_id', 'type', 'prompt', 'explanation', 'points',
                'position', 'options', 'accepted_answers', 'rubric',
            ]));
        }
        $assignment->targets()->delete();
        foreach ($data['targets'] as $target) {
            $children = $target['child_ids'];
            if ($children === []) {
                $children = [null];
            }
            foreach ($children as $childId) {
                $assignment->targets()->create([
                    'catechism_class_id' => $target['catechism_class_id'],
                    'child_id' => $childId,
                    'due_at' => $target['due_at'] ?? null,
                    'attempt_limit' => $target['attempt_limit'] ?? null,
                ]);
            }
        }
    }

    private function targetScopeError(Request $request, array $targets)
    {
        $classIds = collect($targets)->pluck('catechism_class_id')->unique()->values();
        $assigned = $request->user()->teacherProfile->classes()->whereIn('catechism_classes.id', $classIds)->pluck('catechism_classes.id');
        if ($assigned->count() !== $classIds->count()) {
            return response()->json([
                'success' => false, 'message' => 'Bạn chỉ có thể giao bài cho lớp đang phụ trách.',
                'code' => 'CLASS_NOT_ASSIGNED',
            ], 422);
        }
        foreach ($targets as $target) {
            if (($target['child_ids'] ?? []) === []) {
                continue;
            }
            $validCount = Enrollment::query()
                ->where('catechism_class_id', $target['catechism_class_id'])
                ->where('status', Enrollment::STATUS_ACTIVE)
                ->whereIn('child_id', $target['child_ids'])->count();
            if ($validCount !== count(array_unique($target['child_ids']))) {
                return response()->json([
                    'success' => false, 'message' => 'Thiếu nhi được chọn không thuộc lớp.',
                    'code' => 'CHILD_NOT_IN_CLASS',
                ], 422);
            }
        }

        return null;
    }

    private function detail(Assignment $assignment): Assignment
    {
        return $assignment->load([
            'creator:id,name', 'questions', 'targets.catechismClass:id,name,code',
            'targets.child:id,code,full_name',
        ])->loadCount(['recipients', 'submissions']);
    }
}
