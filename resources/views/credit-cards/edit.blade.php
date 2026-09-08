@extends('layouts.app')

@section('title', 'Edit card')
@section('heading', 'Edit ' . $card->card_name)

@section('content')
<div class="row">
    <div class="col-lg-9 col-xl-7">
        <form method="POST" action="{{ route('credit-cards.update', $card) }}">
            @csrf
            @method('PUT')
            <div class="card">
                <div class="card-body">
                    @include('credit-cards._form')
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <button class="btn btn-primary">Save changes</button>
                    <a href="{{ route('credit-cards.show', $card) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
