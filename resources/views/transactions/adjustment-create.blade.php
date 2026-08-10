@extends('layouts.app')

@section('title', 'Record an adjustment — Personal Budget Manager')

@section('content')
    <h1 class="h3 mb-4">Record an adjustment</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('transactions.adjustment.store') }}" id="adjustment-form" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="account_id" class="form-label">Account</label>
                    <select id="account_id" name="account_id" class="form-select @error('account_id') is-invalid @enderror" required>
                        <option value="">Select an account</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" @selected((string) old('account_id') === (string) $account->id)>{{ $account->name }}</option>
                        @endforeach
                    </select>
                    @error('account_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="direction" class="form-label">Direction</label>
                    <select id="direction" name="direction" class="form-select @error('direction') is-invalid @enderror" required>
                        <option value="">Select a direction</option>
                        <option value="INFLOW" @selected(old('direction') === 'INFLOW')>Inflow (increases balance)</option>
                        <option value="OUTFLOW" @selected(old('direction') === 'OUTFLOW')>Outflow (decreases balance)</option>
                    </select>
                    @error('direction') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
                    <label for="reason" class="form-label">Reason</label>
                    <textarea id="reason" name="reason" class="form-control @error('reason') is-invalid @enderror" rows="2" required>{{ old('reason') }}</textarea>
                    <div class="form-text">Required. Explain the verified discrepancy this adjustment corrects.</div>
                    @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="category_id" class="form-label">Category <span class="text-muted">(optional)</span></label>
                    <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror">
                        <option value="">No category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-4">
                    <label for="reference" class="form-label">Reference <span class="text-muted">(optional)</span></label>
                    <input type="text" id="reference" name="reference" class="form-control @error('reference') is-invalid @enderror" value="{{ old('reference') }}">
                    @error('reference') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#confirm-adjustment-modal">Record adjustment</button>
                <a href="{{ route('transactions.create') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>

    <div class="modal fade" id="confirm-adjustment-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm adjustment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Adjustments correct verified discrepancies and are recorded permanently. This cannot be undone from the interface.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="adjustment-form" class="btn btn-primary">Confirm adjustment</button>
                </div>
            </div>
        </div>
    </div>
@endsection
