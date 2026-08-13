<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\AttendanceSessionQrCodeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QrAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-08-13 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_assigned_teacher_can_create_an_expiring_attendance_qr(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = $teacher->teacherProfile->classes()->firstOrFail();

        $response = $this->actingAs($teacher)->postJson("/api/classes/{$class->id}/attendance-qr", [
            'held_at' => now()->toIso8601String(),
            'qr_expires_at' => now()->addMinutes(20)->toIso8601String(),
            'note' => 'Điểm danh đầu giờ',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.session.class.id', $class->id)
            ->assertJsonPath('data.session.note', 'Điểm danh đầu giờ')
            ->assertJsonStructure(['data' => ['token', 'session' => ['id', 'held_at', 'qr_expires_at', 'class']]]);
        $this->assertDatabaseHas('attendance_sessions', [
            'id' => $response->json('data.session.id'),
            'catechism_class_id' => $class->id,
            'taken_by' => $teacher->id,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'attendance.qr_created']);
    }

    public function test_qr_expiry_must_be_in_the_future_and_after_the_session_time(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = $teacher->teacherProfile->classes()->firstOrFail();

        $this->actingAs($teacher)->postJson("/api/classes/{$class->id}/attendance-qr", [
            'held_at' => now()->addHour()->toIso8601String(),
            'qr_expires_at' => now()->addMinutes(30)->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('qr_expires_at');
    }

    public function test_child_checks_in_once_even_when_the_same_qr_is_scanned_again(): void
    {
        [$childUser, $session, $token] = $this->activeQrForSeededChild();

        $first = $this->actingAs($childUser)->postJson('/api/attendance/qr/check-in', ['token' => $token]);
        $first->assertOk()
            ->assertJsonPath('data.was_duplicate', false)
            ->assertJsonPath('data.attendance.status', 'present')
            ->assertJsonPath('data.session.class.id', $session->catechism_class_id);

        $this->postJson('/api/attendance/qr/check-in', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.was_duplicate', true);

        $this->assertSame(1, $session->attendances()->where('child_id', $childUser->child->id)->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'attendance.qr_checked_in']);
    }

    public function test_child_cannot_use_an_expired_tampered_or_other_class_qr(): void
    {
        $childUser = User::where('email', 'child@giaoly.test')->firstOrFail();
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $ownClassId = $childUser->child->activeEnrollment->catechism_class_id;
        $otherClass = $teacher->teacherProfile->classes()->whereKeyNot($ownClassId)->firstOrFail();
        $otherSession = AttendanceSession::create([
            'catechism_class_id' => $otherClass->id,
            'held_at' => now(),
            'qr_expires_at' => now()->addMinutes(10),
            'taken_by' => $teacher->id,
        ]);
        $otherToken = app(AttendanceSessionQrCodeService::class)->token($otherSession);

        $this->actingAs($childUser)->postJson('/api/attendance/qr/check-in', ['token' => $otherToken])
            ->assertUnprocessable()->assertJsonPath('code', 'CHILD_NOT_IN_SESSION_CLASS');
        $this->postJson('/api/attendance/qr/check-in', ['token' => $otherToken.'x'])
            ->assertUnprocessable()->assertJsonPath('code', 'INVALID_QR_CODE');

        $expiredSession = AttendanceSession::create([
            'catechism_class_id' => $ownClassId,
            'held_at' => now()->subHour(),
            'qr_expires_at' => now()->subMinute(),
            'taken_by' => $teacher->id,
        ]);
        $expiredToken = app(AttendanceSessionQrCodeService::class)->token($expiredSession);
        $this->postJson('/api/attendance/qr/check-in', ['token' => $expiredToken])
            ->assertUnprocessable()->assertJsonPath('code', 'QR_CODE_EXPIRED');
    }

    public function test_only_child_accounts_with_permission_can_check_in(): void
    {
        [, , $token] = $this->activeQrForSeededChild();
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $parent = User::where('email', 'parent@giaoly.test')->firstOrFail();

        $this->postJson('/api/attendance/qr/check-in', ['token' => $token])->assertUnauthorized();
        $this->actingAs($teacher)->postJson('/api/attendance/qr/check-in', ['token' => $token])->assertForbidden();
        $this->actingAs($parent)->postJson('/api/attendance/qr/check-in', ['token' => $token])->assertForbidden();
    }

    public function test_session_qr_permission_migration_updates_default_roles(): void
    {
        $permissions = ['create-attendance-qr', 'check-in-attendance-qr'];
        foreach (Role::all() as $role) {
            $role->revokePermissionTo($permissions);
        }

        $migration = require database_path('migrations/2026_08_13_000110_add_session_qr_permissions.php');
        $migration->up();

        $this->assertTrue(Role::findByName('admin')->hasAllPermissions($permissions));
        $this->assertTrue(Role::findByName('teacher')->hasPermissionTo('create-attendance-qr'));
        $this->assertTrue(Role::findByName('child')->hasPermissionTo('check-in-attendance-qr'));
        $this->assertFalse(Role::findByName('parent')->hasAnyPermission($permissions));
    }

    private function activeQrForSeededChild(): array
    {
        $childUser = User::where('email', 'child@giaoly.test')->firstOrFail();
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $session = AttendanceSession::create([
            'catechism_class_id' => $childUser->child->activeEnrollment->catechism_class_id,
            'held_at' => now(),
            'qr_expires_at' => now()->addMinutes(10),
            'taken_by' => $teacher->id,
        ]);

        return [$childUser, $session, app(AttendanceSessionQrCodeService::class)->token($session)];
    }
}
