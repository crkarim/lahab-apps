<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\Admin;
use App\Models\SalaryAdvance;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * HRM Phase 4.2 — Advances / loans admin surface.
 *
 * Cashier / branch manager records the advance the moment the cash
 * leaves the till. Recovery is by `recovery_per_run` per payroll run
 * (deducted automatically once Phase 4.3 ships); for now the UI just
 * surfaces what the next run would deduct so the Payroll prep view
 * matches reality.
 */
class SalaryAdvanceController extends Controller
{
    /** Active advances grouped at top, then recent recovered/cancelled. */
    public function index(Request $request): Renderable
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $base = SalaryAdvance::query()
            ->with(['employee:id,f_name,l_name,employee_code,designation', 'recordedBy:id,f_name,l_name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $active = (clone $base)
            ->where('status', 'active')
            ->orderByDesc('taken_at')
            ->get();

        $closed = (clone $base)
            ->whereIn('status', ['recovered', 'cancelled'])
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get();

        $eligibleStaff = Admin::query()
            ->where('status', 1)
            ->where('admin_role_id', '!=', 1)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('f_name')
            ->get(['id', 'f_name', 'l_name', 'designation', 'employee_code']);

        $totalsActive = [
            'count'   => $active->count(),
            'amount'  => (float) $active->sum('amount'),
            'balance' => (float) $active->sum('balance'),
        ];

        return view('admin-views.advance.index', [
            'active'        => $active,
            'closed'        => $closed,
            'eligibleStaff' => $eligibleStaff,
            'totalsActive'  => $totalsActive,
        ]);
    }

    /** Record a new advance — creates the row + sets balance = amount. */
    public function store(Request $request): RedirectResponse
    {
        $admin = auth('admin')->user();

        $validated = $request->validate([
            'admin_id'         => 'required|integer|exists:admins,id',
            'amount'           => 'required|numeric|min:0.01',
            'recovery_per_run' => 'required|numeric|min:0.01',
            'taken_at'         => 'nullable|date',
            'reason'           => 'nullable|string|max:255',
            'notes'            => 'nullable|string|max:1000',
        ]);

        $employee = Admin::find($validated['admin_id']);
        if (!$employee) return back()->with('error', 'Employee not found.');

        // Branch isolation — managers can't record an advance against
        // a foreign-branch employee unless they're Master Admin (no
        // branch filter on their admin row).
        if ($admin?->branch_id && $employee->branch_id !== $admin->branch_id) {
            return back()->with('error', 'That employee is not in your branch.');
        }

        if ((float) $validated['recovery_per_run'] > (float) $validated['amount']) {
            return back()->with('error', 'Recovery per run cannot exceed the advance amount.');
        }

        SalaryAdvance::create([
            'admin_id'             => $employee->id,
            'branch_id'            => $employee->branch_id,
            'amount'               => (float) $validated['amount'],
            'recovery_per_run'     => (float) $validated['recovery_per_run'],
            'balance'              => (float) $validated['amount'], // starts equal to amount
            'taken_at'             => $validated['taken_at'] ?? now()->toDateString(),
            'reason'               => $validated['reason'] ?? null,
            'status'               => 'active',
            'recorded_by_admin_id' => $admin?->id,
            'notes'                => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Advance recorded · Tk ' . number_format((float) $validated['amount'], 2));
    }

    /** Cancel an active advance (e.g. employee paid back in cash directly). */
    public function cancel(Request $request, int $id): RedirectResponse
    {
        $admin = auth('admin')->user();
        $advance = SalaryAdvance::query()
            ->when($admin?->branch_id, fn ($q) => $q->where('branch_id', $admin->branch_id))
            ->find($id);

        if (!$advance) return back()->with('error', 'Advance not found.');
        if ($advance->status !== 'active') return back()->with('error', 'Advance is not active.');

        $note = trim((string) $request->input('notes', ''));
        $advance->forceFill([
            'status' => 'cancelled',
            'notes'  => trim(($advance->notes ? $advance->notes . ' | ' : '') . ($note ?: 'Cancelled by ' . trim(($admin?->f_name ?? '') . ' ' . ($admin?->l_name ?? '')))),
        ])->save();

        return back()->with('success', 'Advance #' . $advance->id . ' cancelled.');
    }

    /**
     * Manual partial recovery — used when an employee returns cash
     * directly outside of payroll. Deducts from balance; if it hits
     * zero, status flips to recovered.
     */
    public function recover(Request $request, int $id): RedirectResponse
    {
        $admin = auth('admin')->user();
        $advance = SalaryAdvance::query()
            ->when($admin?->branch_id, fn ($q) => $q->where('branch_id', $admin->branch_id))
            ->find($id);

        if (!$advance) return back()->with('error', 'Advance not found.');
        if ($advance->status !== 'active') return back()->with('error', 'Advance is not active.');

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes'  => 'nullable|string|max:255',
        ]);

        $amt = (float) $validated['amount'];
        if ($amt > (float) $advance->balance) {
            return back()->with('error', 'Recovery cannot exceed remaining balance · Tk ' . number_format((float) $advance->balance, 2));
        }

        $newBalance = round((float) $advance->balance - $amt, 2);
        $advance->balance = $newBalance;
        if ($newBalance <= 0) {
            $advance->status = 'recovered';
        }
        if (!empty($validated['notes'])) {
            $advance->notes = trim(($advance->notes ? $advance->notes . ' | ' : '') . 'Manual recovery Tk ' . number_format($amt, 2) . ': ' . $validated['notes']);
        }
        $advance->save();

        return back()->with('success', 'Recovered Tk ' . number_format($amt, 2) . ' · balance now Tk ' . number_format($newBalance, 2));
    }
}
