<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Http\Requests\Admin\TeacherIndexRequest;
use App\Http\Requests\Admin\UpdateTeacherRequest;
use App\Http\Resources\TeacherResource;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AdminTeacherController extends ApiController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function index(TeacherIndexRequest $request)
    {
        $status = $request->string('status')->toString();
        $sort = $request->string('sort', 'name')->toString();
        $direction = $request->string('direction', 'asc')->toString();

        $teachers = TeacherProfile::query()
            ->with(['user', 'parish:id,name,code'])
            ->withCount('classes')
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) =>
                $query->where(fn (Builder $inner) => $inner
                    ->where('code', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))))
            ->when($request->integer('parish_id'), fn (Builder $query, int $parishId) =>
                $query->where('parish_id', $parishId))
            ->when($status === 'archived', fn (Builder $query) =>
                $query->whereHas('user', fn (Builder $user) => $user->onlyTrashed()))
            ->when(in_array($status, ['active', 'blocked'], true), fn (Builder $query) =>
                $query->whereHas('user', fn (Builder $user) => $user
                    ->whereNull('deleted_at')
                    ->where('status', $status)))
            ->when($status === '', fn (Builder $query) =>
                $query->whereHas('user', fn (Builder $user) => $user->whereNull('deleted_at')))
            ->when(
                $sort === 'name',
                fn (Builder $query) => $query->orderBy(
                    User::withTrashed()->select('name')->whereColumn('users.id', 'teacher_profiles.user_id'),
                    $direction,
                ),
                fn (Builder $query) => $query->orderBy('created_at', $direction),
            )
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            TeacherResource::collection($teachers->getCollection()),
            'Đã tải danh sách giáo lý viên.',
            [
                'current_page' => $teachers->currentPage(),
                'last_page' => $teachers->lastPage(),
                'per_page' => $teachers->perPage(),
                'total' => $teachers->total(),
            ],
        );
    }

    public function show(TeacherProfile $teacher)
    {
        $this->loadDetails($teacher);

        return $this->success(new TeacherResource($teacher), 'Đã tải thông tin giáo lý viên.');
    }

    public function store(StoreTeacherRequest $request)
    {
        $data = $request->validated();
        $teacher = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'status' => 'active',
                'must_change_password' => true,
            ]);
            $user->assignRole('teacher');

            $teacher = TeacherProfile::create([
                'user_id' => $user->id,
                'parish_id' => $data['parish_id'],
                'code' => $data['code'],
                'phone' => $data['phone'] ?? null,
            ]);

            $this->auditLogger->record(
                $request,
                'teacher.created',
                $teacher,
                null,
                $this->auditPayload($teacher),
            );

            return $teacher;
        });
        $this->loadDetails($teacher);

        return $this->success(new TeacherResource($teacher), 'Đã tạo giáo lý viên.', [], 201);
    }

    public function update(UpdateTeacherRequest $request, TeacherProfile $teacher)
    {
        $data = $request->validated();
        $oldValues = $this->auditPayload($teacher);

        DB::transaction(function () use ($data, $request, $teacher, $oldValues) {
            $userData = Arr::only($data, ['name', 'email', 'phone', 'status']);
            $profileData = Arr::only($data, ['code', 'parish_id', 'phone']);
            $teacher->user->update($userData);
            $teacher->update($profileData);

            $teacher->refresh();
            $this->auditLogger->record(
                $request,
                'teacher.updated',
                $teacher,
                $oldValues,
                $this->auditPayload($teacher),
            );
        });
        $this->loadDetails($teacher);

        return $this->success(new TeacherResource($teacher), 'Đã cập nhật giáo lý viên.');
    }

    public function destroy(TeacherIndexRequest $request, TeacherProfile $teacher)
    {
        $blockingClasses = DB::transaction(function () use ($request, $teacher) {
            $lockedTeacher = TeacherProfile::query()->whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            $classes = $lockedTeacher->classes()
                ->orderBy('name')
                ->get(['catechism_classes.id', 'name', 'code']);

            if ($classes->isNotEmpty()) {
                return $classes->map(fn ($class) => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'code' => $class->code,
                ])->values()->all();
            }

            $user = $lockedTeacher->user;
            if (! $user->trashed()) {
                $user->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $this->auditLogger->record(
                    $request,
                    'teacher.archived',
                    $lockedTeacher,
                    $this->auditPayload($lockedTeacher),
                );
            }

            return [];
        });

        if ($blockingClasses !== []) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể lưu trữ giáo lý viên khi vẫn còn lớp phụ trách.',
                'code' => 'TEACHER_HAS_CLASSES',
                'data' => ['classes' => $blockingClasses],
            ], 422);
        }

        return $this->success(null, 'Đã lưu trữ giáo lý viên.');
    }

    public function restore(TeacherIndexRequest $request, TeacherProfile $teacher)
    {
        $user = $teacher->user;
        if ($user->trashed()) {
            $user->restore();
            $this->auditLogger->record(
                $request,
                'teacher.restored',
                $teacher,
                null,
                $this->auditPayload($teacher),
            );
        }
        $this->loadDetails($teacher);

        return $this->success(new TeacherResource($teacher), 'Đã khôi phục giáo lý viên.');
    }

    private function loadDetails(TeacherProfile $teacher): void
    {
        $teacher->load([
            'user',
            'parish:id,name,code',
            'classes' => fn ($query) => $query
                ->with(['academicYear:id,name', 'level:id,name'])
                ->orderBy('name'),
        ])->loadCount('classes');
    }

    private function auditPayload(TeacherProfile $teacher): array
    {
        $teacher->loadMissing(['user', 'parish:id,name']);

        return [
            'name' => $teacher->user->name,
            'email' => $teacher->user->email,
            'phone' => $teacher->phone ?? $teacher->user->phone,
            'teacher_code' => $teacher->code,
            'parish_id' => $teacher->parish_id,
            'parish_name' => $teacher->parish->name,
            'status' => $teacher->user->trashed() ? 'archived' : $teacher->user->status,
        ];
    }
}
