<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Attendance\CreateAttendanceQrRequest;
use App\Http\Requests\Attendance\ScanQrAttendanceRequest;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\CatechismClass;
use App\Services\AttendanceSessionQrCodeService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QrAttendanceController extends ApiController
{
    public function __construct(
        private readonly AttendanceSessionQrCodeService $sessionQrCodes,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(CreateAttendanceQrRequest $request, CatechismClass $class)
    {
        $this->authorize('takeAttendance', $class);
        $data = $request->validated();
        $heldAt = Carbon::parse($data['held_at'])->utc();
        $expiresAt = Carbon::parse($data['qr_expires_at'])->utc();

        if ($class->attendanceSessions()->where('held_at', $heldAt)->exists()) {
            return $this->businessError('ATTENDANCE_SESSION_EXISTS', 'Phiên điểm danh tại thời điểm này đã tồn tại.');
        }

        $session = $class->attendanceSessions()->create([
            'held_at' => $heldAt,
            'qr_expires_at' => $expiresAt,
            'taken_by' => $request->user()->id,
            'note' => $data['note'] ?? null,
        ]);
        $session->setRelation('catechismClass', $class);

        $this->auditLogger->record($request, 'attendance.qr_created', $session, null, [
            'catechism_class_id' => $class->id,
            'qr_expires_at' => $expiresAt->toIso8601String(),
        ]);

        return $this->success($this->sessionQrPayload($session), 'Đã tạo mã QR điểm danh.', status: 201);
    }

    public function sessionQr(Request $request, AttendanceSession $session)
    {
        abort_unless($request->user()->can('create-attendance-qr') && $this->canScanSession($request, $session), 403);
        abort_unless($session->qr_expires_at, 404);

        return $this->success($this->sessionQrPayload($session->load('catechismClass')), 'Đã tải mã QR điểm danh.');
    }

    public function checkIn(ScanQrAttendanceRequest $request)
    {
        $session = $this->sessionQrCodes->resolve($request->validated('token'));
        if (! $session) {
            return $this->businessError('INVALID_QR_CODE', 'Mã QR không hợp lệ.');
        }
        if ($session->qr_expires_at->isPast()) {
            return $this->businessError('QR_CODE_EXPIRED', 'Mã QR điểm danh đã hết hạn.');
        }

        $child = $request->user()->child;
        if (! $child || $child->trashed() || $child->status !== 'studying') {
            return $this->businessError('CHILD_NOT_ACTIVE', 'Tài khoản chưa liên kết với hồ sơ thiếu nhi đang học.');
        }
        if (! $child->enrollments()->where('catechism_class_id', $session->catechism_class_id)->where('status', 'active')->exists()) {
            return $this->businessError('CHILD_NOT_IN_SESSION_CLASS', 'Bạn không thuộc lớp của phiên điểm danh này.');
        }

        $now = now();
        $status = $now->greaterThan($session->held_at->copy()->addMinutes(15)) ? 'late' : 'present';
        $inserted = DB::table('attendances')->insertOrIgnore([
            'attendance_session_id' => $session->id,
            'child_id' => $child->id,
            'status' => $status,
            'arrived_at' => $now->format('H:i:s'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $attendance = Attendance::query()
            ->where('attendance_session_id', $session->id)
            ->where('child_id', $child->id)
            ->firstOrFail();
        $duplicate = $inserted === 0;

        $this->auditLogger->record($request, 'attendance.qr_checked_in', $session, null, [
            'child_id' => $child->id,
            'attendance_id' => $attendance->id,
            'status' => $attendance->status,
            'was_duplicate' => $duplicate,
        ]);

        return $this->success([
            'attendance' => $attendance->only(['id', 'child_id', 'status', 'arrived_at']),
            'session' => [
                'id' => $session->id,
                'held_at' => $session->held_at->toIso8601String(),
                'class' => $session->catechismClass->only(['id', 'name', 'code']),
            ],
            'checked_in_at' => $now->toIso8601String(),
            'was_duplicate' => $duplicate,
        ], $duplicate ? 'Bạn đã điểm danh cho buổi học này rồi.' : 'Điểm danh thành công.');
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

    private function canScanSession(Request $request, AttendanceSession $session): bool
    {
        $user = $request->user();

        return $user->can('access-admin')
            || $user->teacherProfile?->classes()->whereKey($session->catechism_class_id)->exists() === true;
    }

    private function sessionQrPayload(AttendanceSession $session): array
    {
        return [
            'token' => $this->sessionQrCodes->token($session),
            'session' => [
                'id' => $session->id,
                'held_at' => $session->held_at->toIso8601String(),
                'qr_expires_at' => $session->qr_expires_at->toIso8601String(),
                'note' => $session->note,
                'class' => $session->catechismClass->only(['id', 'name', 'code']),
            ],
        ];
    }

    private function businessError(string $code, string $message)
    {
        return response()->json(['success' => false, 'message' => $message, 'code' => $code], 422);
    }
}
