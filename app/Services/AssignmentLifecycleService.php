<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Enrollment;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssignmentLifecycleService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly LearningNotificationService $notifications,
    ) {}

    public function publish(Request $request, Assignment $assignment): Assignment
    {
        return DB::transaction(function () use ($request, $assignment) {
            $locked = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);
            if ($locked->status !== Assignment::STATUS_DRAFT) {
                throw new DomainException('ASSIGNMENT_ALREADY_PUBLISHED');
            }
            if ($this->isIncomplete($locked)) {
                throw new DomainException('ASSIGNMENT_INCOMPLETE');
            }

            $seenChildren = [];
            foreach ($locked->targets()->get() as $target) {
                $enrollments = Enrollment::query()
                    ->where('catechism_class_id', $target->catechism_class_id)
                    ->where('status', Enrollment::STATUS_ACTIVE)
                    ->when($target->child_id, fn ($query, $childId) => $query->where('child_id', $childId))
                    ->get();
                foreach ($enrollments as $enrollment) {
                    if (isset($seenChildren[$enrollment->child_id])) {
                        continue;
                    }
                    $seenChildren[$enrollment->child_id] = true;
                    $locked->recipients()->create([
                        'catechism_class_id' => $target->catechism_class_id,
                        'child_id' => $enrollment->child_id,
                        'enrollment_id' => $enrollment->id,
                        'assigned_at' => now(),
                        'due_at' => $target->due_at ?? $locked->due_at,
                    ]);
                }
            }
            if ($seenChildren === []) {
                throw new DomainException('NO_ACTIVE_RECIPIENTS');
            }

            $locked->update([
                'status' => $locked->opens_at?->isFuture()
                    ? Assignment::STATUS_SCHEDULED
                    : Assignment::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => $locked->version + 1,
            ]);
            $this->auditLogger->record($request, 'assignment.published', $locked, null, [
                'status' => $locked->status,
                'recipient_count' => count($seenChildren),
            ]);
            $this->notifications->assignmentEvent($request, $locked, 'published');

            return $locked->fresh()->loadCount(['recipients', 'submissions']);
        });
    }

    private function isIncomplete(Assignment $assignment): bool
    {
        if (! $assignment->targets()->exists()) {
            return true;
        }

        $questions = $assignment->questions()
            ->get(['type', 'prompt', 'points', 'options', 'accepted_answers']);
        if ($questions->isEmpty()) {
            return true;
        }

        foreach ($questions as $question) {
            if (trim((string) $question->prompt) === '' || (float) $question->points <= 0) {
                return true;
            }

            if (in_array($question->type, ['single_choice', 'multiple_choice', 'true_false'], true)) {
                $options = collect($question->options ?? []);
                $correctCount = $options->where('is_correct', true)->count();
                $hasBlankOption = $options->contains(
                    fn (array $option) => trim((string) ($option['content'] ?? '')) === '',
                );
                $invalidAnswers = $options->count() < 2
                    || $hasBlankOption
                    || ($question->type === 'single_choice' && $correctCount !== 1)
                    || ($question->type === 'multiple_choice' && $correctCount < 1)
                    || ($question->type === 'true_false' && ($options->count() !== 2 || $correctCount !== 1));
                if ($invalidAnswers) {
                    return true;
                }
            }

            if ($question->type === 'short_answer') {
                $acceptedAnswers = collect($question->accepted_answers ?? [])
                    ->filter(fn ($answer) => trim((string) $answer) !== '');
                if ($acceptedAnswers->isEmpty()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function changeDueDate(Request $request, Assignment $assignment, $dueAt): Assignment
    {
        return DB::transaction(function () use ($request, $assignment, $dueAt) {
            $locked = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);
            if (in_array($locked->status, [Assignment::STATUS_ARCHIVED, Assignment::STATUS_WITHDRAWN], true)) {
                throw new DomainException('ASSIGNMENT_NOT_ACTIVE');
            }
            $oldDue = $locked->due_at;
            $locked->recipients()->where(function ($query) use ($oldDue) {
                $query->whereNull('due_at');
                if ($oldDue) {
                    $query->orWhere('due_at', $oldDue);
                }
            })->update(['due_at' => $dueAt, 'updated_at' => now()]);
            $locked->update(['due_at' => $dueAt, 'version' => $locked->version + 1]);
            $this->auditLogger->record($request, 'assignment.due_changed', $locked, ['due_at' => $oldDue], ['due_at' => $dueAt]);
            if ($locked->recipients()->exists()) {
                $this->notifications->assignmentEvent($request, $locked, 'due_changed');
            }

            return $locked->fresh()->loadCount(['recipients', 'submissions']);
        });
    }

    public function accommodate(Request $request, Assignment $assignment, int $childId, array $data)
    {
        return DB::transaction(function () use ($request, $assignment, $childId, $data) {
            $locked = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $recipient = $locked->recipients()->where('child_id', $childId)->first();
            if (! $recipient) {
                throw new DomainException('NOT_A_RECIPIENT');
            }
            $accommodation = $locked->accommodations()->updateOrCreate(
                ['child_id' => $childId],
                [...$data, 'granted_by' => $request->user()->id],
            );
            if (! empty($data['due_at'])) {
                $recipient->update(['due_at' => $data['due_at']]);
            }
            $this->auditLogger->record($request, 'assignment.accommodation_granted', $accommodation, null, $data);
            if (($data['extra_attempts'] ?? 0) > 0) {
                $this->notifications->assignmentEvent($request, $locked, 'extra_attempt', [$childId]);
            }

            return $accommodation->fresh()->load('child:id,code,full_name');
        });
    }

    public function close(Request $request, Assignment $assignment): Assignment
    {
        if (! in_array($assignment->status, [Assignment::STATUS_SCHEDULED, Assignment::STATUS_PUBLISHED, Assignment::STATUS_GRADING], true)) {
            throw new DomainException('ASSIGNMENT_CANNOT_CLOSE');
        }
        $assignment->update([
            'status' => Assignment::STATUS_CLOSED,
            'closed_at' => now(),
            'version' => $assignment->version + 1,
        ]);
        $this->auditLogger->record($request, 'assignment.closed', $assignment);

        return $assignment->fresh()->loadCount(['recipients', 'submissions']);
    }

    public function withdraw(Request $request, Assignment $assignment, string $reason): Assignment
    {
        if (in_array($assignment->status, [Assignment::STATUS_ARCHIVED, Assignment::STATUS_WITHDRAWN], true)) {
            throw new DomainException('ASSIGNMENT_CANNOT_WITHDRAW');
        }
        $old = $assignment->only(['status', 'version']);
        $assignment->update([
            'status' => Assignment::STATUS_WITHDRAWN,
            'withdrawn_at' => now(),
            'version' => $assignment->version + 1,
        ]);
        $this->auditLogger->record($request, 'assignment.withdrawn', $assignment, $old, ['reason' => $reason]);

        return $assignment->fresh()->loadCount(['recipients', 'submissions']);
    }
}
