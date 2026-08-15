@extends('layouts.app')

@section('title', 'Statement Imports — Personal Budget Manager')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Statement Imports</h1>
        <a href="{{ route('imports.create') }}" class="btn btn-primary">Upload statement</a>
    </div>

    @if ($imports->isEmpty())
        <div class="alert alert-secondary">No statement imports yet.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover bg-white">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>File</th>
                        <th>Status</th>
                        <th class="text-end">Rows</th>
                        <th>Uploaded</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($imports as $import)
                        <tr>
                            <td><a href="{{ route('imports.show', $import) }}">{{ $import->account->name }}</a></td>
                            <td>{{ $import->original_filename }}</td>
                            <td><span class="badge text-bg-{{ $import->status === 'FAILED' ? 'danger' : ($import->status === 'COMPLETED' ? 'success' : 'secondary') }}">{{ $import->status }}</span></td>
                            <td class="text-end">{{ $import->transaction_count }}</td>
                            <td>{{ $import->created_at->toDayDateTimeString() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $imports->links() }}
    @endif
@endsection
