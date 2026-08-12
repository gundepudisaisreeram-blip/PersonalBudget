<h2 class="h5">Cash Flow</h2>
<div class="table-responsive mb-4">
    <table class="table table-sm bg-white">
        <tbody>
            <tr><th scope="row">Opening balance</th><td class="text-end">{{ $review['cash_flow']['opening_balance'] }}</td></tr>
            <tr><th scope="row">Income</th><td class="text-end">{{ $review['cash_flow']['income'] }}</td></tr>
            <tr><th scope="row">Expenses</th><td class="text-end">{{ $review['cash_flow']['expenses'] }}</td></tr>
            <tr><th scope="row">Transfers</th><td class="text-end">{{ $review['cash_flow']['transfers'] }}</td></tr>
            <tr><th scope="row">Investments</th><td class="text-end">{{ $review['cash_flow']['investments'] }}</td></tr>
            <tr><th scope="row">Adjustments</th><td class="text-end">{{ $review['cash_flow']['adjustments'] }}</td></tr>
            <tr class="table-active fw-bold"><th scope="row">Closing balance</th><td class="text-end">{{ $review['cash_flow']['closing_balance'] }}</td></tr>
        </tbody>
    </table>
</div>

<h2 class="h5">Budgets</h2>
@if ($review['budgets']->isEmpty())
    <div class="alert alert-secondary">No budgets for this period.</div>
@else
    <div class="table-responsive mb-4">
        <table class="table table-sm bg-white">
            <thead><tr><th>Category</th><th class="text-end">Budget</th><th class="text-end">Actual</th><th class="text-end">Remaining</th></tr></thead>
            <tbody>
                @foreach ($review['budgets'] as $row)
                    <tr>
                        <td>{{ $row['budget']->category?->name }}</td>
                        <td class="text-end">{{ $row['budget']->budget_amount }}</td>
                        <td class="text-end">{{ $row['actual'] }}</td>
                        <td class="text-end">{{ $row['remaining'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<h2 class="h5">Obligations</h2>
@if ($review['obligations']->isEmpty())
    <div class="alert alert-secondary">No obligations for this period.</div>
@else
    <div class="table-responsive mb-4">
        <table class="table table-sm bg-white">
            <thead><tr><th>Category</th><th>Due date</th><th class="text-end">Planned</th><th>Status</th><th>Overdue</th></tr></thead>
            <tbody>
                @foreach ($review['obligations'] as $row)
                    <tr class="{{ $row['is_overdue'] ? 'table-danger' : '' }}">
                        <td>{{ $row['obligation']->category?->name }}</td>
                        <td>{{ $row['obligation']->due_date->toDateString() }}</td>
                        <td class="text-end">{{ $row['obligation']->planned_amount }}</td>
                        <td>{{ $row['historical_status'] }}</td>
                        <td>{{ $row['is_overdue'] ? 'Yes' : 'No' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<h2 class="h5">Category Spending</h2>
@if ($review['category_spending']->isEmpty())
    <div class="alert alert-secondary">No transactions for this period.</div>
@else
    <div class="table-responsive mb-4">
        <table class="table table-sm bg-white">
            <thead><tr><th>Category</th><th class="text-end">Net Expenses</th></tr></thead>
            <tbody>
                @foreach ($review['category_spending'] as $row)
                    <tr>
                        <td>{{ $row['category']?->name ?? 'Uncategorized' }}</td>
                        <td class="text-end">{{ $row['net_expenses'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
