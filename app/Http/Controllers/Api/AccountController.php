<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserAvatar;
use App\Rules\VietnamesePhoneNumber;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

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
            'phone' => ['nullable', 'string', 'max:20', new VietnamesePhoneNumber],
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
        $file = $request->file('avatar');
        $contents = file_get_contents($file->getRealPath());
        abort_if($contents === false, 422, 'Không thể đọc ảnh đại diện.');

        $version = bin2hex(random_bytes(12));
        DB::transaction(function () use ($user, $file, $contents, $version): void {
            UserAvatar::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['mime_type' => $file->getMimeType(), 'data' => base64_encode($contents)],
            );
            $user->update(['avatar_path' => "database:{$version}"]);
        });

        if ($oldPath && ! str_starts_with($oldPath, 'database:')) {
            Storage::disk('public')->delete($oldPath);
        }
        $audit->record($request, 'account.avatar_updated', $user);

        return $this->success(new UserResource($user->fresh()), 'Đã cập nhật ảnh đại diện.');
    }

    public function avatarFile(User $user)
    {
        $avatar = $user->avatarImage()->firstOrFail();
        $contents = $avatar->data;
        if (is_resource($contents)) {
            $contents = stream_get_contents($contents);
        }
        abort_unless(is_string($contents), 404);
        $contents = base64_decode($contents, true);
        abort_unless(is_string($contents), 404);

        return response($contents, 200, [
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'Content-Length' => (string) strlen($contents),
            'Content-Type' => $avatar->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
