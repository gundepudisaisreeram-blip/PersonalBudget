@extends('layouts.app')

@section('title', 'Upload Statement — Personal Budget Manager')

@section('content')
    <h1 class="h3 mb-4">Upload Bank Statement</h1>

    <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="col-md-6">
        @csrf

        <div class="mb-3">
            <label for="account_id" class="form-label">Account</label>
            <select name="account_id" id="account_id" class="form-select @error('account_id') is-invalid @enderror" required>
                <option value="">Select an account</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected(old('account_id') == $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
            @error('account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="file" class="form-label">Statement file (CSV or XLSX, max 5&nbsp;MB)</label>
            <input type="file" name="file" id="file" class="form-control @error('file') is-invalid @enderror" accept=".csv,.xlsx" required>
            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Legacy .xls, OFX, QIF, and PDF are not supported in V1.</div>
        </div>

        <button type="submit" class="btn btn-primary">Upload</button>
        <a href="{{ route('imports.index') }}" class="btn btn-link">Cancel</a>
    </form>
@endsection
