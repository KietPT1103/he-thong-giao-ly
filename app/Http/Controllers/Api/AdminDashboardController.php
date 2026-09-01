<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AdminDashboardResource;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\CatechismClass;
use App\Models\Child;
use App\Models\LeaveRequest;
use App\Models\Parish;
use App\Models\TeacherProfile;
use Illuminate\Http\Request;

class AdminDashboardController extends ApiController
{
    public function __invoke(Request $request)
    {
        $this->authorize('access-admin');

        $weekSessions = AttendanceSession::query()
            ->whereBetween('held_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->pluck('id');
        $attendanceTotal = Attendance::whereIn('attendance_session_id', $weekSessions)->count();
        $attended = Attendance::whereIn('attendance_session_id', $weekSessions)
            ->whereIn('status', ['present', 'late'])
            ->count();

        $data = [
            'summary' => [
                'parish_count' => Parish::count(),
                'teacher_count' => TeacherProfile::count(),
                'child_count' => Child::where('status', 'studying')->count(),
                'active_class_count' => CatechismClass::where('status', 'active')->count(),
                'pending_leave_request_count' => LeaveRequest::where('status', 'pending')->count(),
                'class_session_count_this_week' => $weekSessions->count(),
            ],
            'attendance' => [
                'rate_this_week' => $attendanceTotal > 0
                    ? round(($attended / $attendanceTotal) * 100, 1)
                    : null,
                'attended' => $attended,
                'total' => $attendanceTotal,
            ],
            'parishes' => Parish::query()
                ->withCount(['children'])
                ->withCount(['academicYears'])
                ->orderBy('name')
                ->limit(6)
                ->get(['id', 'name', 'code']),
            'recent_announcements' => Announcement::query()
                ->latest()
                ->limit(5)
                ->get(['id', 'title', 'importance', 'created_at']),
            'recent_sessions' => AttendanceSession::query()
                ->with(['catechismClass:id,name'])
                ->withCount('attendances')
                ->latest('held_at')
                ->limit(5)
                ->get(['id', 'catechism_class_id', 'held_at']),
        ];

        return $this->success(new AdminDashboardResource($data));
    }
}
