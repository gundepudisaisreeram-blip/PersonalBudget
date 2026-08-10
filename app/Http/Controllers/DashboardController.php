<?php

namespace App\Http\Controllers;

use App\Domain\Money;
use App\Domain\Services\AccountBalanceService;
use App\Domain\Services\BudgetService;
use App\Domain\Services\SafeToSpendService;
use App\Models\Budget;
use App\Models\PaymentObligation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Thin, read-only dashboard controller (Phase 5 Decision Package v1.2.3).
 * Every financial figure is delegated to SafeToSpendService/BudgetService/
 * AccountBalanceService -- no calculation occurs here.
 */
class DashboardController extends Controller
{
    public function index(
        Request $request,
        SafeToSpendService $safeToSpend,
        BudgetService $budgets,
        AccountBalanceService $accountBalance,
    ): View {
        $user = $request->user();
        $timezone = $user->timezone ?? config('app.timezone');
        $today = Carbon::now($timezone)->startOfDay();
        $todayString = $today->toDateString();

        $snapshot = $safeToSpend->snapshot($user, $today);
        $paidObligations = $safeToSpend->calculatePaidObligations($user, $today);

        $currentBudgets = Budget::query()
            ->where('user_id', $user->id)
            ->where('period_start', '<=', $todayString)
            ->where('period_end', '>=', $todayString)
            ->with('category')
            ->get()
            ->map(function (Budget $budget) use ($budgets) {
                $utilization = $budgets->calculateUtilization($budget);
                $percent = Money::isZero($budget->budget_amount)
                    ? '0.00'
                    : bcmul(bcdiv($utilization, $budget->budget_amount, 6), '100', 2);

                return [
                    'budget' => $budget,
                    'utilization' => $utilization,
                    'remaining' => Money::sub($budget->budget_amount, $utilization),
                    'percent' => $percent,
                    'overspend' => Money::isGreaterThan($utilization, $budget->budget_amount),
                ];
            })
            ->sort(function (array $a, array $b) {
                $byUtilizationDesc = bccomp($b['percent'], $a['percent'], 2);

                return $byUtilizationDesc !== 0
                    ? $byUtilizationDesc
                    : ($a['budget']->category?->name <=> $b['budget']->category?->name);
            })
            ->values();

        $upcomingPayments = PaymentObligation::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['PENDING', 'PARTIALLY_PAID'])
            ->where('due_date', '>=', $todayString)
            ->where('due_date', '<=', $today->copy()->addDays(13)->toDateString())
            ->with(['category', 'plannedAccount'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $overdueObligations = PaymentObligation::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['PENDING', 'PARTIALLY_PAID'])
            ->where('due_date', '<', $todayString)
            ->with('category')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $overspentBudgets = $currentBudgets->filter(fn (array $row) => $row['overspend'])->values();

        $accounts = $user->accounts()
            ->orderBy('name')
            ->get()
            ->map(fn ($account) => [
                'account' => $account,
                'balance' => $accountBalance->calculate($account),
            ]);

        $attentionCenter = $this->buildAttentionCenter(
            $snapshot['safe_to_spend'],
            $overdueObligations,
            $overspentBudgets,
        );

        return view('dashboard.index', [
            'snapshot' => $snapshot,
            'safeToSpendNegative' => Money::compare($snapshot['safe_to_spend'], '0.00') < 0,
            'paidObligations' => $paidObligations,
            'budgets' => $currentBudgets,
            'upcomingPayments' => $upcomingPayments,
            'accounts' => $accounts,
            'attentionCenter' => $attentionCenter,
        ]);
    }

    /**
     * Exactly the three Decision Package v1.2.3 section 13 rules, in the
     * frozen priority order: Negative Safe-to-Spend, Overdue Obligation,
     * Budget Overspend. No other alert type is evaluated.
     *
     * @return array<int, array{type: string, severity: string, priority: int, item: mixed}>
     */
    private function buildAttentionCenter(string $safeToSpend, $overdueObligations, $overspentBudgets): array
    {
        $items = [];

        if (Money::compare($safeToSpend, '0.00') < 0) {
            $items[] = [
                'type' => 'negative_safe_to_spend',
                'severity' => 'critical',
                'priority' => 1,
                'item' => $safeToSpend,
            ];
        }

        foreach ($overdueObligations as $obligation) {
            $items[] = [
                'type' => 'overdue_obligation',
                'severity' => 'high',
                'priority' => 2,
                'item' => $obligation,
            ];
        }

        foreach ($overspentBudgets as $row) {
            $items[] = [
                'type' => 'budget_overspend',
                'severity' => 'medium',
                'priority' => 3,
                'item' => $row,
            ];
        }

        return $items;
    }
}
