<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\MerchantGroup;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MerchantController extends Controller
{
    public function index(): View
    {
        return view('settings.merchants', [
            'merchants' => Merchant::query()
                ->with(['group', 'defaultCategory', 'defaultAccount', 'defaultBeneficiary'])
                ->withCount('transactions')
                ->orderBy('name')
                ->get(),
            'groups' => MerchantGroup::query()->ordered()->withCount('merchants')->get(),
            'categories' => Category::query()->active()->ordered()->get(),
            'channels' => \App\Enums\MerchantChannel::cases(),
            'accounts' => Account::query()->active()->orderBy('name')->get(),
            'payers' => Person::query()->active()->payers()->ordered()->get(),
            'beneficiaries' => Person::query()->active()->beneficiaries()->ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Merchant::create($this->validated($request));

        return back()->with('status', 'Merchant added.');
    }

    /**
     * Defaults set here take priority over what quick entry would otherwise
     * learn on its own.
     */
    public function update(Request $request, Merchant $merchant): RedirectResponse
    {
        $merchant->update($this->validated($request, $merchant) + [
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Merchant defaults updated.');
    }

    public function destroy(Merchant $merchant): RedirectResponse
    {
        $merchant->update(['is_active' => false]);

        return back()->with('status', "\"{$merchant->name}\" hidden from new entries.");
    }

    private function validated(Request $request, ?Merchant $merchant = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:merchants,name'.($merchant ? ','.$merchant->id : '')],
            'merchant_group_id' => ['nullable', 'exists:merchant_groups,id'],
            'channel' => ['nullable', new \Illuminate\Validation\Rules\Enum(\App\Enums\MerchantChannel::class)],
            'default_category_id' => ['nullable', 'exists:categories,id'],
            'default_subcategory_id' => ['nullable', 'exists:categories,id'],
            'default_account_id' => ['nullable', 'exists:accounts,id'],
            'default_payer_id' => ['nullable', 'exists:people,id'],
            'default_beneficiary_id' => ['nullable', 'exists:people,id'],
        ]);
    }
}
