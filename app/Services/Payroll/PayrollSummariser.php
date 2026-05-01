<?php

namespace App\Services\Payroll;

use App\Model\Admin;
use App\Model\Order;
use App\Models\AdminSalaryLine;
use App\Models\AttendanceLog;
use App\Models\SalaryAdvance;
use App\Models\SalaryComponent;
use Carbon\Carbon;

/**
 * Single source of truth for "what would this employee earn for this
 * date range, right now?". Used by both:
 *   - the live Payroll Estimate view (PayrollController) — re-computes
 *     on every page load so figures track changes to attendance,
 *     salary structure, and tip flow in real time.
 *   - the Payroll Runs flow (PayrollRunController) — used to populate
 *     a draft run, and to snapshot payslip rows when the run is
 *     locked.
 *
 * Does NOT mutate state — pure computation. The lock step is in
 * PayrollRunController where the snapshot rows are written and
 * advance balances reduced.
 */
class PayrollSummariser
{
    /**
     * Compute one employee's pay summary for the date range.
     *
     * @return array{
     *   employee: Admin,
     *   days_clocked: int,
     *   calendar_days: int,
     *   attendance_minutes: int,
     *   line_items: array<int, array{component_id:int, name:string, type:string, full_amount:float, prorated_amount:float}>,
     *   salary_basic_full: float,
     *   salary_allow_full: float,
     *   salary_deduction_full: float,
     *   prorated_basic: float,
     *   prorated_allowance: float,
     *   prorated_deduction: float,
     *   advance_recovery: float,
     *   tip_share: float,
     *   gross: float,
     *   net: float,
     * }
     */
    public static function for(Admin $e, Carbon $from, Carbon $to): array
    {
        // 1. Attendance — distinct calendar days clocked + total minutes.
        $rows = AttendanceLog::query()
            ->where('admin_id', $e->id)
            ->whereBetween('clock_in_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['clock_in_at', 'clock_out_at']);

        $daysClocked = $rows
            ->pluck('clock_in_at')
            ->filter()
            ->map(fn ($d) => $d->toDateString())
            ->unique()
            ->count();

        $totalMinutes = $rows->sum(function ($r) {
            $start = $r->clock_in_at;
            $end   = $r->clock_out_at ?? now();
            if (!$start) return 0;
            return max(0, (int) $start->diffInMinutes($end));
        });

        $calendarDays = max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        $proratio     = min(1.0, $daysClocked / $calendarDays);

        // 2. Salary line items — pull all rows + roll up by type.
        $lineRows = AdminSalaryLine::query()
            ->where('admin_id', $e->id)
            ->with('component')
            ->get();

        $basicId = SalaryComponent::where('name', 'Basic')->value('id');
        $basicFull = 0.0;
        $allowanceFull = 0.0;
        $deductionFull = 0.0;
        $lineItems = [];
        foreach ($lineRows as $r) {
            if (!$r->component) continue;
            $type = $r->component->type;
            $amt  = (float) $r->amount;
            $prorated = round($amt * $proratio, 2);
            $lineItems[] = [
                'component_id'    => $r->component_id,
                'name'            => $r->component->name,
                'type'            => $type,
                'full_amount'     => $amt,
                'prorated_amount' => $prorated,
            ];
            if ($type === 'allowance') {
                $allowanceFull += $amt;
                if ($r->component_id === $basicId) {
                    $basicFull = $amt;
                }
            } else {
                $deductionFull += $amt;
            }
        }

        // Backwards-compat fallback — no line items yet, use the legacy
        // flat columns so older employees don't drop to zero.
        if (count($lineRows) === 0) {
            $allowanceFull = (float) (($e->salary_basic ?? 0) + ($e->salary_allowance ?? 0));
            $basicFull     = (float) ($e->salary_basic ?? 0);
        }

        $proratedBasic     = round($basicFull * $proratio, 2);
        $proratedAllowance = round(($allowanceFull - $basicFull) * $proratio, 2);
        $proratedDeduction = round($deductionFull * $proratio, 2);

        // 3. Tip share — sum of orders.tip_amount for paid orders this
        // employee placed in the range.
        $tipShare = (float) Order::query()
            ->where('placed_by_admin_id', $e->id)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->sum('tip_amount');

        // 4. Projected advance recovery — sum of recovery_per_run
        // capped at remaining balance.
        $advanceRecovery = SalaryAdvance::projectedRecoveryFor($e->id);

        $gross = round($proratedBasic + $proratedAllowance + $tipShare, 2);
        $net   = round($gross - $proratedDeduction - $advanceRecovery, 2);

        return [
            'employee'              => $e,
            'days_clocked'          => $daysClocked,
            'calendar_days'         => $calendarDays,
            'attendance_minutes'    => $totalMinutes,
            'line_items'            => $lineItems,
            'salary_basic_full'     => $basicFull,
            'salary_allow_full'     => max(0, $allowanceFull - $basicFull),
            'salary_deduction_full' => $deductionFull,
            'prorated_basic'        => $proratedBasic,
            'prorated_allowance'    => $proratedAllowance,
            'prorated_deduction'    => $proratedDeduction,
            'advance_recovery'      => $advanceRecovery,
            'tip_share'             => $tipShare,
            'gross'                 => $gross,
            'net'                   => $net,
        ];
    }
}
