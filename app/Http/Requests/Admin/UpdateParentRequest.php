<?php

namespace App\Http\Requests\Admin;

use App\Models\ParentProfile;
use App\Rules\VietnamesePhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateParentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update-parents') === true
            && (! $this->exists('child_ids') || $this->user()?->can('link-parent-child') === true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect(['name', 'email', 'phone'])
            ->filter(fn ($field) => $this->exists($field))
            ->mapWithKeys(fn ($field) => [
                $field => is_string($this->input($field)) ? trim($this->input($field)) : $this->input($field),
            ])->all());
    }

    public function rules(): array
    {
        $parent = ParentProfile::findOrFail((int) $this->route('parent'));
        $parishId = $this->integer('parish_id') ?: $parent->parish_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($parent->user_id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', new VietnamesePhoneNumber],
            'parish_id' => ['sometimes', 'required', 'integer', Rule::exists('parishes', 'id')],
            'child_ids' => ['sometimes', 'array', 'max:100'],
            'child_ids.*' => [
                'integer', 'distinct',
                Rule::exists('children', 'id')->where('parish_id', $parishId)->whereNull('deleted_at'),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->exists('parish_id') || $this->exists('child_ids')) {
                return;
            }

            $parent = ParentProfile::findOrFail((int) $this->route('parent'));
            if ($parent->children()->where('parish_id', '!=', $this->integer('parish_id'))->exists()) {
                $validator->errors()->add('parish_id', 'Hãy cập nhật liên kết thiếu nhi trước khi chuyển giáo xứ.');
            }
        });
    }
}
