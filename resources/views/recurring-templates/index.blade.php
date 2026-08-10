@extends('layouts.app')

@section('title', 'Recurring templates — Personal Budget Manager')

@section('content')
    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a class="nav-link" href="{{ route('obligations.index') }}">Obligations</a></li>
        <li class="nav-item"><span class="nav-link active">Recurring Templates</span></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('budgets.index') }}">Budgets</a></li>
    </ul>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Recurring templates</h1>
        <a href="{{ route('recurring-templates.create') }}" class="btn btn-primary">Add recurring template</a>
    </div>

    @if ($templates->isEmpty())
        <div class="alert alert-secondary">No recurring templates yet.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover bg-white">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Amount</th>
                        <th>Due rule</th>
                        <th>Category</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($templates as $template)
                        <tr>
                            <td><a href="{{ route('recurring-templates.show', $template) }}">{{ $template->name }}</a></td>
                            <td>{{ $template->amount }}</td>
                            <td>{{ $template->due_rule }}</td>
                            <td>{{ $template->category?->name }}</td>
                            <td>
                                <span class="badge {{ $template->status === 'ACTIVE' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $template->status }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
