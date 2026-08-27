<?php

namespace App\Http\Requests\Teacher;

use App\Http\Requests\Admin\UpdateClassRequest;
use App\Models\AcademicYear;
use App\Models\CatechismLevel;
use App\Models\Classroom;
use Illuminate\Validation\Validator;

class UpdateTeacherClassRequest extends UpdateClassRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('teacher') === true
            && $this->user()?->can('update-classes') === true
            && $this->user()?->teacherProfile !== null;
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($validator->errors()->hasAny([
                    'academic_year_id',
                    'catechism_level_id',
                    'classroom_id',
                ])) {
                    return;
                }

                $parishId = $this->user()?->teacherProfile?->parish_id;
                $year = AcademicYear::find($this->integer('academic_year_id'));
                $level = CatechismLevel::find($this->integer('catechism_level_id'));
                $room = $this->filled('classroom_id')
                    ? Classroom::find($this->integer('classroom_id'))
                    : null;

                if ($year && $year->parish_id !== $parishId) {
                    $validator->errors()->add('academic_year_id', 'Niên khóa không thuộc giáo xứ của bạn.');
                }
                if ($level && $level->parish_id !== $parishId) {
                    $validator->errors()->add('catechism_level_id', 'Khối giáo lý không thuộc giáo xứ của bạn.');
                }
                if ($room && $room->parish_id !== $parishId) {
                    $validator->errors()->add('classroom_id', 'Phòng học không thuộc giáo xứ của bạn.');
                }
            },
        ];
    }
}
