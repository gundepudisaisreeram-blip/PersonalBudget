@extends('layouts.app')

@section('title', 'Trends — Personal Budget Manager')

@section('content')
    @include('reports._nav')

    <h1 class="h3 mb-4">Trends</h1>

    @include('reports._date_account_filters')

    @if ($months->isEmpty())
        <div class="alert alert-secondary">No transactions for this period.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover bg-white">
                <caption>Accessible summary of the monthly trends chart above (per 05_UI_UX_SPECIFICATION.md section 11).</caption>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="text-end">Income</th>
                        <th class="text-end">Expenses</th>
                        <th class="text-end">Investments</th>
                        <th class="text-end">Savings</th>
                        <th class="text-end">Closing balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($months as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td class="text-end">{{ $row['cash_flow']['income'] }}</td>
                            <td class="text-end">{{ $row['cash_flow']['expenses'] }}</td>
                            <td class="text-end">{{ $row['cash_flow']['investments'] }}</td>
                            <td class="text-end">{{ $row['savings'] }}</td>
                            <td class="text-end">{{ $row['cash_flow']['closing_balance'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
