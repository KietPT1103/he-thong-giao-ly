<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-notifications');
    }

    public function view(User $user, Announcement $announcement): bool
    {
        if ($user->can('access-admin') && $user->can('view-notifications')) {
            return true;
        }
        if ($user->teacherProfile && $user->can('send-notifications')) {
            return $this->hasAssignedClass($user, $announcement);
        }

        return $user->can('view-notifications')
            && $announcement->recipients()->where('users.id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('send-notifications') && $user->teacherProfile !== null;
    }

    public function update(User $user, Announcement $announcement): bool
    {
        if (! $user->can('send-notifications') || ! $user->teacherProfile) {
            return false;
        }

        return $announcement->created_by === $user->id
            || $this->hasAssignedClass($user, $announcement, 'primary');
    }

    public function send(User $user, Announcement $announcement): bool
    {
        return $this->update($user, $announcement);
    }

    private function hasAssignedClass(User $user, Announcement $announcement, ?string $role = null): bool
    {
        $classIds = $announcement->targets()->pluck('catechism_class_id');
        $query = $user->teacherProfile->classes()->whereIn('catechism_classes.id', $classIds);
        if ($role) {
            $query->wherePivot('role', $role);
        }

        return $query->exists();
    }
}
