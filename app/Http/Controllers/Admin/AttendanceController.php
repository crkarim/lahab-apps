<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\Admin;
use App\Models\AttendanceLog;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * HRM Phase 1 — Attendance ledger admin surface.
 *
 * Three tasks:
 *   1. Today's roster: who clocked in, who's still here, hours so far.
 *   2. Per-employee summary for a date range — feeds payroll once
 *      that lands.
 *   3. Manual clock-in / clock-out for staff who can't open a shift
 *      (waiters, chefs) and for admin corrections (forgot to clock).
 *
 * Auto-clock from shift open/close lives in [`ShiftController`]; the
 * row is created there with `method = shift_open`.
 */
class AttendanceController extends Controller
{
    /** Today at this branch — open rows + closed rows + a "didn't show" list. */
    public function index(Request $request): Renderable
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $today = now()->startOfDay();
        $endOfToday = now()->endOfDay();

        $base = AttendanceLog::query()
            ->with(['employee:id,f_name,l_name,designation,admin_role_id', 'branch:id,name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $openRows = (clone $base)
            ->whereNull('clock_out_at')
            ->orderBy('clock_in_at')
            ->get();

        $closedToday = (clone $base)
            ->whereNotNull('clock_out_at')
            ->whereBetween('clock_in_at', [$today, $endOfToday])
            ->orderBy('clock_in_at')
            ->get();

        // Staff in this branch who didn't clock in today — useful for
        // chasing absences.
        $clockedAdminIds = (clone $base)
            ->whereBetween('clock_in_at', [$today, $endOfToday])
            ->pluck('admin_id')
            ->unique();

        $absent = Admin::query()
            ->where('status', 1)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('id', $clockedAdminIds)
            ->where('id', '!=', $admin?->id) // don't shame the viewer
            ->orderBy('f_name')
            ->get(['id', 'f_name', 'l_name', 'designation']);

        // Listing for "manual clock" form — every active staff at this branch.
        $eligibleStaff = Admin::query()
            ->where('status', 1)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('f_name')
            ->get(['id', 'f_name', 'l_name', 'designation']);

        return view('admin-views.attendance.index', [
            'openRows'      => $openRows,
            'closedToday'   => $closedToday,
            'absent'        => $absent,
            'eligibleStaff' => $eligibleStaff,
            'mineOpen'      => $admin ? AttendanceLog::openFor($admin->id) : null,
        ]);
    }

    /** Per-employee summary across a date window — payroll-ready feed. */
    public function employee(Request $request, int $id): Renderable|RedirectResponse
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $employee = Admin::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->find($id);
        if (!$employee) {
            return redirect()->route('admin.attendance.index')->with('error', 'Employee not found in your branch.');
        }

        $from = $request->date('from') ?? now()->startOfMonth();
        $to   = $request->date('to')   ?? now()->endOfMonth();

        $rows = AttendanceLog::query()
            ->where('admin_id', $employee->id)
            ->whereBetween('clock_in_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderByDesc('clock_in_at')
            ->get();

        $totalMinutes = $rows->sum(fn ($r) => $r->workedMinutes());

        return view('admin-views.attendance.employee', [
            'employee'     => $employee,
            'rows'         => $rows,
            'from'         => $from,
            'to'           => $to,
            'totalMinutes' => $totalMinutes,
        ]);
    }

    /**
     * Manual clock-in. Used by waiters/chefs (no shift to ride on)
     * and by branch managers correcting a forgotten clock.
     */
    public function clockIn(Request $request): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (!$admin) return back()->with('error', 'Not authenticated.');

        $validated = $request->validate([
            // 'self' (default — clocks the current user) OR an admin id
            // when a manager is correcting someone else.
            'admin_id' => 'nullable|integer',
            'notes'    => 'nullable|string|max:255',
        ]);

        $targetId = (int) ($validated['admin_id'] ?? $admin->id);
        $target   = Admin::find($targetId);
        if (!$target) return back()->with('error', 'Employee not found.');

        // Branch isolation — managers can't clock staff from a foreign
        // branch unless they're Master Admin (no branch filter).
        if ($admin->branch_id && $target->branch_id !== $admin->branch_id) {
            return back()->with('error', 'That employee is not in your branch.');
        }

        $existing = AttendanceLog::openFor($target->id);
        if ($existing) {
            return back()->with('error', 'Already clocked in (#' . $existing->id . ').');
        }

        AttendanceLog::create([
            'admin_id'    => $target->id,
            'branch_id'   => $target->branch_id,
            'clock_in_at' => now(),
            'method'      => 'manual',
            'notes'       => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Clocked in · ' . trim($target->f_name . ' ' . $target->l_name));
    }

    /** Manual clock-out. Closes the latest open row for the target. */
    public function clockOut(Request $request): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (!$admin) return back()->with('error', 'Not authenticated.');

        $validated = $request->validate([
            'admin_id' => 'nullable|integer',
            'notes'    => 'nullable|string|max:255',
        ]);

        $targetId = (int) ($validated['admin_id'] ?? $admin->id);
        $target   = Admin::find($targetId);
        if (!$target) return back()->with('error', 'Employee not found.');

        if ($admin->branch_id && $target->branch_id !== $admin->branch_id) {
            return back()->with('error', 'That employee is not in your branch.');
        }

        $row = AttendanceLog::openFor($target->id);
        if (!$row) {
            return back()->with('error', 'No open clock-in for that employee.');
        }

        $row->clock_out_at = now();
        if (!empty($validated['notes'])) {
            $row->notes = trim(($row->notes ? $row->notes . ' | ' : '') . $validated['notes']);
        }
        $row->save();

        return back()->with('success', 'Clocked out · ' . trim($target->f_name . ' ' . $target->l_name));
    }
}
