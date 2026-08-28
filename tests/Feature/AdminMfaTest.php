<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMfaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_with_legacy_mfa_data_logs_in_with_password_only(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $admin->forceFill([
            'mfa_secret' => 'LEGACY-MFA-SECRET',
            'mfa_recovery_codes' => [],
            'mfa_confirmed_at' => now(),
        ])->save();

        $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('data.email', $admin->email);

        $this->assertAuthenticatedAs($admin);
    }

    public function test_mfa_endpoints_are_not_exposed(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $this->postJson('/api/auth/mfa-challenge', ['code' => '123456'])->assertNotFound();
        $this->actingAs($admin)->getJson('/api/account/mfa')->assertNotFound();
        $this->postJson('/api/account/mfa/setup')->assertNotFound();
    }
}
