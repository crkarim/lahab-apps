<?php

namespace App\Services\Accounts;

use App\Model\Order;
use App\Models\AccountTransaction;
use App\Models\CashAccount;
use App\Models\OrderPartialPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 8.4 — Auto-post POS sales into the cash ledger.
 *
 * Walks every OrderPartialPayment row on a paid order, looks up the
 * matching cash account by payment method, and writes one IN-direction
 * AccountTransaction per payment with ref_type='pos_order', ref_id=order_id.
 *
 * Method → account mapping (best match by branch, then HQ-wide):
 *   cash      → first active cash-type account in branch
 *   card      → first active bank-type account in branch
 *   bkash     → first active mfs account where provider=bkash
 *   nagad     → first active mfs account where provider=nagad
 *   rocket    → first active mfs account where provider=rocket
 *   upay      → first active mfs account where provider=upay
 *   wallet_*  → skipped (customer credit, not real money movement)
 *
 * Idempotent — re-running for the same order skips already-posted
 * payment rows, so calling from place_order + later from a backfill
 * command both leave the same end state.
 *
 * Best-effort — if no account matches a method, we log a warning and
 * skip that row (the order itself is already saved + paid). Operator
 * sees the gap in /admin/account-transactions and posts manually OR
 * sets up the missing account and runs the backfill.
 */
class PostOrderPaymentToLedger
{
    /**
     * Post all unposted payments on a paid order to the ledger.
     * Returns count of transactions created.
     */
    public static function for(Order $order): int
    {
        // Only paid orders post — pending/unpaid have nothing to record yet.
        if ($order->payment_status !== 'paid') {
            return 0;
        }

        $payments = OrderPartialPayment::where('order_id', $order->id)->get();
        if ($payments->isEmpty()) {
            return 0;
        }

        // Already-posted method strings on this order. Skip those rows on
        // re-run. We use the description column as the disambiguator since
        // a single order can have multiple payments via different methods
        // (e.g. wallet_payment + cash). ref_id alone doesn't distinguish.
        $alreadyPostedMethods = AccountTransaction::query()
            ->where('ref_type', 'pos_order')
            ->where('ref_id', $order->id)
            ->pluck('description')
            ->map(fn ($d) => self::extractMethodFromDescription($d))
            ->filter()
            ->toArray();

        $created = 0;
        foreach ($payments as $pmt) {
            $method = strtolower((string) $pmt->paid_with);

            // Skip wallet — wallet balance changes are tracked elsewhere
            // (customer wallet ledger), not in the company cash ledger.
            if (str_starts_with($method, 'wallet')) continue;

            // Skip the order's deferred-payment hint methods.
            if (in_array($method, ['pay_after_eating', 'cash_on_delivery', 'pay_after_eat'], true)) {
                continue;
            }

            if (in_array($method, $alreadyPostedMethods, true)) {
                continue;
            }

            $account = self::findAccountFor($method, $order->branch_id);
            if (!$account) {
                Log::warning('Order auto-post: no cash account matches POS method', [
                    'order_id'  => $order->id,
                    'method'    => $method,
                    'branch_id' => $order->branch_id,
                ]);
                continue;
            }

            try {
                DB::transaction(function () use ($order, $pmt, $account, $method, &$created) {
                    AccountTransaction::create([
                        'txn_no'              => AccountTransaction::nextTxnNo(),
                        'account_id'          => $account->id,
                        'direction'           => 'in',
                        'amount'              => (float) $pmt->paid_amount,
                        'charge'              => 0,
                        'vat_input'           => 0,
                        'vat_output'          => 0,
                        'tax_amount'          => 0,
                        'ref_type'            => 'pos_order',
                        'ref_id'              => $order->id,
                        'branch_id'           => $order->branch_id,
                        'description'         => 'POS sale #' . $order->id . ' [' . $method . ']'
                            . ($order->transaction_reference ? ' ref ' . $order->transaction_reference : ''),
                        'transacted_at'       => $order->created_at ?? now(),
                        'recorded_by_admin_id' => $order->placed_by_admin_id,
                    ]);
                    $account->recomputeBalance();
                    $created++;
                });
            } catch (\Throwable $e) {
                Log::error('Order auto-post failed', [
                    'order_id' => $order->id,
                    'method'   => $method,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $created;
    }

    /**
     * Resolve the cash account that should receive a payment for the
     * given POS method. Prefers same-branch accounts, falls back to
     * HQ-wide. Returns null if no candidate exists — caller logs and
     * skips so the order itself isn't blocked.
     */
    public static function findAccountFor(string $method, ?int $branchId): ?CashAccount
    {
        $query = CashAccount::query()->where('is_active', true);

        switch ($method) {
            case 'cash':
                $query->where('type', 'cash');
                break;
            case 'card':
            case 'card_payment':
                $query->where('type', 'bank');
                break;
            case 'bkash':
            case 'nagad':
            case 'rocket':
            case 'upay':
                $query->where('type', 'mfs')->where('provider', $method);
                break;
            default:
                // Unknown method — try by provider equality first; if no
                // match, return null so caller logs the gap.
                $query->where('provider', $method);
        }

        // Prefer branch-matching account; then HQ-wide; then anything else.
        // Branch tills first means each branch's POS lands in its own till
        // even if HQ-wide accounts exist.
        if ($branchId) {
            $branchMatch = (clone $query)->where('branch_id', $branchId)->orderBy('sort_order')->first();
            if ($branchMatch) return $branchMatch;
        }
        $hqMatch = (clone $query)->whereNull('branch_id')->orderBy('sort_order')->first();
        if ($hqMatch) return $hqMatch;

        return $query->orderBy('sort_order')->first();
    }

    /** Extract the method tag from our own description format: "POS sale #N [method]". */
    private static function extractMethodFromDescription(?string $desc): ?string
    {
        if (!$desc) return null;
        if (preg_match('/\[([a-z_]+)\]/i', $desc, $m)) return strtolower($m[1]);
        return null;
    }
}
