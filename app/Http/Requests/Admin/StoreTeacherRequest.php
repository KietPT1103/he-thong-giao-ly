<?php

namespace App\Http\Requests\Admin;

use App\Rules\VietnamesePhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->trimmedFields());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30', new VietnamesePhoneNumber],
            'code' => ['required', 'string', 'max:50', Rule::unique('teacher_profiles', 'code')],
            'parish_id' => ['required', 'integer', Rule::exists('parishes', 'id')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    private function trimmedFields(): array
    {
        return collect(['name', 'email', 'phone', 'code'])
            ->filter(fn (string $field) => $this->exists($field))
            ->mapWithKeys(fn (string $field) => [
                $field => is_string($this->input($field)) ? trim($this->input($field)) : $this->input($field),
            ])
            ->all();
    }
}
