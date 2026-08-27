<?php

namespace App\Policies;

use App\Models\CatechismClass;
use App\Models\User;

class CatechismClassPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-classes');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CatechismClass $catechismClass): bool
    {
        if ($user->can('access-admin')) {
            return $user->can('view-classes');
        }

        return $user->can('view-classes')
            && $this->hasTeacherAssignment($user, $catechismClass);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create-classes')
            && ($user->can('access-admin') || $user->teacherProfile !== null);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CatechismClass $catechismClass): bool
    {
        if ($user->can('access-admin')) {
            return $user->can('update-classes');
        }

        return $user->can('update-classes')
            && $this->hasTeacherAssignment($user, $catechismClass, 'primary');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CatechismClass $catechismClass): bool
    {
        if ($user->can('access-admin')) {
            return $user->can('delete-classes');
        }

        return $user->can('delete-classes')
            && $this->hasTeacherAssignment($user, $catechismClass, 'primary');
    }

    public function takeAttendance(User $user, CatechismClass $catechismClass): bool
    {
        return $user->can('create-attendance-session')
            && $this->hasTeacherAssignment($user, $catechismClass);
    }

    public function manageEnrollments(User $user, CatechismClass $catechismClass): bool
    {
        if ($user->can('access-admin')) {
            return $user->can('enroll-children');
        }

        return $user->can('enroll-children')
            && $this->hasTeacherAssignment($user, $catechismClass, 'primary');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CatechismClass $catechismClass): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CatechismClass $catechismClass): bool
    {
        return false;
    }

    private function hasTeacherAssignment(
        User $user,
        CatechismClass $catechismClass,
        ?string $role = null,
    ): bool {
        $teacher = $user->teacherProfile;
        if (! $teacher) {
            return false;
        }
        if ($catechismClass->relationLoaded('teachers')) {
            $assignment = $catechismClass->teachers->firstWhere('id', $teacher->id);

            return $assignment !== null
                && ($role === null || $assignment->pivot->role === $role);
        }

        $query = $teacher->classes()->whereKey($catechismClass->id);
        if ($role !== null) {
            $query->wherePivot('role', $role);
        }

        return $query->exists();
    }
}
