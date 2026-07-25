<?php

namespace App\Policies;

use App\Models\Child;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChildPolicy
{
    public function before(User $user, string $ability): bool|null { return $user->hasPermissionTo('manage-users') ? true : null; }
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-children');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Child $child): bool
    {
        return ($user->can('view-children') && $user->teacherProfile?->classes()->whereHas('enrollments',fn($q)=>$q->where('child_id',$child->id))->exists()) || $user->parentProfile?->children()->whereKey($child)->exists() || $user->child?->is($child);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create-children');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Child $child): bool
    {
        return $user->can('update-children');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Child $child): bool
    {
        return $user->can('delete-children');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Child $child): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Child $child): bool
    {
        return false;
    }
}
