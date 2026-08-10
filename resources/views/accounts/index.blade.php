@extends('layouts.app')

@section('title', 'Accounts — Personal Budget Manager')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Accounts</h1>
        <a href="{{ route('accounts.create') }}" class="btn btn-primary">Add account</a>
    </div>

    <form method="GET" action="{{ route('accounts.index') }}" class="row g-2 mb-4">
        <div class="col-auto">
            <select name="account_type" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All types</option>
                <option value="ASSET" @selected(request('account_type') === 'ASSET')>Asset</option>
                <option value="LIABILITY" @selected(request('account_type') === 'LIABILITY')>Liability</option>
            </select>
        </div>
        <div class="col-auto">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <option value="ACTIVE" @selected(request('status') === 'ACTIVE')>Active</option>
                <option value="CLOSED" @selected(request('status') === 'CLOSED')>Closed</option>
            </select>
        </div>
    </form>

    @if ($rows->isEmpty())
        <div class="alert alert-secondary">Set up your first account to begin.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle bg-white">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Institution</th>
                        <th>Type</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td><a href="{{ route('accounts.show', $row['account']) }}">{{ $row['account']->name }}</a></td>
                            <td>{{ $row['account']->institution }}</td>
                            <td>{{ ucfirst(strtolower($row['account']->account_type)) }}</td>
                            <td class="text-end">{{ $row['balance'] }}</td>
                            <td>
                                <span class="badge {{ $row['account']->status === 'ACTIVE' ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $row['account']->status }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('accounts.edit', $row['account']) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
