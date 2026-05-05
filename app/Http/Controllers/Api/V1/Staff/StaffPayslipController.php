<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Salary statement API for the My Lahab staff app:
 *
 *   GET /api/v1/staff/payslips         — my payslip list (latest first)
 *   GET /api/v1/staff/payslips/{id}    — line items + totals for one slip
 *
 * Only locked / paid runs are surfaced — drafts are still being shaped
 * by the office and would be misleading to show. Read-only; payment
 * state is set by the admin payroll workflow, never by the staff app.
 */
class StaffPayslipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admin = auth('staff_api')->user();
        $perPage = (int) min(36, max(3, (int) $request->query('per_page', 12)));

        $page = Payslip::query()
            ->with('run:id,period_from,period_to,status')
            ->where('admin_id', $admin->id)
            ->whereHas('run', fn ($q) => $q->whereIn('status', ['locked', 'paid']))
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Payslip $p) => [
                'id'              => $p->id,
                'period_from'     => optional($p->run?->period_from)->toDateString(),
                'period_to'       => optional($p->run?->period_to)->toDateString(),
                'run_status'      => $p->run?->status,
                'days_clocked'    => (int) $p->days_clocked,
                'gross'           => (float) $p->gross,
                'net'             => (float) $p->net,
                'paid_at'         => optional($p->paid_at)?->toIso8601String(),
                'paid_method'     => $p->paid_method,
                'paid_reference'  => $p->paid_reference,
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $admin = auth('staff_api')->user();
        $slip = Payslip::query()
            ->with('run:id,period_from,period_to,status')
            ->where('admin_id', $admin->id)
            ->find($id);

        if (! $slip) {
            return response()->json(['errors' => [['code' => 'not-found', 'message' => 'Payslip not found.']]], 404);
        }
        if (! in_array($slip->run?->status, ['locked', 'paid'], true)) {
            return response()->json(['errors' => [['code' => 'not-ready', 'message' => 'Payslip not finalised yet.']]], 422);
        }

        // line_items_json is the canonical breakdown computed at run time
        // (basic + allowances - deductions + tip share - advance recovery).
        $lines = is_array($slip->line_items_json) ? $slip->line_items_json : [];

        return response()->json([
            'data' => [
                'id'                 => $slip->id,
                'period_from'        => optional($slip->run?->period_from)->toDateString(),
                'period_to'          => optional($slip->run?->period_to)->toDateString(),
                'run_status'         => $slip->run?->status,
                'days_clocked'       => (int) $slip->days_clocked,
                'calendar_days'      => (int) $slip->calendar_days,
                'attendance_minutes' => (int) $slip->attendance_minutes,
                'prorated_basic'     => (float) $slip->prorated_basic,
                'prorated_allowance' => (float) $slip->prorated_allowance,
                'prorated_deduction' => (float) $slip->prorated_deduction,
                'tip_share'          => (float) $slip->tip_share,
                'advance_recovery'   => (float) $slip->advance_recovery,
                'gross'              => (float) $slip->gross,
                'net'                => (float) $slip->net,
                'line_items'         => $lines,
                'paid_at'            => optional($slip->paid_at)?->toIso8601String(),
                'paid_method'        => $slip->paid_method,
                'paid_reference'     => $slip->paid_reference,
                'notes'              => $slip->notes,
            ],
        ]);
    }
}
