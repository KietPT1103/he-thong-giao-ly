<?php

namespace App\Http\Requests\Admin;

use App\Rules\VietnamesePhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreParishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-system-settings') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->trimmedFields());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('parishes', 'code')],
            'phone' => ['nullable', 'string', 'max:30', new VietnamesePhoneNumber],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    private function trimmedFields(): array
    {
        return collect(['name', 'code', 'phone', 'email'])
            ->filter(fn (string $field) => $this->exists($field))
            ->mapWithKeys(fn (string $field) => [
                $field => is_string($this->input($field)) ? trim($this->input($field)) : $this->input($field),
            ])
            ->all();
    }
}
