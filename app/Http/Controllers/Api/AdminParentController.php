<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Admin\ParentIndexRequest;
use App\Http\Requests\Admin\StoreParentRequest;
use App\Http\Requests\Admin\UpdateParentRequest;
use App\Http\Resources\ParentResource;
use App\Models\Child;
use App\Models\ParentProfile;
use App\Models\Parish;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AdminParentController extends ApiController
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(ParentIndexRequest $request)
    {
        $status = $request->string('status')->toString();
        $parents = ParentProfile::query()
            ->with(['user', 'parish:id,name,code'])
            ->withCount('children')
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->where(fn (Builder $inner) => $inner
                ->where('phone', 'like', "%{$search}%")
                ->orWhereHas('user', fn (Builder $user) => $user
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))))
            ->when($request->integer('parish_id'), fn (Builder $query, int $parishId) => $query->where('parish_id', $parishId))
            ->when($status === 'archived', fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user->onlyTrashed()))
            ->when(in_array($status, ['active', 'blocked'], true), fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user
                ->whereNull('deleted_at')->where('status', $status)))
            ->when($status === '', fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user->whereNull('deleted_at')))
            ->orderBy(User::withTrashed()->select('name')->whereColumn('users.id', 'parent_profiles.user_id'))
            ->paginate($request->integer('per_page', 15));

        return $this->success(ParentResource::collection($parents->getCollection()), 'Đã tải danh sách phụ huynh.', [
            'current_page' => $parents->currentPage(),
            'last_page' => $parents->lastPage(),
            'per_page' => $parents->perPage(),
            'total' => $parents->total(),
        ]);
    }

    public function options()
    {
        return $this->success([
            'parishes' => Parish::query()->orderBy('name')->get(['id', 'name', 'code']),
            'children' => Child::query()->orderBy('full_name')->get(['id', 'parish_id', 'full_name', 'code']),
        ]);
    }

    public function show(int $parent)
    {
        $profile = ParentProfile::findOrFail($parent);
        $this->loadDetails($profile);

        return $this->success(new ParentResource($profile), 'Đã tải thông tin phụ huynh.');
    }

    public function store(StoreParentRequest $request)
    {
        $data = $request->validated();
        $parent = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'status' => 'active',
                'must_change_password' => true,
            ]);
            $user->assignRole('parent');
            $parent = ParentProfile::create([
                'user_id' => $user->id,
                'parish_id' => $data['parish_id'],
                'phone' => $data['phone'] ?? null,
            ]);
            $parent->children()->sync($data['child_ids']);
            $this->auditLogger->record($request, 'parent.created', $parent, null, $this->auditPayload($parent));

            return $parent;
        });
        $this->loadDetails($parent);

        return $this->success(new ParentResource($parent), 'Đã tạo phụ huynh.', [], 201);
    }

    public function update(UpdateParentRequest $request, int $parent)
    {
        $profile = ParentProfile::findOrFail($parent);
        abort_if($profile->user->trashed(), 409, 'Hãy khôi phục phụ huynh trước khi chỉnh sửa.');
        $data = $request->validated();
        $oldValues = $this->auditPayload($profile);

        DB::transaction(function () use ($data, $request, $profile, $oldValues) {
            $profile->user->update(Arr::only($data, ['name', 'email', 'phone']));
            $profile->update(Arr::only($data, ['parish_id', 'phone']));
            if (array_key_exists('child_ids', $data)) {
                $profile->children()->sync($data['child_ids']);
            }
            $this->auditLogger->record($request, 'parent.updated', $profile, $oldValues, $this->auditPayload($profile));
        });
        $this->loadDetails($profile);

        return $this->success(new ParentResource($profile), 'Đã cập nhật phụ huynh.');
    }

    public function destroy(Request $request, int $parent)
    {
        $profile = ParentProfile::findOrFail($parent);
        if (! $profile->user->trashed()) {
            $oldValues = $this->auditPayload($profile);
            DB::transaction(function () use ($profile, $request, $oldValues) {
                $profile->user->delete();
                DB::table('sessions')->where('user_id', $profile->user_id)->delete();
                $this->auditLogger->record($request, 'parent.archived', $profile, $oldValues);
            });
        }

        return $this->success(null, 'Đã lưu trữ phụ huynh.');
    }

    public function restore(Request $request, int $parent)
    {
        $profile = ParentProfile::findOrFail($parent);
        if ($profile->user->trashed()) {
            DB::transaction(function () use ($profile, $request) {
                $profile->user->restore();
                $this->auditLogger->record($request, 'parent.restored', $profile, null, $this->auditPayload($profile));
            });
        }
        $this->loadDetails($profile);

        return $this->success(new ParentResource($profile), 'Đã khôi phục phụ huynh.');
    }

    private function loadDetails(ParentProfile $parent): void
    {
        $parent->load(['user', 'parish:id,name,code', 'children' => fn ($query) => $query->orderBy('full_name')])
            ->loadCount('children');
    }

    private function auditPayload(ParentProfile $parent): array
    {
        $parent->loadMissing('user');

        return [
            'name' => $parent->user->name,
            'email' => $parent->user->email,
            'phone' => $parent->phone ?? $parent->user->phone,
            'parish_id' => $parent->parish_id,
            'status' => $parent->user->trashed() ? 'archived' : $parent->user->status,
            'child_ids' => $parent->children()->pluck('children.id')->values()->all(),
        ];
    }
}
