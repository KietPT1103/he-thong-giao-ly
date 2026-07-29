<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TotpService;
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

    public function test_only_admin_can_start_mfa_setup_after_password_confirmation(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $this->actingAs($teacher)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->postJson('/api/account/mfa/setup')
            ->assertForbidden();

        $this->flushSession();
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $this->actingAs($admin)->postJson('/api/account/mfa/setup')
            ->assertForbidden()
            ->assertJsonPath('code', 'PASSWORD_CONFIRMATION_REQUIRED');
    }

    public function test_admin_can_enable_mfa_and_recovery_codes_are_only_returned_once(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $session = ['auth.password_confirmed_at' => now()->timestamp];

        $setup = $this->actingAs($admin)->withSession($session)
            ->postJson('/api/account/mfa/setup')
            ->assertOk()
            ->assertJsonStructure(['data' => ['secret', 'otpauth_uri']]);

        $secret = $setup->json('data.secret');
        $code = app(TotpService::class)->code($secret);

        $this->withSession($session)
            ->postJson('/api/account/mfa/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonCount(8, 'data.recovery_codes');

        $admin->refresh();
        $this->assertNotNull($admin->mfa_confirmed_at);
        $this->assertNotNull($admin->mfa_secret);
        $this->assertCount(8, $admin->mfa_recovery_codes);
        $this->assertNotEquals($secret, $admin->getRawOriginal('mfa_secret'));

        $this->getJson('/api/account/mfa')->assertOk()->assertJsonMissingPath('data.recovery_codes');
    }

    public function test_admin_login_requires_mfa_after_password_verification(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $secret = app(TotpService::class)->generateSecret();
        $admin->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => [],
            'mfa_confirmed_at' => now(),
        ])->save();

        $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertAccepted()->assertJsonPath('code', 'MFA_REQUIRED');

        $this->assertGuest('web');

        $this->postJson('/api/auth/mfa-challenge', [
            'code' => app(TotpService::class)->code($secret),
        ])->assertOk();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_recovery_code_is_single_use_and_admin_can_regenerate_codes(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $secret = app(TotpService::class)->generateSecret();
        $plainRecovery = 'alpha-bravo';
        $admin->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => [\Hash::make($plainRecovery)],
            'mfa_confirmed_at' => now(),
        ])->save();

        $this->postJson('/api/auth/login', ['email' => $admin->email, 'password' => 'password'])->assertAccepted();
        $this->postJson('/api/auth/mfa-challenge', ['code' => $plainRecovery])->assertOk();
        $this->assertSame([], $admin->fresh()->mfa_recovery_codes);

        $this->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->postJson('/api/account/mfa/recovery-codes')
            ->assertOk()
            ->assertJsonCount(8, 'data.recovery_codes');
    }

    public function test_mfa_login_challenge_expires_after_five_minutes(): void
    {
        $admin = User::where('email', 'admin@giaoly.test')->firstOrFail();
        $secret = app(TotpService::class)->generateSecret();
        $admin->forceFill(['mfa_secret' => $secret, 'mfa_recovery_codes' => [], 'mfa_confirmed_at' => now()])->save();

        $this->postJson('/api/auth/login', ['email' => $admin->email, 'password' => 'password'])->assertAccepted();
        $this->withSession(['auth.mfa_user_id' => $admin->id, 'auth.mfa_started_at' => now()->subMinutes(5)->subSecond()->timestamp])
            ->postJson('/api/auth/mfa-challenge', ['code' => app(TotpService::class)->code($secret)])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MFA_CHALLENGE_EXPIRED');
    }
}
