<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use App\Models\SalaryAdvance;
use App\Services\Accounts\PostHrmPaymentToLedger;
use Illuminate\Console\Command;

/**
 * Phase 8.5c — Backfill HRM payments into the cash ledger.
 *
 * Walks every active salary advance + every paid payslip and asks the
 * service to post a ledger row. Idempotent — rows already posted are
 * skipped. Rows that have no source/destination account stay
 * unposted (operator must edit them and pick an account first); these
 * surface as warnings in the log.
 *
 * Usage:
 *   php artisan accounts:backfill-hrm
 *   php artisan accounts:backfill-hrm --advances-only
 *   php artisan accounts:backfill-hrm --payslips-only
 *   php artisan accounts:backfill-hrm --dry-run
 */
class BackfillHrmPayments extends Command
{
    protected $signature = 'accounts:backfill-hrm
                            {--advances-only : Only backfill salary advances}
                            {--payslips-only : Only backfill payslips}
                            {--dry-run : Count what would post; do not write}';

    protected $description = 'Post historical salary advances + paid payslips into the cash ledger.';

    public function handle(): int
    {
        $only = null;
        if ($this->option('advances-only')) $only = 'advances';
        if ($this->option('payslips-only')) $only = 'payslips';

        $created = 0;

        if ($only !== 'payslips') {
            $advances = SalaryAdvance::where('status', 'active')->whereNotNull('source_account_id')->get();
            $this->info('Active advances with a source account: ' . $advances->count());
            if (!$this->option('dry-run')) {
                foreach ($advances as $a) {
                    try {
                        $created += PostHrmPaymentToLedger::forAdvance($a);
                    } catch (\Throwable $e) {
                        $this->warn('Advance #' . $a->id . ' failed: ' . $e->getMessage());
                    }
                }
            }
        }

        if ($only !== 'advances') {
            $payslips = Payslip::whereNotNull('paid_at')->whereNotNull('paid_from_account_id')->get();
            $this->info('Paid payslips with a paid_from account: ' . $payslips->count());
            if (!$this->option('dry-run')) {
                foreach ($payslips as $p) {
                    try {
                        $created += PostHrmPaymentToLedger::forPayslip($p);
                    } catch (\Throwable $e) {
                        $this->warn('Payslip #' . $p->id . ' failed: ' . $e->getMessage());
                    }
                }
            }
        }

        if ($this->option('dry-run')) {
            $this->info('Dry-run — exiting without writes.');
        } else {
            $this->info('Created ' . $created . ' ledger transaction(s).');
        }

        // Surface anything that's missing an account so HR knows what to fix.
        $unposted = SalaryAdvance::where('status', 'active')->whereNull('source_account_id')->count();
        if ($unposted > 0) $this->warn($unposted . ' active advance(s) have no source_account_id and were skipped.');
        $unpaid = Payslip::whereNotNull('paid_at')->whereNull('paid_from_account_id')->count();
        if ($unpaid > 0) $this->warn($unpaid . ' paid payslip(s) have no paid_from_account_id and were skipped.');

        return self::SUCCESS;
    }
}
