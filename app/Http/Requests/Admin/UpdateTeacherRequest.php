<?php

namespace App\Http\Requests\Admin;

use App\Models\TeacherProfile;
use App\Rules\VietnamesePhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') === true;
    }

    protected function prepareForValidation(): void
    {
        $fields = collect(['name', 'email', 'phone', 'code'])
            ->filter(fn (string $field) => $this->exists($field))
            ->mapWithKeys(fn (string $field) => [
                $field => is_string($this->input($field)) ? trim($this->input($field)) : $this->input($field),
            ])
            ->all();

        $this->merge($fields);
    }

    public function rules(): array
    {
        /** @var TeacherProfile $teacher */
        $teacher = $this->route('teacher');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($teacher->user_id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', new VietnamesePhoneNumber],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('teacher_profiles', 'code')->ignore($teacher->id),
            ],
            'parish_id' => ['sometimes', 'required', 'integer', Rule::exists('parishes', 'id')],
            'status' => ['sometimes', 'required', Rule::in(['active', 'blocked'])],
        ];
    }
}
