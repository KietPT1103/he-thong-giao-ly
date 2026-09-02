<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassCatalogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') === true;
    }

    public function rules(): array
    {
        return [
            'parish_id' => ['required', 'integer', Rule::exists('parishes', 'id')],
        ];
    }
}
