@extends('layouts.app')

@section('title', 'Import — Personal Budget Manager')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Statement Import #{{ $import->id }}</h1>
        <span class="badge text-bg-{{ $import->status === 'FAILED' ? 'danger' : ($import->status === 'COMPLETED' ? 'success' : 'secondary') }} fs-6">{{ $import->status }}</span>
    </div>

    <dl class="row">
        <dt class="col-sm-3">Account</dt>
        <dd class="col-sm-9">{{ $import->account->name }}</dd>
        <dt class="col-sm-3">Original file</dt>
        <dd class="col-sm-9">{{ $import->original_filename }} <a href="{{ route('imports.download', $import) }}" class="ms-2">Download</a></dd>
        <dt class="col-sm-3">Rows</dt>
        <dd class="col-sm-9">{{ $import->transaction_count }}</dd>
    </dl>

    @if ($import->status === \App\Models\StatementImport::STATUS_UPLOADED && $import->parser_profile === null)
        <div class="alert alert-info">
            Multiple bank formats matched this file's content. Please select the correct one to continue.
        </div>
        <form method="POST" action="{{ route('imports.select-profile', $import) }}" class="col-md-6">
            @csrf
            <div class="mb-3">
                <select name="parser_profile" class="form-select @error('parser_profile') is-invalid @enderror" required>
                    <option value="">Select a bank format</option>
                    @foreach ($candidates as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('parser_profile')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Continue</button>
        </form>
    @elseif (in_array($import->status, ['QUEUED', 'PARSING', 'VALIDATING']))
        <div class="alert alert-secondary">Processing in the background. Refresh this page to check progress.</div>
    @elseif ($import->status === 'FAILED')
        <div class="alert alert-danger">
            <p class="fw-semibold mb-1">{{ $import->error_payload['message'] ?? 'This import failed.' }}</p>
            @if (! empty($import->error_payload['details']))
                <ul class="mb-0">
                    @foreach ($import->error_payload['details'] as $detail)
                        <li>{{ $detail }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
        <a href="{{ route('imports.create') }}" class="btn btn-primary">Upload a corrected file</a>
    @elseif ($import->status === \App\Models\StatementImport::STATUS_PREVIEW_READY)
        @include('imports.preview')
        <form method="POST" action="{{ route('imports.confirm', $import) }}" class="mt-3">
            @csrf
            <button type="submit" class="btn btn-success">Confirm import</button>
        </form>
    @elseif (in_array($import->status, ['CONFIRMED', 'COMPLETED']))
        <div class="alert alert-success">Import completed.</div>
        @include('imports.preview')
    @endif
@endsection
