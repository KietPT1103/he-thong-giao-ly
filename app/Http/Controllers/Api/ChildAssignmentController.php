<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Learning\SaveSubmissionAnswersRequest;
use App\Models\Assignment;
use App\Models\Submission;
use App\Services\AuditLogger;
use App\Services\SubmissionService;
use DomainException;
use Illuminate\Http\Request;

class ChildAssignmentController extends ApiController
{
    public function __construct(
        private readonly SubmissionService $submissions,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('view-assignments') && $request->user()->child, 403);
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $childId = $request->user()->child->id;
        $search = trim((string) ($data['search'] ?? ''));
        $assignments = Assignment::query()
            ->whereHas('recipients', fn ($query) => $query->where('child_id', $childId))
            ->whereNotIn('status', [Assignment::STATUS_DRAFT, Assignment::STATUS_ARCHIVED])
            ->when($search, fn ($query) => $query->where('title', 'like', "%{$search}%"))
            ->with([
                'targets.catechismClass:id,name,code',
                'submissions' => fn ($query) => $query->where('child_id', $childId)->latest('attempt_number'),
            ])
            ->withCount('questions')->orderBy('due_at')->paginate(15);

        return $this->success($assignments, 'Đã tải bài tập của em.');
    }

    public function show(Request $request, Assignment $assignment)
    {
        $this->authorize('view', $assignment);
        $childId = $request->user()->child->id;
        $assignment->load([
            'questions', 'targets.catechismClass:id,name,code', 'files',
            'submissions' => fn ($query) => $query->where('child_id', $childId)
                ->with(['answers', 'files'])->latest('attempt_number'),
        ]);

        return $this->success($this->safeAssignment($assignment, $childId), 'Đã tải nội dung bài tập.');
    }

    public function start(Request $request, Assignment $assignment)
    {
        $this->authorize('submit', $assignment);
        try {
            [$submission, $created] = $this->submissions->start($assignment, $request->user()->child->id);
        } catch (DomainException $exception) {
            return $this->domainError($exception);
        }

        return $this->success($submission, $created ? 'Đã bắt đầu lượt làm.' : 'Đã tiếp tục lượt làm.', [], $created ? 201 : 200);
    }

    public function saveAnswers(SaveSubmissionAnswersRequest $request, Submission $submission)
    {
        try {
            $saved = $this->submissions->autosave($submission, $request->validated());
        } catch (DomainException $exception) {
            return $this->domainError($exception, $exception->getMessage() === 'VERSION_CONFLICT' ? 409 : 422);
        }

        return $this->success([
            'id' => $saved->id,
            'version' => $saved->version,
            'status' => $saved->status,
            'saved_at' => $saved->updated_at,
        ], 'Đã tự động lưu câu trả lời.');
    }

    public function submit(Request $request, Submission $submission)
    {
        $this->authorize('update', $submission);
        try {
            $submitted = $this->submissions->submit($submission);
        } catch (DomainException $exception) {
            return $this->domainError($exception);
        }
        $this->auditLogger->record($request, 'assignment.submitted', $submitted, null, [
            'assignment_id' => $submitted->assignment_id,
            'attempt_number' => $submitted->attempt_number,
            'is_late' => $submitted->is_late,
        ]);

        return $this->success($submitted, 'Đã nộp bài.');
    }

    private function safeAssignment(Assignment $assignment, int $childId): array
    {
        $payload = $assignment->toArray();
        $released = $assignment->submissions->contains('status', Submission::STATUS_RELEASED);
        $payload['questions'] = $assignment->questions->map(function ($question) use ($assignment, $released) {
            $item = $question->only(['id', 'type', 'prompt', 'points', 'position']);
            if ($question->options) {
                $item['options'] = collect($question->options)->values()->map(function ($option, $index) use ($assignment, $released) {
                    $safe = ['id' => $index, 'content' => $option['content']];
                    if ($released && $assignment->show_answers) {
                        $safe['is_correct'] = (bool) $option['is_correct'];
                    }

                    return $safe;
                })->all();
            }
            if ($question->type === 'essay') {
                $item['rubric'] = $question->rubric;
            }
            if ($released && $assignment->show_answers) {
                $item['explanation'] = $question->explanation;
            }

            return $item;
        })->all();
        $payload['submissions'] = $assignment->submissions->map(function (Submission $submission) {
            $safe = $submission->only(['id', 'attempt_number', 'status', 'started_at', 'submitted_at', 'is_late']);
            if ($submission->status === Submission::STATUS_RELEASED) {
                $safe += $submission->only(['auto_score', 'manual_score', 'final_score', 'general_feedback', 'graded_at', 'released_at']);
                $safe['answers'] = $submission->answers->map->only([
                    'assignment_question_id', 'auto_score', 'manual_score', 'rubric_scores', 'feedback', 'graded_at',
                ])->all();
            } elseif (in_array($submission->status, [Submission::STATUS_IN_PROGRESS, Submission::STATUS_REOPENED], true)) {
                $safe['version'] = $submission->version;
                $safe['answers'] = $submission->answers->map->only([
                    'assignment_question_id', 'answer', 'saved_at',
                ])->all();
            }
            $safe['files'] = $submission->files;

            return $safe;
        })->all();
        $payload['recipient'] = $assignment->recipients()->where('child_id', $childId)->first();

        return $payload;
    }

    private function domainError(DomainException $exception, int $status = 422)
    {
        $messages = [
            'NOT_A_RECIPIENT' => 'Em không thuộc danh sách nhận bài.',
            'ASSIGNMENT_NOT_OPEN' => 'Bài tập chưa đến thời gian mở.',
            'ASSIGNMENT_OVERDUE' => 'Bài tập đã hết hạn nộp.',
            'ATTEMPT_LIMIT_REACHED' => 'Em đã dùng hết số lượt làm.',
            'VERSION_CONFLICT' => 'Bài làm đã được cập nhật ở nơi khác.',
            'SUBMISSION_LOCKED' => 'Bài làm không còn cho phép chỉnh sửa.',
            'QUESTION_NOT_IN_ASSIGNMENT' => 'Câu hỏi không thuộc bài tập này.',
        ];

        return response()->json([
            'success' => false,
            'message' => $messages[$exception->getMessage()] ?? 'Không thể cập nhật bài làm.',
            'code' => $exception->getMessage(),
        ], $status);
    }
}
