<?php

namespace App\Policies;

use App\Models\StatementImport;
use App\Models\User;

class StatementImportPolicy
{
    public function view(User $user, StatementImport $statementImport): bool
    {
        return $statementImport->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }
}
