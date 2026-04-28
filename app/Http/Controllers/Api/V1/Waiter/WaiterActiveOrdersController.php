<?php

namespace App\Http\Controllers\Api\V1\Waiter;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\AddOn;
use App\Model\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Active orders surface for the waiter app — the live list of orders
 * that still need attention, plus per-order details. Mirrors the admin
 * panel's `tableRunningOrder` query so the waiter sees the same orders
 * the kitchen / counter sees.
 *
 * "Active" definition (matches admin):
 *   - dine-in   : payment_status != paid AND status not terminal
 *   - delivery  : status not terminal
 *   - take-away : same, type IN (pos, take_away)
 *
 * Terminal statuses: completed, delivered, canceled, failed, refunded.
 */
class WaiterActiveOrdersController extends Controller
{
    private const TERMINAL = ['completed', 'delivered', 'canceled', 'failed', 'refunded', 'refund_requested'];

    /** GET /api/v1/waiter/orders?type=all|dine_in|take_away */
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user('waiter_api');
        if (!$admin || !$admin->branch_id) {
            return response()->json([
                'errors' => [['code' => 'no_branch', 'message' => 'Your account is not assigned to a branch yet.']],
            ], 403);
        }

        $type = $request->query('type', 'all');
        if (!in_array($type, ['all', 'dine_in', 'take_away'], true)) {
            $type = 'all';
        }

        $orders = Order::query()
            ->with([
                'customer:id,f_name,l_name,phone',
                'table:id,number,zone',
                'placedBy:id,f_name,l_name',
            ])
            ->where('branch_id', $admin->branch_id)
            ->where(function ($q) use ($type) {
                if ($type === 'dine_in') {
                    $this->dineInActive($q);
                } elseif ($type === 'take_away') {
                    $this->takeAwayActive($q);
                } else {
                    $q->where(function ($qq) { $this->dineInActive($qq); })
                      ->orWhere(function ($qq) { $this->takeAwayActive($qq); });
                }
            })
            ->orderByDesc('created_at')
            ->limit(60)
            ->get();

        // Counts for the tab badges — cheap because all three buckets
        // are subsets of the same active filter.
        $allCount = Order::query()
            ->where('branch_id', $admin->branch_id)
            ->where(function ($qq) {
                $qq->where(function ($q) { $this->dineInActive($q); })
                   ->orWhere(function ($q) { $this->takeAwayActive($q); });
            })
            ->count();
        $dineInCount = Order::query()->where('branch_id', $admin->branch_id)->where(fn ($q) => $this->dineInActive($q))->count();
        $takeAwayCount = Order::query()->where('branch_id', $admin->branch_id)->where(fn ($q) => $this->takeAwayActive($q))->count();

