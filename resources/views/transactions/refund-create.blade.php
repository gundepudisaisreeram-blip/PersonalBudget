@extends('layouts.app')

@section('title', 'Record a refund — Personal Budget Manager')

@section('content')
    <h1 class="h3 mb-4">Record a refund</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('transactions.refund.store') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="parent_transaction_id" class="form-label">Original expense</label>
                    <select id="parent_transaction_id" name="parent_transaction_id" class="form-select @error('parent_transaction_id') is-invalid @enderror" required>
                        <option value="">Select the expense being refunded</option>
                        @foreach ($parentOptions as $parent)
                            <option value="{{ $parent->id }}" @selected((string) old('parent_transaction_id') === (string) $parent->id)>
                                {{ $parent->transaction_date->toDateString() }} — {{ $parent->description }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Only your own EXPENSE transactions are listed. The server independently verifies this on submit.</div>
                    @error('parent_transaction_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="account_id" class="form-label">Destination account</label>
                    <select id="account_id" name="account_id" class="form-select @error('account_id') is-invalid @enderror" required>
                        <option value="">Select an account</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" @selected((string) old('account_id') === (string) $account->id)>{{ $account->name }}</option>
                        @endforeach
                    </select>
                    @error('account_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="amount" class="form-label">Amount</label>
                    <input type="number" step="0.01" min="0.01" id="amount" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" required>
                    @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="transaction_date" class="form-label">Date</label>
                    <input type="date" id="transaction_date" name="transaction_date" class="form-control @error('transaction_date') is-invalid @enderror" value="{{ old('transaction_date', now()->toDateString()) }}" required>
                    @error('transaction_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <input type="text" id="description" name="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description') }}" required>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="reference" class="form-label">Reference <span class="text-muted">(optional)</span></label>
                    <input type="text" id="reference" name="reference" class="form-control @error('reference') is-invalid @enderror" value="{{ old('reference') }}">
                    @error('reference') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-4">
                    <label for="notes" class="form-label">Notes <span class="text-muted">(optional)</span></label>
                    <textarea id="notes" name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3">{{ old('notes') }}</textarea>
                    @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary">Record refund</button>
                <a href="{{ route('transactions.create') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
