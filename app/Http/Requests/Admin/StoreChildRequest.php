<?php

namespace App\Http\Requests\Admin;

use App\Models\ParentProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create-children') === true
            && (! $this->filled('parent_ids') || $this->user()?->can('link-parent-child') === true)
            && (! $this->filled('class_id') || $this->user()?->can('enroll-children') === true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->trimmed(['full_name', 'code', 'saint_name']));
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('children', 'code')->where('parish_id', $this->integer('parish_id')),
            ],
            'saint_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'parish_id' => ['required', 'integer', Rule::exists('parishes', 'id')],
            'status' => ['required', Rule::in(['studying', 'paused', 'graduated'])],
            'parent_ids' => ['present', 'array', 'max:20'],
            'parent_ids.*' => [
                'integer', 'distinct',
                Rule::exists('parent_profiles', 'id')->where('parish_id', $this->integer('parish_id')),
            ],
            'class_id' => ['nullable', 'integer', Rule::exists('catechism_classes', 'id')->whereNull('deleted_at')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('parent_ids')) {
                return;
            }

            $ids = collect($this->input('parent_ids'))->unique()->values();
            $activeCount = ParentProfile::query()
                ->whereIn('id', $ids)
                ->whereHas('user', fn ($query) => $query->whereNull('deleted_at'))
                ->count();
            if ($activeCount !== $ids->count()) {
                $validator->errors()->add('parent_ids', 'Chỉ có thể liên kết phụ huynh đang hoạt động.');
            }
        });
    }

    private function trimmed(array $fields): array
    {
        return collect($fields)->filter(fn ($field) => $this->exists($field))->mapWithKeys(fn ($field) => [
            $field => is_string($this->input($field)) ? trim($this->input($field)) : $this->input($field),
        ])->all();
    }
}
