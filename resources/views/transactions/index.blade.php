@extends('layouts.app')

@section('title', 'Transactions — Personal Budget Manager')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Transactions</h1>
        <a href="{{ route('transactions.create') }}" class="btn btn-primary">Add transaction</a>
    </div>

    <form method="GET" action="{{ route('transactions.index') }}" class="row g-2 mb-4">
        <div class="col-6 col-md-2">
            <label for="date_from" class="form-label small mb-1">From</label>
            <input type="date" id="date_from" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
        </div>
        <div class="col-6 col-md-2">
            <label for="date_to" class="form-label small mb-1">To</label>
            <input type="date" id="date_to" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
        </div>
        <div class="col-6 col-md-2">
            <label for="account_id" class="form-label small mb-1">Account</label>
            <select id="account_id" name="account_id" class="form-select form-select-sm">
                <option value="">All accounts</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected(request('account_id') == $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="category_id" class="form-label small mb-1">Category</label>
            <select id="category_id" name="category_id" class="form-select form-select-sm">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="transaction_type" class="form-label small mb-1">Type</label>
            <select id="transaction_type" name="transaction_type" class="form-select form-select-sm">
                <option value="">All types</option>
                @foreach (['EXPENSE', 'INCOME', 'TRANSFER', 'REFUND', 'REVERSAL', 'ADJUSTMENT'] as $type)
                    <option value="{{ $type }}" @selected(request('transaction_type') === $type)>{{ ucfirst(strtolower($type)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="amount" class="form-label small mb-1">Amount</label>
            <input type="number" step="0.01" id="amount" name="amount" class="form-control form-control-sm" value="{{ request('amount') }}">
        </div>
        <div class="col-12 col-md-4">
            <label for="description" class="form-label small mb-1">Description</label>
            <input type="text" id="description" name="description" class="form-control form-control-sm" value="{{ request('description') }}">
        </div>
        <div class="col-6 col-md-2">
            <label for="source" class="form-label small mb-1">Source</label>
            <select id="source" name="source" class="form-select form-select-sm">
                <option value="">All sources</option>
                @foreach (['MANUAL', 'BANK_IMPORT', 'ADJUSTMENT', 'SYSTEM'] as $source)
                    <option value="{{ $source }}" @selected(request('source') === $source)>{{ ucfirst(strtolower($source)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-sm btn-outline-primary w-100">Filter</button>
        </div>
    </form>

    @if ($transactions->isEmpty())
        <div class="alert alert-secondary">No transactions for this period.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle bg-white">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Account(s)</th>
                        <th>Category</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transactions as $transaction)
                        <tr>
                            <td><a href="{{ route('transactions.show', $transaction) }}">{{ $transaction->transaction_date->toDateString() }}</a></td>
                            <td>{{ ucfirst(strtolower($transaction->transaction_type)) }}</td>
                            <td>{{ $transaction->description }}</td>
                            <td>{{ $transaction->ledgerEntries->pluck('account.name')->unique()->implode(', ') }}</td>
                            <td>{{ $transaction->category?->name ?? '—' }}</td>
                            <td class="text-end">
                                @foreach ($transaction->ledgerEntries as $entry)
                                    <div>{{ $entry->direction === 'OUTFLOW' ? '-' : '+' }}{{ $entry->amount }}</div>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $transactions->withQueryString()->links() }}
    @endif
@endsection
