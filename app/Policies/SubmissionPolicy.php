<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    public function before(User $user): ?bool
    {
        return $user->can('access-admin') ? $user->can('view-assignments') : null;
    }

    public function view(User $user, Submission $submission): bool
    {
        if ($user->child) {
            return $submission->child_id === $user->child->id;
        }

        return $user->can('grade-assignments')
            && $user->teacherProfile !== null
            && $user->can('view', $submission->assignment);
    }

    public function update(User $user, Submission $submission): bool
    {
        return $user->can('submit-assignments')
            && $user->child !== null
            && $submission->child_id === $user->child->id;
    }

    public function grade(User $user, Submission $submission): bool
    {
        return $user->can('grade', $submission->assignment);
    }
}
