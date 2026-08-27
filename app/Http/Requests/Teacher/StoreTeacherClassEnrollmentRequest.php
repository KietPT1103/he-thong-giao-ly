<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherClassEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('teacher') === true
            && $this->user()?->can('enroll-children') === true;
    }

    public function rules(): array
    {
        return [
            'child_id' => [
                'required',
                'integer',
                Rule::exists('children', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
