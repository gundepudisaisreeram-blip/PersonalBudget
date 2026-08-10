@extends('layouts.app')

@section('title', $template->name.' — Personal Budget Manager')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">{{ $template->name }}</h1>
            <span class="badge {{ $template->status === 'ACTIVE' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $template->status }}</span>
        </div>
        <div>
            <a href="{{ route('recurring-templates.edit', $template) }}" class="btn btn-outline-secondary">Edit</a>
            @if ($template->status === 'ACTIVE')
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancel-template-modal">Cancel template</button>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Amount</dt>
                <dd class="col-sm-9">{{ $template->amount }}</dd>

                <dt class="col-sm-3">Frequency</dt>
                <dd class="col-sm-9">{{ $template->frequency }} ({{ $template->due_rule }})</dd>

                <dt class="col-sm-3">Category</dt>
                <dd class="col-sm-9">{{ $template->category?->name }}</dd>

                <dt class="col-sm-3">Default account</dt>
                <dd class="col-sm-9">{{ $template->defaultAccount?->name ?? '—' }}</dd>

                <dt class="col-sm-3">Active period</dt>
                <dd class="col-sm-9">{{ $template->starts_on->toDateString() }} — {{ $template->ends_on?->toDateString() ?? 'ongoing' }}</dd>

                <dt class="col-sm-3">Mandatory</dt>
                <dd class="col-sm-9">{{ $template->is_mandatory ? 'Yes' : 'No' }}</dd>
            </dl>
        </div>
    </div>

    <h2 class="h5 mb-3">Historical obligations</h2>
    @if ($obligations->isEmpty())
        <div class="alert alert-secondary">No obligations have been generated for this template yet.</div>
    @else
        <div class="table-responsive">
            <table class="table bg-white">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Due date</th>
                        <th class="text-end">Planned</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($obligations as $obligation)
                        <tr>
                            <td><a href="{{ route('obligations.show', $obligation) }}">{{ $obligation->period_start->toDateString() }} — {{ $obligation->period_end->toDateString() }}</a></td>
                            <td>{{ $obligation->due_date->toDateString() }}</td>
                            <td class="text-end">{{ $obligation->planned_amount }}</td>
                            <td>{{ $obligation->status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <a href="{{ route('recurring-templates.index') }}">&larr; Back to recurring templates</a>

    @if ($template->status === 'ACTIVE')
        @include('partials.confirm-modal', [
            'modalId' => 'cancel-template-modal',
            'title' => 'Cancel this template?',
            'body' => 'Future generation will stop. Historical obligations already generated remain unchanged. This cannot be undone from the interface.',
            'action' => route('recurring-templates.cancel', $template),
            'method' => 'PATCH',
            'confirmLabel' => 'Cancel template',
        ])
    @endif
@endsection
