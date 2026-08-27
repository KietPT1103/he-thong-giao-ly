<?php

use App\Http\Middleware\AuthenticateChildDevice;
use App\Http\Middleware\EnforceAbsoluteSessionLifetime;
use App\Http\Middleware\EnforceProductionHttps;
use App\Http\Middleware\RequireRecentPasswordConfirmation;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
        $middleware->append(EnforceProductionHttps::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'child.device' => AuthenticateChildDevice::class,
            'session.absolute' => EnforceAbsoluteSessionLifetime::class,
            'password.recent' => RequireRecentPasswordConfirmation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*') || config('app.debug')) {
                return null;
            }

            if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi hệ thống.',
                'code' => 'SERVER_ERROR',
            ], 500);
        });
    })->create();
