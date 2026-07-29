<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRecentPasswordConfirmation
{
    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);

        if ($confirmedAt === 0 || now()->timestamp - $confirmedAt >= config('auth.password_timeout')) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng xác nhận lại mật khẩu để tiếp tục.',
                'code' => 'PASSWORD_CONFIRMATION_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
