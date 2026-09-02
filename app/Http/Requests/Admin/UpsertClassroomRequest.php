<?php

namespace App\Http\Requests\Admin;

use App\Models\Classroom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('classroom') ? 'update-classrooms' : 'create-classrooms';

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
        $classroom = $this->route('classroom');
        $parishId = $classroom instanceof Classroom ? $classroom->parish_id : $this->integer('parish_id');

        return [
            'parish_id' => [$classroom ? 'prohibited' : 'required', 'integer', Rule::exists('parishes', 'id')],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('classrooms', 'name')
                    ->where('parish_id', $parishId)
                    ->ignore($classroom instanceof Classroom ? $classroom->id : null),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Tên phòng học đã tồn tại trong giáo xứ này.',
            'capacity.min' => 'Sức chứa phải lớn hơn 0.',
        ];
    }
}
