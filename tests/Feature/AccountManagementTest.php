<?php

namespace Tests\Feature;

use App\Models\Parish;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Models\UserAvatar;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_user_can_view_and_update_their_own_profile(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();

        $this->actingAs($teacher)
            ->getJson('/api/account')
            ->assertOk()
            ->assertJsonPath('data.email', 'teacher@giaoly.test');

        $this->patchJson('/api/account', [
            'name' => 'Phêrô Nguyễn',
            'email' => 'teacher@giaoly.test',
            'phone' => '0901234567',
        ])->assertOk()->assertJsonPath('data.name', 'Phêrô Nguyễn');

        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'phone' => '0901234567']);
    }

    public function test_seeded_database_contains_only_the_four_demo_accounts(): void
    {
        $this->assertSame(4, User::count());
        $this->assertEqualsCanonicalizing([
            'admin@giaoly.test',
            'teacher@giaoly.test',
            'parent@giaoly.test',
            'child@giaoly.test',
        ], User::pluck('email')->all());
    }

    public function test_admin_password_reset_rejects_mismatched_confirmation(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->putJson("/api/admin/accounts/{$teacher->id}/password", [
                'password' => 'new-secure-password',
                'password_confirmation' => 'different-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_avatar_upload_rejects_non_images_and_stores_valid_images_in_shared_storage(): void
    {
        Storage::fake('public');
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();

        $this->actingAs($teacher)
            ->postJson('/api/account/avatar', ['avatar' => UploadedFile::fake()->create('avatar.txt', 10)])
            ->assertUnprocessable();

        $response = $this->postJson('/api/account/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.webp', 300, 300)->size(200),
        ])->assertOk();

        $this->assertStringStartsWith('database:', $response->json('data.avatar_path'));
        $this->assertDatabaseHas('user_avatars', [
            'user_id' => $teacher->id,
            'mime_type' => 'image/webp',
        ]);
        $this->assertSame(1, UserAvatar::where('user_id', $teacher->id)->count());
    }

    public function test_uploaded_avatar_survives_logout_and_login_on_another_instance(): void
    {
        Storage::fake('public');
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();

        $upload = $this->actingAs($teacher, 'web')->postJson('/api/account/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 300, 300)->size(200),
        ])->assertOk();

        Storage::disk('public')->deleteDirectory('avatars');

        $this->postJson('/api/auth/logout')->assertOk();
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $login = $this->postJson('/api/auth/login', [
            'email' => 'teacher@giaoly.test',
            'password' => 'password',
        ])->assertOk();

        $avatarUrl = $login->json('data.avatar_url');

        $this->assertSame("/api/avatars/{$teacher->id}", strtok($avatarUrl, '?'));
        $this->assertSame($upload->json('data.avatar_url'), $avatarUrl);
        $this->get($avatarUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_profile_returns_a_same_origin_avatar_url(): void
    {
        config()->set('filesystems.disks.public.url', 'http://localhost/storage');
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $teacher->update(['avatar_path' => 'avatars/avatar.png']);

        $this->actingAs($teacher)
            ->getJson('/api/account')
            ->assertOk()
            ->assertJsonPath('data.avatar_url', '/storage/avatars/avatar.png');
    }

    public function test_admin_account_list_includes_avatar_url(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $child = User::where('email', 'child@giaoly.test')->firstOrFail();
        $child->update(['avatar_path' => 'avatars/child-one.png']);

        $this->actingAs($admin)
            ->getJson('/api/admin/accounts?search=child@giaoly.test')
            ->assertOk()
            ->assertJsonPath('data.0.id', $child->id)
            ->assertJsonPath('data.0.avatar_url', '/storage/avatars/child-one.png');
    }

    public function test_admin_account_list_query_count_does_not_scale_per_account(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        User::factory()->count(10)->create()->each->assignRole('parent');
        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $this->actingAs($admin)
            ->getJson('/api/admin/accounts')
            ->assertOk()
            ->assertJsonPath('meta.total', 14);

        $this->assertLessThanOrEqual(12, $queryCount, sprintf(
            'Danh sách tài khoản đã chạy %d query.',
            $queryCount,
        ));
    }

    public function test_denied_permission_overrides_role_permission_for_api_and_resource(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $permissionId = Permission::findByName('view-classes')->id;
        $teacher->deniedPermissions()->attach($permissionId);

        $this->actingAs($teacher)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonMissing(['permissions' => 'view-classes']);

        $this->getJson('/api/teachers/me/classes')->assertForbidden();
    }

    public function test_admin_can_list_create_and_customize_an_account(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/api/admin/accounts')
            ->assertOk()
            ->assertJsonStructure(['data' => ['*' => ['id', 'name', 'email', 'roles', 'permissions']]]);

        $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);
        $created = $this->postJson('/api/admin/accounts', [
            'name' => 'Tài khoản mới',
            'email' => 'new@giaoly.test',
            'password' => 'secure-password',
            'role' => 'parent',
        ])->assertCreated();

        $targetId = $created->json('data.id');
        $this->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->putJson("/api/admin/accounts/{$targetId}/access", [
                'role' => 'parent',
                'granted_permissions' => ['view-attendance'],
                'denied_permissions' => ['view-classes'],
            ])->assertOk()
            ->assertJsonPath('data.roles.0', 'parent')
            ->assertJsonMissing(['permissions' => 'view-classes']);
    }

    public function test_teacher_accounts_must_be_created_through_teacher_management(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $teacherProfileCount = TeacherProfile::count();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->postJson('/api/admin/accounts', [
                'name' => 'Giáo lý viên sai luồng',
                'email' => 'wrong-teacher-flow@example.test',
                'password' => 'secure-password',
                'role' => 'teacher',
                'parish_id' => Parish::firstOrFail()->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'TEACHER_CREATION_REQUIRES_PROFILE');

        $this->assertDatabaseMissing('users', ['email' => 'wrong-teacher-flow@example.test']);
        $this->assertSame($teacherProfileCount, TeacherProfile::count());
    }

    public function test_account_without_teacher_profile_cannot_be_assigned_teacher_role(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $parent = User::where('email', 'parent@giaoly.test')->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->putJson("/api/admin/accounts/{$parent->id}/access", [
                'role' => 'teacher',
                'granted_permissions' => [],
                'denied_permissions' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'TEACHER_CREATION_REQUIRES_PROFILE');

        $this->assertTrue($parent->fresh()->hasRole('parent'));
        $this->assertFalse($parent->fresh()->hasRole('teacher'));
    }

    public function test_only_admin_cannot_lock_archive_or_remove_their_own_admin_role(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->patchJson("/api/admin/accounts/{$admin->id}/status", ['status' => 'blocked'])
            ->assertUnprocessable();

        $this->deleteJson("/api/admin/accounts/{$admin->id}")->assertUnprocessable();

        $this->putJson("/api/admin/accounts/{$admin->id}/access", [
            'role' => 'teacher',
            'granted_permissions' => [],
            'denied_permissions' => [],
        ])->assertUnprocessable();

        $this->putJson("/api/admin/accounts/{$admin->id}/access", [
            'role' => 'admin',
            'granted_permissions' => [],
            'denied_permissions' => ['manage-users'],
        ])->assertUnprocessable()->assertJsonPath('code', 'ADMIN_FULL_ACCESS_REQUIRED');
    }

    public function test_system_does_not_allow_a_second_admin_account(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $parent = User::where('email', 'parent@giaoly.test')->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->postJson('/api/admin/accounts', [
                'name' => 'Quản trị viên thứ hai',
                'email' => 'second-admin@giaoly.test',
                'password' => 'secure-password',
                'role' => 'admin',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'SINGLE_ADMIN_ACCOUNT');

        $this->putJson("/api/admin/accounts/{$parent->id}/access", [
            'role' => 'admin',
            'granted_permissions' => [],
            'denied_permissions' => [],
        ])->assertUnprocessable()->assertJsonPath('code', 'SINGLE_ADMIN_ACCOUNT');

        $this->assertSame(1, User::role('admin')->count());
        $this->assertTrue($parent->fresh()->hasRole('parent'));
    }

    public function test_profile_fields_do_not_require_password_but_blocking_an_account_does(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();

        $this->actingAs($admin)
            ->patchJson("/api/admin/accounts/{$teacher->id}", [
                'name' => 'Giáo lý viên đã cập nhật',
                'email' => $teacher->email,
                'phone' => '0901234567',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Giáo lý viên đã cập nhật');

        $this->patchJson("/api/admin/accounts/{$teacher->id}/status", ['status' => 'blocked'])
            ->assertForbidden()
            ->assertJsonPath('code', 'PASSWORD_CONFIRMATION_REQUIRED');

        $this->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->patchJson("/api/admin/accounts/{$teacher->id}/status", ['status' => 'blocked'])
            ->assertOk()
            ->assertJsonPath('data.status', 'blocked');

        $this->assertDatabaseHas('users', [
            'id' => $teacher->id,
            'status' => 'blocked',
        ]);
    }

    public function test_account_phone_fields_reject_invalid_vietnamese_numbers(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $parent = User::where('email', 'parent@giaoly.test')->firstOrFail();

        $this->actingAs($parent)
            ->patchJson('/api/account', [
                'name' => $parent->name,
                'email' => $parent->email,
                'phone' => '0123456789',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->postJson('/api/admin/accounts', [
                'name' => 'Phụ huynh sai số điện thoại',
                'email' => 'invalid-phone@example.test',
                'phone' => '0123456789',
                'password' => 'secure-password',
                'role' => 'parent',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->patchJson("/api/admin/accounts/{$parent->id}", [
            'name' => $parent->name,
            'email' => $parent->email,
            'phone' => '1234567890',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_admin_soft_deletes_and_restores_an_account(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->deleteJson("/api/admin/accounts/{$teacher->id}")
            ->assertOk();
        $this->assertSoftDeleted('users', ['id' => $teacher->id]);

        $this->postJson("/api/admin/accounts/{$teacher->id}/restore")
            ->assertOk();
        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'deleted_at' => null]);
    }
}
