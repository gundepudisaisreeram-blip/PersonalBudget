@if ($duplicateCandidateCount > 0)
    <div class="alert alert-warning">
        {{ $duplicateCandidateCount }} row(s) matched an existing imported transaction by fallback fingerprint only
        (no reliable bank reference). These are duplicate <strong>candidates</strong>, not confirmed duplicates --
        review them below. They remain staged and will not be silently discarded.
    </div>
@endif

<div class="table-responsive">
    <table class="table table-sm table-hover bg-white">
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Reference</th>
                <th class="text-end">Amount</th>
                <th>Direction</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr class="{{ $row->duplicate_status === 'POTENTIAL_DUPLICATE' ? 'table-warning' : '' }}">
                    <td>{{ $row->transaction_date->toDateString() }}</td>
                    <td>{{ \App\Domain\Statements\Support\CsvFormulaGuard::sanitize($row->description) }}</td>
                    <td>{{ \App\Domain\Statements\Support\CsvFormulaGuard::sanitize($row->reference_number) }}</td>
                    <td class="text-end">{{ $row->normalized_amount }}</td>
                    <td>{{ $row->direction }}</td>
                    <td>
                        @if ($row->duplicate_status === 'POTENTIAL_DUPLICATE')
                            <span class="badge text-bg-warning">Duplicate candidate</span>
                        @else
                            <span class="badge text-bg-light text-dark">Valid</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
{{ $rows->links() }}
