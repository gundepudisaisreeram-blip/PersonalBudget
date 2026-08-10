@extends('layouts.app')

@section('title', 'Add recurring template — Personal Budget Manager')

@section('content')
    <h1 class="h3 mb-4">Add recurring template</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('recurring-templates.store') }}" novalidate>
                @csrf
                @include('recurring-templates._form', ['template' => null])
                <button type="submit" class="btn btn-primary">Create template</button>
                <a href="{{ route('recurring-templates.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
