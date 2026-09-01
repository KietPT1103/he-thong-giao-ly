<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Submission;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
                return [$existing->load(['answers', 'files']), false];
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

            return [$submission->refresh()->load(['answers', 'files']), true];
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

    public function submit(Submission $submission): Submission
    {
        return DB::transaction(function () use ($submission) {
            $locked = Submission::query()->lockForUpdate()->findOrFail($submission->id);
            if (! in_array($locked->status, [Submission::STATUS_IN_PROGRESS, Submission::STATUS_REOPENED], true)) {
                throw new DomainException('SUBMISSION_LOCKED');
            }
            $assignment = $locked->assignment()->with('questions')->firstOrFail();
            $answers = $locked->answers()->get()->keyBy('assignment_question_id');
            $autoScore = 0.0;
            $hasManualQuestions = false;
            foreach ($assignment->questions as $question) {
                $answer = $answers->get($question->id) ?? $locked->answers()->create([
                    'assignment_question_id' => $question->id,
                    'saved_at' => now(),
                ]);
                $score = $this->scoreAnswer($question, $answer->answer);
                $answer->update(['auto_score' => $score]);
                $autoScore += $score;
                $hasManualQuestions = $hasManualQuestions || $question->type === 'essay';
            }

            $status = $hasManualQuestions ? Submission::STATUS_GRADING : Submission::STATUS_GRADED;
            $finalScore = $hasManualQuestions ? null : $this->normalizeScore($assignment, $locked, $autoScore);
            $releasedAt = null;
            if (! $hasManualQuestions && $assignment->result_release_mode === 'immediate') {
                $status = Submission::STATUS_RELEASED;
                $releasedAt = now();
            }
            $locked->update([
                'status' => $status,
                'submitted_at' => now(),
                'auto_score' => $autoScore,
                'final_score' => $finalScore,
                'released_at' => $releasedAt,
                'version' => $locked->version + 1,
            ]);

            return $locked->fresh()->load('answers');
        });
    }

    private function scoreAnswer($question, ?array $answer): float
    {
        if (! $answer || $question->type === 'essay') {
            return 0.0;
        }
        if ($question->type === 'short_answer') {
            $actual = $this->normalizeText((string) ($answer['text'] ?? ''));
            $accepted = collect($question->accepted_answers ?? [])->map(fn ($value) => $this->normalizeText((string) $value));

            return $actual !== '' && $accepted->contains($actual) ? (float) $question->points : 0.0;
        }
        $expected = collect($question->options ?? [])->keys()
            ->filter(fn ($index) => (bool) ($question->options[$index]['is_correct'] ?? false))
            ->map(fn ($index) => (int) $index)->sort()->values()->all();
        $selected = collect($answer['selected'] ?? [])->map(fn ($index) => (int) $index)->unique()->sort()->values()->all();
        if ($question->type === 'multiple_choice' && ($question->settings['partial_credit'] ?? false)) {
            $correctSelected = count(array_intersect($selected, $expected));
            $incorrectSelected = count(array_diff($selected, $expected));
            $ratio = count($expected) > 0 ? max(0, ($correctSelected - $incorrectSelected) / count($expected)) : 0;

            return round((float) $question->points * $ratio, 2);
        }

        return $selected === $expected ? (float) $question->points : 0.0;
    }

    private function normalizeText(string $value): string
    {
        return Str::of($value)->trim()->lower()->squish()->toString();
    }

    private function normalizeScore(Assignment $assignment, Submission $submission, float $rawScore): float
    {
        $possible = (float) $assignment->questions->sum('points');
        $score = $possible > 0 ? ($rawScore / $possible) * (float) $assignment->max_score : 0;
        if ($submission->is_late && $assignment->allow_late && $assignment->late_penalty_percent > 0) {
            $score *= 1 - ((float) $assignment->late_penalty_percent / 100);
        }

        return round(max(0, $score), 2);
    }
}
