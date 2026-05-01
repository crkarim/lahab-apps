<?php

namespace App\Console\Commands;

use App\Model\Order;
use App\Services\Accounts\PostOrderPaymentToLedger;
use Illuminate\Console\Command;

/**
 * Phase 8.4 — Backfill paid orders into the cash ledger.
 *
 * Called once after deploy if you want historical sales to appear in
 * /admin/account-transactions and the Daily Fund Report. Idempotent:
 * orders already posted are skipped row-by-row at the service layer.
 *
 * Usage:
 *   php artisan accounts:backfill-orders                # all paid orders
 *   php artisan accounts:backfill-orders --from=2026-04-01
 *   php artisan accounts:backfill-orders --branch=2
 *   php artisan accounts:backfill-orders --dry-run      # count only
 */
class BackfillOrderPayments extends Command
{
    protected $signature = 'accounts:backfill-orders
                            {--from= : Earliest order date (YYYY-MM-DD)}
                            {--to= : Latest order date (YYYY-MM-DD)}
                            {--branch= : Limit to one branch_id}
                            {--dry-run : Count what would post; do not write}';

    protected $description = 'Post historical paid POS orders into the cash ledger.';

    public function handle(): int
    {
        $query = Order::query()
            ->where('payment_status', 'paid')
            ->when($this->option('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($this->option('to'),   fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($this->option('branch'), fn ($q, $b) => $q->where('branch_id', (int) $b))
            ->orderBy('id');

        $total = (clone $query)->count();
        $this->info("Paid orders in scope: {$total}");
        if ($this->option('dry-run')) {
            $this->info('Dry-run — exiting without writes.');
            return self::SUCCESS;
        }
        if ($total === 0) return self::SUCCESS;

        $created = 0;
        $bar = $this->output->createProgressBar($total);
        $query->chunkById(200, function ($chunk) use (&$created, $bar) {
            foreach ($chunk as $order) {
                try {
                    $created += PostOrderPaymentToLedger::for($order);
                } catch (\Throwable $e) {
                    $this->newLine();
                    $this->warn("Order #{$order->id} failed: " . $e->getMessage());
                }
                $bar->advance();
            }
        });
        $bar->finish();
        $this->newLine();
        $this->info("Created {$created} ledger transaction(s).");
        return self::SUCCESS;
    }
}
