<?php

namespace App\Http\Requests\Learning;

use App\Models\QuestionBankItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertQuestionBankItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $question = $this->route('question');

        return $question instanceof QuestionBankItem
            ? $this->user()->can('update', $question)
            : $this->user()->can('create', QuestionBankItem::class);
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(['personal', 'parish'])],
            'type' => ['required', Rule::in(['single_choice', 'multiple_choice', 'true_false', 'short_answer', 'essay'])],
            'prompt' => ['required', 'string', 'max:5000'],
            'explanation' => ['nullable', 'string', 'max:5000'],
            'default_points' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:60'],
            'options' => ['nullable', 'array', 'max:20'],
            'options.*.content' => ['required_with:options', 'string', 'max:1000'],
            'options.*.is_correct' => ['required_with:options', 'boolean'],
            'accepted_answers' => ['nullable', 'array', 'max:20'],
            'accepted_answers.*' => ['string', 'max:500'],
            'rubric' => ['nullable', 'array', 'max:20'],
            'rubric.*.label' => ['required_with:rubric', 'string', 'max:200'],
            'rubric.*.points' => ['required_with:rubric', 'numeric', 'gte:0', 'max:1000'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $type = $this->input('type');
            $options = collect($this->input('options', []));
            $correct = $options->where('is_correct', true)->count();
            if (in_array($type, ['single_choice', 'multiple_choice', 'true_false'], true)) {
                if ($options->count() < 2
                    || ($type === 'single_choice' && $correct !== 1)
                    || ($type === 'multiple_choice' && $correct < 1)
                    || ($type === 'true_false' && ($options->count() !== 2 || $correct !== 1))) {
                    $validator->errors()->add('options', 'Cấu hình đáp án đúng chưa hợp lệ.');
                }
            }
            if ($type === 'short_answer' && count($this->input('accepted_answers', [])) === 0) {
                $validator->errors()->add('accepted_answers', 'Cần ít nhất một đáp án được chấp nhận.');
            }
        }];
    }
}
