<?php

namespace App\Http\Requests\Admin;

use App\Models\AcademicYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('academic_year') ? 'update-academic-years' : 'create-academic-years';

        return $this->user()?->can('access-admin') === true
            && $this->user()?->can($permission) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
        ]);
    }

    public function rules(): array
    {
        $year = $this->route('academic_year');
        $parishId = $year instanceof AcademicYear ? $year->parish_id : $this->integer('parish_id');

        return [
            'parish_id' => [$year ? 'prohibited' : 'required', 'integer', Rule::exists('parishes', 'id')],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('academic_years', 'name')
                    ->where('parish_id', $parishId)
                    ->ignore($year instanceof AcademicYear ? $year->id : null),
            ],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after:starts_on'],
            'is_current' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Tên niên khóa đã tồn tại trong giáo xứ này.',
            'ends_on.after' => 'Ngày kết thúc phải sau ngày bắt đầu.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->boolean('is_current') && ! $this->boolean('is_active')) {
                $validator->errors()->add('is_current', 'Niên khóa hiện tại phải đang được sử dụng.');
            }
        }];
    }
}
