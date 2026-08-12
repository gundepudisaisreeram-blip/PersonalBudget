<form method="GET" class="row g-2 align-items-end mb-4">
    <div class="col-auto">
        <label class="form-label small mb-0">Start date</label>
        <input type="date" name="start_date" value="{{ request('start_date') }}" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <label class="form-label small mb-0">End date</label>
        <input type="date" name="end_date" value="{{ request('end_date') }}" class="form-control form-control-sm">
    </div>
    @isset($accounts)
        <div class="col-auto">
            <label class="form-label small mb-0">Accounts</label>
            <select name="account_id[]" multiple class="form-select form-select-sm" style="min-width: 12rem;">
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected(in_array($account->id, $selectedAccountIds ?? [], true))>{{ $account->name }}</option>
                @endforeach
            </select>
        </div>
    @endisset
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Apply</button>
    </div>
</form>

@if (isset($start, $end))
    <p class="text-muted small">Showing {{ $start->toDateString() }} to {{ $end->toDateString() }} (inclusive).</p>
@endif
