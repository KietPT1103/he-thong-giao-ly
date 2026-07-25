<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_demo_teacher_can_login_read_session_and_logout(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'email' => 'teacher@giaoly.test',
            'password' => 'password',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.email', 'teacher@giaoly.test')
            ->assertJsonPath('data.roles.0', 'teacher');

        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.email', 'teacher@giaoly.test');
        $this->postJson('/api/auth/logout')->assertOk();
        $this->assertGuest('web');
    }

    public function test_blocked_account_cannot_login(): void
    {
        User::where('email', 'teacher@giaoly.test')->update(['status' => 'blocked']);

        $this->postJson('/api/auth/login', [
            'email' => 'teacher@giaoly.test',
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'teacher@giaoly.test',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }
}
