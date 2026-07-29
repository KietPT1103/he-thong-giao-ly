<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\Child;
use App\Models\ParentProfile;
use App\Models\Parish;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminAccountController extends ApiController
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::exists('roles', 'name')],
            'status' => ['nullable', Rule::in(['active', 'blocked', 'archived'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = User::withTrashed()->with(['roles', 'permissions', 'deniedPermissions'])
            ->when($validated['search'] ?? null, fn ($q, $search) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($validated['role'] ?? null, fn ($q, $role) => $q->role($role))
            ->when(($validated['status'] ?? null) === 'archived', fn ($q) => $q->onlyTrashed())
            ->when(in_array($validated['status'] ?? null, ['active', 'blocked'], true), fn ($q) => $q->where('status', $validated['status']));
        $accounts = $query->latest()->paginate(15);

        return $this->success(UserResource::collection($accounts->getCollection()), 'Đã tải tài khoản.', [
            'current_page' => $accounts->currentPage(),
            'last_page' => $accounts->lastPage(),
            'per_page' => $accounts->perPage(),
            'total' => $accounts->total(),
        ]);
    }

    public function options()
    {
        return $this->success([
            'roles' => Role::orderBy('name')->get(['id', 'name'])->map(fn ($role) => [
                'name' => $role->name,
                'permissions' => $role->permissions()->orderBy('name')->pluck('name'),
            ]),
            'permissions' => Permission::orderBy('name')->pluck('name'),
        ]);
    }

    public function show(int $account)
    {
        return $this->success(new UserResource(User::withTrashed()->findOrFail($account)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::exists('roles', 'name')],
            'parish_id' => ['nullable', Rule::exists('parishes', 'id')],
        ]);
        $user = DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->only(['name', 'email', 'phone', 'password'])->all());
            $user->assignRole($data['role']);
            $parishId = $data['parish_id'] ?? Parish::query()->value('id');
            if ($data['role'] === 'teacher' && $parishId) {
                TeacherProfile::create(['user_id' => $user->id, 'parish_id' => $parishId, 'code' => 'GLV-U'.$user->id]);
            } elseif ($data['role'] === 'parent' && $parishId) {
                ParentProfile::create(['user_id' => $user->id, 'parish_id' => $parishId, 'phone' => $data['phone'] ?? null]);
            } elseif ($data['role'] === 'child' && $parishId) {
                Child::create(['user_id' => $user->id, 'parish_id' => $parishId, 'code' => 'TN-U'.$user->id, 'full_name' => $user->name]);
            }

            return $user;
        });
        $this->audit($request, 'account.created', $user, null, ['role' => $data['role']]);

        return $this->success(new UserResource($user), 'Đã tạo tài khoản.', [], 201);
    }

    public function update(Request $request, int $account)
    {
        $user = User::withTrashed()->findOrFail($account);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);
        $old = $user->only(['name', 'email', 'phone']);
        $user->update($data);
        $this->audit($request, 'account.updated', $user, $old, $user->only(array_keys($data)));

        return $this->success(new UserResource($user->fresh()), 'Đã cập nhật tài khoản.');
    }

    public function status(Request $request, int $account)
    {
        $user = User::findOrFail($account);
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'blocked'])],
        ]);
        if ($user->is($request->user()) && $data['status'] === 'blocked') {
            return $this->unsafeSelfChange();
        }
        $old = ['status' => $user->status];
        $user->update(['status' => $data['status']]);
        if ($data['status'] === 'blocked') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        $this->audit($request, 'account.status_updated', $user, $old, ['status' => $data['status']]);

        return $this->success(new UserResource($user->fresh()), $data['status'] === 'blocked' ? 'Đã chặn tài khoản.' : 'Đã mở khóa tài khoản.');
    }

    public function access(Request $request, int $account)
    {
        $user = User::findOrFail($account);
        $data = $request->validate([
            'role' => ['required', Rule::exists('roles', 'name')],
            'granted_permissions' => ['present', 'array'],
            'granted_permissions.*' => [Rule::exists('permissions', 'name')],
            'denied_permissions' => ['present', 'array'],
            'denied_permissions.*' => [Rule::exists('permissions', 'name')],
        ]);
        if ($user->is($request->user()) && $data['role'] !== 'admin') {
            return $this->unsafeSelfChange();
        }
        $overlap = array_intersect($data['granted_permissions'], $data['denied_permissions']);
        if ($overlap) {
            return response()->json(['success' => false, 'message' => 'Một quyền không thể vừa cấp vừa từ chối.', 'code' => 'CONFLICTING_PERMISSIONS'], 422);
        }
        $old = ['roles' => $user->getRoleNames(), 'granted' => $user->getDirectPermissions()->pluck('name'), 'denied' => $user->deniedPermissions()->pluck('name')];
        $user->syncRoles([$data['role']]);
        $user->syncPermissions($data['granted_permissions']);
        $deniedIds = Permission::whereIn('name', $data['denied_permissions'])->pluck('id');
        $user->deniedPermissions()->sync($deniedIds);
        $this->audit($request, 'account.access_updated', $user, $old, $data);
        DB::table('sessions')->where('user_id', $user->id)->where('id', '<>', $request->session()->getId())->delete();

        return $this->success(new UserResource($user->fresh()), 'Đã cập nhật quyền.');
    }

    public function password(Request $request, int $account)
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'confirmed']]);
        $user = User::findOrFail($account);
        $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $this->audit($request, 'account.password_reset', $user);

        return $this->success(null, 'Đã đặt lại mật khẩu.');
    }

    public function destroy(Request $request, int $account)
    {
        $user = User::findOrFail($account);
        if ($user->is($request->user())) {
            return $this->unsafeSelfChange();
        }
        $user->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $this->audit($request, 'account.archived', $user);

        return $this->success(null, 'Đã lưu trữ tài khoản.');
    }

    public function restore(Request $request, int $account)
    {
        $user = User::onlyTrashed()->findOrFail($account);
        $user->restore();
        $this->audit($request, 'account.restored', $user);

        return $this->success(new UserResource($user), 'Đã khôi phục tài khoản.');
    }

    private function unsafeSelfChange()
    {
        return response()->json(['success' => false, 'message' => 'Không thể tự khóa, lưu trữ hoặc gỡ vai trò admin của chính mình.', 'code' => 'LAST_ADMIN_PROTECTED'], 422);
    }

    private function audit(Request $request, string $action, User $subject, mixed $old = null, mixed $new = null): void
    {
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => User::class,
            'subject_id' => $subject->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500),
        ]);
    }
}
