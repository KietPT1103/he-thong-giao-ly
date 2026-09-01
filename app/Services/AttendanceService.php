<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\ActivityLog;
use App\Models\AttendanceSession;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function mark(AttendanceSession $session, array $rows, int $userId): AttendanceSession
    {
        return DB::transaction(function () use ($session, $rows, $userId) {
            foreach ($rows as $row) {
                $attendance = $session->attendances()->firstOrNew(['child_id' => $row['child_id']]);
                $old = $attendance->exists ? $attendance->only(['status', 'note']) : null;
                $attendance->fill(['status' => $row['status'], 'note' => $row['note'] ?? null])->save();
                ActivityLog::create(['user_id' => $userId, 'action' => 'attendance.marked', 'subject_type' => $attendance::class, 'subject_id' => $attendance->id, 'old_values' => $old, 'new_values' => $attendance->only(['status', 'note'])]);
            }

return $session->load('attendances.child');
        });
    }

    public function markAllPresent(AttendanceSession $session, int $userId): AttendanceSession
    {
        $rows = $session->catechismClass->children()->pluck('children.id')->map(fn ($id) => ['child_id' => $id, 'status' => AttendanceStatus::Present->value])->all();

        return $this->mark($session, $rows, $userId);
    }
}
