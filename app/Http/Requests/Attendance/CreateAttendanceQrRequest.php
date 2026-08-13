<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class CreateAttendanceQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create-attendance-qr') === true;
    }

    public function rules(): array
    {
        return [
            'held_at' => ['required', 'date'],
            'qr_expires_at' => ['required', 'date', 'after:now', 'after:held_at'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
