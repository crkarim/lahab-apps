<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Notification;
use App\Model\Order;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Kitchen-side surface for the "scan-to-mark-ready" workflow.
 *
 * The kitchen has no app screen — just a USB keyboard-wedge scanner
 * pointed at the printed KOT's barcode (CODE128 of `kot_number`, set
 * by KitchenPrinter / KotPaperStripView). Scanning the paper types
 * the KOT number + Enter into a hidden input on this page; the page
 * fetches `POST /admin/kitchen/scan`; the order flips to `ready` and
 * a push notification fires to the placing waiter's branch topic.
 *
 * Why a single page instead of one-scan-per-station: cheaper (no
 * native app on a kitchen tablet, no Bluetooth hassle, no Android
 * version mismatch). Any old browser + cheap scanner = working
 * "ready" station.
 */
class KitchenScanController extends Controller
{
    /** Render the scan page (full-screen, dark, scanner-friendly). */
    public function index(): Renderable
    {
        return view('admin-views.kitchen.scan');
    }

    /**
     * POST /admin/kitchen/scan — accepts `{kot_number}`, marks the
     * matching order ready, fires FCM. Returns JSON for the page's
     * scan-history strip.
     */
    public function scan(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $validated = $request->validate([
            'kot_number' => 'required|string|max:20',
        ]);
        $kot = trim($validated['kot_number']);

        $order = Order::query()
            ->where('kot_number', $kot)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['placedBy:id,f_name,l_name', 'table:id,number,zone', 'customer:id,f_name,l_name'])
            ->first();

        if (!$order) {
            return response()->json([
                'ok'      => false,
                'code'    => 'not_found',
                'message' => "KOT $kot not found in your branch.",
            ], 404);
        }

        $terminal = ['completed', 'delivered', 'canceled', 'failed', 'refunded', 'refund_requested'];
        if (in_array($order->order_status, $terminal, true)) {
            return response()->json([
                'ok'      => false,
                'code'    => 'already_closed',
                'message' => "KOT $kot is already closed (status: {$order->order_status}).",
                'order'   => $this->shape($order),
            ], 422);
        }

        // If already ready, return idempotently — same paper might get
        // scanned twice (operator double-press on the trigger).
        if ($order->order_status === 'ready') {
            return response()->json([
                'ok'      => true,
                'code'    => 'already_ready',
                'message' => "KOT $kot was already marked ready.",
                'order'   => $this->shape($order),
            ]);
        }

        $order->forceFill([
            'order_status'      => 'ready',
            'ready_at'          => now(),
            'ready_by_admin_id' => $admin?->id,
        ])->save();

        $this->notifyWaiter($order);

        return response()->json([
            'ok'      => true,
            'code'    => 'ready',
            'message' => "KOT $kot ready · " . ($this->shape($order)['placed_by'] ?? 'waiter') . " notified.",
            'order'   => $this->shape($order),
        ]);
    }

    /**
     * Push to `waiter-{branchId}` topic. All waiters in the branch
     * subscribe; the Flutter handler highlights the row prominently
     * for the placing waiter and surfaces it as an info ping for
     * everyone else (useful when the placing waiter is on break).
     */
    private function notifyWaiter(Order $order): void
    {
        try {
            $tableLabel = $order->table?->number
                ? 'Table ' . $order->table->number
                : 'Take-away';
            $title = "KOT {$order->kot_number} ready";
            $body  = "$tableLabel · pick up from kitchen";

            $n = new Notification();
            $n->title        = $title;
            $n->description  = $body;
            $n->status       = 1;
            $n->order_id     = $order->id;
            $n->order_status = 'ready';

            // `type` becomes the Flutter handler's switch key. The
            // foreground listener routes 'order_ready' to a banner +
            // active-orders refresh; system tray shows the title/body.
            Helpers::send_push_notif_to_topic(
                data: $n,
                topic: "waiter-{$order->branch_id}",
                type: 'order_ready',
            );
        } catch (\Throwable $e) {
            // Best-effort — order is already marked ready in the DB.
            // The waiter app will pick it up on the next pull-to-refresh.
            Log::warning('Kitchen ready FCM error', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Live queue of orders currently cooking — feeds the scan page's
     * grid so the kitchen can see what they're waiting on. Includes
     * minimal shape (KOT + table + age + items count) and skips
     * non-cooking states because those don't belong on the pass.
     */
    public function cookingJson(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $orders = Order::query()
            ->where('order_status', 'cooking')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with([
                'table:id,number,zone',
                'placedBy:id,f_name,l_name',
                'customer:id,f_name,l_name',
                'details:id,order_id,quantity',
            ])
            ->orderBy('id')
            ->limit(80)
            ->get();

        $rows = $orders->map(function (Order $o) {
            $placedBy = $o->placedBy
                ? trim(($o->placedBy->f_name ?? '') . ' ' . ($o->placedBy->l_name ?? ''))
                : null;
            $customer = $o->customer
                ? trim(($o->customer->f_name ?? '') . ' ' . ($o->customer->l_name ?? ''))
                : null;
            $itemCount = (int) $o->details->sum('quantity');
            // Use raw timestamps because Carbon 2.62+ flipped diffInSeconds
            // to signed-by-default; max() then clamps a negative result
            // to zero and we lose all aging signal. Explicit math is
            // immune to that.
            $ageSeconds = $o->created_at ? max(0, now()->getTimestamp() - $o->created_at->getTimestamp()) : 0;
            return [
                'id'           => $o->id,
                'kot_number'   => $o->kot_number,
                'order_type'   => $o->order_type,
                'table_label'  => $o->order_type === 'pos' || $o->order_type === 'take_away'
                    ? 'Take-away'
                    : ($o->table?->number ? 'Table ' . $o->table->number : '—'),
                'table_zone'   => $o->table?->zone,
                'customer'     => $customer ?: ($o->is_guest ? 'Walk-in' : '—'),
                'placed_by'    => $placedBy ?: '—',
                'item_count'   => $itemCount,
                'age_seconds'  => $ageSeconds,
                'created_at'   => $o->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'count'  => $rows->count(),
            'orders' => $rows,
            'fetched_at' => now()->toIso8601String(),
        ]);
    }

    private function shape(Order $o): array
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
            'order_status' => $o->order_status,
            'order_type'   => $o->order_type,
            'table_number' => $o->table?->number,
            'customer'     => $customer ?: ($o->is_guest ? 'Walk-in' : null),
            'placed_by'    => $placedBy,
            'ready_at'     => $o->ready_at?->toIso8601String(),
        ];
    }
}
