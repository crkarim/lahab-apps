<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\Admin;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\SalaryAdvance;
use App\Services\Payroll\PayrollSummariser;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * HRM Phase 4.3 — Payroll Runs.
 *
 * Lifecycle:
 *   1. Create draft for a date range. Live preview only — no DB
 *      writes beyond the run row itself.
 *   2. Review the draft (re-computes on every page load).
 *   3. Lock — snapshots all payslips, deducts advance balances. From
 *      this point the numbers are frozen.
 *   4. (optional) Mark individual payslips paid as the cash / bank
 *      transfer goes out. When all are paid → run.status = 'paid'.
 */
class PayrollRunController extends Controller
{
    /** Run list — newest first. Branch-scoped per the viewer. */
    public function index(Request $request): Renderable
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $runs = PayrollRun::query()
            ->with(['branch:id,name', 'createdBy:id,f_name,l_name', 'lockedBy:id,f_name,l_name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('period_from')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin-views.payroll-run.index', [
            'runs' => $runs,
        ]);
    }

    /** Create a new draft run. Defaults the period to last full month. */
    public function store(Request $request): RedirectResponse
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $validated = $request->validate([
            'period_from' => 'required|date',
            'period_to'   => 'required|date|after_or_equal:period_from',
            'notes'       => 'nullable|string|max:500',
        ]);

