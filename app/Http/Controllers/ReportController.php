<?php

namespace App\Http\Controllers;

use App\Domain\Services\BudgetService;
use App\Domain\Services\ReportingService;
use App\Http\Requests\Reports\BudgetReportRequest;
use App\Http\Requests\Reports\CashFlowReportRequest;
use App\Http\Requests\Reports\CategorySpendingReportRequest;
use App\Http\Requests\Reports\MonthlyPeriodReportRequest;
use App\Http\Requests\Reports\ObligationsReportRequest;
use App\Http\Requests\Reports\TrendsReportRequest;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Thin, strictly read-only report controllers (PHASE_6_DECISION_PACKAGE.md
 * v1.4.0). Every financial figure is delegated to ReportingService /
 * BudgetService -- no calculation occurs here. No create/update/delete
 * action exists anywhere in this controller.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportingService $reports) {}

    public function index(): View
    {
        return view('reports.index');
    }

    public function cashFlow(CashFlowReportRequest $request): View
    {
        $user = $request->user();
        $timezone = $user->timezone ?? config('app.timezone');
        [$start, $end] = $this->reports->resolveDateRange(...$request->dateInputs(), timezone: $timezone);
        $accounts = $this->reports->resolveAccountScope($user, $request->accountIdInputs());

        $cashFlow = $this->reports->cashFlow($user, $accounts, $start, $end);

        return view('reports.cash-flow', [
            'cashFlow' => $cashFlow,
            'start' => $start,
            'end' => $end,
            'accounts' => $user->accounts()->orderBy('name')->get(),
            'selectedAccountIds' => $accounts->keys()->all(),
        ]);
    }

    public function categorySpending(CategorySpendingReportRequest $request): View
    {
        $user = $request->user();
        $timezone = $user->timezone ?? config('app.timezone');
        [$start, $end] = $this->reports->resolveDateRange(...$request->dateInputs(), timezone: $timezone);
        $accounts = $this->reports->resolveAccountScope($user, $request->accountIdInputs());

        $categories = $this->reports->categorySpending($user, $accounts, $start, $end);

        return view('reports.category-spending', [
            'categories' => $categories,
            'start' => $start,
            'end' => $end,
            'accounts' => $user->accounts()->orderBy('name')->get(),
            'selectedAccountIds' => $accounts->keys()->all(),
        ]);
    }

    public function budget(BudgetReportRequest $request, BudgetService $budgets): View
    {
        $user = $request->user();
        $timezone = $user->timezone ?? config('app.timezone');
        [$start, $end] = $this->reports->resolveDateRange(...$request->dateInputs(), timezone: $timezone);

        $categoryId = $request->integer('category_id') ?: null;
        if ($categoryId !== null) {
            $category = Category::findOrFail($categoryId);
            $this->authorizeCategoryOwnership($category, $user->id);
        }

        $rows = $this->reports->budgetReport($user, $budgets, $start, $end, $categoryId);

        return view('reports.budget', [
            'rows' => $rows,
            'start' => $start,
            'end' => $end,
            'categories' => $this->userCategories($user),
        ]);
    }

    public function obligations(ObligationsReportRequest $request): View
    {
        $user = $request->user();
        $timezone = $user->timezone ?? config('app.timezone');
        [$start, $end] = $this->reports->resolveDateRange(...$request->dateInputs(), timezone: $timezone);

        $categoryId = $request->integer('category_id') ?: null;
        if ($categoryId !== null) {
            $category = Category::findOrFail($categoryId);
            $this->authorizeCategoryOwnership($category, $user->id);
        }

        $rows = $this->reports->obligationsReport($user, $start, $end, $categoryId, $request->string('status')->value() ?: null);

        return view('reports.obligations', [
            'rows' => $rows,
            'start' => $start,
            'end' => $end,
            'categories' => $this->userCategories($user),
        ]);
    }

    public function trends(TrendsReportRequest $request): View
    {
        $user = $request->user();
        $timezone = $user->timezone ?? config('app.timezone');
        [$start, $end] = $this->reports->resolveDateRange(...$request->dateInputs(), timezone: $timezone);
        $accounts = $this->reports->resolveAccountScope($user, $request->accountIdInputs());

        $months = $this->reports->trends($user, $accounts, $start, $end);

        return view('reports.trends', [
            'months' => $months,
            'start' => $start,
            'end' => $end,
            'accounts' => $user->accounts()->orderBy('name')->get(),
            'selectedAccountIds' => $accounts->keys()->all(),
        ]);
    }

    public function monthlySummary(MonthlyPeriodReportRequest $request, BudgetService $budgets): View
    {
        [$user, $accounts, $monthStart, $monthEnd, $month] = $this->resolveMonthlyPeriod($request);

        return view('reports.monthly-summary', [
            'review' => $this->reports->monthReview($user, $budgets, $accounts, $monthStart, $monthEnd),
            'month' => $month,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
        ]);
    }

    public function monthReview(MonthlyPeriodReportRequest $request, BudgetService $budgets): View
    {
        [$user, $accounts, $monthStart, $monthEnd, $month] = $this->resolveMonthlyPeriod($request);

        return view('reports.month-review', [
            'review' => $this->reports->monthReview($user, $budgets, $accounts, $monthStart, $monthEnd),
            'month' => $month,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
        ]);
    }

    /**
     * @return array{0: User, 1: Collection, 2: Carbon, 3: Carbon, 4: string}
     */
    private function resolveMonthlyPeriod(MonthlyPeriodReportRequest $request): array
    {
        $user = $request->user();
        $timezone = $user->timezone ?? config('app.timezone');
        $month = $request->resolveMonth();

        $monthStart = Carbon::createFromFormat('Y-m-d', $month.'-01', $timezone)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $accounts = $this->reports->resolveAccountScope($user, $request->accountIdInputs());

        return [$user, $accounts, $monthStart, $monthEnd, $month];
    }

    private function userCategories(User $user)
    {
        return Category::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->id))
            ->orderBy('name')
            ->get();
    }

    private function authorizeCategoryOwnership(Category $category, int $userId): void
    {
        if (! $category->ownedBy($userId)) {
            abort(403);
        }
    }
}
