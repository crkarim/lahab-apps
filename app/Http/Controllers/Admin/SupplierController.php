<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\Branch;
use App\Models\Supplier;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Phase 8.6 — Supplier (vendor) master.
 *
 * Master Admin sees all; branch managers see HQ-wide + their branch
 * and can only create/edit branch-scoped rows.
 *
 * Hard-delete blocked once any expense has been booked against the
 * supplier — deactivate instead. Keeps the audit trail intact.
 */
class SupplierController extends Controller
{
    public function index(Request $request): Renderable
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;
        $branchId = $admin?->branch_id;

        $suppliers = Supplier::query()
            ->with(['branch:id,name'])
            ->withCount('expenses')
            ->when(!$isMaster && $branchId, fn ($q) => $q->where(function ($qq) use ($branchId) {
                $qq->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $branches = Branch::query()->orderBy('name')->get(['id', 'name']);

        $totals = [
            'count'       => $suppliers->where('is_active', true)->count(),
            'outstanding' => (float) $suppliers->where('is_active', true)->sum('outstanding_balance'),
        ];

        return view('admin-views.supplier.index', [
            'suppliers' => $suppliers,
            'branches'  => $branches,
            'totals'    => $totals,
            'isMaster'  => $isMaster,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;

        $validated = $request->validate([
            'name'           => 'required|string|max:120',
            'code'           => 'nullable|string|max:32',
            'contact_person' => 'nullable|string|max:120',
            'phone'          => 'nullable|string|max:30',
            'email'          => 'nullable|email|max:120',
            'address'        => 'nullable|string|max:500',
            'bin'            => 'nullable|string|max:32',
            'payment_terms'  => 'nullable|in:net_0,net_7,net_15,net_30,net_45,net_60',
            'branch_id'      => 'nullable|integer|exists:branches,id',
            'sort_order'     => 'nullable|integer',
            'notes'          => 'nullable|string|max:1000',
        ]);

        $branchId = $validated['branch_id'] ?? null;
        if (!$isMaster) {
            $branchId = $admin?->branch_id;
        }

        Supplier::create([
            'name'                => $validated['name'],
            'code'                => $validated['code'] ?? null,
            'contact_person'      => $validated['contact_person'] ?? null,
            'phone'               => $validated['phone'] ?? null,
            'email'               => $validated['email'] ?? null,
            'address'             => $validated['address'] ?? null,
            'bin'                 => $validated['bin'] ?? null,
            'payment_terms'       => $validated['payment_terms'] ?? 'net_0',
            'branch_id'           => $branchId,
            'outstanding_balance' => 0,
            'sort_order'          => (int) ($validated['sort_order'] ?? 0),
            'notes'               => $validated['notes'] ?? null,
            'is_active'           => true,
        ]);

        return back()->with('success', 'Supplier added · ' . $validated['name']);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;

        $sup = Supplier::find($id);
        if (!$sup) return back()->with('error', 'Supplier not found.');

        if (!$isMaster && $admin?->branch_id && $sup->branch_id !== $admin->branch_id) {
            return back()->with('error', 'You can only edit your branch\'s suppliers.');
        }

        $validated = $request->validate([
            'name'           => 'required|string|max:120',
            'code'           => 'nullable|string|max:32',
            'contact_person' => 'nullable|string|max:120',
            'phone'          => 'nullable|string|max:30',
            'email'          => 'nullable|email|max:120',
            'address'        => 'nullable|string|max:500',
            'bin'            => 'nullable|string|max:32',
            'payment_terms'  => 'nullable|in:net_0,net_7,net_15,net_30,net_45,net_60',
            'sort_order'     => 'nullable|integer',
            'notes'          => 'nullable|string|max:1000',
            'is_active'      => 'nullable|boolean',
        ]);

        $sup->forceFill([
            'name'           => $validated['name'],
            'code'           => $validated['code'] ?? null,
            'contact_person' => $validated['contact_person'] ?? null,
            'phone'          => $validated['phone'] ?? null,
            'email'          => $validated['email'] ?? null,
            'address'        => $validated['address'] ?? null,
            'bin'            => $validated['bin'] ?? null,
            'payment_terms'  => $validated['payment_terms'] ?? $sup->payment_terms,
            'sort_order'     => (int) ($validated['sort_order'] ?? 0),
            'notes'          => $validated['notes'] ?? null,
            'is_active'      => (bool) ($validated['is_active'] ?? true),
        ])->save();

        return back()->with('success', 'Supplier updated · ' . $sup->name);
    }

    public function destroy(int $id): RedirectResponse
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;

        $sup = Supplier::withCount('expenses')->find($id);
        if (!$sup) return back()->with('error', 'Supplier not found.');

        if (!$isMaster && $admin?->branch_id && $sup->branch_id !== $admin->branch_id) {
            return back()->with('error', 'You can only delete your branch\'s suppliers.');
        }

        if ($sup->expenses_count > 0) {
            $sup->is_active = false;
            $sup->save();
            return back()->with('success', $sup->name . ' deactivated (has ' . $sup->expenses_count . ' bill(s) booked — cannot hard-delete).');
        }

        $sup->delete();
        return back()->with('success', 'Supplier deleted.');
    }
}
