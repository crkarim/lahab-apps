<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\Admin;
use App\Models\WorkSchedule;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Per-employee work schedule editor — drives the late / early /
 * overtime classification on attendance.
 *
 * One employee, seven rows (Sun..Sat). Each row: shift_start,
 * shift_end, off_day flag, break_minutes, grace_minutes. Save POSTs
 * the full 7-day grid and the controller upserts each row.
 *
 * "Apply BD default" button seeds Sun-Thu + Sat 09:00→18:00 with
 * Friday off (BD Labour Act Sec 100/103 framing).
 */
class WorkScheduleController extends Controller
{
    public function edit(int $id): Renderable|RedirectResponse
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $employee = Admin::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->find($id);
        if (!$employee) {
            return redirect()->route('admin.employee.list')->with('error', 'Employee not found in your branch.');
        }

        // Load existing rows keyed by day_of_week — the form will
        // render a row for each day even if no row exists yet.
        $rows = WorkSchedule::where('admin_id', $employee->id)
            ->orderBy('day_of_week')
            ->get()
            ->keyBy('day_of_week');

        return view('admin-views.employee.schedule', [
            'employee' => $employee,
            'rows'     => $rows,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $employee = Admin::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->find($id);
        if (!$employee) return back()->with('error', 'Employee not found in your branch.');

        $validated = $request->validate([
            'days'                  => 'required|array',
            'days.*.shift_start'    => 'nullable|date_format:H:i',
            'days.*.shift_end'      => 'nullable|date_format:H:i',
            'days.*.is_off_day'     => 'nullable|boolean',
            'days.*.break_minutes'  => 'nullable|integer|min:0|max:480',
            'days.*.grace_minutes'  => 'nullable|integer|min:0|max:60',
        ]);

        foreach ($validated['days'] as $dow => $row) {
            $dow = (int) $dow;
            if ($dow < 1 || $dow > 7) continue;

            $isOff = (bool) ($row['is_off_day'] ?? false);
            $start = $isOff ? null : ($row['shift_start'] ?? null);
            $end   = $isOff ? null : ($row['shift_end']   ?? null);

            // Validate times only when not off-day — both must be
            // set for the row to be useful.
            if (!$isOff && (!$start || !$end)) {
                // Skip empty rows quietly — operator may still be
                // filling them in.
                continue;
            }

            WorkSchedule::updateOrCreate(
                ['admin_id' => $employee->id, 'day_of_week' => $dow],
                [
                    'shift_start'   => $start,
                    'shift_end'     => $end,
                    'is_off_day'    => $isOff,
                    'break_minutes' => (int) ($row['break_minutes'] ?? 60),
                    'grace_minutes' => (int) ($row['grace_minutes'] ?? 10),
                ]
            );
        }

        return back()->with('success', 'Schedule saved for ' . trim(($employee->f_name ?? '') . ' ' . ($employee->l_name ?? '')) . '.');
    }

    /**
     * Apply the BD Labour Act default schedule (Sun-Thu + Sat
     * 09:00→18:00, Friday off, 60 min break, 10 min grace).
     * Overwrites any existing rows for this employee.
     */
    public function applyDefault(int $id): RedirectResponse
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $employee = Admin::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->find($id);
        if (!$employee) return back()->with('error', 'Employee not found in your branch.');

        foreach (WorkSchedule::BD_DEFAULT as $dow => $cfg) {
            WorkSchedule::updateOrCreate(
                ['admin_id' => $employee->id, 'day_of_week' => $dow],
                [
                    'shift_start'   => $cfg['shift_start'],
                    'shift_end'     => $cfg['shift_end'],
                    'is_off_day'    => $cfg['is_off_day'],
                    'break_minutes' => 60,
                    'grace_minutes' => 10,
                ]
            );
        }

        return back()->with('success', 'BD default schedule applied (8h/day, 6 days/week, Friday off).');
    }
}
