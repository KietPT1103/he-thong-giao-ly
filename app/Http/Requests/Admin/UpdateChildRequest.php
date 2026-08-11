<?php

namespace App\Http\Requests\Admin;

use App\Models\Child;
use App\Models\ParentProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update-children') === true
            && (! $this->exists('parent_ids') || $this->user()?->can('link-parent-child') === true)
            && (! $this->exists('class_id') || $this->user()?->can('enroll-children') === true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect(['full_name', 'code', 'saint_name'])
            ->filter(fn ($field) => $this->exists($field))
            ->mapWithKeys(fn ($field) => [
                $field => is_string($this->input($field)) ? trim($this->input($field)) : $this->input($field),
            ])->all());
    }

    public function rules(): array
    {
        $child = Child::withTrashed()->findOrFail((int) $this->route('child'));
        $parishId = $this->integer('parish_id') ?: $child->parish_id;

        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('children', 'code')->where('parish_id', $parishId)->ignore($child->id),
            ],
            'saint_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'parish_id' => ['sometimes', 'required', 'integer', Rule::exists('parishes', 'id')],
            'status' => ['sometimes', 'required', Rule::in(['studying', 'paused', 'graduated'])],
            'parent_ids' => ['sometimes', 'array', 'max:20'],
            'parent_ids.*' => [
                'integer', 'distinct',
                Rule::exists('parent_profiles', 'id')->where('parish_id', $parishId),
            ],
            'class_id' => ['sometimes', 'nullable', 'integer', Rule::exists('catechism_classes', 'id')->whereNull('deleted_at')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('parent_ids')) {
                $ids = collect($this->input('parent_ids'))->unique()->values();
                $activeCount = ParentProfile::query()
                    ->whereIn('id', $ids)
                    ->whereHas('user', fn ($query) => $query->whereNull('deleted_at'))
                    ->count();
                if ($activeCount !== $ids->count()) {
                    $validator->errors()->add('parent_ids', 'Chỉ có thể liên kết phụ huynh đang hoạt động.');
                }
            }

            if (! $this->exists('parish_id') || $this->exists('parent_ids')) {
                return;
            }

            $child = Child::withTrashed()->findOrFail((int) $this->route('child'));
            if ($child->parents()->where('parish_id', '!=', $this->integer('parish_id'))->exists()) {
                $validator->errors()->add('parish_id', 'Hãy cập nhật liên kết phụ huynh trước khi chuyển giáo xứ.');
            }
        });
    }
}
