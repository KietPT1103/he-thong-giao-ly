<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Submission;
use DomainException;
use Illuminate\Support\Facades\DB;

class SubmissionService
{
    public function start(Assignment $assignment, int $childId): array
    {
        return DB::transaction(function () use ($assignment, $childId) {
            $locked = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $recipient = $locked->recipients()->where('child_id', $childId)->first();
            if (! $recipient || $recipient->access_status !== 'active') {
                throw new DomainException('NOT_A_RECIPIENT');
            }
            if (! in_array($locked->status, [Assignment::STATUS_SCHEDULED, Assignment::STATUS_PUBLISHED], true)
                || $locked->opens_at?->isFuture()) {
                throw new DomainException('ASSIGNMENT_NOT_OPEN');
            }
            $deadline = $recipient->due_at ?? $locked->due_at;
            if ($deadline?->isPast() && ! $locked->allow_late) {
                throw new DomainException('ASSIGNMENT_OVERDUE');
            }

            $existing = $locked->submissions()
                ->where('child_id', $childId)
                ->whereIn('status', [Submission::STATUS_IN_PROGRESS, Submission::STATUS_REOPENED])
                ->latest('attempt_number')->first();
            if ($existing && $locked->allow_resume) {
                return [$existing, false];
            }

            $attemptsUsed = $locked->submissions()->where('child_id', $childId)->count();
            $extraAttempts = $locked->accommodations()->where('child_id', $childId)->value('extra_attempts') ?? 0;
            if ($locked->allowed_attempts > 0 && $attemptsUsed >= $locked->allowed_attempts + $extraAttempts) {
                throw new DomainException('ATTEMPT_LIMIT_REACHED');
            }

            $submission = $locked->submissions()->create([
                'child_id' => $childId,
                'attempt_number' => $attemptsUsed + 1,
                'status' => Submission::STATUS_IN_PROGRESS,
                'started_at' => now(),
                'is_late' => $deadline?->isPast() ?? false,
            ]);

            return [$submission->refresh(), true];
        });
    }

    public function autosave(Submission $submission, array $data): Submission
    {
        return DB::transaction(function () use ($submission, $data) {
            $locked = Submission::query()->lockForUpdate()->findOrFail($submission->id);
            if ($locked->version !== $data['version']) {
                throw new DomainException('VERSION_CONFLICT');
            }
            if (! in_array($locked->status, [Submission::STATUS_IN_PROGRESS, Submission::STATUS_REOPENED], true)) {
                throw new DomainException('SUBMISSION_LOCKED');
            }
            $questionIds = $locked->assignment->questions()->pluck('id');
            foreach ($data['answers'] as $answer) {
                if (! $questionIds->contains($answer['question_id'])) {
                    throw new DomainException('QUESTION_NOT_IN_ASSIGNMENT');
                }
                $locked->answers()->updateOrCreate(
                    ['assignment_question_id' => $answer['question_id']],
                    ['answer' => $answer['answer'] ?? null, 'saved_at' => now()],
                );
            }
            $seconds = max(0, now()->diffInSeconds($locked->started_at, true));
            if ($locked->assignment->time_limit_minutes) {
                $seconds = min($seconds, $locked->assignment->time_limit_minutes * 60);
            }
            $locked->update([
                'version' => $locked->version + 1,
                'time_spent_seconds' => $seconds,
            ]);

            return $locked->fresh();
        });
    }
}
