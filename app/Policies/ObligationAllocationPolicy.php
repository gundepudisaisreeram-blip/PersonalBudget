<?php

namespace App\Policies;

use App\Models\ObligationAllocation;
use App\Models\User;

class ObligationAllocationPolicy
{
    public function delete(User $user, ObligationAllocation $allocation): bool
    {
        return $allocation->user_id === $user->id;
    }
}
