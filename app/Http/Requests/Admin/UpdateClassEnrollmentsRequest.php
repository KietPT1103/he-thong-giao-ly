<?php

namespace App\Http\Requests\Admin;

use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassEnrollmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') === true
            && $this->user()?->can('enroll-children') === true;
    }

    public function rules(): array
    {
        return [
            'enrollments' => ['present', 'array', 'max:100'],
            'enrollments.*.child_id' => ['required', 'integer', 'distinct', Rule::exists('children', 'id')->whereNull('deleted_at')],
            'enrollments.*.status' => ['required', Rule::in([
                Enrollment::STATUS_ACTIVE,
                Enrollment::STATUS_INACTIVE,
            ])],
        ];
    }
}
