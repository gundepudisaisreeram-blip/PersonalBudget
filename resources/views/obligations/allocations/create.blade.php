@extends('layouts.app')

@section('title', 'Link a transaction — Personal Budget Manager')

@section('content')
    <h1 class="h3 mb-4">Link a transaction to this obligation</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('obligations.allocations.store', $obligation) }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="transaction_id" class="form-label">Transaction</label>
                    <select id="transaction_id" name="transaction_id" class="form-select @error('transaction_id') is-invalid @enderror" required>
                        <option value="">Select a transaction</option>
                        @foreach ($eligibleTransactions as $transaction)
                            <option value="{{ $transaction->id }}" @selected((string) old('transaction_id') === (string) $transaction->id)>
                                {{ $transaction->transaction_date->toDateString() }} — {{ $transaction->transaction_type }} — {{ $transaction->description }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Only your own EXPENSE/TRANSFER transactions are listed. The server independently verifies this on submit.</div>
                    @error('transaction_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-4">
                    <label for="amount" class="form-label">Amount to allocate</label>
                    <input type="number" step="0.01" min="0.01" id="amount" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" required>
                    @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary">Link transaction</button>
                <a href="{{ route('obligations.show', $obligation) }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
