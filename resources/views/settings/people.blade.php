@extends('layouts.app')

@section('title', 'People')
@section('heading', 'People')
@section('subheading', 'Who pays, and who the money is for')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Relationship</th>
                            <th class="text-center">Can pay</th>
                            <th class="text-center">Can benefit</th>
                            <th style="width:1%"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($people as $person)
                        <tr class="{{ $person->is_active ? '' : 'opacity-50' }}">
                            <td class="fw-medium">
                                {{ $person->name }}
                                @if ($person->is_household)
                                    <span class="badge text-bg-light border">joint</span>
                                @endif
                            </td>
                            <td class="text-body-secondary">{{ $person->relationship ?: '—' }}</td>
                            <td class="text-center">{{ $person->can_be_payer ? '✓' : '—' }}</td>
                            <td class="text-center">{{ $person->can_be_beneficiary ? '✓' : '—' }}</td>
                            <td>
                                <button class="btn btn-sm btn-link text-decoration-none p-0"
                                        data-bs-toggle="collapse" data-bs-target="#person{{ $person->id }}">Edit</button>
                            </td>
                        </tr>
                        <tr class="collapse" id="person{{ $person->id }}">
                            <td colspan="5" class="bg-light">
                                <form method="POST" action="{{ route('settings.people.update', $person) }}" class="row g-2 align-items-end">
                                    @csrf @method('PUT')
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Name</label>
                                        <input type="text" name="name" value="{{ $person->name }}" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Relationship</label>
                                        <input type="text" name="relationship" value="{{ $person->relationship }}" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-4 d-flex gap-3 pb-1">
                                        <div class="form-check">
                                            <input type="checkbox" name="can_be_payer" value="1" class="form-check-input"
                                                   id="payer{{ $person->id }}" @checked($person->can_be_payer)>
                                            <label class="form-check-label small" for="payer{{ $person->id }}">Pays</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" name="can_be_beneficiary" value="1" class="form-check-input"
                                                   id="benef{{ $person->id }}" @checked($person->can_be_beneficiary)>
                                            <label class="form-check-label small" for="benef{{ $person->id }}">Benefits</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                                   id="pactive{{ $person->id }}" @checked($person->is_active)>
                                            <label class="form-check-label small" for="pactive{{ $person->id }}">Active</label>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-sm btn-primary w-100">Save</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Add a person</div>
            <form method="POST" action="{{ route('settings.people.store') }}" class="card-body">
                @csrf
                <div class="mb-3">
                    <label for="p_name" class="form-label">Name</label>
                    <input type="text" name="name" id="p_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="p_rel" class="form-label">Relationship <span class="text-body-secondary">(optional)</span></label>
                    <input type="text" name="relationship" id="p_rel" class="form-control" placeholder="brother, in-laws…">
                </div>
                <div class="mb-3 d-flex gap-3">
                    <div class="form-check">
                        <input type="checkbox" name="can_be_payer" value="1" class="form-check-input" id="p_payer" checked>
                        <label class="form-check-label" for="p_payer">Can pay</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="can_be_beneficiary" value="1" class="form-check-input" id="p_benef" checked>
                        <label class="form-check-label" for="p_benef">Can benefit</label>
                    </div>
                </div>
                <button class="btn btn-primary w-100">Add person</button>
            </form>
        </div>
    </div>
</div>
@endsection
