<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Loan;
use App\Models\Person;
use App\Services\Reporting\NetWorthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function __construct(
        private readonly NetWorthService $netWorth,
    ) {}

    public function index(): View
    {
        return view('assets.index', $this->formData() + [
            'assets' => Asset::with(['owner', 'linkedLoan'])->orderBy('name')->get(),
            'netWorth' => $this->netWorth->summary(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Asset::create($this->validated($request) + ['is_active' => true]);

        return back()->with('status', 'Asset added.');
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $asset->update($this->validated($request) + [
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Asset updated.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $asset->delete();

        return back()->with('status', "\"{$asset->name}\" removed.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', 'max:50'],
            'owner_id' => ['nullable', 'exists:people,id'],
            // Optional by design: an unvalued asset is still worth recording.
            'current_value' => ['nullable', 'numeric', 'min:0', 'max:99999999999.99'],
            'acquired_on' => ['nullable', 'date'],
            'linked_loan_id' => ['nullable', 'exists:loans,id'],
            'notes' => ['nullable', 'string'],
        ]);

        // Stamp when the value was set, so an old estimate is recognisable as one.
        $data['valued_on'] = ($data['current_value'] ?? null) !== null ? now()->toDateString() : null;

        return $data;
    }

    private function formData(): array
    {
        return [
            'owners' => Person::query()->active()->payers()->ordered()->get(),
            'loans' => Loan::orderBy('name')->get(),
            'types' => Asset::TYPES,
        ];
    }
}
