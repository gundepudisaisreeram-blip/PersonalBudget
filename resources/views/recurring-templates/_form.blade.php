<div class="mb-3">
    <label for="name" class="form-label">Name</label>
    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $template?->name ?? '') }}" required>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="amount" class="form-label">Amount</label>
    <input type="number" step="0.01" min="0.01" id="amount" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', $template?->amount ?? '') }}" required>
    @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="frequency" class="form-label">Frequency</label>
    <select id="frequency" name="frequency" class="form-select @error('frequency') is-invalid @enderror" required>
        <option value="MONTHLY" @selected(old('frequency', $template?->frequency ?? 'MONTHLY') === 'MONTHLY')>Monthly</option>
    </select>
    @error('frequency') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="due_rule" class="form-label">Due day</label>
    <input type="text" id="due_rule" name="due_rule" class="form-control @error('due_rule') is-invalid @enderror" value="{{ old('due_rule', $template?->due_rule ?? '') }}" placeholder="DAY:5" required>
    <div class="form-text">Format: DAY:N, where N is 1-31. If the target month is shorter than N, the obligation clamps to that month's last day.</div>
    @error('due_rule') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="category_id" class="form-label">Category</label>
    <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
        <option value="">Select a category</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((string) old('category_id', $template?->category_id) === (string) $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="default_account_id" class="form-label">Default account <span class="text-muted">(optional)</span></label>
    <select id="default_account_id" name="default_account_id" class="form-select @error('default_account_id') is-invalid @enderror">
        <option value="">No default account</option>
        @foreach ($accounts as $account)
            <option value="{{ $account->id }}" @selected((string) old('default_account_id', $template?->default_account_id) === (string) $account->id)>{{ $account->name }}</option>
        @endforeach
    </select>
    @error('default_account_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="starts_on" class="form-label">Starts on</label>
    <input type="date" id="starts_on" name="starts_on" class="form-control @error('starts_on') is-invalid @enderror" value="{{ old('starts_on', optional($template?->starts_on)->toDateString()) }}" required>
    @error('starts_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="ends_on" class="form-label">Ends on <span class="text-muted">(optional)</span></label>
    <input type="date" id="ends_on" name="ends_on" class="form-control @error('ends_on') is-invalid @enderror" value="{{ old('ends_on', optional($template?->ends_on)->toDateString()) }}">
    @error('ends_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3 form-check">
    <input type="checkbox" id="is_mandatory" name="is_mandatory" value="1" class="form-check-input" @checked(old('is_mandatory', $template?->is_mandatory ?? true))>
    <label for="is_mandatory" class="form-check-label">Mandatory commitment</label>
</div>

<div class="mb-4">
    <label for="notes" class="form-label">Notes <span class="text-muted">(optional)</span></label>
    <textarea id="notes" name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3">{{ old('notes', $template?->notes ?? '') }}</textarea>
    @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
