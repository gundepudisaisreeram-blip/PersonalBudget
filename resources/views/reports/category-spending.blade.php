@extends('layouts.app')

@section('title', 'Category Spending — Personal Budget Manager')

@section('content')
    @include('reports._nav')

    <h1 class="h3 mb-4">Category Spending</h1>

    @include('reports._date_account_filters')

    @if ($categories->isEmpty())
        <div class="alert alert-secondary">No transactions for this period.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover bg-white">
                <caption>Accessible summary of the category spending chart above (per 05_UI_UX_SPECIFICATION.md section 11).</caption>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="text-end">Net Expenses</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $row)
                        <tr>
                            <td>{{ $row['category']?->name ?? 'Uncategorized' }}</td>
                            <td class="text-end">{{ $row['net_expenses'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
