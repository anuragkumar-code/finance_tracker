@extends('layouts.app')

@section('title', 'Categories')
@section('heading', 'Categories')
@section('subheading', 'What you group your spending into')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        @foreach ($categories as $category)
            <div class="card mb-2">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fw-medium {{ $category->is_active ? '' : 'text-body-secondary text-decoration-line-through' }}">
                                {{ $category->name }}
                            </span>
                            <span class="badge text-bg-light border ms-1">{{ $category->applies_to }}</span>
                        </div>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-link text-decoration-none p-0 px-2"
                                    data-bs-toggle="collapse" data-bs-target="#cat{{ $category->id }}">Edit</button>
                            @if ($category->is_active)
                                <form method="POST" action="{{ route('settings.categories.destroy', $category) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger text-decoration-none p-0 px-2">Hide</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if ($category->children->isNotEmpty())
                        <div class="mt-1 small text-body-secondary">
                            {{ $category->children->pluck('name')->join(' · ') }}
                        </div>
                    @endif

                    <div class="collapse mt-2" id="cat{{ $category->id }}">
                        <form method="POST" action="{{ route('settings.categories.update', $category) }}"
                              class="row g-2 align-items-end border-top pt-2">
                            @csrf @method('PUT')
                            <div class="col-sm-5">
                                <label class="form-label small mb-1">Name</label>
                                <input type="text" name="name" value="{{ $category->name }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label small mb-1">Applies to</label>
                                <select name="applies_to" class="form-select form-select-sm">
                                    @foreach (['expense', 'income', 'both'] as $applies)
                                        <option value="{{ $applies }}" @selected($category->applies_to === $applies)>
                                            {{ ucfirst($applies) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-2">
                                <div class="form-check">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                           id="active{{ $category->id }}" @checked($category->is_active)>
                                    <label class="form-check-label small" for="active{{ $category->id }}">Active</label>
                                </div>
                            </div>
                            <div class="col-sm-2">
                                <button class="btn btn-sm btn-primary w-100">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Add a category</div>
            <form method="POST" action="{{ route('settings.categories.store') }}" class="card-body">
                @csrf
                <div class="mb-3">
                    <label for="new_name" class="form-label">Name</label>
                    <input type="text" name="name" id="new_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="parent_id" class="form-label">Sits under <span class="text-body-secondary">(optional)</span></label>
                    <select name="parent_id" id="parent_id" class="form-select">
                        <option value="">Top level</option>
                        @foreach ($parents as $parent)
                            <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label for="applies_to" class="form-label">Applies to</label>
                    <select name="applies_to" id="applies_to" class="form-select">
                        <option value="expense">Expense</option>
                        <option value="income">Income</option>
                        <option value="both">Both</option>
                    </select>
                </div>
                <button class="btn btn-primary w-100">Add category</button>
            </form>
        </div>
    </div>
</div>
@endsection
