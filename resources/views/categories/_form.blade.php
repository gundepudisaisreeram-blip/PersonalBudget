<div class="mb-3">
    <label for="name" class="form-label">Category name</label>
    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $category?->name ?? '') }}" required>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="category_type" class="form-label">Type</label>
    <input type="text" id="category_type" name="category_type" class="form-control @error('category_type') is-invalid @enderror" value="{{ old('category_type', $category?->category_type ?? '') }}" placeholder="e.g. EXPENSE, INCOME, INVESTMENT" required>
    @error('category_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="parent_id" class="form-label">Parent category</label>
    <select id="parent_id" name="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
        <option value="">No parent</option>
        @foreach ($parentOptions as $option)
            <option value="{{ $option->id }}" @selected((string) old('parent_id', $category?->parent_id) === (string) $option->id)>
                {{ $option->name }}
            </option>
        @endforeach
    </select>
    <div class="form-text">Only your own categories can be selected as a parent.</div>
    @error('parent_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-4">
    <label for="icon" class="form-label">Icon <span class="text-muted">(optional)</span></label>
    <input type="text" id="icon" name="icon" class="form-control @error('icon') is-invalid @enderror" value="{{ old('icon', $category?->icon ?? '') }}">
    @error('icon') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
