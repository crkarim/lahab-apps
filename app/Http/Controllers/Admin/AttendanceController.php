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

    /**
     * Edit an existing attendance row — adjusts clock_in_at,
     * clock_out_at, and notes. Used by branch managers correcting
     * a forgotten clock or a wrong entry. Branch-scoped.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (!$admin) return back()->with('error', 'Not authenticated.');

        $row = AttendanceLog::query()
            ->when($admin->branch_id, fn ($q) => $q->where('branch_id', $admin->branch_id))
            ->find($id);
        if (!$row) return back()->with('error', 'Attendance row not found in your branch.');

        $validated = $request->validate([
            'clock_in_at'  => 'required|date',
            'clock_out_at' => 'nullable|date|after:clock_in_at',
            'notes'        => 'nullable|string|max:500',
        ]);

        $row->clock_in_at  = $validated['clock_in_at'];
        $row->clock_out_at = $validated['clock_out_at'] ?? null;
        // Append edit note rather than overwriting so the audit trail
        // survives — operators can see what was changed and why.
        $editTag = '[edited by ' . trim(($admin->f_name ?? '') . ' ' . ($admin->l_name ?? '')) . ' @ ' . now()->format('d M H:i') . ']';
        $newNote = $validated['notes'] ?? '';
        $row->notes = trim(($row->notes ? $row->notes . ' | ' : '') . $editTag . ($newNote !== '' ? ' ' . $newNote : ''));
        $row->save();

        return back()->with('success', 'Attendance #' . $row->id . ' updated.');
    }

    /**
     * Force-close an open row at a custom timestamp — for staff who
     * forgot to clock out. Quicker than going through the full edit
     * form when you just need to set the out time.
     */
    public function forceClose(Request $request, int $id): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (!$admin) return back()->with('error', 'Not authenticated.');

        $row = AttendanceLog::query()
            ->when($admin->branch_id, fn ($q) => $q->where('branch_id', $admin->branch_id))
            ->find($id);
        if (!$row) return back()->with('error', 'Attendance row not found.');
        if ($row->clock_out_at) return back()->with('error', 'Row is already closed — use Edit instead.');

        $validated = $request->validate([
            'clock_out_at' => 'required|date|after:' . $row->clock_in_at,
            'notes'        => 'nullable|string|max:255',
        ]);

        $row->clock_out_at = $validated['clock_out_at'];
        $tag = '[force-closed by ' . trim(($admin->f_name ?? '') . ' ' . ($admin->l_name ?? '')) . ']';
        $row->notes = trim(($row->notes ? $row->notes . ' | ' : '') . $tag . ($validated['notes'] ?? '' !== '' ? ' ' . ($validated['notes'] ?? '') : ''));
        $row->save();

        return back()->with('success', 'Force-closed attendance #' . $row->id);
    }

    /**
     * Backfill a complete attendance row for a past day — both
     * clock_in_at and clock_out_at supplied. Admin uses this to
     * correct missed entries (staff worked but device was offline,
     * etc.).
     */
    public function backdate(Request $request): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (!$admin) return back()->with('error', 'Not authenticated.');

        $validated = $request->validate([
            'admin_id'     => 'required|integer|exists:admins,id',
            'clock_in_at'  => 'required|date',
            'clock_out_at' => 'nullable|date|after:clock_in_at',
            'notes'        => 'nullable|string|max:500',
        ]);

        $target = Admin::find($validated['admin_id']);
        if (!$target) return back()->with('error', 'Employee not found.');
        if ($admin->branch_id && $target->branch_id !== $admin->branch_id) {
            return back()->with('error', 'That employee is not in your branch.');
        }

        $tag = '[backdated by ' . trim(($admin->f_name ?? '') . ' ' . ($admin->l_name ?? '')) . ']';
        $note = trim($tag . ' ' . ($validated['notes'] ?? ''));

        AttendanceLog::create([
            'admin_id'     => $target->id,
            'branch_id'    => $target->branch_id,
            'clock_in_at'  => $validated['clock_in_at'],
            'clock_out_at' => $validated['clock_out_at'] ?? null,
            'method'       => 'manual',
            'notes'        => $note,
        ]);

        return back()->with('success', 'Backdated entry recorded for ' . trim($target->f_name . ' ' . $target->l_name));
    }

    /** Delete an attendance row entirely. Branch-scoped. */
    public function destroy(int $id): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (!$admin) return back()->with('error', 'Not authenticated.');

        $row = AttendanceLog::query()
            ->when($admin->branch_id, fn ($q) => $q->where('branch_id', $admin->branch_id))
            ->find($id);
        if (!$row) return back()->with('error', 'Attendance row not found.');

        $row->delete();
        return back()->with('success', 'Attendance #' . $id . ' deleted.');
    }
}
