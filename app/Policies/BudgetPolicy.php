<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

/**
 * No delete ability exists on this policy. Budgets are create-and-edit-only
 * for V1 -- hard delete is prohibited (Phase 4 Decision Package section 9).
 */
class BudgetPolicy
{
    public function view(User $user, Budget $budget): bool
    {
        return $budget->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Budget $budget): bool
    {
        return $budget->user_id === $user->id;
    }
}
