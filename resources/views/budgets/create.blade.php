@extends('layouts.app')

@section('title', 'Add budget — Personal Budget Manager')

@section('content')
    <h1 class="h3 mb-4">Add budget</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('budgets.store') }}" novalidate>
                @csrf
                @include('budgets._form', ['budget' => null])
                <button type="submit" class="btn btn-primary">Create budget</button>
                <a href="{{ route('budgets.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
