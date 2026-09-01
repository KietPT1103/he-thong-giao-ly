<?php

namespace App\Http\Controllers\Api;

use App\Models\Assignment;
use App\Models\AssignmentFile;
use App\Models\Submission;
use App\Models\SubmissionFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LearningFileController extends ApiController
{
    private const MAX_FILES = 5;

    private const FILE_RULES = [
        'required', 'file', 'max:20480',
        'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png,webp,mp3,m4a,wav,mp4',
    ];

    public function storeAssignment(Request $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);
        abort_unless($assignment->status === Assignment::STATUS_DRAFT, 422, 'Chỉ có thể thêm tệp khi bài tập còn là bản nháp.');
        abort_if($assignment->files()->count() >= self::MAX_FILES, 422, 'Bài tập đã đạt tối đa 5 tệp.');
        $file = $request->validate(['file' => self::FILE_RULES])['file'];
        $stored = $this->store($file, "learning/assignments/{$assignment->id}");
        $record = $assignment->files()->create([
            'uploaded_by' => $request->user()->id,
            ...$stored,
        ]);

        return $this->success($record, 'Đã tải tệp lên bài tập.', [], 201);
    }

    public function storeSubmission(Request $request, Submission $submission)
    {
        $this->authorize('update', $submission);
        abort_unless(in_array($submission->status, [Submission::STATUS_IN_PROGRESS, Submission::STATUS_REOPENED], true), 422, 'Bài làm đã khóa tệp đính kèm.');
        abort_if($submission->files()->count() >= self::MAX_FILES, 422, 'Bài làm đã đạt tối đa 5 tệp.');
        $file = $request->validate(['file' => self::FILE_RULES])['file'];
        $stored = $this->store($file, "learning/submissions/{$submission->id}");
        $record = $submission->files()->create([
            'uploaded_by' => $request->user()->id,
            ...$stored,
        ]);

        return $this->success($record, 'Đã tải tệp lên bài làm.', [], 201);
    }

    public function downloadAssignment(Request $request, AssignmentFile $file)
    {
        $this->authorize('view', $file->assignment);

        return $this->download($file);
    }

    public function downloadSubmission(Request $request, SubmissionFile $file)
    {
        $this->authorize('view', $file->submission);

        return $this->download($file);
    }

    public function destroyAssignment(Request $request, AssignmentFile $file)
    {
        $this->authorize('update', $file->assignment);
        abort_unless($file->assignment->status === Assignment::STATUS_DRAFT, 422);
        Storage::disk('local')->delete($file->path);
        $file->delete();

        return $this->success(null, 'Đã xóa tệp khỏi bài tập.');
    }

    public function destroySubmission(Request $request, SubmissionFile $file)
    {
        $this->authorize('update', $file->submission);
        abort_unless(in_array($file->submission->status, [Submission::STATUS_IN_PROGRESS, Submission::STATUS_REOPENED], true), 422);
        Storage::disk('local')->delete($file->path);
        $file->delete();

        return $this->success(null, 'Đã xóa tệp khỏi bài làm.');
    }

    private function store($file, string $directory): array
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $name = Str::uuid().($extension ? ".{$extension}" : '');
        $path = $file->storeAs($directory, $name, 'local');

        return [
            'path' => $path,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
        ];
    }

    private function download(AssignmentFile|SubmissionFile $file)
    {
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
