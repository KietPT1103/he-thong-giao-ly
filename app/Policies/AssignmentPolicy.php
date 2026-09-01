<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;

class AssignmentPolicy
{
    public function before(User $user): ?bool
    {
        return $user->can('access-admin') ? $user->can('view-assignments') : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view-assignments');
    }

    public function view(User $user, Assignment $assignment): bool
    {
        if (! $user->can('view-assignments')) {
            return false;
        }
        if ($user->teacherProfile) {
            return $this->hasAssignedClass($user, $assignment);
        }
        if ($user->child) {
            return $assignment->recipients()->where('child_id', $user->child->id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('create-assignments') && $user->teacherProfile !== null;
    }

    public function update(User $user, Assignment $assignment): bool
    {
        if (! $user->can('update-assignments') || ! $user->teacherProfile) {
            return false;
        }

        return $assignment->created_by === $user->id || $this->hasAssignedClass($user, $assignment, 'primary');
    }

    public function grade(User $user, Assignment $assignment): bool
    {
        return $user->can('grade-assignments')
            && $user->teacherProfile !== null
            && $this->hasAssignedClass($user, $assignment);
    }

    public function submit(User $user, Assignment $assignment): bool
    {
        return $user->can('submit-assignments')
            && $user->child !== null
            && $assignment->recipients()->where('child_id', $user->child->id)->exists();
    }

    public function archive(User $user, Assignment $assignment): bool
    {
        return $user->can('archive-assignments') && $this->update($user, $assignment);
    }

    private function hasAssignedClass(User $user, Assignment $assignment, ?string $role = null): bool
    {
        $classIds = $assignment->targets()->pluck('catechism_class_id');
        $query = $user->teacherProfile->classes()->whereIn('catechism_classes.id', $classIds);
        if ($role) {
            $query->wherePivot('role', $role);
        }

        return $query->exists();
    }
}
