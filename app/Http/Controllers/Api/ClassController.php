<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CatechismClassResource;
use App\Http\Resources\ChildResource;
use App\Models\CatechismClass;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassController extends ApiController
{
    public function show(CatechismClass $class)
    {
        $this->authorize('view', $class);
        $class->load([
            'academicYear.parish',
            'level',
            'classroom',
            'schedules',
            'teachers.user:id,name,email,phone,status,deleted_at',
        ])->loadCount(['activeEnrollments as children_count']);

        return $this->success(new CatechismClassResource($class));
    }

    public function children(Request $request, CatechismClass $class)
    {
        $this->authorize('view', $class);
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['studying', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'compact' => ['nullable', 'boolean'],
        ]);
        $search = trim((string) ($data['search'] ?? ''));
        $relations = ['user:id,email,avatar_path'];
        if (! $request->boolean('compact')) {
            $relations[] = 'parents.user';
        }
        $children = $class->children()
            ->wherePivot('status', Enrollment::STATUS_ACTIVE)
            ->with($relations)
            ->when($search, fn ($query) => $query->where(fn ($inner) => $inner
                ->where('children.full_name', 'like', "%{$search}%")
                ->orWhere('children.code', 'like', "%{$search}%")
                ->orWhere('children.saint_name', 'like', "%{$search}%")))
            ->when($data['status'] ?? null, fn ($query, $status) => $query
                ->where('children.status', $status))
            ->orderBy('children.full_name')
            ->paginate(15);

        return $this->success(ChildResource::collection($children), 'Fetched children', [
            'current_page' => $children->currentPage(),
            'last_page' => $children->lastPage(),
            'per_page' => $children->perPage(),
            'total' => $children->total(),
        ]);
    }
}
