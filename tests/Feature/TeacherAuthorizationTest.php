<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\CatechismClass;
use App\Models\CatechismLevel;
use App\Models\Child;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Parish;
use App\Models\TeacherProfile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_teacher_only_sees_assigned_classes(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $assignedIds = $teacher->teacherProfile->classes()->pluck('catechism_classes.id');

        $response = $this->actingAs($teacher)->getJson('/api/teachers/me/classes')->assertOk();
        $returnedIds = collect($response->json('data'))->pluck('id');

        $this->assertNotEmpty($returnedIds);
        $this->assertTrue($returnedIds->every(fn ($id) => $assignedIds->contains($id)));
    }

    public function test_teacher_cannot_open_another_teachers_class(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $otherClass = CatechismClass::whereKeyNot($teacher->teacherProfile->classes()->firstOrFail()->id)->firstOrFail();
        $otherClass->teachers()->detach($teacher->teacherProfile->id);

        $this->actingAs($teacher)->getJson("/api/classes/{$otherClass->id}")->assertForbidden();
        $this->getJson("/api/teacher/classes/{$otherClass->id}/workspace")->assertForbidden();
    }

    public function test_teacher_class_detail_includes_responsible_teachers_and_filters_children(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = $teacher->teacherProfile->classes()->firstOrFail();
        $target = $class->children()->firstOrFail();

        $this->actingAs($teacher)
            ->getJson("/api/classes/{$class->id}")
            ->assertOk()
            ->assertJsonPath('data.teachers.0.id', $teacher->teacherProfile->id)
            ->assertJsonPath('data.teachers.0.role', 'primary')
            ->assertJsonPath('data.teachers.0.email', $teacher->email)
            ->assertJsonPath('data.schedules.0.weekday', 7);

        $this->getJson("/api/classes/{$class->id}/children?search={$target->code}&status=studying")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $target->id)
            ->assertJsonPath('data.0.date_of_birth', $target->date_of_birth?->toDateString());
    }

    public function test_teacher_class_children_include_the_linked_account_avatar(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $childUser = User::where('email', 'child@giaoly.test')->firstOrFail();
        $childUser->update(['avatar_path' => 'avatars/child-one.png']);
        $child = $childUser->child;
        $class = $child->activeEnrollment->catechismClass;

        $this->actingAs($teacher)
            ->getJson("/api/classes/{$class->id}/children?search={$child->code}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $child->id)
            ->assertJsonPath('data.0.avatar_url', '/storage/avatars/child-one.png');
    }

    public function test_teacher_class_workspace_returns_detail_and_first_children_page_with_bounded_queries(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = $teacher->teacherProfile->classes()->firstOrFail();
        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $response = $this->actingAs($teacher)
            ->getJson("/api/teacher/classes/{$class->id}/workspace")
            ->assertOk()
            ->assertJsonPath('data.class.id', $class->id)
            ->assertJsonPath('data.children_meta.total', 5)
            ->assertJsonCount(5, 'data.children')
            ->assertJsonStructure(['data' => [
                'class' => ['id', 'name', 'teachers', 'schedules'],
                'children' => ['*' => ['id', 'code', 'full_name', 'avatar_url']],
                'children_meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]])
            ->assertJsonMissingPath('data.children.0.parents');

        $this->assertLessThanOrEqual(18, $queryCount, "Class workspace đã chạy {$queryCount} query.");
        $this->assertSame(5, count($response->json('data.children')));
    }

    public function test_teacher_children_directory_uses_one_paginated_endpoint_with_a_bounded_query_count(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $response = $this->actingAs($teacher)
            ->getJson('/api/teachers/me/children?per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.summary.total_children', 30)
            ->assertJsonPath('meta.summary.studying_children', 30)
            ->assertJsonPath('meta.summary.class_count', 6)
            ->assertJsonCount(6, 'meta.filters.classes')
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure(['data' => ['*' => [
                'id', 'code', 'full_name', 'avatar_url', 'class' => ['id', 'name', 'code'],
            ]], 'meta' => ['summary' => ['next_schedule']]]);

        $this->assertLessThanOrEqual(13, $queryCount, sprintf(
            'Danh sách thiếu nhi đã chạy %d query.',
            $queryCount,
        ));
        $this->assertSame(10, count($response->json('data')));

        $class = $teacher->teacherProfile->classes()->firstOrFail();
        $this->getJson("/api/teachers/me/children?per_page=10&class_id={$class->id}&search=TN-001")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'TN-001')
            ->assertJsonPath('data.0.class.id', $class->id);
    }

    public function test_unauthenticated_user_cannot_read_teacher_data(): void
    {
        $this->getJson('/api/teachers/me/classes')->assertUnauthorized();
    }

    public function test_teacher_can_create_a_class_in_their_parish_and_becomes_primary(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $year = AcademicYear::where('parish_id', $teacher->teacherProfile->parish_id)
            ->where('is_current', true)
            ->firstOrFail();
        $level = CatechismLevel::where('parish_id', $teacher->teacherProfile->parish_id)->firstOrFail();
        $room = Classroom::where('parish_id', $teacher->teacherProfile->parish_id)->firstOrFail();

        $this->actingAs($teacher)
            ->getJson('/api/teacher/classes/options')
            ->assertOk()
            ->assertJsonCount(1, 'data.parishes')
            ->assertJsonPath('data.parishes.0.id', $teacher->teacherProfile->parish_id);

        $created = $this->postJson('/api/teacher/classes', [
            'name' => '  Lớp giáo viên tự tạo  ',
            'code' => '  GLV-NEW  ',
            'academic_year_id' => $year->id,
            'catechism_level_id' => $level->id,
            'classroom_id' => $room->id,
            'status' => 'active',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Lớp giáo viên tự tạo')
            ->assertJsonPath('data.code', 'GLV-NEW');

        $classId = $created->json('data.id');
        $this->assertDatabaseHas('teacher_class_assignments', [
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'catechism_class_id' => $classId,
            'role' => 'primary',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'class.created',
            'subject_id' => $classId,
        ]);
    }

    public function test_primary_teacher_can_update_and_archive_their_class(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = $teacher->teacherProfile->classes()->firstOrFail();

        $this->actingAs($teacher)
            ->patchJson("/api/teacher/classes/{$class->id}", [
                'name' => 'Lớp đã chỉnh sửa',
                'code' => $class->code,
                'academic_year_id' => $class->academic_year_id,
                'catechism_level_id' => $class->catechism_level_id,
                'classroom_id' => $class->classroom_id,
                'status' => 'inactive',
            ])->assertOk()
            ->assertJsonPath('data.name', 'Lớp đã chỉnh sửa')
            ->assertJsonPath('data.status', 'inactive');

        $this->deleteJson("/api/teacher/classes/{$class->id}")->assertOk();

        $this->assertSoftDeleted('catechism_classes', ['id' => $class->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'class.updated', 'subject_id' => $class->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'class.archived', 'subject_id' => $class->id]);
    }

    public function test_assistant_teacher_cannot_update_or_archive_a_class(): void
    {
        $primary = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = $primary->teacherProfile->classes()->firstOrFail();
        $assistantUser = User::factory()->create(['status' => 'active']);
        $assistantUser->assignRole('teacher');
        $assistant = TeacherProfile::create([
            'user_id' => $assistantUser->id,
            'parish_id' => $primary->teacherProfile->parish_id,
            'code' => 'GLV-ASSISTANT-ONLY',
        ]);
        $class->teachers()->attach($assistant->id, ['role' => 'assistant']);

        $payload = [
            'name' => 'Không được sửa',
            'code' => $class->code,
            'academic_year_id' => $class->academic_year_id,
            'catechism_level_id' => $class->catechism_level_id,
            'classroom_id' => $class->classroom_id,
            'status' => $class->status,
        ];

        $this->actingAs($assistantUser)
            ->patchJson("/api/teacher/classes/{$class->id}", $payload)
            ->assertForbidden();
        $this->deleteJson("/api/teacher/classes/{$class->id}")->assertForbidden();
    }

    public function test_primary_teacher_can_find_and_enroll_an_existing_child_without_editing_the_profile(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = $teacher->teacherProfile->classes()->firstOrFail();
        $child = Child::create([
            'parish_id' => $teacher->teacherProfile->parish_id,
            'code' => 'TN-AVAILABLE',
            'full_name' => 'Thiếu nhi chưa xếp lớp',
            'saint_name' => 'Maria',
            'date_of_birth' => '2015-05-10',
            'status' => 'studying',
        ]);
        $otherParish = Parish::create(['name' => 'Giáo xứ ngoài phạm vi', 'code' => 'GX-OUTSIDE']);
        Child::create([
            'parish_id' => $otherParish->id,
            'code' => 'TN-OUTSIDE',
            'full_name' => 'Thiếu nhi giáo xứ khác',
            'status' => 'studying',
        ]);

        $this->actingAs($teacher)
            ->getJson("/api/teacher/classes/{$class->id}/enrollment-options?search=TN-AVAILABLE")
            ->assertOk()
            ->assertJsonFragment(['id' => $child->id, 'code' => 'TN-AVAILABLE'])
            ->assertJsonMissing(['code' => 'TN-OUTSIDE']);

        $this->postJson("/api/teacher/classes/{$class->id}/enrollments", [
            'child_id' => $child->id,
        ])->assertCreated()
            ->assertJsonPath('data.child.id', $child->id)
            ->assertJsonPath('data.status', Enrollment::STATUS_ACTIVE);

        $this->assertDatabaseHas('enrollments', [
            'child_id' => $child->id,
            'catechism_class_id' => $class->id,
            'status' => Enrollment::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'class.child_enrolled',
            'subject_id' => $class->id,
        ]);
        $this->assertSame('studying', $child->fresh()->status);
    }

    public function test_teacher_enrollment_scope_rejects_other_parishes_and_assistant_teachers(): void
    {
        $primary = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = $primary->teacherProfile->classes()->firstOrFail();
        $otherParish = Parish::create(['name' => 'Giáo xứ bị chặn', 'code' => 'GX-BLOCKED']);
        $outsideChild = Child::create([
            'parish_id' => $otherParish->id,
            'code' => 'TN-BLOCKED',
            'full_name' => 'Thiếu nhi ngoài giáo xứ',
            'status' => 'studying',
        ]);

        $this->actingAs($primary)
            ->postJson("/api/teacher/classes/{$class->id}/enrollments", ['child_id' => $outsideChild->id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_CLASS_CHILD');

        $assistantUser = User::factory()->create(['status' => 'active']);
        $assistantUser->assignRole('teacher');
        $assistant = TeacherProfile::create([
            'user_id' => $assistantUser->id,
            'parish_id' => $primary->teacherProfile->parish_id,
            'code' => 'GLV-ENROLL-ASSISTANT',
        ]);
        $class->teachers()->attach($assistant->id, ['role' => 'assistant']);

        $this->actingAs($assistantUser)
            ->getJson("/api/teacher/classes/{$class->id}/enrollment-options")
            ->assertForbidden();
    }

    public function test_primary_teacher_cannot_enroll_a_child_into_an_inactive_class(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = $teacher->teacherProfile->classes()->firstOrFail();
        $class->update(['status' => 'inactive']);
        $child = Child::create([
            'parish_id' => $teacher->teacherProfile->parish_id,
            'code' => 'TN-INACTIVE-CLASS',
            'full_name' => 'Thiếu nhi chờ xếp lớp',
            'status' => 'studying',
        ]);

        $this->actingAs($teacher)
            ->postJson("/api/teacher/classes/{$class->id}/enrollments", ['child_id' => $child->id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'SOURCE_CLASS_INACTIVE');

        $this->assertDatabaseMissing('enrollments', [
            'child_id' => $child->id,
            'catechism_class_id' => $class->id,
        ]);
    }

    public function test_primary_teacher_can_remove_stop_and_transfer_children_between_managed_classes(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $classes = $teacher->teacherProfile->classes()->take(2)->get();
        $source = $classes->first();
        $target = $classes->last();
        $child = $source->children()->firstOrFail();

        $this->actingAs($teacher)
            ->patchJson("/api/teacher/classes/{$source->id}/enrollments/{$child->id}", ['action' => 'remove'])
            ->assertOk()
            ->assertJsonPath('data.status', Enrollment::STATUS_INACTIVE)
            ->assertJsonPath('data.ended_reason', Enrollment::ENDED_REMOVED);

        $this->postJson("/api/teacher/classes/{$source->id}/enrollments", ['child_id' => $child->id])
            ->assertCreated();
        $this->patchJson("/api/teacher/classes/{$source->id}/enrollments/{$child->id}", ['action' => 'stop'])
            ->assertOk()
            ->assertJsonPath('data.ended_reason', Enrollment::ENDED_STOPPED);
        $this->assertSame('studying', $child->fresh()->status);

        $this->postJson("/api/teacher/classes/{$source->id}/enrollments", ['child_id' => $child->id])
            ->assertCreated();
        $this->patchJson("/api/teacher/classes/{$source->id}/enrollments/{$child->id}", [
            'action' => 'transfer',
            'target_class_id' => $target->id,
        ])->assertOk()
            ->assertJsonPath('data.source.ended_reason', Enrollment::ENDED_TRANSFERRED)
            ->assertJsonPath('data.target.catechism_class_id', $target->id)
            ->assertJsonPath('data.target.status', Enrollment::STATUS_ACTIVE);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'class.child_transferred',
            'subject_id' => $source->id,
        ]);
        $sourceChildren = $this->actingAs($teacher)
            ->getJson("/api/classes/{$source->id}/children")
            ->assertOk();
        $targetChildren = $this->getJson("/api/classes/{$target->id}/children")
            ->assertOk();
        $this->assertNotContains($child->id, collect($sourceChildren->json('data'))->pluck('id'));
        $this->assertContains($child->id, collect($targetChildren->json('data'))->pluck('id'));
    }

    public function test_primary_teacher_cannot_transfer_a_child_to_an_unmanaged_class(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $source = $teacher->teacherProfile->classes()->firstOrFail();
        $child = $source->children()->firstOrFail();
        $target = CatechismClass::create([
            'academic_year_id' => $source->academic_year_id,
            'catechism_level_id' => $source->catechism_level_id,
            'classroom_id' => null,
            'name' => 'Lớp không được phân công',
            'code' => 'UNMANAGED-TRANSFER',
            'status' => 'active',
        ]);

        $this->actingAs($teacher)
            ->patchJson("/api/teacher/classes/{$source->id}/enrollments/{$child->id}", [
                'action' => 'transfer',
                'target_class_id' => $target->id,
            ])->assertForbidden();

        $this->assertDatabaseHas('enrollments', [
            'child_id' => $child->id,
            'catechism_class_id' => $source->id,
            'status' => Enrollment::STATUS_ACTIVE,
        ]);
    }

    public function test_primary_teacher_cannot_transfer_a_child_to_an_inactive_class(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $classes = $teacher->teacherProfile->classes()->take(2)->get();
        $source = $classes->first();
        $target = $classes->last();
        $target->update(['status' => 'inactive']);
        $child = $source->children()->firstOrFail();

        $this->actingAs($teacher)
            ->patchJson("/api/teacher/classes/{$source->id}/enrollments/{$child->id}", [
                'action' => 'transfer',
                'target_class_id' => $target->id,
            ])->assertUnprocessable()
            ->assertJsonPath('code', 'TARGET_CLASS_INACTIVE');

        $this->assertDatabaseHas('enrollments', [
            'child_id' => $child->id,
            'catechism_class_id' => $source->id,
            'status' => Enrollment::STATUS_ACTIVE,
        ]);
    }

    public function test_teacher_cannot_create_a_class_with_resources_from_another_parish(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $otherParish = Parish::create(['name' => 'Giáo xứ khác', 'code' => 'GX-KHAC-CRUD']);
        $otherYear = AcademicYear::create([
            'parish_id' => $otherParish->id,
            'name' => '2026-2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2027-05-31',
            'is_current' => true,
        ]);
        $otherLevel = CatechismLevel::create([
            'parish_id' => $otherParish->id,
            'name' => 'Khai Tâm',
            'code' => 'KT-OTHER',
            'sort_order' => 1,
        ]);

        $this->actingAs($teacher)
            ->postJson('/api/teacher/classes', [
                'name' => 'Lớp sai giáo xứ',
                'code' => 'WRONG-PARISH-CRUD',
                'academic_year_id' => $otherYear->id,
                'catechism_level_id' => $otherLevel->id,
                'classroom_id' => null,
                'status' => 'active',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('academic_year_id');
    }

    public function test_teacher_class_management_permission_migration_updates_teacher_role(): void
    {
        $permissions = ['create-classes', 'update-classes', 'delete-classes'];
        $role = Role::findByName('teacher');
        $role->revokePermissionTo($permissions);

        $migration = require database_path(
            'migrations/2026_08_26_000300_grant_teacher_class_management_permissions.php',
        );
        $migration->up();

        $this->assertTrue($role->fresh()->hasAllPermissions($permissions));
    }

    public function test_teacher_enrollment_permission_migration_updates_teacher_role(): void
    {
        $role = Role::findByName('teacher');
        $role->revokePermissionTo('enroll-children');

        $migration = require database_path(
            'migrations/2026_08_26_000400_grant_teacher_enrollment_permission.php',
        );
        $migration->up();

        $this->assertTrue($role->fresh()->hasPermissionTo('enroll-children'));
    }
}
