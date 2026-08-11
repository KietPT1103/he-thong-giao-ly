<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Attendance\ScanQrAttendanceRequest;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Child;
use App\Services\AuditLogger;
use App\Services\ChildQrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QrAttendanceController extends ApiController
{
    public function __construct(
        private readonly ChildQrCodeService $qrCodes,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function show(Request $request, int $child)
    {
        $profile = Child::withTrashed()->findOrFail($child);
        abort_unless($this->canView($request, $profile), 403);
        abort_if($profile->trashed(), 404);

        return $this->success($this->qrPayload($profile), 'Đã tải mã QR thiếu nhi.');
    }

    public function familyChildren(Request $request)
    {
        $parent = $request->user()->parentProfile;
        abort_unless($parent, 403);

        $children = $parent->children()
            ->orderBy('children.full_name')
            ->get(['children.id', 'children.code', 'children.full_name', 'children.saint_name', 'children.status']);

        return $this->success($children, 'Đã tải danh sách thiếu nhi liên kết.');
    }

    public function rotate(Request $request, int $child)
    {
        $profile = Child::findOrFail($child);
        DB::transaction(function () use ($profile, $request) {
            $oldVersion = $profile->qr_version;
            $profile->increment('qr_version');
            $profile->refresh();
            $this->auditLogger->record($request, 'child.qr_rotated', $profile, ['qr_version' => $oldVersion], ['qr_version' => $profile->qr_version]);
        });

        return $this->success($this->qrPayload($profile), 'Đã tạo mã QR mới.');
    }

    public function scan(ScanQrAttendanceRequest $request, AttendanceSession $session)
    {
        abort_unless($this->canScanSession($request, $session), 403);
        $child = $this->qrCodes->resolve($request->validated('token'));
        if (! $child) {
            return $this->businessError('INVALID_QR_CODE', 'Mã QR không hợp lệ hoặc đã hết hiệu lực.');
        }
        if ($child->trashed() || $child->status !== 'studying') {
            return $this->businessError('CHILD_NOT_ACTIVE', 'Hồ sơ thiếu nhi hiện không hoạt động.');
        }
        if (! $child->enrollments()->where('catechism_class_id', $session->catechism_class_id)->where('status', 'active')->exists()) {
            return $this->businessError('CHILD_NOT_IN_SESSION_CLASS', 'Thiếu nhi không thuộc lớp của phiên điểm danh này.');
        }

        $result = DB::transaction(function () use ($child, $request, $session) {
            $attendance = Attendance::query()->where('attendance_session_id', $session->id)->where('child_id', $child->id)->lockForUpdate()->first();
            $duplicate = $attendance && in_array($attendance->status, ['present', 'late'], true) && $attendance->arrived_at !== null;
            if (! $duplicate) {
                $status = now()->greaterThan($session->held_at->copy()->addMinutes(15)) ? 'late' : 'present';
                $attendance = Attendance::updateOrCreate(
                    ['attendance_session_id' => $session->id, 'child_id' => $child->id],
                    ['status' => $status, 'arrived_at' => now()->format('H:i:s')],
                );
            }
            $this->auditLogger->record($request, 'attendance.qr_scanned', $session, null, [
                'child_id' => $child->id, 'attendance_id' => $attendance->id,
                'status' => $attendance->status, 'was_duplicate' => $duplicate,
            ]);

            return ['attendance' => $attendance, 'was_duplicate' => $duplicate];
        });

        return $this->success([
            'attendance' => $result['attendance']->only(['id', 'child_id', 'status', 'arrived_at']),
            'child' => $child->only(['id', 'code', 'full_name', 'saint_name']),
            'scanned_at' => now()->toIso8601String(),
            'was_duplicate' => $result['was_duplicate'],
        ], $result['was_duplicate'] ? 'Thiếu nhi đã được điểm danh trước đó.' : 'Đã điểm danh bằng QR.');
    }

    private function canView(Request $request, Child $child): bool
    {
        $user = $request->user();
        if ($user->can('access-admin')) {
            return true;
        }
        if ($user->child?->is($child)) {
            return true;
        }

        return $user->parentProfile?->children()->whereKey($child->id)->exists() === true;
    }

    private function canScanSession(Request $request, AttendanceSession $session): bool
    {
        $user = $request->user();

        return $user->can('access-admin')
            || $user->teacherProfile?->classes()->whereKey($session->catechism_class_id)->exists() === true;
    }

    private function qrPayload(Child $child): array
    {
        return ['token' => $this->qrCodes->token($child), 'version' => $child->qr_version, 'child' => $child->only(['id', 'code', 'full_name'])];
    }

    private function businessError(string $code, string $message)
    {
        return response()->json(['success' => false, 'message' => $message, 'code' => $code], 422);
    }
}
