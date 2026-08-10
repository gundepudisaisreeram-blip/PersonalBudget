<?php

namespace App\Http\Controllers;

use App\Domain\Services\RefundService;
use App\Domain\Services\ReversalService;
use App\Domain\Services\TransactionService;
use App\Domain\Services\TransferService;
use App\Http\Requests\StoreAdjustmentRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\StoreIncomeRequest;
use App\Http\Requests\StoreRefundRequest;
use App\Http\Requests\StoreReversalRequest;
use App\Http\Requests\StoreTransferRequest;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $transactions = $request->user()->transactions()
            ->with(['category', 'ledgerEntries.account', 'parentTransaction'])
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('transaction_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('transaction_date', '<=', $request->date('date_to')))
            ->when($request->filled('account_id'), fn ($query) => $query->whereHas(
                'ledgerEntries',
                fn ($entries) => $entries->where('account_id', $request->integer('account_id')),
            ))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('transaction_type'), fn ($query) => $query->where('transaction_type', $request->string('transaction_type')))
            ->when($request->filled('amount'), fn ($query) => $query->whereHas(
                'ledgerEntries',
                fn ($entries) => $entries->where('amount', $request->string('amount')),
            ))
            ->when($request->filled('description'), fn ($query) => $query->where('description', 'like', '%'.$request->string('description').'%'))
            ->when($request->filled('source'), fn ($query) => $query->where('source', $request->string('source')))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $accounts = $request->user()->accounts()->orderBy('name')->get();
        $categories = Category::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->orderBy('name')
            ->get();

        return view('transactions.index', compact('transactions', 'accounts', 'categories'));
    }

    public function show(Transaction $transaction): View
    {
        $this->authorize('view', $transaction);

        $transaction->load(['category', 'ledgerEntries.account', 'parentTransaction', 'childTransactions']);

        $auditLog = $transaction->transaction_type === 'ADJUSTMENT'
            ? AuditLog::where('entity_type', Transaction::class)->where('entity_id', $transaction->id)->first()
            : null;

        return view('transactions.show', compact('transaction', 'auditLog'));
    }

    public function create(): View
    {
        return view('transactions.create');
    }

    public function createExpense(Request $request): View
    {
        return view('transactions.expense-create', $this->accountAndCategoryOptions($request));
    }

    public function storeExpense(StoreExpenseRequest $request, TransactionService $transactions): RedirectResponse
    {
        $account = Account::findOrFail($request->integer('account_id'));
        $category = $request->filled('category_id') ? Category::findOrFail($request->integer('category_id')) : null;

        $transaction = $transactions->recordExpense(
            $request->user(),
            $account,
            (string) $request->input('amount'),
            $request->input('transaction_date'),
            $request->input('description'),
            $category,
            $request->input('reference'),
            'MANUAL',
            $request->input('notes'),
        );

        return redirect()->route('transactions.show', $transaction)->with('status', 'Expense recorded.');
    }

    public function createIncome(Request $request): View
    {
        return view('transactions.income-create', $this->accountAndCategoryOptions($request));
    }

    public function storeIncome(StoreIncomeRequest $request, TransactionService $transactions): RedirectResponse
    {
        $account = Account::findOrFail($request->integer('account_id'));
        $category = $request->filled('category_id') ? Category::findOrFail($request->integer('category_id')) : null;

        $transaction = $transactions->recordIncome(
            $request->user(),
            $account,
            (string) $request->input('amount'),
            $request->input('transaction_date'),
            $request->input('description'),
            $category,
            $request->input('reference'),
            'MANUAL',
            $request->input('notes'),
        );

        return redirect()->route('transactions.show', $transaction)->with('status', 'Income recorded.');
    }

    public function createTransfer(Request $request): View
    {
        return view('transactions.transfer-create', $this->accountAndCategoryOptions($request));
    }

    public function storeTransfer(StoreTransferRequest $request, TransferService $transfers): RedirectResponse
    {
        $fromAccount = Account::findOrFail($request->integer('from_account_id'));
        $toAccount = Account::findOrFail($request->integer('to_account_id'));
        $category = $request->filled('category_id') ? Category::findOrFail($request->integer('category_id')) : null;

        $transaction = $transfers->transfer(
            $request->user(),
            $fromAccount,
            $toAccount,
            (string) $request->input('amount'),
            $request->input('transaction_date'),
            $request->input('description'),
            $category,
            $request->input('reference'),
            'MANUAL',
            $request->input('notes'),
        );

        return redirect()->route('transactions.show', $transaction)->with('status', 'Transfer recorded.');
    }

    public function createRefund(Request $request): View
    {
        $data = $this->accountAndCategoryOptions($request);
        $data['parentOptions'] = $request->user()->transactions()
            ->where('transaction_type', 'EXPENSE')
            ->orderByDesc('transaction_date')
            ->get();

        return view('transactions.refund-create', $data);
    }

    public function storeRefund(StoreRefundRequest $request, RefundService $refunds): RedirectResponse
    {
        $parent = $request->user()->transactions()->findOrFail($request->integer('parent_transaction_id'));
        $account = Account::findOrFail($request->integer('account_id'));

        $transaction = $refunds->refund(
            $request->user(),
            $parent,
            $account,
            (string) $request->input('amount'),
            $request->input('transaction_date'),
            $request->input('description'),
            $request->input('reference'),
            $request->input('notes'),
        );

        return redirect()->route('transactions.show', $transaction)->with('status', 'Refund recorded.');
    }

    public function createReversal(Request $request): View
    {
        $data = $this->accountAndCategoryOptions($request);
        $data['parentOptions'] = $request->user()->transactions()
            ->whereHas('ledgerEntries')
            ->orderByDesc('transaction_date')
            ->get();

        return view('transactions.reversal-create', $data);
    }

    public function storeReversal(StoreReversalRequest $request, ReversalService $reversals): RedirectResponse
    {
        $parent = $request->user()->transactions()->findOrFail($request->integer('parent_transaction_id'));

        $transaction = $reversals->reverse(
            $request->user(),
            $parent,
            $request->input('transaction_date'),
            $request->input('description'),
            $request->input('reference'),
            $request->input('notes'),
        );

        return redirect()->route('transactions.show', $transaction)->with('status', 'Reversal recorded.');
    }

    public function createAdjustment(Request $request): View
    {
        return view('transactions.adjustment-create', $this->accountAndCategoryOptions($request));
    }

    public function storeAdjustment(StoreAdjustmentRequest $request, TransactionService $transactions): RedirectResponse
    {
        $account = Account::findOrFail($request->integer('account_id'));
        $category = $request->filled('category_id') ? Category::findOrFail($request->integer('category_id')) : null;

        $transaction = $transactions->recordAdjustment(
            $request->user(),
            $account,
            $request->input('direction'),
            (string) $request->input('amount'),
            $request->input('transaction_date'),
            $request->input('description'),
            $request->input('reason'),
            $category,
            $request->input('reference'),
        );

        return redirect()->route('transactions.show', $transaction)->with('status', 'Adjustment recorded.');
    }

    /**
     * @return array{accounts: Collection, categories: Collection}
     */
    private function accountAndCategoryOptions(Request $request): array
    {
        return [
            'accounts' => $request->user()->accounts()->orderBy('name')->get(),
            'categories' => Category::query()
                ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
                ->orderBy('name')
                ->get(),
        ];
    }
}
