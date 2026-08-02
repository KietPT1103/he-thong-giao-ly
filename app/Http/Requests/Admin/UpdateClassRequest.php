<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateClassRequest extends StoreClassRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') === true
            && $this->user()?->can('update-classes') === true;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['code'] = [
            'required',
            'string',
            'max:50',
            Rule::unique('catechism_classes', 'code')
                ->where('academic_year_id', $this->integer('academic_year_id'))
                ->ignore((int) $this->route('class')),
        ];

        return $rules;
    }
}
