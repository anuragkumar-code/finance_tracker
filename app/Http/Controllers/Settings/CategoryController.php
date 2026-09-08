<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('settings.categories', [
            'categories' => Category::query()->topLevel()->ordered()->with('children')->get(),
            'parents' => Category::query()->topLevel()->ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'applies_to' => ['required', 'in:expense,income,both'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        Category::create($data + ['sort_order' => $data['sort_order'] ?? 0]);

        return back()->with('status', 'Category added.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'applies_to' => ['required', 'in:expense,income,both'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $category->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('status', 'Category updated.');
    }

    /**
     * Categories are retired rather than deleted, so historical transactions
     * keep the label they were filed under.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $category->update(['is_active' => false]);

        return back()->with('status', "\"{$category->name}\" hidden from new entries. Past transactions keep it.");
    }
}
