<?php

namespace App\Policies;

use App\Models\PaymentObligation;
use App\Models\User;

class PaymentObligationPolicy
{
    public function view(User $user, PaymentObligation $obligation): bool
    {
        return $obligation->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PaymentObligation $obligation): bool
    {
        return $obligation->user_id === $user->id;
    }
}
