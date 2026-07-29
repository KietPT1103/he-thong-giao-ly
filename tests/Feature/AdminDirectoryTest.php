<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_read_all_available_directories(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $expected = [
            'parishes' => 1,
            'teachers' => 1,
            'parents' => 1,
            'children' => 30,
            'classes' => 6,
            'announcements' => 0,
        ];

        foreach ($expected as $module => $total) {
            $this->actingAs($admin)
                ->getJson("/api/admin/{$module}")
                ->assertOk()
                ->assertJsonPath('meta.total', $total);
        }
    }

    public function test_directory_search_filters_real_records(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/api/admin/children?search=TN-001')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'TN-001');
    }

    public function test_teacher_cannot_read_admin_directories(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();

        $this->actingAs($teacher)
            ->getJson('/api/admin/children')
            ->assertForbidden();
    }
}
