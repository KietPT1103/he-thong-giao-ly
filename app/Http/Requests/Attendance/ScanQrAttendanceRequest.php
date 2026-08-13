<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class ScanQrAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('check-in-attendance-qr') === true;
    }

    public function rules(): array
    {
        return ['token' => ['required', 'string', 'max:512']];
    }
}
