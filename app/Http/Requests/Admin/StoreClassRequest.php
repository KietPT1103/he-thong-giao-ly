<?php

namespace App\Http\Requests\Admin;

use App\Models\AcademicYear;
use App\Models\CatechismLevel;
use App\Models\Classroom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') === true
            && $this->user()?->can('create-classes') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'code' => is_string($this->input('code')) ? trim($this->input('code')) : $this->input('code'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('catechism_classes', 'code')
                    ->where('academic_year_id', $this->integer('academic_year_id')),
            ],
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')],
            'catechism_level_id' => ['required', 'integer', Rule::exists('catechism_levels', 'id')],
            'classroom_id' => ['nullable', 'integer', Rule::exists('classrooms', 'id')],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['academic_year_id', 'catechism_level_id', 'classroom_id'])) {
                return;
            }

            $year = AcademicYear::find($this->integer('academic_year_id'));
            $level = CatechismLevel::find($this->integer('catechism_level_id'));
            $room = $this->filled('classroom_id') ? Classroom::find($this->integer('classroom_id')) : null;

            if ($year && $level && $year->parish_id !== $level->parish_id) {
                $validator->errors()->add('catechism_level_id', 'Khối giáo lý phải cùng giáo xứ với niên khóa.');
            }
            if ($year && $room && $year->parish_id !== $room->parish_id) {
                $validator->errors()->add('classroom_id', 'Phòng học phải cùng giáo xứ với niên khóa.');
            }
        }];
    }
}
