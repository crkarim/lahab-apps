<?php

namespace App\Services\Accounts;

use App\Model\Order;
use App\Models\AccountTransaction;
use App\Models\CashAccount;
use App\Models\OrderPartialPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
            $rawMethod = (string) $pmt->paid_with;
            // Resolve `offline:N` to the offline_payment_methods row's
            // method_name (lower-cased), so admins can use the existing
            // offline-payment configuration as their payment-channel
            // catalogue and we still find the right cash account.
            $method = self::resolveMethod($rawMethod);

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

            // Phase 8.5 — when the cashier picked a specific cash account
            // at checkout, use it verbatim. This bypasses the fuzzy lookup
            // entirely so a Card payment to EBL doesn't accidentally land
            // in DBBL because EBL happened to sort later. Fallback to
            // findAccountFor() for legacy rows where cash_account_id is null.
            if (!empty($pmt->cash_account_id)) {
                $account = CashAccount::where('id', $pmt->cash_account_id)
                    ->where('is_active', true)
                    ->first();
                if (!$account) {
                    Log::warning('Order auto-post: explicit cash_account_id resolves to inactive/missing account', [
                        'order_id'        => $order->id,
                        'cash_account_id' => $pmt->cash_account_id,
                    ]);
                    continue;
                }
            } else {
                $account = self::findAccountFor($method, $order->branch_id);
            }

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

        // Phase 8.5 — Change handling. When the customer over-paid, the
        // cashier handed back cash; without an explicit OUT row the
        // ledger thinks we received more than the actual order amount.
        // Post a single CASH OUT row tagged ref_type='pos_order_change'
        // so the cash drawer reconciles and re-runs are idempotent.
        $created += self::postChangeIfAny($order, $payments);

        return $created;
    }

    /**
     * If the order's non-wallet payments exceed the order amount, post
     * the difference as cash OUT (change returned to customer). Picks
     * the order's branch's cash till. Idempotent via ref_type lookup.
     */
    private static function postChangeIfAny(Order $order, $payments): int
    {
        $totalPaid = (float) $payments
            ->reject(fn ($p) => str_starts_with(strtolower((string) $p->paid_with), 'wallet'))
            ->sum('paid_amount');
        $change = round(max(0, $totalPaid - (float) $order->order_amount), 2);
        if ($change < 0.005) return 0;

        $alreadyPosted = AccountTransaction::query()
            ->where('ref_type', 'pos_order_change')
            ->where('ref_id', $order->id)
            ->exists();
        if ($alreadyPosted) return 0;

        $cashAcc = self::findAccountFor('cash', $order->branch_id);
        if (!$cashAcc) {
            Log::warning('Order auto-post: change exceeds payments but no cash till in branch — change not posted', [
                'order_id'  => $order->id,
                'change'    => $change,
                'branch_id' => $order->branch_id,
            ]);
            return 0;
        }

        try {
            DB::transaction(function () use ($cashAcc, $change, $order) {
                AccountTransaction::create([
                    'txn_no'              => AccountTransaction::nextTxnNo(),
                    'account_id'          => $cashAcc->id,
                    'direction'           => 'out',
                    'amount'              => $change,
                    'charge'              => 0,
                    'ref_type'            => 'pos_order_change',
                    'ref_id'              => $order->id,
                    'branch_id'           => $order->branch_id,
                    'description'         => 'Change returned to customer for order #' . $order->id,
                    'transacted_at'       => $order->created_at ?? now(),
                    'recorded_by_admin_id' => $order->placed_by_admin_id,
                ]);
                $cashAcc->recomputeBalance();
            });
            return 1;
        } catch (\Throwable $e) {
            Log::error('Order auto-post: change post failed', [
                'order_id' => $order->id,
                'change'   => $change,
                'error'    => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Resolve the cash account that should receive a payment for the
     * given POS method. Tries (in order):
     *   1. Hard-typed methods (cash → cash account; card → bank account)
     *   2. provider field exact match (case-insensitive) — current accounts
     *      where the operator filled in "Provider"
     *   3. code field starts with the method name (e.g. method=bkash
     *      matches code='bkash-payment' / 'bkash_owner')
     *   4. name LIKE method (case-insensitive — name='bkash' matches)
     *
     * Each step prefers branch-matching, then HQ-wide, then anything.
     * Returns null only if nothing whatsoever resembles the method.
     */
    public static function findAccountFor(string $method, ?int $branchId): ?CashAccount
    {
        $method = strtolower(trim($method));
        if ($method === '') return null;

        // 1. Hard-typed: cash → first cash account; card → first bank account.
        if ($method === 'cash') {
            return self::pickByQuery(
                CashAccount::query()->where('is_active', true)->where('type', 'cash'),
                $branchId
            );
        }
        if (in_array($method, ['card', 'card_payment'], true)) {
            return self::pickByQuery(
                CashAccount::query()->where('is_active', true)->where('type', 'bank'),
                $branchId
            );
        }

        // 2. Provider exact match — accounts where Provider field was set
        //    explicitly (e.g. "bkash" / "DBBL").
        $byProvider = self::pickByQuery(
            CashAccount::query()->where('is_active', true)->whereRaw('LOWER(provider) = ?', [$method]),
            $branchId
        );
        if ($byProvider) return $byProvider;

        // 3. Code prefix — handles "bkash-payment", "bkash_owner", etc.
        $byCode = self::pickByQuery(
            CashAccount::query()->where('is_active', true)->where('code', 'LIKE', $method . '%'),
            $branchId
        );
        if ($byCode) return $byCode;

        // 4. Name LIKE — last resort, picks up "bKash Personal", "bkash 02"...
        $byName = self::pickByQuery(
            CashAccount::query()->where('is_active', true)->where('name', 'LIKE', '%' . $method . '%'),
            $branchId
        );
        if ($byName) return $byName;

        return null;
    }

    /**
     * Branch-prefer, HQ-fallback, anything-else order from a base query.
     * Centralised so all the matching paths above use the same
     * preference rule.
     */
    private static function pickByQuery($query, ?int $branchId): ?CashAccount
    {
        if ($branchId) {
            $hit = (clone $query)->where('branch_id', $branchId)->orderBy('sort_order')->first();
            if ($hit) return $hit;
        }
        $hit = (clone $query)->whereNull('branch_id')->orderBy('sort_order')->first();
        if ($hit) return $hit;

        return $query->orderBy('sort_order')->first();
    }

    /**
     * Translate raw paid_with strings into a canonical method name the
     * matcher can use. Currently handles:
     *   - `offline:N` → the matching offline_payment_methods row's method_name
     *   - everything else → strtolower
     */
    public static function resolveMethod(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') return '';

        if (str_starts_with($raw, 'offline:')) {
            $id = (int) substr($raw, strlen('offline:'));
            if ($id > 0 && Schema::hasTable('offline_payment_methods')) {
                $name = DB::table('offline_payment_methods')->where('id', $id)->value('method_name');
                if ($name) return strtolower(trim((string) $name));
            }
        }

        return strtolower($raw);
    }

    /** Extract the method tag from our own description format: "POS sale #N [method]". */
    private static function extractMethodFromDescription(?string $desc): ?string
    {
        if (!$desc) return null;
        if (preg_match('/\[([a-z0-9_:.\- ]+)\]/i', $desc, $m)) return strtolower(trim($m[1]));
        return null;
    }
}
