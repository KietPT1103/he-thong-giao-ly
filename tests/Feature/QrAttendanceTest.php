<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Child;
use App\Models\TeacherProfile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QrAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_qr_is_visible_only_to_authorized_family_members_and_admin(): void
    {
        $child = Child::whereNotNull('user_id')->firstOrFail();
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $parent = User::where('email', 'parent@giaoly.test')->firstOrFail();
        $childUser = User::where('email', 'child@giaoly.test')->firstOrFail();
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();

        $this->getJson("/api/children/{$child->id}/qr")->assertUnauthorized();
        $this->actingAs($teacher)->getJson("/api/children/{$child->id}/qr")->assertForbidden();
        $this->actingAs($parent)->getJson("/api/children/{$child->id}/qr")
            ->assertOk()->assertJsonPath('data.child.id', $child->id);
        $this->actingAs($parent)->getJson('/api/parents/me/children')
            ->assertOk()->assertJsonPath('data.0.id', $child->id);
        $this->actingAs($childUser)->getJson("/api/children/{$child->id}/qr")
            ->assertOk()->assertJsonStructure(['data' => ['token', 'version', 'child']]);
        $this->actingAs($childUser)->getJson('/api/auth/me')
            ->assertOk()->assertJsonPath('data.child_profile_id', $child->id);
        $this->actingAs($admin)->getJson("/api/children/{$child->id}/qr")->assertOk();

        $parent->deniedPermissions()->attach(Permission::findByName('view-child-qr', 'web'));
        $this->actingAs($parent)->getJson("/api/children/{$child->id}/qr")->assertForbidden();
        $this->actingAs($parent)->getJson('/api/parents/me/children')->assertForbidden();
    }

    public function test_assigned_teacher_scans_valid_qr_and_duplicate_is_idempotent(): void
    {
        Carbon::setTestNow('2026-08-12 08:10:00');
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $child = Child::firstOrFail();
        $class = $child->activeEnrollment->catechismClass;
        $session = AttendanceSession::create([
            'catechism_class_id' => $class->id,
            'held_at' => now()->subMinutes(10),
            'taken_by' => $teacher->id,
        ]);
        $token = $this->actingAs(User::where('email', 'admin@giaoly.test')->firstOrFail())
            ->getJson("/api/children/{$child->id}/qr")->assertOk()->json('data.token');

        $first = $this->actingAs($teacher)
            ->postJson("/api/attendance-sessions/{$session->id}/qr/scan", ['token' => $token]);
        $first->assertOk()
            ->assertJsonPath('data.child.id', $child->id)
            ->assertJsonPath('data.attendance.status', 'present')
            ->assertJsonPath('data.was_duplicate', false);

        $this->postJson("/api/attendance-sessions/{$session->id}/qr/scan", ['token' => $token])
            ->assertOk()->assertJsonPath('data.was_duplicate', true);
        $this->assertSame(1, Attendance::where('attendance_session_id', $session->id)->where('child_id', $child->id)->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'attendance.qr_scanned', 'subject_id' => $session->id]);
    }

    public function test_qr_scan_marks_late_after_fifteen_minutes(): void
    {
        Carbon::setTestNow('2026-08-12 08:20:00');
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $child = Child::firstOrFail();
        $session = AttendanceSession::create([
            'catechism_class_id' => $child->activeEnrollment->catechism_class_id,
            'held_at' => now()->subMinutes(20),
            'taken_by' => $teacher->id,
        ]);
        $token = $this->actingAs(User::where('email', 'admin@giaoly.test')->firstOrFail())
            ->getJson("/api/children/{$child->id}/qr")->json('data.token');

        $this->actingAs($teacher)->postJson("/api/attendance-sessions/{$session->id}/qr/scan", ['token' => $token])
            ->assertOk()->assertJsonPath('data.attendance.status', 'late');
    }

    public function test_qr_scan_rejects_tampering_wrong_class_and_unassigned_teacher(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $child = Child::firstOrFail();
        $class = $child->activeEnrollment->catechismClass;
        $otherClass = $teacher->teacherProfile->classes()->whereKeyNot($class->id)->firstOrFail();
        $session = AttendanceSession::create(['catechism_class_id' => $otherClass->id, 'held_at' => now(), 'taken_by' => $teacher->id]);
        $token = $this->actingAs(User::where('email', 'admin@giaoly.test')->firstOrFail())
            ->getJson("/api/children/{$child->id}/qr")->json('data.token');

        $this->actingAs($teacher)->postJson("/api/attendance-sessions/{$session->id}/qr/scan", ['token' => $token])
            ->assertUnprocessable()->assertJsonPath('code', 'CHILD_NOT_IN_SESSION_CLASS');
        $this->postJson("/api/attendance-sessions/{$session->id}/qr/scan", ['token' => $token.'x'])
            ->assertUnprocessable()->assertJsonPath('code', 'INVALID_QR_CODE');

        $otherUser = User::factory()->create();
        $otherUser->assignRole('teacher');
        TeacherProfile::create(['user_id' => $otherUser->id, 'parish_id' => $child->parish_id, 'code' => 'GLV-OTHER']);
        $this->actingAs($otherUser)->postJson("/api/attendance-sessions/{$session->id}/qr/scan", ['token' => $token])
            ->assertForbidden();
    }

    public function test_admin_rotation_requires_recent_password_and_invalidates_old_qr(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $child = Child::firstOrFail();
        $oldToken = $this->actingAs($admin)->getJson("/api/children/{$child->id}/qr")->json('data.token');

        $this->postJson("/api/admin/children/{$child->id}/qr/rotate")
            ->assertForbidden()->assertJsonPath('code', 'PASSWORD_CONFIRMATION_REQUIRED');
        $newToken = $this->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->postJson("/api/admin/children/{$child->id}/qr/rotate")
            ->assertOk()->assertJsonPath('data.version', 2)->json('data.token');

        $this->assertNotSame($oldToken, $newToken);
        $this->assertDatabaseHas('children', ['id' => $child->id, 'qr_version' => 2]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'child.qr_rotated', 'subject_id' => $child->id]);

        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $session = AttendanceSession::create([
            'catechism_class_id' => $child->activeEnrollment->catechism_class_id,
            'held_at' => now(),
            'taken_by' => $teacher->id,
        ]);
        $this->actingAs($teacher)->postJson("/api/attendance-sessions/{$session->id}/qr/scan", ['token' => $oldToken])
            ->assertUnprocessable()->assertJsonPath('code', 'INVALID_QR_CODE');
    }

    public function test_qr_permission_migration_updates_existing_default_roles(): void
    {
        $permissions = ['view-child-qr', 'scan-attendance-qr', 'rotate-child-qr'];
        foreach (Role::all() as $role) {
            $role->revokePermissionTo($permissions);
        }

        $migration = require database_path('migrations/2026_08_12_000110_add_qr_permissions.php');
        $migration->up();

        $this->assertTrue(Role::findByName('admin')->hasAllPermissions($permissions));
        $this->assertTrue(Role::findByName('teacher')->hasPermissionTo('scan-attendance-qr'));
        $this->assertTrue(Role::findByName('parent')->hasPermissionTo('view-child-qr'));
        $this->assertTrue(Role::findByName('child')->hasPermissionTo('view-child-qr'));
    }
}
