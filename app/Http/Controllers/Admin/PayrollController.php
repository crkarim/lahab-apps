<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\Admin;
use App\Model\Order;
use App\Models\AttendanceLog;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * HRM Phase 3 — Payroll prep view.
 *
 * Read-only computation. No payroll_runs table yet, no pay-slip
 * generation. The job here is to surface, for any pickable date
 * range (default current month), what each employee would be paid
 * IF you ran payroll right now:
 *
 *   gross = prorated_basic + prorated_allowance + tip_share
 *
 *     prorated_basic     = salary_basic     * (days_clocked / calendar_days)
 *     prorated_allowance = salary_allowance * (days_clocked / calendar_days)
 *     tip_share          = sum(orders.tip_amount) where placed_by = me
 *                          AND payment_status = paid AND created_at in range
 *
 * Why this is "prep" not "payroll": real payroll needs locked records
 * (one row per employee per pay period), pay-slip print, deduction
 * line items, advance management, etc. — that's Phase 4. This view
 * tells you whether the underlying data (attendance + tips + salary
 * fields) is good enough BEFORE we commit to the heavier table model.
 */
class PayrollController extends Controller
{
    /** Branch-scoped roster with each employee's computed gross for the range. */
    public function index(Request $request): Renderable
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        [$from, $to] = $this->dateRange($request);

        $staff = Admin::query()
            ->where('status', 1)
            // Hide the master admin from the list — they're not on payroll.
            ->where('admin_role_id', '!=', 1)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('f_name')
            ->get();

        $rows = $staff->map(fn ($e) => $this->summarise($e, $from, $to));

        return view('admin-views.payroll.index', [
            'rows'  => $rows,
            'from'  => $from,
            'to'    => $to,
            'totals' => [
                'gross'      => (float) $rows->sum('gross'),
                'basic'      => (float) $rows->sum('prorated_basic'),
                'allow'      => (float) $rows->sum('prorated_allowance'),
                'tips'       => (float) $rows->sum('tip_share'),
                'deductions' => (float) $rows->sum('prorated_deduction'),
                'advances'   => (float) $rows->sum('advance_recovery'),
                'net'        => (float) $rows->sum('net'),
                'count'      => $rows->count(),
            ],
        ]);
    }

    /** Per-employee deep-dive: attendance rows + tip-by-day + computed totals. */
    public function employee(Request $request, int $id): Renderable|RedirectResponse
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $employee = Admin::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->find($id);
        if (!$employee) {
            return redirect()->route('admin.payroll.index')->with('error', 'Employee not found in your branch.');
        }

        [$from, $to] = $this->dateRange($request);
        $summary = $this->summarise($employee, $from, $to);

        // Attendance rows in range — same shape the attendance detail
        // page uses, copied here so the breakdown is one-stop.
        $attendance = AttendanceLog::query()
            ->where('admin_id', $employee->id)
            ->whereBetween('clock_in_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderByDesc('clock_in_at')
            ->get();

        // Per-order tip rows so the tip total has line-item proof.
        $tipOrders = Order::query()
            ->where('placed_by_admin_id', $employee->id)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('tip_amount', '>', 0)
            ->orderByDesc('created_at')
            ->get(['id', 'kot_number', 'tip_amount', 'order_amount', 'created_at']);

        return view('admin-views.payroll.employee', [
            'employee'   => $employee,
            'summary'    => $summary,
            'attendance' => $attendance,
            'tipOrders'  => $tipOrders,
            'from'       => $from,
            'to'         => $to,
        ]);
    }

    /** Shared math — delegates to PayrollSummariser so the prep view
     *  and the runs flow stay in lock-step. */
    private function summarise(Admin $e, Carbon $from, Carbon $to): array
    {
        return \App\Services\Payroll\PayrollSummariser::for($e, $from, $to);
    }

    /** Resolve the from/to range from query params, defaulting to this month. */
    private function dateRange(Request $request): array
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to   = $request->date('to')   ?? now()->endOfMonth();
        // Guard against backward range — flip silently rather than error.
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }
        return [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
    }
}
