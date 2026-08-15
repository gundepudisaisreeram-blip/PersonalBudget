<?php

namespace App\Domain\Services;

use App\Domain\Exceptions\OwnershipViolationException;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\ObligationAllocation;
use App\Models\PaymentObligation;
use App\Models\RecurringPaymentTemplate;
use App\Models\StatementImport;
use App\Models\Transaction;

/**
 * Centralized tenant/ownership enforcement.
 *
 * A valid numeric ID alone is never sufficient authorization. Categories are
 * the only intentional exception: user_id IS NULL represents a system
 * category available to every user.
 */
class OwnershipGuard
{
    public function assertAccountOwnership(Account $account, int $userId): void
    {
        if ($account->user_id !== $userId) {
            throw new OwnershipViolationException('Account does not belong to the authenticated user.');
        }
    }

    public function assertCategoryOwnership(?Category $category, int $userId): void
    {
        if ($category === null) {
            return;
        }

        if (! $category->ownedBy($userId)) {
            throw new OwnershipViolationException('Category is neither a system category nor owned by the authenticated user.');
        }
    }

    public function assertTransactionOwnership(Transaction $transaction, int $userId): void
    {
        if ($transaction->user_id !== $userId) {
            throw new OwnershipViolationException('Transaction does not belong to the authenticated user.');
        }
    }

    public function assertPaymentObligationOwnership(PaymentObligation $obligation, int $userId): void
    {
        if ($obligation->user_id !== $userId) {
            throw new OwnershipViolationException('Payment obligation does not belong to the authenticated user.');
        }
    }

    public function assertRecurringTemplateOwnership(RecurringPaymentTemplate $template, int $userId): void
    {
        if ($template->user_id !== $userId) {
            throw new OwnershipViolationException('Recurring payment template does not belong to the authenticated user.');
        }
    }

    public function assertObligationAllocationOwnership(ObligationAllocation $allocation, int $userId): void
    {
        if ($allocation->user_id !== $userId) {
            throw new OwnershipViolationException('Obligation allocation does not belong to the authenticated user.');
        }
    }

    public function assertBudgetOwnership(Budget $budget, int $userId): void
    {
        if ($budget->user_id !== $userId) {
            throw new OwnershipViolationException('Budget does not belong to the authenticated user.');
        }
    }

    public function assertStatementImportOwnership(StatementImport $statementImport, int $userId): void
    {
        if ($statementImport->user_id !== $userId) {
            throw new OwnershipViolationException('Statement import does not belong to the authenticated user.');
        }
    }
}
