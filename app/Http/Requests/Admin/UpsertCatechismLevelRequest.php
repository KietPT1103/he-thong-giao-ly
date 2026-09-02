<?php

namespace App\Http\Requests\Admin;

use App\Models\CatechismLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertCatechismLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('catechism_level') ? 'update-levels' : 'create-levels';

        return $this->user()?->can('access-admin') === true
            && $this->user()?->can($permission) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'code' => is_string($this->input('code')) ? mb_strtoupper(trim($this->input('code'))) : $this->input('code'),
        ]);
    }

    public function rules(): array
    {
        $level = $this->route('catechism_level');
        $parishId = $level instanceof CatechismLevel ? $level->parish_id : $this->integer('parish_id');

        return [
            'parish_id' => [$level ? 'prohibited' : 'required', 'integer', Rule::exists('parishes', 'id')],
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required', 'string', 'max:30', 'regex:/^[\pL\pN_-]+$/u',
                Rule::unique('catechism_levels', 'code')
                    ->where('parish_id', $parishId)
                    ->ignore($level instanceof CatechismLevel ? $level->id : null),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Mã khối đã tồn tại trong giáo xứ này.',
            'code.regex' => 'Mã khối chỉ gồm chữ, số, dấu gạch ngang hoặc gạch dưới.',
        ];
    }
}
