@extends('layouts.app')

@section('title', $account->name.' — Personal Budget Manager')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">{{ $account->name }}</h1>
            <span class="badge {{ $account->status === 'ACTIVE' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $account->status }}</span>
        </div>
        <div>
            <a href="{{ route('accounts.edit', $account) }}" class="btn btn-outline-secondary">Edit</a>
            @if ($account->status === 'ACTIVE')
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#close-account-modal">Close account</button>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Derived balance</dt>
                <dd class="col-sm-9">{{ $balance }} {{ $account->currency }}</dd>

                <dt class="col-sm-3">Type</dt>
                <dd class="col-sm-9">{{ ucfirst(strtolower($account->account_type)) }} ({{ $account->subtype }})</dd>

                <dt class="col-sm-3">Institution</dt>
                <dd class="col-sm-9">{{ $account->institution }}</dd>

                <dt class="col-sm-3">Opening balance</dt>
                <dd class="col-sm-9">{{ $account->opening_balance }} as of {{ $account->opening_balance_date->toDateString() }}</dd>

                @if ($account->notes)
                    <dt class="col-sm-3">Notes</dt>
                    <dd class="col-sm-9">{{ $account->notes }}</dd>
                @endif
            </dl>
        </div>
    </div>

    <a href="{{ route('accounts.index') }}">&larr; Back to accounts</a>

    @if ($account->status === 'ACTIVE')
        @include('partials.confirm-modal', [
            'modalId' => 'close-account-modal',
            'title' => 'Close this account?',
            'body' => 'The account will be marked closed. Its financial history is preserved and account_type, opening balance, opening balance date, and currency can no longer be changed. This cannot be undone from the interface.',
            'action' => route('accounts.close', $account),
            'method' => 'PATCH',
            'confirmLabel' => 'Close account',
        ])
    @endif
@endsection
