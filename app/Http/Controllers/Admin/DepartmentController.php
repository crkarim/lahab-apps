<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\Admin;
use App\Model\Branch;
use App\Models\Department;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * HRM Phase 6.1 — Departments CRUD.
 *
 * Master Admin sees all branches' departments + HQ-wide rows.
 * Branch managers see HQ-wide + their own branch only, and can only
 * create/edit branch-scoped rows; HQ-wide remains Master-Admin-only.
 *
 * Heads are pickable from same-branch (or any-branch for HQ-wide depts);
 * the head row is the org-chart label, not the leave approver.
 */
class DepartmentController extends Controller
{
    public function index(Request $request): Renderable
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;
        $branchId = $admin?->branch_id;

        $departments = Department::query()
            ->with(['branch:id,name', 'head:id,f_name,l_name,employee_code'])
            ->withCount('members')
            ->when(!$isMaster && $branchId, fn ($q) => $q->where(function ($qq) use ($branchId) {
                $qq->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $branches = Branch::query()->orderBy('name')->get(['id', 'name']);

        // Heads pool — active staff in the viewer's branch (Master sees all).
        $heads = Admin::query()
            ->where('status', 1)
            ->when(!$isMaster && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('f_name')
            ->get(['id', 'f_name', 'l_name', 'branch_id', 'employee_code', 'designation']);

        return view('admin-views.department.index', [
            'departments' => $departments,
            'branches'    => $branches,
            'heads'       => $heads,
            'isMaster'    => $isMaster,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;

        $validated = $request->validate([
            'name'          => 'required|string|max:80',
            'code'          => 'required|string|max:32|alpha_dash',
            'branch_id'     => 'nullable|integer|exists:branches,id',
            'head_admin_id' => 'nullable|integer|exists:admins,id',
            'color'         => 'nullable|string|max:16',
            'description'   => 'nullable|string|max:500',
            'sort_order'    => 'nullable|integer',
        ]);

        $branchId = $validated['branch_id'] ?? null;

        // Branch managers can't create HQ-wide rows or rows in other branches.
        if (!$isMaster) {
            if (!$admin?->branch_id) {
                return back()->with('error', 'Only Master Admin can create HQ-wide departments.');
            }
            $branchId = $admin->branch_id;
        }

        // Uniqueness on (code, branch_id) — same code allowed in different branches.
        $exists = Department::query()
            ->where('code', $validated['code'])
            ->where('branch_id', $branchId)
            ->exists();
        if ($exists) {
            return back()->with('error', 'A department with this code already exists in scope.');
        }

        Department::create([
            'name'          => $validated['name'],
            'code'          => $validated['code'],
            'branch_id'     => $branchId,
            'head_admin_id' => $validated['head_admin_id'] ?? null,
            'color'         => $validated['color'] ?: '#6A6A70',
            'description'   => $validated['description'] ?? null,
            'sort_order'    => (int) ($validated['sort_order'] ?? 0),
            'is_active'     => true,
        ]);

        return back()->with('success', 'Department created · ' . $validated['name']);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;

        $dept = Department::find($id);
        if (!$dept) return back()->with('error', 'Department not found.');

        // Branch managers can only edit rows in their own branch
        // (and not HQ-wide rows).
        if (!$isMaster) {
            if (!$dept->branch_id || $dept->branch_id !== $admin?->branch_id) {
                return back()->with('error', 'You can only edit your branch\'s departments.');
            }
        }

        $validated = $request->validate([
            'name'          => 'required|string|max:80',
            'code'          => 'required|string|max:32|alpha_dash',
            'head_admin_id' => 'nullable|integer|exists:admins,id',
            'color'         => 'nullable|string|max:16',
            'description'   => 'nullable|string|max:500',
            'sort_order'    => 'nullable|integer',
            'is_active'     => 'nullable|boolean',
        ]);

        $dept->forceFill([
            'name'          => $validated['name'],
            'code'          => $validated['code'],
            'head_admin_id' => $validated['head_admin_id'] ?? null,
            'color'         => $validated['color'] ?: $dept->color,
            'description'   => $validated['description'] ?? null,
            'sort_order'    => (int) ($validated['sort_order'] ?? 0),
            'is_active'     => (bool) ($validated['is_active'] ?? true),
        ])->save();

        return back()->with('success', 'Department updated · ' . $dept->name);
    }

    /**
     * Soft-delete = deactivate. Hard delete blocked if members exist;
     * forces HR to reassign first so we don't orphan FK references.
     */
    public function destroy(int $id): RedirectResponse
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;

        $dept = Department::withCount('members')->find($id);
        if (!$dept) return back()->with('error', 'Department not found.');

        if (!$isMaster) {
            if (!$dept->branch_id || $dept->branch_id !== $admin?->branch_id) {
                return back()->with('error', 'You can only delete your branch\'s departments.');
            }
        }

        if ($dept->members_count > 0) {
            // Deactivate instead of delete — keeps history intact.
            $dept->is_active = false;
            $dept->save();
            return back()->with('success', $dept->name . ' deactivated (has ' . $dept->members_count . ' employee(s); reassign before hard delete).');
        }

        $dept->delete();
        return back()->with('success', 'Department deleted.');
    }
}
