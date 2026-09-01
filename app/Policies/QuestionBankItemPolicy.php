<?php

namespace App\Policies;

use App\Models\QuestionBankItem;
use App\Models\User;

class QuestionBankItemPolicy
{
    public function before(User $user): ?bool
    {
        return $user->can('access-admin') ? $user->can('view-assignments') : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view-assignments') && $user->teacherProfile !== null;
    }

    public function view(User $user, QuestionBankItem $question): bool
    {
        if (! $user->can('view-assignments') || ! $user->teacherProfile) {
            return false;
        }

        return $question->owner_id === $user->id
            || ($question->scope === 'parish' && $question->parish_id === $user->teacherProfile->parish_id);
    }

    public function create(User $user): bool
    {
        return $user->can('create-assignments') && $user->teacherProfile !== null;
    }

    public function update(User $user, QuestionBankItem $question): bool
    {
        return $user->can('update-assignments') && $question->owner_id === $user->id;
    }

    public function delete(User $user, QuestionBankItem $question): bool
    {
        return $this->update($user, $question);
    }
}
