<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Assignment;
use Illuminate\Http\Request;

class LearningNotificationService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function assignmentEvent(Request $request, Assignment $assignment, string $event, ?array $childIds = null): Announcement
    {
        $sourceType = $childIds && count($childIds) === 1
            ? "assignment_{$event}_{$childIds[0]}"
            : "assignment_{$event}";
        $existing = Announcement::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $assignment->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $assignment->loadMissing(['creator.teacherProfile', 'recipients.child']);
        $copy = match ($event) {
            'results_released' => [
                'title' => "Đã có kết quả: {$assignment->title}",
                'body' => 'Kết quả bài làm đã được công bố. Em có thể mở bài tập để xem điểm và nhận xét.',
                'importance' => 'important',
            ],
            'due_changed' => [
                'title' => "Đổi hạn nộp: {$assignment->title}",
                'body' => 'Giáo lý viên đã điều chỉnh hạn nộp. Em hãy mở bài tập để xem thời gian mới.',
                'importance' => 'important',
            ],
            'extra_attempt' => [
                'title' => "Được thêm lượt làm: {$assignment->title}",
                'body' => 'Giáo lý viên đã cấp thêm lượt làm bài cho em.',
                'importance' => 'important',
            ],
            'submission_reopened' => [
                'title' => "Bài làm đã được mở lại: {$assignment->title}",
                'body' => 'Giáo lý viên đã mở lại lượt làm để em chỉnh sửa và nộp lại.',
                'importance' => 'important',
            ],
            default => [
                'title' => "Bài tập mới: {$assignment->title}",
                'body' => 'Giáo lý viên vừa giao một bài tập mới. Em hãy mở bài để xem yêu cầu và hạn nộp.',
                'importance' => 'normal',
            ],
        };
        $parishId = $assignment->creator?->teacherProfile?->parish_id
            ?? $assignment->recipients->first()?->child?->parish_id;
        $announcement = Announcement::create([
            'parish_id' => $parishId,
            'created_by' => $request->user()->id,
            ...$copy,
            'status' => Announcement::STATUS_SENT,
            'sent_at' => now(),
            'requires_acknowledgement' => false,
            'source_type' => $sourceType,
            'source_id' => $assignment->id,
        ]);

        $recipients = $assignment->recipients
            ->when($childIds, fn ($items) => $items->whereIn('child_id', $childIds));
        foreach ($recipients as $recipient) {
            $announcement->targets()->create([
                'catechism_class_id' => $recipient->catechism_class_id,
                'child_id' => $recipient->child_id,
                'audience' => 'children',
            ]);
        }
        $userIds = $recipients->pluck('child.user_id')->filter()->unique()->values();
        $announcement->recipients()->sync($userIds);
        $this->auditLogger->record($request, "announcement.{$sourceType}", $announcement, null, [
            'assignment_id' => $assignment->id,
            'recipient_count' => $userIds->count(),
        ]);

        return $announcement;
    }
}
