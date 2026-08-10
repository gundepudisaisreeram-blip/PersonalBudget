@extends('layouts.app')

@section('title', 'Add category — Personal Budget Manager')

@section('content')
    <h1 class="h3 mb-4">Add category</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('categories.store') }}" novalidate>
                @csrf
                @include('categories._form', ['category' => null, 'parentOptions' => $parentOptions])
                <button type="submit" class="btn btn-primary">Create category</button>
                <a href="{{ route('categories.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
