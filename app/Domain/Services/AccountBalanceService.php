<?php

namespace App\Domain\Services;

use App\Domain\Money;
use App\Models\Account;
use App\Models\LedgerEntry;

/**
 * Derives an account's authoritative balance from its opening balance plus
 * posted ledger activity. A cached balance may exist for performance but is
 * never an independent source of truth (00 section 2.2, BR-009).
 */
class AccountBalanceService
{
    public function calculate(Account $account): string
    {
        $inflow = (string) LedgerEntry::query()
            ->where('account_id', $account->id)
            ->where('direction', 'INFLOW')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->value('total');

        $outflow = (string) LedgerEntry::query()
            ->where('account_id', $account->id)
            ->where('direction', 'OUTFLOW')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->value('total');

        $netInflow = Money::sub($inflow, $outflow);

        return $account->isAsset()
            ? Money::add($account->opening_balance, $netInflow)
            : Money::sub($account->opening_balance, $netInflow);
    }
}
