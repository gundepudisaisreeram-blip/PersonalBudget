@extends('layouts.app')

@section('title', 'Payment obligations — Personal Budget Manager')

@section('content')
    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><span class="nav-link active">Obligations</span></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('recurring-templates.index') }}">Recurring Templates</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('budgets.index') }}">Budgets</a></li>
    </ul>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Payment obligations</h1>
        <div>
            <form method="POST" action="{{ route('obligations.generate') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">Generate this month's obligations</button>
            </form>
            <a href="{{ route('obligations.create') }}" class="btn btn-primary">Add one-time obligation</a>
        </div>
    </div>

    <form method="GET" action="{{ route('obligations.index') }}" class="row g-2 mb-4">
        <div class="col-6 col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                @foreach (['PENDING', 'PARTIALLY_PAID', 'PAID', 'SKIPPED', 'CANCELLED'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(strtolower($status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <select name="category_id" class="form-select form-select-sm">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
        </div>
    </form>

    @if ($obligations->isEmpty())
        <div class="alert alert-secondary">No payment obligations for this period.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover bg-white">
                <thead>
                    <tr>
                        <th>Due date</th>
                        <th>Category</th>
                        <th class="text-end">Planned</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($obligations as $obligation)
                        <tr>
                            <td><a href="{{ route('obligations.show', $obligation) }}">{{ $obligation->due_date->toDateString() }}</a></td>
                            <td>{{ $obligation->category?->name }}</td>
                            <td class="text-end">{{ $obligation->planned_amount }}</td>
                            <td>{{ $obligation->status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $obligations->withQueryString()->links() }}
    @endif
@endsection
