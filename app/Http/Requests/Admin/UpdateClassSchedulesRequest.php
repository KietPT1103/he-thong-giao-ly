<?php

namespace App\Http\Requests\Admin;

use App\Models\CatechismClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateClassSchedulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') === true
            && $this->user()?->can('update-classes') === true;
    }

    public function rules(): array
    {
        return [
            'schedules' => ['present', 'array', 'max:20'],
            'schedules.*.weekday' => ['required', 'integer', 'between:1,7'],
            'schedules.*.starts_at' => ['required', 'date_format:H:i'],
            'schedules.*.ends_at' => ['required', 'date_format:H:i', 'after:schedules.*.starts_at'],
            'schedules.*.starts_on' => ['nullable', 'date_format:Y-m-d'],
            'schedules.*.ends_on' => ['nullable', 'date_format:Y-m-d'],
            'allow_teacher_conflicts' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $class = CatechismClass::with('academicYear')->find($this->route('class'));
            if (! $class) {
                return;
            }

            foreach ((array) $this->input('schedules', []) as $index => $schedule) {
                if (! is_array($schedule)) {
                    continue;
                }
                $startsOn = $schedule['starts_on'] ?? null;
                $endsOn = $schedule['ends_on'] ?? null;
                $yearStarts = $class->academicYear->starts_on->toDateString();
                $yearEnds = $class->academicYear->ends_on->toDateString();

                if ($startsOn && ($startsOn < $yearStarts || $startsOn > $yearEnds)) {
                    $validator->errors()->add("schedules.{$index}.starts_on", 'Ngày bắt đầu phải nằm trong niên khóa.');
                }
                if ($endsOn && ($endsOn < $yearStarts || $endsOn > $yearEnds)) {
                    $validator->errors()->add("schedules.{$index}.ends_on", 'Ngày kết thúc phải nằm trong niên khóa.');
                }
                if ($startsOn && $endsOn && $startsOn > $endsOn) {
                    $validator->errors()->add("schedules.{$index}.ends_on", 'Ngày kết thúc phải từ ngày bắt đầu trở đi.');
                }
            }
        }];
    }
}
