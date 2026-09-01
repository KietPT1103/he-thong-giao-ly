<?php

namespace Tests\Feature;

use App\Models\CatechismClass;
use App\Models\Child;
use App\Models\Enrollment;
use App\Models\ParentProfile;
use App\Models\Parish;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminFamilyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_family_management_requires_authentication_and_specific_permissions(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $restrictedAdmin = User::factory()->create();
        $restrictedAdmin->assignRole('admin');
        $restrictedAdmin->deniedPermissions()->attach(Permission::findByName('view-parents')->id);

        $this->getJson('/api/admin/parents')->assertUnauthorized();
        $this->actingAs($teacher)->getJson('/api/admin/parents')->assertForbidden();
        $this->actingAs($restrictedAdmin)->getJson('/api/admin/parents')->assertForbidden();
    }

    public function test_admin_can_create_and_update_a_parent_with_linked_children(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $parish = Parish::firstOrFail();
        $children = Child::query()->take(2)->get();

        $created = $this->actingAs($admin)->postJson('/api/admin/parents', [
            'name' => '  Phụ huynh mới  ',
            'email' => '  new-parent@example.test  ',
            'phone' => '0912345678',
            'parish_id' => $parish->id,
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'child_ids' => $children->pluck('id')->all(),
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.name', 'Phụ huynh mới')
            ->assertJsonPath('data.children_count', 2)
            ->assertJsonPath('data.account_status', 'active');

        $parentId = $created->json('data.id');
        $parent = ParentProfile::findOrFail($parentId);
        $this->assertTrue($parent->user->hasRole('parent'));
        $this->assertSame($children->pluck('id')->sort()->values()->all(), $parent->children()->pluck('children.id')->sort()->values()->all());
        $this->assertDatabaseHas('activity_logs', ['action' => 'parent.created', 'subject_id' => $parentId]);

        $this->actingAs($admin)
            ->patchJson("/api/admin/parents/{$parentId}", [
                'name' => 'Phụ huynh đã cập nhật',
                'phone' => '0987654321',
                'child_ids' => [$children->first()->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Phụ huynh đã cập nhật')
            ->assertJsonPath('data.children_count', 1);

        $this->assertDatabaseHas('users', ['id' => $parent->user_id, 'name' => 'Phụ huynh đã cập nhật']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'parent.updated', 'subject_id' => $parentId]);
    }

    public function test_parent_validation_rejects_duplicate_email_and_mismatched_password_confirmation(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $parish = Parish::firstOrFail();

        $this->actingAs($admin)->postJson('/api/admin/parents', [
            'name' => 'Phụ huynh lỗi',
            'email' => 'parent@giaoly.test',
            'phone' => '0123456789',
            'parish_id' => $parish->id,
            'password' => 'secure-password',
            'password_confirmation' => 'different-password',
            'child_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'phone', 'password']);
    }

    public function test_admin_can_create_and_update_a_child_with_parents_and_current_class(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $parish = Parish::firstOrFail();
        $parent = ParentProfile::firstOrFail();
        $class = CatechismClass::query()->firstOrFail();

        $created = $this->actingAs($admin)->postJson('/api/admin/children', [
            'full_name' => '  Thiếu nhi mới  ',
            'code' => '  TN-NEW  ',
            'saint_name' => 'Maria',
            'date_of_birth' => '2015-06-12',
            'parish_id' => $parish->id,
            'status' => 'studying',
            'parent_ids' => [$parent->id],
            'class_id' => $class->id,
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.full_name', 'Thiếu nhi mới')
            ->assertJsonPath('data.parents_count', 1)
            ->assertJsonPath('data.current_class.id', $class->id);

        $childId = $created->json('data.id');
        $this->assertDatabaseHas('enrollments', [
            'child_id' => $childId,
            'catechism_class_id' => $class->id,
            'status' => Enrollment::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'child.created', 'subject_id' => $childId]);

        $this->actingAs($admin)
            ->patchJson("/api/admin/children/{$childId}", [
                'saint_name' => 'Anna',
                'parent_ids' => [],
                'class_id' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.saint_name', 'Anna')
            ->assertJsonPath('data.parents_count', 0)
            ->assertJsonPath('data.current_class', null);

        $this->assertDatabaseHas('enrollments', [
            'child_id' => $childId,
            'catechism_class_id' => $class->id,
            'status' => Enrollment::STATUS_INACTIVE,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'child.updated', 'subject_id' => $childId]);
    }

    public function test_admin_child_options_include_classes_from_the_current_academic_year(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $class = CatechismClass::query()
            ->whereHas('academicYear', fn ($query) => $query->where('is_current', true))
            ->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/api/admin/children/options')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $class->id,
                'name' => $class->name,
                'code' => $class->code,
            ]);
    }

    public function test_child_class_assignment_rejects_a_class_from_another_parish(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $child = Child::firstOrFail();
        $otherParish = Parish::create(['name' => 'Giáo xứ khác', 'code' => 'GX-KHAC']);
        $class = CatechismClass::firstOrFail();
        $class->academicYear->update(['parish_id' => $otherParish->id]);

        $this->actingAs($admin)
            ->patchJson("/api/admin/children/{$child->id}", ['class_id' => $class->id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_CHILD_CLASS');
    }

    public function test_family_links_must_belong_to_the_same_parish(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $parish = Parish::firstOrFail();
        $otherParish = Parish::create(['name' => 'Giáo xứ khác', 'code' => 'GX-KHAC']);
        $child = Child::firstOrFail();
        $child->update(['parish_id' => $otherParish->id]);
        $parent = ParentProfile::firstOrFail();

        $this->actingAs($admin)->postJson('/api/admin/parents', [
            'name' => 'Phụ huynh sai giáo xứ',
            'email' => 'cross-parish-parent@example.test',
            'parish_id' => $parish->id,
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'child_ids' => [$child->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('child_ids.0');

        $this->actingAs($admin)->postJson('/api/admin/children', [
            'full_name' => 'Thiếu nhi sai giáo xứ',
            'code' => 'TN-CROSS',
            'parish_id' => $otherParish->id,
            'status' => 'studying',
            'parent_ids' => [$parent->id],
            'class_id' => null,
        ])->assertUnprocessable()->assertJsonValidationErrors('parent_ids.0');
    }

    public function test_deleting_and_restoring_family_profiles_preserves_relationship_history(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $parent = ParentProfile::firstOrFail();
        $child = $parent->children()->firstOrFail();

        $this->actingAs($admin)
            ->deleteJson("/api/admin/parents/{$parent->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'PASSWORD_CONFIRMATION_REQUIRED');

        $this->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->deleteJson("/api/admin/parents/{$parent->id}")
            ->assertOk();

        $this->assertTrue(User::withTrashed()->findOrFail($parent->user_id)->trashed());
        $this->assertDatabaseHas('parent_child', ['parent_profile_id' => $parent->id, 'child_id' => $child->id]);

        $this->postJson("/api/admin/parents/{$parent->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.is_archived', false);

        $this->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->deleteJson("/api/admin/children/{$child->id}")
            ->assertOk();

        $this->assertTrue(Child::withTrashed()->findOrFail($child->id)->trashed());
        $this->assertDatabaseHas('parent_child', ['parent_profile_id' => $parent->id, 'child_id' => $child->id]);

        $this->postJson("/api/admin/children/{$child->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.is_archived', false);

        $this->assertDatabaseHas('activity_logs', ['action' => 'parent.archived', 'subject_id' => $parent->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'parent.restored', 'subject_id' => $parent->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'child.archived', 'subject_id' => $child->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'child.restored', 'subject_id' => $child->id]);
    }
}
