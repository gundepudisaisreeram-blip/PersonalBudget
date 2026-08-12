@extends('layouts.app')

@section('title', 'Month Review — Personal Budget Manager')

@section('content')
    @include('reports._nav')

    <h1 class="h3 mb-4">Month Review — {{ $monthStart->format('F Y') }}</h1>
    <p class="text-muted small">Read-only summary. Month Close is not part of Phase 6.</p>

    <form method="GET" class="row g-2 align-items-end mb-4">
        <div class="col-auto">
            <label class="form-label small mb-0">Month</label>
            <input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
        </div>
    </form>

    @include('reports._month-composite', ['review' => $review])
@endsection
