<?php

namespace App\Http\Controllers\Api\V1\Waiter;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\AddOn;
use App\Model\Notification;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\Product;
use App\Model\ProductByBranch;
use App\Services\Printer\KitchenPrinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Waiter app place-order surface. Single endpoint that:
 *   1. Validates the cart payload
 *   2. Creates the Order + OrderDetail rows
 *   3. Mints a KOT number, flips status to 'cooking'
 *   4. Pushes FCM to kitchen-{branch}
 *   5. Returns the saved order so the client can show "KOT 27-04-001 sent"
 *
 * Mirrors the admin POS `POSController::place_order` + KitchenTicketController
 * combined, minus coupon / wallet / extra-discount which the waiter app
 * doesn't expose at place time (Phase 2 checkout handles tip/discount/tender).
 */
class WaiterOrderController extends Controller
{
    public function place(Request $request): JsonResponse
    {
        $admin = $request->user('waiter_api');
        if (!$admin || !$admin->branch_id) {
            return response()->json([
                'errors' => [['code' => 'no_branch', 'message' => 'Your account is not assigned to a branch yet.']],
            ], 403);
        }

        $validated = $request->validate([
            'order_type'        => 'required|in:dine_in,pos',
            'table_id'          => 'nullable|integer',
            'number_of_people'  => 'nullable|integer|min:1',
            'customer_id'       => 'nullable|integer',
            'note'              => 'nullable|string|max:500',
            'items'             => 'required|array|min:1',
            'items.*.product_id'=> 'required|integer',
            'items.*.quantity'  => 'required|integer|min:1',
            'items.*.note'      => 'nullable|string|max:500',
            'items.*.variations'=> 'nullable|array',
            'items.*.addons'    => 'nullable|array',
        ]);

        if ($validated['order_type'] === 'dine_in' && empty($validated['table_id'])) {
            return response()->json([
                'errors' => [['code' => 'table_required', 'message' => 'Dine-in orders must include a table_id.']],
            ], 422);
        }

        // Lock branch_id into the existing Helpers/ProductLogic flow that
        // reads it from Config — keeps tax/discount math identical to
        // the admin POS without re-implementing.
        config(['branch_id' => $admin->branch_id]);

        try {
            $order = DB::transaction(function () use ($validated, $admin) {
                return $this->buildAndFire($validated, $admin);
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'errors' => [['code' => 'cart_invalid', 'message' => $e->getMessage()]],
            ], 422);
        }

        $this->notifyKitchen($order);

        // Best-effort network print of the KOT. Order placement has
        // already succeeded; print failure must NOT roll back. We
        // stamp the result on the response so the waiter app can show
        // an honest toast + offer a Reprint affordance.
        $print = $this->safelyPrintKot($order, isReprint: false);
        $hdr = $this->headerForDevice($order, $admin);

        return response()->json([
            'success'             => true,
            'order_id'            => $order->id,
            'kot_number'          => $order->kot_number,
            'order_status'        => $order->order_status,
            'order_amount'        => (float) $order->order_amount,
            'printed'             => $print['ok'],
            'print_skipped'       => $print['skipped'],
            'print_deferred'      => (bool) ($print['deferred'] ?? false),
            'print_error'         => $print['error'],
            'kitchen_ticket_url'  => url("/admin/orders/{$order->id}/kitchen-ticket"),
            'items'               => $this->itemsForDevice($order),
            'header'              => $hdr,
            'message'             => 'Order sent to the kitchen.',
        ]);
    }

    /**
     * Append additional items to an existing active order — the
     * "another round of drinks" flow. Inserts new OrderDetail rows,
     * recomputes the order total, and fires a SUPPLEMENTARY KOT so
     * the kitchen sees only what's new (the original lines have
     * already been cooked / are being cooked).
     */
    public function append(Request $request, int $id): JsonResponse
    {
        $admin = $request->user('waiter_api');
        if (!$admin || !$admin->branch_id) {
            return response()->json([
                'errors' => [['code' => 'no_branch', 'message' => 'Your account is not assigned to a branch yet.']],
            ], 403);
        }

        $order = Order::query()
            ->where('id', $id)
            ->where('branch_id', $admin->branch_id)
            ->first();

        if (!$order) {
            return response()->json([
                'errors' => [['code' => 'not_found', 'message' => 'Order not found in this branch.']],
            ], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'errors' => [['code' => 'order_paid', 'message' => 'Order is already paid — start a new order instead.']],
            ], 422);
        }

        $terminal = ['completed', 'delivered', 'canceled', 'failed', 'refunded', 'refund_requested'];
        if (in_array($order->order_status, $terminal, true)) {
            return response()->json([
                'errors' => [['code' => 'order_closed', 'message' => 'Order is closed and cannot accept new items.']],
            ], 422);
        }

