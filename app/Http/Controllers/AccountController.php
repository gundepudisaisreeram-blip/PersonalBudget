<?php

namespace App\Http\Controllers;

use App\Domain\Services\AccountBalanceService;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(private readonly AccountBalanceService $balances) {}

    public function index(Request $request): View
    {
        $accounts = $request->user()->accounts()
            ->when($request->filled('account_type'), fn ($query) => $query->where('account_type', $request->string('account_type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account) => [
                'account' => $account,
                'balance' => $this->balances->calculate($account),
            ]);

        return view('accounts.index', ['rows' => $accounts]);
    }

    public function create(): View
    {
        $this->authorize('create', Account::class);

        return view('accounts.create');
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $account = $request->user()->accounts()->create($request->validated());

        return redirect()->route('accounts.show', $account)->with('status', 'Account created.');
    }

    public function show(Account $account): View
    {
        $this->authorize('view', $account);

        return view('accounts.show', [
            'account' => $account,
            'balance' => $this->balances->calculate($account),
        ]);
    }

    public function edit(Account $account): View
    {
        $this->authorize('update', $account);

        return view('accounts.edit', ['account' => $account]);
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $data = $request->validated();

        if ($account->status === 'CLOSED') {
            $data = array_intersect_key($data, array_flip(['name', 'institution', 'subtype', 'notes']));
        }

        $account->update($data);

        return redirect()->route('accounts.show', $account)->with('status', 'Account updated.');
    }

    public function close(Account $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $account->update(['status' => 'CLOSED']);

        return redirect()->route('accounts.show', $account)->with('status', 'Account closed.');
    }
}
