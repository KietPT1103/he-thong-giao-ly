<?php

namespace App\Http\Requests\Learning;

use App\Models\Submission;
use Illuminate\Foundation\Http\FormRequest;

class SaveSubmissionAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('submission');

        return $submission instanceof Submission && $this->user()->can('update', $submission);
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'answers' => ['required', 'array', 'min:1', 'max:100'],
            'answers.*.question_id' => ['required', 'integer', 'distinct', 'exists:assignment_questions,id'],
            'answers.*.answer' => ['nullable', 'array', 'max:50'],
        ];
    }
}
