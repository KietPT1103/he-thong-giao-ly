<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Attendance\MarkAttendanceRequest;
use App\Http\Requests\Attendance\StoreAttendanceSessionRequest;
use App\Models\AttendanceSession;
use App\Models\CatechismClass;
use App\Services\AuditLogger;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceController extends ApiController
{
    public function workspace(Request $request)
    {
        $teacher = $request->user()->teacherProfile;
        abort_unless($teacher && $request->user()->can('view-attendance'), 403);
        $classes = $teacher->classes()
            ->where('catechism_classes.status', 'active')
            ->orderBy('catechism_classes.name')
            ->get(['catechism_classes.id', 'name', 'code']);
        $requestedClassId = $request->integer('class_id');
        abort_if($requestedClassId && ! $classes->contains('id', $requestedClassId), 403);
        $classId = $requestedClassId ?: $classes->first()?->id;

        if (! $classId) {
            return $this->success(['classes' => [], 'selected_class_id' => null, 'sessions' => ['data' => [], 'total' => 0], 'session_history_total' => 0, 'children' => []]);
        }

        $class = CatechismClass::query()->findOrFail($classId);
        $sessionHistoryTotal = $class->attendanceSessions()->count();
        $sessions = $class->attendanceSessions()
            ->where('status', 'active')
            ->with('attendances')
            ->latest('held_at')
            ->paginate(15);
        $children = $class->children()
            ->with('user:id,email,avatar_path')
            ->orderBy('children.full_name')
            ->get()
            ->map(fn ($child) => [
                'id' => $child->id,
                'user_id' => $child->user_id,
                'email' => $child->user?->email,
                'avatar_url' => $child->user?->avatar_path ? '/storage/'.ltrim($child->user->avatar_path, '/') : null,
                'code' => $child->code,
                'full_name' => $child->full_name,
                'saint_name' => $child->saint_name,
                'date_of_birth' => $child->date_of_birth?->toDateString(),
                'status' => $child->status,
            ])->values();

        return $this->success([
            'classes' => $classes->map(fn ($item) => $item->only(['id', 'name', 'code']))->values(),
            'selected_class_id' => $classId,
            'sessions' => $sessions,
            'session_history_total' => $sessionHistoryTotal,
            'children' => $children,
        ], 'Đã tải không gian điểm danh.');
    }

    public function index(Request $request, CatechismClass $class)
    {
        $this->authorize('view', $class);
        $status = $request->string('status')->toString();
        $query = $class->attendanceSessions()->with('attendances')->latest('held_at');
        if (in_array($status, ['active', 'ended', 'cancelled'], true)) $query->where('status', $status);

        return $this->success($query->paginate()->withQueryString());
    }

    public function store(StoreAttendanceSessionRequest $request, CatechismClass $class)
    {
        $this->authorize('takeAttendance', $class);
        $data = $request->validated();
        $heldAt = Carbon::parse($data['held_at'])->utc();

        return DB::transaction(function () use ($request, $class, $data, $heldAt) {
            CatechismClass::query()->whereKey($class->id)->lockForUpdate()->firstOrFail();
            if ($class->attendanceSessions()->where('held_at', $heldAt)->exists()) {
                return response()->json(['success' => false, 'message' => 'Phiên điểm danh tại thời điểm này đã tồn tại.', 'code' => 'ATTENDANCE_SESSION_EXISTS'], 422);
            }
            $active = $class->attendanceSessions()->where('status', 'active')->latest('started_at')->first();
            if ($active) {
                return response()->json(['success' => false, 'message' => 'Lớp đang có một phiên điểm danh mở.', 'code' => 'ACTIVE_ATTENDANCE_SESSION_EXISTS', 'data' => $active], 422);
            }
            $session = $class->attendanceSessions()->create([
                'held_at' => $heldAt, 'started_at' => now(), 'status' => 'active',
                'taken_by' => $request->user()->id, 'note' => $data['note'] ?? null,
            ]);
            return $this->success($session->load('attendances'), 'Đã mở phiên điểm danh.');
        });
    }

    public function show(AttendanceSession $session) { $this->authorize('view', $session); return $this->success($session->load('attendances.child', 'catechismClass')); }

    public function mark(MarkAttendanceRequest $request, AttendanceSession $session, AttendanceService $service)
    {
        $this->authorize('update', $session);
        if ($session->status !== 'active') return $this->inactiveSessionResponse();

        return $this->success($service->mark($session, $request->validated('attendances'), $request->user()->id), 'Đã lưu điểm danh.');
    }

    public function markAll(Request $request, AttendanceSession $session, AttendanceService $service)
    {
        $this->authorize('update', $session);
        if ($session->status !== 'active') return $this->inactiveSessionResponse();

        return $this->success($service->markAllPresent($session, $request->user()->id), 'Đã đánh dấu tất cả có mặt.');
    }

    public function end(Request $request, AttendanceSession $session)
    {
        $this->authorize('update', $session);
        if ($session->status === 'cancelled') return $this->inactiveSessionResponse();
        if ($session->status !== 'ended') $session->update(['status' => 'ended', 'ended_at' => now(), 'qr_expires_at' => null]);
        return $this->success($session->fresh()->load('attendances'), 'Đã kết thúc phiên điểm danh.');
    }

    public function cancel(Request $request, AttendanceSession $session, AuditLogger $audit)
    {
        $this->authorize('update', $session);
        if ($session->status === 'ended') return $this->inactiveSessionResponse('Phiên đã kết thúc nên không thể hủy.');

        if ($session->status !== 'cancelled') {
            $old = $session->only(['status', 'ended_at', 'qr_expires_at']);
            $session->update(['status' => 'cancelled', 'ended_at' => now(), 'qr_expires_at' => null]);
            $audit->record($request, 'attendance.session_cancelled', $session, $old, $session->only(['status', 'ended_at', 'qr_expires_at']));
        }

        return $this->success($session->fresh()->load('attendances'), 'Đã hủy phiên điểm danh.');
    }

    public function destroy(Request $request, AttendanceSession $session, AuditLogger $audit)
    {
        $this->authorize('delete', $session);

        DB::transaction(function () use ($request, $session, $audit) {
            $snapshot = [
                'catechism_class_id' => $session->catechism_class_id,
                'held_at' => $session->held_at?->toIso8601String(),
                'status' => $session->status,
                'attendance_count' => $session->attendances()->count(),
            ];
            DB::table('leave_requests')->where('attendance_session_id', $session->id)->update(['attendance_session_id' => null]);
            $session->attendances()->delete();
            $audit->record($request, 'attendance.session_deleted', $session, $snapshot, null);
            $session->delete();
        });

        return $this->success(null, 'Đã xóa phiên điểm danh.');
    }

    public function summary(AttendanceSession $session) { $this->authorize('view', $session); return $this->success($session->attendances()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status')); }

    private function inactiveSessionResponse(string $message = 'Chỉ phiên đang diễn ra mới có thể cập nhật điểm danh.')
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => 'ATTENDANCE_SESSION_NOT_ACTIVE',
        ], 422);
    }
}
