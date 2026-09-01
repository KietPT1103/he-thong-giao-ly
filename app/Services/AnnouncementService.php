<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Enrollment;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function send(Request $request, Announcement $announcement): Announcement
    {
        return DB::transaction(function () use ($request, $announcement) {
            $locked = Announcement::query()->lockForUpdate()->findOrFail($announcement->id);
            if (! in_array($locked->status, [Announcement::STATUS_DRAFT, Announcement::STATUS_SCHEDULED], true)) {
                throw new DomainException('ANNOUNCEMENT_ALREADY_SENT');
            }

            $userIds = [];
            foreach ($locked->targets()->get() as $target) {
                $enrollments = Enrollment::query()
                    ->with(['child.user', 'child.parents.user'])
                    ->where('catechism_class_id', $target->catechism_class_id)
                    ->where('status', Enrollment::STATUS_ACTIVE)
                    ->when($target->child_id, fn ($query, $childId) => $query->where('child_id', $childId))
                    ->get();

                foreach ($enrollments as $enrollment) {
                    if (in_array($target->audience, ['children', 'both'], true) && $enrollment->child->user_id) {
                        $userIds[$enrollment->child->user_id] = true;
                    }
                    if (in_array($target->audience, ['parents', 'both'], true)) {
                        foreach ($enrollment->child->parents as $parent) {
                            if ($parent->user_id) {
                                $userIds[$parent->user_id] = true;
                            }
                        }
                    }
                }
            }
            if ($userIds === []) {
                throw new DomainException('NO_ACTIVE_RECIPIENTS');
            }

            $locked->recipients()->syncWithoutDetaching(array_keys($userIds));
            $scheduled = $locked->scheduled_at?->isFuture() ?? false;
            $locked->update([
                'status' => $scheduled ? Announcement::STATUS_SCHEDULED : Announcement::STATUS_SENT,
                'sent_at' => $scheduled ? null : now(),
                'version' => $locked->version + 1,
            ]);
            $this->auditLogger->record($request, 'announcement.sent', $locked, null, [
                'status' => $locked->status,
                'recipient_count' => count($userIds),
            ]);

            $sent = $locked->fresh()->loadCount('recipients');
            $sent->setAttribute('recipient_count', $sent->recipients_count);

            return $sent;
        });
    }
}
