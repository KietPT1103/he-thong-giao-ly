<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class VietnamesePhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('Số điện thoại không hợp lệ.');

            return;
        }

        $normalized = preg_replace('/[\s.()\-]/u', '', $value);

        if (! is_string($normalized) || ! preg_match('/^(?:\+84|0)(?:[35789]\d{8}|2\d{9})$/', $normalized)) {
            $fail('Số điện thoại không hợp lệ.');
        }
    }
}
