<?php

namespace App\Http\Requests\Admin;

use App\Rules\VietnamesePhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateParishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-system-settings') === true;
    }

    protected function prepareForValidation(): void
    {
        $fields = collect(['name', 'code', 'phone', 'email'])
            ->filter(fn (string $field) => $this->exists($field))
            ->mapWithKeys(fn (string $field) => [
                $field => is_string($this->input($field)) ? trim($this->input($field)) : $this->input($field),
            ])
            ->all();

        $this->merge($fields);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('parishes', 'code')->ignore($this->route('parish')),
            ],
            'phone' => ['nullable', 'string', 'max:30', new VietnamesePhoneNumber],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
