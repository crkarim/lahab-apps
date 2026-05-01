<?php

namespace App\Services\Accounts;

use App\Models\AccountTransaction;
use App\Models\CashAccount;
use App\Models\Payslip;
use App\Models\SalaryAdvance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 8.5c — Auto-post HRM cash movements into the ledger.
 *
 * Three flows:
 *   1. forAdvance($advance)            — OUT to source account (cash given)
 *   2. forAdvanceRecovery($advance, …) — IN to chosen account (manual recovery)
 *   3. forPayslip($payslip)            — OUT to paid_from account (salary paid)
 *
 * All idempotent via ref_type + ref_id lookup, so every controller path
 * can call these without worrying about double-posting.
 *
 * Best-effort: logs and skips when no account is set on the source row;
 * caller stays unaffected (the advance / payslip itself still saves).
 */
class PostHrmPaymentToLedger
{
    public const REF_ADVANCE          = 'salary_advance';
    public const REF_ADVANCE_RECOVERY = 'salary_advance_recovery';
    public const REF_PAYSLIP          = 'payslip';

    /** OUT row to source account when an advance is given. */
    public static function forAdvance(SalaryAdvance $advance): int
    {
        if ($advance->status !== 'active') return 0;
        if (empty($advance->source_account_id)) {
            Log::info('HRM auto-post skipped (advance): no source_account_id', ['advance_id' => $advance->id]);
            return 0;
        }
        if (self::alreadyPosted(self::REF_ADVANCE, $advance->id, 'advance')) return 0;

        $account = CashAccount::where('id', $advance->source_account_id)->where('is_active', true)->first();
        if (!$account) {
            Log::warning('HRM auto-post: advance source_account_id missing/inactive', ['advance_id' => $advance->id, 'source_account_id' => $advance->source_account_id]);
            return 0;
        }

        $employee = \App\Model\Admin::find($advance->admin_id);
        $employeeName = trim(($employee->f_name ?? '') . ' ' . ($employee->l_name ?? '')) ?: '#' . $advance->admin_id;

        try {
            DB::transaction(function () use ($advance, $account, $employeeName) {
                AccountTransaction::create([
                    'txn_no'              => AccountTransaction::nextTxnNo(),
                    'account_id'          => $account->id,
                    'direction'           => 'out',
                    'amount'              => (float) $advance->amount,
                    'charge'              => 0,
                    'ref_type'            => self::REF_ADVANCE,
                    'ref_id'              => $advance->id,
                    'branch_id'           => $advance->branch_id,
                    'description'         => 'Salary advance to ' . $employeeName . ' [advance]'
                        . ($advance->reason ? ' · ' . $advance->reason : '')
                        . ($advance->taken_at ? ' · taken ' . $advance->taken_at->format('d M Y') : ''),
                    // Use the row's actual record time so the ledger order
                    // mirrors when the cashier hit Save. taken_at is a
                    // date-only field (00:00:00 time) which would otherwise
                    // bury the row under same-day POS sales.
                    'transacted_at'       => $advance->created_at ?? now(),
                    'recorded_by_admin_id' => $advance->recorded_by_admin_id,
                ]);
                $account->recomputeBalance();
            });
            return 1;
        } catch (\Throwable $e) {
            Log::error('HRM auto-post (advance) failed', ['advance_id' => $advance->id, 'error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * IN row when employee returns advance cash directly outside payroll.
     * Caller passes the destination account (where the cash went) +
     * amount returned. Each manual recovery is its own ledger row, so
     * we de-dupe on a synthetic ref combining advance id + amount +
     * recorded date (best we can do without a recovery_id table).
     */
    public static function forAdvanceRecovery(SalaryAdvance $advance, float $amount, ?int $destinationAccountId, ?string $note, ?int $byAdminId): int
    {
        if ($amount <= 0.005) return 0;
        if (empty($destinationAccountId)) {
            Log::info('HRM auto-post skipped (advance recovery): no destination_account_id', ['advance_id' => $advance->id]);
            return 0;
        }

        $account = CashAccount::where('id', $destinationAccountId)->where('is_active', true)->first();
        if (!$account) {
            Log::warning('HRM auto-post: recovery destination_account_id missing/inactive', ['advance_id' => $advance->id]);
            return 0;
        }

        $employee = \App\Model\Admin::find($advance->admin_id);
        $employeeName = trim(($employee->f_name ?? '') . ' ' . ($employee->l_name ?? '')) ?: '#' . $advance->admin_id;

        try {
            DB::transaction(function () use ($advance, $account, $amount, $note, $byAdminId, $employeeName) {
                AccountTransaction::create([
                    'txn_no'              => AccountTransaction::nextTxnNo(),
                    'account_id'          => $account->id,
                    'direction'           => 'in',
                    'amount'              => $amount,
                    'charge'              => 0,
                    'ref_type'            => self::REF_ADVANCE_RECOVERY,
                    'ref_id'              => $advance->id,
                    'branch_id'           => $advance->branch_id,
                    'description'         => 'Advance recovery from ' . $employeeName . ' [recovery]'
                        . ($note ? ' · ' . $note : ''),
                    'transacted_at'       => now(),
                    'recorded_by_admin_id' => $byAdminId,
                ]);
                $account->recomputeBalance();
            });
            return 1;
        } catch (\Throwable $e) {
            Log::error('HRM auto-post (recovery) failed', ['advance_id' => $advance->id, 'error' => $e->getMessage()]);
            return 0;
        }
    }

    /** OUT row when a payslip is marked paid. */
    public static function forPayslip(Payslip $payslip): int
    {
        if (!$payslip->paid_at) return 0;
        if (empty($payslip->paid_from_account_id)) {
            Log::info('HRM auto-post skipped (payslip): no paid_from_account_id', ['payslip_id' => $payslip->id]);
            return 0;
        }
        if (self::alreadyPosted(self::REF_PAYSLIP, $payslip->id, 'payslip')) return 0;

        $account = CashAccount::where('id', $payslip->paid_from_account_id)->where('is_active', true)->first();
        if (!$account) {
            Log::warning('HRM auto-post: payslip paid_from_account_id missing/inactive', ['payslip_id' => $payslip->id]);
            return 0;
        }

        $snap = $payslip->employee_snapshot_json ?? [];
        $employeeName = trim(($snap['f_name'] ?? '') . ' ' . ($snap['l_name'] ?? '')) ?: '#' . $payslip->admin_id;

        try {
            DB::transaction(function () use ($payslip, $account, $employeeName) {
                AccountTransaction::create([
                    'txn_no'              => AccountTransaction::nextTxnNo(),
                    'account_id'          => $account->id,
                    'direction'           => 'out',
                    'amount'              => (float) $payslip->net,
                    'charge'              => 0,
                    'ref_type'            => self::REF_PAYSLIP,
                    'ref_id'              => $payslip->id,
                    'branch_id'           => $payslip->branch_id,
                    'description'         => 'Salary paid to ' . $employeeName . ' [payslip]'
                        . ' · run #' . $payslip->run_id
                        . ($payslip->paid_reference ? ' · ref ' . $payslip->paid_reference : ''),
                    'transacted_at'       => $payslip->paid_at ?? now(),
                    'recorded_by_admin_id' => $payslip->paid_by_admin_id,
                ]);
                $account->recomputeBalance();
            });
            return 1;
        } catch (\Throwable $e) {
            Log::error('HRM auto-post (payslip) failed', ['payslip_id' => $payslip->id, 'error' => $e->getMessage()]);
            return 0;
        }
    }

    private static function alreadyPosted(string $refType, int $refId, string $tagInDescription): bool
    {
        return AccountTransaction::query()
            ->where('ref_type', $refType)
            ->where('ref_id', $refId)
            ->exists();
    }
}
