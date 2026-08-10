<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * System categories (user_id IS NULL) are read-only for every user.
     */
    public function update(User $user, Category $category): bool
    {
        return ! $category->isSystem() && $category->user_id === $user->id;
    }
}
