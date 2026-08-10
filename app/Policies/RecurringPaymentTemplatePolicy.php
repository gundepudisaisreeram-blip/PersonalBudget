<?php

namespace App\Policies;

use App\Models\RecurringPaymentTemplate;
use App\Models\User;

class RecurringPaymentTemplatePolicy
{
    public function view(User $user, RecurringPaymentTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, RecurringPaymentTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }
}
