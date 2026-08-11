<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Admin\ChildIndexRequest;
use App\Http\Requests\Admin\StoreChildRequest;
use App\Http\Requests\Admin\UpdateChildRequest;
use App\Http\Resources\ChildResource;
use App\Models\CatechismClass;
use App\Models\Child;
use App\Models\ParentProfile;
use App\Models\Parish;
use App\Services\AuditLogger;
use App\Services\ChildEnrollmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AdminChildController extends ApiController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ChildEnrollmentService $enrollmentService,
    ) {}

    public function index(ChildIndexRequest $request)
    {
        $status = $request->string('status')->toString();
        $children = Child::withTrashed()
            ->with(['user', 'parish:id,name,code', 'activeEnrollment.catechismClass.academicYear'])
            ->withCount('parents')
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->where(fn (Builder $inner) => $inner
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('saint_name', 'like', "%{$search}%")))
            ->when($request->integer('parish_id'), fn (Builder $query, int $parishId) => $query->where('parish_id', $parishId))
            ->when($status === 'archived', fn (Builder $query) => $query->onlyTrashed())
            ->when(in_array($status, ['studying', 'paused', 'graduated'], true), fn (Builder $query) => $query->whereNull('deleted_at')->where('status', $status))
            ->when($status === '', fn (Builder $query) => $query->whereNull('deleted_at'))
            ->orderBy('full_name')
            ->paginate($request->integer('per_page', 15));

        return $this->success(ChildResource::collection($children->getCollection()), 'Đã tải danh sách thiếu nhi.', [
            'current_page' => $children->currentPage(),
            'last_page' => $children->lastPage(),
            'per_page' => $children->perPage(),
            'total' => $children->total(),
        ]);
    }

    public function options()
    {
        return $this->success([
            'parishes' => Parish::query()->orderBy('name')->get(['id', 'name', 'code']),
            'parents' => ParentProfile::query()
                ->whereHas('user', fn (Builder $query) => $query->whereNull('deleted_at'))
                ->with('user:id,name,email')
                ->orderBy('id')
                ->get()
                ->map(fn (ParentProfile $parent) => [
                    'id' => $parent->id,
                    'parish_id' => $parent->parish_id,
                    'name' => $parent->user->name,
                    'email' => $parent->user->email,
                ]),
            'classes' => CatechismClass::query()
                ->where('status', 'active')
                ->whereHas('academicYear', fn (Builder $query) => $query->where('is_current', true))
                ->with('academicYear:id,parish_id,name')
                ->orderBy('name')
                ->get(['id', 'academic_year_id', 'name', 'code'])
                ->map(fn (CatechismClass $class) => [
                    'id' => $class->id,
                    'parish_id' => $class->academicYear->parish_id,
                    'name' => $class->name,
                    'code' => $class->code,
                    'academic_year' => $class->academicYear->name,
                ]),
        ]);
    }

    public function show(int $child)
    {
        $profile = Child::withTrashed()->findOrFail($child);
        $this->loadDetails($profile);

        return $this->success(new ChildResource($profile), 'Đã tải thông tin thiếu nhi.');
    }

    public function store(StoreChildRequest $request)
    {
        $data = $request->validated();
        $child = DB::transaction(function () use ($data, $request) {
            $child = Child::create(Arr::only($data, [
                'full_name', 'code', 'saint_name', 'date_of_birth', 'parish_id', 'status',
            ]));
            $child->parents()->sync($data['parent_ids']);
            if (array_key_exists('class_id', $data)) {
                $this->assertEnrollmentResult($this->enrollmentService->assignCurrentClass($child, $data['class_id']));
            }
            $this->auditLogger->record($request, 'child.created', $child, null, $this->auditPayload($child));

            return $child;
        });
        $this->loadDetails($child);

        return $this->success(new ChildResource($child), 'Đã tạo hồ sơ thiếu nhi.', [], 201);
    }

    public function update(UpdateChildRequest $request, int $child)
    {
        $profile = Child::findOrFail($child);
        $data = $request->validated();
        $oldValues = $this->auditPayload($profile);
        $oldParishId = $profile->parish_id;

        DB::transaction(function () use ($data, $request, $profile, $oldValues, $oldParishId) {
            $profile->update(Arr::only($data, [
                'full_name', 'code', 'saint_name', 'date_of_birth', 'parish_id', 'status',
            ]));
            if ($profile->user && array_key_exists('full_name', $data)) {
                $profile->user->update(['name' => $data['full_name']]);
            }
            if (array_key_exists('parent_ids', $data)) {
                $profile->parents()->sync($data['parent_ids']);
            }
            if (array_key_exists('class_id', $data) || $oldParishId !== $profile->parish_id) {
                $this->assertEnrollmentResult($this->enrollmentService->assignCurrentClass($profile, $data['class_id'] ?? null));
            }
            $this->auditLogger->record($request, 'child.updated', $profile, $oldValues, $this->auditPayload($profile));
        });
        $this->loadDetails($profile);

        return $this->success(new ChildResource($profile), 'Đã cập nhật hồ sơ thiếu nhi.');
    }

    public function destroy(Request $request, int $child)
    {
        $profile = Child::findOrFail($child);
        $oldValues = $this->auditPayload($profile);
        DB::transaction(function () use ($profile, $request, $oldValues) {
            $profile->delete();
            if ($profile->user && ! $profile->user->trashed()) {
                $profile->user->delete();
                DB::table('sessions')->where('user_id', $profile->user_id)->delete();
            }
            $this->auditLogger->record($request, 'child.archived', $profile, $oldValues);
        });

        return $this->success(null, 'Đã lưu trữ hồ sơ thiếu nhi.');
    }

    public function restore(Request $request, int $child)
    {
        $profile = Child::withTrashed()->findOrFail($child);
        if ($profile->trashed()) {
            $profile->restore();
            if ($profile->user?->trashed()) {
                $profile->user->restore();
            }
            $this->auditLogger->record($request, 'child.restored', $profile, null, $this->auditPayload($profile));
        }
        $this->loadDetails($profile);

        return $this->success(new ChildResource($profile), 'Đã khôi phục hồ sơ thiếu nhi.');
    }

    private function loadDetails(Child $child): void
    {
        $child->load([
            'user', 'parish:id,name,code',
            'parents' => fn ($query) => $query->with('user')->orderBy('id'),
            'activeEnrollment.catechismClass.academicYear',
        ])->loadCount('parents');
    }

    private function auditPayload(Child $child): array
    {
        return [
            'full_name' => $child->full_name,
            'code' => $child->code,
            'saint_name' => $child->saint_name,
            'date_of_birth' => $child->date_of_birth?->toDateString(),
            'parish_id' => $child->parish_id,
            'status' => $child->status,
            'parent_ids' => $child->parents()->pluck('parent_profiles.id')->values()->all(),
            'class_id' => $child->activeEnrollment()->value('catechism_class_id'),
        ];
    }

    private function assertEnrollmentResult(array $result): void
    {
        if (! isset($result['error'])) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $result['error']['message'],
            'code' => $result['error']['code'],
        ], 422));
    }
}
