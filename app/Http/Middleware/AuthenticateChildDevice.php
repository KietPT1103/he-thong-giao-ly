<?php

namespace App\Http\Middleware;

use App\Services\ChildDeviceCredentialService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateChildDevice
{
    public function __construct(private readonly ChildDeviceCredentialService $credentials) {}

    public function handle(Request $request, Closure $next): Response
    {
        $device = $this->credentials->resolve(
            $request->cookie(ChildDeviceCredentialService::COOKIE_NAME),
        );
        $child = $device?->child;
        $user = $child?->user;

        if (
            ! $device || ! $child || $child->trashed() || $child->status !== 'studying'
            || ! $user || $user->trashed() || $user->status !== 'active'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Điện thoại chưa được kích hoạt để điểm danh.',
                'code' => 'DEVICE_ACTIVATION_REQUIRED',
            ], 401);
        }

        $request->attributes->set('child_device', $device);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
