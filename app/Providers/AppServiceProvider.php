<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ChildDeviceCredentialService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Gate::define('access-admin', fn (User $user) => $user->hasRole('admin'));

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });
        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id.'|'.$request->ip()));
        RateLimiter::for('upload', fn (Request $request) => Limit::perMinute(5)->by($request->user()?->id.'|'.$request->ip()));
        RateLimiter::for('qr-scan', function (Request $request) {
            $token = $request->cookie(ChildDeviceCredentialService::COOKIE_NAME);
            $deviceKey = is_string($token) ? hash('sha256', $token) : 'guest';

            return Limit::perMinute(120)->by($deviceKey.'|'.$request->ip());
        });
    }
}
