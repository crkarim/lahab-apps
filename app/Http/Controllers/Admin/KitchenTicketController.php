<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Notification;
use App\Model\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KitchenTicketController extends Controller
{
    /**
     * GET /admin/orders/{id}/kitchen-ticket
     *
     * First call per order: assigns KOT number (DD-MM-NNN, per branch per day),
     * flips order_status confirmed -> cooking, fires FCM push to kitchen-{branch}.
     * Subsequent calls: re-prints the same KOT, marked "REPRINT".
     *
     * Always renders the KOT with auto-print JS, intended to be opened in a new tab.
     */
    public function send(Request $request, $id): View
    {
        $order = Order::with(['details', 'customer', 'branch', 'table', 'placedBy.role', 'order_partial_payments'])
            ->findOrFail($id);

        // Supplementary print for an "add-on round" — caller supplies ?after_id=X
        // and the ticket shows ONLY details with id > X, so the kitchen doesn't
        // re-fire items already cooking. We skip KOT-number assignment, status
        // flip, and FCM push for supplementary prints — those are first-round
        // concerns; subsequent rounds piggy-back on the same active ticket.
        $afterId = $request->query('after_id');
        $afterId = is_numeric($afterId) ? (int) $afterId : null;
        $isSupplementary = $afterId !== null;

        $isReprint = !empty($order->kot_number);

        if (!$isReprint && !$isSupplementary) {
            DB::transaction(function () use ($order) {
                $order->kot_number  = $this->nextKotNumber($order->branch_id);
                $order->kot_sent_at = now();
                if ($order->order_status === 'confirmed') {
                    $order->order_status = 'cooking';
                }
                // Orders that print a customer receipt alongside the KOT (delivery + take-away/pos)
                // need a receipt_token minted here so the receipt's verify link works
                // even if checkout hasn't happened yet.
                if (in_array($order->order_type, ['delivery', 'pos'], true) && empty($order->receipt_token)) {
                    $order->receipt_token = Str::random(32);
                }
                $order->save();
            });

            $this->notifyKitchen($order);
        }

        if ($isSupplementary) {
            $this->notifyKitchen($order);
        }

        $order->increment('kot_print_count');

        return view('admin-views.order.kot', [
            'order'            => $order->refresh(),
            'isReprint'        => $isReprint && !$isSupplementary,
            'isSupplementary'  => $isSupplementary,
            'afterId'          => $afterId,
        ]);
    }

    /**
     * Branch + day scoped counter: DD-MM-NNN.
     * Atomic enough under single-queue POS traffic; for heavy concurrency add
     * SELECT ... FOR UPDATE on a dedicated counter table.
     */
    private function nextKotNumber(int $branchId): string
    {
        $prefix = now()->format('d-m') . '-';

        $lastKot = Order::where('branch_id', $branchId)
            ->whereDate('kot_sent_at', now()->toDateString())
            ->where('kot_number', 'like', $prefix . '%')
            ->orderByDesc('kot_number')
            ->value('kot_number');

        $next = 1;
        if ($lastKot) {
            $parts = explode('-', $lastKot);
            $next  = (int) end($parts) + 1;
        }

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function notifyKitchen(Order $order): void
    {
        try {
            $notification = new Notification();
            $notification->title        = "New KOT {$order->kot_number}";
            $notification->description  = (string) $order->id;
            $notification->status       = 1;
            $notification->order_id     = $order->id;
            $notification->order_status = $order->order_status;

            Helpers::send_push_notif_to_topic(
                data: $notification,
                topic: "kitchen-{$order->branch_id}",
                type: 'general',
                isNotificationPayloadRemove: true
            );
        } catch (\Throwable $e) {
            // Intentionally swallow — KOT print must succeed even if FCM is not configured.
        }
    }
}
