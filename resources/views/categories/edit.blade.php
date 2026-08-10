@extends('layouts.app')

@section('title', 'Edit category — Personal Budget Manager')

@section('content')
    <h1 class="h3 mb-4">Edit category</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('categories.update', $category) }}" novalidate>
                @csrf
                @method('PUT')
                @include('categories._form', ['category' => $category, 'parentOptions' => $parentOptions])
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ route('categories.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
