<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignParishTeachersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-system-settings') === true;
    }

    public function rules(): array
    {
        return [
            'teacher_ids' => ['required', 'array', 'min:1'],
            'teacher_ids.*' => ['required', 'integer', 'distinct', 'exists:teacher_profiles,id'],
        ];
    }
}
