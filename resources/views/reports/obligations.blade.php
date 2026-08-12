@extends('layouts.app')

@section('title', 'Obligations Report — Personal Budget Manager')

@section('content')
    @include('reports._nav')

    <h1 class="h3 mb-4">Obligations Report</h1>

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
            <label class="form-label small mb-0">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                @foreach (['PENDING', 'PARTIALLY_PAID', 'PAID', 'SKIPPED', 'CANCELLED', 'OVERDUE'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
        </div>
    </form>

    @if (isset($start, $end))
        <p class="text-muted small">Showing obligations overlapping {{ $start->toDateString() }} to {{ $end->toDateString() }}, historical status as of {{ $end->toDateString() }}.</p>
    @endif

    @if ($rows->isEmpty())
        <div class="alert alert-secondary">No obligations for this period.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover bg-white">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Due date</th>
                        <th class="text-end">Planned amount</th>
                        <th>Historical status</th>
                        <th>Overdue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
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
@endsection
