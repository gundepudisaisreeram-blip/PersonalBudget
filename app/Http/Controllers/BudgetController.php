<?php

namespace App\Http\Controllers;

use App\Domain\Services\BudgetService;
use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\UpdateBudgetRequest;
use App\Models\Budget;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * No destroy action exists. Budgets are create-and-edit-only for V1 --
 * hard delete is prohibited (Phase 4 Decision Package section 9).
 */
class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $budgets) {}

    public function index(Request $request): View
    {
        $budgets = $request->user()->budgets()->with('category')->orderByDesc('period_start')->get();

        $rows = $budgets->map(fn (Budget $budget) => [
            'budget' => $budget,
            'utilization' => $this->budgets->calculateUtilization($budget),
        ]);

        return view('budgets.index', compact('rows'));
    }

    public function create(): View
    {
        $this->authorize('create', Budget::class);

        return view('budgets.create', $this->formOptions());
    }

    public function store(StoreBudgetRequest $request): RedirectResponse
    {
        $category = Category::findOrFail($request->integer('category_id'));

        $budget = $this->budgets->createBudget(
            $request->user(),
            $category,
            $request->input('period_start'),
            $request->input('period_end'),
            (string) $request->input('budget_amount'),
            $request->boolean('is_mandatory_reserve'),
        );

        return redirect()->route('budgets.edit', $budget)->with('status', 'Budget created.');
    }

    public function edit(Budget $budget): View
    {
        $this->authorize('update', $budget);

        return view('budgets.edit', [
            'budget' => $budget,
            'utilization' => $this->budgets->calculateUtilization($budget),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateBudgetRequest $request, Budget $budget): RedirectResponse
    {
        $category = Category::findOrFail($request->integer('category_id'));

        $this->budgets->updateBudget(
            $request->user(),
            $budget,
            $category,
            $request->input('period_start'),
            $request->input('period_end'),
            (string) $request->input('budget_amount'),
            $request->boolean('is_mandatory_reserve'),
        );

        return redirect()->route('budgets.edit', $budget)->with('status', 'Budget updated.');
    }

    /**
     * @return array{categories: Collection}
     */
    private function formOptions(): array
    {
        $user = request()->user();

        return [
            'categories' => Category::query()
                ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->id))
                ->orderBy('name')
                ->get(),
        ];
    }
}
