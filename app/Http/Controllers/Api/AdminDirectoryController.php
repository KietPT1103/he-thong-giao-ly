<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Resources\AdminListItemResource;
use App\Models\{
    Announcement,
    CatechismClass,
    Child,
    ParentProfile,
    Parish,
    TeacherProfile
};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminDirectoryController extends ApiController
{
    public function parishes(AdminIndexRequest $request)
    {
        $query = Parish::query()
            ->withCount(['children', 'academicYears'])
            ->when($request->string('search')->toString(), fn (Builder $q, string $search) =>
                $q->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")));

        return $this->page($query->orderBy('name')->paginate($request->integer('per_page', 15)), fn (Parish $parish) => [
            'id' => $parish->id,
            'name' => $parish->name,
            'code' => $parish->code,
            'secondary' => "{$parish->children_count} thiếu nhi",
            'details' => ["{$parish->academic_years_count} niên khóa"],
            'status' => 'Đang hoạt động',
        ]);
    }

    public function teachers(AdminIndexRequest $request)
    {
        $query = TeacherProfile::query()
            ->with(['user:id,name,email,status', 'parish:id,name'])
            ->withCount('classes')
            ->when($request->string('search')->toString(), fn (Builder $q, string $search) =>
                $q->whereHas('user', fn (Builder $user) => $user
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")));

        return $this->page($query->latest()->paginate($request->integer('per_page', 15)), fn (TeacherProfile $teacher) => [
            'id' => $teacher->id,
            'name' => $teacher->user->name,
            'code' => $teacher->code ?: 'Chưa cấp mã',
            'secondary' => $teacher->user->email,
            'details' => [$teacher->parish->name, "{$teacher->classes_count} lớp phụ trách"],
            'status' => $teacher->user->status === 'active' ? 'Đang hoạt động' : 'Đã khóa',
        ]);
    }

    public function parents(AdminIndexRequest $request)
    {
        $query = ParentProfile::query()
            ->with(['user:id,name,email,status,last_login_at', 'parish:id,name'])
            ->withCount('children')
            ->when($request->string('search')->toString(), fn (Builder $q, string $search) =>
                $q->whereHas('user', fn (Builder $user) => $user
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")));

        return $this->page($query->latest()->paginate($request->integer('per_page', 15)), fn (ParentProfile $parent) => [
            'id' => $parent->id,
            'name' => $parent->user->name,
            'code' => $parent->phone ?: 'Chưa có số điện thoại',
            'secondary' => $parent->user->email,
            'details' => [$parent->parish->name, "{$parent->children_count} thiếu nhi liên kết"],
            'status' => $parent->user->status === 'active' ? 'Đang hoạt động' : 'Đã khóa',
        ]);
    }

    public function children(AdminIndexRequest $request)
    {
        $query = Child::query()
            ->with(['parish:id,name', 'enrollments.catechismClass:id,name'])
            ->withCount('parents')
            ->when($request->string('search')->toString(), fn (Builder $q, string $search) =>
                $q->where(fn (Builder $inner) => $inner
                    ->where('full_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")))
            ->when($request->string('status')->toString(), fn (Builder $q, string $status) =>
                $q->where('status', $status));

        return $this->page($query->orderBy('full_name')->paginate($request->integer('per_page', 15)), fn (Child $child) => [
            'id' => $child->id,
            'name' => $child->full_name,
            'code' => $child->code,
            'secondary' => $child->saint_name ?: 'Chưa cập nhật tên thánh',
            'details' => [
                $child->parish->name,
                $child->enrollments->first()?->catechismClass?->name ?: 'Chưa xếp lớp',
                "{$child->parents_count} phụ huynh",
            ],
            'status' => $child->status === 'studying' ? 'Đang học' : $child->status,
        ]);
    }

    public function classes(AdminIndexRequest $request)
    {
        $query = CatechismClass::query()
            ->with(['academicYear:id,name', 'level:id,name', 'classroom:id,name'])
            ->withCount(['children', 'teachers'])
            ->when($request->string('search')->toString(), fn (Builder $q, string $search) =>
                $q->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")));

        return $this->page($query->orderBy('name')->paginate($request->integer('per_page', 15)), fn (CatechismClass $class) => [
            'id' => $class->id,
            'name' => $class->name,
            'code' => $class->code,
            'secondary' => "{$class->level->name} · {$class->academicYear->name}",
            'details' => [
                $class->classroom?->name ?: 'Chưa xếp phòng',
                "{$class->children_count} thiếu nhi",
                "{$class->teachers_count} giáo lý viên",
            ],
            'status' => $class->status === 'active' ? 'Đang hoạt động' : $class->status,
        ]);
    }

    public function announcements(AdminIndexRequest $request)
    {
        $query = Announcement::query()
            ->with(['parish:id,name', 'creator:id,name'])
            ->withCount('recipients')
            ->when($request->string('search')->toString(), fn (Builder $q, string $search) =>
                $q->where('title', 'like', "%{$search}%"));

        return $this->page($query->latest()->paginate($request->integer('per_page', 15)), fn (Announcement $announcement) => [
            'id' => $announcement->id,
            'name' => $announcement->title,
            'code' => $announcement->importance,
            'secondary' => $announcement->parish->name,
            'details' => [
                "Tạo bởi {$announcement->creator->name}",
                "{$announcement->recipients_count} người nhận",
                $announcement->created_at->format('d/m/Y H:i'),
            ],
            'status' => 'Đã đăng',
        ]);
    }

    private function page(LengthAwarePaginator $paginator, callable $transform)
    {
        $items = $paginator->getCollection()->map($transform);

        return $this->success(
            AdminListItemResource::collection($items),
            'Đã tải dữ liệu.',
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }
}
