<?php

namespace App\Services\Accounts;

use App\Models\AccountTransaction;
use App\Models\CashAccount;
use App\Models\ExpensePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 8.6 — Auto-post supplier-bill payments into the cash ledger.
 *
 * Each ExpensePayment row creates exactly one OUT transaction against
 * the chosen cash_account, ref_type='expense_payment', ref_id=payment.id.
 * Idempotent — re-running on an already-posted payment is a no-op.
 *
 * Best-effort: missing/inactive account → log warning + skip; the
 * payment row itself is already saved.
 */
class PostExpensePaymentToLedger
{
    public const REF_TYPE = 'expense_payment';

    /** Post one payment to the ledger. Returns count of txns created (0 or 1). */
    public static function for(ExpensePayment $payment): int
    {
        if (empty($payment->cash_account_id)) {
            Log::info('Expense auto-post skipped: no cash_account_id', ['payment_id' => $payment->id]);
            return 0;
        }
        if (self::alreadyPosted($payment->id)) return 0;

        $account = CashAccount::where('id', $payment->cash_account_id)->where('is_active', true)->first();
        if (!$account) {
            Log::warning('Expense auto-post: cash_account_id missing/inactive', [
                'payment_id'      => $payment->id,
                'cash_account_id' => $payment->cash_account_id,
            ]);
            return 0;
        }

        $payment->loadMissing('expense.supplier');
        $expense = $payment->expense;
        $supplier = $expense?->supplier;

        $supplierLabel = $supplier?->name ?? 'direct';
        $billRef = $expense?->bill_no ? ' bill ' . $expense->bill_no : '';

        try {
            DB::transaction(function () use ($payment, $account, $expense, $supplierLabel, $billRef) {
                AccountTransaction::create([
                    'txn_no'              => AccountTransaction::nextTxnNo(),
                    'account_id'          => $account->id,
                    'direction'           => 'out',
                    'amount'              => (float) $payment->amount,
                    'charge'              => 0,
                    'vat_input'           => 0,
                    'vat_output'          => 0,
                    'tax_amount'          => 0,
                    'ref_type'            => self::REF_TYPE,
                    'ref_id'              => $payment->id,
                    'branch_id'           => $expense?->branch_id,
                    'description'         => 'Bill payment to ' . $supplierLabel . ' [expense]'
                        . ' · ' . ($expense?->expense_no ?? '#' . $expense?->id)
                        . $billRef
                        . ($payment->reference ? ' · ref ' . $payment->reference : ''),
                    'transacted_at'       => $payment->paid_at ?? $payment->created_at ?? now(),
                    'recorded_by_admin_id' => $payment->paid_by_admin_id,
                ]);
                $account->recomputeBalance();
            });
            return 1;
        } catch (\Throwable $e) {
            Log::error('Expense auto-post failed', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
            return 0;
        }
    }

    private static function alreadyPosted(int $paymentId): bool
    {
        return AccountTransaction::query()
            ->where('ref_type', self::REF_TYPE)
            ->where('ref_id', $paymentId)
            ->exists();
    }
}
