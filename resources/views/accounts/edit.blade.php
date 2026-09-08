@extends('layouts.app')

@section('title', 'Edit account')
@section('heading', 'Edit ' . $account->name)

@section('content')
<div class="row">
    <div class="col-lg-9 col-xl-7">
        <form method="POST" action="{{ route('accounts.update', $account) }}">
            @csrf
            @method('PUT')
            <div class="card">
                <div class="card-body">
                    @include('accounts._form')
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <button class="btn btn-primary">Save changes</button>
                    <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <a href="{{ route('accounts.show', $account) }}" class="btn btn-link ms-auto">View ledger</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
