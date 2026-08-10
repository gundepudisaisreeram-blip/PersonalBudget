@extends('layouts.app')

@section('title', 'Edit budget — Personal Budget Manager')

@section('content')
    <h1 class="h3 mb-4">Edit budget</h1>

    <div class="alert alert-secondary">
        Current utilization for this period: <strong>{{ $utilization }}</strong> of {{ $budget->budget_amount }}
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('budgets.update', $budget) }}" novalidate>
                @csrf
                @method('PUT')
                @include('budgets._form')
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ route('budgets.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
