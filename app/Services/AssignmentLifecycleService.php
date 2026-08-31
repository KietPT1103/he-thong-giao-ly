<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Enrollment;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssignmentLifecycleService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function publish(Request $request, Assignment $assignment): Assignment
    {
        return DB::transaction(function () use ($request, $assignment) {
            $locked = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);
            if ($locked->status !== Assignment::STATUS_DRAFT) {
                throw new DomainException('ASSIGNMENT_ALREADY_PUBLISHED');
            }
            if (! $locked->questions()->exists() || ! $locked->targets()->exists()) {
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

            return $locked->fresh()->loadCount(['recipients', 'submissions']);
        });
    }
}
