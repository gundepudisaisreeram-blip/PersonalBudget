@extends('layouts.app')

@section('title', 'Obligation detail — Personal Budget Manager')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">{{ $obligation->category?->name }} — due {{ $obligation->due_date->toDateString() }}</h1>
            <span class="badge {{ $obligation->status === 'PAID' ? 'text-bg-success' : ($obligation->status === 'PARTIALLY_PAID' ? 'text-bg-warning' : 'text-bg-secondary') }}">{{ $obligation->status }}</span>
        </div>
        @if ($obligation->isActive())
            <div>
                <a href="{{ route('obligations.allocations.create', $obligation) }}" class="btn btn-primary">Link existing transaction</a>
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#skip-obligation-modal">Skip</button>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancel-obligation-modal">Cancel</button>
            </div>
        @endif
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Period</dt>
                <dd class="col-sm-9">{{ $obligation->period_start->toDateString() }} — {{ $obligation->period_end->toDateString() }}</dd>

                <dt class="col-sm-3">Planned amount</dt>
                <dd class="col-sm-9">{{ $obligation->planned_amount }}</dd>

                <dt class="col-sm-3">Allocated</dt>
                <dd class="col-sm-9">{{ $allocations->sum('allocated_amount') }}</dd>

                <dt class="col-sm-3">Remaining</dt>
                <dd class="col-sm-9">{{ number_format($obligation->planned_amount - $allocations->sum('allocated_amount'), 2) }}</dd>

                <dt class="col-sm-3">Planned account</dt>
                <dd class="col-sm-9">{{ $obligation->plannedAccount?->name ?? '—' }}</dd>

                @if ($obligation->recurringPaymentTemplate)
                    <dt class="col-sm-3">Recurring template</dt>
                    <dd class="col-sm-9"><a href="{{ route('recurring-templates.show', $obligation->recurringPaymentTemplate) }}">{{ $obligation->recurringPaymentTemplate->name }}</a></dd>
                @endif
            </dl>
        </div>
    </div>

    <h2 class="h5 mb-3">Allocations</h2>
    @if ($allocations->isEmpty())
        <div class="alert alert-secondary">No transactions linked yet.</div>
    @else
        <div class="table-responsive">
            <table class="table bg-white">
                <thead>
                    <tr>
                        <th>Transaction</th>
                        <th class="text-end">Allocated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($allocations as $allocation)
                        <tr>
                            <td><a href="{{ route('transactions.show', $allocation->transaction) }}">{{ $allocation->transaction->transaction_date->toDateString() }} — {{ $allocation->transaction->description }}</a></td>
                            <td class="text-end">{{ $allocation->allocated_amount }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#remove-allocation-{{ $allocation->id }}">Remove</button>
                                @include('partials.confirm-modal', [
                                    'modalId' => 'remove-allocation-'.$allocation->id,
                                    'title' => 'Remove this allocation?',
                                    'body' => 'The obligation\'s status will be recalculated from its remaining allocations.',
                                    'action' => route('obligations.allocations.destroy', [$obligation, $allocation]),
                                    'method' => 'DELETE',
                                    'confirmLabel' => 'Remove',
                                ])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <a href="{{ route('obligations.index') }}">&larr; Back to obligations</a>

    @if ($obligation->isActive())
        @include('partials.confirm-modal', [
            'modalId' => 'skip-obligation-modal',
            'title' => 'Skip this obligation?',
            'body' => 'It will be excluded from active pending calculations but remain historically visible.',
            'action' => route('obligations.skip', $obligation),
            'method' => 'PATCH',
            'confirmLabel' => 'Skip',
        ])
        @include('partials.confirm-modal', [
            'modalId' => 'cancel-obligation-modal',
            'title' => 'Cancel this obligation?',
            'body' => 'This cannot be undone from the interface.',
            'action' => route('obligations.cancel', $obligation),
            'method' => 'PATCH',
            'confirmLabel' => 'Cancel obligation',
        ])
    @endif
@endsection
