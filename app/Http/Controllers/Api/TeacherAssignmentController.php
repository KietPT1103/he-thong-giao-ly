<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Learning\UpsertAssignmentRequest;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\QuestionBankItem;
use App\Services\AssignmentLifecycleService;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TeacherAssignmentController extends ApiController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AssignmentLifecycleService $lifecycle,
    ) {}

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
        if ($assignment->recipients()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Đề và người nhận đã được khóa sau khi phát hành. Hãy dùng thao tác đổi hạn hoặc cấp ngoại lệ.',
                'code' => 'ASSIGNMENT_PUBLISHED_LOCKED',
            ], 422);
        }
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

    public function publish(Request $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);
        try {
            $published = $this->lifecycle->publish($request, $assignment);
        } catch (DomainException $exception) {
            $messages = [
                'ASSIGNMENT_ALREADY_PUBLISHED' => 'Bài tập đã được phát hành.',
                'ASSIGNMENT_INCOMPLETE' => 'Bài tập cần có câu hỏi và đối tượng nhận.',
                'NO_ACTIVE_RECIPIENTS' => 'Không có Thiếu nhi đang học trong đối tượng đã chọn.',
            ];

            return response()->json([
                'success' => false,
                'message' => $messages[$exception->getMessage()] ?? 'Không thể phát hành bài tập.',
                'code' => $exception->getMessage(),
            ], 422);
        }

        return $this->success($published, 'Đã phát hành bài tập.');
    }

    public function changeDueDate(Request $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);
        $data = $request->validate(['due_at' => ['required', 'date', 'after:now']]);
        try {
            $updated = $this->lifecycle->changeDueDate($request, $assignment, $data['due_at']);
        } catch (DomainException $exception) {
            return $this->lifecycleError($exception);
        }

        return $this->success($updated, 'Đã cập nhật hạn nộp và thông báo đến Thiếu nhi.');
    }

    public function accommodate(Request $request, Assignment $assignment, int $child)
    {
        $this->authorize('update', $assignment);
        $data = $request->validate([
            'due_at' => ['nullable', 'date', 'after:now'],
            'extra_attempts' => ['required', 'integer', 'min:0', 'max:20'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        try {
            $accommodation = $this->lifecycle->accommodate($request, $assignment, $child, $data);
        } catch (DomainException $exception) {
            return $this->lifecycleError($exception);
        }

        return $this->success($accommodation, 'Đã cập nhật ngoại lệ cho Thiếu nhi.');
    }

    public function close(Request $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);
        try {
            $closed = $this->lifecycle->close($request, $assignment);
        } catch (DomainException $exception) {
            return $this->lifecycleError($exception);
        }

        return $this->success($closed, 'Đã đóng bài tập.');
    }

    public function withdraw(Request $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        try {
            $withdrawn = $this->lifecycle->withdraw($request, $assignment, $data['reason']);
        } catch (DomainException $exception) {
            return $this->lifecycleError($exception);
        }

        return $this->success($withdrawn, 'Đã thu hồi bài tập.');
    }

    private function lifecycleError(DomainException $exception)
    {
        $messages = [
            'ASSIGNMENT_NOT_ACTIVE' => 'Bài tập không còn hoạt động.',
            'NOT_A_RECIPIENT' => 'Thiếu nhi không thuộc danh sách nhận bài.',
            'ASSIGNMENT_CANNOT_CLOSE' => 'Trạng thái hiện tại không cho phép đóng bài.',
            'ASSIGNMENT_CANNOT_WITHDRAW' => 'Bài tập đã được thu hồi hoặc lưu trữ.',
        ];

        return response()->json([
            'success' => false,
            'message' => $messages[$exception->getMessage()] ?? 'Không thể cập nhật bài tập.',
            'code' => $exception->getMessage(),
        ], 422);
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
                'position', 'options', 'accepted_answers', 'rubric', 'settings',
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
            'targets.child:id,code,full_name', 'recipients.child:id,code,full_name', 'files',
        ])->loadCount(['recipients', 'submissions']);
    }
}
