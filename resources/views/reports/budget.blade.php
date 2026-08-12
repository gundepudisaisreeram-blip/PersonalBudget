@extends('layouts.app')

@section('title', 'Budget Report — Personal Budget Manager')

@section('content')
    @include('reports._nav')

    <h1 class="h3 mb-4">Budget Report</h1>

    <form method="GET" class="row g-2 align-items-end mb-4">
        <div class="col-auto">
            <label class="form-label small mb-0">Start date</label>
            <input type="date" name="start_date" value="{{ request('start_date') }}" class="form-control form-control-sm">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0">End date</label>
            <input type="date" name="end_date" value="{{ request('end_date') }}" class="form-control form-control-sm">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0">Category</label>
            <select name="category_id" class="form-select form-select-sm">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
        </div>
    </form>

    @if (isset($start, $end))
        <p class="text-muted small">Showing budgets overlapping {{ $start->toDateString() }} to {{ $end->toDateString() }}.</p>
    @endif

    @if ($rows->isEmpty())
        <div class="alert alert-secondary">No budgets for this period.</div>
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
                        <tr>
                            <td>{{ $row['budget']->category?->name }}</td>
                            <td>{{ $row['budget']->period_start->toDateString() }} — {{ $row['budget']->period_end->toDateString() }}</td>
                            <td class="text-end">{{ $row['budget']->budget_amount }}</td>
                            <td class="text-end">{{ $row['actual'] }}</td>
                            <td class="text-end">{{ $row['remaining'] }}</td>
                            <td class="text-end">{{ $row['variance'] }}</td>
                            <td class="text-end">{{ $row['utilization_percent'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
