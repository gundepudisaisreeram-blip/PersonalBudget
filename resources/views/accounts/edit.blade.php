@extends('layouts.app')

@section('title', 'Edit account — Personal Budget Manager')

@section('content')
    <h1 class="h3 mb-4">Edit account</h1>

    @if ($account->status === 'CLOSED')
        <div class="alert alert-info">This account is closed. Financial details are frozen; only cosmetic fields can be edited.</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('accounts.update', $account) }}" novalidate>
                @csrf
                @method('PUT')
                @include('accounts._form', ['account' => $account])
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ route('accounts.show', $account) }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
