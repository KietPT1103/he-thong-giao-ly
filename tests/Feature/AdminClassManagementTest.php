<?php

namespace Tests\Feature;

use App\Models\CatechismClass;
use App\Models\CatechismLevel;
use App\Models\Classroom;
use App\Models\Parish;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminClassManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_class_details_include_the_assigned_teacher_avatar_url(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = CatechismClass::firstOrFail();

        $teacher->update(['avatar_path' => 'avatars/teacher-one.jpg']);
        $class->teachers()->sync([
            $teacher->teacherProfile()->firstOrFail()->id => ['role' => 'primary'],
        ]);

        $this->actingAs($admin)
            ->getJson("/api/admin/classes/{$class->id}")
            ->assertOk()
            ->assertJsonPath('data.teachers.0.avatar_url', '/storage/avatars/teacher-one.jpg');
    }

    public function test_admin_can_create_update_and_list_class_catalogs(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $parish = Parish::firstOrFail();

        $year = $this->actingAs($admin)->postJson('/api/admin/academic-years', [
            'parish_id' => $parish->id,
            'name' => '  2026–2027  ',
            'starts_on' => '2026-08-01',
            'ends_on' => '2027-05-31',
            'is_current' => false,
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.name', '2026–2027');

        $level = $this->postJson('/api/admin/catechism-levels', [
            'parish_id' => $parish->id,
            'name' => '  Dự Trưởng  ',
            'code' => '  DT  ',
            'sort_order' => 5,
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.code', 'DT');

        $room = $this->postJson('/api/admin/classrooms', [
            'parish_id' => $parish->id,
            'name' => '  Hội trường B  ',
            'capacity' => 80,
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.name', 'Hội trường B');

        $this->patchJson('/api/admin/academic-years/'.$year->json('data.id'), [
            'name' => '2026–2027 cập nhật',
            'starts_on' => '2026-08-15',
            'ends_on' => '2027-05-31',
            'is_current' => false,
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.name', '2026–2027 cập nhật');

        $this->getJson("/api/admin/class-catalogs?parish_id={$parish->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $level->json('data.id'), 'name' => 'Dự Trưởng'])
            ->assertJsonFragment(['id' => $room->json('data.id'), 'capacity' => 80]);

        $this->assertDatabaseHas('activity_logs', ['action' => 'academic_year.updated']);
    }

    public function test_only_one_academic_year_can_be_current_per_parish(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $parish = Parish::firstOrFail();
        $oldCurrent = $parish->academicYears()->where('is_current', true)->firstOrFail();

        $created = $this->actingAs($admin)->postJson('/api/admin/academic-years', [
            'parish_id' => $parish->id,
            'name' => '2026–2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2027-05-31',
            'is_current' => true,
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.is_current', true);

        $this->assertDatabaseHas('academic_years', ['id' => $oldCurrent->id, 'is_current' => false]);
        $this->assertDatabaseHas('academic_years', ['id' => $created->json('data.id'), 'is_current' => true]);
    }

    public function test_in_use_catalog_entries_cannot_be_deleted_but_can_be_deactivated(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $class = CatechismClass::firstOrFail();
        $level = $class->level;
        $parishId = $class->academicYear->parish_id;

        $blocked = $this->actingAs($admin)
            ->deleteJson("/api/admin/catechism-levels/{$level->id}")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'CLASS_CATALOG_IN_USE');
        $this->assertGreaterThan(0, $blocked->json('data.classes_count'));

        $this->patchJson("/api/admin/catechism-levels/{$level->id}", [
            'name' => $level->name,
            'code' => $level->code,
            'sort_order' => $level->sort_order,
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);

        $newClassOptions = $this->getJson("/api/admin/classes/options?parish_id={$parishId}")
            ->assertOk()->json('data.levels');
        $editingOptions = $this->getJson("/api/admin/classes/options?parish_id={$parishId}&class_id={$class->id}")
            ->assertOk()->json('data.levels');

        $this->assertNotContains($level->id, collect($newClassOptions)->pluck('id'));
        $this->assertContains($level->id, collect($editingOptions)->pluck('id'));

        $this->postJson('/api/admin/classes', [
            'name' => 'Lớp dùng danh mục đã ngừng',
            'code' => 'INACTIVE-CATALOG',
            'academic_year_id' => $class->academic_year_id,
            'catechism_level_id' => $level->id,
            'classroom_id' => null,
            'status' => 'active',
        ])->assertUnprocessable()->assertJsonValidationErrors('catechism_level_id');
    }

    public function test_unused_catalog_entries_can_be_deleted(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $parish = Parish::firstOrFail();
        $room = Classroom::create([
            'parish_id' => $parish->id,
            'name' => 'Phòng tạm',
            'capacity' => 10,
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/admin/classrooms/{$room->id}")
            ->assertOk();

        $this->assertDatabaseMissing('classrooms', ['id' => $room->id]);
    }

    public function test_archived_classes_still_block_catalog_deletion(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $class = CatechismClass::firstOrFail();
        $room = Classroom::create([
            'parish_id' => $class->academicYear->parish_id,
            'name' => 'Phòng chỉ có lớp lưu trữ',
            'capacity' => 20,
        ]);
        $class->update(['classroom_id' => $room->id]);
        $class->delete();

        $this->actingAs($admin)
            ->deleteJson("/api/admin/classrooms/{$room->id}")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'CLASS_CATALOG_IN_USE');
    }

    public function test_catalog_management_requires_admin_and_specific_permissions(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $parish = Parish::firstOrFail();

        $this->getJson("/api/admin/class-catalogs?parish_id={$parish->id}")->assertUnauthorized();
        $this->actingAs($teacher)
            ->getJson("/api/admin/class-catalogs?parish_id={$parish->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('permissions', ['name' => 'view-classrooms', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'delete-classrooms', 'guard_name' => 'web']);
    }

    public function test_catalog_validation_rejects_invalid_dates_duplicate_codes_and_capacity(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $parish = Parish::firstOrFail();
        $existingLevel = CatechismLevel::where('parish_id', $parish->id)->firstOrFail();

        $this->actingAs($admin)->postJson('/api/admin/academic-years', [
            'parish_id' => $parish->id,
            'name' => 'Niên khóa lỗi',
            'starts_on' => '2027-06-01',
            'ends_on' => '2026-08-01',
            'is_current' => false,
            'is_active' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('ends_on');

        $this->postJson('/api/admin/academic-years', [
            'parish_id' => $parish->id,
            'name' => 'Niên khóa hiện tại không hoạt động',
            'starts_on' => '2026-08-01',
            'ends_on' => '2027-05-31',
            'is_current' => true,
            'is_active' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_current');

        $this->postJson('/api/admin/catechism-levels', [
            'parish_id' => $parish->id,
            'name' => 'Khối trùng mã',
            'code' => $existingLevel->code,
            'sort_order' => 10,
            'is_active' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->postJson('/api/admin/classrooms', [
            'parish_id' => $parish->id,
            'name' => 'Phòng lỗi',
            'capacity' => 0,
            'is_active' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('capacity');
    }
}
