<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserStatus;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Child;
use App\Models\ParentProfile;
use App\Models\Parish;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends ApiController
{
    public function register(RegisterRequest $request, AuditLogger $audit)
    {
        $data = $request->validated();
        $parishId = Parish::query()->value('id');

        if (! $parishId) {
            return response()->json([
                'success' => false,
                'message' => 'Hệ thống chưa được cấu hình giáo xứ. Vui lòng liên hệ quản trị viên.',
                'code' => 'PARISH_CONFIGURATION_REQUIRED',
            ], 409);
        }

        $user = DB::transaction(function () use ($data, $parishId): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
            ]);

            $user->assignRole($data['role']);

            if ($data['role'] === 'parent') {
                ParentProfile::create([
                    'user_id' => $user->id,
                    'parish_id' => $parishId,
                    'phone' => $data['phone'],
                ]);
            } else {
                Child::create([
                    'user_id' => $user->id,
                    'parish_id' => $parishId,
                    'code' => 'TN-U'.$user->id,
                    'full_name' => $user->name,
                ]);
            }

            return $user;
        });

        Auth::login($user, false);
        $request->session()->regenerate();
        $request->session()->put('auth.login_at', now()->timestamp);
        $user->update(['last_login_at' => now()]);
        $audit->record($request, 'auth.registered', $user, null, ['role' => $data['role']]);

        return $this->success(new UserResource($user), 'Đăng ký tài khoản thành công', status: 201);
    }

    public function login(LoginRequest $request, AuditLogger $audit)
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            $audit->record($request, 'auth.login_failed', $user, null, ['email' => $request->string('email')->toString()]);

            return response()->json(['success' => false, 'message' => 'Email hoặc mật khẩu không đúng.'], 422);
        }

        if ($user->status === UserStatus::Blocked->value) {
            $audit->record($request, 'auth.login_blocked', $user);

            return response()->json(['success' => false, 'message' => 'Tài khoản đã bị khóa.'], 403);
        }

        Auth::login($user, false);
        $request->session()->regenerate();
        $request->session()->put('auth.login_at', now()->timestamp);
        $user->update(['last_login_at' => now()]);
        $audit->record($request, 'auth.login_succeeded', $user);

        return $this->success(new UserResource($user), 'Đăng nhập thành công');
    }

    public function logout(Request $request, AuditLogger $audit)
    {
        $user = $request->user();
        $audit->record($request, 'auth.logout', $user);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->success(null, 'Đã đăng xuất');
    }

    public function me(Request $request)
    {
        return $this->success(new UserResource($request->user()));
    }

    public function confirmPassword(Request $request, AuditLogger $audit)
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->string('password'), $request->user()->password)) {
            $audit->record($request, 'auth.password_confirmation_failed', $request->user());

            return response()->json(['success' => false, 'message' => 'Mật khẩu không đúng.'], 422);
        }

        $request->session()->put('auth.password_confirmed_at', now()->timestamp);

        return $this->success(null, 'Đã xác nhận mật khẩu');
    }

    public function forgot(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? $this->success(null, __($status))
            : response()->json(['success' => false, 'message' => __($status)], 422);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
            },
        );

        return $status === Password::PASSWORD_RESET
            ? $this->success(null, 'Đã đặt lại mật khẩu')
            : response()->json(['success' => false, 'message' => __($status)], 422);
    }

    public function change(Request $request, AuditLogger $audit)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        if (! Hash::check($request->string('current_password'), $request->user()->password)) {
            return response()->json(['success' => false, 'message' => 'Mật khẩu hiện tại không đúng.'], 422);
        }

        DB::transaction(function () use ($request): void {
            $request->user()->forceFill([
                'password' => $request->string('password'),
                'remember_token' => Str::random(60),
                'must_change_password' => false,
            ])->save();

            DB::table('sessions')
                ->where('user_id', $request->user()->id)
                ->where('id', '<>', $request->session()->getId())
                ->delete();
        });

        $request->session()->put('auth.password_confirmed_at', now()->timestamp);
        $audit->record($request, 'auth.password_changed', $request->user());

        return $this->success(null, 'Đã đổi mật khẩu');
    }

    public function sessions(Request $request)
    {
        return $this->success(DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']));
    }

    public function destroySession(Request $request, string $session, AuditLogger $audit)
    {
        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', $session)
            ->delete();
        $audit->record($request, 'auth.session_revoked', $request->user());

        return $this->success(null, 'Đã xóa phiên');
    }

    public function destroyOtherSessions(Request $request, AuditLogger $audit)
    {
        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '<>', $request->session()->getId())
            ->delete();
        $audit->record($request, 'auth.other_sessions_revoked', $request->user());

        return $this->success(null, 'Đã xóa các phiên khác');
    }
}
