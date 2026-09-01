<?php

namespace App\Http\Requests\Learning;

use App\Models\Assignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return $assignment instanceof Assignment
            ? $this->user()->can('update', $assignment)
            : $this->user()->can('create', Assignment::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'type' => ['required', Rule::in(['submission', 'quiz', 'hybrid'])],
            'max_score' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'passing_score' => ['required', 'numeric', 'gte:0', 'max:1000'],
            'opens_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'allowed_attempts' => ['required', 'integer', 'min:0', 'max:20'],
            'score_method' => ['required', Rule::in(['highest', 'latest', 'average'])],
            'allow_resume' => ['required', 'boolean'],
            'allow_late' => ['required', 'boolean'],
            'late_penalty_percent' => ['required', 'numeric', 'gte:0', 'lte:100'],
            'shuffle_questions' => ['required', 'boolean'],
            'shuffle_options' => ['required', 'boolean'],
            'allow_backtracking' => ['required', 'boolean'],
            'result_release_mode' => ['required', Rule::in(['manual', 'immediate', 'scheduled'])],
            'results_release_at' => ['nullable', 'date'],
            'show_answers' => ['required', 'boolean'],
            'version' => ['nullable', 'integer', 'min:1'],
            'targets' => ['required', 'array', 'min:1', 'max:30'],
            'targets.*.catechism_class_id' => ['required', 'integer', 'exists:catechism_classes,id'],
            'targets.*.child_ids' => ['present', 'array', 'max:100'],
            'targets.*.child_ids.*' => ['integer', 'distinct', 'exists:children,id'],
            'targets.*.due_at' => ['nullable', 'date'],
            'targets.*.attempt_limit' => ['nullable', 'integer', 'min:0', 'max:20'],
            'questions' => ['required', 'array', 'min:1', 'max:100'],
            'questions.*.source_question_id' => ['nullable', 'integer', 'exists:question_bank_items,id'],
            'questions.*.type' => ['required', Rule::in(['single_choice', 'multiple_choice', 'true_false', 'short_answer', 'essay'])],
            'questions.*.prompt' => ['required', 'string', 'max:5000'],
            'questions.*.explanation' => ['nullable', 'string', 'max:5000'],
            'questions.*.points' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'questions.*.position' => ['required', 'integer', 'min:1'],
            'questions.*.options' => ['nullable', 'array', 'max:20'],
            'questions.*.options.*.content' => ['required_with:questions.*.options', 'string', 'max:1000'],
            'questions.*.options.*.is_correct' => ['required_with:questions.*.options', 'boolean'],
            'questions.*.accepted_answers' => ['nullable', 'array', 'max:20'],
            'questions.*.accepted_answers.*' => ['string', 'max:500'],
            'questions.*.rubric' => ['nullable', 'array', 'max:20'],
            'questions.*.rubric.*.label' => ['required_with:questions.*.rubric', 'string', 'max:200'],
            'questions.*.rubric.*.points' => ['required_with:questions.*.rubric', 'numeric', 'gte:0'],
            'questions.*.settings' => ['nullable', 'array'],
            'questions.*.settings.partial_credit' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->date('opens_at') && $this->date('due_at')?->lte($this->date('opens_at'))) {
                $validator->errors()->add('due_at', 'Hạn nộp phải sau thời điểm mở bài.');
            }
            if ((float) $this->input('passing_score') > (float) $this->input('max_score')) {
                $validator->errors()->add('passing_score', 'Điểm đạt không thể lớn hơn điểm tối đa.');
            }
            if ($this->input('result_release_mode') === 'scheduled' && ! $this->filled('results_release_at')) {
                $validator->errors()->add('results_release_at', 'Cần chọn thời điểm công bố kết quả.');
            }
            foreach ($this->input('questions', []) as $index => $question) {
                $type = $question['type'] ?? null;
                $options = collect($question['options'] ?? []);
                $correct = $options->where('is_correct', true)->count();
                $invalidAuto = in_array($type, ['single_choice', 'multiple_choice', 'true_false'], true)
                    && ($options->count() < 2
                        || ($type === 'single_choice' && $correct !== 1)
                        || ($type === 'multiple_choice' && $correct < 1)
                        || ($type === 'true_false' && ($options->count() !== 2 || $correct !== 1)));
                if ($invalidAuto) {
                    $validator->errors()->add("questions.{$index}.options", 'Cấu hình đáp án đúng chưa hợp lệ.');
                }
                if ($type === 'short_answer' && count($question['accepted_answers'] ?? []) === 0) {
                    $validator->errors()->add("questions.{$index}.accepted_answers", 'Cần ít nhất một đáp án được chấp nhận.');
                }
            }
        }];
    }
}
