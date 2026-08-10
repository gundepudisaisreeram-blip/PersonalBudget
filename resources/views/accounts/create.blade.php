@extends('layouts.app')

@section('title', 'Add account — Personal Budget Manager')

@section('content')
    <h1 class="h3 mb-4">Add account</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('accounts.store') }}" novalidate>
                @csrf
                @include('accounts._form', ['account' => null])
                <button type="submit" class="btn btn-primary">Create account</button>
                <a href="{{ route('accounts.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
