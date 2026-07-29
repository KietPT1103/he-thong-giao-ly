<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    public function test_session_is_rejected_after_the_absolute_lifetime(): void
    {
        $user = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $this->actingAs($user)
            ->withSession(['auth.login_at' => now()->subDays(7)->subSecond()->timestamp])
            ->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'SESSION_ABSOLUTE_EXPIRED');
        $this->assertGuest('web');
    }

    public function test_sensitive_actions_require_a_recent_password_confirmation(): void
    {
        $user = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $this->actingAs($user)
            ->postJson('/api/auth/change-password', [
                'current_password' => 'password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PASSWORD_CONFIRMATION_REQUIRED');
    }

    public function test_password_confirmation_expires_after_fifteen_minutes(): void
    {
        $user = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->subMinutes(15)->subSecond()->timestamp])
            ->deleteJson('/api/auth/sessions/others')
            ->assertForbidden()
            ->assertJsonPath('code', 'PASSWORD_CONFIRMATION_REQUIRED');
    }

    public function test_confirming_password_unlocks_sensitive_actions_for_fifteen_minutes(): void
    {
        $user = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $this->actingAs($user)
            ->postJson('/api/auth/confirm-password', ['password' => 'password'])
            ->assertOk();
        $this->postJson('/api/auth/change-password', [
            'current_password' => 'password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertOk();
        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }

    public function test_changing_password_revokes_other_database_sessions(): void
    {
        $user = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        DB::table('sessions')->insert([
            'id' => 'other-device-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->postJson('/api/auth/change-password', [
                'current_password' => 'password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertOk();
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device-session']);
    }
}
