@php
    $isClosed = $account && $account->status === 'CLOSED';
@endphp

<div class="mb-3">
    <label for="name" class="form-label">Account name</label>
    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $account?->name ?? '') }}" required>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="institution" class="form-label">Institution</label>
    <input type="text" id="institution" name="institution" class="form-control @error('institution') is-invalid @enderror" value="{{ old('institution', $account?->institution ?? '') }}" required>
    @error('institution') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label">Account type</label>
    @if ($isClosed)
        <input type="text" class="form-control" value="{{ ucfirst(strtolower($account->account_type)) }}" disabled>
        <input type="hidden" name="account_type" value="{{ $account->account_type }}">
        <div class="form-text">Cannot be changed once the account is closed.</div>
    @else
        <select id="account_type" name="account_type" class="form-select @error('account_type') is-invalid @enderror" required>
            <option value="">Select type</option>
            <option value="ASSET" @selected(old('account_type', $account?->account_type ?? '') === 'ASSET')>Asset</option>
            <option value="LIABILITY" @selected(old('account_type', $account?->account_type ?? '') === 'LIABILITY')>Liability</option>
        </select>
        @error('account_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    @endif
</div>

<div class="mb-3">
    <label for="subtype" class="form-label">Subtype</label>
    <input type="text" id="subtype" name="subtype" class="form-control @error('subtype') is-invalid @enderror" value="{{ old('subtype', $account?->subtype ?? '') }}" placeholder="e.g. SAVINGS, CREDIT_CARD" required>
    @error('subtype') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label">Currency</label>
    @if ($isClosed)
        <input type="text" class="form-control" value="{{ $account->currency }}" disabled>
        <input type="hidden" name="currency" value="{{ $account->currency }}">
        <div class="form-text">Cannot be changed once the account is closed.</div>
    @else
        <input type="text" id="currency" name="currency" class="form-control @error('currency') is-invalid @enderror" value="{{ old('currency', $account?->currency ?? 'INR') }}" required>
        @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
    @endif
</div>

<div class="mb-3">
    <label class="form-label">Opening balance</label>
    @if ($isClosed)
        <input type="text" class="form-control" value="{{ $account->opening_balance }}" disabled>
        <input type="hidden" name="opening_balance" value="{{ $account->opening_balance }}">
        <div class="form-text">Cannot be changed once the account is closed.</div>
    @else
        <input type="number" step="0.01" min="0" id="opening_balance" name="opening_balance" class="form-control @error('opening_balance') is-invalid @enderror" value="{{ old('opening_balance', $account?->opening_balance ?? '0.00') }}" required>
        @error('opening_balance') <div class="invalid-feedback">{{ $message }}</div> @enderror
    @endif
</div>

<div class="mb-3">
    <label class="form-label">Opening balance date</label>
    @if ($isClosed)
        <input type="text" class="form-control" value="{{ $account->opening_balance_date->toDateString() }}" disabled>
        <input type="hidden" name="opening_balance_date" value="{{ $account->opening_balance_date->toDateString() }}">
        <div class="form-text">Cannot be changed once the account is closed.</div>
    @else
        <input type="date" id="opening_balance_date" name="opening_balance_date" class="form-control @error('opening_balance_date') is-invalid @enderror" value="{{ old('opening_balance_date', optional($account?->opening_balance_date)->toDateString()) }}" required>
        @error('opening_balance_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    @endif
</div>

<div class="mb-4">
    <label for="notes" class="form-label">Notes</label>
    <textarea id="notes" name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3">{{ old('notes', $account?->notes ?? '') }}</textarea>
    @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
