<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Learning\UpsertQuestionBankItemRequest;
use App\Models\QuestionBankItem;
use Illuminate\Http\Request;

class TeacherQuestionBankController extends ApiController
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', QuestionBankItem::class);
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:single_choice,multiple_choice,true_false,short_answer,essay'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $teacher = $request->user();
        $search = trim((string) ($data['search'] ?? ''));
        $items = QuestionBankItem::query()
            ->where(fn ($query) => $query
                ->where('owner_id', $teacher->id)
                ->orWhere(fn ($shared) => $shared
                    ->where('scope', 'parish')
                    ->where('parish_id', $teacher->teacherProfile->parish_id)))
            ->when($search, fn ($query) => $query->where('prompt', 'like', "%{$search}%"))
            ->when($data['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($data['difficulty'] ?? null, fn ($query, $difficulty) => $query->where('difficulty', $difficulty))
            ->with('owner:id,name')
            ->latest()->paginate(20);

        return $this->success($items, 'Đã tải ngân hàng câu hỏi.');
    }

    public function store(UpsertQuestionBankItemRequest $request)
    {
        $question = QuestionBankItem::create([
            ...$request->safe()->except('version'),
            'parish_id' => $request->user()->teacherProfile->parish_id,
            'owner_id' => $request->user()->id,
        ]);

        return $this->success($question, 'Đã thêm câu hỏi vào ngân hàng.', [], 201);
    }

    public function update(UpsertQuestionBankItemRequest $request, QuestionBankItem $question)
    {
        $data = $request->validated();
        if (($data['version'] ?? $question->version) !== $question->version) {
            return response()->json([
                'success' => false,
                'message' => 'Câu hỏi đã được cập nhật ở nơi khác.',
                'code' => 'VERSION_CONFLICT',
            ], 409);
        }
        unset($data['version']);
        $question->update([...$data, 'version' => $question->version + 1]);

        return $this->success($question->fresh(), 'Đã cập nhật câu hỏi.');
    }

    public function destroy(Request $request, QuestionBankItem $question)
    {
        $this->authorize('delete', $question);
        $question->delete();

        return $this->success(null, 'Đã lưu trữ câu hỏi.');
    }
}