        // Block overlapping draft runs for the same branch + period.
        $clash = PayrollRun::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'draft')
            ->exists();
        if ($clash) {
            return back()->with('error', 'A draft run already exists for your branch. Lock or delete it before creating another.');
        }

        $run = PayrollRun::create([
            'branch_id'           => $branchId,
            'period_from'         => $validated['period_from'],
            'period_to'           => $validated['period_to'],
            'status'              => 'draft',
            'created_by_admin_id' => $admin?->id,
            'notes'               => $validated['notes'] ?? null,
        ]);

        return redirect()->route('admin.payroll-runs.show', ['id' => $run->id])
            ->with('success', 'Draft run created — review the figures, then lock to commit.');
    }

    /** Show a run — live computation for drafts, frozen rows for locked. */
    public function show(int $id): Renderable|RedirectResponse
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $run = PayrollRun::query()
            ->with(['branch:id,name', 'createdBy:id,f_name,l_name', 'lockedBy:id,f_name,l_name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->find($id);
        if (!$run) {
            return redirect()->route('admin.payroll-runs.index')->with('error', 'Run not found in your branch.');
        }

        if ($run->isDraft()) {
            // Live preview — recompute every load. List of staff in
            // the same branch, summarised over the run's period.
            $staff = Admin::query()
                ->where('status', 1)
                ->where('admin_role_id', '!=', 1)
                ->when($run->branch_id, fn ($q) => $q->where('branch_id', $run->branch_id))
                ->orderBy('f_name')
                ->get();

            $rows = $staff->map(fn ($e) => PayrollSummariser::for($e, $run->period_from, $run->period_to));

            return view('admin-views.payroll-run.show-draft', [
                'run'    => $run,
                'rows'   => $rows,
                'totals' => [
                    'gross'      => (float) $rows->sum('gross'),
                    'deductions' => (float) $rows->sum('prorated_deduction'),
                    'advances'   => (float) $rows->sum('advance_recovery'),
                    'tips'       => (float) $rows->sum('tip_share'),
                    'net'        => (float) $rows->sum('net'),
                    'count'      => $rows->count(),
                ],
            ]);
        }

        // Locked or paid — read frozen payslips.
        $payslips = Payslip::query()
            ->where('run_id', $run->id)
            ->with('employee:id,f_name,l_name,employee_code,designation')
            ->orderBy('id')
            ->get();

        return view('admin-views.payroll-run.show-locked', [
            'run'      => $run,
            'payslips' => $payslips,
        ]);
    }

    /**
     * Lock a draft run — snapshot every employee's payslip into the
     * `payslips` table, deduct advance balances, mark advances
     * recovered if their balance hits zero. All inside one DB
     * transaction so a partial failure rolls back cleanly.
     */
    public function lock(int $id): RedirectResponse
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $run = PayrollRun::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->find($id);
        if (!$run) return back()->with('error', 'Run not found.');
        if (!$run->isDraft()) return back()->with('error', 'Run is already locked.');

        $staff = Admin::query()
            ->where('status', 1)
            ->where('admin_role_id', '!=', 1)
            ->when($run->branch_id, fn ($q) => $q->where('branch_id', $run->branch_id))
            ->get();

        DB::transaction(function () use ($run, $staff, $admin) {
            $totalGross = 0; $totalDed = 0; $totalAdv = 0; $totalTips = 0; $totalNet = 0;

            foreach ($staff as $e) {
                $s = PayrollSummariser::for($e, $run->period_from, $run->period_to);

                Payslip::create([
                    'run_id'             => $run->id,
                    'admin_id'           => $e->id,
                    'branch_id'          => $e->branch_id,
                    'days_clocked'       => $s['days_clocked'],
                    'calendar_days'      => $s['calendar_days'],
                    'attendance_minutes' => $s['attendance_minutes'],
                    'prorated_basic'     => $s['prorated_basic'],
                    'prorated_allowance' => $s['prorated_allowance'],
                    'prorated_deduction' => $s['prorated_deduction'],
                    'tip_share'          => $s['tip_share'],
                    'advance_recovery'   => $s['advance_recovery'],
                    'gross'              => $s['gross'],
                    'net'                => $s['net'],
                    'line_items_json'    => $s['line_items'],
                    'employee_snapshot_json' => [
                        'f_name'          => $e->f_name,
                        'l_name'          => $e->l_name,
                        'employee_code'   => $e->employee_code,
                        'designation'     => $e->designation,
                        'employment_type' => $e->employment_type,
                        'phone'           => $e->phone,
                        'email'           => $e->email,
                    ],
                ]);

                $totalGross += $s['gross'];
                $totalDed   += $s['prorated_deduction'];
                $totalAdv   += $s['advance_recovery'];
                $totalTips  += $s['tip_share'];
                $totalNet   += $s['net'];

                // Reduce salary_advances balances — for each active
                // advance: subtract min(recovery_per_run, balance).
                // If balance hits zero, mark recovered + link to run.
                if ($s['advance_recovery'] > 0) {
                    $advances = SalaryAdvance::where('admin_id', $e->id)
                        ->where('status', 'active')
                        ->where('balance', '>', 0)
                        ->orderBy('taken_at')
                        ->get();
                    foreach ($advances as $adv) {
                        $deduct = min((float) $adv->recovery_per_run, (float) $adv->balance);
                        if ($deduct <= 0) continue;
                        $newBal = round((float) $adv->balance - $deduct, 2);
                        $adv->balance = $newBal;
                        if ($newBal <= 0) {
                            $adv->status = 'recovered';
                            $adv->recovered_by_run_id = $run->id;
                        }
                        $adv->save();
                    }
                }
            }

            $run->forceFill([
                'status'             => 'locked',
                'locked_by_admin_id' => $admin?->id,
                'locked_at'          => now(),
                'total_gross'        => $totalGross,
                'total_deductions'   => $totalDed,
                'total_advances'     => $totalAdv,
                'total_tips'         => $totalTips,
                'total_net'          => $totalNet,
            ])->save();
        });

        return redirect()->route('admin.payroll-runs.show', ['id' => $run->id])
            ->with('success', 'Run locked — payslips snapshotted, advances reduced.');
    }

    /** Delete a draft run (no payslips yet, safe). Locked runs can't be deleted. */
    public function destroy(int $id): RedirectResponse
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $run = PayrollRun::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->find($id);
        if (!$run) return back()->with('error', 'Run not found.');
        if (!$run->isDraft()) return back()->with('error', 'Locked runs cannot be deleted.');

        $run->delete();
        return redirect()->route('admin.payroll-runs.index')->with('success', 'Draft run deleted.');
    }

    /**
     * Mark one payslip as paid. Phase 7a captures method + reference
     * (bank txn id / bKash trxID / cheque #) + who hit the button so
     * disbursement is fully audited.
     */
    public function markPayslipPaid(Request $request, int $id): RedirectResponse
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $payslip = Payslip::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->find($id);
        if (!$payslip) return back()->with('error', 'Payslip not found.');
        if ($payslip->isPaid()) return back()->with('error', 'Payslip is already marked paid.');

        $validated = $request->validate([
            'paid_method'    => 'nullable|in:cash,bank,mobile,cheque',
            'paid_reference' => 'nullable|string|max:80',
            'paid_at'        => 'nullable|date',
            'notes'          => 'nullable|string|max:500',
        ]);

        $method = $validated['paid_method'] ?? 'cash';

        // Reference required for non-cash payments — gives accounting
        // something to reconcile against the bank statement / MFS log.
        if (in_array($method, ['bank', 'mobile', 'cheque'], true) && empty($validated['paid_reference'])) {
            return back()->with('error', 'Payment reference is required for ' . $method . ' payments (bank txn ID, bKash trxID, cheque #, etc.).');
        }

        $payslip->forceFill([
            'paid_at'          => $validated['paid_at'] ?? now(),
            'paid_method'      => $method,
            'paid_reference'   => $validated['paid_reference'] ?? null,
            'paid_by_admin_id' => $admin?->id,
            'notes'            => $validated['notes'] ?? $payslip->notes,
        ])->save();

        // Bubble up to run.status if every slip in the run is now paid.
        $run = $payslip->run;
        if ($run && $run->status === 'locked') {
            $unpaid = Payslip::where('run_id', $run->id)->whereNull('paid_at')->count();
            if ($unpaid === 0) {
                $run->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
            }
        }

        return back()->with('success', 'Marked paid · ' . strtoupper($method) . ($validated['paid_reference'] ?? null ? ' · ref ' . $validated['paid_reference'] : ''));
    }
}
