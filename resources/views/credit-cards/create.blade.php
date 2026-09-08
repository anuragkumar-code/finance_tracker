@extends('layouts.app')

@section('title', 'Add card')
@section('heading', 'Add a credit card')
@section('subheading', 'Its limit, billing cycle, and what you owe today')

@section('content')
<div class="row">
    <div class="col-lg-9 col-xl-7">
        <form method="POST" action="{{ route('credit-cards.store') }}">
            @csrf
            <div class="card">
                <div class="card-body">
                    @include('credit-cards._form', ['card' => null])
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <button class="btn btn-primary">Save card</button>
                    <a href="{{ route('credit-cards.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
