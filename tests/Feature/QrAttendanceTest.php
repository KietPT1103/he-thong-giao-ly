<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\AttendanceSessionQrCodeService;
use App\Services\ChildDeviceCredentialService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            ->assertJsonStructure(['data' => ['token', 'scan_url', 'session' => ['id', 'held_at', 'qr_expires_at', 'class']]]);
        $this->assertSame(
            '/attendance/scan?token='.rawurlencode($response->json('data.token')),
            $response->json('data.scan_url'),
        );
        $this->assertDatabaseHas('attendance_sessions', [
            'id' => $response->json('data.session.id'),
            'catechism_class_id' => $class->id,
            'taken_by' => $teacher->id,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'attendance.qr_created']);
    }

    public function test_teacher_qr_workspace_bootstraps_classes_and_recent_qr_sessions_in_one_request(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = $teacher->teacherProfile->classes()->firstOrFail();
        $createdIds = collect(range(1, 14))->map(fn ($index) => AttendanceSession::create([
            'catechism_class_id' => $class->id,
            'held_at' => now()->subMinutes($index),
            'qr_expires_at' => now()->addMinutes(20 - $index),
            'taken_by' => $teacher->id,
            'note' => "Phiên QR {$index}",
        ])->id);
        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $response = $this->actingAs($teacher)
            ->getJson('/api/teacher/qr-workspace')
            ->assertOk()
            ->assertJsonCount(6, 'data.classes')
            ->assertJsonCount(12, 'data.recent_sessions')
            ->assertJsonPath('data.recent_sessions.0.id', $createdIds->first())
            ->assertJsonPath('data.recent_sessions.0.class.id', $class->id)
            ->assertJsonMissingPath('data.recent_sessions.0.attendances');

        $this->assertLessThanOrEqual(9, $queryCount, "QR workspace đã chạy {$queryCount} query.");
        $this->assertSame(12, count($response->json('data.recent_sessions')));
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
        $this->useDeviceFor($childUser);

        $first = $this->postJson('/api/attendance/qr/check-in', ['token' => $token]);
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
        $this->useDeviceFor($childUser);
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

        $this->postJson('/api/attendance/qr/check-in', ['token' => $otherToken])
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

    public function test_only_activated_child_devices_with_permission_can_check_in(): void
    {
        [$childUser, , $token] = $this->activeQrForSeededChild();
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $parent = User::where('email', 'parent@giaoly.test')->firstOrFail();

        $this->postJson('/api/attendance/qr/check-in', ['token' => $token])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'DEVICE_ACTIVATION_REQUIRED');

        $this->useDeviceFor($childUser);
        $childUser->deniedPermissions()->attach(Permission::findByName('check-in-attendance-qr')->id);
        $this->postJson('/api/attendance/qr/check-in', ['token' => $token])->assertForbidden();

        $this->actingAs($teacher)->postJson('/api/child-device')->assertForbidden();
        $this->actingAs($parent)->postJson('/api/child-device')->assertForbidden();
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

    public function test_qr_expiry_repair_migration_restores_a_missing_column_and_is_idempotent(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropColumn('qr_expires_at');
        });
        $this->assertFalse(Schema::hasColumn('attendance_sessions', 'qr_expires_at'));

        $migration = require database_path(
            'migrations/2026_08_26_000100_ensure_qr_expires_at_exists_on_attendance_sessions_table.php',
        );
        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('attendance_sessions', 'qr_expires_at'));

        $migration->down();
        $this->assertTrue(Schema::hasColumn('attendance_sessions', 'qr_expires_at'));
    }

    public function test_child_device_credentials_are_hashed_and_rotated(): void
    {
        $child = User::where('email', 'child@giaoly.test')->firstOrFail()->child;
        $credentials = app(ChildDeviceCredentialService::class);

        [$device, $token] = $credentials->issue($child);

        $this->assertNotSame($token, $device->token_hash);
        $this->assertSame($device->id, $credentials->resolve($token)?->id);

        [$rotatedDevice, $rotatedToken] = $credentials->issue($child);

        $this->assertSame($device->id, $rotatedDevice->id);
        $this->assertNull($credentials->resolve($token));
        $this->assertSame($rotatedDevice->id, $credentials->resolve($rotatedToken)?->id);

        $rotatedDevice->update(['expires_at' => now()->subSecond()]);
        $this->assertNull($credentials->resolve($rotatedToken));
    }

    public function test_child_can_activate_and_revoke_the_current_device_without_exposing_the_secret(): void
    {
        $childUser = User::where('email', 'child@giaoly.test')->firstOrFail();

        $activated = $this->actingAs($childUser)->postJson('/api/child-device');

        $activated->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.is_current_device', true)
            ->assertJsonMissing(['token'])
            ->assertJsonMissing(['token_hash'])
            ->assertCookie(ChildDeviceCredentialService::COOKIE_NAME);
        $deviceToken = $activated->getCookie(ChildDeviceCredentialService::COOKIE_NAME)->getValue();
        $cookie = $activated->getCookie(ChildDeviceCredentialService::COOKIE_NAME, false);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->withCredentials()
            ->withCookie(ChildDeviceCredentialService::COOKIE_NAME, $deviceToken)
            ->getJson('/api/child-device')
            ->assertOk()
            ->assertJsonPath('data.is_current_device', true);

        $this->deleteJson('/api/child-device')
            ->assertOk()
            ->assertCookieExpired(ChildDeviceCredentialService::COOKIE_NAME);
        $this->assertNotNull($childUser->child->device()->firstOrFail()->revoked_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'child.device_activated']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'child.device_revoked']);
    }

    public function test_activated_device_can_check_in_without_an_account_session(): void
    {
        [$childUser, $session, $qrToken] = $this->activeQrForSeededChild();
        [$device, $deviceToken] = app(ChildDeviceCredentialService::class)->issue($childUser->child);

        $this->withCredentials()
            ->withCookie(ChildDeviceCredentialService::COOKIE_NAME, $deviceToken)
            ->postJson('/api/attendance/qr/check-in', ['token' => $qrToken])
            ->assertOk()
            ->assertJsonPath('data.was_duplicate', false)
            ->assertJsonPath('data.session.class.id', $session->catechism_class_id);

        $this->assertNotNull($device->fresh()->last_used_at);
    }

    public function test_missing_or_revoked_device_cannot_check_in(): void
    {
        [$childUser, , $qrToken] = $this->activeQrForSeededChild();

        $this->postJson('/api/attendance/qr/check-in', ['token' => $qrToken])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'DEVICE_ACTIVATION_REQUIRED');

        [$device, $deviceToken] = app(ChildDeviceCredentialService::class)->issue($childUser->child);
        $device->update(['revoked_at' => now()]);

        $this->withCredentials()
            ->withCookie(ChildDeviceCredentialService::COOKIE_NAME, $deviceToken)
            ->postJson('/api/attendance/qr/check-in', ['token' => $qrToken])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'DEVICE_ACTIVATION_REQUIRED');
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

    private function useDeviceFor(User $childUser): void
    {
        [, $token] = app(ChildDeviceCredentialService::class)->issue($childUser->child);

        $this->withCredentials()
            ->withCookie(ChildDeviceCredentialService::COOKIE_NAME, $token);
    }
}
