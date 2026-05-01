<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PaySlipEmail;
use App\Model\Admin;
use App\Model\BusinessSetting;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\SalaryAdvance;
use App\Services\Payroll\PayrollSummariser;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * HRM Phase 7b — Stream the pay slip PDF for download.
     * Uses the frozen snapshot, so the PDF reflects payroll-run state
     * at lock time, not the live admin record.
     */
    public function downloadPayslipPdf(int $id): Response
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $payslip = Payslip::query()
            ->with(['run', 'branch'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->find($id);
        if (!$payslip) abort(404, 'Payslip not found.');

        $companyName = optional(BusinessSetting::where('key', 'restaurant_name')->first())->value ?? 'Lahab';
        $branchName  = $payslip->branch?->name ?? '';

        $pdf = Pdf::loadView('admin-views.payroll-run.payslip-pdf', [
            'payslip'     => $payslip,
            'run'         => $payslip->run,
            'companyName' => $companyName,
            'branchName'  => $branchName,
        ])->setPaper('a4', 'portrait');

        $snap   = $payslip->employee_snapshot_json ?? [];
        $name   = trim(($snap['f_name'] ?? '') . ' ' . ($snap['l_name'] ?? '')) ?: 'employee';
        $period = optional($payslip->run?->period_from)->format('M_Y') ?: 'period';
        $filename = 'PaySlip_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $name) . '_' . $period . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Send the pay slip PDF to the employee's email on file.
     * Idempotent for the user — they get one email per click; we don't
     * track sends so HR can resend if the employee asks.
     */
    public function emailPayslip(int $id): RedirectResponse
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $payslip = Payslip::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->find($id);
        if (!$payslip) return back()->with('error', 'Payslip not found.');

        $employee = Admin::find($payslip->admin_id);
        if (!$employee || empty($employee->email)) {
            return back()->with('error', 'Employee has no email address on file.');
        }

        try {
            Mail::to($employee->email)->send(new PaySlipEmail($payslip));
        } catch (\Throwable $e) {
            return back()->with('error', 'Email failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Pay slip emailed to ' . $employee->email);
    }

    /**
     * Bank-batch CSV export for a payroll run. Rows grouped by payment
     * method (BANK, MOBILE, CHEQUE, CASH) with a header per group so
     * the company can copy each section into the bank's upload format.
     *
     * Pulls from the *snapshot* (line_items_json + employee_snapshot_json)
     * for amounts, but uses the *current* admin record for bank/wallet
     * info — bank details may have been corrected after the run was
     * locked, and what matters for disbursement is where the money
     * goes today, not what was on file weeks ago.
     */
    public function exportBankCsv(int $runId): StreamedResponse
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $run = PayrollRun::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->find($runId);
        if (!$run) abort(404, 'Payroll run not found.');

        $payslips = Payslip::query()
            ->where('run_id', $run->id)
            ->orderBy('id')
            ->get();

        // Resolve current bank info per employee in one query.
        $adminIds = $payslips->pluck('admin_id')->unique()->all();
        $admins   = Admin::query()->whereIn('id', $adminIds)->get()->keyBy('id');

        // Bucket by payment method.
        $buckets = ['bank' => [], 'mobile' => [], 'cheque' => [], 'cash' => []];
        foreach ($payslips as $ps) {
            $emp    = $admins->get($ps->admin_id);
            $method = $emp?->payment_method ?: 'cash';
            $buckets[$method][] = ['payslip' => $ps, 'admin' => $emp];
        }

        $period   = optional($run->period_from)->format('Y-m-d') . '_' . optional($run->period_to)->format('Y-m-d');
        $filename = 'PayrollRun_' . $run->id . '_' . $period . '_bank.csv';

        $callback = function () use ($buckets, $run) {
            $out = fopen('php://output', 'w');

            $writeHeader = function ($title, array $cols) use ($out) {
                fputcsv($out, []);
                fputcsv($out, ['# ' . $title]);
                fputcsv($out, $cols);
            };

            // Bank section — what most BD banks consume.
            if (!empty($buckets['bank'])) {
                $writeHeader('BANK TRANSFER', [
                    'SL', 'Employee', 'Code', 'Bank', 'Branch', 'Account Name', 'Account Number', 'Routing', 'Net Amount (Tk)', 'Reference',
                ]);
                $sl = 1;
                foreach ($buckets['bank'] as $row) {
                    $ps   = $row['payslip'];
                    $emp  = $row['admin'];
                    $snap = $ps->employee_snapshot_json ?? [];
                    fputcsv($out, [
                        $sl++,
                        trim(($snap['f_name'] ?? '') . ' ' . ($snap['l_name'] ?? '')),
                        $snap['employee_code'] ?? '',
                        $emp?->bank_name ?? '',
                        $emp?->bank_branch ?? '',
                        $emp?->bank_account_name ?? '',
                        $emp?->bank_account_number ?? '',
                        $emp?->bank_routing_number ?? '',
                        number_format((float) $ps->net, 2, '.', ''),
                        $ps->paid_reference ?? '',
                    ]);
                }
            }

            if (!empty($buckets['mobile'])) {
                $writeHeader('MOBILE MONEY (bKash / Nagad / Rocket / Upay)', [
                    'SL', 'Employee', 'Code', 'Provider', 'Wallet Number', 'Net Amount (Tk)', 'Reference',
                ]);
                $sl = 1;
                foreach ($buckets['mobile'] as $row) {
                    $ps   = $row['payslip'];
                    $emp  = $row['admin'];
                    $snap = $ps->employee_snapshot_json ?? [];
                    fputcsv($out, [
                        $sl++,
                        trim(($snap['f_name'] ?? '') . ' ' . ($snap['l_name'] ?? '')),
                        $snap['employee_code'] ?? '',
                        strtoupper($emp?->mobile_provider ?? ''),
                        $emp?->mobile_wallet_number ?? '',
                        number_format((float) $ps->net, 2, '.', ''),
                        $ps->paid_reference ?? '',
                    ]);
                }
            }

            if (!empty($buckets['cheque'])) {
                $writeHeader('CHEQUE', [
                    'SL', 'Employee', 'Code', 'Net Amount (Tk)', 'Cheque Number / Reference',
                ]);
                $sl = 1;
                foreach ($buckets['cheque'] as $row) {
                    $ps   = $row['payslip'];
                    $snap = $ps->employee_snapshot_json ?? [];
                    fputcsv($out, [
                        $sl++,
                        trim(($snap['f_name'] ?? '') . ' ' . ($snap['l_name'] ?? '')),
                        $snap['employee_code'] ?? '',
                        number_format((float) $ps->net, 2, '.', ''),
                        $ps->paid_reference ?? '',
                    ]);
                }
            }

            if (!empty($buckets['cash'])) {
                $writeHeader('CASH', [
                    'SL', 'Employee', 'Code', 'Net Amount (Tk)',
                ]);
                $sl = 1;
                foreach ($buckets['cash'] as $row) {
                    $ps   = $row['payslip'];
                    $snap = $ps->employee_snapshot_json ?? [];
                    fputcsv($out, [
                        $sl++,
                        trim(($snap['f_name'] ?? '') . ' ' . ($snap['l_name'] ?? '')),
                        $snap['employee_code'] ?? '',
                        number_format((float) $ps->net, 2, '.', ''),
                    ]);
                }
            }

            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
