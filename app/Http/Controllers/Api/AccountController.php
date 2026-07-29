<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use App\Services\AuditLogger;

class AccountController extends ApiController
{
    public function show(Request $request)
    {
        return $this->success(new UserResource($request->user()));
    }

    public function update(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($request->user()->id)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);
        $old = $request->user()->only(['name', 'email', 'phone']);
        $request->user()->update($data);
        $audit->record($request, 'account.profile_updated', $request->user(), $old, $data);

        return $this->success(new UserResource($request->user()->fresh()), 'Đã cập nhật tài khoản.');
    }

    public function avatar(Request $request, AuditLogger $audit)
    {
        $request->validate([
            'avatar' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(2048)],
        ]);
        $user = $request->user();
        $oldPath = $user->avatar_path;
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);
        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }
        $audit->record($request, 'account.avatar_updated', $user);

        return $this->success(new UserResource($user->fresh()), 'Đã cập nhật ảnh đại diện.');
    }
}
