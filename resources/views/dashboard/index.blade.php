@extends('layouts.app')

@section('title', 'Dashboard — Personal Budget Manager')

@section('content')
    @if ($accounts->isEmpty())
        <div class="alert alert-secondary">Set up your first account to begin.</div>
        <a href="{{ route('accounts.create') }}" class="btn btn-primary">Add account</a>
    @else
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Current Asset Balance</div>
                        <div class="h3 mb-0">{{ $snapshot['current_asset_balance'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Safe Balance</div>
                        <div class="h3 mb-0">{{ $snapshot['safe_balance'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card h-100 {{ $safeToSpendNegative ? 'border-danger' : '' }}">
                    <div class="card-body">
                        <div class="text-muted small">Safe-to-Spend</div>
                        <div class="h3 mb-0 {{ $safeToSpendNegative ? 'text-danger' : '' }}">
                            {{ $snapshot['safe_to_spend'] }}
                            @if ($safeToSpendNegative)
                                <span class="badge text-bg-danger align-middle">Shortfall</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">Pending Mandatory Obligations</div>
                    <div class="h5 mb-0">{{ $snapshot['pending_mandatory_fixed_obligations'] }}</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">Pending Mandatory Investments</div>
                    <div class="h5 mb-0">{{ $snapshot['pending_mandatory_investments'] }}</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">Paid Obligations</div>
                    <div class="h5 mb-0">{{ $paidObligations['count'] }} ({{ $paidObligations['total'] }})</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">Variable Budget Reserve</div>
                    <div class="h5 mb-0">{{ $snapshot['variable_budget_reserve'] }}</div>
                </div></div>
            </div>
        </div>

        <h2 class="h5 mb-3">Attention Center</h2>
        @if (empty($attentionCenter))
            <div class="alert alert-secondary">Nothing needs your attention right now.</div>
        @else
            <ul class="list-group mb-4">
                @foreach ($attentionCenter as $alert)
                    @if ($alert['type'] === 'negative_safe_to_spend')
                        <li class="list-group-item list-group-item-danger">Safe-to-Spend is negative: {{ $alert['item'] }}</li>
                    @elseif ($alert['type'] === 'overdue_obligation')
                        <li class="list-group-item list-group-item-warning">
                            Overdue: {{ $alert['item']->category?->name }} — due {{ $alert['item']->due_date->toDateString() }}
                            (<a href="{{ route('obligations.show', $alert['item']) }}">view</a>)
                        </li>
                    @elseif ($alert['type'] === 'budget_overspend')
                        <li class="list-group-item list-group-item-warning">
                            Budget overspend: {{ $alert['item']['budget']->category?->name }} — {{ $alert['item']['percent'] }}% used
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif

        <h2 class="h5 mb-3">Upcoming (next 14 days)</h2>
        @if ($upcomingPayments->isEmpty())
            <div class="alert alert-secondary">No payment obligations due in the next 14 days.</div>
        @else
            <div class="table-responsive mb-4">
                <table class="table table-hover bg-white">
                    <thead>
                        <tr>
                            <th>Due date</th>
                            <th>Category</th>
                            <th>Account</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($upcomingPayments as $obligation)
                            <tr>
                                <td><a href="{{ route('obligations.show', $obligation) }}">{{ $obligation->due_date->toDateString() }}</a></td>
                                <td>{{ $obligation->category?->name }}</td>
                                <td>{{ $obligation->plannedAccount?->name ?? '—' }}</td>
                                <td class="text-end">{{ $obligation->planned_amount }}</td>
                                <td>{{ $obligation->status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <h2 class="h5 mb-3">Budget Snapshot</h2>
        @if ($budgets->isEmpty())
            <div class="alert alert-secondary">No active budgets for this period.</div>
        @else
            <div class="table-responsive mb-4">
                <table class="table table-hover bg-white">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="text-end">Budget</th>
                            <th class="text-end">Actual</th>
                            <th class="text-end">Remaining</th>
                            <th class="text-end">Utilization</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($budgets as $row)
                            <tr class="{{ $row['overspend'] ? 'table-danger' : '' }}">
                                <td>{{ $row['budget']->category?->name }}</td>
                                <td class="text-end">{{ $row['budget']->budget_amount }}</td>
                                <td class="text-end">{{ $row['utilization'] }}</td>
                                <td class="text-end">{{ $row['remaining'] }}</td>
                                <td class="text-end">{{ $row['percent'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <h2 class="h5 mb-3">Account Snapshot</h2>
        <div class="table-responsive">
            <table class="table table-hover bg-white">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Type</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($accounts as $row)
                        <tr>
                            <td><a href="{{ route('accounts.show', $row['account']) }}">{{ $row['account']->name }}</a></td>
                            <td>{{ $row['account']->account_type }}</td>
                            <td class="text-end">{{ $row['balance'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
