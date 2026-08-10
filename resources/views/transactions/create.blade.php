@extends('layouts.app')

@section('title', 'Add a transaction — Personal Budget Manager')

@section('content')
    <h1 class="h3 mb-4">Add a transaction</h1>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
        <div class="col">
            <a href="{{ route('transactions.expense.create') }}" class="btn btn-outline-danger w-100 py-3">Expense</a>
        </div>
        <div class="col">
            <a href="{{ route('transactions.income.create') }}" class="btn btn-outline-success w-100 py-3">Income</a>
        </div>
        <div class="col">
            <a href="{{ route('transactions.transfer.create') }}" class="btn btn-outline-primary w-100 py-3">Transfer</a>
        </div>
        <div class="col">
            <a href="{{ route('transactions.refund.create') }}" class="btn btn-outline-secondary w-100 py-3">Refund</a>
        </div>
        <div class="col">
            <a href="{{ route('transactions.reversal.create') }}" class="btn btn-outline-secondary w-100 py-3">Reversal</a>
        </div>
        <div class="col">
            <a href="{{ route('transactions.adjustment.create') }}" class="btn btn-outline-warning w-100 py-3">Adjustment</a>
        </div>
    </div>
@endsection
