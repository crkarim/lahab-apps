<?php

namespace App\CentralLogics;

use App\Model\Notification;
use App\Model\Order;
use App\Models\CashHandover;
use Illuminate\Support\Facades\Log;

/**
 * Centralised FCM dispatch for waiter-app push events.
 *
 * Each method is intentionally a thin builder + topic call so that
 * every event the waiter app reacts to is documented in one place.
 * The Flutter handler (notification_helper.dart) switches on the
 * `type` string set here — keep them in sync.
 *
 * Topic conventions (subscribed on login by auth_controller.dart):
 *   - `waiter-{branchId}`    : whole-branch broadcast (ready alerts)
 *   - `waiter-self-{adminId}`: per-waiter (cancellations, cash events)
 *
 * All sends are best-effort. We never let a push failure rollback the
 * underlying business action — the order is still ready / the cash is
 * still received even if Google's edge is unreachable.
 */
class WaiterPushHelper
{
    /** Whole branch — kitchen flipped a KOT to ready, any waiter on the floor cares. */
    public static function pushOrderReady(Order $order): void
    {
        $tableLabel = $order->table?->number
            ? 'Table ' . $order->table->number
            : 'Take-away';
        self::dispatch(
            topic: "waiter-{$order->branch_id}",
            type: 'order_ready',
            title: "KOT {$order->kot_number} ready",
            body: "$tableLabel · pick up from kitchen",
            orderId: $order->id,
            orderStatus: 'ready',
        );
    }

    /** Per-waiter — admin canceled the order they placed; they likely told the customer it was coming. */
    public static function pushOrderCanceled(Order $order): void
    {
        $waiterId = $order->placed_by_admin_id;
        if (!$waiterId) return; // Walk-in / system-placed; no one to ping.

        $tableLabel = $order->table?->number
            ? 'Table ' . $order->table->number
            : 'Take-away';
        self::dispatch(
            topic: "waiter-self-{$waiterId}",
            type: 'order_canceled',
            title: "Order canceled · KOT {$order->kot_number}",
            body: "$tableLabel · admin reverted this order",
            orderId: $order->id,
            orderStatus: 'canceled',
        );
    }

    /** Per-waiter — cashier confirmed receipt of the cash the waiter submitted. */
    public static function pushHandoverReceived(CashHandover $handover): void
    {
        if (!$handover->waiter_id) return;
        $amount = number_format((float) $handover->total_cash, 0);
        // `order_id` field doubles as the subject id for non-order
        // events; Flutter switches on `type` to interpret it.
        self::dispatch(
            topic: "waiter-self-{$handover->waiter_id}",
            type: 'handover_received',
            title: "Cash settled · ৳{$amount}",
            body: "Cashier confirmed your handover. Drawer cleared.",
            subjectId: $handover->id,
        );
    }

    /** Per-waiter — cashier pulled the cash directly without waiting for a submit. */
    public static function pushHandoverCollected(CashHandover $handover): void
    {
        if (!$handover->waiter_id) return;
        $amount = number_format((float) $handover->total_cash, 0);
        self::dispatch(
            topic: "waiter-self-{$handover->waiter_id}",
            type: 'handover_collected',
            title: "Cashier collected ৳{$amount}",
            body: "All your cash was added to the drawer just now.",
            subjectId: $handover->id,
        );
    }

    /** Build the Notification stub + topic call. Swallows transport errors. */
    private static function dispatch(
        string $topic,
        string $type,
        string $title,
        string $body,
        ?int $orderId = null,
        ?int $subjectId = null,
        string $orderStatus = '',
    ): void {
        try {
            $n = new Notification();
            $n->title        = $title;
            $n->description  = $body;
            $n->status       = 1;
            // Polymorphic id: real order id for order events,
            // handover id for cash events. Flutter routes based on type.
            $n->order_id     = $orderId ?? $subjectId ?? 0;
            $n->order_status = $orderStatus;

            Helpers::send_push_notif_to_topic(data: $n, topic: $topic, type: $type);
        } catch (\Throwable $e) {
            Log::warning('WaiterPushHelper dispatch failed', [
                'topic' => $topic,
                'type'  => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
