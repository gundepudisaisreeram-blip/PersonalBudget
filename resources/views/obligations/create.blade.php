@extends('layouts.app')

@section('title', 'Add one-time obligation — Personal Budget Manager')

@section('content')
    <h1 class="h3 mb-4">Add one-time obligation</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('obligations.store') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="category_id" class="form-label">Category</label>
                    <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                        <option value="">Select a category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="planned_account_id" class="form-label">Planned account <span class="text-muted">(optional)</span></label>
                    <select id="planned_account_id" name="planned_account_id" class="form-select @error('planned_account_id') is-invalid @enderror">
                        <option value="">No planned account</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" @selected((string) old('planned_account_id') === (string) $account->id)>{{ $account->name }}</option>
                        @endforeach
                    </select>
                    @error('planned_account_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="period_start" class="form-label">Period start</label>
                        <input type="date" id="period_start" name="period_start" class="form-control @error('period_start') is-invalid @enderror" value="{{ old('period_start') }}" required>
                        @error('period_start') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="period_end" class="form-label">Period end</label>
                        <input type="date" id="period_end" name="period_end" class="form-control @error('period_end') is-invalid @enderror" value="{{ old('period_end') }}" required>
                        @error('period_end') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="due_date" class="form-label">Due date</label>
                    <input type="date" id="due_date" name="due_date" class="form-control @error('due_date') is-invalid @enderror" value="{{ old('due_date') }}" required>
                    @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="planned_amount" class="form-label">Planned amount</label>
                    <input type="number" step="0.01" min="0.01" id="planned_amount" name="planned_amount" class="form-control @error('planned_amount') is-invalid @enderror" value="{{ old('planned_amount') }}" required>
                    @error('planned_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-4 form-check">
                    <input type="checkbox" id="is_mandatory" name="is_mandatory" value="1" class="form-check-input" @checked(old('is_mandatory', true))>
                    <label for="is_mandatory" class="form-check-label">Mandatory commitment</label>
                </div>

                <button type="submit" class="btn btn-primary">Create obligation</button>
                <a href="{{ route('obligations.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
