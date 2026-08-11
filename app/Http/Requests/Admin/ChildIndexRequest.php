<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChildIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-children') === true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'parish_id' => ['nullable', 'integer', Rule::exists('parishes', 'id')],
            'status' => ['nullable', Rule::in(['studying', 'paused', 'graduated', 'archived'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ];
    }
}
