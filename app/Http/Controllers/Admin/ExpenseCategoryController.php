<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Phase 8.6 — Expense category CRUD. Master Admin only.
 *
 * Two-level taxonomy. Top-level rows have parent_id NULL; children
 * point at a parent (Utilities → Electricity / Water / Gas).
 *
 * Hard-delete blocked once any expense FKs to the row — deactivate.
 */
class ExpenseCategoryController extends Controller
{
    public function index(Request $request): Renderable
    {
        $categories = ExpenseCategory::query()
            ->withCount('expenses')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Tree shape for the form's parent picker.
        $topLevel = $categories->whereNull('parent_id')->values();

        return view('admin-views.expense-category.index', [
            'categories' => $categories,
            'topLevel'   => $topLevel,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:80',
            'code'        => 'required|string|max:40|alpha_dash|unique:expense_categories,code',
            'parent_id'   => 'nullable|integer|exists:expense_categories,id',
            'color'       => 'nullable|string|max:16',
            'sort_order'  => 'nullable|integer',
            'description' => 'nullable|string|max:500',
        ]);

        ExpenseCategory::create([
            'name'        => $validated['name'],
            'code'        => $validated['code'],
            'parent_id'   => $validated['parent_id'] ?? null,
            'color'       => $validated['color'] ?: '#6A6A70',
            'sort_order'  => (int) ($validated['sort_order'] ?? 0),
            'description' => $validated['description'] ?? null,
            'is_active'   => true,
        ]);

        return back()->with('success', 'Category created · ' . $validated['name']);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $cat = ExpenseCategory::find($id);
        if (!$cat) return back()->with('error', 'Category not found.');

        $validated = $request->validate([
            'name'        => 'required|string|max:80',
            'code'        => 'required|string|max:40|alpha_dash|unique:expense_categories,code,' . $id,
            'parent_id'   => 'nullable|integer|exists:expense_categories,id',
            'color'       => 'nullable|string|max:16',
            'sort_order'  => 'nullable|integer',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
        ]);

        // Prevent self-parenting + circular refs (one-level depth check
        // is enough since we only support a single level of nesting).
        if (($validated['parent_id'] ?? null) == $id) {
            return back()->with('error', 'A category cannot be its own parent.');
        }

        $cat->forceFill([
            'name'        => $validated['name'],
            'code'        => $validated['code'],
            'parent_id'   => $validated['parent_id'] ?? null,
            'color'       => $validated['color'] ?: $cat->color,
            'sort_order'  => (int) ($validated['sort_order'] ?? 0),
            'description' => $validated['description'] ?? null,
            'is_active'   => (bool) ($validated['is_active'] ?? true),
        ])->save();

        return back()->with('success', 'Category updated · ' . $cat->name);
    }

    public function destroy(int $id): RedirectResponse
    {
        $cat = ExpenseCategory::withCount('expenses')->find($id);
        if (!$cat) return back()->with('error', 'Category not found.');

        if ($cat->expenses_count > 0) {
            $cat->is_active = false;
            $cat->save();
            return back()->with('success', $cat->name . ' deactivated (has ' . $cat->expenses_count . ' bill(s)).');
        }

        // Also check for sub-categories
        $childCount = ExpenseCategory::where('parent_id', $id)->count();
        if ($childCount > 0) {
            return back()->with('error', 'Cannot delete: ' . $cat->name . ' has ' . $childCount . ' sub-categor(ies). Reassign or delete them first.');
        }

        $cat->delete();
        return back()->with('success', 'Category deleted.');
    }
}
