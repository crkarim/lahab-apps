<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\Order;
use App\Services\Printer\KitchenPrinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin-side escalation surface for KOT print failures. The admin layout
 * polls `/admin/print-failures/pending` every few seconds; when the list
 * is non-empty, a sticky urgent modal pops up with a Print/Mark-handled
 * pair of actions. Stays up across multiple admin sessions until acted
 * on — first to act wins, others auto-dismiss on the next poll.
 *
 * Branch-scoping: HQ admins (admins.branch_id IS NULL) see every branch;
 * branch-scoped admins see only their own.
 */
class PrintFailureController extends Controller
{
    public function pending(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $rows = Order::query()
            ->whereNotNull('print_failure_at')
            ->whereNull('print_failure_handled_at')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['branch:id,name', 'table:id,number,zone', 'placedBy:id,f_name,l_name', 'customer:id,f_name,l_name'])
            ->orderByDesc('print_failure_at')
            ->limit(20)
            ->get();

        return response()->json([
            'count'    => $rows->count(),
            'failures' => $rows->map(fn (Order $o) => $this->shape($o))->values(),
        ]);
    }

    /** Retry the network print for one specific failure. */
    public function retry(Request $request, int $id): JsonResponse
    {
        $order = $this->findScoped($request, $id);
        if (!$order) return $this->notFound();

        try {
            $result = (new KitchenPrinter())->printOrder($order, isReprint: true);
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'skipped' => false, 'error' => $e->getMessage()];
        }

        if ($result['ok']) {
            $order->update([
                'print_failure_handled_at' => now(),
                'print_failure_handled_by' => auth('admin')->user()->id,
                'print_failure_reason'     => null,
            ]);
            return response()->json([
                'ok'      => true,
                'message' => 'Reprint sent to the kitchen.',
            ]);
        }

        // Update the latest reason so the admin sees fresh diagnostic.
        $order->forceFill([
            'print_failure_reason' => substr((string) $result['error'], 0, 250),
            'print_failure_at'     => now(),
        ])->save();

        return response()->json([
            'ok'      => false,
            'skipped' => $result['skipped'],
            'error'   => $result['error'],
            'message' => $result['skipped']
                ? 'Network printer is disabled — open the kitchen ticket URL.'
                : 'Could not reach the printer.',
        ], 200);
    }

    /**
     * Browser-native print acknowledgement. The bottom-sheet's primary
     * action opens the KOT URL in a new window — that page auto-fires
     * `window.print()`, so by the time this endpoint is hit the operator
     * has already seen the print dialog. We trust the click: clear the
     * failure stamp + record the native-print audit timestamp so the
     * waiter app can drop the PRINTER OFFLINE pill.
     */
    public function ackPrinted(Request $request, int $id): JsonResponse
    {
        $order = $this->findScoped($request, $id);
        if (!$order) return $this->notFound();

        $adminId = auth('admin')->user()->id;
        $order->update([
            'print_failure_handled_at' => now(),
            'print_failure_handled_by' => $adminId,
            'print_failure_reason'     => null,
            'kot_native_printed_at'    => now(),
            'kot_native_printed_by'    => $adminId,
        ]);
        $order->increment('kot_print_count');

        return response()->json([
            'ok'      => true,
            'message' => 'KOT acknowledged — kitchen will see it now.',
        ]);
    }

    /** Operator wrote it on paper — mark this failure handled. */
    public function markHandled(Request $request, int $id): JsonResponse
    {
        $order = $this->findScoped($request, $id);
        if (!$order) return $this->notFound();

        $order->update([
            'print_failure_handled_at' => now(),
            'print_failure_handled_by' => auth('admin')->user()->id,
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Marked handled.',
        ]);
    }

    private function findScoped(Request $request, int $id): ?Order
    {
        $admin = auth('admin')->user();
        return Order::query()
            ->where('id', $id)
            ->whereNotNull('print_failure_at')
            ->whereNull('print_failure_handled_at')
            ->when($admin?->branch_id, fn ($q, $b) => $q->where('branch_id', $b))
            ->first();
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'ok'      => false,
            'message' => 'Failure already handled or out of scope.',
        ], 404);
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
            'id'              => $o->id,
            'kot_number'      => $o->kot_number,
            'order_type'      => $o->order_type,
            'order_amount'    => (float) $o->order_amount,
            'failed_at'       => $o->print_failure_at?->toIso8601String(),
            'failed_human'    => $o->print_failure_at?->diffForHumans(),
            'reason'          => $o->print_failure_reason,
            'branch'          => $o->branch?->name,
            'table_number'    => $o->table?->number,
            'table_zone'      => $o->table?->zone,
            'placed_by'       => $placedBy,
            'customer'        => $customer ?: ($o->is_guest ? 'Walk-in' : null),
            'kitchen_ticket_url' => url("/admin/orders/{$o->id}/kitchen-ticket"),
        ];
    }
}
