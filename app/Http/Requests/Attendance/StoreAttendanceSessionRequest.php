<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'held_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
