@extends('layouts.app')

@section('title', 'Cash Flow — Personal Budget Manager')

@section('content')
    @include('reports._nav')

    <h1 class="h3 mb-4">Cash Flow</h1>

    @include('reports._date_account_filters')

    <div class="table-responsive">
        <table class="table table-hover bg-white">
            <caption>Accessible summary of the cash flow chart above (per 05_UI_UX_SPECIFICATION.md section 11).</caption>
            <tbody>
                <tr><th scope="row">Opening balance</th><td class="text-end">{{ $cashFlow['opening_balance'] }}</td></tr>
                <tr><th scope="row">Income</th><td class="text-end">{{ $cashFlow['income'] }}</td></tr>
                <tr><th scope="row">Expenses</th><td class="text-end">{{ $cashFlow['expenses'] }}</td></tr>
                <tr>
                    <th scope="row">Transfers</th>
                    <td class="text-end">
                        {{ $cashFlow['transfers'] }}
                        <div class="text-muted small">Outbound {{ $cashFlow['outbound_transfer'] }} / Inbound {{ $cashFlow['inbound_transfer'] }}</div>
                    </td>
                </tr>
                <tr><th scope="row">Investments</th><td class="text-end">{{ $cashFlow['investments'] }}</td></tr>
                <tr><th scope="row">Adjustments</th><td class="text-end">{{ $cashFlow['adjustments'] }}</td></tr>
                <tr class="table-active fw-bold"><th scope="row">Closing balance</th><td class="text-end">{{ $cashFlow['closing_balance'] }}</td></tr>
            </tbody>
        </table>
    </div>

    @unless ($cashFlow['reconciled'])
        <div class="alert alert-danger">Reconciliation residual detected: {{ $cashFlow['residual'] }}.</div>
    @endunless
@endsection
