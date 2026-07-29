<?php

namespace App\Services;

use Illuminate\Support\Str;

class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function code(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? now()->timestamp, 30);
        $binaryCounter = pack('N2', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    public function verify(string $secret, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        foreach ([-30, 0, 30] as $drift) {
            if (hash_equals($this->code($secret, now()->timestamp + $drift), $code)) {
                return true;
            }
        }

        return false;
    }

    public function recoveryCodes(): array
    {
        return collect(range(1, 8))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
    }

    public function uri(string $secret, string $email): string
    {
        $issuer = rawurlencode((string) config('app.name'));
        $label = rawurlencode(config('app.name').':'.$email);

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    private function base32Encode(string $value): string
    {
        $bits = '';
        foreach (str_split($value) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        return implode('', array_map(
            fn (string $chunk) => self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))],
            str_split($bits, 5),
        ));
    }

    private function base32Decode(string $value): string
    {
        $bits = '';
        foreach (str_split(strtoupper($value)) as $char) {
            $position = strpos(self::ALPHABET, $char);
            if ($position === false) {
                throw new \InvalidArgumentException('Invalid base32 secret.');
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }
}
