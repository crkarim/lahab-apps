<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * HRM Phase 6.1 — Designations CRUD.
 *
 * Designations are global (not branch-scoped) — a "Waiter" is a waiter
 * regardless of branch. Master Admin only; branch managers consume them
 * via the employee form but don't define them.
 */
class DesignationController extends Controller
{
    public function index(Request $request): Renderable
    {
        $designations = Designation::query()
            ->with(['department:id,name,color'])
            ->withCount('members')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color']);

        return view('admin-views.designation.index', [
            'designations' => $designations,
            'departments'  => $departments,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:80',
            'code'          => 'required|string|max:32|alpha_dash|unique:designations,code',
            'department_id' => 'nullable|integer|exists:departments,id',
            'default_basic' => 'nullable|numeric|min:0',
            'grade'         => 'nullable|string|max:16',
            'notes'         => 'nullable|string|max:500',
            'sort_order'    => 'nullable|integer',
        ]);

        Designation::create([
            'name'          => $validated['name'],
            'code'          => $validated['code'],
            'department_id' => $validated['department_id'] ?? null,
            'default_basic' => $validated['default_basic'] ?? null,
            'grade'         => $validated['grade'] ?? null,
            'notes'         => $validated['notes'] ?? null,
            'sort_order'    => (int) ($validated['sort_order'] ?? 0),
            'is_active'     => true,
        ]);

        return back()->with('success', 'Designation created · ' . $validated['name']);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $des = Designation::find($id);
        if (!$des) return back()->with('error', 'Designation not found.');

        $validated = $request->validate([
            'name'          => 'required|string|max:80',
            'code'          => 'required|string|max:32|alpha_dash|unique:designations,code,' . $id,
            'department_id' => 'nullable|integer|exists:departments,id',
            'default_basic' => 'nullable|numeric|min:0',
            'grade'         => 'nullable|string|max:16',
            'notes'         => 'nullable|string|max:500',
            'sort_order'    => 'nullable|integer',
            'is_active'     => 'nullable|boolean',
        ]);

        $des->forceFill([
            'name'          => $validated['name'],
            'code'          => $validated['code'],
            'department_id' => $validated['department_id'] ?? null,
            'default_basic' => $validated['default_basic'] ?? null,
            'grade'         => $validated['grade'] ?? null,
            'notes'         => $validated['notes'] ?? null,
            'sort_order'    => (int) ($validated['sort_order'] ?? 0),
            'is_active'     => (bool) ($validated['is_active'] ?? true),
        ])->save();

        return back()->with('success', 'Designation updated · ' . $des->name);
    }

    public function destroy(int $id): RedirectResponse
    {
        $des = Designation::withCount('members')->find($id);
        if (!$des) return back()->with('error', 'Designation not found.');

        if ($des->members_count > 0) {
            $des->is_active = false;
            $des->save();
            return back()->with('success', $des->name . ' deactivated (has ' . $des->members_count . ' employee(s); reassign before hard delete).');
        }

        $des->delete();
        return back()->with('success', 'Designation deleted.');
    }
}
