<?php

namespace App\Http\Controllers;

use App\Domain\Services\MonthlyGenerationService;
use App\Domain\Services\ObligationAllocationService;
use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\PaymentObligationService;
use App\Http\Requests\StoreOneTimeObligationRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentObligation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PaymentObligationController extends Controller
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function index(Request $request): View
    {
        $obligations = $request->user()->paymentObligations()
            ->with(['category', 'plannedAccount'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('period_start'), fn ($query) => $query->where('period_start', $request->string('period_start')))
            ->orderByDesc('due_date')
            ->paginate(20)
            ->withQueryString();

        $categories = Category::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->orderBy('name')
            ->get();

        return view('obligations.index', compact('obligations', 'categories'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', PaymentObligation::class);

        $categories = Category::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->orderBy('name')
            ->get();
        $accounts = $request->user()->accounts()->orderBy('name')->get();

        return view('obligations.create', compact('categories', 'accounts'));
    }

    public function store(StoreOneTimeObligationRequest $request, PaymentObligationService $obligations): RedirectResponse
    {
        $category = Category::findOrFail($request->integer('category_id'));

        // createOneTime() asserts category ownership internally, but accepts
        // planned_account_id as a raw nullable int with no ownership check of
        // its own -- verified against the certified, unmodified Phase 1
        // service. Guarded here at the HTTP layer instead.
        $plannedAccountId = $request->integer('planned_account_id') ?: null;
        if ($plannedAccountId !== null) {
            $this->ownership->assertAccountOwnership(Account::findOrFail($plannedAccountId), $request->user()->id);
        }

        $obligation = $obligations->createOneTime(
            $request->user(),
            (string) Str::uuid(),
            $category,
            $request->input('period_start'),
            $request->input('period_end'),
            $request->input('due_date'),
            (string) $request->input('planned_amount'),
            $request->boolean('is_mandatory', true),
            $plannedAccountId,
        );

        return redirect()->route('obligations.show', $obligation)->with('status', 'Payment obligation created.');
    }

    public function show(PaymentObligation $paymentObligation): View
    {
        $this->authorize('view', $paymentObligation);

        $paymentObligation->load(['category', 'plannedAccount', 'recurringPaymentTemplate']);
        $allocations = $paymentObligation->obligationAllocations()->with('transaction')->get();

        return view('obligations.show', [
            'obligation' => $paymentObligation,
            'allocations' => $allocations,
        ]);
    }

    public function skip(Request $request, PaymentObligation $paymentObligation, ObligationAllocationService $allocations): RedirectResponse
    {
        $this->authorize('update', $paymentObligation);

        $allocations->skip($request->user(), $paymentObligation);

        return redirect()->route('obligations.show', $paymentObligation)->with('status', 'Payment obligation skipped.');
    }

    public function cancel(Request $request, PaymentObligation $paymentObligation, ObligationAllocationService $allocations): RedirectResponse
    {
        $this->authorize('update', $paymentObligation);

        $allocations->cancel($request->user(), $paymentObligation);

        return redirect()->route('obligations.show', $paymentObligation)->with('status', 'Payment obligation cancelled.');
    }

    public function generate(Request $request, MonthlyGenerationService $generator): RedirectResponse
    {
        $generator->generate($request->user(), $request->filled('period') ? $request->string('period')->toString() : null);

        return redirect()->route('obligations.index')->with('status', 'Recurring obligations generated.');
    }
}
