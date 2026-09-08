@extends('layouts.app')

@section('title', 'Add account')
@section('heading', 'Add an account')
@section('subheading', 'Record where your money sits today')

@section('content')
<div class="row">
    <div class="col-lg-9 col-xl-7">
        <form method="POST" action="{{ route('accounts.store') }}">
            @csrf
            <div class="card">
                <div class="card-body">
                    @include('accounts._form', ['account' => null])
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <button class="btn btn-primary">Save account</button>
                    <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
