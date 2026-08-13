<?php

namespace App\Services;

use App\Models\AttendanceSession;

class AttendanceSessionQrCodeService
{
    private const PREFIX = 'htgl-attendance';

    public function token(AttendanceSession $session): string
    {
        if (! $session->qr_expires_at) {
            throw new \LogicException('The attendance session does not have a QR expiry time.');
        }

        $payload = implode('.', [self::PREFIX, $session->id, $session->qr_expires_at->timestamp]);

        return $payload.'.'.$this->signature($payload);
    }

    public function resolve(string $token): ?AttendanceSession
    {
        $parts = explode('.', $token);
        if (count($parts) !== 4 || $parts[0] !== self::PREFIX) {
            return null;
        }

        [, $sessionId, $expiresAt, $signature] = $parts;
        if (! ctype_digit($sessionId) || ! ctype_digit($expiresAt)) {
            return null;
        }

        $payload = implode('.', [self::PREFIX, $sessionId, $expiresAt]);
        if (! hash_equals($this->signature($payload), $signature)) {
            return null;
        }

        $session = AttendanceSession::with('catechismClass')->find((int) $sessionId);
        if (! $session?->qr_expires_at || $session->qr_expires_at->timestamp !== (int) $expiresAt) {
            return null;
        }

        return $session;
    }

    private function signature(string $payload): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true) ?: $key;
        }

        return rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $key, true)), '+/', '-_'), '=');
    }
}
