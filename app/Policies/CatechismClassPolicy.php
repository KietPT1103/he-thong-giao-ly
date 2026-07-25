<?php

namespace App\Policies;

use App\Models\CatechismClass;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CatechismClassPolicy
{
    public function before(User $user, string $ability): bool|null { return $user->hasPermissionTo('manage-users') ? true : null; }
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
        return $user->can('view-classes') && $user->teacherProfile?->classes()->whereKey($catechismClass)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create-classes');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CatechismClass $catechismClass): bool
    {
        return $user->can('update-classes');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CatechismClass $catechismClass): bool
    {
        return $user->can('delete-classes');
    }

    public function takeAttendance(User $user, CatechismClass $catechismClass): bool
    {
        return $user->can('create-attendance-session') && $user->teacherProfile?->classes()->whereKey($catechismClass)->exists();
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
}