        return response()->json([
            'counts' => [
                'all'       => $allCount,
                'dine_in'   => $dineInCount,
                'take_away' => $takeAwayCount,
            ],
            'orders' => $orders->map(fn (Order $o) => $this->shapeRow($o))->values(),
        ]);
    }

    /** GET /api/v1/waiter/order/{id} — detailed row including line items. */
    public function show(Request $request, int $id): JsonResponse
    {
        $admin = $request->user('waiter_api');
        if (!$admin || !$admin->branch_id) {
            return response()->json([
                'errors' => [['code' => 'no_branch', 'message' => 'Your account is not assigned to a branch yet.']],
            ], 403);
        }

        $order = Order::query()
            ->with([
                'customer:id,f_name,l_name,phone',
                'table:id,number,zone',
                'placedBy:id,f_name,l_name',
                'details',
                'order_partial_payments',
            ])
            ->where('id', $id)
            ->where('branch_id', $admin->branch_id)
            ->first();

        if (!$order) {
            return response()->json([
                'errors' => [['code' => 'not_found', 'message' => 'Order not found in this branch.']],
            ], 404);
        }

        return response()->json([
            'order' => $this->shapeDetail($order),
        ]);
    }

    private function dineInActive($q): void
    {
        $q->where('order_type', 'dine_in')
          ->where('payment_status', '!=', 'paid')
          ->whereNotIn('order_status', self::TERMINAL);
    }

    private function takeAwayActive($q): void
    {
        $q->whereIn('order_type', ['pos', 'take_away'])
          ->whereNotIn('order_status', self::TERMINAL);
    }

    private function shapeRow(Order $o): array
    {
        $placedBy = $o->placedBy
            ? trim(($o->placedBy->f_name ?? '') . ' ' . ($o->placedBy->l_name ?? ''))
            : null;
        $customer = $o->customer
            ? trim(($o->customer->f_name ?? '') . ' ' . ($o->customer->l_name ?? ''))
            : null;
        return [
            'id'           => $o->id,
            'kot_number'   => $o->kot_number,
            'order_type'   => $o->order_type,
            'order_status' => $o->order_status,
            'payment_status'=> $o->payment_status,
            'order_amount' => (float) $o->order_amount,
            'created_at'   => $o->created_at?->toIso8601String(),
            'created_human'=> $o->created_at?->diffForHumans(),
            'table_number' => $o->table?->number,
            'table_zone'   => $o->table?->zone,
            'customer'     => $customer ?: ($o->is_guest ? 'Walk-in' : null),
            'customer_phone'=> $o->customer?->phone,
            'placed_by'    => $placedBy,
            'is_mine'      => $o->placed_by_admin_id === request()->user('waiter_api')?->id,
            // Surface the print-failure flag at row level so the active-
            // orders list can render an honest "PRINTER OFFLINE" pill
            // instead of the misleading "COOKING" status when the
            // kitchen never got a paper ticket.
            'print_failure' => $o->print_failure_at !== null && $o->print_failure_handled_at === null,
        ];
    }

    private function shapeDetail(Order $o): array
    {
        $row = $this->shapeRow($o);
        $row['number_of_people'] = $o->number_of_people;
        $row['order_note']       = $o->order_note;
        $row['kot_sent_at']      = $o->kot_sent_at?->toIso8601String();
        $row['kot_print_count']  = $o->kot_print_count;
        $row['total_tax_amount'] = (float) ($o->total_tax_amount ?? 0);

        $row['items'] = $o->details->map(fn ($d) => $this->shapeItem($d))->values()->all();

        $row['payments'] = $o->order_partial_payments->map(fn ($p) => [
            'id'         => $p->id,
            'paid_with'  => $p->paid_with,
            'paid_amount'=> (float) $p->paid_amount,
            'due_amount' => (float) $p->due_amount,
            'created_at' => $p->created_at?->toIso8601String(),
        ])->values()->all();

        return $row;
    }

    private function shapeItem($d): array
    {
        $product = is_array($d->product_details)
            ? $d->product_details
            : (json_decode($d->product_details, true) ?: []);
        $variations = is_array($d->variation)
            ? $d->variation
            : (json_decode($d->variation, true) ?: []);
        $addonIds  = is_array($d->add_on_ids)  ? $d->add_on_ids  : (json_decode($d->add_on_ids, true)  ?: []);
        $addonQtys = is_array($d->add_on_qtys) ? $d->add_on_qtys : (json_decode($d->add_on_qtys, true) ?: []);

        // Shape variations like the kitchen view does — single-line summaries
        $variationSummary = [];
        foreach ($variations as $v) {
            if (!is_array($v)) continue;
            $name  = $v['name'] ?? null;
            $value = $v['value'] ?? '';
            if ($value === '' && !empty($v['values']) && is_array($v['values'])) {
                $value = collect($v['values'])
                    ->map(fn ($x) => is_array($x) ? ($x['label'] ?? $x['level'] ?? $x['name'] ?? '') : (string) $x)
                    ->filter()
                    ->implode(', ');
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
                'id'   => (int) $aid,
                'name' => $name,
                'qty'  => (int) ($addonQtys[$i] ?? 1),
            ];
        }

        return [
            'detail_id'  => $d->id,
            'product_id' => $d->product_id,
            'name'       => $product['name'] ?? 'Item',
            'image'      => $product['image'] ?? null,
            'quantity'   => $d->quantity,
            'unit_price' => (float) $d->price,
            'tax_amount' => (float) ($d->tax_amount ?? 0),
            'discount'   => (float) ($d->discount_on_product ?? 0),
            'line_total' => (float) ($d->price * $d->quantity + ($d->add_on_tax_amount ?? 0)),
            'variation_summary' => implode(' · ', $variationSummary),
            'addons'     => $addons,
            'note'       => $product['line_note'] ?? null,
            'created_at' => $d->created_at?->toIso8601String(),
        ];
    }
}
