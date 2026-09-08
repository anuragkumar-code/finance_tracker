<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PersonController extends Controller
{
    public function index(): View
    {
        return view('settings.people', [
            'people' => Person::query()->ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:people,name'],
            'relationship' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        Person::create($data + [
            'can_be_payer' => $request->boolean('can_be_payer'),
            'can_be_beneficiary' => $request->boolean('can_be_beneficiary'),
            'is_household' => $request->boolean('is_household'),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return back()->with('status', 'Person added.');
    }

    public function update(Request $request, Person $person): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:people,name,'.$person->id],
            'relationship' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $person->update($data + [
            'can_be_payer' => $request->boolean('can_be_payer'),
            'can_be_beneficiary' => $request->boolean('can_be_beneficiary'),
            'is_household' => $request->boolean('is_household'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Person updated.');
    }

    public function destroy(Person $person): RedirectResponse
    {
        $person->update(['is_active' => false]);

        return back()->with('status', "\"{$person->name}\" hidden from new entries. Past transactions keep them.");
    }
}
