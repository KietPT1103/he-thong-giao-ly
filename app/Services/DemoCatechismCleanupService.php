<?php

namespace App\Services;

use App\Models\CatechismClass;
use App\Models\Child;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class DemoCatechismCleanupService
{
    public function plan(): array
    {
        $childUser = User::withTrashed()->where('email', 'child@giaoly.test')->firstOrFail();
        $keptChild = Child::withTrashed()->where('user_id', $childUser->id)->first();
        $keptClass = $keptChild?->activeEnrollment?->catechismClass;
        if (! $keptChild || ! $keptClass) {
            throw new DomainException('Không tìm thấy hồ sơ hoặc lớp hiện tại của Thiếu nhi 1.');
        }

        $otherLinkedChildren = Child::withTrashed()
            ->whereKeyNot($keptChild->id)
            ->whereNotNull('user_id')
            ->get(['id', 'code', 'full_name']);
        if ($otherLinkedChildren->isNotEmpty()) {
            throw new DomainException(
                'Phát hiện hồ sơ thiếu nhi khác đã có tài khoản: '
                .$otherLinkedChildren->pluck('code')->join(', ')
                .'. Đã từ chối dọn dữ liệu.',
            );
        }

        $unwantedChildIds = Child::withTrashed()->whereNull('user_id')->pluck('id');
        $unwantedClassIds = CatechismClass::withTrashed()->whereKeyNot($keptClass->id)->pluck('id');

        return [
            'kept_child_id' => $keptChild->id,
            'kept_child_name' => $keptChild->full_name,
            'kept_class_id' => $keptClass->id,
            'kept_class_name' => $keptClass->name,
            'preserved_accounts' => User::withTrashed()->count(),
            'delete_children' => $unwantedChildIds->count(),
            'delete_classes' => $unwantedClassIds->count(),
            'delete_attendance_sessions' => DB::table('attendance_sessions')
                ->whereIn('catechism_class_id', $unwantedClassIds)->count(),
            'delete_attendances' => DB::table('attendances')
                ->whereIn('child_id', $unwantedChildIds)
                ->orWhereIn('attendance_session_id', DB::table('attendance_sessions')
                    ->whereIn('catechism_class_id', $unwantedClassIds)
                    ->select('id'))
                ->count(),
        ];
    }

    public function execute(): array
    {
        $plan = $this->plan();

        return DB::transaction(function () use ($plan) {
            $unwantedChildIds = Child::withTrashed()
                ->whereNull('user_id')
                ->lockForUpdate()
                ->pluck('id');
            $unwantedClassIds = CatechismClass::withTrashed()
                ->whereKeyNot($plan['kept_class_id'])
                ->lockForUpdate()
                ->pluck('id');
            $unwantedSessionIds = DB::table('attendance_sessions')
                ->whereIn('catechism_class_id', $unwantedClassIds)
                ->lockForUpdate()
                ->pluck('id');

            DB::table('attendances')
                ->whereIn('child_id', $unwantedChildIds)
                ->orWhereIn('attendance_session_id', $unwantedSessionIds)
                ->delete();
            DB::table('leave_requests')
                ->whereIn('child_id', $unwantedChildIds)
                ->orWhereIn('attendance_session_id', $unwantedSessionIds)
                ->delete();
            DB::table('child_devices')->whereIn('child_id', $unwantedChildIds)->delete();
            DB::table('parent_child')->whereIn('child_id', $unwantedChildIds)->delete();
            DB::table('enrollments')
                ->whereIn('child_id', $unwantedChildIds)
                ->orWhereIn('catechism_class_id', $unwantedClassIds)
                ->delete();
            DB::table('attendance_sessions')->whereIn('id', $unwantedSessionIds)->delete();
            DB::table('class_schedules')->whereIn('catechism_class_id', $unwantedClassIds)->delete();
            DB::table('teacher_class_assignments')->whereIn('catechism_class_id', $unwantedClassIds)->delete();
            DB::table('catechism_classes')->whereIn('id', $unwantedClassIds)->delete();
            DB::table('children')->whereIn('id', $unwantedChildIds)->delete();

            return $this->plan();
        });
    }
}
