@extends('layouts.app')

@section('title', 'Reports — Personal Budget Manager')

@section('content')
    @include('reports._nav')

    <h1 class="h3 mb-4">Reports</h1>
    <p class="text-muted">Choose a report above. Every report is read-only and reconciles to your underlying ledger data.</p>
@endsection
