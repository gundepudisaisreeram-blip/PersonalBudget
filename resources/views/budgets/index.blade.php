@extends('layouts.app')

@section('title', 'Budgets — Personal Budget Manager')

@section('content')
    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a class="nav-link" href="{{ route('obligations.index') }}">Obligations</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('recurring-templates.index') }}">Recurring Templates</a></li>
        <li class="nav-item"><span class="nav-link active">Budgets</span></li>
    </ul>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Budgets</h1>
        <a href="{{ route('budgets.create') }}" class="btn btn-primary">Add budget</a>
    </div>

    @if ($rows->isEmpty())
        <div class="alert alert-secondary">No budgets yet.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover bg-white">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Period</th>
                        <th class="text-end">Budget</th>
                        <th class="text-end">Actual</th>
                        <th class="text-end">Remaining</th>
                        <th class="text-end">Variance</th>
                        <th class="text-end">Utilization</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $budget = $row['budget'];
                            $utilization = $row['utilization'];
                            $remaining = $budget->budget_amount - $utilization;
                            $variance = $budget->budget_amount - $utilization;
                            $percent = $budget->budget_amount > 0 ? ($utilization / $budget->budget_amount) * 100 : 0;
                            $overspend = $utilization > $budget->budget_amount;
                        @endphp
                        <tr class="{{ $overspend ? 'table-danger' : '' }}">
                            <td><a href="{{ route('budgets.edit', $budget) }}">{{ $budget->category?->name }}</a></td>
                            <td>{{ $budget->period_start->toDateString() }} — {{ $budget->period_end->toDateString() }}</td>
                            <td class="text-end">{{ $budget->budget_amount }}</td>
                            <td class="text-end">{{ $utilization }}</td>
                            <td class="text-end">{{ number_format($remaining, 2) }}</td>
                            <td class="text-end">{{ number_format($variance, 2) }}</td>
                            <td class="text-end">
                                <span class="badge {{ $overspend ? 'text-bg-danger' : ($percent >= 80 ? 'text-bg-warning' : 'text-bg-success') }}">
                                    {{ number_format($percent, 0) }}%
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
