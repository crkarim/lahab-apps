<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\CentralLogics\SMSModule;
use App\Http\Controllers\Controller;
use App\Model\Order;
use App\Models\OfflinePaymentMethod;
use App\Models\OrderPartialPayment;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    /**
     * Submit a checkout for an in-restaurant order.
     *
     * Request fields:
     *   payments[] — array of { method: string, amount: numeric }
     *   tip_amount — numeric, optional
     *   discount_amount — numeric, optional
     *   discount_reason — string, optional (required if discount > 0)
     *   receipt_delivery — 'print' | 'sms' | 'both' | 'none'
     *   receipt_phone — string, required when receipt_delivery includes 'sms'
     */
    public function submit(Request $request, $id): RedirectResponse
    {
        $order = Order::find($id);
        if (!$order) {
            Toastr::error(translate('Order not found'));
            return back();
        }

        // Phase 8.5 — accept either method (legacy form) OR cash_account_id
        // (new picker). When only account_id is sent, derive method from
        // the account's type so the legacy paid_with column stays
        // populated for existing reports.
        $payments = collect($request->input('payments', []))
            ->filter(fn ($p) => (float) ($p['amount'] ?? 0) > 0
                && (isset($p['method']) && $p['method'] !== ''
                    || isset($p['cash_account_id']) && (int) $p['cash_account_id'] > 0))
            ->map(function ($p) {
                if ((empty($p['method']) || $p['method'] === '') && !empty($p['cash_account_id'])) {
                    $acc = \App\Models\CashAccount::find((int) $p['cash_account_id']);
                    $p['method'] = $acc?->type ?? 'cash';
                }
                return $p;
            })
            ->values();

        // Already-paid orders: skip payment recording, just handle receipt delivery.
        if ($order->payment_status === 'paid') {
            $phone    = trim((string) $request->input('receipt_phone', ''));
            $delivery = $request->input('receipt_delivery', 'sms');

            if (in_array($delivery, ['sms', 'both'])) {
                if ($phone === '') {
                    Toastr::error(translate('Phone number is required for SMS receipt'));
                    return back();
                }
                if (empty($order->receipt_token)) {
                    $order->receipt_token = Str::random(32);
                    $order->save();
                }
                $smsResult = $this->sendReceiptSms($order, $phone);
                if ($smsResult === 'success') {
                    Toastr::success(translate('Receipt sent by SMS'));
                } elseif ($smsResult === 'not_found') {
                    Toastr::warning(translate('No SMS gateway is configured — enable one under Third Party → SMS Module'));
                } else {
                    Toastr::error(translate('SMS gateway returned an error — receipt was not sent'));
                }
            }

            if (in_array($delivery, ['print', 'both']) && !empty($order->receipt_token)) {
                session()->flash('auto_print_receipt_token', $order->receipt_token);
            }
            return redirect()->route('admin.orders.details', ['id' => $order->id]);
        }

        if ($payments->isEmpty()) {
            Toastr::error(translate('Please enter at least one payment'));
            return back();
        }

        $request->validate([
            'tip_amount'        => 'nullable|numeric|min:0',
            'discount_amount'   => 'nullable|numeric|min:0',
            'discount_reason'   => 'nullable|string|max:200',
            'receipt_delivery'  => 'nullable|in:none,print,sms,both',
            'receipt_phone'     => 'nullable|string|max:20',
        ]);

        $tip      = (float) $request->input('tip_amount', 0);
        $discount = (float) $request->input('discount_amount', 0);
        $delivery = $request->input('receipt_delivery', 'print');
        $phone    = trim((string) $request->input('receipt_phone', ''));

        if (in_array($delivery, ['sms', 'both']) && $phone === '') {
            Toastr::error(translate('Phone number is required for SMS receipt'));
            return back();
        }

        $orderTotal  = (float) $order->order_amount + $tip - $discount;
        $totalPaid   = (float) $payments->sum(fn ($p) => (float) $p['amount']);
        $changeDue   = max(0, $totalPaid - $orderTotal);
        $stillDue    = max(0, $orderTotal - $totalPaid);

        DB::transaction(function () use ($order, $payments, $tip, $discount, $request, $stillDue, $orderTotal, $totalPaid) {
            if ($tip > 0 || $discount > 0) {
                $order->order_amount = $orderTotal;
            }

            foreach ($payments as $p) {
                // Phase 8.5 — capture cash_account_id when sent. Falls
                // through to fuzzy match in auto-post otherwise. The
                // legacy 'method' string is what existing reports read,
                // so we keep populating it from the picker's data-method.
                $accountId = isset($p['cash_account_id']) && (int) $p['cash_account_id'] > 0
                    ? (int) $p['cash_account_id'] : null;

                OrderPartialPayment::create([
                    'order_id'        => $order->id,
                    'paid_with'       => (string) $p['method'],
                    'paid_amount'     => (float) $p['amount'],
                    'due_amount'      => 0,
                    'cash_account_id' => $accountId,
                ]);
            }

            $order->payment_status = $stillDue <= 0.009 ? 'paid' : 'partially_paid';
            $order->payment_method = (string) $payments->first()['method'];

            // Close the order on full payment (take-away customer left with food; dine-in finished).
            if ($stillDue <= 0.009 && in_array($order->order_type, ['pos', 'dine_in'])) {
                $order->order_status = 'completed';
            }

            if (empty($order->receipt_token)) {
                $order->receipt_token = Str::random(32);
            }

            $order->save();
        });

        $order->refresh();

        // Phase 8.4 — Auto-post the (newly added) payments to the cash
        // ledger. Idempotent: only the rows we just created post; if the
        // service has already seen this method on this order it skips.
        try {
            \App\Services\Accounts\PostOrderPaymentToLedger::for($order);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Checkout auto-post crashed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }

        $smsResult = null;
        if (in_array($delivery, ['sms', 'both'])) {
            $smsResult = $this->sendReceiptSms($order, $phone);
        }

        if ($changeDue > 0) {
            Toastr::success(translate('Paid') . '. ' . translate('Change due') . ': ' . Helpers::set_symbol($changeDue));
        } elseif ($stillDue > 0) {
            Toastr::info(translate('Partial payment recorded') . '. ' . translate('Still due') . ': ' . Helpers::set_symbol($stillDue));
        } else {
            Toastr::success(translate('Payment completed'));
        }

        // Surface the real SMS outcome separately from the payment toast so
        // operators don't get a false-positive when no gateway is configured.
        if ($smsResult === 'not_found') {
            Toastr::warning(translate('No SMS gateway is configured — enable one under Third Party → SMS Module'));
        } elseif ($smsResult === 'error') {
            Toastr::error(translate('SMS gateway returned an error — receipt was not sent'));
        } elseif ($smsResult === 'success') {
            Toastr::success(translate('Receipt sent by SMS'));
        }

        if (in_array($delivery, ['print', 'both']) && !empty($order->receipt_token)) {
            session()->flash('auto_print_receipt_token', $order->receipt_token);
        }

        return redirect()->route('admin.orders.details', ['id' => $order->id]);
    }

    /**
     * Returns 'success' | 'error' | 'not_found' so the caller can show
     * an honest toast. SMSModule::send() returns 'not_found' (string)
     * when no gateway is enabled — it never throws — so we must inspect
     * the return value, not rely on a try/catch.
     */
    private function sendReceiptSms(Order $order, string $phone): string
    {
        $name   = Helpers::get_business_settings('restaurant_name') ?: config('app.name');
        $total  = Helpers::set_symbol($order->order_amount);
        $link   = route('receipt.show', ['token' => $order->receipt_token]);
        $msg    = "Thanks for dining at {$name}! Total {$total}. Receipt: {$link}";

        try {
            // raw: true ships the receipt body with the link as-is —
            // skips the OTP template substitution that would otherwise
            // mangle the URL.
            $result = SMSModule::send($phone, $msg, true);
            return in_array($result, ['success', 'error', 'not_found'], true) ? $result : 'error';
        } catch (\Throwable $e) {
            return 'error';
        }
    }
}
