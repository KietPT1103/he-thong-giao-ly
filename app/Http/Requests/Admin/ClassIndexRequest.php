<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') === true
            && $this->user()?->can('view-classes') === true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'parish_id' => ['nullable', 'integer', Rule::exists('parishes', 'id')],
            'academic_year_id' => ['nullable', 'integer', Rule::exists('academic_years', 'id')],
            'catechism_level_id' => ['nullable', 'integer', Rule::exists('catechism_levels', 'id')],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
