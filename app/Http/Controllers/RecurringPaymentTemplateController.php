<?php

namespace App\Http\Controllers;

use App\Domain\Services\OwnershipGuard;
use App\Http\Requests\StoreRecurringPaymentTemplateRequest;
use App\Http\Requests\UpdateRecurringPaymentTemplateRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringPaymentTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class RecurringPaymentTemplateController extends Controller
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function index(Request $request): View
    {
        $templates = $request->user()->recurringPaymentTemplates()->orderBy('name')->get();

        return view('recurring-templates.index', compact('templates'));
    }

    public function create(): View
    {
        $this->authorize('create', RecurringPaymentTemplate::class);

        return view('recurring-templates.create', $this->formOptions());
    }

    public function store(StoreRecurringPaymentTemplateRequest $request): RedirectResponse
    {
        $category = Category::findOrFail($request->integer('category_id'));
        $this->ownership->assertCategoryOwnership($category, $request->user()->id);

        if ($request->filled('default_account_id')) {
            $account = Account::findOrFail($request->integer('default_account_id'));
            $this->ownership->assertAccountOwnership($account, $request->user()->id);
        }

        $template = $request->user()->recurringPaymentTemplates()->create($request->validated());

        return redirect()->route('recurring-templates.show', $template)->with('status', 'Recurring template created.');
    }

    public function show(RecurringPaymentTemplate $recurringPaymentTemplate): View
    {
        $this->authorize('view', $recurringPaymentTemplate);

        $recurringPaymentTemplate->load(['category', 'defaultAccount']);
        $obligations = $recurringPaymentTemplate->paymentObligations()->orderByDesc('period_start')->get();

        return view('recurring-templates.show', [
            'template' => $recurringPaymentTemplate,
            'obligations' => $obligations,
        ]);
    }

    public function edit(RecurringPaymentTemplate $recurringPaymentTemplate): View
    {
        $this->authorize('update', $recurringPaymentTemplate);

        return view('recurring-templates.edit', [
            'template' => $recurringPaymentTemplate,
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateRecurringPaymentTemplateRequest $request, RecurringPaymentTemplate $recurringPaymentTemplate): RedirectResponse
    {
        $category = Category::findOrFail($request->integer('category_id'));
        $this->ownership->assertCategoryOwnership($category, $request->user()->id);

        if ($request->filled('default_account_id')) {
            $account = Account::findOrFail($request->integer('default_account_id'));
            $this->ownership->assertAccountOwnership($account, $request->user()->id);
        }

        $recurringPaymentTemplate->update($request->validated());

        return redirect()->route('recurring-templates.show', $recurringPaymentTemplate)->with('status', 'Recurring template updated.');
    }

    public function cancel(RecurringPaymentTemplate $recurringPaymentTemplate): RedirectResponse
    {
        $this->authorize('update', $recurringPaymentTemplate);

        $recurringPaymentTemplate->update(['status' => 'CANCELLED']);

        return redirect()->route('recurring-templates.show', $recurringPaymentTemplate)->with('status', 'Recurring template cancelled.');
    }

    /**
     * @return array{categories: Collection, accounts: Collection}
     */
    private function formOptions(): array
    {
        $user = request()->user();

        return [
            'categories' => Category::query()
                ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->id))
                ->orderBy('name')
                ->get(),
            'accounts' => $user->accounts()->orderBy('name')->get(),
        ];
    }
}
