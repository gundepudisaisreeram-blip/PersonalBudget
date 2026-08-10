@extends('layouts.app')

@section('title', 'Edit recurring template — Personal Budget Manager')

@section('content')
    <h1 class="h3 mb-4">Edit recurring template</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('recurring-templates.update', $template) }}" novalidate>
                @csrf
                @method('PUT')
                @include('recurring-templates._form')
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ route('recurring-templates.show', $template) }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
