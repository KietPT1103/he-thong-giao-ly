<?php

namespace App\Http\Controllers\Api;

use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherAssignmentReportController extends ApiController
{
    public function show(Request $request, Assignment $assignment)
    {
        $this->authorizeReport($request, $assignment);

        return $this->success($this->report($assignment), 'Đã tải báo cáo kết quả bài tập.');
    }

    public function export(Request $request, Assignment $assignment): StreamedResponse
    {
        $this->authorizeReport($request, $assignment);
        $report = $this->report($assignment);

        return response()->streamDownload(function () use ($assignment, $report): void {
            $output = fopen('php://output', 'w');
            echo "\xEF\xBB\xBF";
            fputcsv($output, ['Báo cáo kết quả bài tập', $assignment->title]);
            fputcsv($output, []);
            fputcsv($output, ['Tổng người nhận', $report['summary']['recipient_count']]);
            fputcsv($output, ['Đã nộp', $report['summary']['submitted_count']]);
            fputcsv($output, ['Chưa nộp', $report['summary']['not_submitted_count']]);
            fputcsv($output, ['Nộp trễ', $report['summary']['late_count']]);
            fputcsv($output, ['Điểm trung bình', $report['summary']['average_score']]);
            fputcsv($output, ['Tỷ lệ đạt (%)', $report['summary']['pass_rate']]);
            fputcsv($output, []);
            fputcsv($output, ['Mã thiếu nhi', 'Họ và tên', 'Lớp', 'Trạng thái', 'Nộp trễ', 'Điểm', 'Kết quả']);

            foreach ($report['rows'] as $row) {
                fputcsv($output, [
                    $row['child_code'], $row['child_name'], $row['class_name'],
                    $row['submitted'] ? 'Đã nộp' : 'Chưa nộp',
                    $row['is_late'] ? 'Có' : 'Không', $row['score'],
                    $row['passed'] === null ? '' : ($row['passed'] ? 'Đạt' : 'Chưa đạt'),
                ]);
            }

            fclose($output);
        }, "assignment-{$assignment->id}-results.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeReport(Request $request, Assignment $assignment): void
    {
        $this->authorize('view', $assignment);
        abort_unless($request->user()->can('view-assignment-reports'), 403);
    }

    private function report(Assignment $assignment): array
    {
        $assignment->load([
            'recipients.child:id,code,full_name',
            'recipients.catechismClass:id,name,code',
            'submissions' => fn ($query) => $query
                ->whereNotNull('submitted_at')
                ->orderBy('attempt_number'),
        ]);

        $submissionsByChild = $assignment->submissions->groupBy('child_id');
        $rows = $assignment->recipients->map(function ($recipient) use ($assignment, $submissionsByChild): array {
            $submissions = $submissionsByChild->get($recipient->child_id, collect());
            $score = $this->finalScore($assignment->score_method, $submissions->whereNotNull('final_score'));

            return [
                'recipient_id' => $recipient->id,
                'child_id' => $recipient->child_id,
                'child_code' => $recipient->child?->code,
                'child_name' => $recipient->child?->full_name,
                'class_id' => $recipient->catechism_class_id,
                'class_name' => $recipient->catechismClass?->name,
                'submitted' => $submissions->isNotEmpty(),
                'is_late' => $submissions->contains(fn (Submission $submission) => $submission->is_late),
                'attempt_count' => $submissions->count(),
                'score' => $score,
                'passed' => $score === null ? null : $score >= (float) $assignment->passing_score,
            ];
        })->values();

        $scores = $rows->pluck('score')->filter(fn ($score) => $score !== null)->map(fn ($score) => (float) $score);
        $submittedCount = $rows->where('submitted', true)->count();
        $passedCount = $rows->filter(fn ($row) => $row['passed'] === true)->count();

        return [
            'assignment' => $assignment->only(['id', 'title', 'status', 'passing_score', 'score_method']),
            'summary' => [
                'recipient_count' => $rows->count(),
                'submitted_count' => $submittedCount,
                'not_submitted_count' => $rows->count() - $submittedCount,
                'late_count' => $rows->where('is_late', true)->count(),
                'graded_count' => $scores->count(),
                'average_score' => $scores->isEmpty() ? null : round($scores->average(), 2),
                'pass_rate' => $scores->isEmpty() ? null : round(($passedCount / $scores->count()) * 100, 2),
            ],
            'distribution' => [
                'below_5' => $scores->filter(fn ($score) => $score < 5)->count(),
                'from_5_to_7' => $scores->filter(fn ($score) => $score >= 5 && $score < 7)->count(),
                'from_7_to_8_5' => $scores->filter(fn ($score) => $score >= 7 && $score < 8.5)->count(),
                'from_8_5_to_10' => $scores->filter(fn ($score) => $score >= 8.5)->count(),
            ],
            'rows' => $rows,
        ];
    }

    private function finalScore(string $method, Collection $submissions): ?float
    {
        if ($submissions->isEmpty()) {
            return null;
        }

        $score = match ($method) {
            'latest' => $submissions->sortByDesc('attempt_number')->first()->final_score,
            'average' => $submissions->average('final_score'),
            default => $submissions->max('final_score'),
        };

        return round((float) $score, 2);
    }
}
