<?php

namespace Tests\Feature;

use App\Models\{ActivityLog, Attendance, AttendanceSession, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_teacher_can_create_and_save_attendance_for_assigned_class(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = $teacher->teacherProfile->classes()->firstOrFail();
        $heldAt = now()->startOfMinute()->toIso8601String();

        $sessionId = $this->actingAs($teacher)
            ->postJson("/api/classes/{$class->id}/attendance-sessions", ['held_at' => $heldAt])
            ->assertOk()
            ->json('data.id');

        $child = $class->children()->firstOrFail();
        $this->postJson("/api/attendance-sessions/{$sessionId}/mark", [
            'attendances' => [['child_id' => $child->id, 'status' => 'present']],
        ])->assertOk()->assertJsonPath('data.attendances.0.status', 'present');

        $this->assertDatabaseHas('attendances', [
            'attendance_session_id' => $sessionId,
            'child_id' => $child->id,
            'status' => 'present',
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'attendance.marked']);
    }

    public function test_duplicate_session_time_is_rejected(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = $teacher->teacherProfile->classes()->firstOrFail();
        $existing = AttendanceSession::where('catechism_class_id', $class->id)->firstOrFail();

        $this->actingAs($teacher)->postJson(
            "/api/classes/{$class->id}/attendance-sessions",
            ['held_at' => $existing->held_at->toIso8601String()]
        )->assertUnprocessable();
    }

    public function test_attendance_rejects_unknown_status(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $session = AttendanceSession::whereHas(
            'catechismClass.teachers',
            fn ($query) => $query->whereKey($teacher->teacherProfile->id)
        )->firstOrFail();
        $child = $session->catechismClass->children()->firstOrFail();

        $this->actingAs($teacher)->postJson("/api/attendance-sessions/{$session->id}/mark", [
            'attendances' => [['child_id' => $child->id, 'status' => 'invented']],
        ])->assertUnprocessable();
    }
}
