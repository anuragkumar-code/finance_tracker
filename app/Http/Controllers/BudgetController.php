<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Services\Reporting\BudgetService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function __construct(
        private readonly BudgetService $budgets,
    ) {}

    public function index(Request $request): View
    {
        $month = $request->date('month') ?? now()->startOfMonth();

        return view('budgets.index', [
            'month' => $month,
            'rows' => $this->budgets->comparison($month),
            'totals' => $this->budgets->totals($month),
            'anomalies' => $this->budgets->anomalies($month),
            'categories' => Category::query()->active()->forExpenses()->topLevel()->ordered()->get(),
            'monthsOfHistory' => $this->budgets->monthsOfHistory(),
            'hasEnoughHistory' => $this->budgets->hasEnoughHistory(),
            'monthsNeeded' => BudgetService::MONTHS_FOR_SUGGESTION,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'month' => ['nullable', 'date'],
        ], [
            'amount.gt' => 'A budget needs an amount greater than zero.',
        ]);

        try {
            $this->budgets->setBudget(
                Category::findOrFail($data['category_id']),
                $data['amount'],
                isset($data['month']) ? Carbon::parse($data['month']) : null,
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Budget set. It applies from this month onwards — '
            .'earlier months keep the target they were judged against.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->budgets->removeBudget($category);

        return back()->with('status', "Budget removed for \"{$category->name}\".");
    }

    /** What the household's own history suggests, or an honest refusal. */
    public function suggest(Category $category): JsonResponse
    {
        return response()->json($this->budgets->suggest($category));
    }
}
