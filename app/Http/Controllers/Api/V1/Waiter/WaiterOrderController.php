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

        return response()->json([
            'success'             => true,
            'order_id'            => $order->id,
            'kot_number'          => $order->kot_number,
            'order_status'        => $order->order_status,
            'order_amount'        => (float) $order->order_amount,
            'printed'             => $print['ok'],
            'print_skipped'       => $print['skipped'],
            'print_error'         => $print['error'],
            'kitchen_ticket_url'  => url("/admin/orders/{$order->id}/kitchen-ticket"),
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
            'print_error'         => $print['error'],
            'kitchen_ticket_url'  => url("/admin/orders/{$order->id}/kitchen-ticket"),
            'message'             => 'Items added to the order.',
        ]);
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

        return response()->json([
            'order_id'           => $order->id,
            'kot_number'         => $order->kot_number,
            'printed'            => $print['ok'],
            'print_skipped'      => $print['skipped'],
            'print_error'        => $print['error'],
            'kitchen_ticket_url' => url("/admin/orders/{$order->id}/kitchen-ticket"),
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
     */
    private function safelyPrintKot(Order $order, bool $isReprint): array
    {
        try {
            $result = (new KitchenPrinter())->printOrder($order, $isReprint);
        } catch (\Throwable $e) {
            Log::warning('KitchenPrinter unexpected exception', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            $result = ['ok' => false, 'skipped' => false, 'error' => $e->getMessage()];
        }

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

        $order = new Order();
        $order->id                = $orderId;
        $order->user_id           = $payload['customer_id'] ?? null;
        $order->is_guest          = empty($payload['customer_id']) ? 1 : 0;
        $order->placed_by_admin_id= $admin->id;
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
        try {
            $result = (new KitchenPrinter())->printSupplementary($order, $newDetailIds, $round);
        } catch (\Throwable $e) {
            Log::warning('KitchenPrinter supplementary unexpected exception', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            $result = ['ok' => false, 'skipped' => false, 'error' => $e->getMessage()];
        }

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
}