        $validated = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.note'       => 'nullable|string|max:500',
            'items.*.variations' => 'nullable|array',
            'items.*.addons'     => 'nullable|array',
        ]);

        config(['branch_id' => $admin->branch_id]);

        try {
            $newDetailIds = DB::transaction(function () use ($validated, $order) {
                return $this->appendItemsToOrder($order, $validated['items']);
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'errors' => [['code' => 'cart_invalid', 'message' => $e->getMessage()]],
            ], 422);
        }

        $round = $this->roundCount($order);

        $this->notifyKitchen($order);
        $print = $this->safelyPrintSupplementary($order, $newDetailIds, $round);

        return response()->json([
            'success'             => true,
            'order_id'            => $order->id,
            'kot_number'          => $order->kot_number,
            'round'               => $round,
            'order_amount'        => (float) $order->fresh()->order_amount,
            'printed'             => $print['ok'],
            'print_skipped'       => $print['skipped'],
            'print_deferred'      => (bool) ($print['deferred'] ?? false),
            'print_error'         => $print['error'],
            'kitchen_ticket_url'  => url("/admin/orders/{$order->id}/kitchen-ticket"),
            // Only the just-appended detail rows — the device prints a
            // SUPPLEMENTARY KOT showing the new round, not the originals.
            'items'               => $this->itemsForDevice($order, $newDetailIds),
            'header'              => $this->headerForDevice($order, $admin),
            'message'             => 'Items added to the order.',
        ]);
    }

    /**
     * Settle an order — accept payment, save tip / discount, mark
     * paid, return everything the device needs to print the customer
     * receipt. Mirrors admin POS's place_order payment-capture branch
     * but for an order that's already been fired (KOT sent, items
     * cooking) and is now being closed at table turn-over.
     *
     * Body shape:
     *   tip_amount      (number, optional)   default 0
     *   discount_amount (number, optional)   default 0 — flat ৳ off
     *   payments        (array, required)    [{ method: 'cash'|'card', amount: number }]
     *   change_amount   (number, optional)   computed if missing
     */
    public function checkout(Request $request, int $id): JsonResponse
    {
        $admin = $request->user('waiter_api');
        if (!$admin || !$admin->branch_id) {
            return response()->json([
                'errors' => [['code' => 'no_branch', 'message' => 'Your account is not assigned to a branch yet.']],
            ], 403);
        }

        $order = Order::query()
            ->where('id', $id)
            ->where('branch_id', $admin->branch_id)
            ->first();
        if (!$order) {
            return response()->json([
                'errors' => [['code' => 'not_found', 'message' => 'Order not found in this branch.']],
            ], 404);
        }
        if ($order->payment_status === 'paid') {
            return response()->json([
                'errors' => [['code' => 'already_paid', 'message' => 'Order is already paid.']],
            ], 422);
        }

        $validated = $request->validate([
            'tip_amount'       => 'nullable|numeric|min:0',
            'discount_amount'  => 'nullable|numeric|min:0',
            'change_amount'    => 'nullable|numeric|min:0',
            'payments'         => 'required|array|min:1',
            // Tender methods — must match what the day-end report
            // recognises as a drawer bucket. New gateways added here
            // automatically appear in the report's payment breakdown.
            'payments.*.method'=> 'required|in:cash,card,wallet_payment,bkash,nagad,rocket',
            'payments.*.amount'=> 'required|numeric|min:0',
            // Phase 8.5d — Specific cash account each payment lands in.
            // Optional for back-compat with older app builds; when sent,
            // the auto-post service uses it verbatim instead of the
            // fuzzy method-string fallback. App calls
            // GET /api/v1/waiter/cash-accounts to populate the picker.
            'payments.*.cash_account_id' => 'nullable|integer|exists:cash_accounts,id',
            // Receipt delivery preference (Phase 2 closure):
            //   print — auto-print on device only (default; current behaviour)
            //   sms   — fire SMS to customer phone via the existing gateway
            //   both  — print + SMS
            //   none  — settle silently (no paper, no SMS)
            'delivery'         => 'nullable|in:print,sms,both,none',
        ]);

        $tip      = (float) ($validated['tip_amount'] ?? 0);
        $discount = (float) ($validated['discount_amount'] ?? 0);
        $tendered = collect($validated['payments'])->sum('amount');

        // Order amount stays as the kitchen total — tips are tracked
        // separately, discounts reduce what the customer owes. We
        // recompute the final figure here rather than mutating
        // `order_amount` so historical reports aren't disturbed.
        // Round payable UP to whole Taka — matches the device's
        // ceil math so a ৳485.48 internal bill takes ৳485 cash
        // (restaurants don't deal in paisa).
        $orderTotal      = (float) $order->order_amount;
        $payable         = (float) ceil($orderTotal - $discount + $tip);
        if ($payable < 0) $payable = 0;
        $changeRequested = (float) ($validated['change_amount'] ?? 0);
        $change          = $changeRequested > 0
            ? $changeRequested
            : max(0, $tendered - $payable);

        if ($tendered + 0.01 < $payable) {
            return response()->json([
                'errors' => [['code' => 'insufficient_payment', 'message' => 'Tendered amount is less than the bill.']],
            ], 422);
        }

        DB::transaction(function () use ($order, $validated, $tip, $discount, $change, $admin) {
            // Settle = lifecycle terminal. We close the order on three
            // axes at once:
            //   - payment_status='paid' so reports + the active-orders
            //     filter agree the bill is done
            //   - order_status='completed' so anyone watching status
            //     directly (kitchen views, exports, the header badge)
            //     sees the closed state
            //   - print_failure_handled_at stamped so the admin panel's
            //     bottom-sheet escalation drops this row — the order
            //     has left the kitchen, there's nothing to reprint
            $order->forceFill([
                'tip_amount'              => $tip > 0 ? $tip : null,
                'extra_discount'          => $discount > 0 ? $discount : 0,
                'bring_change_amount'     => $change,
                'payment_status'          => 'paid',
                'payment_method'          => $validated['payments'][0]['method'] ?? null,
                'order_status'            => 'completed',
                'print_failure_handled_at'=> now(),
                'print_failure_handled_by'=> $admin->id,
                'print_failure_reason'    => null,
            ])->save();

            // Drop any prior partial-payment rows in case this is a
            // re-checkout after admin cleared an old failed attempt.
            // Also drop any matching auto-posted ledger rows so the
            // re-checkout doesn't double-post the same order.
            \App\Models\OrderPartialPayment::where('order_id', $order->id)->delete();
            \App\Models\AccountTransaction::query()
                ->whereIn('ref_type', ['pos_order', 'pos_order_change'])
                ->where('ref_id', $order->id)
                ->delete();

            foreach ($validated['payments'] as $p) {
                \App\Models\OrderPartialPayment::create([
                    'order_id'        => $order->id,
                    'paid_with'       => $p['method'],
                    'paid_amount'     => (float) $p['amount'],
                    'due_amount'      => 0,
                    // Phase 8.5d — specific account, when picker was used.
                    'cash_account_id' => isset($p['cash_account_id']) && (int) $p['cash_account_id'] > 0
                        ? (int) $p['cash_account_id'] : null,
                ]);
            }
        });

        // Phase 8.4 + 8.5 — auto-post the order's payments + change row
        // to the cash ledger. Idempotent + best-effort: if no account is
        // mapped, the service logs a warning and the order itself stays
        // settled. Wrap in try so a ledger hiccup never breaks checkout.
        try {
            \App\Services\Accounts\PostOrderPaymentToLedger::for($order->refresh());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Waiter checkout auto-post crashed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }

        $order->refresh();

        // Receipt delivery — print is auto-handled by the device when
        // delivery=print|both. SMS we fire here using the existing
        // admin-panel SMSModule (same gateway as OTP / order alerts).
        $delivery = (string) ($validated['delivery'] ?? 'print');
        $smsResult = null; // 'success' | 'error' | 'not_found' | 'no_phone' | null
        if (in_array($delivery, ['sms', 'both'], true)) {
            $smsResult = $this->fireReceiptSms($order);
        }

        return response()->json([
            'success'        => true,
            'order_id'       => $order->id,
            'kot_number'     => $order->kot_number,
            'order_amount'   => (float) $order->order_amount,
            'tip_amount'     => (float) $tip,
            'discount_amount'=> (float) $discount,
            'payable'        => (float) $payable,
            'tendered'       => (float) $tendered,
            'change_amount'  => (float) $change,
            'delivery'       => $delivery,
            'sms_result'     => $smsResult,
            'receipt'        => $this->receiptPayload($order, $tip, $discount, $payable, $change, $validated['payments']),
            'message'        => 'Payment recorded.',
        ]);
    }

    /**
     * Send the customer their receipt via SMS using the configured
     * gateway. Mirrors `Admin\CheckoutController::sendReceiptSms` so
     * messaging stays consistent across surfaces.
     *
     * Returns:
     *   'success'   — gateway accepted the message
     *   'error'     — gateway returned an error
     *   'not_found' — no SMS gateway is configured
     *   'no_phone'  — order has no customer phone to send to
     */
    private function fireReceiptSms(Order $order): string
    {
        $order->loadMissing('customer:id,phone,f_name,l_name');
        $phone = $order->customer?->phone;
        if (empty($phone)) return 'no_phone';

        // Make sure we have a receipt_token — dine-in orders skip
        // setting this at place-order time; mint on demand here so the
        // SMS link works.
        if (empty($order->receipt_token)) {
            $order->forceFill(['receipt_token' => \Illuminate\Support\Str::random(32)])->save();
        }

        $name  = Helpers::get_business_settings('restaurant_name') ?: config('app.name');
        $total = Helpers::set_symbol($order->order_amount + (float) ($order->tip_amount ?? 0));
        $link  = route('receipt.show', ['token' => $order->receipt_token]);
        $msg   = "Thanks for dining at {$name}! Total {$total}. Receipt: {$link}";

        try {
            // raw: true bypasses the gateway's `otp_template` so the
            // receipt link reaches the customer unmangled. Without
            // this the message gets stuffed into "Your OTP is #OTP#"
            // style templates and the link is destroyed.
            $result = \App\CentralLogics\SMSModule::send($phone, $msg, true);
            return in_array($result, ['success', 'error', 'not_found'], true) ? $result : 'error';
        } catch (\Throwable $e) {
            \Log::warning('Waiter checkout SMS error', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            return 'error';
        }
    }

    /**
     * Slim shape the device-side ReceiptPrinter needs to compose the
     * customer receipt. Mirrors the admin Receipt blade fields so the
     * paper looks identical regardless of which path printed it.
     */
    private function receiptPayload(Order $order, float $tip, float $discount, float $payable, float $change, array $payments): array
    {
        $order->loadMissing(['details', 'customer:id,f_name,l_name,phone', 'table:id,number,zone', 'placedBy:id,f_name,l_name', 'branch:id,name,address,phone']);
        $customer = $order->customer
            ? trim(($order->customer->f_name ?? '') . ' ' . ($order->customer->l_name ?? ''))
            : null;
        $placedBy = $order->placedBy
            ? trim(($order->placedBy->f_name ?? '') . ' ' . ($order->placedBy->l_name ?? ''))
            : null;

        // Logo + restaurant name come from business_settings — same
        // source as the admin-panel receipt template, so paper from the
        // waiter app and paper from the admin POS look identical.
        $logoFile = \App\CentralLogics\Helpers::get_business_settings('logo');
        $logoUrl  = $logoFile ? asset('storage/app/public/restaurant/' . $logoFile) : null;
        $restaurantName = \App\CentralLogics\Helpers::get_business_settings('restaurant_name');

        // Mint a receipt_token here if the order doesn't have one yet —
        // the verification barcode at the receipt footer encodes it,
        // and the customer-facing /r/{token} link uses the same value.
        if (empty($order->receipt_token)) {
            $order->forceFill(['receipt_token' => \Illuminate\Support\Str::random(32)])->save();
        }

        $totalPaid  = array_sum(array_map(fn ($p) => (float) ($p['amount'] ?? 0), $payments));
        $balanceDue = max(0, $payable - $totalPaid);

        return [
            'order_id'        => (int) $order->id,
            'kot_number'      => $order->kot_number,
            'order_type'      => $order->order_type,
            'table_number'    => $order->table?->number,
            'customer'        => $customer ?: 'Walk-in',
            'customer_phone'  => $order->customer?->phone,
            'placed_by'       => $placedBy,
            'branch_name'     => $order->branch?->name,
            'branch_address'  => $order->branch?->address,
            'branch_phone'    => $order->branch?->phone,
            'restaurant_name' => $restaurantName,
            'logo_url'        => $logoUrl,
            'created_at'      => $order->created_at?->toIso8601String(),
            'receipt_token'   => $order->receipt_token,
            'items'           => $this->itemsForDevice($order),
            'subtotal'        => (float) ($order->order_amount - ($order->total_tax_amount ?? 0)),
            'tax'             => (float) ($order->total_tax_amount ?? 0),
            'discount'        => (float) $discount,
            'tip'             => (float) $tip,
            'total'           => (float) $payable,
            'total_paid'      => (float) $totalPaid,
            'balance_due'     => (float) $balanceDue,
            'change'          => (float) $change,
            'payments'        => $payments,
        ];
    }

    /**
     * Reprint the KOT for an existing order on demand. Used by the
     * Reprint affordance in the waiter app when the first attempt
     * silently failed (printer offline, paper out, etc.). Returns
     * the same shape as `place` so the client can branch on it
     * uniformly.
     */
    public function printKot(Request $request, int $id): JsonResponse
    {
        $admin = $request->user('waiter_api');
        if (!$admin || !$admin->branch_id) {
            return response()->json([
                'errors' => [['code' => 'no_branch', 'message' => 'Your account is not assigned to a branch yet.']],
            ], 403);
        }

        $order = Order::query()
            ->where('id', $id)
            ->where('branch_id', $admin->branch_id)
            ->first();

        if (!$order) {
            return response()->json([
                'errors' => [['code' => 'not_found', 'message' => 'Order not found in this branch.']],
            ], 404);
        }

        $print = $this->safelyPrintKot($order, isReprint: true);
        $order->loadMissing(['table:id,number,zone', 'customer:id,f_name,l_name', 'placedBy:id,f_name,l_name']);

        $customer = $order->customer
            ? trim(($order->customer->f_name ?? '') . ' ' . ($order->customer->l_name ?? ''))
            : null;
        $placedBy = $order->placedBy
            ? trim(($order->placedBy->f_name ?? '') . ' ' . ($order->placedBy->l_name ?? ''))
            : null;

        return response()->json([
            'order_id'           => $order->id,
            'kot_number'         => $order->kot_number,
            'order_type'         => $order->order_type,
            'table_number'       => $order->table?->number,
            'table_zone'         => $order->table?->zone,
            'customer'           => $customer ?: ($order->is_guest ? 'Walk-in' : null),
            'placed_by'          => $placedBy,
            'order_note'         => $order->order_note,
            'number_of_people'   => $order->number_of_people,
            'printed'            => $print['ok'],
            'print_skipped'      => $print['skipped'],
            'print_deferred'     => (bool) ($print['deferred'] ?? false),
            'print_error'        => $print['error'],
            'kitchen_ticket_url' => url("/admin/orders/{$order->id}/kitchen-ticket"),
            // Reprint includes the full item list so the device can
            // re-render the KOT layout without a separate detail fetch.
            'items'              => $this->itemsForDevice($order),
            'message'            => $print['ok']
                ? 'Reprint sent to the kitchen.'
                : ($print['skipped']
                    ? 'Network printer is disabled — open the kitchen ticket URL in a browser.'
                    : 'Could not reach the printer — try again or use the kitchen ticket URL.'),
        ]);
    }

    /**
     * Wraps KitchenPrinter to swallow any unexpected throw and stamp
     * a print-failure flag on the order so the admin escalation modal
     * can pick it up. On a successful retry we clear the flag.
     *
     * If `print_path = device`, the cloud doesn't even try the TCP
     * socket — the waiter Flutter app prints over its own LAN
     * connection and reports the outcome via reportPrintSuccess /
     * reportPrintFailure. Returns `defer` so callers can branch.
     */
    private function safelyPrintKot(Order $order, bool $isReprint): array
    {
        $cfg = \App\Services\Printer\ReceiptPrinter::config();
        if (($cfg['print_path'] ?? 'device') === 'device') {
            // Device handles printing. Don't stamp a failure — that
            // would race with the device's own success report.
            return ['ok' => false, 'skipped' => true, 'deferred' => true, 'error' => null];
        }

        try {
            $result = (new KitchenPrinter())->printOrder($order, $isReprint);
        } catch (\Throwable $e) {
            Log::warning('KitchenPrinter unexpected exception', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            $result = ['ok' => false, 'skipped' => false, 'error' => $e->getMessage()];
        }
        $result['deferred'] = false;

        if ($result['ok']) {
            // Clear any prior failure stamp — print succeeded so the
            // escalation modal should disappear from the admin panel.
            if ($order->print_failure_at !== null && $order->print_failure_handled_at === null) {
                $order->update([
                    'print_failure_handled_at' => now(),
                    'print_failure_reason'     => null,
                ]);
            }
        } else {
            // Stamp the order whenever the KOT didn't physically print —
            // disabled, unreachable, or errored alike. Operators want to
            // see ONE consistent escalation, not have the admin choose
            // which kind of failure deserves attention.
            $order->forceFill([
                'print_failure_at'         => now(),
                'print_failure_reason'     => 'Printer offline',
                'print_failure_handled_at' => null,
                'print_failure_handled_by' => null,
            ])->save();
        }

        return $result;
    }

    /**
     * Device successfully printed the KOT — clear any escalation
     * stamp and record the audit timestamp so reports can tell
     * device-printed KOTs from server-printed ones.
     */
    public function reportPrintSuccess(Request $request, int $id): JsonResponse
    {
        $admin = $request->user('waiter_api');
        if (!$admin || !$admin->branch_id) {
            return response()->json(['errors' => [['code' => 'no_branch', 'message' => 'No branch.']]], 403);
        }

        $order = Order::query()
            ->where('id', $id)
            ->where('branch_id', $admin->branch_id)
            ->first();
        if (!$order) {
            return response()->json(['errors' => [['code' => 'not_found', 'message' => 'Order not found.']]], 404);
        }

        $order->forceFill([
            'print_failure_at'         => null,
            'print_failure_reason'     => null,
            'print_failure_handled_at' => null,
            'print_failure_handled_by' => null,
            'kot_native_printed_at'    => now(),
            'kot_native_printed_by'    => $admin->id,
        ])->save();
        $order->increment('kot_print_count');

        return response()->json(['ok' => true, 'message' => 'Print acknowledged.']);
    }

    /**
     * Device failed to print (printer offline / IP wrong / TCP timeout).
     * Stamp the failure so the admin panel's bottom sheet pops up with
     * the L2 fallback (browser native print). Idempotent — re-firing
     * doesn't compound rows.
     */
    public function reportPrintFailure(Request $request, int $id): JsonResponse
    {
        $admin = $request->user('waiter_api');
        if (!$admin || !$admin->branch_id) {
            return response()->json(['errors' => [['code' => 'no_branch', 'message' => 'No branch.']]], 403);
        }

        $order = Order::query()
            ->where('id', $id)
            ->where('branch_id', $admin->branch_id)
            ->first();
        if (!$order) {
            return response()->json(['errors' => [['code' => 'not_found', 'message' => 'Order not found.']]], 404);
        }

        $reason = (string) $request->input('reason', 'Printer offline');
        if (mb_strlen($reason) > 250) $reason = mb_substr($reason, 0, 250);

        $order->forceFill([
            'print_failure_at'         => now(),
            'print_failure_reason'     => $reason,
            'print_failure_handled_at' => null,
            'print_failure_handled_by' => null,
        ])->save();

        return response()->json(['ok' => true, 'message' => 'Failure recorded.']);
    }

    private function buildAndFire(array $payload, $admin): Order
    {
        $orderTypeIn  = $payload['order_type'];
        $orderType    = $orderTypeIn === 'dine_in' ? 'dine_in' : 'pos';
        $branchId     = $admin->branch_id;

        $processed = $this->processLines($payload['items'], $branchId);
        $orderDetails    = $processed['details'];
        $totalTax        = $processed['tax'];
        $totalAddonPrice = $processed['addon_price'];
        $productPrice    = $processed['product_price'];

        $orderAmount = Helpers::set_price($productPrice + $totalAddonPrice + $totalTax);

        // Generate sequential id (mirror admin POS formula)
        $orderId = 100000 + Order::count() + 1;
        if (Order::find($orderId)) {
            $orderId = (Order::orderByDesc('id')->value('id') ?? $orderId) + 1;
        }

        // Attribute the order to the cashier's currently-open shift.
        // Per design: warn-but-allow — if no shift is open, shift_id
        // stays null and the order surfaces under "no shift" on the
        // Day-End. Lookup keyed on the placing waiter's branch since
        // a waiter doesn't run their own till — they hand cash to a
        // cashier whose shift owns it.
        $shiftId = null;
        try {
            $openShift = \App\Models\Shift::query()
                ->where('branch_id', $branchId)
                ->where('status', 'open')
                ->orderByDesc('opened_at')
                ->first();
            $shiftId = $openShift?->id;
        } catch (\Throwable $e) { /* shifts table missing pre-migration — skip */ }

        $order = new Order();
        $order->id                = $orderId;
        $order->user_id           = $payload['customer_id'] ?? null;
        $order->is_guest          = empty($payload['customer_id']) ? 1 : 0;
        $order->placed_by_admin_id= $admin->id;
        $order->shift_id          = $shiftId;
        $order->branch_id         = $branchId;
        $order->table_id          = $orderType === 'dine_in' ? ($payload['table_id'] ?? null) : null;
        $order->number_of_people  = $orderType === 'dine_in' ? ($payload['number_of_people'] ?? null) : null;
        $order->order_type        = $orderType;
        $order->order_status      = 'cooking';
        $order->payment_status    = 'unpaid';
        $order->payment_method    = null;
        $order->order_amount      = $orderAmount;
        $order->total_tax_amount  = $totalTax;
        $order->coupon_discount_amount = 0;
        $order->extra_discount    = 0;
        $order->delivery_charge   = 0;
        $order->checked           = 1;
        $order->order_note        = $payload['note'] ?? null;
        $order->delivery_date     = now()->format('Y-m-d');
        $order->delivery_time     = now()->format('H:i:s');
        $order->kot_number        = $this->nextKotNumber($branchId);
        $order->kot_sent_at       = now();
        $order->kot_print_count   = 0;
        // Take-away orders need a receipt token at fire-time so the
        // KOT-and-receipt combined print works (same pattern as admin POS).
        if ($orderType === 'pos') {
            $order->receipt_token = Str::random(32);
        }
        $order->created_at        = now();
        $order->updated_at        = now();
        $order->save();

        foreach ($orderDetails as $i => $d) {
            $orderDetails[$i]['order_id'] = $order->id;
        }
        OrderDetail::insert($orderDetails);

        return $order;
    }

    /** DD-MM-NNN per branch per day. Same scheme as KitchenTicketController. */
    private function nextKotNumber(int $branchId): string
    {
        $prefix = now()->format('d-m') . '-';
        $last = Order::where('branch_id', $branchId)
            ->whereDate('kot_sent_at', now()->toDateString())
            ->where('kot_number', 'like', $prefix . '%')
            ->orderByDesc('kot_number')
            ->value('kot_number');
        $next = 1;
        if ($last) {
            $parts = explode('-', $last);
            $next = (int) end($parts) + 1;
        }
        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function notifyKitchen(Order $order): void
    {
        try {
            $n = new Notification();
            $n->title        = "New KOT {$order->kot_number}";
            $n->description  = (string) $order->id;
            $n->status       = 1;
            $n->order_id     = $order->id;
            $n->order_status = $order->order_status;

            Helpers::send_push_notif_to_topic(
                data: $n,
                topic: "kitchen-{$order->branch_id}",
                type: 'general',
                isNotificationPayloadRemove: true,
            );
        } catch (\Throwable $e) {
            // Push best-effort — order placement succeeded already.
        }
    }

    /**
     * Cart payload → OrderDetail-shaped rows + running totals. Shared
     * between place_order and append so price / tax / addon math stays
     * consistent across the two flows. Mutates ProductByBranch.sold
     * counters as a side-effect (caller is expected to be inside a
     * transaction).
     *
     * @return array{details: array<int, array<string, mixed>>, tax: float, addon_price: float, product_price: float}
     */
    private function processLines(array $items, int $branchId): array
    {
        $totalTax        = 0;
        $totalAddonPrice = 0;
        $productPrice    = 0;
        $details         = [];

        foreach ($items as $line) {
            $product = Product::find($line['product_id']);
            if (!$product) {
                throw new \InvalidArgumentException("Product #{$line['product_id']} not found.");
            }

            $branchProduct = ProductByBranch::where([
                'product_id' => $product->id,
                'branch_id'  => $branchId,
            ])->first();

            if (!$branchProduct || !$branchProduct->is_available) {
                throw new \InvalidArgumentException("Product #{$product->id} is not available at this branch.");
            }

            if (in_array($branchProduct->stock_type, ['daily', 'fixed'], true)) {
                $available = $branchProduct->stock - $branchProduct->sold_quantity;
                if ($available < $line['quantity']) {
                    throw new \InvalidArgumentException("Stock exhausted for {$product->name}.");
                }
            }

            $unitPrice = (float) ($branchProduct->price ?? $product->price);

            $variationDelta = 0;
            $variationsForOrder = [];
            foreach ($line['variations'] ?? [] as $v) {
                $optPrice = (float) ($v['option_price'] ?? 0);
                $variationDelta += $optPrice;
                $variationsForOrder[] = [
                    'name'        => (string) ($v['name'] ?? 'Variation'),
                    'value'       => (string) ($v['value'] ?? ''),
                    'optionPrice' => $optPrice,
                ];
            }
            $unitPrice += $variationDelta;

            $discountData = [
                'discount_type' => $branchProduct->discount_type,
                'discount'      => $branchProduct->discount,
            ];
            $discountPerUnit = Helpers::discount_calculate($discountData, $unitPrice);
            $taxPerUnit      = Helpers::new_tax_calculate(
                Helpers::product_data_formatting($product),
                $unitPrice,
                $discountData
            );

            $addonIds = [];
            $addonQtys = [];
            $addonPrices = [];
            $addonTaxes = [];
            $addonTotalTax = 0;
            $addonLineSubtotal = 0;
            foreach ($line['addons'] ?? [] as $a) {
                $aid  = (int) ($a['id'] ?? 0);
                $aqty = max(1, (int) ($a['qty'] ?? 1));
                $row  = AddOn::find($aid);
                if (!$row) continue;
                $aprice = (float) $row->price;
                $atax   = (float) ($row->tax ?? 0);
                $addonIds[]    = $aid;
                $addonQtys[]   = $aqty;
                $addonPrices[] = $aprice;
                $addonTaxes[]  = $atax;
                $addonTotalTax += $atax * $aqty;
                $addonLineSubtotal += $aprice * $aqty;
            }

            $productPayload = Helpers::product_data_formatting($product);
            $lineNote = trim((string) ($line['note'] ?? ''));
            if ($lineNote !== '') {
                $productPayload['line_note'] = $lineNote;
            }

            $details[] = [
                'product_id'         => $product->id,
                'product_details'    => json_encode($productPayload),
                'quantity'           => $line['quantity'],
                'price'              => $unitPrice,
                'tax_amount'         => $taxPerUnit,
                'discount_on_product'=> $discountPerUnit,
                'discount_type'      => 'discount_on_product',
                'variation'          => json_encode($variationsForOrder),
                'add_on_ids'         => json_encode($addonIds),
                'add_on_qtys'        => json_encode($addonQtys),
                'add_on_prices'      => json_encode($addonPrices),
                'add_on_taxes'       => json_encode($addonTaxes),
                'add_on_tax_amount'  => $addonTotalTax,
                'created_at'         => now(),
                'updated_at'         => now(),
            ];

            $totalTax        += $taxPerUnit * $line['quantity'];
            $totalAddonPrice += $addonLineSubtotal;
            $productPrice    += ($unitPrice - $discountPerUnit) * $line['quantity'];

            if (in_array($branchProduct->stock_type, ['daily', 'fixed'], true)) {
                $branchProduct->sold_quantity += $line['quantity'];
                $branchProduct->save();
            }
        }

        return [
            'details'      => $details,
            'tax'          => $totalTax,
            'addon_price'  => $totalAddonPrice,
            'product_price'=> $productPrice,
        ];
    }

    /**
     * Insert appended items into an existing order, recompute order
     * totals from ALL details, and return the IDs of the just-inserted
     * detail rows so the caller can fire a supplementary KOT for only
     * the new items.
     *
     * @return array<int, int> newly inserted detail IDs
     */
    private function appendItemsToOrder(Order $order, array $items): array
    {
        $processed = $this->processLines($items, $order->branch_id);
        $newDetails = $processed['details'];

        $idBefore = (int) (OrderDetail::max('id') ?? 0);

        foreach ($newDetails as $i => $d) {
            $newDetails[$i]['order_id'] = $order->id;
        }
        OrderDetail::insert($newDetails);

        // Recompute order_amount / total_tax_amount from ALL lines so
        // the order header stays honest after the append. Cheaper than
        // tracking deltas + safer if rounding ever differs.
        $allDetails = OrderDetail::where('order_id', $order->id)->get();
        $newProductPrice = 0;
        $newTotalTax     = 0;
        $newAddonPrice   = 0;
        foreach ($allDetails as $d) {
            $unitPrice = (float) $d->price;
            $discountPerUnit = (float) ($d->discount_on_product ?? 0);
            $taxPerUnit = (float) ($d->tax_amount ?? 0);
            $newProductPrice += ($unitPrice - $discountPerUnit) * (int) $d->quantity;
            $newTotalTax     += $taxPerUnit * (int) $d->quantity;

            $addonPrices = is_array($d->add_on_prices) ? $d->add_on_prices : (json_decode($d->add_on_prices, true) ?: []);
            $addonQtys   = is_array($d->add_on_qtys)   ? $d->add_on_qtys   : (json_decode($d->add_on_qtys, true)   ?: []);
            foreach ($addonPrices as $i => $p) {
                $newAddonPrice += (float) $p * (int) ($addonQtys[$i] ?? 1);
            }
        }

        $order->order_amount     = Helpers::set_price($newProductPrice + $newAddonPrice + $newTotalTax);
        $order->total_tax_amount = $newTotalTax;
        $order->kot_sent_at      = now();
        $order->updated_at       = now();
        $order->save();

        // IDs > idBefore are the rows we just inserted. Cheaper than
        // re-querying by created_at and survives clock skew.
        return OrderDetail::where('order_id', $order->id)
            ->where('id', '>', $idBefore)
            ->pluck('id')
            ->all();
    }

    /**
     * Round = number of distinct fire batches. Round 1 = original
     * placement, round 2 = first append, etc. Counted by distinct
     * minute-truncated created_at values across the order's details.
     */
    private function roundCount(Order $order): int
    {
        $stamps = OrderDetail::where('order_id', $order->id)
            ->orderBy('created_at')
            ->pluck('created_at')
            ->map(fn ($s) => $s ? \Carbon\Carbon::parse($s)->format('Y-m-d H:i') : null)
            ->filter()
            ->unique()
            ->values();
        return max(1, $stamps->count());
    }

    /**
     * Best-effort supplementary KOT — same envelope as safelyPrintKot()
     * but only the newly added details land on paper, with a "ROUND N"
     * banner so the kitchen knows it's an addition rather than a fresh
     * order.
     */
    private function safelyPrintSupplementary(Order $order, array $newDetailIds, int $round): array
    {
        // Mirror safelyPrintKot — when print_path=device the cloud
        // skips its TCP attempt entirely and the waiter app prints
        // the supplementary KOT over its own LAN connection.
        $cfg = \App\Services\Printer\ReceiptPrinter::config();
        if (($cfg['print_path'] ?? 'device') === 'device') {
            return ['ok' => false, 'skipped' => true, 'deferred' => true, 'error' => null];
        }

        try {
            $result = (new KitchenPrinter())->printSupplementary($order, $newDetailIds, $round);
        } catch (\Throwable $e) {
            Log::warning('KitchenPrinter supplementary unexpected exception', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            $result = ['ok' => false, 'skipped' => false, 'error' => $e->getMessage()];
        }
        $result['deferred'] = false;

        if ($result['ok']) {
            if ($order->print_failure_at !== null && $order->print_failure_handled_at === null) {
                $order->update([
                    'print_failure_handled_at' => now(),
                    'print_failure_reason'     => null,
                ]);
            }
        } else {
            $order->forceFill([
                'print_failure_at'         => now(),
                'print_failure_reason'     => 'Printer offline',
                'print_failure_handled_at' => null,
                'print_failure_handled_by' => null,
            ])->save();
        }

        return $result;
    }

    /**
     * Header fields the Flutter `KotPaperStripView` needs to render
     * the meta block — table number/zone, customer, placed_by, guests,
     * order_type, order_note. Computed once and shipped alongside
     * `items` in place / append / reprint responses so the device
     * doesn't need a separate detail fetch before printing.
     */
    private function headerForDevice(Order $order, $admin): array
    {
        $order->loadMissing(['table:id,number,zone', 'customer:id,f_name,l_name']);
        $customer = $order->customer
            ? trim(($order->customer->f_name ?? '') . ' ' . ($order->customer->l_name ?? ''))
            : null;
        $placedBy = trim(($admin->f_name ?? '') . ' ' . ($admin->l_name ?? ''));
        if ($placedBy === '') $placedBy = $admin->email ?? 'Staff';
        return [
            'order_type'       => $order->order_type,
            'table_number'     => $order->table?->number,
            'table_zone'       => $order->table?->zone,
            'customer'         => $customer ?: ($order->is_guest ? 'Walk-in' : null),
            'placed_by'        => $placedBy,
            'order_note'       => $order->order_note,
            'number_of_people' => $order->number_of_people,
        ];
    }

    /**
     * Produce the slim item shape the Flutter KotPrinter needs to
     * compose ESC/POS bytes locally — name, qty, variation summary,
     * addons, line note. Same fields as `WaiterActiveOrdersController::shapeItem`
     * but pared down (no prices, taxes, discounts: a KOT doesn't show money).
     *
     * @param array<int, int>|null $detailIdFilter when set, only those
     *   detail IDs are returned (used for the supplementary append KOT
     *   so device prints just the new round, not the originals).
     */
    private function itemsForDevice(Order $order, ?array $detailIdFilter = null): array
    {
        $order->loadMissing('details');
        $rows = $detailIdFilter
            ? $order->details->whereIn('id', $detailIdFilter)
            : $order->details;

        return $rows->map(function ($d) {
            $product = is_array($d->product_details)
                ? $d->product_details
                : (json_decode($d->product_details, true) ?: []);
            $variations = is_array($d->variation)
                ? $d->variation
                : (json_decode($d->variation, true) ?: []);
            $addonIds  = is_array($d->add_on_ids)  ? $d->add_on_ids  : (json_decode($d->add_on_ids, true)  ?: []);
            $addonQtys = is_array($d->add_on_qtys) ? $d->add_on_qtys : (json_decode($d->add_on_qtys, true) ?: []);

            $variationSummary = [];
            foreach ($variations as $v) {
                if (!is_array($v)) continue;
                $name  = $v['name'] ?? null;
                $value = $v['value'] ?? '';
                if ($value === '' && !empty($v['values']) && is_array($v['values'])) {
                    $value = collect($v['values'])
                        ->map(fn ($x) => is_array($x) ? ($x['label'] ?? $x['level'] ?? $x['name'] ?? '') : (string) $x)
                        ->filter()->implode(', ');
                }
                if ($value !== '') {
                    $variationSummary[] = ($name ? "$name: " : '') . $value;
                }
            }

            $addons = [];
            foreach ($addonIds as $i => $aid) {
                $name = collect($product['add_ons'] ?? [])->firstWhere('id', $aid)['name']
                    ?? AddOn::find($aid)?->name
                    ?? 'Addon';
                $addons[] = [
                    'name' => $name,
                    'qty'  => (int) ($addonQtys[$i] ?? 1),
                ];
            }

            return [
                'name'              => $product['name'] ?? 'Item',
                'quantity'          => (int) $d->quantity,
                'variation_summary' => implode(' · ', $variationSummary),
                'addons'            => $addons,
                'note'              => $product['line_note'] ?? null,
            ];
        })->values()->all();
    }
}
