<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTeacherClassEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('teacher') === true
            && $this->user()?->can('enroll-children') === true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['remove', 'stop', 'transfer'])],
            'target_class_id' => [
                'nullable',
                'integer',
                'required_if:action,transfer',
                Rule::exists('catechism_classes', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->string('action')->toString() === 'transfer'
                && $this->integer('target_class_id') === (int) $this->route('class')) {
                $validator->errors()->add('target_class_id', 'Lớp chuyển đến phải khác lớp hiện tại.');
            }
        }];
    }
}
