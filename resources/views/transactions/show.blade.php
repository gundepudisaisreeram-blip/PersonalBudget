@extends('layouts.app')

@section('title', 'Transaction detail — Personal Budget Manager')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">{{ ucfirst(strtolower($transaction->transaction_type)) }} — {{ $transaction->transaction_date->toDateString() }}</h1>
        <a href="{{ route('transactions.index') }}">&larr; Back to transactions</a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Date</dt>
                <dd class="col-sm-9">{{ $transaction->transaction_date->toDateString() }}</dd>

                <dt class="col-sm-3">Type</dt>
                <dd class="col-sm-9">{{ ucfirst(strtolower($transaction->transaction_type)) }}</dd>

                <dt class="col-sm-3">Description</dt>
                <dd class="col-sm-9">{{ $transaction->description }}</dd>

                <dt class="col-sm-3">Category</dt>
                <dd class="col-sm-9">{{ $transaction->category?->name ?? '—' }}</dd>

                <dt class="col-sm-3">Source</dt>
                <dd class="col-sm-9">{{ ucfirst(strtolower($transaction->source)) }}</dd>

                @if ($transaction->reference)
                    <dt class="col-sm-3">Reference</dt>
                    <dd class="col-sm-9">{{ $transaction->reference }}</dd>
                @endif

                @if ($transaction->notes)
                    <dt class="col-sm-3">Notes</dt>
                    <dd class="col-sm-9">{{ $transaction->notes }}</dd>
                @endif

                @if ($transaction->parentTransaction)
                    <dt class="col-sm-3">Parent transaction</dt>
                    <dd class="col-sm-9">
                        <a href="{{ route('transactions.show', $transaction->parentTransaction) }}">
                            {{ $transaction->parentTransaction->transaction_date->toDateString() }} — {{ $transaction->parentTransaction->description }}
                        </a>
                    </dd>
                @endif

                @if ($transaction->childTransactions->isNotEmpty())
                    <dt class="col-sm-3">Related transactions</dt>
                    <dd class="col-sm-9">
                        <ul class="mb-0 ps-3">
                            @foreach ($transaction->childTransactions as $child)
                                <li>
                                    <a href="{{ route('transactions.show', $child) }}">
                                        {{ ucfirst(strtolower($child->transaction_type)) }} — {{ $child->transaction_date->toDateString() }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </dd>
                @endif

                @if ($auditLog)
                    <dt class="col-sm-3">Audit</dt>
                    <dd class="col-sm-9">
                        Reason: {{ $auditLog->metadata['reason'] ?? '—' }}<br>
                        Recorded {{ $auditLog->created_at }}
                    </dd>
                @endif
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Ledger entries</div>
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Account</th>
                    <th>Direction</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transaction->ledgerEntries as $entry)
                    <tr>
                        <td>{{ $entry->account->name }}</td>
                        <td>{{ $entry->direction }}</td>
                        <td class="text-end">{{ $entry->amount }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
