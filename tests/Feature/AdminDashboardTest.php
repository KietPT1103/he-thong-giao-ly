<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_view_real_dashboard_metrics(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.parish_count', 1)
            ->assertJsonPath('data.summary.teacher_count', 1)
            ->assertJsonPath('data.summary.child_count', 30)
            ->assertJsonCount(1, 'data.parishes');
    }

    public function test_teacher_cannot_view_admin_dashboard(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();

        $this->actingAs($teacher)
            ->getJson('/api/admin/dashboard')
            ->assertForbidden();
    }
}
