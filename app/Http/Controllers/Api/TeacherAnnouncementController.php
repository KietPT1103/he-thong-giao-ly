<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Learning\UpsertAnnouncementRequest;
use App\Models\Announcement;
use App\Models\Enrollment;
use App\Services\AnnouncementService;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TeacherAnnouncementController extends ApiController
{
    public function __construct(
        private readonly AnnouncementService $announcementService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Announcement::class);
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,scheduled,sent,expired,archived,withdrawn'],
            'class_id' => ['nullable', 'integer', 'exists:catechism_classes,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $teacherId = $request->user()->teacherProfile->id;
        $search = trim((string) ($data['search'] ?? ''));
        $announcements = Announcement::query()
            ->whereHas('targets.catechismClass.teachers', fn ($query) => $query->where('teacher_profiles.id', $teacherId))
            ->when($search, fn ($query) => $query->where(fn ($filtered) => $filtered
                ->where('title', 'like', "%{$search}%")
                ->orWhere('body', 'like', "%{$search}%")))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['class_id'] ?? null, fn ($query, $classId) => $query
                ->whereHas('targets', fn ($targets) => $targets->where('catechism_class_id', $classId)))
            ->with(['targets.catechismClass:id,name,code', 'creator:id,name'])
            ->withCount('recipients')
            ->latest()->paginate(15);

        return $this->success($announcements, 'Đã tải danh sách thông báo.');
    }

    public function store(UpsertAnnouncementRequest $request)
    {
        $data = $request->validated();
        if ($error = $this->targetScopeError($request, $data['targets'])) {
            return $error;
        }

        $announcement = DB::transaction(function () use ($request, $data) {
            $announcement = Announcement::create([
                ...Arr::except($data, ['targets', 'version']),
                'parish_id' => $request->user()->teacherProfile->parish_id,
                'created_by' => $request->user()->id,
                'status' => Announcement::STATUS_DRAFT,
            ]);
            $this->syncTargets($announcement, $data['targets']);
            $this->auditLogger->record($request, 'announcement.created', $announcement);

            return $announcement;
        });

        return $this->success($this->detail($announcement), 'Đã tạo bản nháp thông báo.', [], 201);
    }

    public function show(Request $request, Announcement $announcement)
    {
        $this->authorize('view', $announcement);

        return $this->success($this->detail($announcement), 'Đã tải thông báo.');
    }

    public function update(UpsertAnnouncementRequest $request, Announcement $announcement)
    {
        $data = $request->validated();
        if (($data['version'] ?? $announcement->version) !== $announcement->version) {
            return response()->json([
                'success' => false, 'message' => 'Thông báo đã được cập nhật ở nơi khác.',
                'code' => 'VERSION_CONFLICT',
            ], 409);
        }
        if ($error = $this->targetScopeError($request, $data['targets'])) {
            return $error;
        }

        DB::transaction(function () use ($request, $announcement, $data) {
            $old = $announcement->only(['title', 'body', 'version']);
            $announcement->update([
                ...Arr::except($data, ['targets', 'version']),
                'version' => $announcement->version + 1,
            ]);
            if (in_array($announcement->status, [Announcement::STATUS_DRAFT, Announcement::STATUS_SCHEDULED], true)) {
                $this->syncTargets($announcement, $data['targets']);
            }
            $this->auditLogger->record($request, 'announcement.updated', $announcement, $old, $announcement->only([
                'title', 'body', 'version',
            ]));
        });

        return $this->success($this->detail($announcement->fresh()), 'Đã cập nhật thông báo.');
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        $this->authorize('update', $announcement);
        if ($announcement->status === Announcement::STATUS_DRAFT && ! $announcement->recipients()->exists()) {
            $announcement->delete();
        } else {
            $announcement->update(['status' => Announcement::STATUS_ARCHIVED]);
        }
        $this->auditLogger->record($request, 'announcement.archived', $announcement);

        return $this->success(null, 'Đã lưu trữ thông báo.');
    }

    public function send(Request $request, Announcement $announcement)
    {
        $this->authorize('send', $announcement);
        try {
            $sent = $this->announcementService->send($request, $announcement);
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() === 'NO_ACTIVE_RECIPIENTS'
                    ? 'Không có tài khoản người nhận đang hoạt động.'
                    : 'Thông báo đã được gửi.',
                'code' => $exception->getMessage(),
            ], 422);
        }

        return $this->success($sent, $sent->status === Announcement::STATUS_SCHEDULED
            ? 'Đã lên lịch gửi thông báo.' : 'Đã gửi thông báo.');
    }

    public function withdraw(Request $request, Announcement $announcement)
    {
        $this->authorize('update', $announcement);
        abort_unless(in_array($announcement->status, [Announcement::STATUS_SCHEDULED, Announcement::STATUS_SENT], true), 422);
        $announcement->update([
            'status' => Announcement::STATUS_WITHDRAWN,
            'withdrawn_at' => now(),
            'version' => $announcement->version + 1,
        ]);
        $this->auditLogger->record($request, 'announcement.withdrawn', $announcement);

        return $this->success($this->detail($announcement->fresh()), 'Đã thu hồi thông báo.');
    }

    public function remind(Request $request, Announcement $announcement)
    {
        $this->authorize('send', $announcement);
        abort_unless($announcement->status === Announcement::STATUS_SENT, 422);
        $pending = $announcement->recipients()
            ->wherePivotNull($announcement->requires_acknowledgement ? 'acknowledged_at' : 'read_at')
            ->pluck('users.id');
        if ($pending->isNotEmpty()) {
            DB::table('announcement_recipients')
                ->where('announcement_id', $announcement->id)
                ->whereIn('user_id', $pending)
                ->update(['reminded_at' => now(), 'updated_at' => now()]);
        }
        $this->auditLogger->record($request, 'announcement.reminded', $announcement, null, [
            'recipient_count' => $pending->count(),
        ]);

        return $this->success(['reminded_count' => $pending->count()], 'Đã nhắc người nhận chưa hoàn thành.');
    }

    private function syncTargets(Announcement $announcement, array $targets): void
    {
        $announcement->targets()->delete();
        foreach ($targets as $target) {
            $children = $target['child_ids'] === [] ? [null] : $target['child_ids'];
            foreach ($children as $childId) {
                $announcement->targets()->create([
                    'catechism_class_id' => $target['catechism_class_id'],
                    'child_id' => $childId,
                    'audience' => $target['audience'],
                ]);
            }
        }
    }

    private function targetScopeError(Request $request, array $targets)
    {
        $classIds = collect($targets)->pluck('catechism_class_id')->unique()->values();
        $assigned = $request->user()->teacherProfile->classes()
            ->whereIn('catechism_classes.id', $classIds)->pluck('catechism_classes.id');
        if ($assigned->count() !== $classIds->count()) {
            return response()->json([
                'success' => false, 'message' => 'Bạn chỉ có thể gửi thông báo cho lớp đang phụ trách.',
                'code' => 'CLASS_NOT_ASSIGNED',
            ], 422);
        }
        foreach ($targets as $target) {
            if ($target['child_ids'] === []) {
                continue;
            }
            $validCount = Enrollment::query()
                ->where('catechism_class_id', $target['catechism_class_id'])
                ->where('status', Enrollment::STATUS_ACTIVE)
                ->whereIn('child_id', $target['child_ids'])->count();
            if ($validCount !== count(array_unique($target['child_ids']))) {
                return response()->json([
                    'success' => false, 'message' => 'Thiếu nhi được chọn không thuộc lớp.',
                    'code' => 'CHILD_NOT_IN_CLASS',
                ], 422);
            }
        }

        return null;
    }

    private function detail(Announcement $announcement): Announcement
    {
        return $announcement->load([
            'creator:id,name', 'targets.catechismClass:id,name,code',
            'targets.child:id,code,full_name',
        ])->loadCount([
            'recipients',
            'recipients as unread_count' => fn ($query) => $query->wherePivotNull('read_at'),
            'recipients as unacknowledged_count' => fn ($query) => $query->wherePivotNull('acknowledged_at'),
        ]);
    }
}
