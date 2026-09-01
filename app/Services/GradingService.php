<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Submission;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GradingService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly LearningNotificationService $notifications,
    ) {}

    public function grade(Request $request, Submission $submission, array $data): Submission
    {
        return DB::transaction(function () use ($request, $submission, $data) {
            $locked = Submission::query()->lockForUpdate()->findOrFail($submission->id);
            if ($locked->version !== $data['version']) {
                throw new DomainException('VERSION_CONFLICT');
            }
            if (! in_array($locked->status, [Submission::STATUS_GRADING, Submission::STATUS_GRADED, Submission::STATUS_RELEASED], true)) {
                throw new DomainException('SUBMISSION_NOT_READY_FOR_GRADING');
            }
            $assignment = $locked->assignment()->with('questions')->firstOrFail();
            foreach ($data['answers'] as $graded) {
                $question = $assignment->questions->firstWhere('id', $graded['question_id']);
                if (! $question) {
                    throw new DomainException('QUESTION_NOT_IN_ASSIGNMENT');
                }
                if ((float) $graded['score'] > (float) $question->points) {
                    throw new DomainException('SCORE_EXCEEDS_QUESTION');
                }
                $answer = $locked->answers()->firstOrCreate(['assignment_question_id' => $question->id]);
                $manualScore = $question->type === 'essay'
                    ? (float) $graded['score']
                    : (float) $graded['score'] - (float) $answer->auto_score;
                $answer->update([
                    'manual_score' => $manualScore,
                    'feedback' => $graded['feedback'] ?? null,
                    'rubric_scores' => $graded['rubric_scores'] ?? null,
                    'graded_by' => $request->user()->id,
                    'graded_at' => now(),
                ]);
            }

            $essayIds = $assignment->questions->where('type', 'essay')->pluck('id');
            $gradedEssayCount = $locked->answers()->whereIn('assignment_question_id', $essayIds)
                ->whereNotNull('graded_at')->count();
            $complete = $gradedEssayCount === $essayIds->count();
            $rawScore = (float) $locked->answers()->sum(DB::raw('auto_score + manual_score'));
            $possible = (float) $assignment->questions->sum('points');
            $finalScore = $complete && $possible > 0
                ? ($rawScore / $possible) * (float) $assignment->max_score
                : null;
            if ($finalScore !== null && $locked->is_late && $assignment->late_penalty_percent > 0) {
                $finalScore *= 1 - ((float) $assignment->late_penalty_percent / 100);
            }
            $finalScore = $finalScore === null ? null : round(max(0, $finalScore), 2);
            $oldScore = $locked->final_score;
            $locked->update([
                'status' => $locked->status === Submission::STATUS_RELEASED
                    ? Submission::STATUS_RELEASED
                    : ($complete ? Submission::STATUS_GRADED : Submission::STATUS_GRADING),
                'manual_score' => (float) $locked->answers()->sum('manual_score'),
                'final_score' => $finalScore,
                'general_feedback' => $data['general_feedback'] ?? null,
                'graded_at' => $complete ? now() : null,
                'version' => $locked->version + 1,
            ]);
            if ($oldScore !== $finalScore) {
                $locked->histories()->create([
                    'changed_by' => $request->user()->id,
                    'old_score' => $oldScore,
                    'new_score' => $finalScore,
                    'reason' => $data['reason'] ?? 'Chấm bài lần đầu',
                    'details' => ['answer_count' => count($data['answers'])],
                ]);
            }
            $this->auditLogger->record($request, 'assignment.graded', $locked, ['final_score' => $oldScore], [
                'final_score' => $finalScore, 'status' => $locked->status,
            ]);

            return $locked->fresh()->load(['answers.question', 'child:id,code,full_name']);
        });
    }

    public function release(Request $request, Assignment $assignment): Assignment
    {
        return DB::transaction(function () use ($request, $assignment) {
            $locked = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);
            if ($locked->submissions()->where('status', Submission::STATUS_GRADING)->exists()) {
                throw new DomainException('UNFINISHED_GRADING');
            }
            $locked->submissions()->where('status', Submission::STATUS_GRADED)->update([
                'status' => Submission::STATUS_RELEASED,
                'released_at' => now(),
                'version' => DB::raw('version + 1'),
            ]);
            $locked->update([
                'status' => Assignment::STATUS_RELEASED,
                'released_at' => now(),
                'version' => $locked->version + 1,
            ]);
            $this->auditLogger->record($request, 'assignment.results_released', $locked);
            $this->notifications->assignmentEvent($request, $locked, 'results_released');

            return $locked->fresh()->loadCount(['recipients', 'submissions']);
        });
    }

    public function reopen(Request $request, Submission $submission, string $reason): Submission
    {
        return DB::transaction(function () use ($request, $submission, $reason) {
            $locked = Submission::query()->lockForUpdate()->findOrFail($submission->id);
            if (! in_array($locked->status, [Submission::STATUS_GRADED, Submission::STATUS_RELEASED], true)) {
                throw new DomainException('SUBMISSION_CANNOT_REOPEN');
            }
            $old = $locked->only(['status', 'final_score', 'version']);
            $locked->update([
                'status' => Submission::STATUS_REOPENED,
                'submitted_at' => null,
                'released_at' => null,
                'version' => $locked->version + 1,
            ]);
            $locked->histories()->create([
                'changed_by' => $request->user()->id,
                'old_score' => $old['final_score'],
                'new_score' => $old['final_score'],
                'reason' => $reason,
                'details' => ['action' => 'reopened'],
            ]);
            $this->auditLogger->record($request, 'assignment.submission_reopened', $locked, $old, ['reason' => $reason]);
            $this->notifications->assignmentEvent($request, $locked->assignment, 'submission_reopened', [$locked->child_id]);

            return $locked->fresh()->load(['answers.question', 'files', 'child:id,code,full_name']);
        });
    }
}
