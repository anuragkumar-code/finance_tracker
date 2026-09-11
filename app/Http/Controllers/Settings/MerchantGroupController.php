<?php

namespace App\Http\Controllers\Settings;

use App\Enums\MerchantChannel;
use App\Http\Controllers\Controller;
use App\Models\MerchantGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

/**
 * The merchant-group master.
 *
 * Kept alongside categories and people rather than buried in the merchant
 * screen: it is a list the household curates, not a per-merchant setting.
 */
class MerchantGroupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        MerchantGroup::create($this->validated($request) + ['is_active' => true]);

        return back()->with('status', 'Group added.');
    }

    public function update(Request $request, MerchantGroup $group): RedirectResponse
    {
        $group->update($this->validated($request, $group) + [
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Group updated.');
    }

    /**
     * Retired, not deleted.
     *
     * The foreign key is nullOnDelete, so actually deleting a group would
     * quietly unfile every merchant in it. Hiding it keeps the merchants where
     * they are while taking the group out of the picker.
     */
    public function destroy(MerchantGroup $group): RedirectResponse
    {
        $group->update(['is_active' => false]);

        return back()->with('status', "\"{$group->name}\" hidden. The merchants in it keep their group.");
    }

    private function validated(Request $request, ?MerchantGroup $group = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:merchant_groups,name'.($group ? ','.$group->id : '')],
            'default_channel' => ['nullable', new Enum(MerchantChannel::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
    }
}
