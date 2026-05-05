<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Leave API for the My Lahab staff app:
 *
 *   GET  /api/v1/staff/leaves              — my leave history (paginated)
 *   POST /api/v1/staff/leaves              — file a new request (status=pending)
 *   POST /api/v1/staff/leaves/{id}/cancel  — withdraw a still-pending request
 *   GET  /api/v1/staff/leave-types         — picker options (paid + unpaid)
 *
 * All scoped to the authenticated admin so a staff member can never see
 * or affect another employee's record.
 */
class StaffLeaveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admin = auth('staff_api')->user();
        $perPage = (int) min(50, max(5, (int) $request->query('per_page', 20)));

        $page = LeaveRequest::query()
            ->with('type:id,name,code,color,is_paid')
            ->where('admin_id', $admin->id)
            ->orderByDesc('from_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn (LeaveRequest $l) => $this->payload($l))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    public function leaveTypes(): JsonResponse
    {
        $types = LeaveType::query()
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'days_per_year', 'is_paid', 'color']);
        return response()->json(['data' => $types]);
    }

    public function store(Request $request): JsonResponse
    {
        $admin = auth('staff_api')->user();

        $validator = Validator::make($request->all(), [
            'leave_type_id' => 'required|integer|exists:leave_types,id',
            'from_date'     => 'required|date',
            'to_date'       => 'required|date|after_or_equal:from_date',
            'reason'        => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $from = Carbon::parse($request->input('from_date'))->startOfDay();
        $to   = Carbon::parse($request->input('to_date'))->startOfDay();
        $days = $from->diffInDays($to) + 1; // inclusive of both ends

        // Block overlapping requests so the staff can't double-book the
        // same window. Cancelled / rejected don't count.
        $overlap = LeaveRequest::query()
            ->where('admin_id', $admin->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('from_date', [$from, $to])
                  ->orWhereBetween('to_date', [$from, $to])
                  ->orWhere(function ($q2) use ($from, $to) {
                      $q2->where('from_date', '<=', $from)
                         ->where('to_date',   '>=', $to);
                  });
            })
            ->exists();
        if ($overlap) {
            return response()->json([
                'errors' => [['code' => 'overlap', 'message' => 'You already have a request that covers some of these dates.']],
            ], 422);
        }

        $leave = LeaveRequest::create([
            'admin_id'      => $admin->id,
            'leave_type_id' => (int) $request->input('leave_type_id'),
            'branch_id'     => $admin->branch_id,
            'from_date'     => $from,
            'to_date'       => $to,
            'days'          => (int) $days,
            'reason'        => $request->input('reason'),
            'status'        => 'pending',
        ]);

        return response()->json([
            'message' => 'Leave request submitted.',
            'data'    => $this->payload($leave->fresh('type')),
        ], 201);
    }

    public function cancel(int $id): JsonResponse
    {
        $admin = auth('staff_api')->user();
        $leave = LeaveRequest::query()->where('admin_id', $admin->id)->find($id);
        if (! $leave) {
            return response()->json(['errors' => [['code' => 'not-found', 'message' => 'Leave request not found.']]], 404);
        }
        if ($leave->status !== 'pending') {
            return response()->json([
                'errors' => [['code' => 'not-pending', 'message' => 'Only pending requests can be cancelled.']],
            ], 422);
        }
        $leave->status = 'cancelled';
        $leave->save();
        return response()->json(['message' => 'Leave request cancelled.', 'data' => $this->payload($leave->fresh('type'))]);
    }

    private function payload(LeaveRequest $l): array
    {
        return [
            'id'           => $l->id,
            'from_date'    => optional($l->from_date)->toDateString(),
            'to_date'      => optional($l->to_date)->toDateString(),
            'days'         => (int) $l->days,
            'reason'       => $l->reason,
            'status'       => $l->status,
            'reviewed_at'  => optional($l->reviewed_at)?->toIso8601String(),
            'review_note'  => $l->review_note,
            'leave_type'   => $l->type ? [
                'id'      => $l->type->id,
                'name'    => $l->type->name,
                'code'    => $l->type->code,
                'is_paid' => (bool) $l->type->is_paid,
                'color'   => $l->type->color,
            ] : null,
        ];
    }
}
