<?php

namespace App\Http\Controllers\Api;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;

class NotificationInboxController extends ApiController
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('view-notifications'), 403);
        $data = $request->validate([
            'unread' => ['nullable', 'boolean'],
        ]);
        $base = $this->visibleQuery($request);
        $unreadCount = (clone $base)->wherePivotNull('read_at')->count();
        $announcements = $base
            ->when($data['unread'] ?? false, fn ($query) => $query->wherePivotNull('read_at'))
            ->orderByDesc('is_pinned')
            ->orderByDesc('sent_at')
            ->orderByDesc('scheduled_at')
            ->get()
            ->map(fn (Announcement $announcement) => $this->payload($announcement));

        return $this->success($announcements, 'Đã tải hộp thư thông báo.', [
            'unread_count' => $unreadCount,
        ]);
    }

    public function show(Request $request, Announcement $announcement)
    {
        $announcement = $this->recipientAnnouncement($request, $announcement);

        return $this->success($this->payload($announcement), 'Đã tải thông báo.');
    }

    public function read(Request $request, Announcement $announcement)
    {
        $announcement = $this->recipientAnnouncement($request, $announcement);
        $request->user()->receivedAnnouncements()->updateExistingPivot($announcement->id, [
            'read_at' => $announcement->pivot->read_at ?? now(),
        ]);

        return $this->success($this->payload($this->recipientAnnouncement($request, $announcement)), 'Đã đánh dấu đã đọc.');
    }

    public function acknowledge(Request $request, Announcement $announcement)
    {
        $announcement = $this->recipientAnnouncement($request, $announcement);
        abort_unless($announcement->requires_acknowledgement, 422);
        $request->user()->receivedAnnouncements()->updateExistingPivot($announcement->id, [
            'read_at' => $announcement->pivot->read_at ?? now(),
            'acknowledged_at' => $announcement->pivot->acknowledged_at ?? now(),
        ]);

        return $this->success($this->payload($this->recipientAnnouncement($request, $announcement)), 'Đã xác nhận thông báo.');
    }

    public function readAll(Request $request)
    {
        abort_unless($request->user()->can('view-notifications'), 403);
        $ids = $this->visibleQuery($request)->wherePivotNull('read_at')->pluck('announcements.id');
        foreach ($ids as $id) {
            $request->user()->receivedAnnouncements()->updateExistingPivot($id, ['read_at' => now()]);
        }

        return $this->success(['updated_count' => $ids->count()], 'Đã đánh dấu tất cả là đã đọc.');
    }

    private function visibleQuery(Request $request): BelongsToMany
    {
        return $request->user()->receivedAnnouncements()
            ->where(function ($query) {
                $query->where('status', Announcement::STATUS_SENT)
                    ->orWhere(function ($scheduled) {
                        $scheduled->where('status', Announcement::STATUS_SCHEDULED)
                            ->where('scheduled_at', '<=', now());
                    });
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    private function recipientAnnouncement(Request $request, Announcement $announcement): Announcement
    {
        $visible = $this->visibleQuery($request)->where('announcements.id', $announcement->id)->first();
        abort_unless($visible, 404);

        return $visible;
    }

    private function payload(Announcement $announcement): array
    {
        return [
            ...$announcement->only([
                'id', 'title', 'body', 'importance', 'status', 'scheduled_at', 'sent_at',
                'expires_at', 'is_pinned', 'requires_acknowledgement', 'source_type', 'source_id',
            ]),
            'is_read' => $announcement->pivot->read_at !== null,
            'is_acknowledged' => $announcement->pivot->acknowledged_at !== null,
            'read_at' => $announcement->pivot->read_at,
            'acknowledged_at' => $announcement->pivot->acknowledged_at,
            'reminded_at' => $announcement->pivot->reminded_at,
        ];
    }
}
