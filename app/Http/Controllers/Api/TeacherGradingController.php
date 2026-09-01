<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Learning\GradeSubmissionRequest;
use App\Models\Assignment;
use App\Models\Submission;
use App\Services\GradingService;
use DomainException;
use Illuminate\Http\Request;

class TeacherGradingController extends ApiController
{
    public function __construct(private readonly GradingService $grading) {}

    public function index(Request $request, Assignment $assignment)
    {
        $this->authorize('grade', $assignment);
        $data = $request->validate([
            'status' => ['nullable', 'in:in_progress,submitted,grading,graded,released,reopened'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim((string) ($data['search'] ?? ''));
        $submissions = $assignment->submissions()
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($search, fn ($query) => $query->whereHas('child', fn ($children) => $children
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->with(['child:id,code,full_name,user_id', 'answers.question', 'files'])
            ->latest('submitted_at')->paginate(15);

        return $this->success($submissions, 'Đã tải danh sách bài nộp.');
    }

    public function grade(GradeSubmissionRequest $request, Submission $submission)
    {
        try {
            $graded = $this->grading->grade($request, $submission, $request->validated());
        } catch (DomainException $exception) {
            return $this->error($exception, $exception->getMessage() === 'VERSION_CONFLICT' ? 409 : 422);
        }

        return $this->success($graded, 'Đã lưu điểm và nhận xét.');
    }

    public function release(Request $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);
        try {
            $released = $this->grading->release($request, $assignment);
        } catch (DomainException $exception) {
            return $this->error($exception);
        }

        return $this->success($released, 'Đã công bố kết quả.');
    }

    public function reopen(Request $request, Submission $submission)
    {
        $this->authorize('grade', $submission);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        try {
            $reopened = $this->grading->reopen($request, $submission, $data['reason']);
        } catch (DomainException $exception) {
            return $this->error($exception);
        }

        return $this->success($reopened, 'Đã mở lại bài làm cho Thiếu nhi.');
    }

    private function error(DomainException $exception, int $status = 422)
    {
        $messages = [
            'VERSION_CONFLICT' => 'Bài chấm đã được cập nhật ở nơi khác.',
            'SUBMISSION_NOT_READY_FOR_GRADING' => 'Bài nộp chưa sẵn sàng để chấm.',
            'QUESTION_NOT_IN_ASSIGNMENT' => 'Câu hỏi không thuộc bài tập này.',
            'SCORE_EXCEEDS_QUESTION' => 'Điểm chấm vượt quá điểm tối đa của câu.',
            'UNFINISHED_GRADING' => 'Vẫn còn bài đang chờ hoàn tất chấm điểm.',
            'SUBMISSION_CANNOT_REOPEN' => 'Chỉ có thể mở lại bài đã chấm hoặc đã công bố.',
        ];

        return response()->json([
            'success' => false,
            'message' => $messages[$exception->getMessage()] ?? 'Không thể cập nhật điểm.',
            'code' => $exception->getMessage(),
        ], $status);
    }
}
