@extends('layouts.app')

@section('title', 'Merchants')
@section('heading', 'Merchants')
@section('subheading', 'What each place usually means, so quick entry can fill itself in')

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Merchant</th>
                            <th>Usually</th>
                            <th class="text-end">Entries</th>
                            <th style="width:1%"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($merchants as $merchant)
                        <tr class="{{ $merchant->is_active ? '' : 'opacity-50' }}">
                            <td class="fw-medium">{{ $merchant->name }}</td>
                            <td class="small text-body-secondary">
                                <span class="badge text-bg-{{ $merchant->channel->badgeClass() }}">{{ $merchant->channel->label() }}</span>
                                {{ $merchant->defaultCategory?->name ?: 'No category' }}
                                @if ($merchant->defaultAccount) · {{ $merchant->defaultAccount->name }} @endif
                                @if ($merchant->defaultBeneficiary) · for {{ $merchant->defaultBeneficiary->name }} @endif
                            </td>
                            <td class="text-end text-body-secondary">{{ $merchant->transactions_count }}</td>
                            <td>
                                <button class="btn btn-sm btn-link text-decoration-none p-0"
                                        data-bs-toggle="collapse" data-bs-target="#m{{ $merchant->id }}">Edit</button>
                            </td>
                        </tr>
                        <tr class="collapse" id="m{{ $merchant->id }}">
                            <td colspan="4" class="bg-light">
                                <form method="POST" action="{{ route('settings.merchants.update', $merchant) }}" class="row g-2 align-items-end">
                                    @csrf @method('PUT')
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Name</label>
                                        <input type="text" name="name" value="{{ $merchant->name }}" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">How you buy</label>
                                        <select name="channel" class="form-select form-select-sm">
                                            @foreach ($channels as $channel)
                                                <option value="{{ $channel->value }}"
                                                        @selected($merchant->channel === $channel)>
                                                    {{ $channel->label() }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Category</label>
                                        <select name="default_category_id" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}" @selected($merchant->default_category_id == $category->id)>
                                                    {{ $category->full_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Account</label>
                                        <select name="default_account_id" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach ($accounts as $account)
                                                <option value="{{ $account->id }}" @selected($merchant->default_account_id == $account->id)>
                                                    {{ $account->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1">For</label>
                                        <select name="default_beneficiary_id" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach ($beneficiaries as $person)
                                                <option value="{{ $person->id }}" @selected($merchant->default_beneficiary_id == $person->id)>
                                                    {{ $person->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <button class="btn btn-sm btn-primary w-100">Save</button>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                                   id="mactive{{ $merchant->id }}" @checked($merchant->is_active)>
                                            <label class="form-check-label small" for="mactive{{ $merchant->id }}">Active</label>
                                        </div>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty-state">
                                No merchants yet — they are created automatically as you type them into Quick Entry.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Add a merchant</div>
            <form method="POST" action="{{ route('settings.merchants.store') }}" class="card-body">
                @csrf
                <div class="mb-3">
                    <label for="m_name" class="form-label">Name</label>
                    <input type="text" name="name" id="m_name" class="form-control" required placeholder="Amazon">
                </div>
                <div class="mb-3">
                    <label for="m_channel" class="form-label">How you buy from them</label>
                    <select name="channel" id="m_channel" class="form-select">
                        @foreach ($channels as $channel)
                            <option value="{{ $channel->value }}" @selected($channel->value === 'offline')>
                                {{ $channel->label() }} — {{ $channel->hint() }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        Drives the quick-commerce and online-shopping figures in reports.
                    </div>
                </div>
                <div class="mb-3">
                    <label for="m_cat" class="form-label">Usual category</label>
                    <select name="default_category_id" id="m_cat" class="form-select">
                        <option value="">—</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label for="m_acc" class="form-label">Usual account</label>
                    <select name="default_account_id" id="m_acc" class="form-select">
                        <option value="">—</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary w-100">Add merchant</button>
            </form>
        </div>
    </div>
</div>
@endsection
