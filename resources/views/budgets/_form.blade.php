<div class="mb-3">
    <label for="category_id" class="form-label">Category</label>
    <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
        <option value="">Select a category</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((string) old('category_id', $budget?->category_id) === (string) $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="period_start" class="form-label">Period start</label>
        <input type="date" id="period_start" name="period_start" class="form-control @error('period_start') is-invalid @enderror" value="{{ old('period_start', optional($budget?->period_start)->toDateString()) }}" required>
        @error('period_start') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="period_end" class="form-label">Period end</label>
        <input type="date" id="period_end" name="period_end" class="form-control @error('period_end') is-invalid @enderror" value="{{ old('period_end', optional($budget?->period_end)->toDateString()) }}" required>
        @error('period_end') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3">
    <label for="budget_amount" class="form-label">Budget amount</label>
    <input type="number" step="0.01" min="0" id="budget_amount" name="budget_amount" class="form-control @error('budget_amount') is-invalid @enderror" value="{{ old('budget_amount', $budget?->budget_amount ?? '') }}" required>
    @error('budget_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-4 form-check">
    <input type="checkbox" id="is_mandatory_reserve" name="is_mandatory_reserve" value="1" class="form-check-input" @checked(old('is_mandatory_reserve', $budget?->is_mandatory_reserve ?? false))>
    <label for="is_mandatory_reserve" class="form-check-label">Mandatory reserve</label>
</div>
