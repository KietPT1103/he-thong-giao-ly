<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignClassTeachersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') === true
            && $this->user()?->can('assign-teachers') === true;
    }

    public function rules(): array
    {
        return [
            'teachers' => ['present', 'array', 'max:50'],
            'teachers.*.teacher_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('teacher_profiles', 'id'),
            ],
            'teachers.*.role' => ['required', Rule::in(['primary', 'assistant'])],
            'allow_teacher_conflicts' => ['sometimes', 'boolean'],
        ];
    }
}
