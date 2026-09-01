<?php

namespace App\Http\Requests\Learning;

use App\Models\Submission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GradeSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('submission');

        return $submission instanceof Submission && $this->user()->can('grade', $submission);
    }

    public function rules(): array
    {
        $submission = $this->route('submission');

        return [
            'version' => ['required', 'integer', 'min:1'],
            'general_feedback' => ['nullable', 'string', 'max:5000'],
            'reason' => [Rule::requiredIf($submission?->status === Submission::STATUS_RELEASED), 'nullable', 'string', 'max:1000'],
            'answers' => ['required', 'array', 'min:1', 'max:100'],
            'answers.*.question_id' => ['required', 'integer', 'distinct', 'exists:assignment_questions,id'],
            'answers.*.score' => ['required', 'numeric', 'gte:0', 'max:1000'],
            'answers.*.feedback' => ['nullable', 'string', 'max:5000'],
            'answers.*.rubric_scores' => ['nullable', 'array', 'max:20'],
            'answers.*.rubric_scores.*.label' => ['required_with:answers.*.rubric_scores', 'string', 'max:200'],
            'answers.*.rubric_scores.*.score' => ['required_with:answers.*.rubric_scores', 'numeric', 'gte:0'],
        ];
    }
}
