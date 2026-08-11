<?php

namespace App\Http\Requests\Admin;

use App\Rules\VietnamesePhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreParentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create-parents') === true
            && (! $this->filled('child_ids') || $this->user()?->can('link-parent-child') === true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->trimmed(['name', 'email', 'phone']));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30', new VietnamesePhoneNumber],
            'parish_id' => ['required', 'integer', Rule::exists('parishes', 'id')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'child_ids' => ['present', 'array', 'max:100'],
            'child_ids.*' => [
                'integer', 'distinct',
                Rule::exists('children', 'id')
                    ->where('parish_id', $this->integer('parish_id'))
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    private function trimmed(array $fields): array
    {
        return collect($fields)->filter(fn ($field) => $this->exists($field))->mapWithKeys(fn ($field) => [
            $field => is_string($this->input($field)) ? trim($this->input($field)) : $this->input($field),
        ])->all();
    }
}
