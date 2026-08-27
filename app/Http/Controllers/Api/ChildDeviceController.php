<?php

namespace App\Http\Controllers\Api;

use App\Models\Child;
use App\Models\ChildDevice;
use App\Services\AuditLogger;
use App\Services\ChildDeviceCredentialService;
use Illuminate\Http\Request;

class ChildDeviceController extends ApiController
{
    public function show(Request $request, ChildDeviceCredentialService $credentials)
    {
        $child = $this->activeChild($request);

        return $this->success($this->payload(
            $child->device,
            $credentials->resolve($request->cookie(ChildDeviceCredentialService::COOKIE_NAME)),
        ));
    }

    public function store(
        Request $request,
        ChildDeviceCredentialService $credentials,
        AuditLogger $audit,
    ) {
        $child = $this->activeChild($request);
        [$device, $token] = $credentials->issue($child);
        $audit->record($request, 'child.device_activated', $device);

        return $this->success(
            $this->payload($device, $device),
            'Đã kích hoạt điện thoại này để điểm danh.',
        )->withCookie($credentials->cookie($token));
    }

    public function destroy(
        Request $request,
        ChildDeviceCredentialService $credentials,
        AuditLogger $audit,
    ) {
        $child = $this->activeChild($request);
        $device = $child->device;
        if ($device && ! $device->revoked_at) {
            $device->update(['revoked_at' => now()]);
            $audit->record($request, 'child.device_revoked', $device);
        }

        return $this->success(
            $this->payload($device?->fresh(), null),
            'Đã thu hồi quyền điểm danh của điện thoại.',
        )->withCookie($credentials->forgetCookie());
    }

    private function activeChild(Request $request): Child
    {
        $child = $request->user()?->child;
        abort_unless($child && $child->status === 'studying', 403);

        return $child;
    }

    private function payload(?ChildDevice $device, ?ChildDevice $current): array
    {
        $isActive = $device && ! $device->revoked_at && $device->expires_at->isFuture();

        return [
            'is_active' => (bool) $isActive,
            'is_current_device' => (bool) ($isActive && $current?->is($device)),
            'activated_at' => $device?->activated_at?->toIso8601String(),
            'expires_at' => $device?->expires_at?->toIso8601String(),
            'last_used_at' => $device?->last_used_at?->toIso8601String(),
        ];
    }
}
