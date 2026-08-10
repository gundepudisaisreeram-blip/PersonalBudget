@extends('layouts.app')

@section('title', 'Categories — Personal Budget Manager')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Categories</h1>
        <a href="{{ route('categories.create') }}" class="btn btn-primary">Add category</a>
    </div>

    @if ($categories->isEmpty())
        <div class="alert alert-secondary">No categories yet.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle bg-white">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Parent</th>
                        <th>Owner</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $category)
                        <tr>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->category_type }}</td>
                            <td>{{ $category->parent?->name ?? '—' }}</td>
                            <td>
                                @if ($category->isSystem())
                                    <span class="badge text-bg-info">System</span>
                                @else
                                    <span class="badge text-bg-light border">Yours</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $category->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                @unless ($category->isSystem())
                                    <a href="{{ route('categories.edit', $category) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    @if ($category->is_active)
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deactivate-category-{{ $category->id }}"
                                        >Deactivate</button>
                                        @include('partials.confirm-modal', [
                                            'modalId' => 'deactivate-category-'.$category->id,
                                            'title' => 'Deactivate this category?',
                                            'body' => 'The category will no longer be selectable for new items. Historical records referencing it are preserved.',
                                            'action' => route('categories.deactivate', $category),
                                            'method' => 'PATCH',
                                            'confirmLabel' => 'Deactivate',
                                        ])
                                    @endif
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
