<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceAbsoluteSessionLifetime
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $loginAt = $request->session()->get('auth.login_at');

        if ($loginAt === null) {
            $request->session()->put('auth.login_at', now()->timestamp);
        } elseif (now()->timestamp - (int) $loginAt >= config('session.absolute_lifetime') * 60) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'success' => false,
                'message' => 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
                'code' => 'SESSION_ABSOLUTE_EXPIRED',
            ], 401);
        }

        return $next($request);
    }
}
