<?php

namespace App\Services;

use App\Models\Child;
use App\Models\ChildDevice;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class ChildDeviceCredentialService
{
    public const COOKIE_NAME = 'htgl_child_device';

    private const TOKEN_LENGTH = 80;

    private const COOKIE_MINUTES = 525600;

    /** @return array{ChildDevice, string} */
    public function issue(Child $child): array
    {
        $token = Str::random(self::TOKEN_LENGTH);
        $device = ChildDevice::query()->updateOrCreate(
            ['child_id' => $child->id],
            [
                'token_hash' => $this->hash($token),
                'activated_at' => now(),
                'expires_at' => now()->addMinutes(self::COOKIE_MINUTES),
                'last_used_at' => null,
                'revoked_at' => null,
            ],
        );

        return [$device, $token];
    }

    public function resolve(?string $token): ?ChildDevice
    {
        if (! is_string($token) || strlen($token) !== self::TOKEN_LENGTH) {
            return null;
        }

        return ChildDevice::query()
            ->with('child.user')
            ->where('token_hash', $this->hash($token))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function cookie(string $token): Cookie
    {
        return cookie()->make(
            self::COOKIE_NAME,
            $token,
            self::COOKIE_MINUTES,
            '/',
            config('session.domain'),
            (bool) config('session.secure'),
            true,
            false,
            'lax',
        );
    }

    public function forgetCookie(): Cookie
    {
        return cookie()->forget(self::COOKIE_NAME, '/', config('session.domain'));
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
