<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_responses_include_security_headers(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_local_csp_allows_vite_stylesheets(): void
    {
        config(['app.env' => 'local']);

        $csp = $this->get('/login')
            ->assertOk()
            ->headers->get('Content-Security-Policy');

        $this->assertStringContainsString(
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com http://localhost:5173 http://127.0.0.1:5173",
            (string) $csp,
        );
    }

    public function test_hsts_is_only_sent_for_secure_production_requests(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

        config(['app.env' => 'production']);
        $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_successful_and_failed_logins_are_audited_without_passwords(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'teacher@giaoly.test',
            'password' => 'wrong-password',
        ])->assertUnprocessable();

        $this->postJson('/api/auth/login', [
            'email' => 'teacher@giaoly.test',
            'password' => 'password',
        ])->assertOk();

        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.login_failed']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.login_succeeded']);
        $serialized = ActivityLog::query()->get()->toJson();
        $this->assertStringNotContainsString('wrong-password', $serialized);
        $this->assertStringNotContainsString('"password"', $serialized);
    }

    public function test_profile_changes_and_avatar_uploads_are_audited(): void
    {
        $user = User::where('email', 'teacher@giaoly.test')->firstOrFail();

        $this->actingAs($user)->patchJson('/api/account', [
            'name' => 'Tên mới',
            'email' => $user->email,
            'phone' => null,
        ])->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'account.profile_updated',
        ]);
    }

    public function test_unknown_api_errors_use_a_safe_json_shape(): void
    {
        $this->getJson('/api/not-a-real-endpoint')
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Không tìm thấy tài nguyên.',
                'code' => 'NOT_FOUND',
            ]);
    }

    public function test_production_api_exceptions_do_not_expose_internal_details(): void
    {
        config(['app.debug' => false]);
        $response = $this->getJson('/api/security-test-error')
            ->assertServerError()
            ->assertJson([
                'success' => false,
                'message' => 'Đã xảy ra lỗi hệ thống.',
                'code' => 'SERVER_ERROR',
            ]);

        $this->assertStringNotContainsString('database-secret-detail', $response->getContent());
    }
}
