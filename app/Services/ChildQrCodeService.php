<?php

namespace App\Services;

use App\Models\Child;

class ChildQrCodeService
{
    private const PREFIX = 'GQR1';

    public function token(Child $child): string
    {
        $payload = implode('.', [self::PREFIX, $child->id, $child->qr_version]);

        return $payload.'.'.$this->signature($payload);
    }

    public function resolve(string $token): ?Child
    {
        if (strlen($token) > 512 || ! preg_match('/^GQR1\.(\d+)\.(\d+)\.([A-Za-z0-9_-]{43})$/', $token, $matches)) {
            return null;
        }

        $payload = implode('.', [self::PREFIX, $matches[1], $matches[2]]);
        if (! hash_equals($this->signature($payload), $matches[3])) {
            return null;
        }

        $child = Child::withTrashed()->find((int) $matches[1]);

        return $child && $child->qr_version === (int) $matches[2] ? $child : null;
    }

    private function signature(string $payload): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, (string) config('app.key'), true)), '+/', '-_'), '=');
    }
}
