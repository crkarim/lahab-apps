<?php

namespace App\Http\Controllers\Api\V1\Waiter;

use App\Http\Controllers\Controller;
use App\Model\Order;
use App\Models\CashHandover;
use App\Models\OrderPartialPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Waiter app surface for the cash handover lifecycle.
 *
 * `index()` returns the live "drawer" — money the waiter has collected
 * but hasn't yet handed to the cashier. Only cash + tip amounts are
 * counted; card / bKash settle through the gateway and don't pass
 * through the waiter's hands physically.
 *
 * `submit()` packages everything currently in the drawer into a single
 * pending handover. The cashier then ack's it via the admin panel.
 */
class WaiterHandoverController extends Controller
{
    /** GET /api/v1/waiter/handover — current drawer state. */
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user('waiter_api');
        if (!$admin || !$admin->branch_id) {
            return response()->json([
                'errors' => [['code' => 'no_branch', 'message' => 'Your account is not assigned to a branch yet.']],
            ], 403);
        }

        // Drawer = sum of unhandover'd cash partial-payment rows. The
        // cash row's `paid_amount` already includes whatever portion of
        // the tip the customer paid in cash, so we DO NOT add
        // `orders.tip_amount` again — that would double-count for
        // cash-paid orders and over-count for card-paid orders (where
        // the tip went to the gateway, not to the waiter's hand).
        $rows = OrderPartialPayment::query()
            ->whereNull('handover_id')
            ->where('paid_with', 'cash')
            ->whereHas('order', function ($q) use ($admin) {
                $q->where('branch_id', $admin->branch_id)
                  ->where('placed_by_admin_id', $admin->id);
            })
            ->with(['order:id,kot_number,tip_amount,placed_by_admin_id,branch_id'])
            ->get();

        $cashTotal  = (float) $rows->sum('paid_amount');
        $orderCount = $rows->groupBy('order_id')->count();

        // Tips earned — informational only, NOT added to the handover
        // total. Counted across ALL of today's paid orders this waiter
        // placed (regardless of how the customer paid). This is the
        // waiter's "tips earned today" metric, independent of what
        // they're physically holding for the cashier.
        $tipsToday = (float) Order::query()
            ->where('branch_id', $admin->branch_id)
            ->where('placed_by_admin_id', $admin->id)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->sum('tip_amount');

        // Last handover (so the device can show "submitted N min ago — pending cashier")
        $pending = CashHandover::query()
            ->where('waiter_id', $admin->id)
            ->where('status', 'pending')
            ->orderByDesc('submitted_at')
            ->first();

        return response()->json([
            'drawer' => [
                'order_count' => $orderCount,
                // Physical cash to hand over to the cashier.
                'cash'        => $cashTotal,
                // Tips earned today — informational, NOT part of the
                // handover total. Tips paid in cash are already in the
                // `cash` figure above; tips paid by card went to the
                // gateway and never reach the waiter's hand.
                'tips_today'  => $tipsToday,
                // Backwards-compat: kept as the cash figure so older
                // device builds don't crash. New screen reads `cash`
                // directly.
                'total'       => $cashTotal,
            ],
            'pending_handover' => $pending ? [
                'id'              => $pending->id,
                'submitted_at'    => $pending->submitted_at?->toIso8601String(),
                'submitted_human' => $pending->submitted_at?->diffForHumans(),
                'total'           => (float) $pending->total,
            ] : null,
        ]);
    }

    /** POST /api/v1/waiter/handover — submit drawer → pending row. */
    public function submit(Request $request): JsonResponse
    {
        $admin = $request->user('waiter_api');
        if (!$admin || !$admin->branch_id) {
            return response()->json([
                'errors' => [['code' => 'no_branch', 'message' => 'No branch.']],
            ], 403);
        }

        $note = trim((string) $request->input('notes', ''));
        $handover = DB::transaction(function () use ($admin, $note) {
            // Lock the eligible rows for this waiter before mutating.
            $rows = OrderPartialPayment::query()
                ->whereNull('handover_id')
                ->where('paid_with', 'cash')
                ->whereHas('order', function ($q) use ($admin) {
                    $q->where('branch_id', $admin->branch_id)
                      ->where('placed_by_admin_id', $admin->id);
                })
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                return null;
            }

            $cashTotal = (float) $rows->sum('paid_amount');
            $orderIds  = $rows->pluck('order_id')->unique();
            // Tip metadata — what portion of the cash being handed
            // over represents tips on these orders. Used purely for
            // reporting (cashier sees "৳500 cash, of which ৳88 is tip").
            // NOT added to total_cash; the cash sum is the truth, tips
            // are a slice of it.
            $tipMetadata = (float) Order::whereIn('id', $orderIds)->sum('tip_amount');

            $handover = CashHandover::create([
                'waiter_id'    => $admin->id,
                'branch_id'    => $admin->branch_id,
                'submitted_at' => now(),
                'total_cash'   => $cashTotal,
                // total_tips is now an informational slice of total_cash,
                // NOT additive. The handover total = total_cash. Reports
                // can subtract tips from cash to show "bill cash" vs
                // "tip cash" if needed.
                'total_tips'   => $tipMetadata,
                'status'       => 'pending',
                'notes'        => $note !== '' ? $note : null,
            ]);

            OrderPartialPayment::whereIn('id', $rows->pluck('id'))
                ->update(['handover_id' => $handover->id]);

            return $handover;
        });

        if (!$handover) {
            return response()->json([
                'errors' => [['code' => 'empty_drawer', 'message' => 'Drawer is empty — nothing to hand over.']],
            ], 422);
        }

        return response()->json([
            'success'        => true,
            'handover_id'    => $handover->id,
            'total_cash'     => (float) $handover->total_cash,
            'total_tips'     => (float) $handover->total_tips,
            'total'          => (float) $handover->total,
            'submitted_at'   => $handover->submitted_at?->toIso8601String(),
            'message'        => 'Drawer handed over · awaiting cashier acknowledgement.',
        ]);
    }
}
