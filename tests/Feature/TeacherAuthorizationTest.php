<?php

namespace Tests\Feature;

use App\Models\{CatechismClass, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }

    public function test_unauthenticated_user_cannot_read_teacher_data(): void
    {
        $this->getJson('/api/teachers/me/classes')->assertUnauthorized();
    }
}
