<?php

namespace App\Http\Requests\Learning;

use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $announcement = $this->route('announcement');

        return $announcement instanceof Announcement
            ? $this->user()->can('update', $announcement)
            : $this->user()->can('create', Announcement::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'importance' => ['required', Rule::in(['normal', 'important', 'urgent'])],
            'scheduled_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:scheduled_at'],
            'is_pinned' => ['required', 'boolean'],
            'requires_acknowledgement' => ['required', 'boolean'],
            'version' => ['nullable', 'integer', 'min:1'],
            'targets' => ['required', 'array', 'min:1', 'max:30'],
            'targets.*.catechism_class_id' => ['required', 'integer', 'distinct', 'exists:catechism_classes,id'],
            'targets.*.audience' => ['required', Rule::in(['children', 'parents', 'both'])],
            'targets.*.child_ids' => ['present', 'array', 'max:100'],
            'targets.*.child_ids.*' => ['integer', 'distinct', 'exists:children,id'],
        ];
    }
}
